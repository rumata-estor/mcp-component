#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
if ! command -v "$PHP_BIN" >/dev/null 2>&1 && [[ ! -x "$PHP_BIN" ]]; then echo "PHP CLI not found: $PHP_BIN" >&2; exit 3; fi
echo '== MODX2 MCP release smoke =='
"$PHP_BIN" -v | head -n 2
count=0; while IFS= read -r -d '' f; do "$PHP_BIN" -l "$f" >/dev/null; count=$((count+1)); done < <(find "$ROOT" -type f -name '*.php' -not -path '*/node_modules/*' -print0); echo "PHP_LINT_OK files=$count"
python3 "$ROOT/_build/test.architecture.py"
python3 "$ROOT/_build/test.client-server-actions.py"
python3 "$ROOT/_build/test.modx2-php-compat.py"
"$PHP_BIN" "$ROOT/_build/build.transport.php"
"$PHP_BIN" "$ROOT/_build/install.transport.php"
"$PHP_BIN" "$ROOT/_build/smoke.endpoint.php"
before=$("$PHP_BIN" "$ROOT/_build/smoke.endpoint.php" --settings-hash | awk -F= '/^SETTINGS_HASH=/{print $2}')
"$PHP_BIN" "$ROOT/_build/install.transport.php"
after=$("$PHP_BIN" "$ROOT/_build/smoke.endpoint.php" --settings-hash | awk -F= '/^SETTINGS_HASH=/{print $2}')
[[ -n "$before" && "$before" == "$after" ]] || { echo 'Settings changed during reinstall.' >&2; exit 6; }
echo REINSTALL_SETTINGS_PRESERVED_OK
"$PHP_BIN" "$ROOT/_build/install.transport.php" --action=uninstall
echo 'MODX2_RELEASE_SMOKE_OK final_state=uninstalled'
