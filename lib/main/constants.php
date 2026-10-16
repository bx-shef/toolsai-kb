<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Main;

/**
 * Постоянные модуля: идентификатор, коды настроек, группа блокировки.
 */
final class Constants
{
	public const MODULE_ID = 'shef.toolsaikb';

	/** Название раздела в «Базе знаний», если его надо создать. */
	public const OPTION_COLLECTION_NAME = 'KB_collectionname';
	/** Обновлять статьи агентом раз в сутки: Y/N. */
	public const OPTION_AGENT = 'KB_agent';
	/** ID раздела в `note` — пишет импорт, руками не правится. */
	public const OPTION_COLLECTION_ID = 'SYS_collectionid';
	/** Итог последнего импорта одной строкой — для страницы настроек. */
	public const OPTION_LAST_REPORT = 'SYS_lastreport';

	public const DEFAULT_COLLECTION_NAME = 'ИИ: База знаний';

	/** Группа блокировки импорта (Shef\Options\Main\TempFile\Pid): агент и кнопка не идут вдвоём. */
	public const LOCK_GROUP = 'shef.toolsaikb.import';

	/** Адрес документа в «Базе знаний» (WikiImportLinkProvider ядра `note`). */
	public const DOCUMENT_URL = '/note/document/#ID#/';

	/** Агент импорта — раз в сутки. */
	public const AGENT_NAME = '\\Shef\\ToolsAiKb\\Agent\\Refresh::run();';
	public const AGENT_INTERVAL = 86400;

	public static function documentUrl(int $documentId): string
	{
		return str_replace('#ID#', (string)$documentId, self::DOCUMENT_URL);
	}
}
