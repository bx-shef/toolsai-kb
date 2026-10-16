<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Main;

\Bitrix\Main\Loader::includeModule('shef.problems');

use Shef\Problems\Factory\Trait\LoggerProblems;

/**
 * Журнал модуля через shef.problems: сбои импорта — в файл и в журнал
 * событий Битрикса с модулем и ответственным (навык shef-use-logger).
 */
final class ProblemLogger
{
	use LoggerProblems;

	public function __construct()
	{
		$this->initLogger();
	}

	/** Имя класса для записи в журнал. */
	public static function getClassName(): string
	{
		return static::class;
	}

	/** Модуль для записи в журнал. */
	public static function getModuleId(): string
	{
		return Constants::MODULE_ID;
	}

	/**
	 * Записать предупреждение.
	 *
	 * @param array<string, mixed> $context
	 */
	public function warning(string $message, array $context = []): void
	{
		$this->logger->warning($message, $context);
	}
}
