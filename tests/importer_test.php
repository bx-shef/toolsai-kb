<?php declare(strict_types=1);

/**
 * Import\Importer на подставной «Базе знаний» (stub/fakes.php): раздел,
 * идемпотентность, ручные правки, корзина, архив, сбои.
 */

require_once __DIR__.'/assert.php';
require_once __DIR__.'/stub/autoload.php';

use Shef\ToolsAiKb\Import\ArticleParser;
use Shef\ToolsAiKb\Import\Importer;
use Shef\ToolsAiKb\Import\Planner;
use Shef\ToolsAiKb\Import\Source;

$articles = [
	ArticleParser::parse('a.md', "# Альфа\nтекст А"),
	ArticleParser::parse('b.md', "# Бета\nтекст Б"),
];
$reader = static function(string $dir) use (&$articles): array
{
	return ['articles' => $articles, 'errors' => $dir === '/bad' ? ['битый файл'] : []];
};
$source = new Source('shef.toolsai:guide', 'ИИ: Справка', '/kb', 'shef.toolsai');
$note = new FakeNote();
$map = new FakeMapping();
$importer = new Importer($note, $map, 7);

Check::group('первый импорт');

$r = $importer->run([$source], 0, 'ИИ: База знаний', $reader);
Check::same('раздел создан от служебного пользователя', [$r->collectionCreated, $note->calls[0]], [true, 'collection ИИ: База знаний by 7']);
Check::same('раздел в отчёте', $r->collectionId, 1);
Check::same('документ-раздел пакета и две статьи', $r->get(Planner::CREATE), 3);
$page = $map->get('shef.toolsai:guide', '');
Check::same('страница пакета — верхний уровень', $note->documents[$page['documentId']]['parentId'], null);
Check::same('статьи — под страницей пакета', $note->documents[$map->get('shef.toolsai:guide', 'a.md')['documentId']]['parentId'], $page['documentId']);
Check::same('в разделе 1', $note->documents[$page['documentId']]['collectionId'], 1);
Check::same('заголовок статьи', $note->documents[$map->get('shef.toolsai:guide', 'b.md')['documentId']]['title'], 'Бета');
Check::same('текст страницы пакета называет модуль', str_contains($note->documents[$page['documentId']]['markdown'], 'shef.toolsai'), true);

Check::group('повторный импорт без изменений');

$calls = count($note->calls);
$r = $importer->run([$source], 1, 'ИИ: База знаний', $reader);
Check::same('ничего не записано', count($note->calls), $calls);
Check::same('всё без изменений', $r->get(Planner::UNCHANGED), 3);
Check::same('раздел не создаётся заново', $r->collectionCreated, false);

Check::group('статья изменилась; другую поправили руками');

$idA = $map->get('shef.toolsai:guide', 'a.md')['documentId'];
$idB = $map->get('shef.toolsai:guide', 'b.md')['documentId'];
$note->documents[$idB]['markdown'] = 'текст Б, правка человека';
$articles = [
	ArticleParser::parse('a.md', "# Альфа\nтекст А, версия 2"),
	ArticleParser::parse('b.md', "# Бета\nтекст Б, версия 2"),
	ArticleParser::parse('c.md', "# Гамма\nновая"),
];
$r = $importer->run([$source], 1, 'ИИ: База знаний', $reader);
Check::same('изменённая перезаписана', $note->documents[$idA]['markdown'], 'текст А, версия 2');
Check::same('хэш соответствия обновлён', $map->get('shef.toolsai:guide', 'a.md')['hash'], $articles[0]->hash);
Check::same('правленная руками не тронута', $note->documents[$idB]['markdown'], 'текст Б, правка человека');
Check::same('и названа в отчёте', $r->manual, ['shef.toolsai:guide/b.md']);
Check::same('хэш правленной не тронут', $map->get('shef.toolsai:guide', 'b.md')['hash'] === $articles[1]->hash, false);
Check::same('новая создана', $r->get(Planner::CREATE), 1);
Check::same('итог', [$r->get(Planner::UPDATE), $r->get(Planner::MANUAL), $r->get(Planner::UNCHANGED)], [1, 1, 1]);

Check::group('открыли в редакторе, архив, удаление, корзина');

$note->documents[$idA]['format'] = 'yjs';
$articles[0] = ArticleParser::parse('a.md', "# Альфа\nверсия 3");
$r = $importer->run([$source], 1, 'ИИ: База знаний', $reader);
Check::same('документ в yjs не перезаписан', [$r->get(Planner::MANUAL), $note->documents[$idA]['markdown']], [2, 'текст А, версия 2']);

$idC = $map->get('shef.toolsai:guide', 'c.md')['documentId'];
$note->documents[$idC]['archived'] = true;
$articles[2] = ArticleParser::parse('c.md', "# Гамма\nверсия 2");
$r = $importer->run([$source], 1, 'ИИ: База знаний', $reader);
Check::same('архивный не тронут', [$r->get(Planner::ARCHIVED), $note->documents[$idC]['markdown']], [1, 'новая']);

$note->documents[$idC]['archived'] = false;
$note->documents[$idC]['removed'] = true;
$r = $importer->run([$source], 1, 'ИИ: База знаний', $reader);
Check::same('в корзине — отчёт, не ошибка', [$r->get(Importer::REMOVED), $r->errors], [1, []]);

unset($note->documents[$idC]);
$r = $importer->run([$source], 1, 'ИИ: База знаний', $reader);
Check::same('удалён совсем — создан заново', $r->get(Planner::RECREATE), 1);
Check::same('соответствие на новый документ', $map->get('shef.toolsai:guide', 'c.md')['documentId'] !== $idC, true);

Check::group('раздел');

$note->collections[1] = FakeNote::COLLECTION_ARCHIVED;
$calls = count($note->calls);
$r = $importer->run([$source], 1, 'ИИ: База знаний', $reader);
Check::same('раздел в архиве — импорт не идёт', [count($note->calls), count($r->errors), $r->collectionId], [$calls, 1, 1]);

unset($note->collections[1]);
$r = $importer->run([$source], 1, 'ИИ: База знаний', $reader);
Check::same('раздел удалён — создан новый', [$r->collectionCreated, $r->collectionId !== 1], [true, true]);

Check::group('сбои');

$bad = new Source('shef.toolsai:bad', 'Плохой', '/bad', 'shef.toolsai');
$fresh = new FakeNote();
$fresh->failCreate = true;
$r = (new Importer($fresh, new FakeMapping(), 7))->run([$bad], 0, 'Р', $reader);
Check::same('ошибки чтения пакета и записи — в отчёте, без падения', count($r->errors), 1 + 4);
Check::same('ошибка называет статью', in_array('shef.toolsai:bad/a.md: сбой note', $r->errors, true), true);
Check::same('summary', str_contains($r->summary(), 'ошибок: 5'), true);

Check::finish();
