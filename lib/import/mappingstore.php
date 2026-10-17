<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Import;

/**
 * Таблица соответствий «статья пакета -> документ `note`». Настоящая —
 * Model\MappingStoreOrm (ORM-таблица `shef_toolsaikb_mapping`), в тестах — в памяти.
 */
interface MappingStore
{
	/**
	 * @return array{documentId: int, hash: string}|null
	 */
	public function get(string $sourceCode, string $path): ?array;

	/**
	 * Записать соответствие: документ и хэш того, что импорт в него записал.
	 */
	public function save(string $sourceCode, string $path, int $documentId, string $hash): void;

	/**
	 * Все соответствия.
	 *
	 * @return list<array{sourceCode: string, path: string, documentId: int, hash: string}>
	 */
	public function all(): array;

	/**
	 * Убрать соответствие (документ удалён или его больше нет).
	 */
	public function delete(string $sourceCode, string $path): void;
}
