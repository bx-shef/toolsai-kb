<?php declare(strict_types=1);

/**
 * Search\ResultMerger: сведение точного поиска и поиска по словам.
 */

require_once __DIR__.'/assert.php';
require_once __DIR__.'/stub/autoload.php';

use Shef\ToolsAiKb\Search\ResultMerger;

$hit = static fn(int $id, float $score = 1.0, string $snippet = ''): array => ['id' => $id, 'title' => 'Статья '.$id, 'snippet' => $snippet, 'score' => $score];
$ids = static fn(array $list): array => array_map(static fn(array $r): int => $r['id'], $list);

Check::group('порядок');

Check::same('только точный — как есть', $ids(ResultMerger::merge([$hit(3), $hit(1)], [], 5)), [3, 1]);
Check::same(
	'точный выше, затем по числу слов',
	$ids(ResultMerger::merge([$hit(9, 0.1)], [[$hit(1), $hit(2)], [$hit(2), $hit(9)]], 5)),
	[9, 2, 1]
);
Check::same(
	'при равном числе слов — по сумме оценок',
	$ids(ResultMerger::merge([], [[$hit(1, 1.0), $hit(2, 5.0)]], 5)),
	[2, 1]
);
Check::same(
	'при равных оценках — по порядку появления',
	$ids(ResultMerger::merge([], [[$hit(4), $hit(5)], [$hit(6)]], 5)),
	[4, 5, 6]
);
Check::same('лимит', $ids(ResultMerger::merge([], [[$hit(1), $hit(2), $hit(3)]], 2)), [1, 2]);
Check::same('лимит 0', ResultMerger::merge([$hit(1)], [], 0), []);
Check::same('повтор в одном ответе — одно совпадение', $ids(ResultMerger::merge([], [[$hit(1), $hit(1)], [$hit(2)], [$hit(2)]], 5)), [2, 1]);
Check::same('пустой id прочь', $ids(ResultMerger::merge([$hit(0)], [], 5)), []);

Check::group('сниппет');

$merged = ResultMerger::merge([$hit(1)], [[$hit(1, 1.0, 'по <b>слову</b>')]], 5);
Check::same('пустой сниппет добирается из ответа по слову', $merged[0]['snippet'], 'по <b>слову</b>');
Check::same('оценка — первого ответа', $merged[0]['score'], 1.0);
Check::same('HTML -> текст', ResultMerger::plainSnippet("<b>Квота</b> &amp; расход\n\n  месяц"), 'Квота & расход месяц');
Check::same('обрезка с многоточием', ResultMerger::plainSnippet(str_repeat('я', 10), 5), 'яяяя…');

Check::finish();
