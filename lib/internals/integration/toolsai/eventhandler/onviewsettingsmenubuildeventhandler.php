<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Internals\Integration\ToolsAi\EventHandler;

use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Shef\ToolsAiKb\Main\Constants;

/**
 * Обработчик shef.toolsai:OnViewSettingsMenuBuild (shef.toolsai 1.53.0, у нас
 * 1.1.2) — пункт «ИИ база знаний» в меню шестерёнки вида «ИИ»: ссылка на
 * страницу настроек модуля, только при праве на модуль от R (как у options.php).
 *
 * Контракт — shef.toolsai Public\Event\ViewSettingsMenuBuildEvent: ответ
 * EventResult SUCCESS с ['items' => [['text', 'url']]]. Права проверяет
 * обработчик: вид показывает всё, что вернули. Типизирован Main\Event: без
 * shef.toolsai 1.53 событие просто не придёт. Сбой — пустой список.
 */
final class OnViewSettingsMenuBuildEventHandler
{
	public const TEXT = 'ИИ база знаний';

	public static function handle(Event $event): EventResult
	{
		try
		{
			global $APPLICATION;
			$items = static::items(is_object($APPLICATION) ? (string)$APPLICATION->GetGroupRight(Constants::MODULE_ID) : 'D');
		}
		catch(\Throwable)
		{
			$items = [];
		}

		return new EventResult(EventResult::SUCCESS, ['items' => $items], Constants::MODULE_ID);
	}

	/**
	 * Пункт по праву на модуль. Чистая часть.
	 *
	 * @return list<array{text: string, url: string}>
	 */
	public static function items(string $moduleRight): array
	{
		return $moduleRight >= 'R' ? [['text' => self::TEXT, 'url' => Constants::settingsUrl()]] : [];
	}
}
