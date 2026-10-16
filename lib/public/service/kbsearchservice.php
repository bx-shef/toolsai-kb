<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Public\Service;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Engine\CurrentUser;
use Shef\ToolsAiKb\Main\Constants;
use Shef\ToolsAiKb\Note\BitrixNoteGateway;
use Shef\ToolsAiKb\Note\NoteGateway;
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
 */
final class KbSearchService
{
	public const MAX_LIMIT = 20;
	/** По скольким словам добирать — не больше этого числа запросов к `note`. */
	public const PER_TERM_MAX = 3;
	/** Во сколько раз больше просить у `note`, когда выдача фильтруется по разделу. */
	public const SCOPE_OVERFETCH = 4;

	/**
	 * @param string $query вопрос как есть, по-русски
	 * @param int $limit сколько статей вернуть, 1..20
	 * @return list<array{id: int, title: string, url: string, snippet: string}>
	 *         id — документ `note`, url — адрес от корня портала (`/note/document/<id>/`),
	 *         title и snippet — простой текст без экранирования (snippet — до 300 символов)
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
		$key = static::cacheKey($userId, $query, $limit, $collectionId);
		if($cache->initCache(Constants::SEARCH_CACHE_TTL, $key, '/shef/toolsaikb/search'))
		{
			$vars = $cache->getVars();
			if(is_array($vars))
			{
				return $vars;
			}
		}
		$found = static::findWith(new BitrixNoteGateway(), $query, $limit, $collectionId);
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

	/** Ключ кеша: пользователь, запрос, лимит, раздел. */
	public static function cacheKey(int $userId, string $query, int $limit, ?int $collectionId): string
	{
		return md5(implode('|', [$userId, $limit, $collectionId ?? 'all', $query]));
	}

	/**
	 * То же на заданной «Базе знаний», без кеша — для тестов и своих сценариев.
	 *
	 * @param int|null $collectionId только этот раздел; null — вся «База знаний»
	 * @return list<array{id: int, title: string, url: string, snippet: string}>
	 */
	public static function findWith(NoteGateway $note, string $query, int $limit = 5, ?int $collectionId = null): array
	{
		$limit = max(1, min(self::MAX_LIMIT, $limit));
		$terms = QueryNormalizer::terms($query);
		if($terms === [] || $collectionId === 0)
		{
			return [];
		}

		$ask = $collectionId === null ? $limit : $limit * self::SCOPE_OVERFETCH;
		$inScope = static fn(array $hits): array => $collectionId === null
			? $hits
			: array_values(array_filter($hits, static fn(array $hit): bool => (int)$hit['collectionId'] === $collectionId));

		$exact = $inScope($note->search($terms, $ask));
		$perTerm = [];
		if(count($exact) < $limit && count($terms) > 1)
		{
			foreach(static::longest($terms, self::PER_TERM_MAX) as $term)
			{
				$perTerm[] = $inScope($note->search([$term], $ask));
			}
		}

		return array_map(
			static fn(array $hit): array => [
				'id' => $hit['id'],
				'title' => $hit['title'],
				'url' => Constants::documentUrl($hit['id']),
				'snippet' => ResultMerger::plainSnippet($hit['snippet']),
			],
			ResultMerger::merge($exact, $perTerm, $limit)
		);
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
