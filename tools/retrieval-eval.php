<?php declare(strict_types=1);

/**
 * Замер поиска статей без портала (tools/retrievaleval/lib.php).
 *
 *   php tools/retrieval-eval.php [--articles=../toolsai/kb/public/raw/guide]
 *       [--cases=tools/retrieval-evals/toolsai-guide.json] [--k=5]
 *       [--report=файл.md] [--json=файл.json] [--min-gain=0]
 *
 * --articles — каталог статей пакета (*.md), по умолчанию справка shef.toolsai
 * из соседнего клона; --min-gain — провал, если MRR «rag» меньше «note» плюс порог.
 */

$root = dirname(__DIR__);
require $root.'/tests/stub/autoload.php';
require __DIR__.'/retrievaleval/lib.php';

use RetrievalEval\Runner;

$opt = getopt('', ['articles:', 'cases:', 'k:', 'report:', 'json:', 'min-gain:']);
$articles = (string)($opt['articles'] ?? $root.'/../toolsai/kb/public/raw/guide');
$casesFile = (string)($opt['cases'] ?? __DIR__.'/retrieval-evals/toolsai-guide.json');
$k = max(1, (int)($opt['k'] ?? 5));

$files = Runner::corpus($articles);
if($files === [])
{
	fwrite(STDERR, "Нет статей в {$articles}\n");
	exit(2);
}
$parsed = Runner::cases(json_decode((string)file_get_contents($casesFile), true), array_keys($files));
foreach($parsed['errors'] as $error)
{
	fwrite(STDERR, "кейс: {$error}\n");
}
$result = Runner::run($files, $parsed['cases'], $k);
$report = Runner::report($result, count($files));
echo $report;
if(isset($opt['report']))
{
	file_put_contents((string)$opt['report'], $report);
}
if(isset($opt['json']))
{
	file_put_contents((string)$opt['json'], json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
if(isset($opt['min-gain']) && $result['modes']['rag']['mrr'] < $result['modes']['note']['mrr'] + (float)$opt['min-gain'])
{
	fwrite(STDERR, "MRR rag ниже note + порог\n");
	exit(1);
}
exit($parsed['errors'] === [] ? 0 : 1);
