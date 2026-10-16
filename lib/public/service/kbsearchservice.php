<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Public\Service;

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
 * Ищет по всем документам `note`, которые видит ТЕКУЩИЙ пользователь
 * (SearchProvider ядра проверяет права сам); без пользователя — пустой список.
 *
 * Вопрос разбирается на основы слов (QueryNormalizer). Сначала ищутся
 * документы со всеми словами сразу; набралось меньше $limit — добирается
 * поиском по каждому слову, и списки сводятся (ResultMerger).
 */
final class KbSearchService
{
	public const MAX_LIMIT = 20;

	/**
	 * @param string $query вопрос как есть, по-русски
	 * @param int $limit сколько статей вернуть, 1..20
	 * @return list<array{id: int, title: string, url: string, snippet: string}>
	 *         id — документ `note`, url — адрес от корня портала (`/note/document/<id>/`),
	 *         snippet — простой текст до 300 символов
	 */
	public static function find(string $query, int $limit = 5): array
	{
		if(QueryNormalizer::terms($query) === [])
		{
			return [];
		}

		return static::findWith(new BitrixNoteGateway(), $query, $limit);
	}

	/**
	 * То же на заданной «Базе знаний» — для тестов.
	 *
	 * @return list<array{id: int, title: string, url: string, snippet: string}>
	 */
	public static function findWith(NoteGateway $note, string $query, int $limit = 5): array
	{
		$limit = max(1, min(self::MAX_LIMIT, $limit));
		$terms = QueryNormalizer::terms($query);
		if($terms === [])
		{
			return [];
		}

		$exact = $note->search($terms, $limit);
		$perTerm = [];
		if(count($exact) < $limit && count($terms) > 1)
		{
			foreach($terms as $term)
			{
				$perTerm[] = $note->search([$term], $limit);
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
}
