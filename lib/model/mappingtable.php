<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Model;

use Bitrix\Main\Application;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields;

/**
 * Соответствия «статья пакета -> документ `note`».
 *
 * SOURCE_CODE — код пакета (`shef.toolsai:guide`), PATH — путь статьи от
 * каталога пакета (пустой — документ-раздел пакета), NOTE_DOCUMENT_ID —
 * документ в `note`, HASH — sha256 заголовка и текста, которые импорт
 * записал последним (Import\ArticleParser::hash()). Пара SOURCE_CODE + PATH уникальна.
 */
class MappingTable extends DataManager
{
	/** Имя таблицы в базе. */
	public static function getTableName(): string
	{
		return 'shef_toolsaikb_mapping';
	}

	/** Поля таблицы. */
	public static function getMap(): array
	{
		return [
			(new Fields\IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
			(new Fields\StringField('SOURCE_CODE'))->configureSize(100)->configureRequired(),
			(new Fields\StringField('PATH'))->configureSize(255)->configureDefaultValue(''),
			(new Fields\StringField('HASH'))->configureSize(64)->configureRequired(),
			(new Fields\IntegerField('NOTE_DOCUMENT_ID'))->configureRequired(),
			(new Fields\DatetimeField('UPDATED_AT'))->configureRequired(),
		];
	}

	/** Создать таблицу, если её нет. Идемпотентно. */
	public static function init(): void
	{
		$connection = Application::getConnection();
		if($connection->isTableExists(static::getTableName()))
		{
			return;
		}
		$connection->queryExecute(sprintf(
			"CREATE TABLE %s (\n\tID INT(11) NOT NULL AUTO_INCREMENT,\n\tSOURCE_CODE VARCHAR(100) NOT NULL,\n\tPATH VARCHAR(255) NOT NULL DEFAULT '',\n\tHASH CHAR(64) NOT NULL,\n\tNOTE_DOCUMENT_ID INT(11) NOT NULL,\n\tUPDATED_AT DATETIME NOT NULL,\n\tPRIMARY KEY (ID),\n\tUNIQUE KEY UX_SHEF_TOOLSAIKB_MAPPING (SOURCE_CODE, PATH),\n\tKEY IX_SHEF_TOOLSAIKB_MAPPING_DOC (NOTE_DOCUMENT_ID)\n)",
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
