<?php declare(strict_types=1);

/**
 * Настраиваемые параметры модуля shef.toolsaikb
 *
 * * requireModules -> модули линейки: autoload.php подключает их при каждой
 *   загрузке, их удаление установщик не пропустит
 * * requirePhpExt -> обязательные расширения PHP
 * * registerAutoLoadClasses, registerNamespace -> чужих классов нет; свои
 *   (Shef\ToolsAiKb\...) ядро находит в lib/ по соглашению, путь строчными
 * * installEvents -> пусто: модуль сам ни на что не подписан, он отправляет
 *   событие OnKbSourcesCollect
 * * installDir -> пусто: публичных файлов нет
 * * controllers -> ajax: BX.ajax.runAction('shef:toolsaikb.search.find')
 *   -> Shef\ToolsAiKb\Controller\Search::findAction()
 *
 * `note` («База знаний» ядра) — не здесь, а в NEED_MODULES установщика: без
 * него модуль не ставится, но autoload.php не поднимает его на каждой загрузке.
 */

return [
	'requireModules' => [
		'value' => [
			'shef.options',
			'shef.problems',
		],
		'readonly' => true,
	],
	'requirePhpExt' => [
		'value' => [
			'mbstring',
			'json',
		],
		'readonly' => true,
	],
	'registerAutoLoadClasses' => [
		'value' => [],
		'readonly' => true,
	],
	'registerNamespace' => [
		'value' => [],
		'readonly' => true,
	],
	'options' => [
		'value' => [],
		'readonly' => true,
	],
	'installEvents' => [
		'value' => [],
		'readonly' => true,
	],
	'installDir' => [
		'value' => [],
		'readonly' => true,
	],
	'controllers' => [
		'value' => [
			'defaultNamespace' => '\\Shef\\ToolsAiKb\\Controller',
			'namespaces' => [],
		],
		'readonly' => true,
	],
];
