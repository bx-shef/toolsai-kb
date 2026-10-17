<?php declare(strict_types=1);

/**
 * Страница настроек модуля (/bitrix/admin/settings.php?mid=shef.toolsaikb).
 *
 * Сверху — карточка «Импорт статей» (право чтения на модуль): раздел, время,
 * плитки счётчиков (Settings\ReportView), свёрнутые списки, кнопка
 * «Обновить статьи» (ui.buttons); итог нажатия — ui.alert (право записи, POST с sessid). Ниже — обычная страница настроек
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
use Shef\ToolsAiKb\Settings\ReportView;
use Bitrix\Main\UI\Extension;

defined('B_PROLOG_INCLUDED') && B_PROLOG_INCLUDED === true || die();

Loc::loadMessages(__FILE__);

/** @var \CMain $APPLICATION */
global $APPLICATION;

// Без права чтения на модуль своя часть не показывается; отказ выведет options_page.php
if(Loader::includeModule('shef.toolsaikb') && $APPLICATION->GetGroupRight('shef.toolsaikb') >= 'R')
{
	$canWrite = $APPLICATION->GetGroupRight('shef.toolsaikb') >= 'W';
	Extension::load(['ui.alerts', 'ui.buttons']);
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
		$alert = match(true)
		{
			$report === null => ['ui-alert-warning', (string)Loc::getMessage('SHEF_TOOLSAIKB_REFRESH_BUSY')],
			$report->errors !== [] => ['ui-alert-danger', (string)Loc::getMessage('SHEF_TOOLSAIKB_REFRESH_DONE_ERRORS')],
			default => ['ui-alert-success', (string)Loc::getMessage('SHEF_TOOLSAIKB_REFRESH_DONE')],
		};
	}

	$collectionId = (int)Option::get(Constants::MODULE_ID, Constants::OPTION_COLLECTION_ID, '0');
	$view = ReportView::fromStored((string)Option::get(Constants::MODULE_ID, Constants::OPTION_LAST_REPORT, ''));
	$h = static fn(string $s): string => htmlspecialcharsbx($s);
	$list = static function(string $title, array $items, bool $open) use ($h): void
	{
		if($items === [])
		{
			return;
		}
		?>
		<details class="shef-kb-list"<?=$open ? ' open' : ''?>>
			<summary><?=$h($title)?> (<?=count($items)?>)</summary>
			<ul>
				<?php foreach($items as $item):?>
					<li><?php if(is_array($item) && $item['url'] !== ''):?><a href="<?=$h($item['url'])?>" target="_blank"><?=$h($item['title'])?></a><?php else:?><?=$h(is_array($item) ? $item['title'] : (string)$item)?><?php endif;?></li>
				<?php endforeach;?>
			</ul>
		</details>
		<?php
	};
	?>
	<style>
		.shef-kb-card { background: var(--ui-color-background-primary, #fff); border: 1px solid var(--ui-color-divider, #e6e7e9); border-radius: var(--ui-border-radius-md, 12px); padding: 16px 20px; margin: 0 0 16px; max-width: 960px; }
		.shef-kb-card h3 { margin: 0 0 12px; font-size: 16px; }
		.shef-kb-meta { display: grid; grid-template-columns: max-content 1fr; gap: 6px 16px; margin: 0 0 14px; }
		.shef-kb-meta dt { color: var(--ui-color-text-secondary, #828b95); }
		.shef-kb-meta dd { margin: 0; }
		.shef-kb-tiles { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 14px; padding: 0; list-style: none; }
		.shef-kb-tile { min-width: 112px; padding: 8px 12px; border-radius: var(--ui-border-radius-sm, 8px); background: var(--ui-color-background-secondary, #f5f7f8); }
		.shef-kb-tile b { display: block; font-size: 20px; line-height: 26px; }
		.shef-kb-tile span { font-size: 12px; color: var(--ui-color-text-secondary, #828b95); }
		.shef-kb-tile.is-muted b { color: var(--ui-color-text-disabled, #c9ccd0); font-weight: normal; }
		.shef-kb-tile.is-errors:not(.is-muted) b { color: var(--ui-color-text-alert, #e92f2a); }
		.shef-kb-list { margin: 0 0 10px; }
		.shef-kb-list summary { cursor: pointer; color: var(--ui-color-link-primary-base, #2066b0); }
		.shef-kb-list ul { margin: 6px 0 0 18px; padding: 0; }
	</style>
	<?php if(isset($alert)):?>
		<div class="ui-alert <?=$alert[0]?>" style="max-width: 960px"><span class="ui-alert-message"><?=$h($alert[1])?></span></div>
	<?php endif;?>
	<div class="shef-kb-card">
		<h3><?=Loc::getMessage('SHEF_TOOLSAIKB_SUMMARY')?></h3>
		<dl class="shef-kb-meta">
			<dt><?=Loc::getMessage('SHEF_TOOLSAIKB_COLLECTION')?></dt>
			<dd>
				<?php if($collectionId > 0):?>
					<a href="/note/workspace/<?=$collectionId?>/" target="_blank"><?=$h((string)Option::get(Constants::MODULE_ID, Constants::OPTION_COLLECTION_NAME, Constants::DEFAULT_COLLECTION_NAME))?></a>
				<?php else:?>
					<?=Loc::getMessage('SHEF_TOOLSAIKB_COLLECTION_NONE')?>
				<?php endif;?>
			</dd>
			<dt><?=Loc::getMessage('SHEF_TOOLSAIKB_LAST')?></dt>
			<dd>
				<?php if(!$view['empty']):?>
					<?=$h($view['at'])?> · <?=Loc::getMessage('SHEF_TOOLSAIKB_SOURCES')?>: <?=$view['sources']?>
				<?php elseif($view['legacy'] !== ''):?>
					<?=$h($view['legacy'])?>
				<?php else:?>
					<?=Loc::getMessage('SHEF_TOOLSAIKB_LAST_NONE')?>
				<?php endif;?>
			</dd>
		</dl>
		<?php if(!$view['empty']):?>
			<ul class="shef-kb-tiles">
				<?php foreach($view['counters'] as $c):?>
					<li class="shef-kb-tile is-<?=$h($c['code'])?><?=$c['muted'] ? ' is-muted' : ''?>"><b><?=$c['value']?></b><span><?=$h($c['label'])?></span></li>
				<?php endforeach;?>
			</ul>
			<?php
			$list((string)Loc::getMessage('SHEF_TOOLSAIKB_REFRESH_MANUAL'), $view['manual'], false);
			$list((string)Loc::getMessage('SHEF_TOOLSAIKB_REFRESH_KEPT'), $view['kept'], false);
			$list((string)Loc::getMessage('SHEF_TOOLSAIKB_REFRESH_ERRORS'), $view['errors'], true);
			?>
		<?php endif;?>
		<form method="POST" action="<?=$h($APPLICATION->GetCurPage())?>?mid=<?=urlencode((string)$mid)?>&amp;lang=<?=LANGUAGE_ID?>" style="margin: 14px 0 0">
			<?=bitrix_sessid_post()?>
			<button type="submit" name="shef_toolsaikb_refresh" value="Y" class="ui-btn ui-btn-primary ui-btn-sm"<?=$canWrite ? '' : ' disabled'?>><?=$h((string)Loc::getMessage('SHEF_TOOLSAIKB_REFRESH'))?></button>
		</form>
	</div>
	<?php
}

require __DIR__.'/options_page.php';
