<?php declare(strict_types=1);

/**
 * Каждый класс в lib/ лежит там, где его ищет ядро: Shef\ToolsAiKb\Foo\Bar ->
 * lib/foo/bar.php, путь строчными; один файл — один класс.
 */

require_once __DIR__.'/assert.php';

$root = dirname(__DIR__);
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/lib', FilesystemIterator::SKIP_DOTS));
$count = 0;
foreach($files as $file)
{
	if($file->getExtension() !== 'php')
	{
		continue;
	}
	$count++;
	$rel = substr($file->getPathname(), strlen($root.'/lib/'));
	$src = (string)file_get_contents($file->getPathname());
	preg_match('/^namespace\s+([^;]+);/m', $src, $ns);
	preg_match_all('/^(?:final\s+|abstract\s+)?(?:class|interface|trait|enum)\s+(\w+)/m', $src, $classes);
	Check::same($rel.': один класс', count($classes[1]), 1);
	$fqcn = ($ns[1] ?? '').'\\'.($classes[1][0] ?? '');
	$expected = mb_strtolower(str_replace('\\', '/', substr($fqcn, strlen('Shef\\ToolsAiKb\\')))).'.php';
	Check::same($rel.': путь по namespace', $rel, $expected);
	Check::same($rel.': strict_types', str_starts_with($src, '<?php declare(strict_types=1);'), true);
	Check::same($rel.': phpDoc у класса', (bool)preg_match('/\*\/\s*\n(?:final\s+|abstract\s+)?(?:class|interface|trait|enum)\s/m', $src), true);
}
Check::same('классы нашлись', $count > 10, true);

Check::finish();
