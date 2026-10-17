# Опора на ядро: модуль `note` 26.250.0

> Сверено с `bitrix/modules/note` портала клиента, версия 26.250.0 (2026-07-23).
> Обновился `note` — пройти по списку.

Своих событий у `note` нет. Модуль пользуется только публичным API
`Bitrix\Note\Public\…`; классы `Internal\Exceptions\*` упоминаются лишь для
`instanceof` при разборе отказа команды.

| файл ядра | что из него следует |
|---|---|
| `lib/Public/Command/CreateCollectionCommand.php` | `(name, createdBy, position, policyLevel, permissions)`; результат — `getData()['collection']` |
| `lib/Internal/Service/Collection/CollectionService.php::create()` | без политики и прав ставится `*` = none — раздел видит только автор. Поэтому политика `view` и автор `moderate` |
| `lib/Internal/Access/Service/CollectionAccessService.php` | уровни `none/view/manage/moderate`; у раздела обязан быть модератор |
| `lib/Public/Command/CreateDocumentCommand.php` | `(collectionId, parentId, title, markdown, userId, contentFormat = yjs)`; результат — `getData()['document']` |
| `lib/Internal/Service/Document/DocumentService.php::create()` | в поисковый индекс сразу попадает только формат `md` |
| `lib/Infrastructure/Rest/V3/Controller/Document.php::addAction()` | REST ядра: есть текст — формат `md`, нет — `yjs`. Импорт делает так же |
| `lib/Public/Command/OverwriteDocumentContentCommand.php` | перезапись текста и заголовка; документ в `yjs`/`json` переводится в `md`; с несохранёнными правками без `overwrite` — отказ; корзина и архив — исключения |
| `lib/Public/Provider/DocumentProvider.php::getById()` | документ без проверки прав: заголовок, текст, формат, архив |
| `lib/Public/Provider/CollectionProvider.php::getById()` | раздел, `getIsArchived()` |
| `lib/Public/Provider/SearchProvider.php::search()` | права текущего пользователя (`CurrentUser`), без пользователя — пусто |
| `lib/Internal/Service/Search/SearchQueryBuilder.php` | `Helper::matchAgainstWildcard()`: каждое слово от 3 букв обязательно, `слово*` |
| `lib/Public/Provider/Param/Document/DocumentLimits.php` | текст документа — до 1 048 576 байт |
| `lib/Public/Provider/WikiImportLinkProvider.php` | адрес документа — `/note/document/<id>/` |
| `main/lib/Command/AbstractCommand.php::run()` | исключение команды приходит обёрнутым в `CommandException` (`getPrevious()`) |

Документация Битрикса (`framework-docs`): события D7 — правило
`bitrix-best-practice-core/rules/event.md`, контроллеры — `controller.md`,
ORM — `bitrix-best-practice-sql`.

## Удаление пропавших статей (1.1.0)

- `lib/Public/Command/DeleteDocumentCommand.php` — документ в корзину вместе
  с дочерними (`MoveToRecycleBinService::moveSubtree`), снятие с поиска
  (`SearchIndexService::deindexDocuments`); нет документа или он уже в
  корзине — `data['success'] = false`, ошибки в `Result` нет. Прав не
  проверяет — их проверяют контроллеры (`DocumentController`).
- `lib/Public/Provider/TreeProvider.php::getTree()` — всё дерево раздела без
  проверки прав (`DocumentRepository::getCollectionDocuments`: без архивных и
  без корзины). По нему импорт проверяет, нет ли под удаляемым документом
  документов, созданных людьми.
