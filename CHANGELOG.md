**English** | [Русский](CHANGELOG.ru.md)

# MODX MCP — Changelog

## 1.1.0 (2026-10-02)

Major architecture release preparing the project as a shared MODX 2 / MODX 3 codebase.

### MODX 2 and MODX 3 support

- Added a shared modular Runtime with platform adapters for MODX Revolution 2.8.x and 3.x.
- One source tree now prepares two platform-specific release artifacts: `modxmcp-1.1.0-pl.transport.zip` for MODX 2.8.x and `modx3mcp-1.1.0-pl.transport.zip` for MODX 3.x.
- The Node.js MCP client and public MCP action contract are shared across both MODX generations.
- MODX-version differences are isolated behind platform adapters and release overlays instead of being spread through domain tools.
- The visible component name is now **MODX MCP**. Existing technical identifiers such as the `modxmcp` namespace, repository URL, and Node package name are retained for backward compatibility.

### Complete modular migration

- All **182/182** server actions now execute through the modular Runtime.
- Read-only migration is complete: **74/74** actions.
- Mutation/write migration is complete: **108/108** actions.
- Domain modules now cover elements/resources, contexts, ACL, property sets, media, packages/providers, MIGX, VirtualPage, VersionX, miniShop2, system settings, TV values, bulk operations and maintenance actions.
- The legacy dispatcher remains only as a compatibility/reference layer.

### Validation and release engineering

- Added platform release staging via `_build/prepare-release.py --platform modx2|modx3`.
- MODX 3.2.4-pl passed the full live regression matrix, including read parity and domain-specific mutation parity suites.
- MODX 3 processor compatibility continues to be checked against 3.2.2-pl, 3.2.4-pl and current 3.x.
- MODX 2.8.9-pl passed the dedicated transport release-smoke and core live regression suite: 74 read-only parity actions, 46 processor-mutation checks, all 7 element lifecycles, media 9/9, property sets 5/5, package operations 5/5 and the remaining core mutation suites.
- MODX 3.2.4-pl was re-validated after the shared MODX 2 fixes; the release-smoke and all 14 live parity suites completed successfully, including MIGX 3/3 and miniShop2 12/12.
- Fixed modular element-type bridging for create/update/delete requests and the MODX 2 absolute-path requirement of `removeContainer()` in `delete_media_folder`.
- Release/architecture checks now run correctly from prepared platform trees after source-only `legacy` and `_build/platform` templates have been removed.
- Hardened the MODX 2 release path to the same security baseline as MODX 3: the transport builder is CLI-only, no API token is accepted through a URL, package files use explicit `0644`/`0755` permissions, stale package staging is removed, same-signature installed packages block rebuilding, and release smoke preserves/restores the original `modxmcp.*` settings.
- Removed the weak MODX 2 fallback token generator and aligned fallback transaction rollback and `bulk_resources set_template` validation with the shared runtime.
- CI now treats the modular 182-action contract and both platform build variants as release invariants.

## 1.0.1 (2026-09-29)

Patch release fixing empty-template assignment in bulk resource operations.

### Fixed

- `modx_bulk_resources` with `operation: "set_template"` now accepts `template: 0`, which is the valid MODX value for a resource with no assigned template.
- Server-side validation now distinguishes a missing parameter from the integer `0` and rejects only absent, null, empty, non-integer, or negative template values.
- The MCP tool schema now declares `template` as an integer with `minimum: 0`, so clients and agents can pass the empty-template value correctly.
- Release regression checks now guard the server validation and client schema against reintroducing the `template=0` bug.
- Transport upgrades now recover when an earlier install left MODX3 MCP files without the owner-write bit; package-owned files/directories are installed with explicit `0644`/`0755` permissions.
- The transport verifier now checks that the deployed model version actually matches the package version, and endpoint smoke tests support `MODX_MCP_SMOKE_SITE_URL` for test hosts whose MODX `site_url` points elsewhere.

## 1.0.0 (2026-09-27)

The first stable release of the **MODX3 MCP** line for MODX Revolution 3.x, based on the original [**modxMCP**](https://github.com/dampilov94/mcp-component) project by [**dampilov94**](https://github.com/dampilov94).

Version 1.0.0 is not a simple port of the original component to MODX 3. The project was substantially reworked: MODX bootstrap and routing, installation and upgrade flow, the security model, service-user handling, write operations, auditing, backups, and automated release checks were all changed. MODX 3-specific updates — namespaces, MODX/xPDO classes, processor routes, and installation paths — are listed below.

The migration was not done by mechanically replacing class names and paths. The implementation was adapted to how MODX 3 actually behaves: core bootstrap, namespaces, processors, permissions, the service user, package installation, transactions, and rollback. For critical parts, we tested not only whether the code runs, but whether it behaves predictably during installation, reinstall, failure, and uninstall.

### Migration to MODX Revolution 3

- The project now has its own identity: **MODX3 MCP**, package **MODX3MCP**, and Node.js package `modx3-mcp`. The internal `modxmcp` namespace is kept for compatibility.
- The server side now uses native MODX Revolution 3 bootstrap, MODX/xPDO namespaces, and MODX 3 processor routing.
- Supported versions are limited to **MODX Revolution >= 3.0.0 and < 4.0.0**.
- Processor compatibility is checked against MODX Revolution 3.2.2-pl, 3.2.4-pl, and the current 3.x branch.

### Installation and upgrades

- The transport package and command-line installer were made portable and no longer depend on one specific site layout.
- The command-line installer creates and updates manager menu entries while preserving existing settings and the API token.
- New files are prepared in temporary directories first. Live directories are replaced only after preparation succeeds; on failure, the previous version is restored.
- Namespace, menu, settings, and token changes are handled in one xPDO transaction and are rolled back together with the files if installation does not complete.
- Transport-package uninstall removes manager menu entries, `modxmcp.*` settings, the namespace, and component files.
- A parity check was added for transport and command-line installation. It compares all 16 `modxmcp.*` definitions, including default value, field type, and settings area.
- The transport-package builder is now CLI-only.

### Security

- `service_user_id=0` now selects only an active MODX user with `sudo` permission. The old dependency on user ID 1 was removed.
- A manually configured service user must also be active and have `sudo` permission.
- `auto_static` is disabled by default.
- HTTPS is required by default. Trusting `X-Forwarded-Proto` requires an explicit opt-in setting.
- API tokens are generated only with `random_bytes()`; weaker fallback methods were removed.
- The API token can no longer be passed in the query string, so it does not leak into URLs, access logs, or browser history.
- HTTPS checks happen before the health GET response; request-body size is checked before and after reading the body.
- The transaction helper rolls back on any `Throwable`.
- Manager screens require the `settings` permission; `{core_path}` handling in the manager connector was fixed.
- `clear_tv_values` requires explicit confirmation, and the client creates a safety backup first.

### TV values and write operations

- Server actions `list_tv_values` and `clear_tv_values` were added for safer work with explicitly stored TV values.
- Read/write classification was expanded: `list/get/search/read/view/check/describe/find/suggest` and dedicated read-only actions no longer take the project write lock or create unnecessary audit events.
- Write operations still use the project lock to reduce the risk of conflicting parallel changes.

### Auditing

- The client supports the optional `MODX_MCP_AUDIT_HOOK`.
- After a successful write operation, a trusted external program can receive JSON over standard input with operation data and paths to automatically created safety backups.
- If no hook is configured, normal client behaviour does not change.
- If the hook fails, the client reports a warning but does not turn an already successful site change into an error.

### Release checks

- Automated checks were added for installation portability and for consistency between client tools and server actions.
- A full release workflow was added with `_build/release.smoke.sh` and `_build/smoke.endpoint.php`.
- The workflow checks PHP syntax, build, installation, API access, CRUD operations, reinstall with settings preserved, clean uninstall, and a final install with read-only checks.
- The API token is not printed in test logs.
- Before 1.0.0 was released, a separate clean installation was tested on MODX Revolution 3.2.4-pl, including the MCP client, changes to test objects, reinstall with settings preserved, and complete component removal.

## Original modxMCP feature history

The original feature history below is kept in English from the [**modxMCP**](https://github.com/dampilov94/mcp-component) project by [**dampilov94**](https://github.com/dampilov94).

This section belongs to the original project line on which MODX3 MCP is based. The original wording is preserved so that technical details and release-history phrasing are not changed by translation.

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

