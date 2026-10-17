<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Import;

use Bitrix\Main\Config\Option;
use Shef\Options\Main\Constants as OptionsConstants;
use Shef\Options\Main\TempFile\Pid;
use Shef\ToolsAiKb\Main\Constants;
use Shef\ToolsAiKb\Main\ProblemLogger;
use Shef\ToolsAiKb\Model\ChunkStoreOrm;
use Shef\ToolsAiKb\Model\ChunkTable;
use Shef\ToolsAiKb\Model\MappingStoreOrm;
use Shef\ToolsAiKb\Model\MappingTable;
use Shef\ToolsAiKb\Note\BitrixNoteGateway;
use Shef\ToolsAiKb\Note\NoteGateway;
use Shef\ToolsAiKb\Search\ChunkStore;
use Shef\ToolsAiKb\Search\IndexBuilder;

/**
 * Один прогон импорта на портале: блокировка, сбор пакетов, импорт,
 * пересборка индекса фрагментов для поиска (1.3.0), итог в настройки, ошибки в журнал. Его зовут агент, кнопка «Обновить статьи» и
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

		Option::set(Constants::MODULE_ID, Constants::OPTION_LAST_REPORT, static::reportJson($report, date('Y-m-d H:i:s')));
		static::log($report);

		return $report;
	}

	private static function import(): ImportReport
	{
		$collected = SourceCollector::collect();
		try
		{
			// Обновление модуля без переустановки: колонка VIEW_URL появляется здесь
			MappingTable::init();
			$note = new BitrixNoteGateway();
			$importer = new Importer($note, new MappingStoreOrm(), OptionsConstants::getSystemUserId());
			$report = $importer->run(
				$collected['sources'],
				(int)Option::get(Constants::MODULE_ID, Constants::OPTION_COLLECTION_ID, '0'),
				static::collectionName((string)Option::get(Constants::MODULE_ID, Constants::OPTION_COLLECTION_NAME, Constants::DEFAULT_COLLECTION_NAME)),
				null,
				// Сбор с ошибками — пропажа пакета может быть сбоем: пропавшие пакеты не удаляем
				$collected['errors'] === []
			);
			// Обновление без переустановки: таблица фрагментов появляется здесь
			ChunkTable::init();
			$version = static::reindex($report, $note, new ChunkStoreOrm());
			if($version !== null)
			{
				Option::set(Constants::MODULE_ID, Constants::OPTION_INDEX_VERSION, $version);
			}
		}
		catch(\Throwable $throwable)
		{
			$report = new ImportReport();
			$report->errors[] = $throwable->getMessage();
		}
		$report = static::withCollectErrors($report, $collected['errors']);
		$collectionOption = static::collectionOption($report);
		if($collectionOption !== null)
		{
			Option::set(Constants::MODULE_ID, Constants::OPTION_COLLECTION_ID, $collectionOption);
		}

		return $report;
	}

	/**
	 * Пересобрать индекс фрагментов по разделу отчёта. Сбой индекса импорт не
	 * отменяет: он в ошибках отчёта, поиск остаётся на прежнем индексе.
	 *
	 * @return string|null новая метка индекса; null — не пересобран (раздела нет, сбой)
	 */
	public static function reindex(ImportReport $report, NoteGateway $note, ChunkStore $store): ?string
	{
		if($report->collectionId <= 0)
		{
			return null;
		}
		try
		{
			$built = (new IndexBuilder($note, $store))->rebuild($report->collectionId);
		}
		catch(\Throwable $throwable)
		{
			$report->errors[] = 'индекс поиска не пересобран: '.$throwable->getMessage();

			return null;
		}
		$report->indexChunks = $built['chunks'];
		$report->errors = array_merge($report->errors, $built['errors']);

		return date('YmdHis').'.'.$built['chunks'];
	}

	/**
	 * Название раздела из настройки: пробелы по краям прочь, пусто — по умолчанию.
	 */
	public static function collectionName(string $raw): string
	{
		$name = trim($raw);

		return $name !== '' ? mb_substr($name, 0, 255) : Constants::DEFAULT_COLLECTION_NAME;
	}

	/**
	 * Ошибки сбора пакетов — в начало отчёта: они объясняют, почему пакетов меньше.
	 *
	 * @param list<string> $collectErrors
	 */
	public static function withCollectErrors(ImportReport $report, array $collectErrors): ImportReport
	{
		$report->errors = array_merge($collectErrors, $report->errors);

		return $report;
	}

	/**
	 * Что записать в SYS_collectionid: ID раздела из отчёта; null — не трогать
	 * (импорт упал до раздела — прежний ID не стирать).
	 */
	public static function collectionOption(ImportReport $report): ?string
	{
		return $report->collectionId > 0 ? (string)$report->collectionId : null;
	}

	/**
	 * Итог для страницы настроек — JSON (Settings\ReportView разбирает его в сводку).
	 */
	public static function reportJson(ImportReport $report, string $at): string
	{
		return (string)json_encode($report->toArray($at), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	}

	/** Строка итога для журнала. */
	public static function reportLine(ImportReport $report, string $at): string
	{
		return $at.' — '.$report->summary();
	}

	private static function log(ImportReport $report): void
	{
		if($report->errors === [])
		{
			return;
		}
		try
		{
			(new ProblemLogger())->warning('Импорт статей в «Базу знаний»: '.$report->summary(), ['errors' => $report->errors, 'kept' => $report->kept]);
		}
		catch(\Throwable)
		{
			// Журнал недоступен — итог всё равно на странице настроек
		}
	}
}
