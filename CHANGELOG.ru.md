[English](CHANGELOG.md) | **Русский**

# MODX MCP — Журнал изменений

## 1.1.0 (2026-10-02)

Крупный архитектурный релиз, переводящий проект на общую кодовую базу для MODX 2 и MODX 3.

### Поддержка MODX 2 и MODX 3

- Добавлен единый модульный Runtime с платформенными адаптерами для MODX Revolution 2.8.x и 3.x.
- Из одного дерева исходников теперь готовятся две платформенные сборки: `modxmcp-1.1.0-pl.transport.zip` для MODX 2.8.x и `modx3mcp-1.1.0-pl.transport.zip` для MODX 3.x.
- Node.js MCP-клиент и публичный набор MCP-действий одинаковы для обеих веток MODX.
- Различия между версиями MODX изолированы в платформенных адаптерах и release overlay, а не распределены по прикладным Tool-классам.
- Отображаемое имя компонента изменено на **MODX MCP**. Технические идентификаторы `modxmcp`, URL репозитория и имя Node-пакета сохранены ради обратной совместимости.

### Полный перенос на модульную архитектуру

- Все **182/182** серверных действия теперь выполняются через модульный Runtime.
- Read-only часть перенесена полностью: **74/74** действия.
- Изменяющие операции перенесены полностью: **108/108** действий.
- Модульные блоки охватывают элементы и ресурсы, контексты, ACL, Property Sets, media, пакеты/providers, MIGX, VirtualPage, VersionX, miniShop2, системные настройки, TV-значения, массовые операции и служебные действия.
- Старый dispatcher остаётся только как совместимый/эталонный слой.

### Проверка и подготовка релиза

- Добавлена подготовка платформенной сборки через `_build/prepare-release.py --platform modx2|modx3`.
- MODX 3.2.4-pl прошёл полный live regression, включая read parity и отдельные mutation parity-наборы.
- Совместимость процессоров MODX 3 продолжает проверяться на 3.2.2-pl, 3.2.4-pl и актуальной ветке 3.x.
- Статическая/PHP-совместимость и release staging MODX 2 проходят.
- Перед публикацией 1.1.0 как окончательного стабильного релиза GitHub требуется отдельный live release-smoke на реальном MODX 2.
- CI теперь фиксирует как инварианты полный контракт из 182 действий и обе платформенные сборки.

## 1.0.1 (2026-09-29)

Исправляющий релиз для назначения пустого шаблона в массовых операциях с ресурсами.

### Исправлено

- `modx_bulk_resources` с `operation: "set_template"` теперь принимает `template: 0` — штатное значение MODX для ресурса без назначенного шаблона.
- Серверная валидация теперь отличает отсутствующий параметр от целого значения `0` и отклоняет только отсутствующие, `null`, пустые, нецелые или отрицательные значения шаблона.
- В схеме MCP параметр `template` теперь объявлен как целое число с `minimum: 0`, поэтому клиенты и агенты могут корректно передавать пустой шаблон.
- В release regression-проверки добавлена защита от повторного появления ошибки с `template=0` на сервере и в клиентской схеме.
- Обновление transport package теперь восстанавливается даже если предыдущая установка оставила файлы MODX3 MCP без права записи для владельца; для файлов и каталогов пакета явно задаются права `0644`/`0755`.
- Проверка transport package теперь подтверждает фактически установленную версию модели, а endpoint smoke-test поддерживает `MODX_MCP_SMOKE_SITE_URL` для тестовых стендов, где системный `site_url` указывает на другой адрес.

## 1.0.0 (2026-09-27)

Первый стабильный релиз линии **MODX3 MCP** для MODX Revolution 3.x, созданной на базе оригинального проекта [**modxMCP**](https://github.com/dampilov94/mcp-component), автор — [**dampilov94**](https://github.com/dampilov94).

Версия 1.0.0 — это не простой перенос исходного компонента на MODX 3. Проект был существенно переработан: изменены загрузка и маршрутизация MODX, схема установки и обновления, модель безопасности, работа с сервисным пользователем, обработка изменяющих операций, аудит, резервное копирование и набор автоматических проверок. Специфические для MODX 3 изменения — пространства имён, классы MODX/xPDO, маршруты процессоров и пути установки — перечислены ниже в технических пунктах.

Переработка выполнялась не как механическая замена имён классов и путей. Решения принимались с учётом реального поведения MODX 3: загрузки ядра, пространств имён, процессоров, прав доступа, сервисного пользователя, установки пакета, транзакций и отката изменений. Для критичных участков проверялось не только то, что код запускается, но и то, что он ведёт себя предсказуемо при установке, повторной установке, ошибках и удалении компонента.

### Переход на MODX Revolution 3

- Проект получил новое имя и идентификаторы: **MODX3 MCP**, пакет **MODX3MCP**, Node.js-пакет `modx3-mcp`. Внутреннее пространство имён `modxmcp` сохранено для совместимости.
- Серверная часть переведена на нативную загрузку MODX Revolution 3, пространства имён MODX/xPDO и маршрутизацию процессоров MODX 3.
- Поддерживаемый диапазон версий ограничен **MODX Revolution >= 3.0.0 и < 4.0.0**.
- Совместимость процессоров проверяется на MODX Revolution 3.2.2-pl, 3.2.4-pl и актуальной ветке 3.x.

### Установка и обновление

- Транспортный пакет и установка из командной строки приведены к переносимой схеме без привязки к конкретному сайту.
- Установщик из командной строки создаёт и обновляет пункты меню менеджера, сохраняя существующие настройки и API-токен.
- Новая версия файлов сначала собирается во временных каталогах. Рабочие каталоги заменяются только после успешной подготовки; при ошибке восстанавливается предыдущая версия.
- Изменения пространства имён, меню, настроек и токена выполняются в общей транзакции xPDO и откатываются вместе с файлами при незавершённой установке.
- Удаление транспортного пакета очищает пункты меню, настройки `modxmcp.*`, пространство имён и файлы компонента.
- Добавлена проверка соответствия транспортной и командной установки: сравниваются все 16 определений `modxmcp.*`, включая значение по умолчанию, тип поля и область настроек.
- Сборщик транспортного пакета переведён в режим запуска только из командной строки.

### Безопасность

- `service_user_id=0` автоматически выбирает только активного пользователя MODX с правом `sudo`; зависимость от пользователя с ID 1 удалена.
- Явно заданный сервисный пользователь также должен быть активным и иметь право `sudo`.
- `auto_static` отключён по умолчанию.
- HTTPS обязателен по умолчанию. Доверие к `X-Forwarded-Proto` включается только отдельной настройкой.
- Для генерации API-токена используется только `random_bytes()`; небезопасные резервные варианты удалены.
- API-токен больше нельзя передать через строку запроса, чтобы он не попадал в URL, журналы доступа и историю браузера.
- Проверка HTTPS выполняется до ответа на служебный GET-запрос; размер тела запроса проверяется до и после чтения.
- Помощник транзакций откатывает изменения при любом `Throwable`.
- Экраны менеджера требуют право `settings`; исправлено разворачивание `{core_path}` в коннекторе менеджера.
- Для `clear_tv_values` требуется явное подтверждение, а клиент предварительно создаёт резервную копию.

### Работа с TV и изменяющими операциями

- Добавлены серверные операции `list_tv_values` и `clear_tv_values` для безопасной работы с явно сохранёнными значениями TV.
- Классификация операций чтения и изменения расширена: `list/get/search/read/view/check/describe/find/suggest` и отдельные операции чтения больше не получают блокировку проекта и не создают лишние события аудита.
- Для изменяющих операций сохраняется механизм блокировки проекта, чтобы уменьшить риск параллельной записи.

### Аудит

- Клиент поддерживает необязательный `MODX_MCP_AUDIT_HOOK`.
- После успешной операции, изменяющей сайт, доверенная внешняя программа может получить через стандартный ввод JSON с данными операции и путями к автоматически созданным резервным копиям.
- Отсутствие обработчика не меняет обычную работу клиента.
- Ошибка внешнего обработчика выдаёт предупреждение, но не превращает уже успешно выполненное изменение сайта в ошибку.

### Проверки перед релизом

- Добавлены автоматические проверки переносимости установки и соответствия клиентских инструментов серверным операциям.
- Добавлен полный сценарий `_build/release.smoke.sh` и `_build/smoke.endpoint.php`.
- Сценарий проверяет синтаксис PHP, сборку, установку, работу API, CRUD-операции, повторную установку с сохранением настроек, полное удаление и финальную установку с проверкой операций только для чтения.
- API-токен не выводится в журналы проверок.
- Перед выпуском 1.0.0 выполнена отдельная чистая установка на MODX Revolution 3.2.4-pl с проверкой MCP-клиента, изменения тестовых объектов, повторной установки и полного удаления компонента.

## История исходной функциональности modxMCP

Ниже без перевода сохранена история функциональности исходного проекта [**modxMCP**](https://github.com/dampilov94/mcp-component), созданного [**dampilov94**](https://github.com/dampilov94).

Эта часть относится к исходной линии проекта, на которой основан MODX3 MCP. Оригинальный английский текст сохранён намеренно, чтобы не искажать формулировки и технические детали исходного журнала изменений.

## 1.9.0 (2026-08-10)

- **`dependency_graph`** — a structural map of how the site's elements wire together: which
  template pulls which chunk/snippet/TV, which snippet renders which chunk, where each TV is
  attached. Returns `nodes` + `edges` (`[from, to, kind]`) and, for free, two things a text
  search cannot give: **`missing`** (tags pointing at a chunk/snippet that does not exist —
  genuinely broken references) and **`orphans`** (elements nothing references — dead code).
  References are detected in MODX tags, chunk-valued properties — inline `&tpl=`…`, an element's
  **default properties** and **named property sets** (miniShop2 wires `msProducts` →
  `tpl.msProducts.row` there, not in any content) — `$modx->getChunk()`/`runSnippet()` in PHP,
  MIGX `inputTV`/`renderchunktpl`, `@CHUNK` bindings and the template↔TV relation. Chunks used
  by something that isn't an element (a system setting, a miniShop2 order-status e-mail, which
  references chunks by id) get a `used_by` field and are never called orphans. `focus`/`depth`/`direction` return just the neighbourhood of one
  element — precise where `find_usages` is a substring match — and `format:"summary"` is a
  cheap site health check. Token-safe by the same rule as `project_overview`: it scales with
  the element count, never with content (resources are counts, not nodes). Orphan candidates
  are cross-checked against resource content so a chunk pasted into a page isn't falsely
  listed (auto-skipped above 5000 resources; `stats.orphans_verified` says which). Read-only:
  static elements are read via `getFileContent()`, avoiding the re-save `getContent()` can
  trigger. New `graph` help topic; `getting_started`/`index` updated to route to it.
- **Visual graph on its own manager screen** — Components → modxMCP → «Граф связей» (a second
  menu item and manager action, `?a=graph&namespace=modxmcp`; the settings page links to it and
  is otherwise unchanged). Three layouts, because one undifferentiated hairball is unreadable:
  **Слои** (default — each type gets its own horizontal row, in the manager's own tree order:
  resources, then templates, TVs, chunks, snippets, plugins, with tinted bands and sticky row
  headers), **Кластеры** (each type pulled into its own cloud) and **Свободно** (pure force).
  Full-height force-directed map with arrows showing direction of use,
  nodes sized by reference count, hover-to-highlight, drag/zoom/pan, type filters with counts,
  a search box with results, clickable sidebar lists of broken references and unused elements,
  and a details card (category, degrees, `used_by`, incoming/outgoing lists, edit link).
  Filters for the two kinds of noise that drown a real site: **hide vendor elements** (nodes
  carry a `vendor` flag — their category, or their name, matches an installed add-on namespace;
  on a stock install that is ~60% of the graph) and **hide unused**. Plus a density slider,
  since the useful spacing depends on how big the site is.
  The central interaction is **isolation**: double-click a node to show only its neighbourhood,
  with 1–3 hop depth and an uses / used-by switch — that is the "what breaks if I touch this"
  view. Broken references render as hollow red nodes, orphans get a dashed halo. Repulsion uses
  a uniform grid so large sites stay interactive. No external libraries (the manager ships none
  and the transport package stays self-contained). The screen and the MCP action share ONE
  builder, so what the owner sees and what the AI reasons over cannot drift apart.

## 1.8.20 (2026-06-25)

- Fix: `edit_element_lines` and `replace_across` now fire the core save events
  (`OnBefore/On{Type}FormSave`) like a normal element update, so **VersionX (and any other
  save-event plugin) creates a version on a line/replace edit** — previously these saved the
  content directly (`$el->save()` / file write) and bypassed the events, so no version was made
  (create and full `update_element` did version, line edits didn't). They now save the full
  field set through the element update processor (content field overridden), and still write the
  static file first for static elements (so the event sees the new content). Token efficiency is
  unchanged — the model still sends only the delta; the server reconstructs and saves properly.

## 1.8.19 (2026-06-23)

- Docs/steering: `getting_started` rewritten around a numbered **recommended workflow**
  (orient → locate → look-before-change/`describe_object` → cheap edits → dry-run destructive →
  verify) so a model that's weak at MODX follows the safe, token-efficient path; `index` help
  landing refreshed to surface `project_overview`, `suggest_tv_type`, `describe_object` and the
  current topics. Helps reduce "which of ~180 tools do I use?" load.

## 1.8.18 (2026-06-23)

- MIGX guide expanded (verified against the MIGX 3.0.2 source): documents `inputTV` — reuse an
  existing TV as a MIGX field's input (for resource pickers limited by parent/template, richtext,
  media, or nested MIGX) instead of hand-writing `@SELECT` — with the required `ForMigx` naming
  convention for those helper TVs (e.g. `listNewsForMigx`). Also clarifies that inline
  `input_properties` (formtabs/columns + optional contextmenus/actionbuttons/columnbuttons/
  filters/extended) is as capable as a named config for TV-stored MIGX, so the inline-only,
  all-JSON workflow is the recommended path (named configs only for cross-TV reuse / MIGXdb).

## 1.8.17 (2026-06-23)

- `suggest_tv_type` — describe a field need (English or Russian) and get ranked candidate TV
  `field_type`s with reasons + a ready-to-edit create_element skeleton (with the extra keys that
  type needs) for the top pick. Deterministic bilingual keyword rules; helps a model unsure about
  MODX pick the right TV type. (group: tv_inputs)
- `list_tv_input_types` now also reports a `colorpicker` custom type when its namespace is
  installed, even if the OnTVInputRenderList event was swallowed (e.g. a broken plugin on it).

## 1.8.16 (2026-06-23)

- MIGX authoring guide (`migx` help doc) rewritten to lead with a complete, copy-pasteable
  INLINE MIGX TV example (a gallery: fields + columns as JSON strings in `input_properties`,
  no separate config object) so a model can build a working MIGX TV on a fresh site without an
  existing config to copy. Field/column reference + the renderChunk gotchas (server-side render,
  `renderchunktpl` key, mandatory virtual `dataIndex`) kept. Verified live: the documented
  example creates a valid `field_type:"migx"` TV end-to-end.

## 1.8.15 (2026-06-23)

- TV authoring guidance (mission: help any model pick the right input type): `list_tv_input_types`
  now returns per-core-type `use` (when to pick it) + `requires` (extra create_element keys like
  elements/media_source); the `tv_input_types` help doc rewritten into a "task → type" decision
  guide with correct examples.
- Robustness: `list_tv_input_types` no longer 500s when a third-party plugin on the manager event
  `OnTVInputRenderList` fatals headlessly (e.g. Ace's `addLexiconTopic() on null`). The event
  invocation is now guarded (Exception + Throwable) — a misbehaving plugin's custom types are
  skipped instead of crashing the action.

## 1.8.14 (2026-06-22)

- `project_overview` — orient on a whole installed site in ONE compact, token-safe call:
  template↔TV map, resource/product COUNTS (overall + by template + by context), a shallow
  resource tree (roots + child counts, capped), element categories, content types, contexts,
  integrations. Scales with structure not content (a 100k-resource site returns the same small
  payload); per-item browsing stays in list_resources / list_elements. `sections` and
  `max_tree_nodes` params; documented as the "orient first" step in getting_started.

## 1.8.13 (baseline)

Capability baseline (history before this point intentionally collapsed). The server exposes a
broad MODX management surface via the MCP client; capability groups are toggled in the CMP
(Components → modxMCP) and enforced server-side.

- **Elements** (chunk/snippet/template/resource/tv/category/plugin): list/get/create/update/
  delete, `make_static`, line-based `view_element` / `edit_element_lines`, `duplicate_element`.
- **Resources**: `list_resources`, `bulk_resources` (publish/unpublish/set_template/move/delete,
  with dry-run), `duplicate_resource`, `reorder_resources`, trash `undelete_resource` /
  `empty_recycle_bin`, `get`/`update_resource_tvs`.
- **Code navigation**: `search_code` (returns match line + line_text), `find_usages`,
  `replace_across` (site-wide, dry-run preview).
- **Media sources**: source CRUD + file/folder ops (create/update/rename/delete file,
  create/delete folder).
- **System settings**, **TV input types**, **components introspection** (read add-on source),
  **describe_object** (xPDO schema).
- **Toggleable groups**: VersionX, VirtualPage, miniShop2, MIGX, Access Control, contexts,
  property sets, package management, namespaces, lexicon.
- **Ops/diagnostics**: `list_actions`, `get_capabilities`, `help` (RAG docs), `clear_cache`,
  `read_audit_log`, `read_error_log`, `refresh_uris`, `remove_locks`, `system_info`,
  `regenerate_token`, `run_processor` (gated).
- **Endpoint**: token auth, optional IP allowlist + HTTPS enforcement, health/version GET.

Versions are kept in sync across `_build/build.config.php`, `package.json`,
`package-lock.json` and `modxMCP::VERSION` (CI-enforced).

