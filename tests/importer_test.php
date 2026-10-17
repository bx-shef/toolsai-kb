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

Check::group('статья пропала из пакета');

$note = new FakeNote();
$map = new FakeMapping();
$importer = new Importer($note, $map, 7);
$articles = [
	ArticleParser::parse('a.md', "# Альфа\nА"),
	ArticleParser::parse('b.md', "# Бета\nБ"),
	ArticleParser::parse('c.md', "# Гамма\nВ"),
];
$other = new Source('shef.other:guide', 'Другой', '/kb2', 'shef.other');
$otherArticles = [ArticleParser::parse('x.md', "# Икс\nИ")];
$reader = static function(string $dir) use (&$articles, &$otherArticles): array
{
	return ['articles' => $dir === '/kb2' ? $otherArticles : ($dir === '/bad' ? [] : $articles), 'errors' => $dir === '/bad' ? ['битый файл'] : []];
};
$importer->run([$source, $other], 0, 'Р', $reader);
$idA = $map->get('shef.toolsai:guide', 'a.md')['documentId'];
$idB = $map->get('shef.toolsai:guide', 'b.md')['documentId'];
$note->documents[$idB]['markdown'] = 'правка человека';
$articles = [ArticleParser::parse('c.md', "# Гамма\nВ")];
$r = $importer->run([$source, $other], 1, 'Р', $reader);
Check::same('нетронутая — в корзину от служебного пользователя', [$note->documents[$idA]['removed'], in_array('delete '.$idA.' by 7', $note->calls, true)], [true, true]);
Check::same('её соответствие убрано', $map->get('shef.toolsai:guide', 'a.md'), null);
Check::same('правленная руками не тронута', [$note->documents[$idB]['removed'], $map->get('shef.toolsai:guide', 'b.md') !== null], [false, true]);
Check::same('и названа в отчёте', $r->kept, ['shef.toolsai:guide/b.md: нет в пакете, но правлена руками — оставлена']);
Check::same('итог', [$r->get(Planner::DELETE), $r->get(Importer::OBSOLETE_KEPT), $r->errors], [1, 1, []]);
Check::same('в summary', str_contains($r->summary(), 'удалено в корзину: 1, нет в пакете, оставлены: 1'), true);

Check::group('пакет пропал из события');

$pageOther = $map->get('shef.other:guide', '')['documentId'];
$calls = count($note->calls);
$r = $importer->run([$source], 1, 'Р', $reader, false);
Check::same('сбор с ошибками — пропавший пакет не трогается', [count($note->calls), $map->get('shef.other:guide', '') !== null], [$calls, true]);
$r = $importer->run([], 1, 'Р', $reader);
Check::same('событие без пакетов — ничего не удаляется', [count($note->calls), $map->get('shef.other:guide', '') !== null], [$calls, true]);
$r = $importer->run([$source], 1, 'Р', $reader);
Check::same('пакет пропал — статьи и страница в корзине', [$note->documents[$pageOther]['removed'], $r->get(Planner::DELETE)], [true, 2]);
Check::same('статья раньше страницы', array_slice($note->calls, -2), ['delete '.($pageOther + 1).' by 7', 'delete '.$pageOther.' by 7']);
Check::same('соответствия пакета убраны', array_filter(array_keys($map->rows), static fn($k) => str_starts_with($k, 'shef.other:')), []);

Check::group('страница пакета с правленной статьёй остаётся');

$page = $map->get('shef.toolsai:guide', '')['documentId'];
$r = $importer->run([$other], 1, 'Р', $reader);
Check::same('страница оставлена, правленная тоже', [$note->documents[$page]['removed'], $note->documents[$idB]['removed']], [false, false]);
Check::same('нетронутая статья пакета удалена', $map->get('shef.toolsai:guide', 'c.md'), null);
Check::same('отчёт объясняет', in_array('shef.toolsai:guide: страница пакета оставлена — под ней правленные руками или архивные статьи', $r->kept, true), true);

Check::group('ошибки чтения и сбой удаления');

$broken = new Source('shef.other:guide', 'Другой', '/bad', 'shef.other');
$articles = [];
$r = $importer->run([$broken], 1, 'Р', $reader);
Check::same('пакет прочитан с ошибками — его статьи не удаляются', $r->get(Planner::DELETE), 0);
$idB = $map->get('shef.toolsai:guide', 'b.md')['documentId'];
$note->documents[$idB]['markdown'] = 'Б';
$note->failDelete = true;
$r = $importer->run([$other], 1, 'Р', $reader);
Check::same('сбой удаления — ошибка, соответствие остаётся', [count($r->errors) > 0, $map->get('shef.toolsai:guide', '') !== null], [true, true]);
$note->failDelete = false;

Check::group('документ уже в корзине или удалён');

$note->documents[$idB]['removed'] = true;
$r = $importer->run([$other], 1, 'Р', $reader);
Check::same('в корзине — соответствие убрано, не ошибка', [$r->get(Planner::GONE), $map->get('shef.toolsai:guide', 'b.md'), $r->errors], [1, null, []]);
Check::same('страница пакета ушла следом', $map->get('shef.toolsai:guide', ''), null);

Check::group('под документом — созданный вручную');

$note = new FakeNote();
$map = new FakeMapping();
$importer = new Importer($note, $map, 7);
$articles = [ArticleParser::parse('a.md', "# Альфа\nА"), ArticleParser::parse('b.md', "# Бета\nБ")];
$importer->run([$source], 0, 'Р', $reader);
$idA = $map->get('shef.toolsai:guide', 'a.md')['documentId'];
$human = $note->createDocument(1, $idA, 'Своё', 'текст человека', 3);
$articles = [ArticleParser::parse('b.md', "# Бета\nБ")];
$r = $importer->run([$source], 1, 'Р', $reader);
Check::same('статья с вложенным чужим документом не удалена', [$note->documents[$idA]['removed'], $note->documents[$human]['removed'], $map->get('shef.toolsai:guide', 'a.md') !== null], [false, false, true]);
Check::same('отчёт объясняет', $r->kept, ['shef.toolsai:guide/a.md: оставлен — под ним документы, созданные вручную']);

$other = new Source('shef.other:guide', 'Другой', '/kb2', 'shef.other');
$page = $map->get('shef.toolsai:guide', '')['documentId'];
$note->documents[$human]['parentId'] = $page;
$r = $importer->run([$other], 1, 'Р', $reader);
Check::same('статьи удалены, страница с чужим документом оставлена', [$note->documents[$idA]['removed'], $note->documents[$page]['removed'], $note->documents[$human]['removed']], [true, false, false]);
Check::same('и в отчёте', in_array('shef.toolsai:guide: оставлен — под ним документы, созданные вручную', $r->kept, true), true);

Check::group('пакет пришёл пустым');

$note = new FakeNote();
$map = new FakeMapping();
$importer = new Importer($note, $map, 7);
$articles = [ArticleParser::parse('a.md', "# Альфа\nА")];
$importer->run([$source], 0, 'Р', $reader);
$articles = [];
$calls = count($note->calls);
$r = $importer->run([$source], 1, 'Р', $reader);
Check::same('пустой каталог — ничего не удаляется', [$r->get(Planner::DELETE), count($note->calls), $map->get('shef.toolsai:guide', 'a.md') !== null], [0, $calls, true]);

Check::finish();
