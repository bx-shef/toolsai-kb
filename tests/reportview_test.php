<?php declare(strict_types=1);

/**
 * Settings\ReportView: сводка последнего импорта для страницы настроек.
 */

require_once __DIR__.'/assert.php';
require_once __DIR__.'/stub/autoload.php';

use Shef\ToolsAiKb\Import\ImportReport;
use Shef\ToolsAiKb\Import\Importer;
use Shef\ToolsAiKb\Import\Planner;
use Shef\ToolsAiKb\Import\Runner;
use Shef\ToolsAiKb\Settings\ReportView;

$r = new ImportReport();
$r->sources = 2;
$r->count(Planner::CREATE);
$r->count(Planner::RECREATE);
$r->count(Planner::UNCHANGED);
$r->count(Planner::MANUAL);
$r->count(Importer::OBSOLETE_KEPT);
$r->count(Planner::DELETE);
$r->manualItems[] = ['label' => 'shef.toolsai:guide/x.md', 'title' => 'Квота <b>', 'documentId' => 12];
$r->manualItems[] = ['label' => 'shef.toolsai:guide/y.md', 'title' => '  ', 'documentId' => 0];
$r->kept[] = 'shef.toolsai:guide: страница оставлена';
$r->errors[] = 'сбой';

$view = ReportView::fromStored(Runner::reportJson($r, '2026-10-17 10:00:00'));
$byCode = array_column($view['counters'], null, 'code');

Check::group('сохранённый итог');
Check::same('время и пакеты', [$view['empty'], $view['at'], $view['sources']], [false, '2026-10-17 10:00:00', 2]);
Check::same('порядок плиток', array_column($view['counters'], 'code'), array_keys(ReportView::COUNTERS));
Check::same('создано = create + recreate', $byCode['created']['value'], 2);
Check::same('правлены руками = в пакете + пропавшие', $byCode['manual']['value'], 2);
Check::same('удалено в корзину', $byCode['deleted']['value'], 1);
Check::same('ошибки — по списку', $byCode['errors']['value'], 1);
Check::same('нули приглушены, ненули нет', [$byCode['updated']['muted'], $byCode['archived']['muted'], $byCode['created']['muted']], [true, true, false]);
Check::same('правленные — название и ссылка на документ (сырые, экранирует вывод)', $view['manual'][0], ['title' => 'Квота <b>', 'url' => '/note/document/12/']);
Check::same('без названия — путь, без документа — без ссылки', $view['manual'][1], ['title' => 'shef.toolsai:guide/y.md', 'url' => '']);
Check::same('оставленные и ошибки', [$view['kept'], $view['errors']], [['shef.toolsai:guide: страница оставлена'], ['сбой']]);

Check::group('пусто и старый формат');
$empty = ReportView::fromStored('');
Check::same('импорта не было', [$empty['empty'], $empty['legacy']], [true, '']);
$legacy = ReportView::fromStored('2026-10-16 10:00:00 — пакетов: 1, создано: 0');
Check::same('строка 1.0/1.1 — как есть', [$legacy['empty'], $legacy['legacy']], [true, '2026-10-16 10:00:00 — пакетов: 1, создано: 0']);
$junk = ReportView::fromStored('{"at":"x","counts":"bad","manual":[1,{"title":"T","documentId":"3"}],"errors":[["a"],"e"]}');
Check::same('мусор в JSON не роняет', [$junk['counters'][0]['value'], $junk['manual'], $junk['errors']], [0, [['title' => 'T', 'url' => '/note/document/3/']], ['e']]);

Check::finish();
