<?php declare(strict_types=1);

/**
 * Страница настроек модуля (/bitrix/admin/settings.php?mid=shef.toolsaikb).
 *
 * Сверху — итог последнего импорта (право чтения на модуль) и кнопка
 * «Обновить статьи» (право записи, POST с sessid). Ниже — обычная страница настроек
 * линейки: options_page.php — дословная копия options.php модуля
 * shef.options (навык shef-new-module), её не правим. Своя часть вынесена сюда,
 * чтобы копия оставалась дословной.
 *
 * @global string $mid
 */

use Bitrix\Main\Config\Option;
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Shef\ToolsAiKb\Import\Runner;
use Shef\ToolsAiKb\Main\Constants;

defined('B_PROLOG_INCLUDED') && B_PROLOG_INCLUDED === true || die();

Loc::loadMessages(__FILE__);

/** @var \CMain $APPLICATION */
global $APPLICATION;

// Без права чтения на модуль своя часть не показывается; отказ выведет options_page.php
if(Loader::includeModule('shef.toolsaikb') && $APPLICATION->GetGroupRight('shef.toolsaikb') >= 'R')
{
	$canWrite = $APPLICATION->GetGroupRight('shef.toolsaikb') >= 'W';
	$request = Context::getCurrent()?->getRequest();

	if(
		$canWrite
		&& $request !== null
		&& $request->isPost()
		&& (string)$request->getPost('shef_toolsaikb_refresh') !== ''
		&& check_bitrix_sessid()
	)
	{
		$report = Runner::run();
		if($report === null)
		{
			\CAdminMessage::ShowMessage(['TYPE' => 'ERROR', 'MESSAGE' => Loc::getMessage('SHEF_TOOLSAIKB_REFRESH_BUSY')]);
		}
		else
		{
			$details = htmlspecialcharsbx($report->summary());
			if($report->manual !== [])
			{
				$details .= '<br><br>'.Loc::getMessage('SHEF_TOOLSAIKB_REFRESH_MANUAL').'<br>'.implode('<br>', array_map('htmlspecialcharsbx', $report->manual));
			}
			if($report->kept !== [])
			{
				$details .= '<br><br>'.Loc::getMessage('SHEF_TOOLSAIKB_REFRESH_KEPT').'<br>'.implode('<br>', array_map('htmlspecialcharsbx', $report->kept));
			}
			if($report->errors !== [])
			{
				$details .= '<br><br>'.Loc::getMessage('SHEF_TOOLSAIKB_REFRESH_ERRORS').'<br>'.implode('<br>', array_map('htmlspecialcharsbx', $report->errors));
			}
			\CAdminMessage::ShowMessage([
				'TYPE' => $report->errors === [] ? 'OK' : 'ERROR',
				'HTML' => true,
				'MESSAGE' => Loc::getMessage('SHEF_TOOLSAIKB_REFRESH_DONE'),
				'DETAILS' => $details,
			]);
		}
	}

	$collectionId = (int)Option::get(Constants::MODULE_ID, Constants::OPTION_COLLECTION_ID, '0');
	$last = (string)Option::get(Constants::MODULE_ID, Constants::OPTION_LAST_REPORT, '');
	?>
	<form method="POST" action="<?=htmlspecialcharsbx($APPLICATION->GetCurPage())?>?mid=<?=urlencode((string)$mid)?>&amp;lang=<?=LANGUAGE_ID?>" style="margin: 0 0 16px">
		<p>
			<?=Loc::getMessage('SHEF_TOOLSAIKB_COLLECTION')?>:
			<?php if($collectionId > 0):?>
				<a href="/note/workspace/<?=$collectionId?>/" target="_blank"><?=htmlspecialcharsbx((string)Option::get(Constants::MODULE_ID, Constants::OPTION_COLLECTION_NAME, Constants::DEFAULT_COLLECTION_NAME))?></a> (ID <?=$collectionId?>)
			<?php else:?>
				<?=Loc::getMessage('SHEF_TOOLSAIKB_COLLECTION_NONE')?>
			<?php endif;?>
			<br>
			<?=Loc::getMessage('SHEF_TOOLSAIKB_LAST')?>: <?=$last !== '' ? htmlspecialcharsbx($last) : Loc::getMessage('SHEF_TOOLSAIKB_LAST_NONE')?>
		</p>
		<?=bitrix_sessid_post()?>
		<input type="submit" name="shef_toolsaikb_refresh" value="<?=htmlspecialcharsbx((string)Loc::getMessage('SHEF_TOOLSAIKB_REFRESH'))?>" <?=$canWrite ? '' : 'disabled'?> class="adm-btn">
	</form>
	<?php
}

require __DIR__.'/options_page.php';
