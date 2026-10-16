<?php declare(strict_types=1);

/**
 * Search\QueryNormalizer: разбор вопроса в слова для поиска `note`.
 *
 * tokens() и stem() перенесены из Shef\ToolsAi\Kb\Rag (shef.toolsai 1.21.0)
 * без изменения смысла. Эталон — fixtures/rag-tokens.json: его выдал
 * настоящий Rag::tokens() модуля shef.toolsai 1.49.0 на тех же фразах.
 * Разойдётся — значит, перенос поменял смысл.
 */

require_once __DIR__.'/assert.php';
require_once __DIR__.'/stub/autoload.php';

use Shef\ToolsAiKb\Search\QueryNormalizer;

Check::group('совпадение с Rag::tokens() shef.toolsai');

$fixture = json_decode((string)file_get_contents(__DIR__.'/fixtures/rag-tokens.json'), true, 512, JSON_THROW_ON_ERROR);
Check::same('эталонов', count($fixture) >= 9, true);
foreach($fixture as $row)
{
	Check::same('tokens: '.$row['text'], QueryNormalizer::tokens($row['text']), $row['tokens']);
}

Check::group('stem');

Check::same('квоту -> квот', QueryNormalizer::stem('квоту'), 'квот');
Check::same('ё -> е', QueryNormalizer::stem('ёлка'), 'елк');
Check::same('короткая основа не режется', QueryNormalizer::stem('дела'), 'дел');
Check::same('латиница как есть', QueryNormalizer::stem('whisper'), 'whisper');

Check::group('terms: слова для note');

Check::same('стоп-слова прочь, основы', QueryNormalizer::terms('Как настроить квоту расхода на месяц?'), ['настро', 'квот', 'расход', 'месяц']);
Check::same('короче трёх букв прочь', QueryNormalizer::terms('ИИ и ок'), []);
Check::same('пусто', QueryNormalizer::terms('   '), []);
Check::same('только стоп-слова', QueryNormalizer::terms('как что где'), []);
Check::same('без повторов', QueryNormalizer::terms('квота квоты квоту'), ['квот']);
Check::same('слово с точкой — только части', QueryNormalizer::terms('shef.toolsai'), ['shef', 'toolsai']);
Check::same('число — строкой', QueryNormalizer::terms('отчёт 2024'), ['отчет', '2024']);
Check::same('не больше MAX_TERMS', count(QueryNormalizer::terms('альфа бета гамма дельта эпсилон дзета эта тета')), QueryNormalizer::MAX_TERMS);
Check::same('длинный запрос режется до 200', QueryNormalizer::terms(str_repeat('а', 300).' квота'), [str_repeat('а', 199)]);

Check::finish();
