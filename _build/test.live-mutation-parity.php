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
        if (is_file($candidate)) { $config = $candidate; break; }
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
        if (is_file($probe . '/core/vendor/autoload.php')) {
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

function mutation_call($mcp, $action, array $data)
{
    try {
        return array(
            'ok' => true,
            'value' => $mcp->processRequest($action, '', $data),
        );
    } catch (Throwable $e) {
        return array(
            'ok' => false,
            'error_class' => get_class($e),
            'error' => $e->getMessage(),
        );
    }
}

function mutation_legacy($modx)
{
    $mcp = new modxMCP($modx);
    $property = new ReflectionProperty('modxMCP', 'modularRuntime');
    $property->setAccessible(true);
    $property->setValue($mcp, null);
    return $mcp;
}

function mutation_tx($modx, $mcp, $action, array $data)
{
    $modx->beginTransaction();
    try {
        $result = mutation_call($mcp, $action, $data);
    } finally {
        $modx->rollback();
    }
    return $result;
}

function mutation_normalize_error($error)
{
    $parts = explode(' | ', (string)$error);
    $seen = array();
    $out = array();
    foreach ($parts as $part) {
        if (isset($seen[$part])) { continue; }
        $seen[$part] = true;
        $out[] = $part;
    }
    return implode(' | ', $out);
}

function mutation_normalize($value)
{
    if (!is_array($value)) { return $value; }
    $out = array();
    foreach ($value as $key => $item) {
        if ((string)$key === 'error' && is_string($item)) {
            $out[$key] = mutation_normalize_error($item);
            continue;
        }
        if (in_array((string)$key, array('createdon', 'editedon', 'lastlogin', 'password', 'cachepwd', 'salt', 'hash_class'), true)) {
            continue;
        }
        if (in_array((string)$key, array('id', 'principal', 'target'), true)
            && (is_int($item) || ctype_digit((string)$item))) {
            $out[$key] = '__ID__';
            continue;
        }
        $out[$key] = mutation_normalize($item);
    }
    ksort($out);
    return $out;
}

$cases = array(
    array('create_user', array()),
    array('update_user', array('id' => 999999)),
    array('delete_user', array('id' => 999999)),
    array('create_user_group', array()),
    array('update_user_group', array('id' => 999999)),
    array('delete_user_group', array('id' => 999999)),
    array('add_user_to_group', array('user' => 999999, 'usergroup' => 999999)),
    array('update_group_member', array('id' => 999999)),
    array('remove_user_from_group', array('id' => 999999)),
    array('create_role', array()),
    array('update_role', array('id' => 999999)),
    array('delete_role', array('id' => 999999)),
    array('create_access_policy', array()),
    array('update_access_policy', array('id' => 999999)),
    array('delete_access_policy', array('id' => 999999)),
    array('create_access_policy_template', array()),
    array('update_access_policy_template', array('id' => 999999)),
    array('delete_access_policy_template', array('id' => 999999)),
    array('create_resource_group', array()),
    array('update_resource_group', array('id' => 999999)),
    array('delete_resource_group', array('id' => 999999)),
    array('assign_resource_to_group', array('resource' => 999999, 'resourceGroup' => 999999)),
    array('remove_resource_from_group', array('resource' => 999999, 'resourceGroup' => 999999)),
    array('grant_context_access', array('principal' => 999999, 'target' => '__none__', 'policy' => 999999, 'authority' => 999999)),
    array('update_context_access', array('principal' => 999999, 'target' => '__none__', 'policy' => 999999, 'authority' => 999999)),
    array('revoke_context_access', array('principal' => 999999, 'target' => '__none__', 'policy' => 999999)),
    array('grant_resourcegroup_access', array('principal' => 999999, 'target' => 999999, 'policy' => 999999, 'authority' => 999999)),
    array('update_resourcegroup_access', array('principal' => 999999, 'target' => 999999, 'policy' => 999999, 'authority' => 999999)),
    array('revoke_resourcegroup_access', array('principal' => 999999, 'target' => 999999, 'policy' => 999999)),

    array('create_context', array()),
    array('update_context', array('key' => '__modxmcp_missing__')),
    array('delete_context', array('key' => '__modxmcp_missing__')),
    array('create_context_setting', array('context_key' => '__modxmcp_missing__', 'key' => '__modxmcp_test__', 'value' => 'x')),
    array('update_context_setting', array('context_key' => '__modxmcp_missing__', 'key' => '__modxmcp_test__', 'value' => 'x')),
    array('delete_context_setting', array('context_key' => '__modxmcp_missing__', 'key' => '__modxmcp_test__')),

    array('create_namespace', array()),
    array('update_namespace', array('name' => '__modxmcp_missing__')),
    array('delete_namespace', array('name' => '__modxmcp_missing__')),
    array('set_lexicon_entry', array('namespace' => '__modxmcp_missing__', 'topic' => '__modxmcp_missing__', 'name' => '__modxmcp_test__', 'value' => 'x', 'language' => 'en')),
    array('revert_lexicon_entry', array('namespace' => '__modxmcp_missing__', 'topic' => '__modxmcp_missing__', 'name' => '__modxmcp_test__', 'language' => 'en')),

    // Positive write paths. Each one is rolled back before the next call.
    array('create_context', array('key' => '__modxmcp_parity_ctx__', 'name' => 'modxMCP parity context')),
    array('create_namespace', array('name' => '__modxmcp_parity_ns__')),
    array('create_user_group', array('name' => '__modxmcp_parity_group__')),
    array('create_role', array('name' => '__modxmcp_parity_role__', 'authority' => 999)),
    array('create_resource_group', array('name' => '__modxmcp_parity_rg__')),
    array('create_user', array(
        'username' => '__modxmcp_parity_user__',
        'fullname' => 'modxMCP parity user',
        'email' => 'modxmcp-parity@example.invalid',
        'password' => 'Temp-Parity-9x7Q!',
        'active' => 1,
    )),
);

$failures = array();
foreach ($cases as $case) {
    list($action, $data) = $case;
    $modular = mutation_tx($modx, new modxMCP($modx), $action, $data);
    $legacy = mutation_tx($modx, mutation_legacy($modx), $action, $data);

    $a = mutation_normalize($modular);
    $b = mutation_normalize($legacy);
    if ($a !== $b) {
        $failures[] = array(
            'action' => $action,
            'modular' => $a,
            'legacy' => $b,
        );
        echo "DIFF {$action}\n";
    } else {
        echo "OK {$action}\n";
    }
}

if (!empty($failures)) {
    echo json_encode($failures, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}

echo "MUTATION_PARITY_OK " . count($cases) . " checks across 40 processor mutations\n";
