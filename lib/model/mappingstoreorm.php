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
			->setSelect(['NOTE_DOCUMENT_ID', 'HASH', 'VIEW_URL'])
			->where('SOURCE_CODE', $sourceCode)
			->where('PATH', $path)
			->setLimit(1)
			->fetch();

		return $row ? ['documentId' => (int)$row['NOTE_DOCUMENT_ID'], 'hash' => (string)$row['HASH'], 'viewUrl' => (string)$row['VIEW_URL']] : null;
	}

	/** {@inheritDoc} */
	public function save(string $sourceCode, string $path, int $documentId, string $hash, string $viewUrl = ''): void
	{
		$row = MappingTable::query()
			->setSelect(['ID'])
			->where('SOURCE_CODE', $sourceCode)
			->where('PATH', $path)
			->setLimit(1)
			->fetch();
		$fields = ['NOTE_DOCUMENT_ID' => $documentId, 'HASH' => $hash, 'VIEW_URL' => $viewUrl, 'UPDATED_AT' => new DateTime()];
		$result = $row
			? MappingTable::update((int)$row['ID'], $fields)
			: MappingTable::add($fields + ['SOURCE_CODE' => $sourceCode, 'PATH' => $path]);
		if(!$result->isSuccess())
		{
			throw new \RuntimeException('соответствие не записано: '.implode('; ', $result->getErrorMessages()));
		}
	}

	/** {@inheritDoc} */
	public function all(): array
	{
		$out = [];
		$rows = MappingTable::query()
			->setSelect(['SOURCE_CODE', 'PATH', 'NOTE_DOCUMENT_ID', 'HASH'])
			->setOrder(['ID' => 'ASC'])
			->exec();
		while($row = $rows->fetch())
		{
			$out[] = [
				'sourceCode' => (string)$row['SOURCE_CODE'],
				'path' => (string)$row['PATH'],
				'documentId' => (int)$row['NOTE_DOCUMENT_ID'],
				'hash' => (string)$row['HASH'],
			];
		}

		return $out;
	}

	/** {@inheritDoc} */
	public function viewUrls(array $documentIds): array
	{
		$documentIds = array_values(array_unique(array_filter(array_map('intval', $documentIds), static fn(int $id): bool => $id > 0)));
		if($documentIds === [])
		{
			return [];
		}
		$out = [];
		$rows = MappingTable::query()
			->setSelect(['NOTE_DOCUMENT_ID', 'VIEW_URL'])
			->whereIn('NOTE_DOCUMENT_ID', $documentIds)
			->where('VIEW_URL', '!=', '')
			->exec();
		while($row = $rows->fetch())
		{
			$out[(int)$row['NOTE_DOCUMENT_ID']] = (string)$row['VIEW_URL'];
		}

		return $out;
	}

	/** {@inheritDoc} */
	public function delete(string $sourceCode, string $path): void
	{
		$row = MappingTable::query()
			->setSelect(['ID'])
			->where('SOURCE_CODE', $sourceCode)
			->where('PATH', $path)
			->setLimit(1)
			->fetch();
		if(!$row)
		{
			return;
		}
		$result = MappingTable::delete((int)$row['ID']);
		if(!$result->isSuccess())
		{
			throw new \RuntimeException('соответствие не удалено: '.implode('; ', $result->getErrorMessages()));
		}
	}
}
