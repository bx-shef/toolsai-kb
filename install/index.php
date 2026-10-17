<?php

use Bitrix\Main\Application;
use Bitrix\Main\Config;
use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Result;

if(class_exists('shef_toolsaikb'))
{
	return;
}

Loc::loadMessages(__FILE__);

/**
 * Установка shef.toolsaikb.
 *
 * Канон — установщик shef.toolsai (проверки UTF-8, PHP, модулей и версий,
 * savedata, прерывание только через ShowForm). Что ставится:
 *
 *   1) таблица соответствий статей и документов (Model\MappingTable);
 *   2) агент «Обновить статьи» раз в сутки (Agent\Refresh);
 *   2а) подписки из installEvents (.settings.php, 1.1.2) — пункт в меню
 *      шестерёнки вида «ИИ»; снимает удаление;
 *   3) первый импорт (Import\Runner): раздел «ИИ: База знаний» и статьи модулей,
 *      которые уже отвечают на OnKbSourcesCollect.
 *
 * `note` («База знаний» ядра) проверяется здесь, а не в requireModules
 * (.settings.php): как ai и crm у shef.toolsai.
 *
 * Удаление: агент, блокировки, настройки (без savedata). Раздел и документы
 * в «Базе знаний» остаются — это содержимое портала, их могли поправить
 * люди; вместе с ними остаются ID раздела и таблица соответствий, чтобы
 * переустановка продолжила с тех же документов, а не плодила копии.
 */
class shef_toolsaikb
	extends CModule
{
	public $MODULE_ID = 'shef.toolsaikb';
	public $MODULE_VERSION;
	public $MODULE_VERSION_DATE;
	public $MODULE_NAME;
	public $MODULE_DESCRIPTION;
	public $MODULE_SORT;
	public $MODULE_GROUP_RIGHTS = 'N';

	public $PARTNER_NAME;
	public $PARTNER_URI;

	/** @var string */
	public $PHP_MIN_VER = '8.2.0';
	/** @var string */
	public $NEED_MAIN_VERSION = '22.600.300';
	/** @var array */
	public $NEED_MODULES = [];
	/**
	 * shef.options 3.0.0 — страница настроек на ShOptionsConfig;
	 * shef.problems 2.0.0 — логгер проблем;
	 * note 26.250.0 — публичный API `Bitrix\Note\Public\…`, с которым сверен модуль.
	 *
	 * @var array
	 */
	public $NEED_MODULES_BY_VERSION = [
		'shef.options' => '3.0.0',
		'shef.problems' => '2.0.0',
		'note' => '26.250.0',
	];

	/** Constants::LOCK_GROUP литералом: при удалении автозагрузки модуля уже нет. */
	private const LOCK_GROUP = 'shef.toolsaikb.import';
	/** Constants::AGENT_NAME, AGENT_INTERVAL литералами — по той же причине. */
	private const AGENT_NAME = '\\Shef\\ToolsAiKb\\Agent\\Refresh::run();';
	private const AGENT_INTERVAL = 86400;

	/**
	 * Настройки, которые удаление без savedata стирает. SYS_collectionid
	 * (ID раздела) — не здесь: см. UnInstallDB().
	 */
	private const OPTIONS_TO_DELETE = ['KB_collectionname', 'KB_agent', 'KB_searchall', 'SYS_lastreport', 'SYS_eventsversion'];

	/** @var \CMain */
	private $application;

	/** Итог первого импорта — показывается в форме после установки. */
	private string $importReport = '';

	public function __construct()
	{
		$arModuleVersion = [];

		require __DIR__.'/version.php';

		$this->MODULE_VERSION = $arModuleVersion['VERSION'];
		$this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];

		$this->MODULE_NAME = Loc::getMessage('shef.toolsaikb_MODULE_NAME');
		$this->MODULE_DESCRIPTION = Loc::getMessage('shef.toolsaikb_MODULE_DESC');

		$sort = 'MODULE_SORT';
		$this->{$sort} = (int)'1007';

		$this->PARTNER_NAME = Loc::getMessage('shef.toolsaikb_PARTNER_NAME');
		$this->PARTNER_URI = Loc::getMessage('shef.toolsaikb_PARTNER_URI');

		$this->application = $GLOBALS['APPLICATION'];
	}

	// region DB && Register Module ////
	/**
	 * Регистрация модуля и таблица соответствий.
	 *
	 * @memo Need call first
	 */
	public function InstallDB(array $arParams = []): bool
	{
		RegisterModule($this->MODULE_ID);

		\Bitrix\Main\Loader::includeModule($this->MODULE_ID);

		\Shef\ToolsAiKb\Model\MappingTable::init();

		return true;
	}

	/**
	 * Снятие регистрации.
	 *
	 * Раздел и документы в «Базе знаний» остаются после удаления — это
	 * содержимое портала. Поэтому всегда остаются и сведения о них: ID раздела
	 * (SYS_collectionid) и таблица соответствий. Иначе переустановка создала
	 * бы второй раздел и копии всех статей.
	 *
	 * @param array $arParams ключ savedata = 'Y' оставляет и остальные
	 *                        настройки; без него удаляются настройки, кроме ID раздела
	 * @memo Need call last
	 */
	public function UnInstallDB(array $arParams = []): bool
	{
		$isSaveData = ($arParams['savedata'] ?? 'N') === 'Y';

		if(!$isSaveData)
		{
			foreach(self::OPTIONS_TO_DELETE as $name)
			{
				Config\Option::delete($this->MODULE_ID, ['name' => $name]);
			}
		}

		UnRegisterModule($this->MODULE_ID);

		$GLOBALS['CACHE_MANAGER']->CleanAll();

		return true;
	}
	// endregion ////

	// region Events ////
	/**
	 * Постоянные подписки из installEvents (.settings.php).
	 *
	 * @return list<array>
	 */
	private function getEventsList(): array
	{
		$list = Config\Configuration::getInstance($this->MODULE_ID)->get('installEvents');

		return is_array($list) ? $list : [];
	}

	public function InstallEvents(): bool
	{
		$eventManager = \Bitrix\Main\EventManager::getInstance();
		foreach($this->getEventsList() as $event)
		{
			$method = !empty($event['isCompatible']) ? 'registerEventHandlerCompatible' : 'registerEventHandler';
			$eventManager->{$method}(
				$event['from']['module'],
				$event['from']['event'],
				$event['to']['module'],
				$event['to']['class'],
				$event['to']['function'],
				(int)($event['to']['sort'] ?? 100)
			);
		}

		return true;
	}

	/** Снять те же подписки: класс — с «\\» и без (ядро хранит как передали). */
	public function UnInstallEvents(): bool
	{
		$eventManager = \Bitrix\Main\EventManager::getInstance();
		foreach($this->getEventsList() as $event)
		{
			foreach(array_unique([$event['to']['class'], ltrim($event['to']['class'], '\\')]) as $class)
			{
				$eventManager->unRegisterEventHandler(
					$event['from']['module'],
					$event['from']['event'],
					$event['to']['module'],
					$class,
					$event['to']['function']
				);
			}
		}

		return true;
	}
	// endregion ////

	// region Агент и первый импорт ////
	/**
	 * Агент раз в сутки и первый импорт. Сбой импорта установку не роняет:
	 * итог — в форме, статьи обновит агент или кнопка.
	 */
	public function InstallAgent(): bool
	{
		\CAgent::RemoveAgent(self::AGENT_NAME, $this->MODULE_ID);
		\CAgent::AddAgent(self::AGENT_NAME, $this->MODULE_ID, 'N', self::AGENT_INTERVAL);

		try
		{
			$report = \Shef\ToolsAiKb\Import\Runner::run();
			$this->importReport = $report === null
				? (string)Loc::getMessage('SH_TOOLSAIKB_INSTALL_BUSY')
				: Loc::getMessage('SH_TOOLSAIKB_INSTALL_REPORT').' '.$report->summary()
					.($report->errors !== [] ? '<br>'.implode('<br>', array_map('htmlspecialcharsbx', $report->errors)) : '');
		}
		catch(\Throwable $throwable)
		{
			$this->importReport = htmlspecialcharsbx($throwable->getMessage());
		}

		return true;
	}

	/**
	 * Снять агент и блокировки импорта. Классы модуля не нужны: имена — литералами.
	 */
	public function UnInstallAgent(): bool
	{
		\CAgent::RemoveModuleAgents($this->MODULE_ID);

		// shef.options — чужой модуль, он остаётся; подключить явно.
		if(\Bitrix\Main\Loader::includeModule('shef.options'))
		{
			\Shef\Options\Main\TempFile\Pid::removeByGroup(self::LOCK_GROUP, 0);
			\Bitrix\Main\IO\Directory::deleteDirectory(
				\Shef\Options\Main\TempFile\Pid::getBasePath(self::LOCK_GROUP)
			);
		}

		return true;
	}
	// endregion ////

	// region Files ////
	/** Публичных файлов у модуля нет. */
	public function InstallFiles(array $arParams = []): bool
	{
		return true;
	}

	/**
	 * Файлов нет; здесь — проверка, что модуль не нужен другим модулям линейки
	 * (их requireModules).
	 */
	public function UnInstallFiles(): bool
	{
		$response = $this->checkChildModules();

		if(!$response->isSuccess())
		{
			$this->application->ThrowException(
				join(PHP_EOL.'<br>',
					array_merge(
						[Loc::getMessage('SH_PROBLEM_UNINSTALL_MODULE')],
						$response->getErrorMessages()
					)
				)
			);

			return false;
		}

		return true;
	}
	// endregion ////

	// region Install.Public ////
	public function DoInstall(): void
	{
		// Модуль поставляется в UTF-8 и не перекодируется: на CP1251 не ставимся.
		if(!Application::isUtfMode())
		{
			$this->ShowForm('ERROR', Loc::getMessage('SH_NEED_UTF8'));
		}

		$phpVer = phpversion();

		if(version_compare($phpVer, $this->PHP_MIN_VER, '<'))
		{
			$this->ShowForm(
				'ERROR',
				Loc::getMessage('SH_NEED_PHP_VER', [
					'#CURRENT#' => $phpVer,
					'#NEED#' => $this->PHP_MIN_VER,
				])
			);
		}

		foreach($this->NEED_MODULES as $module)
		{
			if(!ModuleManager::isModuleInstalled($module))
			{
				$this->ShowForm('ERROR', Loc::getMessage('SH_NEED_MODULES', ['#NEED#' => $module]));
			}
		}

		foreach($this->NEED_MODULES_BY_VERSION as $module => $ver)
		{
			if(
				!ModuleManager::isModuleInstalled($module)
				|| version_compare((string)ModuleManager::getVersion($module), $ver) < 0
			)
			{
				$this->ShowForm(
					'ERROR',
					Loc::getMessage('SH_NEED_MODULES_BY_VERSION', [
						'#URL#' => (strpos($module, '.') === false
							? 'https://www.1c-bitrix.ru/products/cms/versions.php?module='.$module
							: 'https://marketplace.1c-bitrix.ru/solutions/'.$module.'/'
						),
						'#NEED#' => $module,
						'#VER#' => $ver,
					])
				);
			}
		}

		if(
			mb_strlen($this->NEED_MAIN_VERSION) <= 0
			|| version_compare(SM_VERSION, $this->NEED_MAIN_VERSION) >= 0
		)
		{
			$this->InstallDB();
			$this->InstallFiles();
			$this->InstallEvents();
			$this->InstallAgent();

			$this->ShowForm(
				'OK',
				Loc::getMessage('SH_MOD_INST_OK').($this->importReport !== '' ? '<br><br>'.$this->importReport : '')
			);
		}
		else
		{
			$this->ShowForm(
				'ERROR',
				Loc::getMessage('SH_NEED_RIGHT_VER', ['#NEED#' => $this->NEED_MAIN_VERSION])
			);
		}
	}

	public function DoUninstall(): void
	{
		$response = $this->UnInstallFiles();
		if(!$response)
		{
			/** @var \CApplicationException $problem */
			$problem = $this->application->GetException();
			$this->ShowForm(
				'ERROR',
				(
					$problem instanceof \CApplicationException
					? $problem->GetString()
					: 'Error Uninstall'
				)
			);
		}
		$this->UnInstallAgent();
		$this->UnInstallEvents();
		$this->UnInstallDB();
	}

	private function ShowForm(
		string $type,
		string $message,
		string $buttonName = ''
	): void
	{
		/** @memo need for show title at prolog */
		$keys = array_keys($GLOBALS);
		for($i = 0; $i < count($keys); $i++)
		{
			if(
				$keys[$i] != 'i'
				&& $keys[$i] != 'GLOBALS'
				&& $keys[$i] != 'strTitle'
				&& $keys[$i] != 'filepath'
			)
			{
				global ${$keys[$i]};
			}
		}
		global $APPLICATION, $adminPage, $adminMenu, $adminChain, $USER;
		
		$this->application->SetTitle(
			Loc::getMessage('shef.toolsaikb_MODULE_NAME')
		);

		include(Application::getDocumentRoot().'/bitrix/modules/main/include/prolog_admin_after.php');

		\CAdminMessage::ShowMessage([
			'MESSAGE' => $message,
			'TYPE' => $type,
			'HTML' => true
		]);
		?>
		<form action="<?=$this->application->GetCurPage()?>" method="get">
			<p>
				<input type="hidden" name="lang" value="<?=LANG?>">
				<input type="submit" value="<?=($buttonName <> '' ? $buttonName : Loc::getMessage('SH_MOD_BACK'))?>">
			</p>
		</form>
		<?php
		include(Application::getDocumentRoot().'/bitrix/modules/main/include/epilog_admin.php');
		die();
	}
	// endregion /////

	// region Tools ////
	private function checkChildModules(): Result
	{
		$result = new Result();
		foreach(ModuleManager::getInstalledModules() as $moduleId => $moduleDesc)
		{
			if($this->MODULE_ID === $moduleId)
			{
				continue;
			}
			
			$requireModules = Config\Configuration::getInstance($moduleId)->get('requireModules');
			if(empty($requireModules) || !is_array($requireModules))
			{
				continue;
			}
			
			if(in_array($this->MODULE_ID, $requireModules))
			{
				$result->addError(new Error(Loc::getMessage('SH_NEED_UNINSTALL_MODULE_BEFORE', [
					'#TARGET#' => $moduleId
				])));
			}
		}
		
		return $result;
	}
	// endregion /////
}