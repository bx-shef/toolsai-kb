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
 * Фильтры ядра по умолчанию (Authentication, HttpMethod GET/POST, Csrf) —
 * configureActions() не переопределяет их. Права на документы проверяет
 * SearchProvider `note` по текущему пользователю.
 */
class Search extends Controller
{
	/**
	 * @return array{articles: list<array{id: int, title: string, url: string, snippet: string}>}|null
	 */
	public function findAction(string $query, int $limit = 5): ?array
	{
		if(mb_strlen($query) > 1000)
		{
			$this->addError(new Error('Слишком длинный запрос'));

			return null;
		}
		try
		{
			return ['articles' => KbSearchService::find($query, $limit)];
		}
		catch(\Throwable)
		{
			$this->addError(new Error('Поиск по базе знаний недоступен'));

			return null;
		}
	}
}
