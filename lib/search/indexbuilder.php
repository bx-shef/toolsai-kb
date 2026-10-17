<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Search;

use Shef\ToolsAiKb\Note\NoteGateway;

/**
 * Пересборка индекса фрагментов по разделу «Базы знаний».
 *
 * Берутся все документы раздела (дерево `note`: без архива и корзины), а не
 * только импортированные: правленная руками статья индексируется с текстом,
 * который лежит в `note` сейчас, документ, добавленный человеком, — тоже.
 * Документ, который не прочитался, пропускается — в ошибках. Текст —
 * markdown документа; у `yjs` с несохранёнными правками он может отставать
 * до следующего сохранения — ядро другого текста не даёт.
 *
 * Права здесь не нужны: индекс только ранжирует, видимость документа
 * сотруднику проверяет поиск `note` (KbSearchService).
 *
 * Без ядра — tests/indexbuilder_test.php.
 */
final class IndexBuilder
{
	public function __construct(
		private readonly NoteGateway $note,
		private readonly ChunkStore $store,
	)
	{
	}

	/**
	 * @return array{documents: int, chunks: int, errors: list<string>}
	 */
	public function rebuild(int $collectionId): array
	{
		$errors = [];
		$rows = [];
		$documents = 0;
		foreach(array_keys($this->note->getCollectionTree($collectionId)) as $documentId)
		{
			try
			{
				$document = $this->note->getDocument((int)$documentId);
			}
			catch(\Throwable $throwable)
			{
				$errors[] = 'индекс: документ '.$documentId.' не прочитан: '.$throwable->getMessage();
				continue;
			}
			if($document === null || $document['archived'])
			{
				continue;
			}
			$chunks = Chunker::split($document['markdown']);
			if($chunks === [])
			{
				continue;
			}
			$documents++;
			foreach($chunks as $chunk)
			{
				$rows[] = ['documentId' => (int)$documentId, 'title' => $document['title'], 'heading' => $chunk['heading'], 'text' => $chunk['text']];
			}
		}
		$this->store->replace($rows);

		return ['documents' => $documents, 'chunks' => count($rows), 'errors' => $errors];
	}
}
