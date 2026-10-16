<?php declare(strict_types=1);

/**
 * Controller\Search: фильтры ядра не переопределены, длинный запрос — отказ,
 * лимит в пределах, сбой поиска — ошибка без подробностей.
 */

require_once __DIR__.'/assert.php';
require_once __DIR__.'/stub/autoload.php';

use Shef\ToolsAiKb\Controller\Search;

$src = (string)file_get_contents(dirname(__DIR__).'/lib/controller/search.php');
Check::same('configureActions не переопределён (фильтры ядра на месте)', str_contains($src, 'function configureActions'), false);
Check::same('prefilters нигде не задан', (bool)preg_match("/['\"][+-]?prefilters['\"]/", $src), false);
Check::same('действие find', method_exists(Search::class, 'findAction'), true);

$c = new Search();
Check::same('длинный запрос — null', $c->findAction(str_repeat('а', Search::QUERY_MAX + 1)), null);
Check::same('и ошибка', $c->getErrors()[0]->getMessage(), 'Слишком длинный запрос');

$c = new Search();
Check::same('пробелы по краям не в счёт длины', $c->findAction('  '.str_repeat('и ', 499).'  '), ['articles' => []]);
Check::same('ровно на границе — без ошибки', $c->getErrors(), []);

Check::same('лимит снизу', Search::limit(-5), 1);
Check::same('лимит сверху', Search::limit(500), 20);
Check::same('лимит как есть', Search::limit(7), 7);

Check::finish();
