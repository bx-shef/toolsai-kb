<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Search;

/**
 * Статья -> фрагменты для индекса (Bm25Index).
 *
 * Фрагмент — раздел статьи под заголовком `#`…`####`; длинный раздел режется
 * по абзацам, короткие абзацы склеиваются, пока помещаются в MAX_LENGTH.
 * Абзац длиннее MAX_LENGTH режется по границе слова. Заголовок фрагмента —
 * цепочка заголовков разделов («Квота › Как считается»), заголовок статьи —
 * отдельно. Блок кода не режется на заголовках внутри него.
 *
 * Без ядра — tests/chunker_test.php.
 */
final class Chunker
{
	/** Предел длины текста фрагмента, символов. */
	public const MAX_LENGTH = 1200;

	/**
	 * @return list<array{heading: string, text: string}> в порядке статьи; пустые — прочь
	 */
	public static function split(string $markdown, int $max = self::MAX_LENGTH): array
	{
		$sections = [];
		/** @var array<int, string> $path */
		$path = [];
		$heading = '';
		$lines = [];
		$fence = false;
		foreach(preg_split('/\R/u', $markdown) ?: [] as $line)
		{
			if(preg_match('/^\s*(```|~~~)/u', $line))
			{
				$fence = !$fence;
			}
			if(!$fence && preg_match('/^(#{1,4})\s+(.+?)\s*#*\s*$/u', $line, $m))
			{
				$sections[] = [$heading, $lines];
				$level = strlen($m[1]);
				// Уровень заголовка -> текст: глубже текущего — прочь
				$path = array_filter($path, static fn(int $l): bool => $l < $level, ARRAY_FILTER_USE_KEY);
				$path[$level] = trim($m[2]);
				ksort($path);
				$heading = implode(' › ', $path);
				$lines = [];
				continue;
			}
			$lines[] = $line;
		}
		$sections[] = [$heading, $lines];

		$out = [];
		foreach($sections as [$sectionHeading, $sectionLines])
		{
			foreach(static::pack(static::paragraphs($sectionLines), $max) as $text)
			{
				$out[] = ['heading' => $sectionHeading, 'text' => $text];
			}
		}

		return $out;
	}

	/**
	 * @param list<string> $lines
	 * @return list<string>
	 */
	private static function paragraphs(array $lines): array
	{
		$text = trim(implode("\n", $lines));
		if($text === '')
		{
			return [];
		}

		return array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/u', $text) ?: []), static fn(string $p): bool => $p !== ''));
	}

	/**
	 * Склеить абзацы во фрагменты не длиннее $max.
	 *
	 * @param list<string> $paragraphs
	 * @return list<string>
	 */
	private static function pack(array $paragraphs, int $max): array
	{
		$out = [];
		$current = '';
		foreach($paragraphs as $paragraph)
		{
			foreach(static::cut($paragraph, $max) as $piece)
			{
				if($current !== '' && mb_strlen($current) + 2 + mb_strlen($piece) > $max)
				{
					$out[] = $current;
					$current = '';
				}
				$current = $current === '' ? $piece : $current."\n\n".$piece;
			}
		}
		if($current !== '')
		{
			$out[] = $current;
		}

		return $out;
	}

	/**
	 * Абзац длиннее $max — куски по границе слова.
	 *
	 * @return list<string>
	 */
	private static function cut(string $paragraph, int $max): array
	{
		$out = [];
		while(mb_strlen($paragraph) > $max)
		{
			$head = mb_substr($paragraph, 0, $max);
			$space = mb_strrpos($head, ' ');
			$at = $space !== false && $space > (int)($max / 2) ? $space : $max;
			$out[] = rtrim(mb_substr($paragraph, 0, $at));
			$paragraph = ltrim(mb_substr($paragraph, $at));
		}
		if($paragraph !== '')
		{
			$out[] = $paragraph;
		}

		return $out;
	}
}
