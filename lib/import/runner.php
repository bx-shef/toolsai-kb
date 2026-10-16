<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Import;

use Bitrix\Main\Config\Option;
use Shef\Options\Main\Constants as OptionsConstants;
use Shef\Options\Main\TempFile\Pid;
use Shef\ToolsAiKb\Main\Constants;
use Shef\ToolsAiKb\Main\ProblemLogger;
use Shef\ToolsAiKb\Model\MappingStoreOrm;
use Shef\ToolsAiKb\Note\BitrixNoteGateway;

/**
 * Один прогон импорта на портале: блокировка, сбор пакетов, импорт, итог в
 * настройки, ошибки в журнал. Его зовут агент, кнопка «Обновить статьи» и
 * установщик.
 *
 * Автор раздела и документов — служебный пользователь shef.options
 * (`Shef\Options\Main\Constants::getSystemUserId()`, по умолчанию ID 1).
 */
final class Runner
{
	/**
	 * @return ImportReport|null null — импорт уже идёт в другом процессе
	 */
	public static function run(): ?ImportReport
	{
		$lock = new Pid(Constants::LOCK_GROUP);
		if(!$lock->add())
		{
			return null;
		}

		try
		{
			// После add() в каталоге группы — только живые блокировки: больше одной — работает кто-то ещё
			if(count(glob(Pid::getBasePath(Constants::LOCK_GROUP).'/*.lock') ?: []) > 1)
			{
				return null;
			}

			$report = static::import();
		}
		finally
		{
			$lock->remove();
		}

		Option::set(Constants::MODULE_ID, Constants::OPTION_LAST_REPORT, date('Y-m-d H:i:s').' — '.$report->summary());
		static::log($report);

		return $report;
	}

	private static function import(): ImportReport
	{
		$collected = SourceCollector::collect();
		try
		{
			$importer = new Importer(new BitrixNoteGateway(), new MappingStoreOrm(), OptionsConstants::getSystemUserId());
			$name = trim((string)Option::get(Constants::MODULE_ID, Constants::OPTION_COLLECTION_NAME, Constants::DEFAULT_COLLECTION_NAME));
			$report = $importer->run(
				$collected['sources'],
				(int)Option::get(Constants::MODULE_ID, Constants::OPTION_COLLECTION_ID, '0'),
				$name !== '' ? $name : Constants::DEFAULT_COLLECTION_NAME
			);
		}
		catch(\Throwable $throwable)
		{
			$report = new ImportReport();
			$report->errors[] = $throwable->getMessage();
		}
		array_unshift($report->errors, ...$collected['errors']);
		if($report->collectionId > 0)
		{
			Option::set(Constants::MODULE_ID, Constants::OPTION_COLLECTION_ID, (string)$report->collectionId);
		}

		return $report;
	}

	private static function log(ImportReport $report): void
	{
		if($report->errors === [])
		{
			return;
		}
		try
		{
			(new ProblemLogger())->warning('Импорт статей в «Базу знаний»: '.$report->summary(), ['errors' => $report->errors]);
		}
		catch(\Throwable)
		{
			// Журнал недоступен — итог всё равно на странице настроек
		}
	}
}
