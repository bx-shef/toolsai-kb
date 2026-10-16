<?php declare(strict_types=1);

/**
 * Подставные «База знаний» и таблица соответствий — в памяти. Повторяют
 * поведение настоящих (Note\BitrixNoteGateway, Model\MappingStoreOrm) и
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
	/** @var array<string, array{documentId: int, hash: string}> */
	public array $rows = [];

	public function get(string $sourceCode, string $path): ?array
	{
		return $this->rows[$sourceCode.'|'.$path] ?? null;
	}

	public function save(string $sourceCode, string $path, int $documentId, string $hash): void
	{
		$this->rows[$sourceCode.'|'.$path] = ['documentId' => $documentId, 'hash' => $hash];
	}
}
