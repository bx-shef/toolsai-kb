<?php declare(strict_types=1);

/**
 * Import\SourceValidator и Import\SourceCollector: ответы на OnKbSourcesCollect.
 */

require_once __DIR__.'/assert.php';
require_once __DIR__.'/stub/autoload.php';

use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Shef\ToolsAiKb\Import\SourceCollector;
use Shef\ToolsAiKb\Import\SourceValidator;

$validator = new SourceValidator(static fn(string $dir): bool => $dir !== '/nope');
$ok = static fn(string $module, array $sources): array => ['moduleId' => $module, 'success' => true, 'parameters' => ['sources' => $sources]];
$item = static fn(string $code, string $title = 'ИИ: Справка', string $dir = '/var/kb'): array => ['code' => $code, 'title' => $title, 'dir' => $dir];

Check::group('принимается');

$r = $validator->validate([$ok('shef.toolsai', [$item('shef.toolsai:guide', ' ИИ: Справка ', '/var/kb/')])]);
Check::same('один пакет', count($r['sources']), 1);
Check::same('код', $r['sources'][0]->code, 'shef.toolsai:guide');
Check::same('название без пробелов по краям', $r['sources'][0]->title, 'ИИ: Справка');
Check::same('каталог без / в конце', $r['sources'][0]->dir, '/var/kb');
Check::same('модуль', $r['sources'][0]->moduleId, 'shef.toolsai');
Check::same('ошибок нет', $r['errors'], []);

Check::group('отклоняется');

$cases = [
	'чужой префикс кода' => [$ok('shef.toolsai', [$item('shef.other:guide')])],
	'код без имени' => [$ok('shef.toolsai', [$item('shef.toolsai:')])],
	'код с заглавными' => [$ok('shef.toolsai', [$item('shef.toolsai:Guide')])],
	'код длиннее 100' => [$ok('shef.toolsai', [$item('shef.toolsai:'.str_repeat('a', 100))])],
	'пустое название' => [$ok('shef.toolsai', [$item('shef.toolsai:guide', '  ')])],
	'относительный путь' => [$ok('shef.toolsai', [$item('shef.toolsai:guide', 'Т', 'kb')])],
	'нет каталога' => [$ok('shef.toolsai', [$item('shef.toolsai:guide', 'Т', '/nope')])],
	'не SUCCESS' => [['moduleId' => 'shef.toolsai', 'success' => false, 'parameters' => ['sources' => [$item('shef.toolsai:guide')]]]],
	'без модуля' => [$ok('', [$item(':guide')])],
	'нет sources' => [['moduleId' => 'shef.toolsai', 'success' => true, 'parameters' => ['x' => 1]]],
	'sources не список' => [['moduleId' => 'shef.toolsai', 'success' => true, 'parameters' => ['sources' => ['a' => $item('shef.toolsai:guide')]]]],
	'пакет не массив' => [$ok('shef.toolsai', ['строка'])],
];
foreach($cases as $what => $responses)
{
	$r = $validator->validate($responses);
	Check::same($what.': пакетов нет, одна ошибка', [count($r['sources']), count($r['errors'])], [0, 1]);
}

$r = $validator->validate([$ok('shef.toolsai', [$item('shef.toolsai:guide', 'Первый'), $item('shef.toolsai:guide', 'Второй')])]);
Check::same('повтор кода — первый, повтор в ошибки', [$r['sources'][0]->title, count($r['sources']), count($r['errors'])], ['Первый', 1, 1]);

Check::group('SourceCollector: событие');

Event::$handlers['shef.toolsaikb:OnKbSourcesCollect'] = [
	static fn(Event $e): EventResult => new EventResult(EventResult::SUCCESS, ['sources' => [$item('shef.toolsai:guide', 'Справка', __DIR__)]], 'shef.toolsai'),
	static fn(Event $e): EventResult => new EventResult(EventResult::ERROR, null, 'shef.broken'),
];
$r = SourceCollector::collect();
Check::same('пакет из события', array_map(static fn($s) => $s->code, $r['sources']), ['shef.toolsai:guide']);
Check::same('ERROR — в ошибки', count($r['errors']), 1);

Event::$handlers['shef.toolsaikb:OnKbSourcesCollect'] = [static function(): never { throw new RuntimeException('упал'); }];
$r = SourceCollector::collect();
Check::same('исключение обработчика — ошибка, не падение', [count($r['sources']), str_contains($r['errors'][0], 'упал')], [0, true]);
Event::$handlers = [];

Check::finish();
