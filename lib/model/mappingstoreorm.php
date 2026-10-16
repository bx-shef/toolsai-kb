<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Model;

use Bitrix\Main\Type\DateTime;
use Shef\ToolsAiKb\Import\MappingStore;

/**
 * Таблица соответствий на ORM (MappingTable).
 */
final class MappingStoreOrm implements MappingStore
{
	/** {@inheritDoc} */
	public function get(string $sourceCode, string $path): ?array
	{
		$row = MappingTable::query()
			->setSelect(['NOTE_DOCUMENT_ID', 'HASH'])
			->where('SOURCE_CODE', $sourceCode)
			->where('PATH', $path)
			->setLimit(1)
			->fetch();

		return $row ? ['documentId' => (int)$row['NOTE_DOCUMENT_ID'], 'hash' => (string)$row['HASH']] : null;
	}

	/** {@inheritDoc} */
	public function save(string $sourceCode, string $path, int $documentId, string $hash): void
	{
		$row = MappingTable::query()
			->setSelect(['ID'])
			->where('SOURCE_CODE', $sourceCode)
			->where('PATH', $path)
			->setLimit(1)
			->fetch();
		$fields = ['NOTE_DOCUMENT_ID' => $documentId, 'HASH' => $hash, 'UPDATED_AT' => new DateTime()];
		$result = $row
			? MappingTable::update((int)$row['ID'], $fields)
			: MappingTable::add($fields + ['SOURCE_CODE' => $sourceCode, 'PATH' => $path]);
		if(!$result->isSuccess())
		{
			throw new \RuntimeException('соответствие не записано: '.implode('; ', $result->getErrorMessages()));
		}
	}
}
