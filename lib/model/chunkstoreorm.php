<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Model;

use Bitrix\Main\Application;
use Shef\ToolsAiKb\Search\ChunkStore;

/**
 * Фрагменты статей на ORM (ChunkTable).
 */
final class ChunkStoreOrm implements ChunkStore
{
	/** {@inheritDoc} */
	public function replace(array $rows): void
	{
		$connection = Application::getConnection();
		$connection->startTransaction();
		try
		{
			// DELETE, а не TRUNCATE: TRUNCATE в MySQL завершает транзакцию
			$connection->queryExecute('DELETE FROM '.ChunkTable::getTableName());
			foreach($rows as $row)
			{
				$result = ChunkTable::add([
					'NOTE_DOCUMENT_ID' => (int)$row['documentId'],
					'TITLE' => mb_substr($row['title'], 0, 255),
					'HEADING' => mb_substr($row['heading'], 0, 500),
					'TEXT' => $row['text'],
				]);
				if(!$result->isSuccess())
				{
					throw new \RuntimeException('фрагмент не записан: '.implode('; ', $result->getErrorMessages()));
				}
			}
			$connection->commitTransaction();
		}
		catch(\Throwable $throwable)
		{
			$connection->rollbackTransaction();

			throw $throwable;
		}
	}

	/** {@inheritDoc} */
	public function all(): array
	{
		$out = [];
		$rows = ChunkTable::query()
			->setSelect(['NOTE_DOCUMENT_ID', 'TITLE', 'HEADING', 'TEXT'])
			->setOrder(['ID' => 'ASC'])
			->exec();
		while($row = $rows->fetch())
		{
			$out[] = [
				'documentId' => (int)$row['NOTE_DOCUMENT_ID'],
				'title' => (string)$row['TITLE'],
				'heading' => (string)$row['HEADING'],
				'text' => (string)$row['TEXT'],
			];
		}

		return $out;
	}
}
