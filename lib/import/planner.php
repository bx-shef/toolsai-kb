<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Import;

/**
 * Что делать со статьёй при импорте — решение без побочных эффектов.
 *
 * Главное правило: документ, который поправили руками в `note` после импорта,
 * не затирается. «Поправили» — значит, то, что сейчас лежит в `note`
 * (заголовок и текст), разошлось с тем, что импорт записал последним (хэш в
 * таблице соответствий), или документ перешёл из формата `md` в совместный
 * `yjs`/`json` — так `note` хранит документ, открытый на правку в редакторе.
 *
 * Без ядра — tests/planner_test.php.
 */
final class Planner
{
	/** Соответствия нет — создать документ. */
	public const CREATE = 'create';
	/** Документ по соответствию исчез из `note` — создать заново. */
	public const RECREATE = 'recreate';
	/** Статья в пакете изменилась, документ не трогали — перезаписать. */
	public const UPDATE = 'update';
	/** Статья не менялась — ничего. */
	public const UNCHANGED = 'unchanged';
	/** Документ поправили руками — не трогать. */
	public const MANUAL = 'manual';
	/** Документ в архиве — решение человека, не трогать. */
	public const ARCHIVED = 'archived';

	/** Статьи больше нет в пакете (или нет пакета), документ не трогали — убрать в корзину. */
	public const DELETE = 'delete';
	/** Статьи больше нет в пакете, документа в `note` уже нет — только убрать соответствие. */
	public const GONE = 'gone';

	/** Формат документа, который импорт пишет и умеет сравнивать (DocumentTable::CONTENT_FORMAT_MD). */
	public const FORMAT_MD = 'md';

	/**
	 * @param string $articleHash хэш статьи из пакета (ArticleParser::hash())
	 * @param string|null $importedHash хэш, записанный импортом последним; null — соответствия нет
	 * @param array{archived: bool, format: string, title: string, markdown: string}|null $document
	 *        документ в `note` сейчас; null — документа нет
	 */
	public static function decide(string $articleHash, ?string $importedHash, ?array $document): string
	{
		if($importedHash === null)
		{
			return self::CREATE;
		}
		if($document === null)
		{
			return self::RECREATE;
		}
		if($document['archived'])
		{
			return self::ARCHIVED;
		}
		if(
			$document['format'] !== self::FORMAT_MD
			|| !hash_equals($importedHash, ArticleParser::hash($document['title'], $document['markdown']))
		)
		{
			return self::MANUAL;
		}

		return hash_equals($importedHash, $articleHash) ? self::UNCHANGED : self::UPDATE;
	}

	/**
	 * Что делать с документом статьи, которой больше нет в пакете (или пакета
	 * больше нет в событии).
	 *
	 * Правленный руками (MANUAL) и архивный (ARCHIVED) документ не трогается —
	 * это решение человека; импорт только называет его в отчёте. Остальное —
	 * DELETE (в корзину `note`), а если документа уже нет — GONE.
	 *
	 * @param string $importedHash хэш, записанный импортом последним
	 * @param array{archived: bool, format: string, title: string, markdown: string}|null $document
	 *        документ в `note` сейчас; null — документа нет
	 */
	public static function decideObsolete(string $importedHash, ?array $document): string
	{
		if($document === null)
		{
			return self::GONE;
		}
		if($document['archived'])
		{
			return self::ARCHIVED;
		}
		if(
			$document['format'] !== self::FORMAT_MD
			|| !hash_equals($importedHash, ArticleParser::hash($document['title'], $document['markdown']))
		)
		{
			return self::MANUAL;
		}

		return self::DELETE;
	}
}
