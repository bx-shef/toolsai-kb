<?php declare(strict_types=1);

/**
 * Import\Runner — чистая часть: название раздела, ошибки сбора, что писать в
 * настройку ID раздела, строка итога.
 */

require_once __DIR__.'/assert.php';
require_once __DIR__.'/stub/autoload.php';

use Shef\ToolsAiKb\Import\ImportReport;
use Shef\ToolsAiKb\Import\Runner;

Check::same('название как есть', Runner::collectionName(' Справка '), 'Справка');
Check::same('пустое — по умолчанию', Runner::collectionName('   '), 'ИИ: База знаний');
Check::same('не длиннее 255', mb_strlen(Runner::collectionName(str_repeat('я', 300))), 255);

$r = new ImportReport();
$r->errors = ['импорт'];
Check::same('ошибки сбора — первыми', Runner::withCollectErrors($r, ['сбор 1', 'сбор 2'])->errors, ['сбор 1', 'сбор 2', 'импорт']);

$r = new ImportReport();
Check::same('раздела нет — ID не трогать', Runner::collectionOption($r), null);
$r->collectionId = 42;
Check::same('раздел есть — записать', Runner::collectionOption($r), '42');

Check::same('строка итога', str_starts_with(Runner::reportLine($r, '2026-10-16 10:00:00'), '2026-10-16 10:00:00 — пакетов: 0'), true);

Check::finish();
