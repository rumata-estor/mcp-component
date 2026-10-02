<?php
use MODX\Revolution\modX;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(2);
}

error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

$config = getenv('MODX_CONFIG_CORE');
if (!$config || !is_file($config)) {
    $dir = __DIR__;
    for ($i = 0; $i < 12; $i++) {
        $candidate = $dir . DIRECTORY_SEPARATOR . 'config.core.php';
        if (is_file($candidate)) {
            $config = $candidate;
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) { break; }
        $dir = $parent;
    }
}
if (!$config || !is_file($config)) {
    fwrite(STDERR, "config.core.php not found.\n");
    exit(2);
}
if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $probe = dirname((string)(realpath($config) ?: $config));
    for ($i = 0; $i < 12; $i++) {
        $autoload = $probe . '/core/vendor/autoload.php';
        if (is_file($autoload)) {
            $_SERVER['DOCUMENT_ROOT'] = rtrim($probe, '/\\');
            break;
        }
        $parent = dirname($probe);
        if ($parent === $probe) { break; }
        $probe = $parent;
    }
}

require_once $config;
require_once rtrim(MODX_CORE_PATH, '/\\') . '/vendor/autoload.php';

$modx = modX::getInstance();
$modx->initialize('mgr');
if (method_exists($modx, 'setOption')) {
    $modx->setOption('modxmcp.disabled_groups', '');
} else {
    $modx->config['modxmcp.disabled_groups'] = '';
}

$corePath = $modx->getOption(
    'modxmcp.core_path',
    null,
    $modx->getOption('core_path') . 'components/modxmcp/'
);
$corePath = str_replace(
    array('{core_path}', '[[++core_path]]'),
    rtrim((string)$modx->getOption('core_path'), '/\\') . DIRECTORY_SEPARATOR,
    (string)$corePath
);
require_once $corePath . 'model/modxmcp.class.php';

function parity_call($mcp, $action, $type, array $data) {
    try {
        return array('ok' => true, 'value' => $mcp->processRequest($action, $type, $data));
    } catch (Throwable $e) {
        return array(
            'ok' => false,
            'error_class' => get_class($e),
            'error' => $e->getMessage(),
        );
    }
}

function legacy_instance($modx) {
    $mcp = new modxMCP($modx);
    $property = new ReflectionProperty('modxMCP', 'modularRuntime');
    $property->setAccessible(true);
    $property->setValue($mcp, null);
    return $mcp;
}

$cases = array(
    array('get_capabilities', '', array()),
    array('list_actions', '', array()),
    array('system_info', '', array()),
    array('project_overview', '', array('sections' => array('summary', 'contexts', 'resources'))),
    array('list_resources', '', array('limit' => 5)),
    array('check_integrations', '', array()),
    array('list_system_settings', '', array('namespace' => 'modxmcp')),
    array('get_system_setting', '', array('key' => 'modxmcp.enabled')),
    array('list_tv_input_types', '', array()),
    array('suggest_tv_type', '', array('description' => 'single image')),
    array('list_installed_components', '', array()),
    array('list_media_sources', '', array()),
    array('get_media_source', '', array('id' => 1)),
    array('list_media_source_files', '', array('id' => 1)),
    array('read_media_source_file', '', array('id' => 1, 'path' => 'nonexistent-parity-probe.txt')),
    array('get_resource_tvs', '', array('resource_id' => 1)),
    array('list_tv_values', '', array('tv_id' => 1, 'limit' => 5)),
    array('get_component_files', '', array('name' => 'modxmcp', 'path' => 'lexicon')),
    array('read_component_file', '', array('name' => 'modxmcp', 'path' => 'lexicon/en/default.inc.php')),
    array('list_contexts', '', array()),
    array('get_context', '', array('key' => 'web')),
    array('list_context_settings', '', array('context_key' => 'web')),
    array('get_context_setting', '', array('context_key' => 'web', 'key' => 'site_name')),
    array('list_namespaces', '', array('limit' => 5)),
    array('list_lexicon_entries', '', array(
        'namespace' => 'core', 'topic' => 'default', 'language' => 'en', 'limit' => 5
    )),
    array('list_lexicon_topics', '', array(
        'namespace' => 'core', 'language' => 'en', 'limit' => 5
    )),
    array('list_property_sets', '', array('limit' => 5)),
    array('get_property_set', '', array('id' => 999999)),
    array('list_providers', '', array()),
    array('search_packages', '', array('query' => '__modxmcp_parity_nonexistent__', 'limit' => 1)),
    array('list_users', '', array('limit' => 5)),
    array('get_user', '', array('id' => 999999)),
    array('list_user_groups', '', array('limit' => 5)),
    array('get_user_group', '', array('id' => 999999)),
    array('list_user_group_members', '', array('usergroup' => 1, 'limit' => 5)),
    array('list_roles', '', array('limit' => 5)),
    array('get_role', '', array('id' => 999999)),
    array('list_access_policies', '', array('limit' => 5)),
    array('list_access_policy_templates', '', array('limit' => 5)),
    array('list_access_permissions', '', array('template' => 1, 'limit' => 5)),
    array('list_resource_groups', '', array('limit' => 5)),
    array('list_context_access', '', array('usergroup' => 1, 'limit' => 5)),
    array('list_resourcegroup_access', '', array('usergroup' => 1, 'limit' => 5)),
    array('help', '', array('topic' => 'index')),
    array('read_error_log', '', array('limit' => 5)),
    array('read_audit_log', '', array('limit' => 5)),
    array('describe_object', '', array('class' => 'resource')),
    array('search_code', '', array('query' => '__modxmcp_parity_nonexistent__', 'limit' => 5)),
    array('find_usages', '', array('name' => '__modxmcp_parity_nonexistent__', 'limit' => 5)),
    array('dependency_graph', '', array('format' => 'summary', 'verify_orphans' => false)),
    array('versionx_list_versions', '', array('type' => 'resource', 'content_id' => 1, 'limit' => 3)),
    array('versionx_get_version', '', array('type' => 'resource', 'content_id' => 1, 'version_id' => 999999)),
    array('migx_list_configs', '', array('limit' => 5)),
    array('migx_get_config', '', array('id' => 999999)),
    array('virtualpage_list_events', '', array('limit' => 5)),
    array('virtualpage_get_event', '', array('id' => 999999)),
    array('virtualpage_list_handlers', '', array('limit' => 5)),
    array('virtualpage_get_handler', '', array('id' => 999999)),
    array('virtualpage_list_routes', '', array('limit' => 5)),
    array('virtualpage_get_route', '', array('id' => 999999)),
    array('virtualpage_resolve_route', '', array('path' => '/__modxmcp_parity_nonexistent__', 'method' => 'GET')),
    array('ms2_list_link_types', '', array('limit' => 5)),
    array('ms2_get_link_type', '', array('id' => 999999)),
    array('ms2_list_product_links', '', array('limit' => 5)),
    array('ms2_list_categories', '', array('limit' => 5)),
    array('ms2_list_orders', '', array('limit' => 5)),
    array('ms2_get_order', '', array('id' => 999999)),
    array('ms2_list_option_types', '', array()),
    array('ms2_list_options', '', array('limit' => 5)),
    array('ms2_get_option', '', array('id' => 999999)),
    array('ms2_get_product_options', '', array('product_id' => 1)),
);

$failures = array();
foreach ($cases as $case) {
    list($action, $type, $data) = $case;
    $modular = new modxMCP($modx);
    $legacy = legacy_instance($modx);
    $a = parity_call($modular, $action, $type, $data);
    $b = parity_call($legacy, $action, $type, $data);
    if ($a !== $b) {
        $failures[] = $action;
        echo "DIFF {$action}\n";
    } else {
        echo "OK {$action}\n";
    }
}

$elementTypes = array('chunk', 'snippet', 'template', 'resource', 'tv', 'category', 'plugin');
foreach ($elementTypes as $type) {
    $modular = new modxMCP($modx);
    $legacy = legacy_instance($modx);
    $a = parity_call($modular, 'list_elements', $type, array('limit' => 1));
    $b = parity_call($legacy, 'list_elements', $type, array('limit' => 1));
    if ($a !== $b) {
        $failures[] = 'list_elements:' . $type;
        echo "DIFF list_elements:{$type}\n";
        continue;
    }
    echo "OK list_elements:{$type}\n";
    if (!$a['ok'] || empty($a['value'][0]['id'])) {
        $failures[] = 'get_element:' . $type . ':no-fixture';
        echo "DIFF get_element:{$type}:no-fixture\n";
        continue;
    }
    $id = (int)$a['value'][0]['id'];
    $a = parity_call(new modxMCP($modx), 'get_element', $type, array('id' => $id));
    $b = parity_call(legacy_instance($modx), 'get_element', $type, array('id' => $id));
    if ($a !== $b) {
        $failures[] = 'get_element:' . $type;
        echo "DIFF get_element:{$type}\n";
    } else {
        echo "OK get_element:{$type}\n";
    }
    if (in_array($type, array('chunk', 'snippet', 'template', 'plugin'), true)) {
        $a = parity_call(new modxMCP($modx), 'view_element', '', array('type' => $type, 'id' => $id, 'start_line' => 1, 'end_line' => 3));
        $b = parity_call(legacy_instance($modx), 'view_element', '', array('type' => $type, 'id' => $id, 'start_line' => 1, 'end_line' => 3));
        if ($a !== $b) {
            $failures[] = 'view_element:' . $type;
            echo "DIFF view_element:{$type}\n";
        } else {
            echo "OK view_element:{$type}\n";
        }
    }
}

if ($failures) {
    fwrite(STDERR, "MODULAR_PARITY_FAIL: " . implode(', ', $failures) . "\n");
    exit(1);
}

echo "MODULAR_PARITY_OK 74 actions; 7/7 element types; 4/4 viewable element types\n";
