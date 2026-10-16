<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Import;

use Shef\ToolsAiKb\Note\DocumentRemovedException;
use Shef\ToolsAiKb\Note\NoteGateway;

/**
 * Импорт пакетов статей в раздел «Базы знаний».
 *
 * Порядок:
 *  1. Раздел: есть — пополняем; нет — создаём заново; в архиве — импорт
 *     останавливается (архив — решение человека).
 *  2. На каждый пакет — документ-раздел верхнего уровня (заголовок пакета),
 *     статьи пакета — его дочерние документы.
 *  3. Каждая статья — по Planner: создать, перезаписать, не трогать.
 *     Соответствие и хэш записанного — в MappingStore.
 *
 * Повторный прогон без изменений в пакетах ничего не пишет в `note`.
 * Статьи, которых больше нет в пакете, не удаляются: документ мог стать
 * чужим (его поправили, на него ссылаются).
 *
 * Сбой одной статьи не останавливает остальные — он в отчёте.
 *
 * Без ядра — tests/importer_test.php на подставных NoteGateway и MappingStore.
 */
final class Importer
{
	/** Документ оказался в корзине или в архиве при перезаписи. */
	public const REMOVED = 'removed';

	/** Текст документа-раздела пакета. */
	public const SOURCE_PAGE_TEXT = 'Статьи раздела ниже. Раздел наполняется автоматически модулем #MODULE#: правка статьи в «Базе знаний» сохраняется, следующий импорт её не затрёт.';

	/**
	 * @param NoteGateway $note «База знаний»
	 * @param MappingStore $mapping таблица соответствий
	 * @param int $authorId автор раздела и документов (служебный пользователь)
	 */
	public function __construct(
		private readonly NoteGateway $note,
		private readonly MappingStore $mapping,
		private readonly int $authorId,
	)
	{
	}

	/**
	 * @param list<Source> $sources пакеты (SourceValidator)
	 * @param int $collectionId раздел из настроек; 0 — ещё не создан
	 * @param string $collectionName название раздела, если его надо создать
	 * @param null|callable(string): array{articles: list<Article>, errors: list<string>} $reader
	 *        чтение пакета, по умолчанию SourceReader::read()
	 */
	public function run(array $sources, int $collectionId, string $collectionName, ?callable $reader = null): ImportReport
	{
		$reader ??= static fn(string $dir): array => SourceReader::read($dir);
		$report = new ImportReport();
		$report->sources = count($sources);

		$state = $this->note->getCollectionState($collectionId);
		if($state === NoteGateway::COLLECTION_ARCHIVED)
		{
			$report->collectionId = $collectionId;
			$report->errors[] = 'раздел «'.$collectionName.'» (ID '.$collectionId.') в архиве — импорт не выполнен. Верните раздел из архива или удалите его: тогда модуль создаст новый.';

			return $report;
		}
		if($state !== NoteGateway::COLLECTION_OK)
		{
			try
			{
				$collectionId = $this->note->createCollection($collectionName, $this->authorId);
				$report->collectionCreated = true;
			}
			catch(\Throwable $throwable)
			{
				$report->errors[] = 'раздел «'.$collectionName.'» не создан: '.$throwable->getMessage();

				return $report;
			}
		}
		$report->collectionId = $collectionId;

		foreach($sources as $source)
		{
			$read = $reader($source->dir);
			foreach($read['errors'] as $error)
			{
				$report->errors[] = $source->code.': '.$error;
			}

			$text = str_replace('#MODULE#', $source->moduleId, self::SOURCE_PAGE_TEXT);
			$page = new Article('', $source->title, $text, ArticleParser::hash($source->title, $text));
			$parentId = $this->apply($report, $source, $page, $collectionId, null);

			foreach($read['articles'] as $article)
			{
				$this->apply($report, $source, $article, $collectionId, $parentId);
			}
		}

		return $report;
	}

	/**
	 * Одна статья по решению Planner.
	 *
	 * @return int|null ID документа в `note` (годится как родитель), null — документа нет
	 */
	private function apply(ImportReport $report, Source $source, Article $article, int $collectionId, ?int $parentId): ?int
	{
		$label = $source->code.($article->path !== '' ? '/'.$article->path : '');
		try
		{
			$mapped = $this->mapping->get($source->code, $article->path);
			$document = $mapped !== null ? $this->note->getDocument($mapped['documentId']) : null;
			$action = Planner::decide($article->hash, $mapped['hash'] ?? null, $document);

			switch($action)
			{
				case Planner::CREATE:
				case Planner::RECREATE:
					$documentId = $this->note->createDocument($collectionId, $parentId, $article->title, $article->markdown, $this->authorId);
					$this->mapping->save($source->code, $article->path, $documentId, $article->hash);
					$report->count($action);

					return $documentId;

				case Planner::UPDATE:
					try
					{
						$this->note->overwriteDocument($mapped['documentId'], $article->title, $article->markdown, $this->authorId);
					}
					catch(DocumentRemovedException)
					{
						$report->count(self::REMOVED);

						return null;
					}
					$this->mapping->save($source->code, $article->path, $mapped['documentId'], $article->hash);
					$report->count($action);

					return $mapped['documentId'];

				case Planner::MANUAL:
					$report->manual[] = $label;
					$report->count($action);

					return $mapped['documentId'];

				case Planner::ARCHIVED:
					$report->count($action);

					return null;

				default:
					$report->count($action);

					return $mapped['documentId'] ?? null;
			}
		}
		catch(\Throwable $throwable)
		{
			$report->errors[] = $label.': '.$throwable->getMessage();

			return null;
		}
	}
}
