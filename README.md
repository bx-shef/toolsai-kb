# shef.toolsaikb — [SH] ИИ база знаний

Модуль коробочного Битрикс24. Кладёт статьи модулей линейки shef.* в штатную
«Базу знаний» портала (модуль ядра `note`) и даёт другим модулям поиск по ней.

**Зачем.** Справка модулей (например, shef.toolsai) должна жить там, где её
читают сотрудники, — в «Базе знаний», с правами, поиском и правкой. Модули
отдают свои статьи событием, этот модуль раскладывает их в раздел
«ИИ: База знаний» и обновляет. Если статью поправили в «Базе знаний» руками,
следующее обновление её не затирает. Чат ИИ-помощника (shef.toolsaiassistant)
находит здесь статьи для ответа.

## Что умеет

- Создаёт раздел «ИИ: База знаний»: читают все сотрудники, правят модераторы.
- Импортирует пакеты статей (`*.md`) от модулей — событие
  `shef.toolsaikb:OnKbSourcesCollect`. Повторный импорт без изменений ничего не
  пишет; изменённая статья перезаписывается; правленная руками — нет.
- Обновляет статьи агентом раз в сутки и кнопкой «Обновить статьи» в
  настройках модуля.
- Ищет статьи с учётом прав пользователя: `KbSearchService::find()` и ajax
  `shef:toolsaikb.search.find`. По умолчанию — только в своём разделе.

## Требования

- PHP 8.2+, Битрикс24 «коробка» в UTF-8;
- модуль ядра `note` («База знаний») 26.250.0+;
- [shef.options](https://github.com/bx-shef/options) 3.0+, [shef.problems](https://github.com/bx-shef/problems) 2.0+.

## Установка

Composer (`bxshef/toolsai-kb`, ставится в `bitrix/modules/shef.toolsaikb`) или
архив `./build.sh` -> `shef.toolsaikb.zip`, распаковать в `bitrix/modules/`.
Дальше — «Настройки → Модули», установить. Установка сразу делает первый импорт.

## Для разработчика модуля

```php
// Отдать свои статьи: обработчик события (registerEventHandler в установщике)
public static function handle(\Bitrix\Main\Event $event): \Bitrix\Main\EventResult
{
	return new \Bitrix\Main\EventResult(\Bitrix\Main\EventResult::SUCCESS, ['sources' => [
		['code' => 'shef.demo:guide', 'title' => 'Demo: Справка', 'dir' => __DIR__.'/../kb'],
	]], 'shef.demo');
}

// Найти статьи (права текущего пользователя)
if(\Bitrix\Main\Loader::includeModule('shef.toolsaikb'))
{
	$articles = \Shef\ToolsAiKb\Public\Service\KbSearchService::find('как настроить квоту', 5);
	// [['id' => 12, 'title' => '…', 'url' => '/note/document/12/', 'snippet' => '…'], …]
}
```

Подробно — [docs/guide/](https://github.com/bx-shef/toolsai-kb/blob/main/docs/guide/README.md).

## Лицензия

MIT, см. [LICENSE](LICENSE).
