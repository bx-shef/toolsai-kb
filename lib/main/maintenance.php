<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Main;

use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;

/**
 * Обновление без установщика (1.1.2) — лёгкий вариант Main\Maintenance
 * shef.toolsaiassistant.
 *
 * На портале модуль обновляют копированием файлов в bitrix/modules, установщик
 * при этом не запускается — подписка из installEvents (1.1.2:
 * shef.toolsai:OnViewSettingsMenuBuild) сама не появится. Своих подписок на
 * каждый хит у модуля нет, поэтому доводят себя две точки, которые есть с
 * первой версии: агент «Обновить статьи» (раз в сутки) и страница настроек.
 * registerEventHandler ядра идемпотентен (INSERT IGNORE по UNIQUE_ID,
 * main/lib/eventmanager.php), повтор безвреден.
 *
 * Версия, до которой довели, — опция SYS_eventsversion. Совпала — одно чтение
 * опции из кеша ядра. Сбой — версию не пишем. Исключений наружу нет.
 */
final class Maintenance
{
	public const OPTION_VERSION = 'SYS_eventsversion';

	/** Версия отстала — поставить подписки из installEvents. */
	public static function ensure(): void
	{
		try
		{
			$moduleDir = dirname(__DIR__, 2);
			$version = static::moduleVersion($moduleDir);
			if(static::isNeeded((string)Option::get(Constants::MODULE_ID, self::OPTION_VERSION, ''), $version))
			{
				$settings = require $moduleDir.'/.settings.php';
				static::registerEvents((array)($settings['installEvents']['value'] ?? []));
				Option::set(Constants::MODULE_ID, self::OPTION_VERSION, $version);
			}
		}
		catch(\Throwable)
		{
		}
	}

	/** Нужно ли: сохранённая версия не та, что у модуля. */
	public static function isNeeded(string $saved, string $current): bool
	{
		return $current !== '' && $saved !== $current;
	}

	/** Подписки из installEvents — как InstallEvents() установщика, без снятия. */
	public static function registerEvents(array $events): void
	{
		$manager = EventManager::getInstance();
		foreach($events as $event)
		{
			$method = !empty($event['isCompatible']) ? 'registerEventHandlerCompatible' : 'registerEventHandler';
			$manager->{$method}(
				$event['from']['module'],
				$event['from']['event'],
				$event['to']['module'],
				$event['to']['class'],
				$event['to']['function'],
				(int)($event['to']['sort'] ?? 100)
			);
		}
	}

	/** Версия модуля из install/version.php. */
	public static function moduleVersion(string $moduleDir): string
	{
		$arModuleVersion = [];
		$file = $moduleDir.'/install/version.php';
		if(is_file($file))
		{
			include $file;
		}

		return (string)($arModuleVersion['VERSION'] ?? '');
	}
}
