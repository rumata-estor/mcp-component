**English** | [Русский](BUILD-ARCHITECTURE.ru.md)

# Build architecture

One connector source tree produces two platform-specific release artifacts:

- **MODX Revolution 2.8.x** — platform key `modx2`, transport package `modxmcp-<version>-pl.transport.zip`;
- **MODX Revolution 3.x** — platform key `modx3`, transport package `modx3mcp-<version>-pl.transport.zip`.

Both artifacts use the same MCP client, the same public action contract, and the same modular runtime under `core/components/modxmcp/src/`.

Shared payload:

- `core/components/modxmcp/src/`;
- tools, registry, extras, endpoint logic and documentation;
- platform-neutral assets and manager logic where MODX 2 and MODX 3 behave the same.

Platform-specific build/bootstrap files live under `_build/platform/<platform>/`. `_build/prepare-release.py` creates a clean staging tree for the selected platform and applies only the required overlay.

The architecture deliberately avoids scattering `if (MODX_VERSION...)` checks through domain tools. MODX-version differences are isolated at bootstrap/build boundaries and behind `PlatformInterface`.

## Preparing a release tree

MODX 3:

```bash
python3 _build/prepare-release.py --platform modx3 --output /tmp/modxmcp-modx3
```

MODX 2:

```bash
python3 _build/prepare-release.py --platform modx2 --output /tmp/modxmcp-modx2
```

Build each transport package on a MODX installation of the matching major version.

## Version 1.1.0 status

Version 1.1.0 is the first shared-source release line for MODX 2 and MODX 3.

- modular action coverage: **182/182**;
- MODX 3.2.4-pl: full live regression completed;
- MODX 3 processor compatibility: checked against 3.2.2-pl, 3.2.4-pl and current 3.x;
- MODX 2.8.9-pl: dedicated transport release-smoke and core live regression completed;
- both platform live-validation gates are complete; the final artifacts/checksums were verified and **v1.1.0 is published**.
