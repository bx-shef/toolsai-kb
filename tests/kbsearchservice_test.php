<?php declare(strict_types=1);

/**
 * Public\Service\KbSearchService: вопрос -> слова -> поиск note -> статьи.
 */

require_once __DIR__.'/assert.php';
require_once __DIR__.'/stub/autoload.php';

use Shef\ToolsAiKb\Public\Service\KbSearchService;

$hit = static fn(int $id, string $snippet = '', int $collectionId = 1): array => ['id' => $id, 'collectionId' => $collectionId, 'title' => 'Статья '.$id, 'snippet' => $snippet, 'score' => 1.0];

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

Check::group('добор — только по трём самым длинным словам');

$note = new FakeNote();
KbSearchService::findWith($note, 'квота расходы менеджеров звонков сделки', 5);
Check::same('всё сразу + три запроса', $note->calls, ['search квот расход менеджер звонк сделк 5', 'search менеджер 5', 'search расход 5', 'search звонк 5']);
Check::same('longest: при равной длине — порядок вопроса', KbSearchService::longest(['абв', 'где', 'жзийк'], 2), ['жзийк', 'абв']);

Check::group('где искать');

Check::same('по умолчанию — свой раздел', KbSearchService::scope('N', 12), 12);
Check::same('раздела нет — негде', KbSearchService::scope('N', 0), 0);
Check::same('KB_searchall = Y — везде', KbSearchService::scope('Y', 12), null);

$note = new FakeNote();
$note->searchAnswers['квот'] = [$hit(1, '', 12), $hit(2, '', 99), $hit(3, '', 0), $hit(4, '', 12)];
Check::same('свой раздел — чужие и личные прочь', array_column(KbSearchService::findWith($note, 'квота', 5, 12), 'id'), [1, 4]);
Check::same('у note просится с запасом', $note->calls, ['search квот 20']);
Check::same('везде — всё', array_column(KbSearchService::findWith($note, 'квота', 5, null), 'id'), [1, 2, 3, 4]);
$note->calls = [];
Check::same('раздел 0 — пусто и без запроса', [KbSearchService::findWith($note, 'квота', 5, 0), $note->calls], [[], []]);

Check::group('кеш');

Check::same('ключ зависит от пользователя', KbSearchService::cacheKey(1, 'q', 5, 12) === KbSearchService::cacheKey(2, 'q', 5, 12), false);
Check::same('от лимита', KbSearchService::cacheKey(1, 'q', 5, 12) === KbSearchService::cacheKey(1, 'q', 6, 12), false);
Check::same('от области', KbSearchService::cacheKey(1, 'q', 5, 12) === KbSearchService::cacheKey(1, 'q', 5, null), false);
Check::same('от запроса', KbSearchService::cacheKey(1, 'q', 5, 12) === KbSearchService::cacheKey(1, 'w', 5, 12), false);
Check::same('одинаковый — одинаковый', KbSearchService::cacheKey(1, 'q', 5, 12), KbSearchService::cacheKey(1, 'q', 5, 12));

Check::finish();
