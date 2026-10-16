<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Note;

/**
 * Всё, что модулю нужно от «Базы знаний» (`note`). Настоящая реализация —
 * BitrixNoteGateway (публичный API `Bitrix\Note\Public\…`), в тестах —
 * подставная. Так импорт и поиск проверяются без портала.
 */
interface NoteGateway
{
	public const COLLECTION_OK = 'ok';
	public const COLLECTION_ARCHIVED = 'archived';
	public const COLLECTION_MISSING = 'missing';

	/**
	 * Состояние раздела (коллекции): COLLECTION_OK, COLLECTION_ARCHIVED или
	 * COLLECTION_MISSING (нет такого или ID пустой).
	 */
	public function getCollectionState(int $collectionId): string;

	/**
	 * Создать раздел: все сотрудники читают, $ownerId — модератор.
	 *
	 * @return int ID раздела
	 */
	public function createCollection(string $name, int $ownerId): int;

	/**
	 * Создать документ в формате `md`.
	 *
	 * @return int ID документа
	 */
	public function createDocument(int $collectionId, ?int $parentId, string $title, string $markdown, int $userId): int;

	/**
	 * Перезаписать заголовок и текст документа.
	 *
	 * @throws DocumentRemovedException документ в корзине, в архиве или удалён
	 */
	public function overwriteDocument(int $documentId, string $title, string $markdown, int $userId): void;

	/**
	 * Документ как он лежит сейчас, без проверки прав; null — документа нет.
	 *
	 * @return array{archived: bool, format: string, title: string, markdown: string}|null
	 */
	public function getDocument(int $documentId): ?array;

	/**
	 * Полнотекстовый поиск с правами ТЕКУЩЕГО пользователя: каждое слово
	 * запроса должно найтись в документе.
	 *
	 * @param list<string> $terms слова (QueryNormalizer::terms())
	 * @return list<array{id: int, title: string, snippet: string, score: float}>
	 */
	public function search(array $terms, int $limit): array;
}
