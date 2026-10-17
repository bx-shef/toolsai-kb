# Памятка агенту: shef.toolsaikb

> Последняя сверка: 2026-10-17

Модуль коробочного Битрикс24: статьи модулей линейки в «Базе знаний» (модуль
ядра `note`) и поиск по ней. Опирается на `shef.options` и `shef.problems`,
сделан по канону линейки и образцу [shef.toolsai](https://github.com/bx-shef/toolsai):
раскладка, установщик, `build.sh`, тесты на обвязке `tests/assert.php`.

Проверка перед сдачей — всегда `./build.sh --check`, ровно это гоняет CI.

**Процесс** — [docs/private/agent-rules.md](docs/private/agent-rules.md): только
через PR, ревью панелью из пяти на изменения поведения, установщика, API и CI;
навыки `review-panel` и `merge-pr`. Состояние — [docs/private/project-map.md](docs/private/project-map.md).

**Описание модуля** — [docs/guide/README.md](docs/guide/README.md). Любая правка
кода — проверь и поправь документацию в том же PR.

**Опора на ядро** — [docs/private/00-research.md](docs/private/00-research.md):
какие файлы `note` прочитаны и что из них следует (формат `md`, права раздела,
поиск «все слова сразу»). Обновился `note` — сверить по этому списку.

Документация Битрикса — локально в `.bitrix-docs/` (в `.gitignore`), статьи
`framework-docs` (D7, ORM, события). Расходится с ядром — прав код ядра.

## Где что

| что | где |
|---|---|
| публичный поиск | `lib/public/service/kbsearchservice.php` |
| событие сбора пакетов | `lib/public/event/onkbsourcescollectevent.php` |
| импорт (чистая логика) | `lib/import/` — `Planner`, `Importer`, `ArticleParser`, `SourceValidator`, `SourceReader` |
| импорт на портале | `lib/import/runner.php`, агент `lib/agent/refresh.php` |
| доступ к `note` | `lib/note/bitrixnotegateway.php` (только `Bitrix\Note\Public\…`) |
| разбор вопроса | `lib/search/querynormalizer.php` — перенос `Rag::tokens()` shef.toolsai |
| таблица соответствий | `lib/model/mappingtable.php` |
| ajax | `lib/controller/search.php` -> `shef:toolsaikb.search.find` |
| сводка импорта на странице настроек | `lib/settings/reportview.php`, вывод — `options.php` |

## Тесты

`tests/*_test.php`, гоняются как скрипты. Ядро — заглушки `tests/stub/bitrix.php`,
публичный API `note` — `tests/stub/note.php` (имена параметров как в ядре),
подставные «База знаний» и таблица — `tests/stub/fakes.php`. Классы модуля —
настоящие (`tests/stub/autoload.php`).

## Решения

- Документы пишутся в формате `md` — иначе текст не виден и не ищется.
- Правленная руками статья не затирается: сравнение с хэшем последнего импорта
  и формат `md` (документ, открытый в редакторе, переходит в `yjs`).
- Статьи, пропавшие из пакета (и пропавшие пакеты), — в корзину `note`;
  правленные руками и архивные не трогаются. Событие без пакетов или сбор с
  ошибками — пропавшие пакеты не удаляются. Раздел в архиве не трогается.
- Поиск по умолчанию — только свой раздел (фильтр выдачи), всё — `KB_searchall`.
- Удаление не стирает ID раздела и таблицу соответствий: документы в `note` остаются.
- `note` — в установщике (`NEED_MODULES_BY_VERSION`), не в `requireModules`.
- `options_page.php` — дословная копия `options.php` shef.options; своё — в `options.php`.
