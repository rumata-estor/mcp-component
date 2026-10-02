[English](VALIDATION.md) | **Русский**

# Статус валидации коннектора

Дата: 2026-10-02

## Общая архитектура

В одном дереве исходников находятся общий модульный core и платформенные адаптеры:

- `Platform/Modx2Platform.php`
- `Platform/Modx3Platform.php`
- `Registry/ToolRegistry.php`
- `Legacy/LegacyActionAdapter.php`
- модульные `Tools/`
- общий endpoint `endpoint/api.common.php`

Модульный registry теперь полностью покрывает публичный контракт: 74 read-only действия и 108 mutation-действий, всего **182/182**. Старый dispatcher остаётся только как совместимый/эталонный слой; обычные запросы обрабатываются модульным Runtime.

## Готовность 1.1.0 к релизу

Версия 1.1.0 — первая общая линия исходников для MODX 2 / MODX 3. MODX 3.2.4-pl прошёл полный live regression. Для MODX 2 проходят release staging и статическая/PHP-совместимость; перед публикацией GitHub release 1.1.0 как окончательного стабильного релиза всё ещё требуется отдельный live release-smoke на реальном MODX 2.

## Текущее модульное покрытие read-only

В registry находятся все 74 read-only Tool.

<!-- READONLY_MIGRATION_COVERAGE: 74/74 -->

Read-only миграция завершена: все 74 серверных действия зарегистрированы в модульном Runtime. Маркер покрытия выше проверяется `_build/test.migration-coverage.py`, поэтому документированный счётчик не может незаметно разойтись с кодом.

Перенесённые действия:

- `get_capabilities`
- `system_info`
- `project_overview`
- `list_resources`
- `check_integrations`
- `list_system_settings`
- `get_system_setting`
- `list_tv_input_types`
- `suggest_tv_type`
- `list_installed_components`
- `list_elements`
- `get_element`
- `list_media_sources`
- `get_media_source`
- `list_media_source_files`
- `read_media_source_file`
- `get_resource_tvs`
- `list_tv_values`
- `get_component_files`
- `read_component_file`
- `list_contexts`
- `get_context`
- `list_context_settings`
- `get_context_setting`
- `list_namespaces`
- `list_lexicon_entries`
- `list_lexicon_topics`
- `list_property_sets`
- `get_property_set`
- `list_providers`
- `search_packages`
- `list_users`
- `get_user`
- `list_user_groups`
- `get_user_group`
- `list_user_group_members`
- `list_roles`
- `get_role`
- `list_access_policies`
- `list_access_policy_templates`
- `list_access_permissions`
- `list_resource_groups`
- `list_context_access`
- `list_resourcegroup_access`
- `help`
- `list_actions`
- `read_error_log`
- `read_audit_log`
- `describe_object`
- `search_code`
- `find_usages`
- `view_element`
- `dependency_graph`
- `versionx_list_versions`
- `versionx_get_version`
- `migx_list_configs`
- `migx_get_config`
- `virtualpage_list_events`
- `virtualpage_get_event`
- `virtualpage_list_handlers`
- `virtualpage_get_handler`
- `virtualpage_list_routes`
- `virtualpage_get_route`
- `virtualpage_resolve_route`
- `ms2_list_link_types`
- `ms2_get_link_type`
- `ms2_list_product_links`
- `ms2_list_categories`
- `ms2_list_orders`
- `ms2_get_order`
- `ms2_list_option_types`
- `ms2_list_options`
- `ms2_get_option`
- `ms2_get_product_options`

## Текущее модульное покрытие mutations

Модульный Runtime теперь содержит все 108 mutation-действий.

<!-- MUTATION_MIGRATION_COVERAGE: 108/108 -->

Первым mutation-блоком был прямой слой штатных MODX processors: запись Access/ACL, Context, Namespace и Lexicon. Эти действия выполняются через `ProcessorMutationTool` + `MutationProcessorCatalog` вместо legacy dispatcher. Миграция mutations завершена: все 108 изменяющих действий зарегистрированы в модульном Runtime, поэтому все **182** серверных действия имеют модульную реализацию.

2 октября 2026 года текущий модульный registry повторно проверен на live sandbox MODX 3.2.4-pl через `_build/test.live-modular-parity.php`. Каждое действие выполнялось один раз через модульный registry и один раз через тот же экземпляр коннектора с отключённым `modularRuntime`; все результаты совпали, включая ожидаемые контракты ошибок.

Чтение файловой системы через media source на sandbox остаётся отключено настройкой `modxmcp.allow_root_filesystem_read=0`; parity проверяет одинаковый запрет безопасности по обоим путям и не ослабляет настройку ради теста. Группы возможностей, отключённые в постоянных настройках, включаются только в памяти процесса parity-теста, поэтому live-аудит не оставляет sandbox с расширенными правами.

## Автоматические проверки

- Architecture: PASS.
- Build architecture: PASS.
- Client/server contract: PASS, 182 действия.
- Endpoint architecture: PASS.
- MODX2 PHP compatibility: PASS.
- Platform mapping: PASS.
- Modular core: PASS.
- MODX3 processor compatibility: PASS, 113 файлов процессоров на live sandbox MODX 3.
- Release portability: PASS.
- Release staging: PASS.
- Migration coverage consistency: PASS, read 74/74; mutations 108/108.
- Live modular-vs-legacy parity: PASS для всех 74 read-only действий.
- Live mutation processor parity: PASS, 46 транзакционных проверок для 40 модульных mutation-действий; все записи откатывались.
- Live system/TV/ops mutation parity: PASS, 8/8 действий.
- Live Property-set mutation parity: PASS, 5/5 действий с транзакционным rollback.
- Live media mutation parity: PASS, 9/9 действий в изолированном временном media source; все артефакты удалены.
- Live resource/ops mutation parity: PASS, 6/6 безопасных live-действий; `empty_recycle_bin` пропущен, потому что на sandbox уже был удалённый ресурс, `regenerate_token` пропущен, чтобы не менять активный API-токен.
- Live VersionX mutation parity: PASS для безопасного контракта `confirm=true` + отсутствующая версия; реальный контент не откатывался.
- Live miniShop2 mutation parity: PASS, 12/12 действий; положительные create-paths откатывались транзакционно, реальные заказы/товары не изменялись.
- Live final mutation parity: PASS, `bulk_resources` и `replace_across` совпадают с legacy и в dry-run, и при реальной записи на временных объектах.
- Full live regression: PASS, все 14 live parity-наборов завершились с exit code 0 на финальном Runtime.
- Два глобально разрушительных действия не запускались live: `empty_recycle_bin` и `regenerate_token`; оба покрыты модульной регистрацией, статическими contract checks и проверкой эквивалентности реализации legacy.
- Live Package-management mutation parity: PASS, 5/5 действий; install/uninstall пакетов проверялись только на неразрушительных путях.
- Live MIGX mutation parity: PASS, 3/3 действий с транзакционным rollback.
- Live VirtualPage mutation absence parity: PASS, 10/10 действий совпадают с legacy при отсутствии VirtualPage; положительный lifecycle ещё нужно проверить на тестовом сайте с установленным VirtualPage.
- Live element mutation parity: PASS для create/update/dry-run delete/delete по всем 7 типам элементов.
- Live element file mutation parity: PASS для `make_static` и редактирования строк DB/static по chunk/snippet/template/plugin; тестовые файлы удалены.
- Element list/get live parity matrix: PASS по всем 7 поддерживаемым типам элементов.
- Element view live parity matrix: PASS по всем 4 поддерживаемым viewable-типам.
- Контракт безопасности при отключённом filesystem media-source: PASS во всём parity-run.

## MODX 3

Live-проверка выполнена на отдельном sandbox MODX 3.2.4-pl. Установленный модульный Runtime соответствует текущему дереву исходников для проверенного registry.

Статус: **LIVE-VALIDATED**.

## MODX 2

- release-stage успешно собирается;
- статический тест PHP-совместимости проходит;
- platform mapping проходит, включая отображение `modTemplateVarResource`;
- build overlay присутствует.

Отдельный live sandbox MODX 2 пока не подключён.

Статус: **STATICALLY VALIDATED, LIVE TEST PENDING**.
