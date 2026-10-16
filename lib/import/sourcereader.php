<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Import;

/**
 * Статьи пакета с диска: все `*.md` каталога и подкаталогов.
 *
 * Каталоги-ссылки не обходятся.
 *
 * Порядок — по пути (как в каталоге: `10-…` раньше `20-…`), в нём статьи и
 * встанут в `note`. Скрытые файлы и каталоги (`.…`) пропускаются; файл,
 * который ссылкой уводит за пределы каталога пакета, не читается; файл больше
 * ограничения `note` — в ошибки.
 *
 * Файловая система, но без ядра — tests/sourcereader_test.php на tests/fixtures.
 */
final class SourceReader
{
	/** Длина колонки PATH таблицы соответствий (Model\MappingTable). */
	public const PATH_MAX = 255;

	/**
	 * @return array{articles: list<Article>, errors: list<string>}
	 */
	public static function read(string $dir): array
	{
		$root = realpath($dir);
		if($root === false || !is_dir($root))
		{
			return ['articles' => [], 'errors' => ['каталог не найден: '.$dir]];
		}

		$files = [];
		static::collect($root, '', $files);
		sort($files, SORT_STRING);

		$articles = [];
		$errors = [];
		foreach($files as $rel)
		{
			if(mb_strlen($rel) > self::PATH_MAX)
			{
				$errors[] = $rel.': путь длиннее '.self::PATH_MAX.' символов — пропущен';
				continue;
			}
			$real = realpath($root.'/'.$rel);
			if($real === false || !str_starts_with($real, $root.'/') || !is_file($real))
			{
				$errors[] = $rel.': ведёт за пределы каталога пакета — пропущен';
				continue;
			}
			$content = file_get_contents($real);
			if($content === false)
			{
				$errors[] = $rel.': не читается';
				continue;
			}
			$article = ArticleParser::parse($rel, $content);
			if(strlen($article->markdown) > ArticleParser::MAX_MARKDOWN_BYTES)
			{
				$errors[] = $rel.': больше '.ArticleParser::MAX_MARKDOWN_BYTES.' байт — пропущен';
				continue;
			}
			$articles[] = $article;
		}

		return ['articles' => $articles, 'errors' => $errors];
	}

	/**
	 * @param list<string> $files
	 */
	private static function collect(string $root, string $rel, array &$files): void
	{
		$names = scandir($rel === '' ? $root : $root.'/'.$rel) ?: [];
		foreach($names as $name)
		{
			if($name === '' || $name[0] === '.')
			{
				continue;
			}
			$path = $rel === '' ? $name : $rel.'/'.$name;
			if(is_dir($root.'/'.$path))
			{
				// Каталог-ссылка: обход по ней мог бы зациклиться
				if(is_link($root.'/'.$path))
				{
					continue;
				}
				static::collect($root, $path, $files);
			}
			elseif(preg_match('/\.md$/i', $name))
			{
				$files[] = $path;
			}
		}
	}
}
