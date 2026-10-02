# Connector validation status

Date: 2026-10-02

## Shared architecture

One source tree contains a shared modular core plus platform adapters:

- `Platform/Modx2Platform.php`
- `Platform/Modx3Platform.php`
- `Registry/ToolRegistry.php`
- `Legacy/LegacyActionAdapter.php`
- modular `Tools/`
- shared endpoint `endpoint/api.common.php`

The modular registry gets first refusal. Actions not yet migrated fall through to
the legacy dispatcher without changing the MCP contract.

## Current modular read-only coverage

The registry currently contains all 74 read-only tools.

<!-- READONLY_MIGRATION_COVERAGE: 74/74 -->

The read-only migration is complete: all 74 read-only server actions are registered in the modular runtime. The coverage marker above is checked by `_build/test.migration-coverage.py` so the documented count cannot silently drift from the code.

Migrated actions:

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

## Current modular mutation coverage

The modular runtime now owns all 108 mutation actions.

<!-- MUTATION_MIGRATION_COVERAGE: 108/108 -->

The first mutation block is the direct MODX-processor layer: Access/ACL, Context,
Namespace and Lexicon writes. These actions now execute through
`ProcessorMutationTool` + `MutationProcessorCatalog` instead of the legacy
dispatcher. The mutation migration is now complete: all 108 mutation actions
are registered in the modular Runtime, so all 182 server actions have a modular
implementation.

On 2026-10-02 the current modular registry was re-audited on the live MODX 3.2.4-pl sandbox with `_build/test.live-modular-parity.php`. Each action was executed once through the modular registry and once through the same connector instance with `modularRuntime` disabled; all migrated results matched exactly, including expected error contracts.

Filesystem media-source reads remain disabled on the sandbox by `modxmcp.allow_root_filesystem_read=0`; parity therefore verifies the same security denial on both paths rather than weakening the setting for a test. Capability groups that are disabled in persistent settings are enabled only in the parity process memory, so the live audit does not leave the sandbox with broader permissions.

## Automated tests

- Architecture: PASS.
- Build architecture: PASS.
- Client/server contract: PASS, 182 actions.
- Endpoint architecture: PASS.
- MODX2 PHP compatibility: PASS.
- Platform mapping: PASS.
- Modular core: PASS.
- MODX3 processor compatibility: PASS, 113 processor files on the live MODX3 sandbox.
- Release portability: PASS.
- Release staging: PASS.
- Migration coverage consistency: PASS, read 74/74; mutations 108/108.
- Live modular-vs-legacy parity: PASS for all 74 migrated actions.
- Live mutation processor parity: PASS, 46 transactional checks across 40 modular mutation actions; all writes rolled back.
- Live system/TV/ops mutation parity: PASS, 8/8 actions.
- Live Property-set mutation parity: PASS, 5/5 actions with transactional rollback.
- Live media mutation parity: PASS, 9/9 actions in an isolated temporary media source; all artifacts removed.
- Live resource/ops mutation parity: PASS, 6/6 safe-live actions; empty_recycle_bin skipped because the sandbox had a pre-existing deleted resource, regenerate_token skipped to preserve the active API token.
- Live VersionX mutation parity: PASS for the safe confirm=true + missing-version contract; no live content was reverted.
- Live miniShop2 mutation parity: PASS, 12/12 actions; positive create paths were transactionally rolled back and real orders/products were not modified.
- Live final mutation parity: PASS, bulk_resources and replace_across both match legacy for dry-run and real writes on temporary test objects.
- Full live regression: PASS, all 14 live parity suites completed with exit code 0 on the final Runtime.
- Live Package-management mutation parity: PASS, 5/5 actions; package install/uninstall tested only on non-destructive paths.
- Live MIGX mutation parity: PASS, 3/3 actions with transactional rollback.
- Live VirtualPage mutation absence parity: PASS, 10/10 actions match legacy when VirtualPage is not installed; positive lifecycle remains to be verified on a VirtualPage-enabled test site.
- Live element mutation parity: PASS for create/update/dry-run delete/delete across all 7 element types.
- Live element file mutation parity: PASS for make_static + DB/static line editing across chunk/snippet/template/plugin, with test files removed afterwards.
- Element list/get live parity matrix: PASS for all 7 supported element types.
- Element view live parity matrix: PASS for all 4 supported viewable element types.
- Filesystem media-source disabled-security contract: PASS through the full parity run.

## MODX 3

Live-tested on a dedicated MODX 3.2.4-pl sandbox. The installed modular Runtime
matches the current source tree for the validated registry.

Status: LIVE-VALIDATED.

## MODX 2

- release-stage builds successfully;
- static PHP compatibility test passes;
- platform mapping test passes, including `modTemplateVarResource` mapping;
- build overlay is present.

No dedicated live MODX2 sandbox has been attached yet.

Status: STATICALLY VALIDATED, LIVE TEST PENDING.
