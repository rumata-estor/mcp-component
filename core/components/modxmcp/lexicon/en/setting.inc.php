<?php
$_lang['setting_modxmcp.enabled'] = 'Enable MODX MCP';
$_lang['setting_modxmcp.enabled_desc'] = 'Globally enables or disables the MODX MCP API component. When disabled, all API requests are rejected.';

$_lang['setting_modxmcp.api_token'] = 'MODX MCP API token';
$_lang['setting_modxmcp.api_token_desc'] = 'Secret token passed in the X-MCP-Token header to authorize all requests to the MCP API.';

$_lang['setting_modxmcp.service_user_id'] = 'Service user ID';
$_lang['setting_modxmcp.service_user_id_desc'] = 'The MODX sudo user ID under which MCP executes administrative actions. Value 0 automatically selects the first active sudo user; a positive ID uses that explicitly configured active sudo user.';

$_lang['setting_modxmcp.debug'] = 'MCP debug mode';
$_lang['setting_modxmcp.debug_desc'] = 'When enabled, the API returns internal error details in the response. It is recommended to keep this disabled in production.';

$_lang['setting_modxmcp.audit_log'] = 'MCP audit log';
$_lang['setting_modxmcp.audit_log_desc'] = 'When enabled, create/update/delete operations and TV updates are written to the MODX log for auditing and diagnostics.';

$_lang['setting_modxmcp.max_payload_bytes'] = 'Maximum payload size';
$_lang['setting_modxmcp.max_payload_bytes_desc'] = 'Maximum allowed size of the JSON request body in bytes. Protects the component from oversized or malformed requests.';

$_lang['setting_modxmcp.require_https'] = 'Require HTTPS';
$_lang['setting_modxmcp.require_https_desc'] = 'Rejects MCP API requests that are not HTTPS. Enabled by default for new installations.';
$_lang['setting_modxmcp.trust_proxy_https'] = 'Trust reverse-proxy HTTPS header';
$_lang['setting_modxmcp.trust_proxy_https_desc'] = 'When enabled, X-Forwarded-Proto: https is accepted as HTTPS evidence. Enable only behind a trusted reverse proxy that sets this header itself.';
$_lang['setting_modxmcp.allowed_ips'] = 'Allowed MCP IPs';
$_lang['setting_modxmcp.allowed_ips_desc'] = 'Optional comma-separated IP/CIDR allowlist. Empty allows any IP with a valid token; the real REMOTE_ADDR is checked and X-Forwarded-For is not trusted.';

$_lang['setting_modxmcp.allow_root_filesystem_read'] = 'Allow root Filesystem read';
$_lang['setting_modxmcp.allow_root_filesystem_read_desc'] = 'When enabled, MCP may browse and read files through the root Filesystem media source. Keep disabled if you only want safe component-code inspection.';

$_lang['setting_modxmcp.component_code_roots'] = 'Component code roots';
$_lang['setting_modxmcp.component_code_roots_desc'] = 'Comma-separated list of directories that MCP may scan for installed component code, for example core/components,assets/components.';

$_lang['setting_modxmcp.core_path'] = 'Core path';
$_lang['setting_modxmcp.core_path_desc'] = 'Filesystem path to the modxMCP core component directory. Defaults to {core_path}components/modxmcp/.';

$_lang['area_modxmcp:main'] = 'modxMCP: Main';
$_lang['area_modxmcp:limits'] = 'modxMCP: Limits';
$_lang['area_modxmcp:security'] = 'modxMCP: Security';
$_lang['area_modxmcp:paths'] = 'modxMCP: Paths';

$_lang['setting_modxmcp.auto_static'] = 'Auto static elements';
$_lang['setting_modxmcp.auto_static_desc'] = 'When enabled, MCP create/update of a chunk/snippet/template/plugin automatically converts it to a static file under core/elements/ (source = Filesystem). Edit those files directly afterwards.';


$_lang['setting_modxmcp.allow_run_processor'] = 'Allow run_processor';
$_lang['setting_modxmcp.allow_run_processor_desc'] = 'When enabled, the run_processor MCP action may execute ANY MODX processor directly. Powerful escape hatch — off by default. Prefer dedicated actions when they exist.';