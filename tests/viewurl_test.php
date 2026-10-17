<?php declare(strict_types=1);

/**
 * Поле viewUrl пакета: Import\ViewUrl, проверка в SourceValidator, запись в
 * таблицу соответствий (Importer) и ссылка статьи в KbSearchService.
 */

require_once __DIR__.'/assert.php';
require_once __DIR__.'/stub/autoload.php';

use Shef\ToolsAiKb\Import\ArticleParser;
use Shef\ToolsAiKb\Import\Importer;
use Shef\ToolsAiKb\Import\Source;
use Shef\ToolsAiKb\Import\SourceValidator;
use Shef\ToolsAiKb\Import\ViewUrl;
use Shef\ToolsAiKb\Public\Service\KbSearchService;

Check::group('ViewUrl::build');

$t = '/bitrix/tools/shef.toolsai/kb.php?page=guide/{path}&s={slug}';
Check::same('путь без .md и имя', ViewUrl::build($t, 'setup/quota.md'), '/bitrix/tools/shef.toolsai/kb.php?page=guide/setup/quota&s=quota');
Check::same('части кодируются, / остаются', ViewUrl::build('/kb/{path}', 'а б/x&y.md'), '/kb/'.rawurlencode('а б').'/x%26y');
Check::same('пустой шаблон — пусто', ViewUrl::build('', 'a.md'), '');
Check::same('страница пакета — пусто', ViewUrl::build($t, ''), '');
Check::same('слишком длинный итог — пусто', ViewUrl::build('/{path}', str_repeat('a', 600).'.md'), '');

Check::group('ViewUrl::isValidTemplate');

foreach(['/kb/{path}', '/x?p={slug}', '/a/{path}/{slug}'] as $ok)
{
	Check::same('годится: '.$ok, ViewUrl::isValidTemplate($ok), true);
}
foreach([
	'без подстановки' => '/kb/page',
	'относительный' => 'kb/{path}',
	'чужой сайт //' => '//evil.example/{path}',
	'схема' => 'https://evil.example/{path}',
	'javascript' => 'javascript:alert(1)//{path}',
	'обратный слэш' => '/\\evil.example/{path}',
	'пробел' => '/kb /{path}',
	'перевод строки' => "/kb\n/{path}",
	'не строка' => ['/kb/{path}'],
	'пусто' => '',
	'длиннее 255' => '/'.str_repeat('a', 255).'{path}',
] as $what => $bad)
{
	Check::same('отклонён: '.$what, ViewUrl::isValidTemplate($bad), false);
}

Check::group('SourceValidator: viewUrl');

$validator = new SourceValidator(static fn(string $dir): bool => true);
$ok = static fn(array $item): array => ['moduleId' => 'shef.toolsai', 'success' => true, 'parameters' => ['sources' => [$item + ['code' => 'shef.toolsai:guide', 'title' => 'Т', 'dir' => '/kb']]]];
$r = $validator->validate([$ok(['viewUrl' => '/kb/{path}'])]);
Check::same('viewUrl принят', [$r['sources'][0]->viewUrl, $r['errors']], ['/kb/{path}', []]);
$r = $validator->validate([$ok([])]);
Check::same('без viewUrl — пусто', [$r['sources'][0]->viewUrl, $r['errors']], ['', []]);
$r = $validator->validate([$ok(['viewUrl' => 'https://evil.example/{path}'])]);
Check::same('чужой адрес — пакет отклонён', [count($r['sources']), count($r['errors'])], [0, 1]);

Check::group('Importer: адрес статьи в таблице');

$articles = [ArticleParser::parse('setup/quota.md', "# Квота\nтекст"), ArticleParser::parse('b.md', "# Бета\nтекст")];
$reader = static fn(string $dir): array => ['articles' => $articles, 'errors' => []];
$note = new FakeNote();
$map = new FakeMapping();
$importer = new Importer($note, $map, 7);
$source = new Source('shef.toolsai:guide', 'Справка', '/kb', 'shef.toolsai', '/kb/{path}');
$importer->run([$source], 0, 'ИИ', $reader);
Check::same('статья — адрес по шаблону', $map->get('shef.toolsai:guide', 'setup/quota.md')['viewUrl'], '/kb/setup/quota');
Check::same('страница пакета — без адреса', $map->get('shef.toolsai:guide', '')['viewUrl'], '');

$map->saves = [];
$importer->run([$source], 1, 'ИИ', $reader);
Check::same('повтор без изменений ничего не пишет', $map->saves, []);

$importer->run([new Source('shef.toolsai:guide', 'Справка', '/kb', 'shef.toolsai', '/new/{slug}')], 1, 'ИИ', $reader);
Check::same('сменился шаблон — адрес обновлён без перезаписи note', $map->get('shef.toolsai:guide', 'setup/quota.md')['viewUrl'], '/new/quota');

$quotaId = $map->get('shef.toolsai:guide', 'setup/quota.md')['documentId'];
$note->documents[$quotaId]['markdown'] = 'правка руками';
$importer->run([$source], 1, 'ИИ', $reader);
Check::same('правлена руками — адрес убран', $map->get('shef.toolsai:guide', 'setup/quota.md')['viewUrl'], '');
Check::same('остальные — по шаблону', $map->get('shef.toolsai:guide', 'b.md')['viewUrl'], '/kb/b');

Check::group('KbSearchService: url и noteUrl');

$bId = $map->get('shef.toolsai:guide', 'b.md')['documentId'];
$hit = static fn(int $id): array => ['id' => $id, 'collectionId' => 1, 'title' => 'С'.$id, 'snippet' => '', 'score' => 1.0];
$note->searchAnswers['квот'] = [$hit($bId), $hit($quotaId), $hit(999)];
$found = KbSearchService::findWith($note, 'квота', 5, 1, $map);
Check::same('статья пакета с viewUrl — в шаблон модуля', [$found[0]['url'], $found[0]['noteUrl']], ['/kb/b', '/note/document/'.$bId.'/']);
Check::same('правлена руками — в note', $found[1]['url'], '/note/document/'.$quotaId.'/');
Check::same('чужой документ — в note', $found[2]['url'], '/note/document/999/');
$found = KbSearchService::findWith($note, 'квота', 5, 1);
Check::same('без таблицы — все в note', $found[0]['url'], '/note/document/'.$bId.'/');

Check::finish();
