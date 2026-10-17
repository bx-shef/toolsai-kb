<?php declare(strict_types=1);

/**
 * Замер поиска статей (shef.toolsaikb) без портала: hit@k, MRR, recall@k по
 * набору «вопрос → нужные статьи».
 *
 * Сравниваются два пути одного KbSearchService::findWith():
 *  - «note» — только поиск «Базы знаний» (как до 1.3.0): все слова сразу,
 *    добор по словам, порядок ResultMerger;
 *  - «rag» — те же кандидаты и индекс фрагментов (Bm25Index, 1.3.0).
 *
 * Поиск `note` здесь — MemoryNote: каждое слово запроса должно найтись в
 * документе началом слова (как `слово*` у SearchProvider ядра), оценка —
 * сколько раз слова встретились. Это приближение полнотекстового поиска MySQL,
 * а не он сам: абсолютные цифры «note» на портале будут другими, сравнение
 * «note → rag» на одном корпусе — честное. Индекс фрагментов строится
 * настоящим IndexBuilder по тем же документам.
 *
 * Без ядра — tests/retrievaleval_test.php.
 */

namespace RetrievalEval;

use Shef\ToolsAiKb\Import\ArticleParser;
use Shef\ToolsAiKb\Note\NoteGateway;
use Shef\ToolsAiKb\Public\Service\KbSearchService;
use Shef\ToolsAiKb\Search\Bm25Index;
use Shef\ToolsAiKb\Search\ChunkStore;
use Shef\ToolsAiKb\Search\IndexBuilder;

/** «База знаний» в памяти: раздел 1, документы — статьи корпуса. */
final class MemoryNote implements NoteGateway
{
	/** @var array<int, array{slug: string, title: string, markdown: string, words: list<string>}> */
	public array $docs = [];

	/** @param array<string, string> $files slug => содержимое .md */
	public function __construct(array $files)
	{
		$id = 1;
		foreach($files as $slug => $content)
		{
			$article = ArticleParser::parse($slug.'.md', $content);
			preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower(str_replace('ё', 'е', $article->title.' '.$article->markdown)), $m);
			$this->docs[$id++] = ['slug' => $slug, 'title' => $article->title, 'markdown' => $article->markdown, 'words' => $m[0]];
		}
	}

	public function getCollectionState(int $collectionId): string
	{
		return $collectionId === 1 ? self::COLLECTION_OK : self::COLLECTION_MISSING;
	}

	public function createCollection(string $name, int $ownerId): int
	{
		throw new \LogicException('только чтение');
	}

	public function createDocument(int $collectionId, ?int $parentId, string $title, string $markdown, int $userId): int
	{
		throw new \LogicException('только чтение');
	}

	public function overwriteDocument(int $documentId, string $title, string $markdown, int $userId): void
	{
		throw new \LogicException('только чтение');
	}

	public function deleteDocument(int $documentId, int $userId): array
	{
		throw new \LogicException('только чтение');
	}

	public function getCollectionTree(int $collectionId): array
	{
		return $collectionId === 1 ? array_fill_keys(array_keys($this->docs), null) : [];
	}

	public function getDocument(int $documentId): ?array
	{
		$doc = $this->docs[$documentId] ?? null;

		return $doc === null ? null : ['archived' => false, 'format' => 'md', 'title' => $doc['title'], 'markdown' => $doc['markdown']];
	}

	public function search(array $terms, int $limit): array
	{
		$hits = [];
		foreach($this->docs as $id => $doc)
		{
			$score = 0;
			foreach($terms as $term)
			{
				$term = str_replace('ё', 'е', mb_strtolower($term));
				$found = count(array_filter($doc['words'], static fn(string $w): bool => str_starts_with($w, $term)));
				if($found === 0)
				{
					continue 2;
				}
				$score += $found;
			}
			$hits[] = ['id' => $id, 'collectionId' => 1, 'title' => $doc['title'], 'snippet' => '', 'score' => (float)$score];
		}
		usort($hits, static fn(array $a, array $b): int => [$b['score'], $a['id']] <=> [$a['score'], $b['id']]);

		return array_slice($hits, 0, $limit);
	}

	/** slug документа. */
	public function slug(int $id): string
	{
		return $this->docs[$id]['slug'] ?? '';
	}
}

/** Фрагменты в памяти. */
final class MemoryChunks implements ChunkStore
{
	private array $rows = [];

	public function replace(array $rows): void
	{
		$this->rows = $rows;
	}

	public function all(): array
	{
		return $this->rows;
	}
}

/** Метрики выдачи. */
final class Metrics
{
	/**
	 * Место первой нужной статьи (1…), нет в выдаче — null.
	 *
	 * @param list<string> $found
	 * @param list<string> $expected
	 */
	public static function firstRank(array $found, array $expected): ?int
	{
		foreach(array_values($found) as $i => $slug)
		{
			if(in_array($slug, $expected, true))
			{
				return $i + 1;
			}
		}

		return null;
	}

	/** Нужная статья в первых $k. */
	public static function hit(?int $rank, int $k): bool
	{
		return $rank !== null && $rank <= $k;
	}

	/** Обратный ранг: 1/место, нет — 0. */
	public static function reciprocal(?int $rank): float
	{
		return $rank === null ? 0.0 : 1 / $rank;
	}

	/**
	 * Доля нужных статей в первых $k.
	 *
	 * @param list<string> $found
	 * @param list<string> $expected
	 */
	public static function recall(array $found, array $expected, int $k): float
	{
		return $expected === [] ? 0.0 : count(array_intersect(array_slice($found, 0, $k), $expected)) / count(array_unique($expected));
	}
}

/** Прогон набора. */
final class Runner
{
	public const MODES = ['note', 'rag'];

	/**
	 * Статьи корпуса: *.md каталога, slug — имя файла без .md.
	 *
	 * @return array<string, string>
	 */
	public static function corpus(string $dir): array
	{
		$files = [];
		foreach(glob(rtrim($dir, '/').'/*.md') ?: [] as $file)
		{
			$files[basename($file, '.md')] = (string)file_get_contents($file);
		}
		ksort($files);

		return $files;
	}

	/**
	 * Кейсы: [{"id", "question", "expect": ["slug", …]}]; негодные — в ошибки.
	 *
	 * @return array{cases: list<array{id: string, question: string, expect: list<string>}>, errors: list<string>}
	 */
	public static function cases(mixed $data, array $slugs): array
	{
		$cases = [];
		$errors = [];
		foreach(is_array($data) ? $data : [] as $i => $case)
		{
			$id = is_array($case) && is_string($case['id'] ?? null) ? $case['id'] : '#'.$i;
			$question = is_array($case) && is_string($case['question'] ?? null) ? trim($case['question']) : '';
			$expect = is_array($case) && is_array($case['expect'] ?? null) ? array_values(array_filter($case['expect'], 'is_string')) : [];
			if($question === '' || $expect === [])
			{
				$errors[] = $id.': нужны question и expect';
				continue;
			}
			$unknown = array_diff($expect, $slugs);
			if($unknown !== [])
			{
				$errors[] = $id.': статей нет в корпусе: '.implode(', ', $unknown);
				continue;
			}
			$cases[] = ['id' => $id, 'question' => $question, 'expect' => $expect];
		}

		return ['cases' => $cases, 'errors' => $errors];
	}

	/**
	 * @param list<array{id: string, question: string, expect: list<string>}> $cases
	 * @return array{k: int, modes: array<string, array{hit: float, mrr: float, recall: float}>, cases: list<array<string, mixed>>}
	 */
	public static function run(array $files, array $cases, int $k): array
	{
		$note = new MemoryNote($files);
		$chunks = new MemoryChunks();
		(new IndexBuilder($note, $chunks))->rebuild(1);
		$index = Bm25Index::build($chunks->all());

		$sum = array_fill_keys(self::MODES, ['hit' => 0.0, 'mrr' => 0.0, 'recall' => 0.0]);
		$rows = [];
		foreach($cases as $case)
		{
			$row = ['id' => $case['id'], 'question' => $case['question'], 'expect' => $case['expect']];
			foreach(self::MODES as $mode)
			{
				$found = array_map(
					static fn(array $hit): string => $note->slug((int)$hit['id']),
					KbSearchService::findWith($note, $case['question'], $k, 1, null, $mode === 'rag' ? $index : null)
				);
				$rank = Metrics::firstRank($found, $case['expect']);
				$sum[$mode]['hit'] += Metrics::hit($rank, $k) ? 1 : 0;
				$sum[$mode]['mrr'] += Metrics::reciprocal($rank);
				$sum[$mode]['recall'] += Metrics::recall($found, $case['expect'], $k);
				$row[$mode] = ['rank' => $rank, 'found' => $found];
			}
			$rows[] = $row;
		}
		$n = max(1, count($cases));
		$modes = [];
		foreach($sum as $mode => $s)
		{
			$modes[$mode] = ['hit' => round($s['hit'] / $n, 3), 'mrr' => round($s['mrr'] / $n, 3), 'recall' => round($s['recall'] / $n, 3)];
		}

		return ['k' => $k, 'modes' => $modes, 'cases' => $rows];
	}

	/** Отчёт markdown. */
	public static function report(array $result, int $docs): string
	{
		$out = ['# Замер поиска статей', '', 'Статей в корпусе: '.$docs.', кейсов: '.count($result['cases']).', k = '.$result['k'].'.', '',
			'| путь | hit@'.$result['k'].' | MRR | recall@'.$result['k'].' |', '|---|---|---|---|'];
		foreach($result['modes'] as $mode => $m)
		{
			$out[] = '| '.$mode.' | '.$m['hit'].' | '.$m['mrr'].' | '.$m['recall'].' |';
		}
		$out[] = '';
		$out[] = '| кейс | note | rag |';
		$out[] = '|---|---|---|';
		foreach($result['cases'] as $row)
		{
			$cell = static fn(?int $rank): string => $rank === null ? '—' : (string)$rank;
			$out[] = '| '.$row['id'].' | '.$cell($row['note']['rank']).' | '.$cell($row['rag']['rank']).' |';
		}

		return implode("\n", $out)."\n";
	}
}
