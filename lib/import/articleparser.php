<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Import;

/**
 * Файл `*.md` -> статья: заголовок, текст, хэш.
 *
 * Заголовок — первая строка вида `# Заголовок`, если файл с неё начинается
 * (пустые строки до неё не в счёт); из текста она убирается — `note` показывает
 * заголовок документа отдельно. Нет такой строки — заголовок из имени файла:
 * без `.md` и порядкового номера в начале (`10-quota` -> `quota`), `-` и `_`
 * -> пробел, первая буква заглавная.
 *
 * Перевод строк приводится к LF, BOM снимается, пробелы по краям текста
 * убираются — тот же файл даёт тот же хэш на любой системе.
 *
 * Без ядра — tests/articleparser_test.php.
 */
final class ArticleParser
{
	/** Ограничение `note` на один документ (DocumentLimits::MAX_MARKDOWN_BYTES). */
	public const MAX_MARKDOWN_BYTES = 1_048_576;
	public const TITLE_MAX = 255;

	/**
	 * Разобрать файл статьи.
	 *
	 * @param string $path путь файла от каталога пакета — для заголовка по имени
	 * @param string $content содержимое файла как есть
	 */
	public static function parse(string $path, string $content): Article
	{
		if(str_starts_with($content, "\xEF\xBB\xBF"))
		{
			$content = substr($content, 3);
		}
		$content = str_replace(["\r\n", "\r"], "\n", $content);
		$title = '';
		if(preg_match('/\A\s*#[ \t]+([^\n]+)\n?/u', $content, $m))
		{
			$title = trim(rtrim(trim($m[1]), '#'));
			$content = substr($content, strlen($m[0]));
		}
		if($title === '')
		{
			$title = static::titleFromPath($path);
		}
		$title = mb_substr($title, 0, self::TITLE_MAX);
		$markdown = trim($content);

		return new Article($path, $title, $markdown, static::hash($title, $markdown));
	}

	/**
	 * Хэш содержимого документа: заголовок и текст. По нему импорт понимает и
	 * «статья в пакете изменилась», и «документ поправили руками в note».
	 */
	public static function hash(string $title, string $markdown): string
	{
		return hash('sha256', $title."\n".$markdown);
	}

	/**
	 * Заголовок из имени файла: без `.md` и номера в начале, `-`/`_` -> пробел,
	 * первая буква заглавная. Ничего не осталось — имя файла как есть.
	 */
	public static function titleFromPath(string $path): string
	{
		$name = (string)preg_replace('/\.md$/iu', '', basename($path));
		$name = (string)preg_replace('/^\d+[-_. ]+/u', '', $name);
		$name = trim((string)preg_replace('/[-_]+/u', ' ', $name));
		if($name === '')
		{
			return basename($path);
		}

		return mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1);
	}
}
