<?php
use MODX\Revolution\modX;

if (PHP_SAPI !== 'cli') { exit(2); }

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
if (!$config || !is_file($config)) { fwrite(STDERR, "config.core.php not found\n"); exit(2); }
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

$corePath = $modx->getOption('modxmcp.core_path', null, $modx->getOption('core_path') . 'components/modxmcp/');
$corePath = str_replace(
    array('{core_path}', '[[++core_path]]'),
    rtrim((string)$modx->getOption('core_path'), '/\\') . DIRECTORY_SEPARATOR,
    (string)$corePath
);
require_once $corePath . 'model/modxmcp.class.php';

function legacy_mcp($modx)
{
    $mcp = new modxMCP($modx);
    $property = new ReflectionProperty('modxMCP', 'modularRuntime');
    $property->setAccessible(true);
    $property->setValue($mcp, null);
    return $mcp;
}

function call_action($mcp, $action, array $data)
{
    try {
        return array('ok' => true, 'value' => $mcp->processRequest($action, '', $data));
    } catch (Throwable $e) {
        return array('ok' => false, 'error' => $e->getMessage());
    }
}

function tx_call($modx, $mcp, $action, array $data, $setup = null)
{
    $modx->beginTransaction();
    try {
        if ($setup) { $data = call_user_func($setup, $modx, $data); }
        return call_action($mcp, $action, $data);
    } finally {
        $modx->rollback();
    }
}

function normalize_value($value)
{
    if (!is_array($value)) { return $value; }
    $out = array();
    foreach ($value as $key => $item) {
        if (in_array((string)$key, array('id', 'resource_id', 'template_id'), true)
            && (is_int($item) || ctype_digit((string)$item))) {
            $out[$key] = '__ID__';
            continue;
        }
        $out[$key] = normalize_value($item);
    }
    ksort($out);
    return $out;
}

function compare_case($label, $a, $b, &$failures)
{
    $a = normalize_value($a);
    $b = normalize_value($b);
    if ($a !== $b) {
        $failures[] = array('case' => $label, 'modular' => $a, 'legacy' => $b);
        echo "DIFF {$label}\n";
    } else {
        echo "OK {$label}\n";
    }
}

$failures = array();
$key = '__modxmcp_ops_parity_setting__';

// System setting: positive create, update, delete — each fully rolled back.
$create = array('key' => $key, 'value' => 'one', 'namespace' => 'core', 'area' => 'modxmcp');
compare_case(
    'create_system_setting',
    tx_call($modx, new modxMCP($modx), 'create_system_setting', $create),
    tx_call($modx, legacy_mcp($modx), 'create_system_setting', $create),
    $failures
);

$setupSetting = function ($modx, $data) use ($key) {
    $class = \MODX\Revolution\modSystemSetting::class;
    $obj = $modx->newObject($class);
    $obj->fromArray(array(
        'key' => $key,
        'value' => 'before',
        'xtype' => 'textfield',
        'namespace' => 'core',
        'area' => 'modxmcp',
    ), '', true, true);
    $obj->save();
    return $data;
};
compare_case(
    'update_system_setting',
    tx_call($modx, new modxMCP($modx), 'update_system_setting', array('key' => $key, 'value' => 'after'), $setupSetting),
    tx_call($modx, legacy_mcp($modx), 'update_system_setting', array('key' => $key, 'value' => 'after'), $setupSetting),
    $failures
);
compare_case(
    'delete_system_setting',
    tx_call($modx, new modxMCP($modx), 'delete_system_setting', array('key' => $key), $setupSetting),
    tx_call($modx, legacy_mcp($modx), 'delete_system_setting', array('key' => $key), $setupSetting),
    $failures
);

// TV actions: validate exact failure/preview contracts without touching live values.
compare_case(
    'update_resource_tvs',
    call_action(new modxMCP($modx), 'update_resource_tvs', array('resource_id' => 999999, 'tvs' => array('__x__' => 'y'))),
    call_action(legacy_mcp($modx), 'update_resource_tvs', array('resource_id' => 999999, 'tvs' => array('__x__' => 'y'))),
    $failures
);
compare_case(
    'clear_tv_values',
    call_action(new modxMCP($modx), 'clear_tv_values', array('tv_id' => 999999)),
    call_action(legacy_mcp($modx), 'clear_tv_values', array('tv_id' => 999999)),
    $failures
);

// Cache and permissions are intentionally safe/idempotent.
compare_case(
    'clear_cache',
    call_action(new modxMCP($modx), 'clear_cache', array()),
    call_action(legacy_mcp($modx), 'clear_cache', array()),
    $failures
);
compare_case(
    'flush_permissions',
    call_action(new modxMCP($modx), 'flush_permissions', array()),
    call_action(legacy_mcp($modx), 'flush_permissions', array()),
    $failures
);

// run_processor must remain independently gated off by default.
compare_case(
    'run_processor',
    call_action(new modxMCP($modx), 'run_processor', array('processor' => 'system/clearcache')),
    call_action(legacy_mcp($modx), 'run_processor', array('processor' => 'system/clearcache')),
    $failures
);

if ($modx->getObject(\MODX\Revolution\modSystemSetting::class, array('key' => $key))) {
    fwrite(STDERR, "Temporary system setting survived rollback\n");
    exit(1);
}

if ($failures) {
    echo json_encode($failures, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}
echo "OPS_MUTATION_PARITY_OK 8/8 actions\n";
