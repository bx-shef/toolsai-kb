# Событие `shef.toolsaikb:OnKbSourcesCollect`

Модуль отправляет его при каждом импорте. Класс —
`Shef\ToolsAiKb\Public\Event\OnKbSourcesCollectEvent`, параметров нет.

## Ответ обработчика

```php
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;

final class OnKbSourcesCollectEventHandler
{
	public static function handle(Event $event): EventResult
	{
		return new EventResult(EventResult::SUCCESS, ['sources' => [
			[
				'code' => 'shef.toolsai:guide',          // <moduleId>:<имя>
				'title' => 'ИИ: Справка по модулю',      // название пакета в «Базе знаний»
				'dir' => '/абсолютный/путь/к/каталогу',  // *.md, подкаталоги тоже
			],
		]], 'shef.toolsai');                             // moduleId обязателен
	}
}
```

Типизируйте аргумент `Bitrix\Main\Event`, а не классом события: тогда ваш
модуль не зависит от shef.toolsaikb жёстко.

## Правила (`Import\SourceValidator`)

- ответ `SUCCESS` и в нём указан модуль (третий аргумент `EventResult`);
- `code` — `<moduleId>:<имя>`, строчные латинские, цифры, `.`, `_`, `-`;
  до 100 символов; **начинается с модуля, который ответил**;
- `title` — непустой, до 255 символов;
- `dir` — абсолютный путь к существующему каталогу;
- код повторился — берётся первый пакет.

Нарушение — пакет пропускается, причина — в итоге импорта и журнале.
Исключение в обработчике не роняет импорт целиком, но без пакетов этот прогон
ничего не сделает: исключение прерывает отправку события.

## Подписка

Постоянная — в установщике вашего модуля:

```php
\Bitrix\Main\EventManager::getInstance()->registerEventHandler(
	'shef.toolsaikb', 'OnKbSourcesCollect', 'shef.demo',
	\Shef\Demo\Internals\Integration\Shef\ToolsAiKb\EventHandler\OnKbSourcesCollectEventHandler::class, 'handle'
);
```

и парный `unRegisterEventHandler()` при удалении. Модуль shef.toolsaikb не
установлен — событие просто никто не шлёт.

Код пакета — это ключ в таблице соответствий: сменить его — значит получить
новый пакет рядом со старым.
