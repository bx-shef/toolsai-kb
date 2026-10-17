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
	/** Искать по всей «Базе знаний», а не только в своём разделе: Y/N, по умолчанию N. */
	public const OPTION_SEARCH_ALL = 'KB_searchall';
	/** ID раздела в `note` — пишет импорт, руками не правится. */
	public const OPTION_COLLECTION_ID = 'SYS_collectionid';
	/** Итог последнего импорта одной строкой — для страницы настроек. */
	public const OPTION_LAST_REPORT = 'SYS_lastreport';
	/** Метка пересборки индекса фрагментов (1.3.0) — ключ кеша индекса. */
	public const OPTION_INDEX_VERSION = 'SYS_indexversion';

	public const DEFAULT_COLLECTION_NAME = 'ИИ: База знаний';

	/** Группа блокировки импорта (Shef\Options\Main\TempFile\Pid): агент и кнопка не идут вдвоём. */
	public const LOCK_GROUP = 'shef.toolsaikb.import';

	/** Адрес документа в «Базе знаний» (WikiImportLinkProvider ядра `note`). */
	public const DOCUMENT_URL = '/note/document/#ID#/';

	/** Сколько секунд держать ответ поиска в кеше (на пользователя и запрос). */
	public const SEARCH_CACHE_TTL = 60;

	/** Сколько секунд держать индекс фрагментов в кеше; сменилась метка — новый ключ. */
	public const INDEX_CACHE_TTL = 86400 * 7;

	/** Агент импорта — раз в сутки. */
	public const AGENT_NAME = '\\Shef\\ToolsAiKb\\Agent\\Refresh::run();';
	public const AGENT_INTERVAL = 86400;

	/** Страница настроек модуля на языке страницы (пункт в меню шестерёнки вида «ИИ», 1.1.2). */
	public static function settingsUrl(): string
	{
		return '/bitrix/admin/settings.php?mid='.self::MODULE_ID.'&lang='.(defined('LANGUAGE_ID') ? LANGUAGE_ID : 'ru');
	}

	/** Адрес документа `note` от корня портала. */
	public static function documentUrl(int $documentId): string
	{
		return str_replace('#ID#', (string)$documentId, self::DOCUMENT_URL);
	}
}
