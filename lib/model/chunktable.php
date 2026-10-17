<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Model;

use Bitrix\Main\Application;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields;

/**
 * Фрагменты статей раздела для поиска (Search\Bm25Index, 1.3.0).
 *
 * Производные данные: пересобираются после каждого импорта
 * (Search\IndexBuilder) из документов `note`, поэтому удаляются вместе с
 * модулем. NOTE_DOCUMENT_ID — документ `note`, TITLE — его заголовок, HEADING —
 * цепочка заголовков раздела статьи, TEXT — текст фрагмента.
 */
class ChunkTable extends DataManager
{
	/** Имя таблицы в базе. */
	public static function getTableName(): string
	{
		return 'shef_toolsaikb_chunk';
	}

	/** Поля таблицы. */
	public static function getMap(): array
	{
		return [
			(new Fields\IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
			(new Fields\IntegerField('NOTE_DOCUMENT_ID'))->configureRequired(),
			(new Fields\StringField('TITLE'))->configureSize(255)->configureDefaultValue(''),
			(new Fields\StringField('HEADING'))->configureSize(500)->configureDefaultValue(''),
			(new Fields\TextField('TEXT'))->configureRequired(),
		];
	}

	/** Создать таблицу, если её нет. Идемпотентно — зовут установщик и импорт. */
	public static function init(): void
	{
		$connection = Application::getConnection();
		if($connection->isTableExists(static::getTableName()))
		{
			return;
		}
		$connection->queryExecute(sprintf(
			"CREATE TABLE %s (\n\tID INT(11) NOT NULL AUTO_INCREMENT,\n\tNOTE_DOCUMENT_ID INT(11) NOT NULL,\n\tTITLE VARCHAR(255) NOT NULL DEFAULT '',\n\tHEADING VARCHAR(500) NOT NULL DEFAULT '',\n\tTEXT MEDIUMTEXT NOT NULL,\n\tPRIMARY KEY (ID),\n\tKEY IX_SHEF_TOOLSAIKB_CHUNK_DOC (NOTE_DOCUMENT_ID)\n) ENGINE=InnoDB",
			static::getTableName()
		));
	}

	/** Удалить таблицу, если есть. */
	public static function drop(): void
	{
		$connection = Application::getConnection();
		if($connection->isTableExists(static::getTableName()))
		{
			$connection->queryExecute('DROP TABLE '.static::getTableName());
		}
	}
}
