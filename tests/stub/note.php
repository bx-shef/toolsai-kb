<?php declare(strict_types=1);

/**
 * Заглушки публичного API модуля `note` 26.250 — ровно то, что зовёт
 * Note\BitrixNoteGateway. Конструкторы повторяют имена и порядок параметров
 * ядра (lib/Public/Command/*.php, lib/Public/Provider/*.php): шлюз зовёт их
 * именованными аргументами, и опечатка в имени уронит тест так же, как
 * портал. Вызовы пишутся в NoteStub::$calls, ответы задаются там же.
 */

namespace
{
	final class NoteStub
	{
		/** @var list<array{0: string, 1: array}> */
		public static array $calls = [];
		/** @var array<int, object> */
		public static array $collections = [];
		/** @var array<int, object> */
		public static array $documents = [];
		/** @var list<object> */
		public static array $searchResults = [];
		public static ?\Throwable $overwriteThrows = null;
		public static int $nextId = 100;

		public static function reset(): void
		{
			static::$calls = [];
			static::$collections = [];
			static::$documents = [];
			static::$searchResults = [];
			static::$overwriteThrows = null;
			static::$nextId = 100;
		}
	}

	/** Объект ORM ядра с геттерами по полям. */
	final class NoteStubEntity
	{
		public function __construct(private array $fields)
		{
		}

		public function __call(string $name, array $args): mixed
		{
			$key = lcfirst(substr($name, 3));
			if(!array_key_exists($key, $this->fields))
			{
				throw new \Error('нет геттера '.$name);
			}

			return $this->fields[$key];
		}
	}
}

namespace Bitrix\Note\Internal\Exceptions
{
	class DocumentArchivedException extends \Exception {}
	class DocumentInRecycleBinException extends \Exception {}
	class DocumentNotFoundException extends \Exception {}
}

namespace Bitrix\Note\Public\Command
{
	use Bitrix\Main\Command\Exception\CommandException;
	use Bitrix\Main\Result;

	class CreateCollectionCommand
	{
		public function __construct(
			private string $name,
			private int $createdBy,
			private int $position = 0,
			private string|int|null $policyLevel = null,
			private ?array $permissions = null,
		)
		{
		}

		public function run(): Result
		{
			\NoteStub::$calls[] = ['createCollection', get_object_vars($this)];
			$id = \NoteStub::$nextId++;
			\NoteStub::$collections[$id] = new \NoteStubEntity(['id' => $id, 'isArchived' => false]);

			return (new Result())->setData(['collection' => \NoteStub::$collections[$id]]);
		}
	}

	class CreateDocumentCommand
	{
		public function __construct(
			private int $collectionId,
			private ?int $parentId,
			private string $title,
			private string $markdown,
			private int $userId,
			private string $contentFormat = 'yjs',
			private ?object $documentService = null,
			private ?object $pushService = null,
			private bool $notifyInitiator = false,
			private string $analyticsType = 'bk',
		)
		{
		}

		public function run(): Result
		{
			\NoteStub::$calls[] = ['createDocument', get_object_vars($this)];
			$id = \NoteStub::$nextId++;
			\NoteStub::$documents[$id] = new \NoteStubEntity([
				'id' => $id, 'isArchived' => false, 'contentFormat' => $this->contentFormat,
				'title' => $this->title, 'markdown' => $this->markdown,
			]);

			return (new Result())->setData(['document' => \NoteStub::$documents[$id]]);
		}
	}

	class OverwriteDocumentContentCommand
	{
		public function __construct(
			private int $documentId,
			private string $markdown,
			private int $userId,
			private bool $overwrite = false,
			private ?string $title = null,
		)
		{
		}

		public function run(): Result
		{
			\NoteStub::$calls[] = ['overwrite', get_object_vars($this)];
			if(\NoteStub::$overwriteThrows !== null)
			{
				// Как AbstractCommand::run(): исключение команды — внутри CommandException
				throw new CommandException($this, 'Command has unprocessed exception', previous: \NoteStub::$overwriteThrows);
			}

			return new Result();
		}
	}
}

namespace Bitrix\Note\Public\Provider\Param\Search
{
	final class SearchQuery
	{
		public function __construct(public readonly string $query)
		{
		}

		public function getQuery(): string
		{
			return $this->query;
		}
	}
}

namespace Bitrix\Note\Public\Provider
{
	use Bitrix\Main\Provider\Params\Pager;
	use Bitrix\Note\Public\Provider\Param\Search\SearchQuery;

	class CollectionProvider
	{
		public function getById(int $id): ?object
		{
			return \NoteStub::$collections[$id] ?? null;
		}
	}

	class DocumentProvider
	{
		public function getById(int $id): ?object
		{
			return \NoteStub::$documents[$id] ?? null;
		}
	}

	final class SearchProvider
	{
		public function search(SearchQuery $query, Pager $pager, bool $withSnippets = true): iterable
		{
			\NoteStub::$calls[] = ['search', ['query' => $query->getQuery(), 'limit' => $pager->getLimit(), 'offset' => $pager->getOffset()]];

			return \NoteStub::$searchResults;
		}
	}
}
