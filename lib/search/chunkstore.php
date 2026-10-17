<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Search;

/**
 * Хранилище фрагментов статей (индекс Bm25Index). Настоящее —
 * Model\ChunkStoreOrm (таблица `shef_toolsaikb_chunk`), в тестах — в памяти.
 */
interface ChunkStore
{
	/**
	 * Заменить все фрагменты целиком.
	 *
	 * @param list<array{documentId: int, title: string, heading: string, text: string}> $rows
	 */
	public function replace(array $rows): void;

	/**
	 * Все фрагменты в порядке записи.
	 *
	 * @return list<array{documentId: int, title: string, heading: string, text: string}>
	 */
	public function all(): array;
}
