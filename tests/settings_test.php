<?php declare(strict_types=1);

/**
 * Обвязка модуля: зависимости, контроллеры, установщик, настройки, языковые
 * файлы. Литералы установщика обязаны совпасть с Main\Constants: при удалении
 * автозагрузки модуля уже нет, и разойтись им ничто не мешает, кроме этого теста.
 */

require_once __DIR__.'/assert.php';
require_once __DIR__.'/stub/autoload.php';

use Shef\ToolsAiKb\Main\Constants;
use Shef\ToolsAiKb\Public\Event\OnKbSourcesCollectEvent;

$root = dirname(__DIR__);
$settings = require $root.'/.settings.php';

Check::group('.settings.php');

Check::same('зависимости линейки', $settings['requireModules']['value'], ['shef.options', 'shef.problems']);
Check::same('все ключи readonly', array_unique(array_column($settings, 'readonly')), [true]);
Check::same('контроллеры — Shef\ToolsAiKb\Controller', $settings['controllers']['value']['defaultNamespace'], '\\Shef\\ToolsAiKb\\Controller');
Check::same('каталог контроллеров есть (иначе ajax — 404)', is_file($root.'/lib/controller/search.php'), true);
Check::same('событий не ставит', $settings['installEvents']['value'], []);

Check::group('установщик');

$installer = (string)file_get_contents($root.'/install/index.php');
Check::same('note — в установщике', str_contains($installer, "'note' => '26.250.0'"), true);
Check::same('версии линейки', [str_contains($installer, "'shef.options' => '3.0.0'"), str_contains($installer, "'shef.problems' => '2.0.0'")], [true, true]);
Check::same('группа блокировки = Constants', str_contains($installer, "LOCK_GROUP = '".Constants::LOCK_GROUP."'"), true);
Check::same('агент = Constants', str_contains($installer, "AGENT_NAME = '".str_replace('\\', '\\\\', Constants::AGENT_NAME)."'"), true);
Check::same('интервал агента = Constants', str_contains($installer, 'AGENT_INTERVAL = '.Constants::AGENT_INTERVAL.';'), true);
Check::same('класс агента существует', method_exists('Shef\\ToolsAiKb\\Agent\\Refresh', 'run'), true);
preg_match("/OPTIONS_TO_DELETE = \\[([^\\]]*)\\]/", $installer, $del);
$deleted = array_map(static fn(string $x): string => trim($x, " '"), explode(',', $del[1] ?? ''));
$shef_toolsaikb_default_option = null;
require $root.'/default_option.php';
Check::same('удаление стирает все настройки, кроме ID раздела', $deleted, array_merge(array_keys($shef_toolsaikb_default_option), [Constants::OPTION_LAST_REPORT]));
Check::same('ID раздела переживает удаление', in_array(Constants::OPTION_COLLECTION_ID, $deleted, true), false);
Check::same('таблица соответствий переживает удаление', str_contains($installer, 'MappingTable::drop'), false);
Check::same('класс установщика', str_contains($installer, 'class shef_toolsaikb'), true);

Check::group('настройки');

$shef_toolsaikb_default_option = null;
require $root.'/default_option.php';
Check::same('умолчания', $shef_toolsaikb_default_option, [
	Constants::OPTION_COLLECTION_NAME => Constants::DEFAULT_COLLECTION_NAME,
	Constants::OPTION_AGENT => 'Y',
	Constants::OPTION_SEARCH_ALL => 'N',
]);
$conf = (string)file_get_contents($root.'/options_conf.php');
Check::same('вкладка KB, опции collectionname и agent', [str_contains($conf, "Tab('KB')"), str_contains($conf, "Text('collectionname')"), str_contains($conf, "Checkbox('agent')"), str_contains($conf, "Checkbox('searchall')")], [true, true, true, true]);
Check::same('options_page.php — копия options.php shef.options (без своих правок)', str_contains((string)file_get_contents($root.'/options_page.php'), 'Shef\\ToolsAiKb'), false);

Check::group('языковые файлы');

$MESS = [];
foreach(glob($root.'/lang/ru/*.php') ?: [] as $f)
{
	require $f;
}
require $root.'/lang/ru/install/index.php';
foreach(['options.php' => '/SHEF_TOOLSAIKB_\w+/', 'options_conf.php' => '/_TAB_KB_\w+/', 'install/index.php' => '/\'(SH_\w+|shef\.toolsaikb_\w+)\'/'] as $file => $pattern)
{
	preg_match_all($pattern, (string)file_get_contents($root.'/'.$file), $m);
	foreach(array_unique($m[1] ?? $m[0]) as $key)
	{
		$key = str_starts_with($key, '_TAB_') ? 'shef.toolsaikb'.$key : $key;
		Check::same($file.': есть '.$key, isset($MESS[$key]), true);
	}
}

Check::group('событие');

$event = new OnKbSourcesCollectEvent();
Check::same('модуль и имя', [$event->getModuleId(), $event->getEventType()], ['shef.toolsaikb', 'OnKbSourcesCollect']);

Check::finish();
