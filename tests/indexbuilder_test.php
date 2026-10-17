<?php declare(strict_types=1);

/**
 * Search\IndexBuilder и Import\Runner::reindex(): все документы раздела, архив и
 * чужой раздел — нет, сбой — в отчёте без потери импорта.
 */

require_once __DIR__.'/assert.php';
require_once __DIR__.'/stub/autoload.php';

use Shef\ToolsAiKb\Import\ImportReport;
use Shef\ToolsAiKb\Import\Runner;
use Shef\ToolsAiKb\Search\IndexBuilder;

$note = new FakeNote();
$note->collections[1] = FakeNote::COLLECTION_OK;
$page = $note->createDocument(1, null, 'Пакет', 'Статьи раздела ниже.', 7);
$note->createDocument(1, $page, 'Квота', "## Предел\n\nМесячный.\n\n## Перерасход\n\nОтказ.", 7);
$manual = $note->createDocument(1, $page, 'Добавлено человеком', 'Свой текст.', 7);
$archived = $note->createDocument(1, $page, 'Архивная', 'Старое.', 7);
$note->documents[$archived]['archived'] = true;
$note->createDocument(2, null, 'Чужой раздел', 'Не наше.', 7);
$note->createDocument(1, $page, 'Пустая', '', 7);

$store = new FakeChunkStore();
$built = (new IndexBuilder($note, $store))->rebuild(1);
Check::same('итог', $built, ['documents' => 3, 'chunks' => 4, 'errors' => []]);
Check::same('документы раздела, и добавленный человеком; архив, чужие и пустые — нет', array_values(array_unique(array_column($store->rows, 'title'))), ['Пакет', 'Квота', 'Добавлено человеком']);
Check::same('фрагмент: документ, заголовок статьи и раздела, текст', $store->rows[2], ['documentId' => 2, 'title' => 'Квота', 'heading' => 'Перерасход', 'text' => 'Отказ.']);

$note->documents[$manual]['markdown'] = 'Правлено руками.';
(new IndexBuilder($note, $store))->rebuild(1);
Check::same('пересборка заменяет целиком и берёт текст из note', [$store->replaces, in_array('Правлено руками.', array_column($store->rows, 'text'), true), count($store->rows)], [2, true, 4]);

Check::group('Runner::reindex');

$r = new ImportReport();
Check::same('раздела нет — не пересобирается', [Runner::reindex($r, $note, $store), $store->replaces], [null, 2]);
$r->collectionId = 1;
$version = Runner::reindex($r, $note, $store);
Check::same('метка: время и число фрагментов', (bool)preg_match('/^\d{14}\.4$/', (string)$version), true);
Check::same('число фрагментов — в отчёте и строке итога', [$r->indexChunks, str_contains($r->summary(), 'фрагментов в индексе: 4'), $r->toArray('t')['indexChunks']], [4, true, 4]);

$broken = new FakeChunkStore();
$broken->fail = true;
$r = new ImportReport();
$r->collectionId = 1;
Check::same('сбой таблицы — метка не меняется, ошибка в отчёте', [Runner::reindex($r, $note, $broken), $r->errors], [null, ['индекс поиска не пересобран: сбой таблицы']]);

Check::finish();
