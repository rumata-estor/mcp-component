# Build architecture

One connector source tree produces two platform artifacts:

- MODX 2: `modx2`
- MODX 3: `modx3`

Shared payload:

- `core/components/modxmcp/src/`
- shared tools, registry, extras and documentation
- shared assets where the Manager/API code is platform-neutral

Platform-specific build/bootstrap lives only under `_build/platform/<platform>/`.

The goal is not a runtime `if (MODX_VERSION...)` spread through the codebase. Platform differences are resolved at bootstrap/build boundaries and through `PlatformInterface`.

Current production builder remains untouched until the new matrix passes regression tests.
