<?php declare(strict_types=1);

/**
 * Import\Planner: что делать со статьёй. Главное — ручную правку в note не затирать.
 */

require_once __DIR__.'/assert.php';
require_once __DIR__.'/stub/autoload.php';

use Shef\ToolsAiKb\Import\ArticleParser;
use Shef\ToolsAiKb\Import\Planner;

$old = ArticleParser::hash('Т', 'старый');
$new = ArticleParser::hash('Т', 'новый');
$doc = static fn(string $title = 'Т', string $markdown = 'старый', string $format = 'md', bool $archived = false): array => compact('archived', 'format', 'title', 'markdown');

Check::same('нет соответствия — создать', Planner::decide($new, null, null), Planner::CREATE);
Check::same('документ исчез — создать заново', Planner::decide($new, $old, null), Planner::RECREATE);
Check::same('в архиве — не трогать', Planner::decide($new, $old, $doc(archived: true)), Planner::ARCHIVED);
Check::same('статья та же — ничего', Planner::decide($old, $old, $doc()), Planner::UNCHANGED);
Check::same('статья изменилась, документ не трогали — перезаписать', Planner::decide($new, $old, $doc()), Planner::UPDATE);
Check::same('текст поправили руками — не трогать', Planner::decide($new, $old, $doc(markdown: 'старый, правка')), Planner::MANUAL);
Check::same('заголовок поправили руками — не трогать', Planner::decide($new, $old, $doc(title: 'Т!')), Planner::MANUAL);
Check::same('открыли в редакторе (yjs) — не трогать', Planner::decide($new, $old, $doc(format: 'yjs')), Planner::MANUAL);
Check::same('поправили руками, статья не менялась — тоже MANUAL', Planner::decide($old, $old, $doc(markdown: 'правка')), Planner::MANUAL);

Check::finish();
