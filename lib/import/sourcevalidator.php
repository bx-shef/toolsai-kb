<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Import;

/**
 * Проверка ответов на событие `shef.toolsaikb:OnKbSourcesCollect`.
 *
 * Контракт ответа: `EventResult(SUCCESS, ['sources' => [['code', 'title', 'dir'], ...]], <moduleId>)`.
 * Пакет принимается, если:
 *
 *  - ответ SUCCESS и в нём указан модуль;
 *  - `code` вида `<moduleId>:<имя>` (строчные латинские буквы, цифры, `.`, `_`, `-`;
 *    до 100 символов) и начинается с модуля, который его отдал;
 *  - `title` непустой, до 255 символов;
 *  - `dir` — абсолютный путь к существующему каталогу.
 *
 * Код повторился — берётся первый пакет. Всё отклонённое попадает в ошибки:
 * их пишет в журнал импорт. Проверка каталога подставляется снаружи — так
 * класс тестируется без файловой системы.
 *
 * Без ядра — tests/sourcevalidator_test.php.
 */
final class SourceValidator
{
	public const CODE_PATTERN = '/^[a-z0-9][a-z0-9._-]*:[a-z0-9][a-z0-9._-]*$/';
	public const CODE_MAX = 100;
	public const TITLE_MAX = 255;

	/** @var callable(string): bool */
	private $isDir;

	/**
	 * @param null|callable(string): bool $isDir проверка каталога, по умолчанию is_dir()
	 */
	public function __construct(?callable $isDir = null)
	{
		$this->isDir = $isDir ?? static fn(string $dir): bool => is_dir($dir);
	}

	/**
	 * @param list<array{moduleId: string, success: bool, parameters: mixed}> $responses ответы обработчиков
	 * @return array{sources: list<Source>, errors: list<string>}
	 */
	public function validate(array $responses): array
	{
		$sources = [];
		$errors = [];
		foreach($responses as $response)
		{
			$moduleId = trim((string)($response['moduleId'] ?? ''));
			$label = $moduleId !== '' ? $moduleId : '(модуль не указан)';
			if(!($response['success'] ?? false))
			{
				$errors[] = $label.': ответ не SUCCESS — пропущен';
				continue;
			}
			if($moduleId === '')
			{
				$errors[] = 'ответ без модуля в EventResult — пропущен';
				continue;
			}
			$parameters = $response['parameters'] ?? null;
			$list = is_array($parameters) ? ($parameters['sources'] ?? null) : null;
			if(!is_array($list) || !array_is_list($list))
			{
				$errors[] = $label.': нет списка sources — пропущен';
				continue;
			}
			foreach($list as $i => $item)
			{
				$error = $this->check($moduleId, $item);
				if($error !== null)
				{
					$errors[] = $label.', пакет '.$i.': '.$error;
					continue;
				}
				$code = (string)$item['code'];
				if(isset($sources[$code]))
				{
					$errors[] = $label.': пакет '.$code.' уже отдал модуль '.$sources[$code]->moduleId.' — пропущен';
					continue;
				}
				$sources[$code] = new Source($code, trim((string)$item['title']), rtrim((string)$item['dir'], '/'), $moduleId);
			}
		}

		return ['sources' => array_values($sources), 'errors' => $errors];
	}

	private function check(string $moduleId, mixed $item): ?string
	{
		if(!is_array($item))
		{
			return 'не массив';
		}
		$code = $item['code'] ?? null;
		if(!is_string($code) || strlen($code) > self::CODE_MAX || !preg_match(self::CODE_PATTERN, $code))
		{
			return 'неверный code';
		}
		if(!str_starts_with($code, $moduleId.':'))
		{
			return 'code '.$code.' не начинается с «'.$moduleId.':»';
		}
		$title = $item['title'] ?? null;
		if(!is_string($title) || trim($title) === '' || mb_strlen(trim($title)) > self::TITLE_MAX)
		{
			return 'пустой или слишком длинный title';
		}
		$dir = $item['dir'] ?? null;
		if(!is_string($dir) || !str_starts_with($dir, '/') || str_contains($dir, "\0") || !($this->isDir)($dir))
		{
			return 'dir не абсолютный путь к каталогу';
		}

		return null;
	}
}
