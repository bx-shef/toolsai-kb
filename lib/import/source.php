<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Import;

/**
 * Пакет статей от модуля: ответ на событие `shef.toolsaikb:OnKbSourcesCollect`
 * после проверки (SourceValidator).
 */
final class Source
{
	/**
	 * @param string $code код пакета, `<moduleId>:<имя>`, например `shef.toolsai:guide`
	 * @param string $title название раздела в «Базе знаний»
	 * @param string $dir абсолютный путь к каталогу со статьями `*.md`
	 * @param string $moduleId модуль, который отдал пакет
	 * @param string $viewUrl шаблон адреса статьи в шаблоне модуля (ViewUrl); '' — нет
	 */
	public function __construct(
		public readonly string $code,
		public readonly string $title,
		public readonly string $dir,
		public readonly string $moduleId,
		public readonly string $viewUrl = '',
	)
	{
	}
}
