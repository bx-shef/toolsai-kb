<?php declare(strict_types=1);

use Bitrix\Main\Localization\Loc;
use Shef\Options\Main\Options;

/**
 * Опции страницы настроек (API shef.options 3.x, навык shef-options-settings).
 *
 * Tab(prefix)->Option(code) ~> код настройки: prefix_code. Коды — в
 * Shef\ToolsAiKb\Main\Constants::OPTION_*; умолчания — default_option.php.
 *
 * Языковой файл — lang/ru/options_conf.php.
 */

Loc::loadMessages(__FILE__);

$response = ShOptionsConfig::getInstance(
	moduleId: 'shef.toolsaikb'
);
if(!$response->isSuccess())
{
	return $response;
}

/** @var ShOptionsConfig $options */
$options = $response->getData()['OPTIONS'];

$options->addTab(
	(new Options\Tab('KB'))
		->setName((string)Loc::getMessage($options->moduleId.'_TAB_KB_NAME'))
		->setTitle((string)Loc::getMessage($options->moduleId.'_TAB_KB_TITLE'))
		->addOption(
			(new Options\RowInfo('about'))
				->setDescription((string)Loc::getMessage($options->moduleId.'_TAB_KB_about'))
				->setType(Options\TypeUIAlert::Note)
		)
		->addOption(
			(new Options\Text('collectionname'))
				->setDefValue('ИИ: База знаний')
				->setTitle((string)Loc::getMessage($options->moduleId.'_TAB_KB_collectionname'))
				->setDescription((string)Loc::getMessage($options->moduleId.'_TAB_KB_collectionname_descr'))
		)
		->addOption(
			(new Options\Checkbox('agent'))
				->setDefValue('Y')
				->setTitle((string)Loc::getMessage($options->moduleId.'_TAB_KB_agent'))
				->setDescription((string)Loc::getMessage($options->moduleId.'_TAB_KB_agent_descr'))
		)
		->addOption(
			(new Options\Checkbox('searchall'))
				->setDefValue('N')
				->setTitle((string)Loc::getMessage($options->moduleId.'_TAB_KB_searchall'))
				->setDescription((string)Loc::getMessage($options->moduleId.'_TAB_KB_searchall_descr'))
		)
);

return $options->get();
