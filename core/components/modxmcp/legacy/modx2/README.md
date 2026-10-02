# MODX 2 legacy bridge

Temporary compatibility fallback sourced from repository commit recorded in `SOURCE_COMMIT`.

The shared modular Registry gets first refusal. Actions not yet migrated fall through to this legacy implementation.

Security adjustment versus the historical branch: the service account must actually be an active sudo MODX user; the bridge no longer forces `sudo=1` in memory.
