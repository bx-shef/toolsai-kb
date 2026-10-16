<?php declare(strict_types=1);

/**
 * Note\BitrixNoteGateway против заглушек публичного API note 26.250
 * (stub/note.php): какие команды и с какими аргументами зовутся.
 */

require_once __DIR__.'/assert.php';
require_once __DIR__.'/stub/autoload.php';

use Bitrix\Main\Loader;
use Bitrix\Note\Internal\Exceptions\DocumentInRecycleBinException;
use Shef\ToolsAiKb\Note\BitrixNoteGateway;
use Shef\ToolsAiKb\Note\DocumentRemovedException;
use Shef\ToolsAiKb\Note\NoteGateway;

NoteStub::reset();
$g = new BitrixNoteGateway();

Check::group('раздел');

Check::same('ID 0 — нет', $g->getCollectionState(0), NoteGateway::COLLECTION_MISSING);
Check::same('нет такого', $g->getCollectionState(5), NoteGateway::COLLECTION_MISSING);
$id = $g->createCollection('ИИ: База знаний', 7);
Check::same('создан', $g->getCollectionState($id), NoteGateway::COLLECTION_OK);
Check::same('аргументы: все читают, автор — модератор', NoteStub::$calls[0][1], [
	'name' => 'ИИ: База знаний', 'createdBy' => 7, 'position' => 0, 'policyLevel' => 'view',
	'permissions' => [['subjectCode' => 'U7', 'level' => 'moderate']],
]);
NoteStub::$collections[$id] = new NoteStubEntity(['id' => $id, 'isArchived' => true]);
Check::same('в архиве', $g->getCollectionState($id), NoteGateway::COLLECTION_ARCHIVED);

Check::group('документ');

$doc = $g->createDocument($id, null, 'Квота', 'текст', 7);
$args = NoteStub::$calls[1][1];
Check::same('формат md, текст и автор', [$args['contentFormat'], $args['markdown'], $args['userId'], $args['parentId']], ['md', 'текст', 7, null]);
Check::same('прочитан', $g->getDocument($doc), ['archived' => false, 'format' => 'md', 'title' => 'Квота', 'markdown' => 'текст']);
Check::same('нет документа', $g->getDocument(9999), null);
NoteStub::$documents[555] = new NoteStubEntity(['id' => 555, 'isArchived' => false, 'contentFormat' => 'json', 'title' => 'Т', 'markdown' => ['не строка']]);
Check::same('текст не строкой — пустой', $g->getDocument(555)['markdown'], '');

$g->overwriteDocument($doc, 'Квота 2', 'текст 2', 7);
Check::same('перезапись без overwrite, с заголовком', NoteStub::$calls[2][1], ['documentId' => $doc, 'markdown' => 'текст 2', 'userId' => 7, 'overwrite' => false, 'title' => 'Квота 2']);

NoteStub::$overwriteThrows = new DocumentInRecycleBinException('в корзине');
Check::throws('корзина -> DocumentRemovedException', DocumentRemovedException::class, static fn() => $g->overwriteDocument($doc, 'Т', 'М', 7));
NoteStub::$overwriteThrows = new LogicException('другое');
Check::throws('прочий сбой не маскируется', \Bitrix\Main\Command\Exception\CommandException::class, static fn() => $g->overwriteDocument($doc, 'Т', 'М', 7));
NoteStub::$overwriteThrows = null;

Check::group('поиск');

NoteStub::$searchResults = [
	new NoteStubEntity(['documentId' => 3, 'collectionId' => 12, 'sharedAccess' => false, 'title' => 'Квота', 'snippet' => '<b>квот</b>а', 'score' => 2.5]),
	new NoteStubEntity(['documentId' => 4, 'collectionId' => 99, 'sharedAccess' => true, 'title' => 'Личное', 'snippet' => '', 'score' => 1.0]),
];
Check::same('результат; личный доступ — без раздела', $g->search(['настро', 'квот'], 4), [
	['id' => 3, 'collectionId' => 12, 'title' => 'Квота', 'snippet' => '<b>квот</b>а', 'score' => 2.5],
	['id' => 4, 'collectionId' => 0, 'title' => 'Личное', 'snippet' => '', 'score' => 1.0],
]);
Check::same('запрос — слова через пробел, первая страница', end(NoteStub::$calls)[1], ['query' => 'настро квот', 'limit' => 4, 'offset' => 0]);
$before = count(NoteStub::$calls);
Check::same('без слов — без запроса', [$g->search([], 4), count(NoteStub::$calls)], [[], $before]);

Check::group('note не установлен');

Loader::$modules['note'] = false;
Check::throws('шлюз не создаётся', RuntimeException::class, static fn() => new BitrixNoteGateway());
Loader::$modules['note'] = true;

Check::finish();
