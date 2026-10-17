<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Search;

/**
 * Лексический индекс фрагментов статей: BM25 (k1 1.2, b 0.75), слова —
 * QueryNormalizer::tokens() (основы без стоп-слов), заголовок статьи и раздела
 * весит втрое — как подбор разделов справки shef.toolsai до 1.59.
 *
 * Права индекс не знает: кандидатов (документы, которые видит сотрудник)
 * задаёт вызывающий — KbSearchService берёт их из поиска `note`.
 *
 * Состояние — массивы (toArray()/fromArray()): индекс строится один раз
 * после импорта и лежит в кеше, а не пересчитывается на каждый вопрос.
 *
 * Без ядра — tests/bm25index_test.php.
 */
final class Bm25Index
{
	public const K1 = 1.2;
	public const B = 0.75;
	/** Во сколько раз заголовок весомее текста. */
	public const HEADING_WEIGHT = 3;

	/**
	 * @param list<array{documentId: int, heading: string, text: string, tf: array<string, int>, len: int}> $chunks
	 * @param array<string, int> $df слово -> в скольких фрагментах
	 */
	private function __construct(
		private readonly array $chunks,
		private readonly array $df,
		private readonly float $avgLength,
	)
	{
	}

	/**
	 * @param list<array{documentId: int, title: string, heading: string, text: string}> $rows фрагменты (ChunkStore::all())
	 */
	public static function build(array $rows): self
	{
		$chunks = [];
		$df = [];
		$total = 0;
		foreach($rows as $row)
		{
			$head = QueryNormalizer::tokens($row['title'].' '.$row['heading']);
			$tf = [];
			foreach(array_merge(QueryNormalizer::tokens($row['text']), ...array_fill(0, self::HEADING_WEIGHT, $head)) as $token)
			{
				$tf[$token] = ($tf[$token] ?? 0) + 1;
			}
			foreach($tf as $token => $_)
			{
				$df[$token] = ($df[$token] ?? 0) + 1;
			}
			$length = array_sum($tf);
			$total += $length;
			$chunks[] = ['documentId' => (int)$row['documentId'], 'heading' => (string)$row['heading'], 'text' => (string)$row['text'], 'tf' => $tf, 'len' => $length];
		}

		return new self($chunks, $df, $chunks === [] ? 0.0 : $total / count($chunks));
	}

	/** Для кеша. */
	public function toArray(): array
	{
		return ['chunks' => $this->chunks, 'df' => $this->df, 'avg' => $this->avgLength];
	}

	/** Из кеша; негодное — пустой индекс. */
	public static function fromArray(mixed $data): self
	{
		if(!is_array($data) || !is_array($data['chunks'] ?? null) || !is_array($data['df'] ?? null))
		{
			return new self([], [], 0.0);
		}

		return new self($data['chunks'], $data['df'], (float)($data['avg'] ?? 0));
	}

	/** Сколько фрагментов в индексе. */
	public function size(): int
	{
		return count($this->chunks);
	}

	/** Есть ли у документа фрагменты в индексе. */
	public function hasDocument(int $documentId): bool
	{
		foreach($this->chunks as $chunk)
		{
			if($chunk['documentId'] === $documentId)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Документы-кандидаты по вопросу: оценка документа и `best` — лучший фрагмент,
	 * фрагменты — лучшие по оценке, но в порядке статьи и в пределах $budget
	 * символов на документ.
	 *
	 * @param list<int> $documentIds кандидаты (права уже проверены)
	 * @return array<int, array{score: float, best: string, fragments: list<array{heading: string, text: string}>}>
	 *         только документы с ненулевой оценкой, по убыванию оценки
	 */
	public function rank(string $query, array $documentIds, int $budget, int $maxFragments = 3): array
	{
		$terms = array_values(array_unique(QueryNormalizer::tokens($query)));
		$allowed = array_fill_keys(array_map('intval', $documentIds), true);
		if($terms === [] || $allowed === [] || $this->chunks === [])
		{
			return [];
		}

		$count = count($this->chunks);
		/** @var array<int, list<array{pos: int, score: float}>> $scored */
		$scored = [];
		foreach($this->chunks as $pos => $chunk)
		{
			if(!isset($allowed[$chunk['documentId']]))
			{
				continue;
			}
			$score = 0.0;
			foreach($terms as $term)
			{
				$f = $chunk['tf'][$term] ?? 0;
				if($f === 0)
				{
					continue;
				}
				$n = $this->df[$term] ?? 0;
				$idf = log(1 + ($count - $n + 0.5) / ($n + 0.5));
				$norm = $this->avgLength > 0 ? $chunk['len'] / $this->avgLength : 1.0;
				$score += $idf * $f * (self::K1 + 1) / ($f + self::K1 * (1 - self::B + self::B * $norm));
			}
			if($score > 0)
			{
				$scored[$chunk['documentId']][] = ['pos' => $pos, 'score' => $score];
			}
		}

		$out = [];
		foreach($scored as $documentId => $list)
		{
			usort($list, static fn(array $a, array $b): int => [$b['score'], $a['pos']] <=> [$a['score'], $b['pos']]);
			$picked = [];
			$used = 0;
			foreach($list as $item)
			{
				$length = mb_strlen($this->chunks[$item['pos']]['text']);
				if(count($picked) >= $maxFragments || ($picked !== [] && $used + $length > $budget))
				{
					continue;
				}
				$picked[] = $item['pos'];
				$used += $length;
			}
			sort($picked);
			$out[$documentId] = [
				'score' => $list[0]['score'],
				'best' => $this->chunks[$list[0]['pos']]['text'],
				'fragments' => array_map(fn(int $pos): array => [
					'heading' => $this->chunks[$pos]['heading'],
					'text' => mb_substr($this->chunks[$pos]['text'], 0, $budget),
				], $picked),
			];
		}
		uasort($out, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

		return $out;
	}
}
