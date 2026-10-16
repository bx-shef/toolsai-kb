<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Error;
use Shef\ToolsAiKb\Public\Service\KbSearchService;

/**
 * Поиск статей из браузера:
 *
 *   BX.ajax.runAction('shef:toolsaikb.search.find', {data: {query: 'квота', limit: 5}})
 *     -> {articles: [{id, title, url, snippet}, ...]}
 *
 * Фильтры ядра по умолчанию (Authentication, HttpMethod GET/POST, Csrf):
 * configureActions() их не переопределяет — ключ `prefilters` заменил бы их
 * целиком (навык shef-new-ajax-action). Права на документы проверяет
 * SearchProvider `note` по текущему пользователю; где искать — KbSearchService.
 */
class Search extends Controller
{
	/** Длиннее — отказ: такой «вопрос» — не вопрос, а вставленный текст. */
	public const QUERY_MAX = 1000;

	/**
	 * Найти статьи.
	 *
	 * @return array{articles: list<array{id: int, title: string, url: string, snippet: string}>}|null
	 */
	public function findAction(string $query, int $limit = 5): ?array
	{
		$query = trim($query);
		if(mb_strlen($query) > self::QUERY_MAX)
		{
			$this->addError(new Error('Слишком длинный запрос'));

			return null;
		}
		try
		{
			return ['articles' => KbSearchService::find($query, static::limit($limit))];
		}
		catch(\Throwable)
		{
			$this->addError(new Error('Поиск по базе знаний недоступен'));

			return null;
		}
	}

	/** Лимит из запроса — в пределах 1..KbSearchService::MAX_LIMIT. */
	public static function limit(int $limit): int
	{
		return max(1, min(KbSearchService::MAX_LIMIT, $limit));
	}
}
