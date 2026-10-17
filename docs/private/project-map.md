# Карта проекта

> Последняя сверка: 2026-10-17

## Цель

Справка модулей линейки — в штатной «Базе знаний» Битрикс24, с поиском для
ИИ-помощника. Модуль — часть разделения shef.toolsai на четыре модуля
(toolsai, toolsai-announce, toolsai-kb, toolsai-assistant).

## Сделано (1.0.0)

- раздел, событие, идемпотентный импорт, агент, кнопка, поиск, ajax;
- тесты на чистую логику и шлюз к `note` на заглушках.

## Сделано (1.1.0)

- название «[SH] ИИ база знаний», порядок в списке модулей 1007;
- статьи, пропавшие из пакета, и пропавшие пакеты — в корзину `note`
  (решение владельца 2026-10-17), правленные руками не трогаются.

## Сделано (1.1.1–1.2.0)

- карточка «Импорт статей» на странице настроек (1.1.1);
- пункт «ИИ база знаний» в шестерёнке вида «ИИ» shef.toolsai — событием
  `OnViewSettingsMenuBuild` (1.1.2);
- `viewUrl` пакета: ссылка статьи в поиске ведёт в родной шаблон модуля
  (у shef.toolsai — его справка), `noteUrl` — всегда документ «Базы знаний» (1.2.0).

На портале stuttgart — 1.2.0 (выкладка 3.72).

## Связи

- shef.toolsai отдаёт пакет справки `shef.toolsai:guide` (обработчик
  регистрирует его установщик);
- shef.toolsaiassistant мягко зовёт `KbSearchService::find()`.

## Линейка ИИ: зависимости и связь событиями

С 1.50.0 shef.toolsai разделён на четыре модуля. Зачем: баннеры, база знаний
и чат ставятся по желанию и выпускаются отдельно, а основной модуль от них
не зависит. Связь — только событиями D7 (`Bitrix\Main\Event`).

Сверено с кодом 2026-10-17; версии — на портале stuttgart (выкладка 3.72).

| модуль | репозиторий | версия | обязательно нужен | по желанию |
|---|---|---|---|---|
| `shef.toolsai` | [bx-shef/toolsai](https://github.com/bx-shef/toolsai) | 1.58.0 | `shef.options`, `shef.problems`; модули ядра `ai`, `crm` — проверка в установщике | — |
| `shef.toolsaiassistant` | [bx-shef/toolsai-assistant](https://github.com/bx-shef/toolsai-assistant) | 1.6.0 | `shef.options`, `shef.problems`, `shef.toolsai` (модель, квота, журнал расхода, реестр промптов, история записи) | `shef.toolsaikb` — поиск статей для ответа |
| `shef.toolsaiannounce` | [bx-shef/toolsai-announce](https://github.com/bx-shef/toolsai-announce) | 1.4.1 | `shef.options`, `shef.problems` | — |
| `shef.toolsaikb` | [bx-shef/toolsai-kb](https://github.com/bx-shef/toolsai-kb) | 1.2.0 | `shef.options`, `shef.problems`; модуль ядра `note` 26.250.0+ — проверка в установщике | — |

| событие | кто шлёт | кто отвечает | что передаёт |
|---|---|---|---|
| `shef.toolsai:OnPromptRegistryBuild` | toolsai | assistant | роли чата в реестр промптов |
| `shef.toolsai:OnPromptChanged` | toolsai | — (для своих модулей) | код изменённого промпта |
| `shef.toolsai:OnPromptPlacesCollect` | toolsai | — (для своих модулей) | места «Где доступен» промпта |
| `shef.toolsai:OnViewRowActionsBuild` | toolsai | assistant | пункт строки в виде «ИИ» |
| `shef.toolsai:OnViewToolbarBuild` | toolsai | assistant | кнопка в шапке вида «ИИ» |
| `shef.toolsai:OnViewSettingsMenuBuild` | toolsai | assistant, announce, kb | пункты шестерёнки вида «ИИ» |
| `shef.toolsaiannounce:OnAnnouncementsCollect` | announce | toolsai | объявления по умолчанию |
| `shef.toolsaiannounce:OnAnnouncementPlacesCollect` | announce | — (для своих модулей) | места показа объявлений |
| `shef.toolsaikb:OnKbSourcesCollect` | kb | toolsai | пакет статей справки (`shef.toolsai:guide`) |
| `shef.toolsaiassistant:OnChatPlacesCollect` | assistant | toolsai | места «Где показывать чат» |

Подробно: публичный API и события toolsai —
[guide/32](https://github.com/bx-shef/toolsai/blob/main/docs/guide/32-public-api.md),
реестр промптов —
[guide/31](https://github.com/bx-shef/toolsai/blob/main/docs/guide/31-prompt-registry.md).

## Открыто

- Проверка на стенде с живым `note`: вид документов `md` в редакторе,
  поведение формата после правки человеком (ожидается переход в `yjs`).
- Поиск — решено после панели (2026-10-16): по умолчанию только свой раздел,
  по всей «Базе знаний» — настройка `KB_searchall`, выключена. Ядро не
  фильтрует поиск по разделу, фильтр — по выдаче (KbSearchService).
