<?php

/**
 * Автозагрузка для тестов без портала.
 *
 * Правило то же, по которому классы ищет портал: Shef\ToolsAiKb\Foo\Bar ->
 * lib/foo/bar.php, путь СТРОЧНЫМИ (держит tests/autoload_test.php). Классы
 * модуля подключаются настоящие; ядро и `note` — заглушки tests/stub/*.php.
 */

require_once __DIR__.'/bitrix.php';
require_once __DIR__.'/note.php';

spl_autoload_register(static function(string $class): void
{
	$prefix = 'Shef\\ToolsAiKb\\';
	if(!str_starts_with($class, $prefix))
	{
		return;
	}

	$path = dirname(__DIR__, 2).'/lib/'.mb_strtolower(str_replace('\\', '/', substr($class, strlen($prefix)))).'.php';
	if(is_file($path))
	{
		require_once $path;
	}
});

require_once __DIR__.'/fakes.php';
