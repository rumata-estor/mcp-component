# Modular connector core (staged)

This directory is wired into the connector through a fail-open first-refusal registry. Explicitly migrated actions run here; all other actions still fall through to the legacy monolith.

Migration strategy:

1. Keep the proven `model/modxmcp.class.php` monolith working.
2. Route MODX-version differences through `Platform`.
3. Migrate one action at a time into `Tools` + `ToolRegistry`.
4. Keep extras under `Extras`, separate from MODX 2/3 platform adapters.
5. Remove legacy code only after regression parity is proven on both MODX majors.

Syntax is kept compatible with PHP 7.x so MODX 2 support is not excluded by the new core itself.
