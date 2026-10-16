<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Public\Event;

use Bitrix\Main\Event;

/**
 * Событие `shef.toolsaikb:OnKbSourcesCollect`: модули отдают свои пакеты
 * статей для импорта в «Базу знаний».
 *
 * Параметров нет. Ответ обработчика:
 *
 *   new EventResult(EventResult::SUCCESS, ['sources' => [
 *       ['code' => 'shef.toolsai:guide', 'title' => 'ИИ: Справка по модулю', 'dir' => '/абсолютный/путь/к/каталогу'],
 *   ], 'shef.toolsai');
 *
 * `code` начинается с идентификатора модуля и двоеточия; `dir` — каталог со
 * статьями `*.md` (подкаталоги тоже). Правила проверки — Import\SourceValidator.
 *
 * Отправляет Import\SourceCollector при каждом импорте (агент, кнопка
 * «Обновить статьи», установка).
 */
final class OnKbSourcesCollectEvent extends Event
{
	public const MODULE_ID = 'shef.toolsaikb';
	public const NAME = 'OnKbSourcesCollect';

	/** Событие без параметров: модуль и имя зафиксированы. */
	public function __construct()
	{
		parent::__construct(self::MODULE_ID, self::NAME);
	}
}
