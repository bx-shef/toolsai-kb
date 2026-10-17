<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Import;

/**
 * Адрес просмотра статьи в родном шаблоне модуля — поле `viewUrl` пакета
 * события OnKbSourcesCollect.
 *
 * Шаблон — путь своего портала с подстановками:
 *  - `{path}` — путь файла в пакете без `.md` (`setup/quota.md` -> `setup/quota`);
 *  - `{slug}` — имя файла без `.md` (`quota`).
 * Части пути подставляются через rawurlencode(), `/` между частями остаются.
 *
 * Шаблон принимается, только если это путь от корня своего портала:
 * начинается с одного `/` (не `//` — это адрес другого сайта), без `\`,
 * пробелов и управляющих символов, до TEMPLATE_MAX символов и хотя бы с одной
 * подстановкой. Схемы (`https:`, `javascript:`) и домена в таком адресе быть
 * не может — он начинается с `/`.
 *
 * Без ядра — tests/viewurl_test.php.
 */
final class ViewUrl
{
	public const TEMPLATE_MAX = 255;
	/** Длина колонки VIEW_URL таблицы соответствий (Model\MappingTable). */
	public const URL_MAX = 500;

	/** Шаблон годится: путь своего портала с `{path}` или `{slug}`. */
	public static function isValidTemplate(mixed $template): bool
	{
		if(!is_string($template) || $template === '' || strlen($template) > self::TEMPLATE_MAX)
		{
			return false;
		}
		if($template[0] !== '/' || str_starts_with($template, '//') || str_contains($template, '\\'))
		{
			return false;
		}
		if(preg_match('/[\x00-\x20\x7f]/', $template))
		{
			return false;
		}

		return str_contains($template, '{path}') || str_contains($template, '{slug}');
	}

	/**
	 * Адрес статьи по шаблону. Пустой шаблон, пустой путь (страница пакета)
	 * или слишком длинный итог — пустая строка: ссылка пойдёт на `note`.
	 *
	 * @param string $template шаблон viewUrl пакета ('' — нет)
	 * @param string $path путь статьи от каталога пакета (`setup/quota.md`)
	 */
	public static function build(string $template, string $path): string
	{
		if($template === '' || $path === '')
		{
			return '';
		}
		$bare = preg_replace('/\.md$/i', '', $path) ?? $path;
		$parts = array_map('rawurlencode', explode('/', $bare));
		$url = strtr($template, [
			'{path}' => implode('/', $parts),
			'{slug}' => (string)end($parts),
		]);

		return strlen($url) > self::URL_MAX ? '' : $url;
	}
}
