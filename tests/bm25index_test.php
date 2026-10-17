<?php declare(strict_types=1);

/**
 * Search\Bm25Index: ранжирование фрагментов среди кандидатов, заголовок весомее,
 * фрагменты в порядке статьи и в бюджете, кеш туда и обратно.
 */

require_once __DIR__.'/assert.php';
require_once __DIR__.'/stub/autoload.php';

use Shef\ToolsAiKb\Search\Bm25Index;

$row = static fn(int $doc, string $title, string $heading, string $text): array => ['documentId' => $doc, 'title' => $title, 'heading' => $heading, 'text' => $text];
$rows = [
	$row(1, 'Квота', '', 'Месячный предел расхода на модели.'),
	$row(1, 'Квота', 'Как считается', 'Расход считается по журналу: успешные задания и свежие в работе.'),
	$row(1, 'Квота', 'Перерасход', 'При перерасходе задания получают отказ quota_exceeded.'),
	$row(2, 'Распознавание звонков', '', 'Запись звонка уходит на сервер распознавания.'),
	$row(3, 'Письма', '', 'Резюме входящих писем и дела менеджеру. Расход тоже в журнале.'),
];
$index = Bm25Index::build($rows);

Check::group('ранжирование');

$ranked = $index->rank('Как считается расход квоты?', [1, 2, 3], 1500);
Check::same('статья с вопросом в заголовке — первой, без совпадений — нет', array_keys($ranked), [1, 3]);
Check::same('лучшие фрагменты — в порядке статьи', array_column($ranked[1]['fragments'], 'heading'), ['', 'Как считается', 'Перерасход']);
Check::same('кандидаты ограничивают выдачу (права)', array_keys($index->rank('расход квоты', [3], 1500)), [3]);
Check::same('нет кандидатов — пусто', $index->rank('расход', [], 1500), []);
Check::same('вопрос из стоп-слов — пусто', $index->rank('как и что', [1, 2, 3], 1500), []);
Check::same('заголовок весомее текста', array_key_first($index->rank('звонки', [1, 2, 3], 1500)), 2);

Check::group('бюджет');

$small = $index->rank('расход квота перерасход журнал', [1], 70);
Check::same('в бюджете — сколько влезает, но хотя бы один', count($small[1]['fragments']), 1);
Check::same('фрагмент не длиннее бюджета', mb_strlen($small[1]['fragments'][0]['text']) <= 70, true);
Check::same('не больше трёх фрагментов', count($index->rank('расход квота перерасход журнал отказ модели', [1], 99999, 2)[1]['fragments']), 2);

Check::group('кеш');

$again = Bm25Index::fromArray($index->toArray());
Check::same('из кеша — то же ранжирование', $again->rank('расход квоты', [1, 2, 3], 1500), $index->rank('расход квоты', [1, 2, 3], 1500));
Check::same('сломанный кеш — пустой индекс', [Bm25Index::fromArray('x')->size(), Bm25Index::fromArray(['chunks' => 1])->size()], [0, 0]);
Check::same('размер и документы', [$index->size(), $index->hasDocument(2), $index->hasDocument(9)], [5, true, false]);
Check::same('пустой индекс — пусто', Bm25Index::build([])->rank('расход', [1], 100), []);

Check::finish();
