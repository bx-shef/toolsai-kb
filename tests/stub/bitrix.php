<?php declare(strict_types=1);

/**
 * Заглушки ядра Битрикса — ровно то, что трогают тестируемые классы модуля.
 * Сигнатуры — как в ядре (main), поведение — минимальное.
 */

namespace Bitrix\Main
{
	class Loader
	{
		/** @var array<string, bool> какие модули «установлены» */
		public static array $modules = ['note' => true];

		public static function includeModule(string $module): bool
		{
			return static::$modules[$module] ?? false;
		}
	}

	class Error
	{
		public function __construct(private string $message, private int|string $code = 0)
		{
		}

		public function getMessage(): string
		{
			return $this->message;
		}
	}

	class Result
	{
		/** @var list<Error> */
		private array $errors = [];
		private array $data = [];

		public function isSuccess(): bool
		{
			return $this->errors === [];
		}

		public function addError(Error $error): static
		{
			$this->errors[] = $error;

			return $this;
		}

		/** @return list<string> */
		public function getErrorMessages(): array
		{
			return array_map(static fn(Error $e): string => $e->getMessage(), $this->errors);
		}

		public function setData(array $data): static
		{
			$this->data = $data;

			return $this;
		}

		public function getData(): array
		{
			return $this->data;
		}
	}

	class EventResult
	{
		public const UNDEFINED = 0;
		public const SUCCESS = 1;
		public const ERROR = 2;

		public function __construct(private int $type, private mixed $parameters = null, private ?string $moduleId = null)
		{
		}

		public function getType(): int
		{
			return $this->type;
		}

		public function getParameters(): mixed
		{
			return $this->parameters;
		}

		public function getModuleId(): ?string
		{
			return $this->moduleId;
		}
	}

	class Event
	{
		/** @var list<callable(Event): mixed> обработчики «по модулю:событию» */
		public static array $handlers = [];

		/** @var list<EventResult> */
		private array $results = [];

		public function __construct(private string $moduleId, private string $type, private array $parameters = [])
		{
		}

		public function getModuleId(): string
		{
			return $this->moduleId;
		}

		public function getEventType(): string
		{
			return $this->type;
		}

		public function send(): void
		{
			foreach(static::$handlers[$this->moduleId.':'.$this->type] ?? [] as $handler)
			{
				$result = $handler($this);
				if($result instanceof EventResult)
				{
					$this->results[] = $result;
				}
			}
		}

		/** @return list<EventResult> */
		public function getResults(): array
		{
			return $this->results;
		}
	}
}

namespace Bitrix\Main\Command\Exception
{
	class CommandException extends \Exception
	{
		public function __construct(object $command, string $message = '', int $code = 0, ?\Throwable $previous = null)
		{
			parent::__construct($message, $code, $previous);
		}
	}
}

namespace Bitrix\Main\Provider\Params
{
	class Pager
	{
		public function __construct(protected int $limit = 50, protected int $offset = 0)
		{
		}

		public function getLimit(): int
		{
			return $this->limit;
		}

		public function getOffset(): int
		{
			return $this->offset;
		}
	}
}
