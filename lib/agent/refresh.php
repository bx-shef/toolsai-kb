<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Agent;

use Bitrix\Main\Config\Option;
use Shef\ToolsAiKb\Import\Runner;
use Shef\ToolsAiKb\Main\Constants;
use Shef\ToolsAiKb\Main\Maintenance;

/**
 * Агент «Обновить статьи» — раз в сутки (Constants::AGENT_INTERVAL).
 * Ставит установщик, снимает удаление модуля. Настройка KB_agent = N —
 * агент жив, но импорт не запускает.
 */
final class Refresh
{
	/**
	 * Точка входа агента.
	 *
	 * @return string строка следующего вызова
	 */
	public static function run(): string
	{
		// Обновление копированием: подписки installEvents (Main\Maintenance)
		Maintenance::ensure();
		try
		{
			if(Option::get(Constants::MODULE_ID, Constants::OPTION_AGENT, 'Y') === 'Y')
			{
				Runner::run();
			}
		}
		catch(\Throwable)
		{
			// Агент не должен выпасть из расписания: Runner сам пишет итог и журнал
		}

		return Constants::AGENT_NAME;
	}
}
