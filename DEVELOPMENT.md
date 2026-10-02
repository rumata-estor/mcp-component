**English** | [Русский](DEVELOPMENT.ru.md)

# MODX MCP — development and maintenance guide

This document is for people who want to **run, maintain, or extend MODX MCP**.

It is written so that an experienced developer can quickly understand the project architecture, extension points, constraints, and test flow. A less experienced user should still be able to understand the overall structure and see what needs attention, but this guide does not replace practical knowledge of PHP, Node.js, MODX, and server administration.

For a normal installation, the published release and MCP client configuration are usually enough. Development, server-side changes, security settings, production upgrades, and unusual failures require more technical experience. If, after reading the relevant section, you still cannot clearly explain what will change and how you will verify it, do not experiment on a live site. Use a test environment or involve someone with the right experience.


MODX MCP is designed for **MODX Revolution 3.x**. Instructions written for the old MODX 2 line must not be copied here mechanically: MODX 3 changed PHP class names, namespaces, core bootstrap, and the location and resolution of built-in processors.

> **The main rule of the project: do not give AI the widest possible access. Give it the correct, limited, and verifiable access to MODX objects.**

## 1. How the system works

MODX MCP has two main parts.

The first part is installed **on the MODX site**. It is a PHP component that works with resources, templates, chunks, snippets, TVs, settings, extras, and other MODX objects.

The second part runs **next to the program where the AI is working**. It is a local MCP server written in Node.js. It exposes tools to the AI and forwards the selected operation to the site.

A simplified flow looks like this:

```text
AI or MCP-enabled application
      ⇅
local MCP server on Node.js
client/index.js
      ⇅ HTTPS + API token
site API
assets/components/modxmcp/api.php
      ⇅
main MODX MCP logic
core/components/modxmcp/model/modxmcp.class.php
      ⇅
MODX Revolution 3
```

The important point is that the AI does not need to edit MODX database tables or arbitrary files directly. It asks for a meaningful operation such as "get this chunk", "find where this TV is used", or "update this resource". The server side then performs that operation through MODX.

The component also has its own pages in the MODX manager, including settings and the dependency graph.

## 2. What you need for a normal setup

If you only want to use MODX MCP, you do not need to understand the whole codebase.

The basic setup is:

1. Install the MODX MCP transport package on the site.
2. Get the automatically generated API token.
3. Configure an MCP-enabled application to start the Node.js part of MODX MCP.
4. Give it the site API URL and the API token.
5. Start with read-only operations.
6. Enable write or dangerous operations only when they are actually needed.

For stable use, prefer a specific release such as `v1.0.1` instead of the current development branch.

Example MCP client configuration:

```json
{
  "mcpServers": {
    "modx": {
      "command": "npx",
      "args": [
        "-y",
        "github:rumata-estor/modx3-mcp#v1.1.0"
      ],
      "env": {
        "MODX_MCP_SITE_URL": "https://example.com/assets/components/modxmcp/api.php",
        "MODX_MCP_TOKEN": "your-token"
      }
    }
  }
}
```

The local part requires **Node.js 18 or newer**.

After connecting, start with a safe task such as showing the project structure, reading a known element, or building a dependency graph.

## 3. Main parts of the repository

This section is a quick map of the codebase.

### `client/index.js`

The local MCP server on Node.js.

It contains:

- tool definitions shown to the AI;
- requests to the site API;
- read/write classification;
- automatic backups before some write operations;
- a lock that prevents two processes from changing the same project at the same time;
- the external audit handler;
- extra restrictions for dangerous operations.

### `assets/components/modxmcp/api.php`

The HTTP API on the MODX site.

It:

- requires HTTPS by default;
- checks the API token;
- limits request size;
- accepts commands from the local MCP server;
- passes them to the main server-side model.

### `core/components/modxmcp/model/modxmcp.class.php`

The main server-side logic.

It contains:

- the registry of supported actions;
- work with MODX objects;
- calls to built-in MODX processors;
- xPDO operations;
- integrations with supported extras;
- transactions;
- the internal audit log.

### MODX manager interface

These files implement the component UI in the MODX manager:

```text
assets/components/modxmcp/connector.php
assets/components/modxmcp/js/
core/components/modxmcp/controllers/
core/components/modxmcp/processors/mgr/
core/components/modxmcp/templates/
```

They include the settings screen and dependency graph.

### Built-in help for AI

```text
core/components/modxmcp/docs/
```

These documents are available through MCP help and guide the model toward the right tools.

### The `_build/` directory

This directory contains build, installation, and release checks.

Important files:

- `build.config.php` — transport package name and version;
- `build.transport.php` — transport package builder;
- `install.headless.php` — command-line installation without Package Manager;
- `install.transport.php` — transport install/uninstall helper used by release tests;
- `release.smoke.sh` — full release verification cycle;
- `smoke.endpoint.php` — API and lifecycle checks;
- `test.release-portability.py` — portability and security checks;
- `test.client-server-actions.py` — client/server action consistency;
- `test.modx3-processors.php` — compatibility check for built-in MODX 3 processors;
- `data/transport.settings.php` — component system settings;
- `resolvers/` — install, update, and uninstall actions.

## 4. Things that must stay in sync

Several parts of the project must always match each other.

### Server actions

The main registry of server capabilities is:

```php
modxMCP::actionRegistry()
```

A new server operation must be registered there.

### Tools exposed to the AI

They are defined in:

```text
client/index.js → toolDefinitions
```

For example, the tool `modx_example_action` normally maps to the server action `example_action`.

An automated test checks that the Node.js tools and PHP server actions do not drift apart.

Version 1.1.0 keeps a 182-action public server contract, and all 182 actions are registered in the modular Runtime.

### Project version

For 1.1.0 and later, the release version must stay aligned across the shared source and both platform release templates:

- `_build/build.config.php` → MODX 3 `PKG_VERSION`;
- `_build/platform/modx2/overlay/_build/build.config.php` → MODX 2 `PKG_VERSION`;
- `package.json` → `version`;
- `package-lock.json` root package version;
- `core/components/modxmcp/model/modxmcp.class.php` → shared/MODX 3 `modxMCP::VERSION`;
- `core/components/modxmcp/legacy/modx2/modxmcp.class.php` → MODX 2 fallback `modxMCP::VERSION`;
- the version substituted by `_build/prepare-release.py`.

CI and release-staging checks must fail when these values drift.

### System settings

Normal installation through the transport package and command-line installation must create **the same system settings**.

The project currently compares all 16 `modxmcp.*` settings, including default value, field type, and settings area.

If you add a setting, add it to both installation methods.

## 5. Platform architecture: MODX 2 and MODX 3

Version 1.1.0 uses one shared modular Runtime for MODX Revolution 2.8.x and 3.x.

The shared Tool classes must not depend directly on one MODX major version. Platform differences are isolated behind `PlatformInterface` and the release/bootstrap boundary:

- `Platform/Modx2Platform.php`;
- `Platform/Modx3Platform.php`;
- `_build/platform/modx2/`;
- `_build/platform/modx3/`.

MODX 2 and MODX 3 differ in class names, bootstrap, manager controllers, processor routing and transport APIs. Do not add ad-hoc version checks to domain tools when a platform mapping can express the difference.

Architecture checks:

```bash
python3 _build/test.architecture.py
python3 _build/test.build-architecture.py
python3 _build/test.modx2-php-compat.py
python3 _build/test.release-staging.py
```

MODX 3 processor compatibility is additionally checked against 3.2.2-pl, 3.2.4-pl and the current 3.x branch:

```bash
php _build/test.modx3-processors.php /path/to/modx/core/src/Revolution/Processors
```

If you are unsure which class, processor or field exists on a target platform, read that MODX version's source instead of guessing.

## 6. How to add a new capability

Even if you are not a developer, this section is useful because it shows the required parts of a correct project extension.

Before changing code, answer four questions:

1. What exactly should the new operation do in MODX?
2. Does it only read data, or does it change the site?
3. Is there already a MODX processor or extra processor that performs this operation?
4. What protection is needed: backup, preview, a no-write test run, or explicit confirmation?

### Step 1. Add the server action

Add the new action to the right group in `actionRegistry()`.

Example:

```php
'my_action' => 'myAction',
```

Then implement `myAction()`.

Do not invent a new dispatch mechanism if the existing registry can already call the required method or processor.

### Step 2. Choose the correct way to work with MODX

Use this order of preference.

**1. Use a built-in MODX processor.**

This is the best option when MODX already supports the operation. It keeps MODX validation, events, and the standard object lifecycle.

**2. Use a processor from an installed extra.**

Use this when the operation belongs to something such as miniShop2 and its processor can safely run without the manager UI.

**3. Work directly with an xPDO object.**

This is the fallback option. Use it when there is no suitable processor, or when the processor depends on manager UI state and cannot run properly through the API.

With direct xPDO access, our code becomes responsible for input validation and data integrity.

If you need to inspect third-party source code, you can temporarily place a copy under `_reference/`. That directory is ignored by Git and is never included in the release package.

### Step 3. Add the MCP tool

In `client/index.js`, add the new tool to `toolDefinitions`.

The usual naming pattern is:

```text
modx_my_action
```

and the server receives:

```text
my_action
```

The common handler removes the technical `modx_` prefix and sends the rest of the name to the server.

The tool description matters. The AI uses that text to decide when the tool should be called.

A good description should state:

- what the tool does;
- when to use it;
- which parameters are required;
- whether it changes the site;
- whether the result can be previewed first;
- whether explicit confirmation is required.

### Step 4. Mark the operation correctly as read-only or write

MODX MCP separates:

- read-only operations;
- operations that change the site.

This affects project locking, backups, and audit logging.

A new read-only action must not accidentally be treated as a write action.

A new write action must not be incorrectly classified as safe reading.

After adding a tool, check the relevant classification in `client/index.js`.

### Step 5. Add the right protection

For a write operation, decide whether it needs:

- a backup;
- a preview before applying changes;
- a no-write test mode;
- `confirm=true`;
- a separate opt-in environment variable;
- or a dedicated safe workflow instead of direct execution.

Do not copy safety logic mechanically from another operation. The protection should match the real risk of the specific action.

## 7. Security rules that must not be weakened by accident

The current safe defaults are checked automatically.

Important rules:

- the API requires HTTPS by default;
- `X-Forwarded-Proto` is not trusted unless the administrator explicitly enables it for a controlled proxy;
- API tokens are created with `random_bytes()`;
- token comparison is done safely;
- tokens cannot be passed in the URL query string;
- arbitrary MODX processor execution is disabled by default;
- unrestricted root filesystem reading is disabled by default;
- automatic conversion to static elements is disabled by default;
- debug mode is disabled by default;
- the service user must be an active MODX user with `sudo`;
- when `service_user_id=0`, the component selects an active sudo user automatically;
- capability checks happen before the operation is dispatched.

If a change weakens any of these rules, treat it as a security-model change, not as a routine refactor.

## 8. Automatic backups and project locking

The Node.js side adds another safety layer for write operations.

For full use of this layer, it is best to configure:

```text
MODX_MCP_SITE_ID
MODX_MCP_MANAGER_ROOT
```

`MODX_MCP_SITE_ID` is a readable identifier for the site.

`MODX_MCP_MANAGER_ROOT` is a local service directory for the project.

It stores:

- `modx-backups/` — automatic backups for supported changes;
- `project.lock` — a lock that prevents two processes from changing the same project at the same time.

By default, up to 20 backup directories are kept.

You can change this with:

```text
MODX_MCP_BACKUP_KEEP_COUNT
```

This setting:

```text
MODX_MCP_SKIP_AUTO_BACKUP=1
```

disables the Node.js backup layer. This is not recommended on a live site without a clear reason.

If `MODX_MCP_MANAGER_ROOT` is not configured, local locking and some automatic backups cannot work. Server-side checks and transactions still exist, but the overall safety level is lower.

## 9. Separate permissions for dangerous operations

Some operations are intentionally blocked until the operator enables them explicitly.

These permissions use environment variables with names like:

```text
MODX_MCP_ALLOW_...
```

Separate opt-ins exist, among other things, for:

- direct creation of some elements;
- direct plugin creation;
- deletion of TVs, chunks, snippets, templates, resources, and plugins;
- bulk resource changes;
- emptying the recycle bin;
- package installation and uninstall;
- transport provider changes.

Do not enable these "just in case".

The correct process is: understand the exact task and risk first, then enable only the required permission.

## 10. Audit logging

The project has two independent audit mechanisms.

### Internal component log

The system setting:

```text
modxmcp.audit_log
```

is enabled by default.

The server records information about executed actions in its own component log.

### External audit handler

The Node.js side supports:

```text
MODX_MCP_AUDIT_HOOK
```

Set this to the path of a local program.

After a successful write operation, MODX MCP starts that program and sends operation data as JSON through standard input.

The payload may include:

- MCP tool name;
- arguments;
- result;
- site identifier;
- actor information;
- paths to automatic backups.

The program is started directly, without a shell.

If no external handler is configured, normal operation is unchanged.

If the handler fails, MODX MCP reports a warning but does not pretend that an already completed site change failed or rolled back.

## 11. Installing from source

Normal users should prefer a platform-specific transport package from a release. Source installation is mainly for development and automation.

Create a clean platform tree first.

MODX 3:

```bash
python3 _build/prepare-release.py --platform modx3 --output /tmp/modxmcp-modx3
cd /tmp/modxmcp-modx3
MODX_CONFIG_CORE=/full/path/to/config.core.php php _build/install.headless.php
```

MODX 2:

```bash
python3 _build/prepare-release.py --platform modx2 --output /tmp/modxmcp-modx2
cd /tmp/modxmcp-modx2
MODX_CONFIG_CORE=/full/path/to/config.core.php php _build/install.headless.php
```

The source repository itself defaults to the MODX 3 build. The release-preparation step applies the matching API wrapper, fallback model, manager overlay and build/install scripts, then removes source-only platform templates and legacy snapshots.

If a configuration depends on `DOCUMENT_ROOT`, set `MODX_DOCUMENT_ROOT` explicitly where supported.

## 12. Building the transport packages

One source version produces two transport packages.

Prepare a staging tree for the target platform, then run its builder on a MODX installation of the same major version.

Expected 1.1.0 artifacts:

```text
MODX 2.8.x: modxmcp-1.1.0-pl.transport.zip
MODX 3.x:   modx3mcp-1.1.0-pl.transport.zip
```

Example for MODX 3:

```bash
python3 _build/prepare-release.py --platform modx3 --output /tmp/modxmcp-modx3
cd /tmp/modxmcp-modx3
MODX_CONFIG_CORE=/full/path/to/config.core.php php _build/build.transport.php
```

Use `--platform modx2` and a MODX 2.8.x build installation for the MODX 2 artifact.

Do not build one platform's package on the other MODX major version. The transport requirements intentionally reject that mismatch.

## 13. Checks after development changes

A small change does not require a full install/uninstall cycle immediately. Start with the relevant source checks.

### JavaScript syntax

```bash
node --check client/index.js
```

### Client/server consistency

```bash
python3 _build/test.client-server-actions.py
```

This checks that every Node.js MCP tool has a corresponding server action and vice versa.

### Portability and security checks

```bash
python3 _build/test.release-portability.py
```

Among other things, this checks:

- required files;
- version consistency;
- identical system settings for transport and command-line installation;
- safe default settings;
- secure API-token generation;
- service-user rules;
- capability enforcement;
- cleanup after uninstall;
- CLI-only restrictions;
- HTTPS rules;
- staged file deployment and restoration on failure;
- absence of paths and addresses tied to one private test server.

### PHP syntax

Run this for each changed PHP file:

```bash
php -l path/to/file.php
```

Before a release, all PHP files in the project are checked.

### Platform compatibility checks

If built-in MODX processor routes were changed:

```bash
php _build/test.modx3-processors.php /path/to/modx/core/src/Revolution/Processors
```

## 14. Automated checks on GitHub

The workflow is defined in:

```text
.github/workflows/ci.yml
```

GitHub Actions checks:

- PHP and Node.js syntax;
- architecture and platform staging;
- MODX 2 PHP/static compatibility;
- the complete 182-action client/server contract;
- modular migration coverage (74/74 reads + 108/108 mutations);
- release portability and secure defaults;
- version consistency across shared and platform-specific release metadata;
- changelog coverage for the current version;
- MODX 3 processor compatibility with 3.2.2-pl, 3.2.4-pl and current 3.x.

Static CI does not replace live release-smoke on a real installation of each supported MODX major version.

## 15. Full release verification

Full release smoke changes the installed component state: installation, test operations, reinstall, uninstall and final reinstall. Never run it on production.

Prepare the platform tree first, then execute the smoke script on a dedicated test site of the same MODX major version.

MODX 3 example:

```bash
python3 _build/prepare-release.py --platform modx3 --output /tmp/modxmcp-modx3
cd /tmp/modxmcp-modx3
MODX_CONFIG_CORE=/full/path/to/config.core.php bash _build/release.smoke.sh
```

MODX 2 uses the same process with `--platform modx2` and the MODX 2 release overlay/smoke runner.

A release is ready only when the required static checks pass and the platform artifact has completed its dedicated live smoke. For 1.1.0, MODX 3.2.4-pl is already fully live-validated; the MODX 2 live smoke is the remaining release gate.

## 16. Updating the version

Before a new release, update the version in every shared and platform-specific location listed in the Project version section.

Then:

1. update both `CHANGELOG.md` and `CHANGELOG.ru.md`;
2. update English and Russian public documentation when compatibility or installation changes;
3. run the complete static/architecture/release-staging suite;
4. prepare both platform release trees;
5. run the required live release-smoke on dedicated MODX 2 and MODX 3 test installations;
6. inspect both resulting transport packages and checksums;
7. only then create the Git tag and publish the GitHub release.

Do not publish a release from a version bump alone.

## 17. Maintaining an installed system

If you are not developing the code and only maintain an installed MODX MCP setup, focus on a few things.

### Local and server parts should use the same version

The Node.js side compares its own version with the site API version and warns if they differ.

On production, use a fixed release:

```text
github:rumata-estor/modx3-mcp#v1.1.0
```

instead of a development branch.

### After an upgrade, test reading first

Start with safe read-only checks:

- verify version and system information;
- read several known elements;
- verify the project structure;
- build a dependency graph.

Only after that should you move to write operations.

### The existing API token should be preserved

Both installation methods are designed to preserve existing system settings and the API token during reinstall and upgrade.

### If the site is behind a reverse proxy

By default, MODX MCP does not trust `X-Forwarded-Proto`.

Enable:

```text
modxmcp.trust_proxy_https
```

only when the proxy is under your control and correctly reports the original HTTPS protocol.

## 18. Common errors

### `401 Unauthorized`

Usually the `MODX_MCP_TOKEN` is wrong, or the client is pointing to the wrong site.

### `HTTPS required`

The API sees the request as HTTP.

Check real HTTPS and reverse-proxy configuration. Do not disable the HTTPS requirement just to hide the underlying problem.

### `No active sudo MODX user found`

With `service_user_id=0`, the component could not find an active MODX user with `sudo`.

Create or activate a suitable user, or set a valid `modxmcp.service_user_id`.

### `Capability ... is disabled`

The required capability group is disabled in MODX MCP settings.

Enable it only if the current task really needs it.

### `PROJECT BUSY`

Another process is already performing a write operation and holds the project lock.

Do not delete `project.lock` blindly. First make sure the other process has really finished.

### Version mismatch warning

The local Node.js part and the site component are on different versions.

Bring both sides to the same release.


## 19. What a developer should verify before a serious change

Before any significant change, the following should be clear:

- which MODX object will change;
- which standard MODX mechanism will perform the change;
- why that mechanism was chosen;
- whether the operation only reads data or modifies the site;
- what happens on failure;
- whether a backup is required;
- whether the change can be previewed before writing;
- how the operation behaves when run again;
- which automated checks verify it;
- whether any safe default is weakened;
- whether the feature is installed consistently through both the transport package and command-line installer.

If these questions cannot be answered clearly, the change is not ready.

## 20. What is not part of MODX MCP

The Telegram bot, external server scripts, and internal `AGENT.md` used in our own agent environment are not required parts of MODX MCP.

The project should remain a standalone MCP server and MODX component.

It can be connected to any compatible MCP application or agent environment.

## 21. Minimum checks before a Pull Request or release

Basic commands:

```bash
node --check client/index.js
python3 _build/test.client-server-actions.py
python3 _build/test.release-portability.py
bash -n _build/release.smoke.sh
```

Changed PHP files must also be checked with `php -l`.

If built-in MODX 3 processor routing changed, run the processor compatibility test.

If installation, upgrade, uninstall, system settings, API behaviour, write operations, or security changed, source checks are not enough. Use a separate MODX 3 test installation and run the full release cycle.

> **Never use a production site as a test bench for a new installer, component uninstall, or a new destructive workflow.**
