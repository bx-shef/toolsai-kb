<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Import;

/**
 * Таблица соответствий «статья пакета -> документ `note`». Настоящая —
 * Model\MappingStoreOrm (ORM-таблица `shef_toolsaikb_mapping`), в тестах — в памяти.
 */
interface MappingStore
{
	/**
	 * @return array{documentId: int, hash: string, viewUrl: string}|null
	 */
	public function get(string $sourceCode, string $path): ?array;

	/**
	 * Записать соответствие: документ, хэш того, что импорт в него записал, и
	 * адрес статьи в шаблоне модуля ('' — ссылка на документ `note`).
	 */
	public function save(string $sourceCode, string $path, int $documentId, string $hash, string $viewUrl = ''): void;

	/**
	 * Все соответствия.
	 *
	 * @return list<array{sourceCode: string, path: string, documentId: int, hash: string}>
	 */
	public function all(): array;

	/**
	 * Адреса статей в шаблоне модуля по документам `note`. Документа нет в
	 * таблице или адреса нет — ключа нет.
	 *
	 * @param list<int> $documentIds
	 * @return array<int, string> ID документа -> адрес
	 */
	public function viewUrls(array $documentIds): array;

	/**
	 * Убрать соответствие (документ удалён или его больше нет).
	 */
	public function delete(string $sourceCode, string $path): void;
}
