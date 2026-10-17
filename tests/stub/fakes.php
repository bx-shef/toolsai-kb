<?php declare(strict_types=1);

/**
 * Подставные «База знаний», таблица соответствий и фрагменты — в памяти. Повторяют
 * поведение настоящих (Note\BitrixNoteGateway, Model\MappingStoreOrm, Model\ChunkStoreOrm) и
 * пишут каждый вызов, чтобы тест видел, что импорт сделал.
 */

use Shef\ToolsAiKb\Import\MappingStore;
use Shef\ToolsAiKb\Note\DocumentRemovedException;
use Shef\ToolsAiKb\Note\NoteGateway;

final class FakeNote implements NoteGateway
{
	/** @var array<int, string> ID раздела -> состояние */
	public array $collections = [];
	/** @var array<int, array{collectionId: int, parentId: ?int, archived: bool, removed: bool, format: string, title: string, markdown: string}> */
	public array $documents = [];
	/** @var list<string> */
	public array $calls = [];
	/** @var array<string, list<array{id: int, title: string, snippet: string, score: float}>> запрос -> ответ */
	public array $searchAnswers = [];
	public int $nextId = 1;
	public bool $failCreate = false;

	public function getCollectionState(int $collectionId): string
	{
		return $this->collections[$collectionId] ?? self::COLLECTION_MISSING;
	}

	public function createCollection(string $name, int $ownerId): int
	{
		$id = $this->nextId++;
		$this->collections[$id] = self::COLLECTION_OK;
		$this->calls[] = 'collection '.$name.' by '.$ownerId;

		return $id;
	}

	public function createDocument(int $collectionId, ?int $parentId, string $title, string $markdown, int $userId): int
	{
		if($this->failCreate)
		{
			throw new RuntimeException('сбой note');
		}
		$id = $this->nextId++;
		$this->documents[$id] = ['collectionId' => $collectionId, 'parentId' => $parentId, 'archived' => false, 'removed' => false, 'format' => 'md', 'title' => $title, 'markdown' => $markdown];
		$this->calls[] = 'create '.$id.' '.$title;

		return $id;
	}

	public function overwriteDocument(int $documentId, string $title, string $markdown, int $userId): void
	{
		if(!isset($this->documents[$documentId]) || $this->documents[$documentId]['removed'] || $this->documents[$documentId]['archived'])
		{
			throw new DocumentRemovedException('документ в корзине');
		}
		$this->documents[$documentId]['title'] = $title;
		$this->documents[$documentId]['markdown'] = $markdown;
		$this->calls[] = 'overwrite '.$documentId.' '.$title;
	}

	public bool $failDelete = false;

	public function deleteDocument(int $documentId, int $userId): array
	{
		if($this->failDelete)
		{
			throw new RuntimeException('сбой удаления');
		}
		if(!isset($this->documents[$documentId]) || $this->documents[$documentId]['removed'])
		{
			return [];
		}
		$trashed = [];
		foreach($this->documents as $id => $d)
		{
			if(!$d['removed'] && ($id === $documentId || $d['parentId'] === $documentId))
			{
				$this->documents[$id]['removed'] = true;
				$trashed[] = $id;
			}
		}
		$this->calls[] = 'delete '.$documentId.' by '.$userId;

		return $trashed;
	}

	public function getCollectionTree(int $collectionId): array
	{
		$out = [];
		foreach($this->documents as $id => $d)
		{
			if($d['collectionId'] === $collectionId && !$d['removed'] && !$d['archived'])
			{
				$out[$id] = $d['parentId'];
			}
		}

		return $out;
	}

	public function getDocument(int $documentId): ?array
	{
		$d = $this->documents[$documentId] ?? null;
		if($d === null)
		{
			return null;
		}

		return ['archived' => $d['archived'], 'format' => $d['format'], 'title' => $d['title'], 'markdown' => $d['markdown']];
	}

	public function search(array $terms, int $limit): array
	{
		$key = implode(' ', $terms);
		$this->calls[] = 'search '.$key.' '.$limit;

		return array_slice($this->searchAnswers[$key] ?? [], 0, $limit);
	}
}

final class FakeMapping implements MappingStore
{
	/** @var array<string, array{documentId: int, hash: string, viewUrl: string}> */
	public array $rows = [];
	/** @var list<string> записи save() — тест видит, что импорт писал */
	public array $saves = [];

	public function get(string $sourceCode, string $path): ?array
	{
		return $this->rows[$sourceCode.'|'.$path] ?? null;
	}

	public function save(string $sourceCode, string $path, int $documentId, string $hash, string $viewUrl = ''): void
	{
		$this->rows[$sourceCode.'|'.$path] = ['documentId' => $documentId, 'hash' => $hash, 'viewUrl' => $viewUrl];
		$this->saves[] = $sourceCode.'|'.$path;
	}

	public function viewUrls(array $documentIds): array
	{
		$out = [];
		foreach($this->rows as $row)
		{
			if(in_array($row['documentId'], $documentIds, true) && ($row['viewUrl'] ?? '') !== '')
			{
				$out[$row['documentId']] = $row['viewUrl'];
			}
		}

		return $out;
	}

	public function all(): array
	{
		$out = [];
		foreach($this->rows as $key => $row)
		{
			[$sourceCode, $path] = explode('|', $key, 2);
			$out[] = ['sourceCode' => $sourceCode, 'path' => $path] + $row;
		}

		return $out;
	}

	public function delete(string $sourceCode, string $path): void
	{
		unset($this->rows[$sourceCode.'|'.$path]);
	}
}

final class FakeChunkStore implements \Shef\ToolsAiKb\Search\ChunkStore
{
	/** @var list<array{documentId: int, title: string, heading: string, text: string}> */
	public array $rows = [];
	public int $replaces = 0;
	public bool $fail = false;

	public function replace(array $rows): void
	{
		if($this->fail)
		{
			throw new RuntimeException('сбой таблицы');
		}
		$this->rows = $rows;
		$this->replaces++;
	}

	public function all(): array
	{
		return $this->rows;
	}
}
