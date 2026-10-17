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

	/** @var list<array{label: string, title: string, documentId: int}> правленные руками: путь, название, документ */
	public array $manualItems = [];

	/** @var list<string> пропавшие из пакетов статьи, которые оставлены, и почему */
	public array $kept = [];

	/** Фрагментов в индексе поиска после импорта (1.3.0); 0 — индекс не пересобран. */
	public int $indexChunks = 0;

	/** Учесть ещё одну статью с действием $action. */
	public function count(string $action): void
	{
		$this->counts[$action] = ($this->counts[$action] ?? 0) + 1;
	}

	/** Сколько статей с действием $action. */
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
			'удалено в корзину: '.$this->get(Planner::DELETE),
			'нет в пакете, оставлены: '.$this->get(Importer::OBSOLETE_KEPT),
			'фрагментов в индексе: '.$this->indexChunks,
			'ошибок: '.count($this->errors),
		];

		return implode(', ', $parts);
	}

	/**
	 * Для сохранения итога (страница настроек): счётчики, правленные руками,
	 * оставленные, ошибки и время прогона.
	 *
	 * @return array{at: string, collectionId: int, sources: int, counts: array<string, int>, manual: list<array{label: string, title: string, documentId: int}>, kept: list<string>, errors: list<string>, indexChunks: int}
	 */
	public function toArray(string $at): array
	{
		return [
			'at' => $at,
			'collectionId' => $this->collectionId,
			'sources' => $this->sources,
			'counts' => $this->counts,
			'manual' => $this->manualItems,
			'kept' => $this->kept,
			'errors' => $this->errors,
			'indexChunks' => $this->indexChunks,
		];
	}
}
