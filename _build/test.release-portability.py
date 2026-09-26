#!/usr/bin/env python3
from pathlib import Path
import json
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
errors = []

EXPECTED_VERSION = "1.0.0"
EXPECTED_NODE_NAME = "modx3-mcp"
EXPECTED_TRANSPORT_NAME = "MODX3MCP"

def fail(msg):
    errors.append(msg)

required_files = [
    "assets/components/modxmcp/api.php",
    "assets/components/modxmcp/connector.php",
    "core/components/modxmcp/model/modxmcp.class.php",
    "core/components/modxmcp/controllers/index.class.php",
    "core/components/modxmcp/processors/mgr/getstatus.class.php",
    "_build/build.transport.php",
    "_build/data/transport.settings.php",
    "_build/resolvers/resolve.token.php",
    "_build/install.headless.php",
    "client/index.js",
]
for rel in required_files:
    if not (ROOT / rel).is_file():
        fail(f"required file missing: {rel}")

package = json.loads((ROOT / "package.json").read_text())
lock = json.loads((ROOT / "package-lock.json").read_text())
build_config = (ROOT / "_build/build.config.php").read_text()
model_text = (ROOT / "core/components/modxmcp/model/modxmcp.class.php").read_text()

def php_define(name, text):
    m = re.search(rf"define\('{re.escape(name)}',\s*'([^']+)'\)", text)
    return None if not m else m.group(1)

def model_version(text):
    m = re.search(r"const\s+VERSION\s*=\s*'([^']+)'", text)
    return None if not m else m.group(1)

versions = {
    "build.config.php": php_define("PKG_VERSION", build_config),
    "model::VERSION": model_version(model_text),
    "package.json": package.get("version"),
    "package-lock.json": lock.get("version"),
    "package-lock root": lock.get("packages", {}).get("", {}).get("version"),
}
for source, version in versions.items():
    if version != EXPECTED_VERSION:
        fail(f"{source}: version={version!r}, expected {EXPECTED_VERSION}")

if php_define("PKG_NAME", build_config) != EXPECTED_TRANSPORT_NAME:
    fail(f"transport package name must be {EXPECTED_TRANSPORT_NAME}")
if package.get("name") != EXPECTED_NODE_NAME:
    fail(f"package.json name must be {EXPECTED_NODE_NAME}")
if lock.get("name") != EXPECTED_NODE_NAME or lock.get("packages", {}).get("", {}).get("name") != EXPECTED_NODE_NAME:
    fail("package-lock Node package identity does not match package.json")

scan_ext = {".php", ".js", ".json", ".md", ".yml", ".yaml"}
for path in ROOT.rglob("*"):
    if not path.is_file() or ".git" in path.parts or "node_modules" in path.parts:
        continue
    if path.name == "RELEASE_AUDIT_RU.md":
        continue
    if path.suffix.lower() not in scan_ext:
        continue
    text = path.read_text(errors="ignore")
    rel = path.relative_to(ROOT)
    forbidden = [
        ("test.alex-palochkin.ru", "test-site domain"),
        ("130.17.9.68", "deployment-specific IP"),
        ("/home/codexbot", "deployment-specific home path"),
    ]
    for needle, label in forbidden:
        if needle in text:
            fail(f"{rel}: contains {label}: {needle}")

transport = (ROOT / "_build/data/transport.settings.php").read_text()
headless = (ROOT / "_build/install.headless.php").read_text()

transport_keys = set(re.findall(r"array\('(modxmcp\.[^']+)'", transport))
headless_keys = set(re.findall(r"'(modxmcp\.[^']+)'\s*=>\s*array\(", headless))

if not transport_keys:
    fail("could not parse transport setting keys")
if not headless_keys:
    fail("could not parse headless setting keys")

if transport_keys != headless_keys:
    only_transport = sorted(transport_keys - headless_keys)
    only_headless = sorted(headless_keys - transport_keys)
    fail(f"settings parity mismatch; transport-only={only_transport}, headless-only={only_headless}")

def parse_simple_default(text, key, headless_mode=False):
    if headless_mode:
        pattern = rf"'{re.escape(key)}'\s*=>\s*array\(\s*([^,\n]+)"
    else:
        pattern = rf"array\('{re.escape(key)}',\s*([^,\n]+)"
    m = re.search(pattern, text)
    return None if not m else m.group(1).strip().strip("'\"")

expected_defaults = {
    "modxmcp.service_user_id": "0",
    "modxmcp.auto_static": "0",
    "modxmcp.allow_run_processor": "0",
    "modxmcp.allow_root_filesystem_read": "0",
    "modxmcp.require_https": "1",
    "modxmcp.trust_proxy_https": "0",
    "modxmcp.debug": "0",
}
for key, expected in expected_defaults.items():
    if key not in transport_keys or key not in headless_keys:
        fail(f"required setting missing: {key}")
        continue
    t_value = parse_simple_default(transport, key, False)
    h_value = parse_simple_default(headless, key, True)
    if t_value != expected:
        fail(f"unsafe/unexpected transport default {key}={t_value}; expected {expected}")
    if h_value != expected:
        fail(f"unsafe/unexpected headless default {key}={h_value}; expected {expected}")

token_sources = [
    ROOT / "_build/resolvers/resolve.token.php",
    ROOT / "_build/install.headless.php",
    ROOT / "core/components/modxmcp/model/modxmcp.class.php",
]
for path in token_sources:
    text = path.read_text()
    if "random_bytes" not in text:
        fail(f"{path.relative_to(ROOT)}: secure random_bytes token generation missing")
    if re.search(r"(md5|sha1|uniqid)\s*\(", text, re.I):
        fail(f"{path.relative_to(ROOT)}: weak token fallback primitive found")

token_resolver = (ROOT / "_build/resolvers/resolve.token.php").read_text()
if "if (!$setting)" not in token_resolver:
    fail("resolve.token.php: missing fail-closed check for absent api_token setting")
if "if (!$setting->save())" not in token_resolver:
    fail("resolve.token.php: missing fail-closed check for api_token save failure")

if "service_user_id', null, 0" not in model_text:
    fail("model: service_user_id portable default must remain 0")
if "['active' => 1, 'sudo' => 1]" not in model_text:
    fail("model: automatic service user selection must require active + sudo")
if "if (!$user->get('sudo'))" not in model_text:
    fail("model: explicitly configured service user must already be sudo")

builder = (ROOT / "_build/build.transport.php").read_text()
builder_requirements = {
    "registerNamespace(": "transport builder must register namespace",
    "newObject(modMenu::class)": "transport builder must create manager menus",
    "transport.settings.php": "transport builder must package system settings",
    "resolve.token.php": "transport builder must attach token resolver",
    "resolve.integrations.php": "transport builder must attach integrations resolver",
    "source_core": "transport builder must package core files",
    "source_assets": "transport builder must package assets files",
    "UPDATE_OBJECT => false": "transport settings must preserve admin-edited values on upgrade",
    "'license'": "transport package must include license attribute",
    "'readme'": "transport package must include readme attribute",
    "'changelog'": "transport package must include changelog attribute",
    "'requires'": "transport package must declare platform dependencies",
    "'modx' => '>=3.0.0 <4.0.0'": "transport package must restrict installation to MODX 3.x",
}
for needle, message in builder_requirements.items():
    if needle not in builder:
        fail(message)
if "'modxmcp'" not in builder or "'modxmcp_graph'" not in builder:
    fail("transport builder: both manager menu entries are required")

headless_text = (ROOT / "_build/install.headless.php").read_text()
if "getVersionData()" not in headless_text or "version_compare($fullVersion, '3.0.0', '<')" not in headless_text or "version_compare($fullVersion, '4.0.0', '>=')" not in headless_text:
    fail("headless installer: explicit MODX 3.x preflight guard missing")
for menu_key in ["'modxmcp'", "'modxmcp_graph'"]:
    if menu_key not in headless_text:
        fail(f"headless installer: manager menu missing {menu_key}")

api = (ROOT / "assets/components/modxmcp/api.php").read_text()
connector = (ROOT / "assets/components/modxmcp/connector.php").read_text()
for controller_rel in [
    "core/components/modxmcp/controllers/index.class.php",
    "core/components/modxmcp/controllers/graph.class.php",
]:
    controller = (ROOT / controller_rel).read_text()
    if "hasPermission('settings')" not in controller:
        fail(f"{controller_rel}: manager screen must require the settings permission")
if "str_replace" not in connector or "{core_path}" not in connector:
    fail("connector.php: modxmcp.core_path placeholders are not expanded")
if "hash_equals" not in api:
    fail("api.php: token comparison must use hash_equals")
if "modxmcp.trust_proxy_https" not in api:
    fail("api.php: explicit reverse-proxy HTTPS trust setting missing")
if "HTTP_X_FORWARDED_FOR" in api:
    fail("api.php: X-Forwarded-For must not be trusted for IP allowlist")
content_length_pos = api.find("CONTENT_LENGTH")
input_read_pos = api.find("file_get_contents('php://input')")
if content_length_pos < 0 or input_read_pos < 0 or content_length_pos > input_read_pos:
    fail("api.php: Content-Length payload limit must be checked before reading request body")
if "$rawInput === false" not in api:
    fail("api.php: request-body read failure must be handled explicitly")
https_pos = api.find("modxmcp.require_https")
health_get_pos = api.find("REQUEST_METHOD'] === 'GET'")
if https_pos < 0 or health_get_pos < 0 or https_pos > health_get_pos:
    fail("api.php: HTTPS enforcement must run before the unauthenticated health GET")

tx_match = re.search(r"private function runWithTransaction\(callable \$callback\).*?\n    \}", model_text, re.S)
if not tx_match or "catch (Throwable $e)" not in tx_match.group(0):
    fail("model: runWithTransaction must rollback on Throwable, not only Exception")

if errors:
    print("MODX3 MCP portability check FAILED:")
    for e in errors:
        print(" -", e)
    sys.exit(1)

print(f"MODX3 MCP portability check passed: {len(required_files)} required files, {len(transport_keys)} settings, secure defaults aligned.")