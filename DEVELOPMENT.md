**English** | [Русский](DEVELOPMENT.ru.md)

# MODX3 MCP — development and maintenance guide

This document is for people who want to **run, maintain, or extend MODX3 MCP**.

It is written so that an experienced developer can quickly understand the project architecture, extension points, constraints, and test flow. A less experienced user should still be able to understand the overall structure and see what needs attention, but this guide does not replace practical knowledge of PHP, Node.js, MODX, and server administration.

For a normal installation, the published release and MCP client configuration are usually enough. Development, server-side changes, security settings, production upgrades, and unusual failures require more technical experience. If, after reading the relevant section, you still cannot clearly explain what will change and how you will verify it, do not experiment on a live site. Use a test environment or involve someone with the right experience.


MODX3 MCP is designed for **MODX Revolution 3.x**. Instructions written for the old MODX 2 line must not be copied here mechanically: MODX 3 changed PHP class names, namespaces, core bootstrap, and the location and resolution of built-in processors.

> **The main rule of the project: do not give AI the widest possible access. Give it the correct, limited, and verifiable access to MODX objects.**

## 1. How the system works

MODX3 MCP has two main parts.

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
main MODX3 MCP logic
core/components/modxmcp/model/modxmcp.class.php
      ⇅
MODX Revolution 3
```

The important point is that the AI does not need to edit MODX database tables or arbitrary files directly. It asks for a meaningful operation such as "get this chunk", "find where this TV is used", or "update this resource". The server side then performs that operation through MODX.

The component also has its own pages in the MODX manager, including settings and the dependency graph.

## 2. What you need for a normal setup

If you only want to use MODX3 MCP, you do not need to understand the whole codebase.

The basic setup is:

1. Install the MODX3 MCP transport package on the site.
2. Get the automatically generated API token.
3. Configure an MCP-enabled application to start the Node.js part of MODX3 MCP.
4. Give it the site API URL and the API token.
5. Start with read-only operations.
6. Enable write or dangerous operations only when they are actually needed.

For stable use, prefer a specific release such as `v1.0.0` instead of the current development branch.

Example MCP client configuration:

```json
{
  "mcpServers": {
    "modx": {
      "command": "npx",
      "args": [
        "-y",
        "github:rumata-estor/modx3-mcp#v1.0.0"
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

In version 1.0.0, the server registry contains 182 actions.

### Project version

The version number must match in four places:

- `_build/build.config.php` → `PKG_VERSION`;
- `package.json` → `version`;
- `package-lock.json`;
- `modxMCP::VERSION`.

If the values do not match, automated checks should fail.

### System settings

Normal installation through the transport package and command-line installation must create **the same system settings**.

The project currently compares all 16 `modxmcp.*` settings, including default value, field type, and settings area.

If you add a setting, add it to both installation methods.

## 5. What is specific to MODX 3

Moving from MODX 2 to MODX 3 is not just a matter of renaming a few files.

MODX 3 changed:

- full PHP class names;
- namespaces;
- core bootstrap;
- locations of built-in processors;
- how some processors are resolved and called.

This means an old MODX 2 processor path cannot simply be copied into MODX3 MCP and assumed to work.

The project contains dedicated logic that maps the internal processor route to the real MODX 3 processor class and runs it through `runProcessor()`.

A separate test checks that all referenced processors actually exist:

```bash
php _build/test.modx3-processors.php /path/to/modx/core/src/Revolution/Processors
```

On GitHub, this is checked against MODX 3.2.2-pl, 3.2.4-pl, and the current 3.x branch.

If you are not sure which class, processor, or field to use, the correct next step is to **read the current MODX or extra source code**, not guess.

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

MODX3 MCP separates:

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

After a successful write operation, MODX3 MCP starts that program and sends operation data as JSON through standard input.

The payload may include:

- MCP tool name;
- arguments;
- result;
- site identifier;
- actor information;
- paths to automatic backups.

The program is started directly, without a shell.

If no external handler is configured, normal operation is unchanged.

If the handler fails, MODX3 MCP reports a warning but does not pretend that an already completed site change failed or rolled back.

## 11. Installing from source

Normal users should prefer the ready-made transport package from a release.

Direct installation from source is mainly for development, automation, and servers managed by an agent.

Run:

```bash
php _build/install.headless.php
```

If `config.core.php` cannot be found automatically:

```bash
MODX_CONFIG_CORE=/full/path/to/config.core.php php _build/install.headless.php
```

If the MODX configuration depends on `DOCUMENT_ROOT`, set the site root explicitly:

```bash
MODX_DOCUMENT_ROOT=/full/path/to/site \
MODX_CONFIG_CORE=/full/path/to/config.core.php \
php _build/install.headless.php
```

This installer is CLI-only and accepts only MODX Revolution 3.x.

During installation, new `core/components/modxmcp` and `assets/components/modxmcp` trees are prepared separately first.

The live directories are replaced only after preparation succeeds.

If installation does not complete, the previous files can be restored.

Namespace, menu, system-setting, and token changes are performed inside a database transaction so they can be rolled back together with the file deployment if the installation fails.

## 12. Building the transport package

The builder runs only from the command line:

```bash
MODX_CONFIG_CORE=/full/path/to/config.core.php \
php _build/build.transport.php
```

The finished package is written to the MODX directory:

```text
core/packages/
```

The builder must not be run through the browser.

It also refuses to build the same package signature on a MODX installation where that package is already installed. This protects the installed package data from accidental damage.

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

### MODX 3 processor compatibility

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

- PHP syntax;
- Node.js syntax;
- shell syntax of the release runner;
- portability and core security rules;
- client/server action consistency;
- version consistency;
- a changelog entry for the current version;
- built-in processor compatibility with MODX 3.2.2-pl, 3.2.4-pl, and the current 3.x branch.

These checks are useful, but they do not replace understanding what changed.

## 15. Full release verification

The file:

```text
_build/release.smoke.sh
```

runs the full release verification cycle.

It **changes the installed component state**: installs it, performs test operations, reinstalls it, removes it, and installs it again.

Therefore:

> **Never run the full release cycle on a production site. Use a separate MODX 3 test installation.**

Run:

```bash
MODX_CONFIG_CORE=/full/path/to/config.core.php \
bash _build/release.smoke.sh
```

The script:

1. checks PHP syntax;
2. runs source-level regression checks;
3. checks the Node.js client;
4. builds the transport package;
5. saves current settings from the test installation;
6. installs the package;
7. checks the API and basic create/read/update/delete operations;
8. reinstalls the package and verifies that user settings are preserved;
9. removes the package completely;
10. installs it again;
11. restores the original settings;
12. performs a final read-only check.

For a preliminary check without the install/uninstall cycle:

```bash
bash _build/release.smoke.sh --preflight-only
```

## 16. Updating the version

Before a new release, update the version consistently in all sources listed above.

Then:

1. update the changelog;
2. run source checks;
3. run the full release verification on a separate MODX 3 installation;
4. inspect the resulting transport package;
5. only then create the Git tag and GitHub release.

Do not update only `package.json`. The automated checks are designed to catch that mismatch.

## 17. Maintaining an installed system

If you are not developing the code and only maintain an installed MODX3 MCP setup, focus on a few things.

### Local and server parts should use the same version

The Node.js side compares its own version with the site API version and warns if they differ.

On production, use a fixed release:

```text
github:rumata-estor/modx3-mcp#v1.0.0
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

By default, MODX3 MCP does not trust `X-Forwarded-Proto`.

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

The required capability group is disabled in MODX3 MCP settings.

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

## 20. What is not part of MODX3 MCP

The Telegram bot, external server scripts, and internal `AGENT.md` used in our own agent environment are not required parts of MODX3 MCP.

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
