<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Note;

use Bitrix\Main\Command\Exception\CommandException;
use Bitrix\Main\Loader;
use Bitrix\Main\Provider\Params\Pager;
use Bitrix\Note\Internal\Exceptions\DocumentArchivedException;
use Bitrix\Note\Internal\Exceptions\DocumentInRecycleBinException;
use Bitrix\Note\Internal\Exceptions\DocumentNotFoundException;
use Bitrix\Note\Public\Command\CreateCollectionCommand;
use Bitrix\Note\Public\Command\CreateDocumentCommand;
use Bitrix\Note\Public\Command\DeleteDocumentCommand;
use Bitrix\Note\Public\Command\OverwriteDocumentContentCommand;
use Bitrix\Note\Public\Provider\CollectionProvider;
use Bitrix\Note\Public\Provider\DocumentProvider;
use Bitrix\Note\Public\Provider\Param\Search\SearchQuery;
use Bitrix\Note\Public\Provider\SearchProvider;

/**
 * «База знаний» через публичный API модуля `note` (сверено с note 26.250).
 *
 *  - Раздел — CreateCollectionCommand с политикой `view` для всех (`*`) и
 *    `moderate` для автора: читают все сотрудники, правят модераторы.
 *    Без политики ядро ставит `*` = none (CollectionAccessService::createDefaultAccess)
 *    — раздел увидел бы только автор.
 *  - Документ — CreateDocumentCommand с форматом `md`: так пишет документ с
 *    текстом REST ядра (Rest\V3\Controller\Document::addAction) и импорт
 *    ядра; только для `md` ядро сразу кладёт текст в поисковый индекс
 *    (DocumentService::create). Формат по умолчанию `yjs` — пустой
 *    совместный документ, текст в нём редактор не покажет.
 *  - Перезапись — OverwriteDocumentContentCommand (без $overwrite: документ
 *    в `yjs` с несохранёнными правками ядро не перезапишет; до него импорт и
 *    не доходит — Planner считает такой документ правленым руками).
 *  - Удаление — DeleteDocumentCommand: в корзину вместе с дочерними
 *    (MoveToRecycleBinService::moveSubtree), документ снимается с поиска;
 *    нет документа или он уже в корзине — ядро отвечает data['success'] = false.
 *  - Поиск — SearchProvider::search() с правами текущего пользователя.
 *
 * Команды прав не проверяют — их проверяют контроллеры ядра. Импорт пишет
 * от имени служебного пользователя (автор раздела) и только в свой раздел.
 */
final class BitrixNoteGateway implements NoteGateway
{
	/**
	 * Подключить модуль `note`.
	 *
	 * @throws \RuntimeException модуль `note` не установлен
	 */
	public function __construct()
	{
		if(!Loader::includeModule('note'))
		{
			throw new \RuntimeException('модуль «База знаний» (note) не установлен');
		}
	}

	/** {@inheritDoc} */
	public function getCollectionState(int $collectionId): string
	{
		if($collectionId <= 0)
		{
			return self::COLLECTION_MISSING;
		}
		$collection = (new CollectionProvider())->getById($collectionId);
		if($collection === null)
		{
			return self::COLLECTION_MISSING;
		}

		return $collection->getIsArchived() ? self::COLLECTION_ARCHIVED : self::COLLECTION_OK;
	}

	/** {@inheritDoc} */
	public function createCollection(string $name, int $ownerId): int
	{
		$result = (new CreateCollectionCommand(
			name: $name,
			createdBy: $ownerId,
			policyLevel: 'view',
			permissions: [['subjectCode' => 'U'.$ownerId, 'level' => 'moderate']],
		))->run();
		$collection = $result->getData()['collection'] ?? null;
		if(!$result->isSuccess() || $collection === null)
		{
			throw new \RuntimeException(implode('; ', $result->getErrorMessages()) ?: 'раздел не создан');
		}

		return (int)$collection->getId();
	}

	/** {@inheritDoc} */
	public function createDocument(int $collectionId, ?int $parentId, string $title, string $markdown, int $userId): int
	{
		$result = (new CreateDocumentCommand(
			collectionId: $collectionId,
			parentId: $parentId,
			title: $title,
			markdown: $markdown,
			userId: $userId,
			contentFormat: 'md',
		))->run();
		$document = $result->getData()['document'] ?? null;
		if(!$result->isSuccess() || $document === null)
		{
			throw new \RuntimeException(implode('; ', $result->getErrorMessages()) ?: 'документ не создан');
		}

		return (int)$document->getId();
	}

	/** {@inheritDoc} */
	public function overwriteDocument(int $documentId, string $title, string $markdown, int $userId): void
	{
		try
		{
			$result = (new OverwriteDocumentContentCommand(
				documentId: $documentId,
				markdown: $markdown,
				userId: $userId,
				title: $title,
			))->run();
		}
		catch(CommandException $exception)
		{
			$previous = $exception->getPrevious();
			if(
				$previous instanceof DocumentInRecycleBinException
				|| $previous instanceof DocumentArchivedException
				|| $previous instanceof DocumentNotFoundException
			)
			{
				throw new DocumentRemovedException($previous->getMessage(), 0, $exception);
			}

			throw $exception;
		}
		if(!$result->isSuccess())
		{
			throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
		}
	}

	/** {@inheritDoc} */
	public function deleteDocument(int $documentId, int $userId): array
	{
		$result = (new DeleteDocumentCommand(id: $documentId, userId: $userId))->run();
		if(!$result->isSuccess())
		{
			throw new \RuntimeException(implode('; ', $result->getErrorMessages()) ?: 'документ не удалён');
		}

		$data = $result->getData();
		if(empty($data['success']))
		{
			return [];
		}

		return array_values(array_map('intval', (array)($data['trashedIds'] ?? [$documentId])));
	}

	/** {@inheritDoc} */
	public function getDocument(int $documentId): ?array
	{
		$document = (new DocumentProvider())->getById($documentId);
		if($document === null)
		{
			return null;
		}

		return [
			'archived' => (bool)$document->getIsArchived(),
			'format' => (string)$document->getContentFormat(),
			'title' => (string)$document->getTitle(),
			// Поле текста ORM может отдать не строкой — тогда считаем текст пустым
			'markdown' => is_string($markdown = $document->getMarkdown()) ? $markdown : '',
		];
	}

	/** {@inheritDoc} */
	public function search(array $terms, int $limit): array
	{
		if($terms === [] || $limit <= 0)
		{
			return [];
		}
		$found = (new SearchProvider())->search(new SearchQuery(implode(' ', $terms)), new Pager($limit, 0));
		$out = [];
		foreach($found as $item)
		{
			$out[] = [
				'id' => (int)$item->getDocumentId(),
				'collectionId' => $item->isSharedAccess() ? 0 : (int)$item->getCollectionId(),
				'title' => (string)$item->getTitle(),
				'snippet' => (string)$item->getSnippet(),
				'score' => (float)$item->getScore(),
			];
		}

		return $out;
	}
}
