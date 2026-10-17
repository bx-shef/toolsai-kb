<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Public\Service;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Engine\CurrentUser;
use Shef\ToolsAiKb\Import\MappingStore;
use Shef\ToolsAiKb\Main\Constants;
use Shef\ToolsAiKb\Model\ChunkStoreOrm;
use Shef\ToolsAiKb\Model\MappingStoreOrm;
use Shef\ToolsAiKb\Note\BitrixNoteGateway;
use Shef\ToolsAiKb\Note\NoteGateway;
use Shef\ToolsAiKb\Search\Bm25Index;
use Shef\ToolsAiKb\Search\QueryNormalizer;
use Shef\ToolsAiKb\Search\ResultMerger;

/**
 * Поиск статей «Базы знаний» для других модулей (публичный API модуля).
 *
 *   if(Loader::includeModule('shef.toolsaikb'))
 *   {
 *       $articles = \Shef\ToolsAiKb\Public\Service\KbSearchService::find('как настроить квоту', 5);
 *   }
 *
 * Где ищет:
 *  - по умолчанию — только в своём разделе «ИИ: База знаний» (ID раздела из
 *    настроек). Поиск ядра раздел не принимает (SearchQuery — только текст),
 *    поэтому выдача фильтруется по разделу документа;
 *  - настройка «Искать по всей Базе знаний» (KB_searchall = Y) — по всем
 *    документам, которые видит пользователь.
 * В обоих случаях права проверяет SearchProvider ядра по ТЕКУЩЕМУ
 * пользователю; без пользователя — пустой список.
 *
 * Вопрос разбирается на основы слов (QueryNormalizer). Сначала ищутся
 * документы со всеми словами сразу; набралось меньше $limit — добирается
 * поиском по каждому из трёх самых длинных слов, списки сводятся
 * (ResultMerger). Ответ держится в кеше 60 секунд на пользователя и запрос.
 *
 * С индексом фрагментов (1.3.0, Search\Bm25Index — статьи своего раздела,
 * пересобирается после импорта) поиск `note` только отбирает кандидатов —
 * документы, которые сотрудник видит, с запасом (CANDIDATES) и добором по
 * словам всегда. Порядок задаёт BM25 по фрагментам, а в `text` уходят лучшие
 * фрагменты статьи (до TEXT_MAX символов) — это контекст для модели.
 * Документы вне индекса (чужой раздел при «Искать по всей Базе знаний»)
 * идут после, со сниппетом `note`. Индекса нет — поиск как до 1.3.0.
 *
 * Ссылка статьи (`url`): если пакет статьи отдал `viewUrl` (событие
 * OnKbSourcesCollect), — адрес статьи в родном шаблоне модуля; иначе — документ
 * `note`. Правленная руками в `note` статья всегда ведёт в `note`: там
 * актуальный текст. Адрес документа `note` всегда есть в `noteUrl`.
 */
final class KbSearchService
{
	public const MAX_LIMIT = 20;
	/** По скольким словам добирать — не больше этого числа запросов к `note`. */
	public const PER_TERM_MAX = 3;
	/** Во сколько раз больше просить у `note`, когда выдача фильтруется по разделу. */
	public const SCOPE_OVERFETCH = 4;
	/** С индексом: сколько кандидатов отобрать поиском `note` для ранжирования. */
	public const CANDIDATES = 20;
	/** Предел `text` статьи — фрагменты для модели, символов. */
	public const TEXT_MAX = 1500;

	/**
	 * @param string $query вопрос как есть, по-русски
	 * @param int $limit сколько статей вернуть, 1..20
	 * @return list<array{id: int, title: string, url: string, noteUrl: string, snippet: string, text: string}>
	 *         id — документ `note`; url — адрес от корня портала: статья в шаблоне
	 *         модуля (viewUrl пакета) или документ `note`; noteUrl — всегда документ
	 *         `note` (`/note/document/<id>/`); title, snippet и text — простой текст без
	 *         экранирования (snippet — до 300 символов; text — лучшие фрагменты
	 *         статьи под вопрос, до 1500 символов, нет индекса — равен snippet; 1.3.0)
	 */
	public static function find(string $query, int $limit = 5): array
	{
		if(QueryNormalizer::terms($query) === [])
		{
			return [];
		}
		$userId = (int)CurrentUser::get()->getId();
		if($userId <= 0)
		{
			return [];
		}
		$collectionId = static::scope(
			(string)Option::get(Constants::MODULE_ID, Constants::OPTION_SEARCH_ALL, 'N'),
			(int)Option::get(Constants::MODULE_ID, Constants::OPTION_COLLECTION_ID, '0')
		);
		if($collectionId === 0)
		{
			return [];
		}

		$cache = Cache::createInstance();
		$key = static::cacheKey($userId, $query, $limit, $collectionId, (string)Option::get(Constants::MODULE_ID, Constants::OPTION_INDEX_VERSION, ''));
		if($cache->initCache(Constants::SEARCH_CACHE_TTL, $key, '/shef/toolsaikb/search'))
		{
			$vars = $cache->getVars();
			if(is_array($vars))
			{
				return $vars;
			}
		}
		$found = static::findWith(new BitrixNoteGateway(), $query, $limit, $collectionId, new MappingStoreOrm(), static::index());
		if($cache->startDataCache())
		{
			$cache->endDataCache($found);
		}

		return $found;
	}

	/**
	 * Где искать: null — по всей «Базе знаний», ID раздела — только в нём,
	 * 0 — негде (раздел ещё не создан, а искать везде не разрешено).
	 */
	public static function scope(string $searchAll, int $collectionId): ?int
	{
		if($searchAll === 'Y')
		{
			return null;
		}

		return max(0, $collectionId);
	}

	/** Ключ кеша: пользователь, запрос, лимит, раздел, метка индекса. */
	public static function cacheKey(int $userId, string $query, int $limit, ?int $collectionId, string $indexVersion = ''): string
	{
		return md5(implode('|', [$userId, $limit, $collectionId ?? 'all', $indexVersion, $query]));
	}

	/**
	 * Индекс фрагментов из кеша (ключ — метка пересборки); нет метки, таблицы
	 * или фрагментов — null, поиск идёт без него.
	 */
	public static function index(): ?Bm25Index
	{
		$version = (string)Option::get(Constants::MODULE_ID, Constants::OPTION_INDEX_VERSION, '');
		if($version === '')
		{
			return null;
		}
		try
		{
			$cache = Cache::createInstance();
			if($cache->initCache(Constants::INDEX_CACHE_TTL, 'index|'.$version, '/shef/toolsaikb/index'))
			{
				$index = Bm25Index::fromArray($cache->getVars());
			}
			else
			{
				$index = Bm25Index::build((new ChunkStoreOrm())->all());
				if($cache->startDataCache())
				{
					$cache->endDataCache($index->toArray());
				}
			}
		}
		catch(\Throwable)
		{
			// Таблицы нет или кеш сломан — поиск без индекса
			return null;
		}

		return $index->size() > 0 ? $index : null;
	}

	/**
	 * То же на заданной «Базе знаний», без кеша — для тестов и своих сценариев.
	 *
	 * @param int|null $collectionId только этот раздел; null — вся «База знаний»
	 * @param MappingStore|null $mapping откуда брать адреса в шаблоне модуля; null — все ссылки на `note`
	 * @param Bm25Index|null $index фрагменты статей; null — порядок и сниппеты `note`
	 * @return list<array{id: int, title: string, url: string, noteUrl: string, snippet: string, text: string}>
	 */
	public static function findWith(NoteGateway $note, string $query, int $limit = 5, ?int $collectionId = null, ?MappingStore $mapping = null, ?Bm25Index $index = null): array
	{
		$limit = max(1, min(self::MAX_LIMIT, $limit));
		$terms = QueryNormalizer::terms($query);
		if($terms === [] || $collectionId === 0)
		{
			return [];
		}

		// С индексом кандидатов берём с запасом: порядок задаст BM25
		$pool = $index === null ? $limit : max($limit, self::CANDIDATES);
		$ask = $collectionId === null ? $pool : $pool * self::SCOPE_OVERFETCH;
		$inScope = static fn(array $hits): array => $collectionId === null
			? $hits
			: array_values(array_filter($hits, static fn(array $hit): bool => (int)$hit['collectionId'] === $collectionId));

		$exact = $inScope($note->search($terms, $ask));
		$perTerm = [];
		if(count($terms) > 1 && ($index !== null || count($exact) < $limit))
		{
			foreach(static::longest($terms, self::PER_TERM_MAX) as $term)
			{
				$perTerm[] = $inScope($note->search([$term], $ask));
			}
		}

		$merged = ResultMerger::merge($exact, $perTerm, $pool);
		$ranked = [];
		if($index !== null && $merged !== [])
		{
			$ranked = $index->rank($query, array_map(static fn(array $hit): int => (int)$hit['id'], $merged), self::TEXT_MAX);
			$position = array_flip(array_keys($ranked));
			$order = array_flip(array_map(static fn(array $hit): int => (int)$hit['id'], $merged));
			usort($merged, static fn(array $a, array $b): int => [$position[(int)$a['id']] ?? PHP_INT_MAX, $order[(int)$a['id']]] <=> [$position[(int)$b['id']] ?? PHP_INT_MAX, $order[(int)$b['id']]]);
		}
		$merged = array_slice($merged, 0, $limit);
		$viewUrls = [];
		if($mapping !== null && $merged !== [])
		{
			try
			{
				$viewUrls = $mapping->viewUrls(array_map(static fn(array $hit): int => (int)$hit['id'], $merged));
			}
			catch(\Throwable)
			{
				// Таблица недоступна — ссылки на `note`, поиск не падает
				$viewUrls = [];
			}
		}

		return array_map(
			static function(array $hit) use ($viewUrls, $ranked): array
			{
				$snippet = ResultMerger::plainSnippet($hit['snippet']);
				$fragments = $ranked[(int)$hit['id']]['fragments'] ?? [];
				$best = $ranked[(int)$hit['id']]['best'] ?? '';

				return [
					'id' => $hit['id'],
					'title' => $hit['title'],
					'url' => $viewUrls[(int)$hit['id']] ?? Constants::documentUrl($hit['id']),
					'noteUrl' => Constants::documentUrl($hit['id']),
					'snippet' => $snippet !== '' || $best === '' ? $snippet : ResultMerger::plainSnippet($best),
					'text' => $fragments === [] ? $snippet : static::fragmentsText($fragments),
				];
			},
			$merged
		);
	}

	/**
	 * Фрагменты статьи одним текстом: раздел — строкой «## …» перед фрагментом,
	 * не длиннее TEXT_MAX.
	 *
	 * @param list<array{heading: string, text: string}> $fragments
	 */
	public static function fragmentsText(array $fragments): string
	{
		$parts = [];
		foreach($fragments as $fragment)
		{
			$parts[] = ($fragment['heading'] !== '' ? '## '.$fragment['heading']."\n" : '').$fragment['text'];
		}
		$text = implode("\n\n", $parts);

		return mb_strlen($text) > self::TEXT_MAX ? rtrim(mb_substr($text, 0, self::TEXT_MAX - 1)).'…' : $text;
	}

	/**
	 * Самые длинные слова — самые редкие и точные; при равной длине — в порядке вопроса.
	 *
	 * @param list<string> $terms
	 * @return list<string>
	 */
	public static function longest(array $terms, int $max): array
	{
		$order = array_flip($terms);
		usort($terms, static fn(string $a, string $b): int => [mb_strlen($b), $order[$a]] <=> [mb_strlen($a), $order[$b]]);

		return array_slice($terms, 0, $max);
	}
}
