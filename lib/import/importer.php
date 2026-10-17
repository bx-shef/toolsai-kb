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
 *  4. Статьи, которых больше нет в пакете, и пакеты, которых больше нет в
 *     событии, — по Planner::decideObsolete(): документ уходит в корзину
 *     `note` (восстановить можно оттуда), соответствие убирается. Правленный
 *     руками и архивный документ не трогается — только строка в отчёте;
 *     страница пакета с такими статьями тоже остаётся.
 *
 * Защита от сбоя: событие не вернуло ни одного пакета — ничего не удаляется;
 * пакет прочитан с ошибками или пришёл пустым — его статьи не удаляются;
 * под документом есть документ, которого нет в соответствиях (создан
 * человеком), — документ не удаляется: ядро унесло бы его в корзину вместе; сбор пакетов с
 * ошибками ($pruneMissingSources = false) — пропавшие пакеты не удаляются.
 *
 * Повторный прогон без изменений в пакетах ничего не пишет в `note`.
 *
 * Сбой одной статьи не останавливает остальные — он в отчёте.
 *
 * Без ядра — tests/importer_test.php на подставных NoteGateway и MappingStore.
 */
final class Importer
{
	/** Документ оказался в корзине или в архиве при перезаписи. */
	public const REMOVED = 'removed';
	/** Статьи нет в пакете, но документ оставлен (правлен руками или в архиве). */
	public const OBSOLETE_KEPT = 'obsolete_kept';

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
	 * @param bool $pruneMissingSources удалять статьи пакетов, которых нет в событии;
	 *        false — сбор пакетов прошёл с ошибками, пропажа может быть сбоем
	 */
	public function run(array $sources, int $collectionId, string $collectionName, ?callable $reader = null, bool $pruneMissingSources = true): ImportReport
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

		/** @var array<string, bool> код пакета -> прочитан без ошибок */
		$clean = [];
		/** @var array<string, true> "код|путь" статей, которые есть в пакетах */
		$present = [];
		foreach($sources as $source)
		{
			$read = $reader($source->dir);
			// Пакет без единой статьи — неуверенно (пустой каталог может быть сбоем): из него не удаляем
			$clean[$source->code] = $read['errors'] === [] && $read['articles'] !== [];
			$present[$source->code.'|'] = true;
			foreach($read['articles'] as $article)
			{
				$present[$source->code.'|'.$article->path] = true;
			}
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

		if($sources !== [])
		{
			$this->prune($report, $clean, $present, $pruneMissingSources);
		}

		return $report;
	}

	/**
	 * Убрать в корзину документы статей, которых больше нет в пакетах.
	 *
	 * Сначала статьи пакета, потом его страница — и только если под ней не
	 * осталось оставленных статей: ядро уносит в корзину документ вместе с
	 * дочерними.
	 *
	 * @param array<string, bool> $clean код пакета из события -> прочитан без ошибок
	 * @param array<string, true> $present "код|путь" статей из пакетов
	 */
	private function prune(ImportReport $report, array $clean, array $present, bool $pruneMissingSources): void
	{
		try
		{
			$rows = $this->mapping->all();
		}
		catch(\Throwable $throwable)
		{
			$report->errors[] = 'список соответствий не прочитан, пропавшие статьи не удалены: '.$throwable->getMessage();

			return;
		}

		$owned = [];
		foreach($rows as $row)
		{
			$owned[$row['documentId']] = true;
		}

		/** @var array<string, array{pages: list<array>, articles: list<array>}> $groups */
		$groups = [];
		foreach($rows as $row)
		{
			$code = $row['sourceCode'];
			if(isset($present[$code.'|'.$row['path']]))
			{
				continue;
			}
			$known = array_key_exists($code, $clean);
			if(($known && !$clean[$code]) || (!$known && !$pruneMissingSources))
			{
				continue;
			}
			$groups[$code][$row['path'] === '' ? 'pages' : 'articles'][] = $row;
		}

		if($groups === [])
		{
			return;
		}
		try
		{
			$tree = $this->note->getCollectionTree($report->collectionId);
		}
		catch(\Throwable $throwable)
		{
			$report->errors[] = 'дерево раздела не прочитано, пропавшие статьи не удалены: '.$throwable->getMessage();

			return;
		}
		$children = [];
		foreach($tree as $id => $parentId)
		{
			if($parentId !== null)
			{
				$children[$parentId][] = $id;
			}
		}

		foreach($groups as $code => $group)
		{
			$kept = 0;
			foreach($group['articles'] ?? [] as $row)
			{
				$kept += $this->removeObsolete($report, $row, $children, $owned) ? 0 : 1;
			}
			foreach($group['pages'] ?? [] as $row)
			{
				if($kept > 0)
				{
					$report->kept[] = $code.': страница пакета оставлена — под ней правленные руками или архивные статьи';
					continue;
				}
				$this->removeObsolete($report, $row, $children, $owned);
			}
		}
	}

	/**
	 * Один пропавший документ по Planner::decideObsolete().
	 *
	 * @param array{sourceCode: string, path: string, documentId: int, hash: string} $row
	 * @param array<int, list<int>> $children ID документа -> ID дочерних (дерево раздела)
	 * @param array<int, true> $owned ID документов из таблицы соответствий
	 * @return bool true — документа больше нет (удалён или уже исчез), false — оставлен
	 */
	private function removeObsolete(ImportReport $report, array $row, array $children, array $owned): bool
	{
		$label = $row['sourceCode'].($row['path'] !== '' ? '/'.$row['path'] : '');
		try
		{
			$action = Planner::decideObsolete($row['hash'], $this->note->getDocument($row['documentId']));
			switch($action)
			{
				case Planner::MANUAL:
					$report->kept[] = $label.': нет в пакете, но правлена руками — оставлена';
					$report->count(self::OBSOLETE_KEPT);

					return false;

				case Planner::ARCHIVED:
					$report->kept[] = $label.': нет в пакете, документ в архиве — оставлен';
					$report->count(self::OBSOLETE_KEPT);

					return false;

				case Planner::DELETE:
					if(self::hasForeignDescendant($row['documentId'], $children, $owned))
					{
						$report->kept[] = $label.': оставлен — под ним документы, созданные вручную';
						$report->count(self::OBSOLETE_KEPT);

						return false;
					}
					$trashed = $this->note->deleteDocument($row['documentId'], $this->authorId);
					$extra = count(array_diff($trashed, [$row['documentId']]));
					if($extra > 0)
					{
						$report->kept[] = $label.': вместе с ним в корзину ушли вложенные документы ('.$extra.') — восстановить можно из корзины';
					}
					$report->count($trashed === [] ? Planner::GONE : Planner::DELETE);
					break;

				default:
					$report->count(Planner::GONE);
			}
			$this->mapping->delete($row['sourceCode'], $row['path']);

			return true;
		}
		catch(\Throwable $throwable)
		{
			$report->errors[] = $label.': не удалён: '.$throwable->getMessage();

			return false;
		}
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

	/**
	 * Есть ли под документом (на любой глубине) документ не из таблицы соответствий.
	 *
	 * @param array<int, list<int>> $children
	 * @param array<int, true> $owned
	 */
	private static function hasForeignDescendant(int $documentId, array $children, array $owned): bool
	{
		$queue = $children[$documentId] ?? [];
		$seen = [];
		while($queue !== [])
		{
			$id = array_pop($queue);
			if(isset($seen[$id]))
			{
				continue;
			}
			$seen[$id] = true;
			if(!isset($owned[$id]))
			{
				return true;
			}
			array_push($queue, ...($children[$id] ?? []));
		}

		return false;
	}
}
