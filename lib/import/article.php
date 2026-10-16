<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Import;

/**
 * Статья пакета, готовая к записи в `note`.
 */
final class Article
{
	/**
	 * @param string $path путь файла от каталога пакета (`setup/quota.md`); у
	 *                     страницы-раздела пакета — пустая строка
	 * @param string $title заголовок документа
	 * @param string $markdown текст документа без заголовка
	 * @param string $hash хэш содержимого (ArticleParser::hash())
	 */
	public function __construct(
		public readonly string $path,
		public readonly string $title,
		public readonly string $markdown,
		public readonly string $hash,
	)
	{
	}
}
