<?php declare(strict_types=1);

/**
 * Документация: относительные ссылки в *.md ведут на существующие файлы;
 * классы и события, названные в docs/guide, существуют в lib/.
 */

require_once __DIR__.'/assert.php';

$root = dirname(__DIR__);
$files = array_merge(glob($root.'/*.md') ?: [], glob($root.'/docs/*.md') ?: [], glob($root.'/docs/*/*.md') ?: []);
Check::same('документы нашлись', count($files) >= 12, true);

$broken = [];
foreach($files as $file)
{
	preg_match_all('/\]\(([^)#\s]+)(?:#[^)]*)?\)/u', (string)file_get_contents($file), $m);
	foreach($m[1] as $link)
	{
		if(preg_match('#^[a-z]+://#i', $link) || str_starts_with($link, 'mailto:'))
		{
			continue;
		}
		if(!file_exists(dirname($file).'/'.$link))
		{
			$broken[] = substr($file, strlen($root) + 1).' -> '.$link;
		}
	}
}
Check::same('ссылки не битые', $broken, []);

$missing = [];
$named = 0;
foreach(glob($root.'/docs/guide/*.md') ?: [] as $file)
{
	preg_match_all('/(?<!\w\\\\)Shef\\\\ToolsAiKb\\\\([A-Za-z\\\\]+)/u', (string)file_get_contents($file), $m);
	foreach(array_unique($m[1]) as $class)
	{
		$named++;
		$path = $root.'/lib/'.mb_strtolower(str_replace('\\', '/', $class)).'.php';
		if(!is_file($path))
		{
			$missing[] = $class;
		}
	}
}
Check::same('guide называет классы модуля', $named >= 3, true);
Check::same('классы из guide существуют', $missing, []);

Check::finish();
