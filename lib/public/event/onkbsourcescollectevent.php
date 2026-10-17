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
 *       ['code' => 'shef.toolsai:guide', 'title' => 'ИИ: Справка по модулю', 'dir' => '/абсолютный/путь/к/каталогу',
 *        'viewUrl' => '/shef/toolsai/kb/guide/{path}'],
 *   ], 'shef.toolsai');
 *
 * `code` начинается с идентификатора модуля и двоеточия; `dir` — каталог со
 * статьями `*.md` (подкаталоги тоже). `viewUrl` — необязательный шаблон адреса
 * статьи в родном шаблоне модуля: путь своего портала (начинается с `/`), в нём
 * `{path}` — путь файла в пакете без `.md`, `{slug}` — имя файла без `.md`.
 * Есть viewUrl — поиск (KbSearchService) ведёт на него, иначе на документ
 * `note`. Правила проверки — Import\SourceValidator и Import\ViewUrl.
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
