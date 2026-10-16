<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Search;

/**
 * Сведение результатов нескольких запросов к `note` в один список.
 *
 * Поиск идёт в два шага (KbSearchService): сначала все слова сразу — самые
 * точные статьи; не набралось — каждое слово отдельно. Здесь эти списки
 * сводятся: статья из точного запроса всегда выше, дальше — по числу слов
 * вопроса, которые в ней нашлись, потом по сумме оценок `note`, потом по
 * порядку появления. Одна статья — одна строка.
 *
 * Без ядра — tests/resultmerger_test.php.
 */
final class ResultMerger
{
	/**
	 * @param list<array{id: int, title: string, snippet: string, score: float}> $exact найденное всеми словами сразу
	 * @param list<list<array{id: int, title: string, snippet: string, score: float}>> $perTerm найденное по каждому слову
	 * @return list<array{id: int, title: string, snippet: string, score: float}>
	 */
	public static function merge(array $exact, array $perTerm, int $limit): array
	{
		if($limit <= 0)
		{
			return [];
		}

		/** @var array<int, array{hit: array, exact: int, terms: int, score: float, order: int}> $rows */
		$rows = [];
		$order = 0;
		$take = static function(array $hit, bool $isExact) use (&$rows, &$order): void
		{
			$id = (int)$hit['id'];
			if($id <= 0)
			{
				return;
			}
			if(!isset($rows[$id]))
			{
				$rows[$id] = ['hit' => $hit, 'exact' => 0, 'terms' => 0, 'score' => 0.0, 'order' => $order++];
			}
			if($isExact)
			{
				$rows[$id]['exact'] = 1;
			}
			else
			{
				$rows[$id]['terms']++;
			}
			$rows[$id]['score'] += (float)$hit['score'];
			// Сниппет с подсветкой — из первого непустого ответа
			if($rows[$id]['hit']['snippet'] === '' && (string)$hit['snippet'] !== '')
			{
				$rows[$id]['hit']['snippet'] = (string)$hit['snippet'];
			}
		};

		foreach($exact as $hit)
		{
			$take($hit, true);
		}
		foreach($perTerm as $list)
		{
			// Одна статья дважды в ответе на одно слово — одно совпадение
			$seen = [];
			foreach($list as $hit)
			{
				if(isset($seen[(int)$hit['id']]))
				{
					continue;
				}
				$seen[(int)$hit['id']] = true;
				$take($hit, false);
			}
		}

		$rows = array_values($rows);
		usort($rows, static fn(array $a, array $b): int => [$b['exact'], $b['terms'], $b['score'], $a['order']] <=> [$a['exact'], $a['terms'], $a['score'], $b['order']]);

		return array_map(
			static fn(array $row): array => [
				'id' => (int)$row['hit']['id'],
				'title' => (string)$row['hit']['title'],
				'snippet' => (string)$row['hit']['snippet'],
				'score' => (float)$row['hit']['score'],
			],
			array_slice($rows, 0, $limit)
		);
	}

	/**
	 * Сниппет `note` — HTML с подсветкой найденного. Наружу — простой текст:
	 * теги прочь, сущности раскрыты, пробелы схлопнуты, не длиннее $max символов.
	 */
	public static function plainSnippet(string $html, int $max = 300): string
	{
		$text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = trim((string)preg_replace('/\s+/u', ' ', $text));
		if(mb_strlen($text) > $max)
		{
			$text = rtrim(mb_substr($text, 0, $max - 1)).'…';
		}

		return $text;
	}
}
