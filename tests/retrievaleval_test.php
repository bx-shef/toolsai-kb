<?php declare(strict_types=1);

/**
 * Замер поиска статей (tools/retrievaleval/lib.php): метрики, разбор кейсов,
 * поиск MemoryNote как у `note` (все слова началом слова), прогон обоих путей
 * на маленьком корпусе — индекс фрагментов поднимает статью, где ответ в
 * разделе, выше статьи, где слова просто чаще.
 */

require_once __DIR__.'/assert.php';
require_once __DIR__.'/stub/autoload.php';
require_once dirname(__DIR__).'/tools/retrievaleval/lib.php';

use RetrievalEval\MemoryNote;
use RetrievalEval\Metrics;
use RetrievalEval\Runner;

Check::group('метрики');

Check::same('место первой нужной', [Metrics::firstRank(['a', 'b', 'c'], ['c', 'b']), Metrics::firstRank(['a'], ['z'])], [2, null]);
Check::same('hit@k', [Metrics::hit(2, 2), Metrics::hit(3, 2), Metrics::hit(null, 5)], [true, false, false]);
Check::same('обратный ранг', [Metrics::reciprocal(4), Metrics::reciprocal(null)], [0.25, 0.0]);
Check::same('recall@k', [Metrics::recall(['a', 'b', 'c'], ['a', 'c'], 2), Metrics::recall(['a'], [], 5)], [0.5, 0.0]);

Check::group('кейсы');

$parsed = Runner::cases([
	['id' => 'ok', 'question' => 'Как квоту?', 'expect' => ['quota']],
	['id' => 'нет статьи', 'question' => 'x', 'expect' => ['missing']],
	['id' => 'пусто', 'question' => ' ', 'expect' => ['quota']],
	'мусор',
], ['quota', 'calls']);
Check::same('годные и ошибки', [array_column($parsed['cases'], 'id'), count($parsed['errors'])], [['ok'], 3]);

Check::group('поиск note в памяти');

$files = [
	'quota' => "# Квота\n\n## Как настроить квоту\n\nКвоту задают в настройках модуля: сумма в месяц.\n\n## История\n\nРаньше лимитов не было.",
	'calls' => "# Звонки\n\nЗвонки распознаются. Квота квота квота квота квота упоминается часто, настройки тоже настройки.\n\n## Записи\n\nЗапись скачивается с портала.",
	'deals' => "# Сделки\n\nАнализ сделок по профилям.",
];
$note = new MemoryNote($files);
Check::same('все слова началом слова, по числу совпадений', array_column($note->search(['квот', 'настро'], 10), 'id'), [2, 1]);
Check::same('нет слова — нет документа', $note->search(['сделк', 'квот'], 10), []);
Check::same('дерево и документ для индекса', [count($note->getCollectionTree(1)), $note->getDocument(1)['title'], $note->getCollectionTree(2)], [3, 'Квота', []]);

Check::group('прогон');

$result = Runner::run($files, [['id' => 'q', 'question' => 'Как настроить квоту?', 'expect' => ['quota']]], 2);
Check::same('note: чаще слова — выше; rag: раздел с ответом — выше', [$result['cases'][0]['note']['found'], $result['cases'][0]['rag']['found']], [['calls', 'quota'], ['quota', 'calls']]);
Check::same('MRR', [$result['modes']['note']['mrr'], $result['modes']['rag']['mrr']], [0.5, 1.0]);
Check::same('отчёт: таблица путей и кейсов', [str_contains(Runner::report($result, 3), '| rag | 1 | 1 | 1 |'), str_contains(Runner::report($result, 3), '| q | 2 | 1 |')], [true, true]);
$dir = sys_get_temp_dir().'/reteval_'.uniqid();
mkdir($dir);
file_put_contents($dir.'/b.md', '# Б');
file_put_contents($dir.'/a.md', '# А');
file_put_contents($dir.'/skip.txt', 'x');
Check::same('корпус: *.md по имени', array_keys(Runner::corpus($dir)), ['a', 'b']);

Check::finish();
