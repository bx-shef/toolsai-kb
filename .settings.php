<?php declare(strict_types=1);

/**
 * Настраиваемые параметры модуля shef.toolsaikb
 *
 * * requireModules -> модули линейки: autoload.php подключает их при каждой
 *   загрузке, их удаление установщик не пропустит
 * * requirePhpExt -> обязательные расширения PHP
 * * registerAutoLoadClasses, registerNamespace -> чужих классов нет; свои
 *   (Shef\ToolsAiKb\...) ядро находит в lib/ по соглашению, путь строчными
 * * installEvents -> постоянные подписки (1.1.2): пункт «ИИ база знаний» в меню
 *   шестерёнки вида «ИИ» shef.toolsai. Ставит установщик и Main\Maintenance
 *   (обновление копированием), снимает удаление
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
		'value' => [
			[
				'isCompatible' => false,
				'from' => ['module' => 'shef.toolsai', 'event' => 'OnViewSettingsMenuBuild'],
				'to' => [
					'module' => 'shef.toolsaikb',
					'class' => '\\Shef\\ToolsAiKb\\Internals\\Integration\\ToolsAi\\EventHandler\\OnViewSettingsMenuBuildEventHandler',
					'function' => 'handle',
					'sort' => 100,
				],
			],
		],
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
