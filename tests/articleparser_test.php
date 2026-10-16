<?php declare(strict_types=1);

/**
 * Import\ArticleParser: файл -> заголовок, текст, хэш.
 */

require_once __DIR__.'/assert.php';
require_once __DIR__.'/stub/autoload.php';

use Shef\ToolsAiKb\Import\ArticleParser;

Check::group('заголовок');

$a = ArticleParser::parse('quota.md', "# Квота расхода\n\nТекст статьи.\n");
Check::same('из первой строки #', $a->title, 'Квота расхода');
Check::same('строка заголовка убрана из текста', $a->markdown, 'Текст статьи.');
Check::same('путь', $a->path, 'quota.md');

$b = ArticleParser::parse('setup/10-first_steps.md', "Без заголовка.\n");
Check::same('из имени файла без номера', $b->title, 'First steps');
Check::same('текст целиком', $b->markdown, 'Без заголовка.');

Check::same('## — не заголовок документа', ArticleParser::parse('a.md', "## Раздел\nтекст")->title, 'A');
Check::same('пустые строки до заголовка', ArticleParser::parse('a.md', "\n\n# Заголовок #\nтекст")->title, 'Заголовок');
Check::same('имя из одного номера остаётся', ArticleParser::titleFromPath('10.md'), '10');
Check::same('имя только из номера и дефиса', ArticleParser::titleFromPath('10-.md'), '10-.md');

Check::group('нормализация и хэш');

$crlf = ArticleParser::parse('a.md', "\xEF\xBB\xBF# Т\r\n\r\nстрока\r\n");
$lf = ArticleParser::parse('a.md', "# Т\n\nстрока\n");
Check::same('BOM и CRLF не меняют хэш', $crlf->hash, $lf->hash);
Check::same('хэш = hash(title, markdown)', $lf->hash, ArticleParser::hash('Т', 'строка'));
Check::same('другой текст — другой хэш', ArticleParser::parse('a.md', "# Т\nстрока!")->hash === $lf->hash, false);
Check::same('другой заголовок — другой хэш', ArticleParser::hash('Т2', 'строка') === $lf->hash, false);

Check::finish();
