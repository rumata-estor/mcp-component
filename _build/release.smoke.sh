#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
FINAL_INSTALL=1
PREFLIGHT_ONLY=0

case "${1:-}" in
  "")
    ;;
  --leave-uninstalled)
    FINAL_INSTALL=0
    ;;
  --preflight-only)
    PREFLIGHT_ONLY=1
    ;;
  *)
    echo "Usage: $0 [--leave-uninstalled|--preflight-only]" >&2
    exit 2
    ;;
esac

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  echo "PHP CLI not found: $PHP_BIN" >&2
  exit 3
fi

stage="preflight"
settings_snapshot=""

cleanup() {
  if [[ -n "$settings_snapshot" && -f "$settings_snapshot" ]]; then
    rm -f "$settings_snapshot"
  fi
}

on_error() {
  rc=$?
  echo "RELEASE_SMOKE_FAILED stage=$stage rc=$rc" >&2
  cleanup
  exit "$rc"
}

trap on_error ERR
trap cleanup EXIT

echo "== MODX3 MCP release smoke =="
"$PHP_BIN" -v | head -n 2

stage="php-lint"
php_files=0
while IFS= read -r -d '' file; do
  "$PHP_BIN" -l "$file" >/dev/null
  php_files=$((php_files + 1))
done < <(find "$ROOT" -type f -name '*.php' -not -path '*/node_modules/*' -print0)
echo "PHP_LINT_OK files=$php_files"

stage="source-regression"
if command -v python3 >/dev/null 2>&1; then
  python3 "$ROOT/_build/test.release-portability.py"
  python3 "$ROOT/_build/test.client-server-actions.py"
else
  echo "SOURCE_REGRESSION_SKIP python3-not-found"
fi
if command -v node >/dev/null 2>&1; then
  node --check "$ROOT/client/index.js"
else
  echo "CLIENT_SYNTAX_SKIP node-not-found"
fi

stage="build-transport"
"$PHP_BIN" "$ROOT/_build/build.transport.php"

signature="$(
  ROOT="$ROOT" "$PHP_BIN" -r '
    require getenv("ROOT") . "/_build/build.config.php";
    echo strtolower(PKG_NAME) . "-" . PKG_VERSION . "-" . PKG_RELEASE;
  '
)"
if [[ -z "$signature" ]]; then
  echo "Could not determine transport signature." >&2
  exit 4
fi
echo "TRANSPORT_BUILD_OK signature=$signature"

if [[ "$PREFLIGHT_ONLY" -eq 1 ]]; then
  stage="done"
  trap - ERR
  echo "RELEASE_PREFLIGHT_OK signature=$signature"
  exit 0
fi

stage="settings-snapshot-original"
settings_snapshot="$(mktemp "${TMPDIR:-/tmp}/modx3mcp-settings.XXXXXX.json")"
chmod 600 "$settings_snapshot"
"$PHP_BIN" "$ROOT/_build/smoke.endpoint.php" "--settings-export=$settings_snapshot"

stage="fresh-install"
"$PHP_BIN" "$ROOT/_build/install.transport.php"
echo "FRESH_INSTALL_OK"

stage="endpoint-crud-smoke"
"$PHP_BIN" "$ROOT/_build/smoke.endpoint.php"

stage="settings-snapshot-before-reinstall"
before_hash="$(
  "$PHP_BIN" "$ROOT/_build/smoke.endpoint.php" --settings-hash |
    awk -F= '/^SETTINGS_HASH=/{print $2}'
)"
if [[ -z "$before_hash" ]]; then
  echo "Could not capture settings hash before reinstall." >&2
  exit 5
fi

stage="same-package-reinstall"
"$PHP_BIN" "$ROOT/_build/install.transport.php"

stage="settings-snapshot-after-reinstall"
after_hash="$(
  "$PHP_BIN" "$ROOT/_build/smoke.endpoint.php" --settings-hash |
    awk -F= '/^SETTINGS_HASH=/{print $2}'
)"
if [[ "$before_hash" != "$after_hash" ]]; then
  echo "Settings changed during same-package reinstall." >&2
  exit 6
fi
echo "REINSTALL_SETTINGS_PRESERVED_OK"

stage="clean-uninstall"
"$PHP_BIN" "$ROOT/_build/install.transport.php" --action=uninstall
echo "CLEAN_UNINSTALL_OK"

if [[ "$FINAL_INSTALL" -eq 1 ]]; then
  stage="final-install"
  "$PHP_BIN" "$ROOT/_build/install.transport.php"

  stage="restore-original-settings"
  "$PHP_BIN" "$ROOT/_build/smoke.endpoint.php" "--settings-restore=$settings_snapshot"

  stage="final-read-only-smoke"
  "$PHP_BIN" "$ROOT/_build/smoke.endpoint.php" --read-only
  final_state="installed"
else
  final_state="uninstalled"
fi

stage="done"
trap - ERR
echo "RELEASE_SMOKE_OK signature=$signature final_state=$final_state"