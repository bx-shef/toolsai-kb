<?php declare(strict_types=1);

/**
 * Search\Chunker: статья -> фрагменты по разделам, длинное — по абзацам.
 */

require_once __DIR__.'/assert.php';
require_once __DIR__.'/stub/autoload.php';

use Shef\ToolsAiKb\Search\Chunker;

Check::group('разделы');

$md = "Вступление.\n\n## Квота\n\nМесячный предел.\n\n### Как считается\n\nПо журналу.\n\n## Ключ\n\nВ настройках.";
Check::same('по заголовкам, цепочка заголовков', Chunker::split($md), [
	['heading' => '', 'text' => 'Вступление.'],
	['heading' => 'Квота', 'text' => 'Месячный предел.'],
	['heading' => 'Квота › Как считается', 'text' => 'По журналу.'],
	['heading' => 'Ключ', 'text' => 'В настройках.'],
]);
Check::same('раздел без текста — без фрагмента', Chunker::split("## Пусто\n\n## Есть\n\nтекст"), [['heading' => 'Есть', 'text' => 'текст']]);
Check::same('пустая статья — пусто', Chunker::split("  \n\n"), []);
Check::same('# внутри блока кода — не заголовок', Chunker::split("## Код\n\n```\n# комментарий\n```"), [['heading' => 'Код', 'text' => "```\n# комментарий\n```"]]);

Check::group('длина');

$para = static fn(string $c, int $n): string => trim(str_repeat($c.' ', $n));
$chunks = Chunker::split($para('а', 30)."\n\n".$para('б', 30)."\n\n".$para('в', 30), 130);
Check::same('короткие абзацы склеиваются до предела', array_map(static fn(array $c): int => substr_count($c['text'], "\n\n") + 1, $chunks), [2, 1]);
$long = Chunker::split($para('слово', 100), 120);
Check::same('длинный абзац — куски не длиннее предела', max(array_map(static fn(array $c): int => mb_strlen($c['text']), $long)) <= 120, true);
Check::same('режется по словам', array_filter($long, static fn(array $c): bool => !preg_match('/^(слово ?)+$/u', $c['text'])), []);
Check::same('текст не теряется', implode(' ', array_column($long, 'text')), $para('слово', 100));

Check::finish();
