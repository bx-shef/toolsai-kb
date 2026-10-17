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
				'viewUrl' => '/shef/toolsai/kb/guide/{path}', // необязательно, с 1.2.0
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
- `viewUrl` (необязательно, с 1.2.0) — шаблон адреса статьи в родном шаблоне
  модуля (`Import\ViewUrl`). Только путь своего портала: начинается с одного
  `/` (не `//`), без `\`, пробелов и управляющих символов, до 255 символов —
  схемы (`https:`, `javascript:`) и домена в нём быть не может. Подстановки,
  нужна хотя бы одна:
  - `{path}` — путь файла в пакете без `.md`: `setup/quota.md` -> `setup/quota`;
  - `{slug}` — имя файла без `.md`: `quota`.

  Части пути кодируются `rawurlencode()`, `/` между ними остаются. Поле есть,
  но не годится — пакет отклоняется целиком;
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

## Куда ведёт ссылка статьи

Импорт считает адрес каждой статьи по `viewUrl` и пишет его в таблицу
соответствий (колонка `VIEW_URL`). Поиск (`KbSearchService::find`) отдаёт его
в `url`; адрес документа `note` — всегда в `noteUrl`. Статья, которую поправили
руками в «Базе знаний», ведёт в `note` — актуальный текст там (с ближайшего
импорта: агент раз в сутки или кнопка «Обновить статьи»). Статья пропала из
пакета, а документ оставлен (правлен руками, в архиве) — тоже в `note`. Пакет без
`viewUrl` и страница пакета ведут в `note`. Убрали или сменили `viewUrl` —
адреса меняются при следующем импорте, документы `note` не перезаписываются.

Код пакета — это ключ в таблице соответствий: сменить его — значит получить
новый пакет рядом со старым.

## Подписки модуля

Сам модуль подписан на одно событие (1.1.2):

| событие | ответ shef.toolsaikb |
|---|---|
| `shef.toolsai:OnViewSettingsMenuBuild` (shef.toolsai 1.53.0) | пункт «ИИ база знаний» в меню шестерёнки вида «ИИ» — ссылка на страницу настроек модуля, только при праве на модуль от R |

Обработчик — `Internals\Integration\ToolsAi\EventHandler\OnViewSettingsMenuBuildEventHandler`,
принимает `Main\Event`: нет shef.toolsai 1.53 — событие никто не шлёт. Подписку
ставят установщик и `Main\Maintenance` (обновление копированием), снимает
удаление — см. [установку](05-install.md).
