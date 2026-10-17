<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Settings;

use Shef\ToolsAiKb\Import\Importer;
use Shef\ToolsAiKb\Import\Planner;
use Shef\ToolsAiKb\Main\Constants;

/**
 * Сводка последнего импорта для страницы настроек — подготовка без вывода.
 *
 * На входе — сохранённый итог (Import\Runner::reportJson()) или старая
 * строка 1.0/1.1 (её показываем как есть). На выходе — плоские данные:
 * время, плитки счётчиков (нули приглушены), правленные руками статьи
 * (название и ссылка на документ, без названия — путь в пакете), оставленные,
 * ошибки. Экранирует вывод options.php — здесь только данные.
 *
 * Без ядра — tests/reportview_test.php.
 */
final class ReportView
{
	/** Плитки: код -> подпись. Порядок — порядок на странице. */
	public const COUNTERS = [
		'created' => 'Создано',
		'updated' => 'Обновлено',
		'unchanged' => 'Без изменений',
		'manual' => 'Правлены руками',
		'archived' => 'В архиве',
		'deleted' => 'Удалено в корзину',
		'errors' => 'Ошибки',
	];

	/**
	 * @param string $raw значение SYS_lastreport
	 * @return array{empty: bool, legacy: string, at: string, sources: int,
	 *   counters: list<array{code: string, label: string, value: int, muted: bool}>,
	 *   manual: list<array{title: string, url: string}>, kept: list<string>, errors: list<string>}
	 */
	public static function fromStored(string $raw): array
	{
		$raw = trim($raw);
		$data = $raw !== '' ? json_decode($raw, true) : null;
		if(!is_array($data))
		{
			$view = self::build([]);
			$view['legacy'] = $raw;

			return $view;
		}

		return self::build($data);
	}

	/**
	 * @param array $data ImportReport::toArray()
	 */
	public static function build(array $data): array
	{
		$counts = is_array($data['counts'] ?? null) ? $data['counts'] : [];
		$n = static fn(string $key): int => (int)($counts[$key] ?? 0);
		$errors = self::strings($data['errors'] ?? []);
		$values = [
			'created' => $n(Planner::CREATE) + $n(Planner::RECREATE),
			'updated' => $n(Planner::UPDATE),
			'unchanged' => $n(Planner::UNCHANGED),
			'manual' => $n(Planner::MANUAL) + $n(Importer::OBSOLETE_KEPT),
			'archived' => $n(Planner::ARCHIVED) + $n(Importer::REMOVED),
			'deleted' => $n(Planner::DELETE),
			'errors' => count($errors),
		];
		$counters = [];
		foreach(self::COUNTERS as $code => $label)
		{
			$counters[] = ['code' => $code, 'label' => $label, 'value' => $values[$code], 'muted' => $values[$code] === 0];
		}

		$manual = [];
		foreach(is_array($data['manual'] ?? null) ? $data['manual'] : [] as $item)
		{
			if(!is_array($item))
			{
				continue;
			}
			$title = trim((string)($item['title'] ?? ''));
			$id = (int)($item['documentId'] ?? 0);
			$manual[] = [
				'title' => $title !== '' ? $title : (string)($item['label'] ?? ''),
				'url' => $id > 0 ? Constants::documentUrl($id) : '',
			];
		}

		return [
			'empty' => !isset($data['at']),
			'legacy' => '',
			'at' => (string)($data['at'] ?? ''),
			'sources' => (int)($data['sources'] ?? 0),
			'counters' => $counters,
			'manual' => $manual,
			'kept' => self::strings($data['kept'] ?? []),
			'errors' => $errors,
		];
	}

	/** @return list<string> */
	private static function strings(mixed $list): array
	{
		return is_array($list) ? array_values(array_map('strval', array_filter($list, 'is_scalar'))) : [];
	}
}
