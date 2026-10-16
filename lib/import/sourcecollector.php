<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Import;

use Bitrix\Main\EventResult;
use Shef\ToolsAiKb\Public\Event\OnKbSourcesCollectEvent;

/**
 * Собрать пакеты статей: отправить событие OnKbSourcesCollect и проверить
 * ответы (SourceValidator).
 *
 * Исключение обработчика прерывает отправку события (поведение
 * `Bitrix\Main\Event::send()`), поэтому оно ловится здесь и идёт в ошибки:
 * чужой сбой не должен оставить портал без импорта навсегда, но и молча
 * пропасть не должен.
 */
final class SourceCollector
{
	/**
	 * @return array{sources: list<Source>, errors: list<string>}
	 */
	public static function collect(): array
	{
		$event = new OnKbSourcesCollectEvent();
		try
		{
			$event->send();
		}
		catch(\Throwable $throwable)
		{
			return ['sources' => [], 'errors' => ['обработчик события '.OnKbSourcesCollectEvent::NAME.' упал: '.$throwable->getMessage()]];
		}

		$responses = [];
		foreach($event->getResults() as $result)
		{
			$responses[] = [
				'moduleId' => (string)$result->getModuleId(),
				'success' => $result->getType() === EventResult::SUCCESS,
				'parameters' => $result->getParameters(),
			];
		}

		return (new SourceValidator())->validate($responses);
	}
}
