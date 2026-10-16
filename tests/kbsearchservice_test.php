<?php declare(strict_types=1);

/**
 * Public\Service\KbSearchService: вопрос -> слова -> поиск note -> статьи.
 */

require_once __DIR__.'/assert.php';
require_once __DIR__.'/stub/autoload.php';

use Shef\ToolsAiKb\Public\Service\KbSearchService;

$hit = static fn(int $id, string $snippet = ''): array => ['id' => $id, 'title' => 'Статья '.$id, 'snippet' => $snippet, 'score' => 1.0];

Check::group('точный поиск набрал лимит');

$note = new FakeNote();
$note->searchAnswers['настро квот'] = [$hit(1, '<b>Квота</b> расхода'), $hit(2)];
$found = KbSearchService::findWith($note, 'Как настроить квоту?', 2);
Check::same('в note ушли основы без стоп-слов, с лимитом', $note->calls, ['search настро квот 2']);
Check::same('форма ответа', $found[0], ['id' => 1, 'title' => 'Статья 1', 'url' => '/note/document/1/', 'snippet' => 'Квота расхода']);
Check::same('две статьи', count($found), 2);

Check::group('добор по словам');

$note = new FakeNote();
$note->searchAnswers['настро квот'] = [$hit(5)];
$note->searchAnswers['настро'] = [$hit(7), $hit(5)];
$note->searchAnswers['квот'] = [$hit(8), $hit(5), $hit(7)];
$found = KbSearchService::findWith($note, 'Как настроить квоту?', 5);
Check::same('сначала все слова, потом каждое', $note->calls, ['search настро квот 5', 'search настро 5', 'search квот 5']);
Check::same('точная выше, затем по числу слов', array_column($found, 'id'), [5, 7, 8]);

$note = new FakeNote();
$note->searchAnswers['квот'] = [];
KbSearchService::findWith($note, 'квота', 5);
Check::same('одно слово — без добора', $note->calls, ['search квот 5']);

Check::group('границы');

$note = new FakeNote();
Check::same('пустой вопрос — без запроса в note', [KbSearchService::findWith($note, 'как и что', 5), $note->calls], [[], []]);
KbSearchService::findWith($note, 'квота', 500);
Check::same('лимит не больше 20', $note->calls, ['search квот 20']);
$note->calls = [];
KbSearchService::findWith($note, 'квота', -1);
Check::same('лимит не меньше 1', $note->calls, ['search квот 1']);
Check::same('find() без слов не трогает note', KbSearchService::find('и в на'), []);

Check::finish();
