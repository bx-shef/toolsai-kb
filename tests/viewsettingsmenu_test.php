<?php declare(strict_types=1);

/**
 * Пункт «ИИ база знаний» в меню шестерёнки вида «ИИ» shef.toolsai
 * (OnViewSettingsMenuBuild, 1.1.2) и подписки после обновления копированием
 * (Main\Maintenance).
 */

require_once __DIR__.'/assert.php';
require_once __DIR__.'/stub/autoload.php';

use Bitrix\Main\Config\Option;
use Bitrix\Main\Event;
use Bitrix\Main\EventManager;
use Bitrix\Main\EventResult;
use Shef\ToolsAiKb\Internals\Integration\ToolsAi\EventHandler\OnViewSettingsMenuBuildEventHandler as Handler;
use Shef\ToolsAiKb\Main\Constants;
use Shef\ToolsAiKb\Main\Maintenance;

$root = dirname(__DIR__);

Check::group('пункт меню');
$item = ['text' => 'ИИ база знаний', 'url' => '/bitrix/admin/settings.php?mid=shef.toolsaikb&lang=ru'];
Check::same('право R — пункт', Handler::items('R'), [$item]);
Check::same('право W — пункт', Handler::items('W'), [$item]);
Check::same('право D — пусто', Handler::items('D'), []);
Check::same('годен для вида: путь портала, текст до 60', [(bool)preg_match('~^/(?![/\\\\])[^\s"\'<>\\\\]*$~', $item['url']), mb_strlen($item['text']) <= 60], [true, true]);

$GLOBALS['APPLICATION'] = new class {
	public function GetGroupRight(string $module): string { return $module === 'shef.toolsaikb' ? 'R' : 'D'; }
};
$result = Handler::handle(new Event('shef.toolsai', 'OnViewSettingsMenuBuild', ['entityTypeId' => 2, 'categoryId' => 0]));
Check::same('handle: SUCCESS от модуля с пунктом', [$result->getType(), $result->getModuleId(), $result->getParameters()['items']], [EventResult::SUCCESS, Constants::MODULE_ID, [$item]]);
$GLOBALS['APPLICATION'] = null;
Check::same('нет $APPLICATION — пусто', Handler::handle(new Event('shef.toolsai', 'OnViewSettingsMenuBuild'))->getParameters()['items'], []);
$GLOBALS['APPLICATION'] = new class {
	public function GetGroupRight(string $module): string { throw new \RuntimeException('сбой'); }
};
Check::same('сбой — пусто', Handler::handle(new Event('shef.toolsai', 'OnViewSettingsMenuBuild'))->getParameters()['items'], []);
unset($GLOBALS['APPLICATION']);

Check::group('подписка');
$settings = require $root.'/.settings.php';
$events = $settings['installEvents']['value'];
Check::same('одна подписка — OnViewSettingsMenuBuild', array_map(static fn(array $e): string => $e['from']['module'].':'.$e['from']['event'].'→'.$e['to']['module'], $events), ['shef.toolsai:OnViewSettingsMenuBuild→shef.toolsaikb']);
Check::same('класс и статический метод есть', (new ReflectionMethod(ltrim($events[0]['to']['class'], '\\'), $events[0]['to']['function']))->isStatic(), true);
$installer = (string)file_get_contents($root.'/install/index.php');
Check::same('установщик ставит и снимает по installEvents', [str_contains($installer, "get('installEvents')"), str_contains($installer, '$this->InstallEvents();'), str_contains($installer, '$this->UnInstallEvents();')], [true, true, true]);

Check::group('самовосстановление');
Check::same('версия отстала — нужно', Maintenance::isNeeded('1.1.1', '1.1.2'), true);
Check::same('совпала — не нужно', Maintenance::isNeeded('1.1.2', '1.1.2'), false);
Check::same('версии нет — не нужно', Maintenance::isNeeded('', ''), false);
Option::set(Constants::MODULE_ID, Maintenance::OPTION_VERSION, '1.1.1');
EventManager::$registered = [];
Maintenance::ensure();
Check::same('после обновления — подписка поставлена', array_map(static fn(array $r): string => $r[0].':'.$r[1], EventManager::$registered), ['shef.toolsai:OnViewSettingsMenuBuild']);
Check::same('версия записана', Option::get(Constants::MODULE_ID, Maintenance::OPTION_VERSION), Maintenance::moduleVersion($root));
EventManager::$registered = [];
Maintenance::ensure();
Check::same('повторно — ничего', EventManager::$registered, []);
$agent = (string)file_get_contents($root.'/lib/agent/refresh.php').(string)file_get_contents($root.'/options.php');
Check::same('зовут агент и страница настроек', substr_count($agent, 'Maintenance::ensure();'), 2);

Check::finish();
