#!/usr/bin/env python3
from pathlib import Path
import json
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
errors = []

EXPECTED_VERSION = "1.0.1"
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
    "_build/resolvers/resolve.permissions.php",
    "_build/resolvers/resolve.settings.php",
    "_build/install.headless.php",
    "_build/install.transport.php",
    "_build/smoke.endpoint.php",
    "_build/release.smoke.sh",
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

def parse_setting_defs(text, headless_mode=False):
    value = r"(?:'[^']*'|\"[^\"]*\"|-?\d+)"
    if headless_mode:
        pattern = re.compile(
            rf"'(?P<key>modxmcp\.[^']+)'\s*=>\s*array\(\s*"
            rf"(?P<value>{value})\s*,\s*'(?P<xtype>[^']+)'\s*,\s*'(?P<area>[^']+)'\s*\)",
            re.S,
        )
    else:
        pattern = re.compile(
            rf"array\(\s*'(?P<key>modxmcp\.[^']+)'\s*,\s*"
            rf"(?P<value>{value})\s*,\s*'(?P<xtype>[^']+)'\s*,\s*'(?P<area>[^']+)'\s*\)",
            re.S,
        )
    out = {}
    for m in pattern.finditer(text):
        raw = m.group("value").strip()
        if len(raw) >= 2 and raw[0] in "'\"" and raw[-1] == raw[0]:
            raw = raw[1:-1]
        out[m.group("key")] = {
            "value": raw,
            "xtype": m.group("xtype"),
            "area": m.group("area"),
        }
    return out

transport_defs = parse_setting_defs(transport, False)
headless_defs = parse_setting_defs(headless, True)
if set(transport_defs) != transport_keys:
    fail(f"could not parse all transport setting definitions: parsed={len(transport_defs)} expected={len(transport_keys)}")
if set(headless_defs) != headless_keys:
    fail(f"could not parse all headless setting definitions: parsed={len(headless_defs)} expected={len(headless_keys)}")
for key in sorted(transport_keys & headless_keys):
    if transport_defs.get(key) != headless_defs.get(key):
        fail(f"setting definition mismatch for {key}: transport={transport_defs.get(key)} headless={headless_defs.get(key)}")

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
    if key not in transport_defs or key not in headless_defs:
        fail(f"required setting missing: {key}")
        continue
    if transport_defs[key]["value"] != expected:
        fail(f"unsafe/unexpected transport default {key}={transport_defs[key]['value']}; expected {expected}")
    if headless_defs[key]["value"] != expected:
        fail(f"unsafe/unexpected headless default {key}={headless_defs[key]['value']}; expected {expected}")

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
if "$this->modx->user->set('sudo', 1)" in model_text or "$this->modx->user->set('sudo',1)" in model_text:
    fail("model: processRequest must not elevate the service user to sudo in memory")
cap_pos = model_text.find("$this->assertCapabilityEnabled($action)")
dispatch_pos = model_text.find("$this->resolveActionSpec($action)")
if cap_pos < 0 or dispatch_pos < 0 or cap_pos > dispatch_pos:
    fail("model: capability enforcement must run before action dispatch")
if "modxmcp.allow_run_processor', null, false" not in model_text:
    fail("model: run_processor must remain independently gated off by default")
if "empty($data['template'])" in model_text:
    fail("model: bulk set_template must not treat template=0 as missing")
if "array_key_exists('template', $data)" not in model_text or "(int) $data['template'] >= 0" not in model_text:
    fail("model: bulk set_template must explicitly allow non-negative template ids including 0")
client_text = (ROOT / "client/index.js").read_text()
if 'template: { type: "integer", minimum: 0' not in client_text:
    fail("client: bulk set_template schema must advertise template id 0 as valid")

settings_resolver = (ROOT / "_build/resolvers/resolve.settings.php").read_text()
for needle, message in {
    "ACTION_UNINSTALL": "resolve.settings.php: uninstall action guard missing",
    "modSystemSetting::class": "resolve.settings.php: modxmcp settings cleanup missing",
    "'namespace' => 'modxmcp'": "resolve.settings.php: cleanup must be scoped to modxmcp namespace",
    "modMenu::class": "resolve.settings.php: manager-menu cleanup missing",
    "'modxmcp_graph', 'modxmcp'": "resolve.settings.php: both manager menus must be cleaned child-first",
    "modNamespace::class": "resolve.settings.php: namespace cleanup missing",
}.items():
    if needle not in settings_resolver:
        fail(message)

transport_installer = (ROOT / "_build/install.transport.php").read_text()
for needle, message in {
    "TRANSPORT_UNINSTALL_VERIFY_OK": "transport verifier: uninstall success marker missing",
    "getCount(modSystemSetting::class": "transport verifier: leftover settings check missing",
    "getObject(modMenu::class": "transport verifier: leftover menu check missing",
    "getObject(modNamespace::class": "transport verifier: leftover namespace check missing",
    "components' . DIRECTORY_SEPARATOR . 'modxmcp": "transport verifier: leftover component-directory check missing",
    "$expectedSettings = 16": "transport verifier: exact 16-setting install check missing",
    "$rootMenu": "transport verifier: root menu install check missing",
    "$graphMenu": "transport verifier: graph menu install check missing",
    "$deployedVersion !== PKG_VERSION": "transport verifier: deployed code version check missing",
    "exit(7)": "transport verifier: leftover artifacts must fail the uninstall test",
}.items():
    if needle not in transport_installer:
        fail(message)

endpoint_smoke = (ROOT / "_build/smoke.endpoint.php").read_text()
for needle, message in {
    "PHP_SAPI !== 'cli'": "endpoint smoke must be CLI-only",
    "--settings-hash": "endpoint smoke settings-hash mode missing",
    "--read-only": "endpoint smoke read-only mode missing",
    "--settings-export=": "endpoint smoke settings snapshot export mode missing",
    "--settings-restore=": "endpoint smoke settings restore mode missing",
    "--settings-compare=": "endpoint smoke settings comparison mode missing",
    "SETTINGS_SNAPSHOT_OK": "endpoint smoke settings snapshot success marker missing",
    "SETTINGS_COMPARE_OK": "endpoint smoke settings comparison success marker missing",
    "SETTINGS_RESTORE_OK": "endpoint smoke settings restore success marker missing",
    "MODX_DOCUMENT_ROOT": "endpoint smoke portable CLI bootstrap missing",
    "MODX_MCP_SMOKE_SITE_URL": "endpoint smoke URL override missing",
    "MCP_ENDPOINT_SMOKE_OK": "endpoint CRUD smoke success marker missing",
    "MCP_ENDPOINT_READ_ONLY_SMOKE_OK": "endpoint read-only smoke marker missing",
    "finally": "endpoint smoke must guarantee CRUD cleanup",
    "actionCount !== 182": "endpoint smoke must detect client/server action skew",
}.items():
    if needle not in endpoint_smoke:
        fail(message)
if "TOKEN=" in endpoint_smoke or "echo $token" in endpoint_smoke:
    fail("endpoint smoke must never print the API token")

release_smoke = (ROOT / "_build/release.smoke.sh").read_text()
if r"\${" in release_smoke:
    fail("release smoke: shell variables must not be backslash-escaped")

for needle, message in {
    "php-lint": "release smoke PHP lint stage missing",
    "build.transport.php": "release smoke transport build stage missing",
    "--preflight-only": "release smoke non-mutating preflight mode missing",
    "--settings-export=": "release smoke original settings snapshot missing",
    "--settings-restore=": "release smoke original settings restore missing",
    "REINSTALL_SETTINGS_PRESERVED_OK": "release smoke settings preservation check missing",
    "--action=uninstall": "release smoke clean uninstall stage missing",
    "--read-only": "release smoke final read-only verification missing",
    "RELEASE_SMOKE_OK": "release smoke success marker missing",
}.items():
    if needle not in release_smoke:
        fail(message)

builder = (ROOT / "_build/build.transport.php").read_text()
if "PHP_SAPI !== 'cli'" not in builder:
    fail("transport builder: must be CLI-only")
if "$_GET['key']" in builder or "web build requires" in builder:
    fail("transport builder: API token must never be accepted through a web query string")
builder_requirements = {
    "registerNamespace(": "transport builder must register namespace",
    "newObject(modMenu::class)": "transport builder must create manager menus",
    "transport.settings.php": "transport builder must package system settings",
    "resolve.token.php": "transport builder must attach token resolver",
    "resolve.integrations.php": "transport builder must attach integrations resolver",
    "resolve.settings.php": "transport builder must attach uninstall settings resolver",
    "resolve.permissions.php": "transport builder must attach upgrade-permission resolver before file vehicles",
    "'new_file_permissions' => '0644'": "transport builder must set explicit writable file permissions",
    "'new_folder_permissions' => '0755'": "transport builder must set explicit directory permissions",
    "source_core": "transport builder must package core files",
    "source_assets": "transport builder must package assets files",
    "UPDATE_OBJECT => false": "transport settings must preserve admin-edited values on upgrade",
    "'license'": "transport package must include license attribute",
    "'readme'": "transport package must include readme attribute",
    "'changelog'": "transport package must include changelog attribute",
    "'requires'": "transport package must declare platform dependencies",
    "'modx' => '>=3.0.0,<4.0.0'": "transport package must restrict installation to MODX 3.x using xPDO constraint syntax",
    "Package staging directory still exists after cleanup": "transport builder must remove its unpacked staging tree after pack()",
    "deleteTree($stagingPath": "transport builder staging cleanup must use a bounded package path",
    "modTransportPackage::class": "transport builder must detect an already installed same-signature package",
    "Refusing to build {$desiredSignature}": "transport builder must refuse to clobber an installed package staging tree",
}
for needle, message in builder_requirements.items():
    if needle not in builder:
        fail(message)
if "'modxmcp'" not in builder or "'modxmcp_graph'" not in builder:
    fail("transport builder: both manager menu entries are required")

headless_text = (ROOT / "_build/install.headless.php").read_text()

for cli_name, cli_text in {
    "build.transport.php": builder,
    "install.transport.php": transport_installer,
    "install.headless.php": headless_text,
}.items():
    for needle, message in {
        "MODX_DOCUMENT_ROOT": "must support explicit MODX_DOCUMENT_ROOT",
        "$_SERVER['DOCUMENT_ROOT']": "must restore DOCUMENT_ROOT for CLI bootstrap",
        "core' . DIRECTORY_SEPARATOR . 'vendor'": "must infer MODX web root using core/vendor/autoload.php",
        "MODX bootstrap failed": "must fail closed when MODX autoload cannot be resolved",
    }.items():
        if needle not in cli_text:
            fail(f"{cli_name}: {message}")

if "getVersionData()" not in headless_text or "version_compare($fullVersion, '3.0.0', '<')" not in headless_text or "version_compare($fullVersion, '4.0.0', '>=')" not in headless_text:
    fail("headless installer: explicit MODX 3.x preflight guard missing")
for needle, message in {
    "register_shutdown_function": "headless installer: rollback shutdown handler missing",
    ".modxmcp-stage-": "headless installer: staged deployment missing",
    ".modxmcp-previous-": "headless installer: previous-tree backup missing",
    "rename($stageCore, $targetCore)": "headless installer: atomic core swap missing",
    "rename($stageAssets, $targetAssets)": "headless installer: atomic assets swap missing",
    "$deployCommitted = true": "headless installer: successful deployment commit marker missing",
    "$copyTree($runtimeLogs, $stageCore . DIRECTORY_SEPARATOR . 'logs')": "headless installer: runtime audit logs must survive update",
    "$modx->beginTransaction()": "headless installer: MODX database transaction missing",
    "$modx->rollback()": "headless installer: database rollback missing",
    "$modx->commit()": "headless installer: database commit missing",
    "$dbTransactionOpen = false": "headless installer: transaction state tracking missing",
}.items():
    if needle not in headless_text:
        fail(message)
if "Refusing to deploy into a symlinked component directory" not in headless_text:
    fail("headless installer: component target symlink guard missing")
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