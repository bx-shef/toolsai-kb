<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Import;

/**
 * Итог одного прогона импорта: раздел, сколько статей и что с ними сделано,
 * ошибки. Показывается на странице настроек, ошибки уходят в журнал.
 */
final class ImportReport
{
	public int $collectionId = 0;
	public bool $collectionCreated = false;
	public int $sources = 0;

	/** @var array<string, int> действие Planner::* -> число статей */
	public array $counts = [];

	/** @var list<string> */
	public array $errors = [];

	/** @var list<string> статьи, которые не тронуты, потому что их поправили руками */
	public array $manual = [];

	public function count(string $action): void
	{
		$this->counts[$action] = ($this->counts[$action] ?? 0) + 1;
	}

	public function get(string $action): int
	{
		return $this->counts[$action] ?? 0;
	}

	/**
	 * Одной строкой по-русски — для страницы настроек и журнала.
	 */
	public function summary(): string
	{
		$parts = [
			'пакетов: '.$this->sources,
			'создано: '.($this->get(Planner::CREATE) + $this->get(Planner::RECREATE)),
			'обновлено: '.$this->get(Planner::UPDATE),
			'без изменений: '.$this->get(Planner::UNCHANGED),
			'правлены руками (не тронуты): '.$this->get(Planner::MANUAL),
			'в архиве (не тронуты): '.($this->get(Planner::ARCHIVED) + $this->get(Importer::REMOVED)),
			'ошибок: '.count($this->errors),
		];

		return implode(', ', $parts);
	}
}
