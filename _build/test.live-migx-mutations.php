<?php
use MODX\Revolution\modX;

if (PHP_SAPI !== 'cli') { exit(2); }
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
if (!$config || !is_file($config)) { exit(2); }
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
    $modx->setOption('modxmcp.audit_log', false);
} else {
    $modx->config['modxmcp.disabled_groups'] = '';
    $modx->config['modxmcp.audit_log'] = false;
}

$corePath = $modx->getOption('modxmcp.core_path', null, $modx->getOption('core_path') . 'components/modxmcp/');
$corePath = str_replace(
    array('{core_path}', '[[++core_path]]'),
    rtrim((string)$modx->getOption('core_path'), '/\\') . DIRECTORY_SEPARATOR,
    (string)$corePath
);
require_once $corePath . 'model/modxmcp.class.php';

function migx_legacy($modx)
{
    $mcp = new modxMCP($modx);
    $property = new ReflectionProperty('modxMCP', 'modularRuntime');
    $property->setAccessible(true);
    $property->setValue($mcp, null);
    return $mcp;
}

function migx_call($mcp, $action, array $data)
{
    try {
        return array('ok' => true, 'value' => $mcp->processRequest($action, '', $data));
    } catch (Throwable $e) {
        return array('ok' => false, 'error' => $e->getMessage());
    }
}

function migx_normalize($value)
{
    if (!is_array($value)) { return $value; }
    $out = array();
    foreach ($value as $key => $item) {
        if ((string)$key === 'id' && (is_int($item) || ctype_digit((string)$item))) {
            $out[$key] = '__ID__';
            continue;
        }
        $out[$key] = migx_normalize($item);
    }
    ksort($out);
    return $out;
}

function migx_compare($label, $a, $b, &$failures)
{
    $a = migx_normalize($a);
    $b = migx_normalize($b);
    if ($a !== $b) {
        $failures[] = array('case' => $label, 'modular' => $a, 'legacy' => $b);
        echo "DIFF {$label}\n";
    } else {
        echo "OK {$label}\n";
    }
}

function migx_tx($modx, $mcp, $action, array $data, $setup = null)
{
    $modx->beginTransaction();
    try {
        if ($setup) { $data = call_user_func($setup, $modx, $data); }
        return migx_call($mcp, $action, $data);
    } finally {
        $modx->rollback();
    }
}

$core = $modx->getOption(
    'migx.core_path',
    null,
    $modx->getOption('core_path') . 'components/migx/'
);
$modx->addPackage('migx', $core . 'model/');
if (!$modx->loadClass('migxConfig')) {
    fwrite(STDERR, "MIGX not installed\n");
    exit(2);
}

$setup = function ($modx, $data) {
    $config = $modx->newObject('migxConfig');
    $config->set('name', '__modxmcp_migx_existing__');
    $config->set('category', 'parity');
    $config->set('published', 1);
    if (!$config->save()) {
        throw new RuntimeException('Could not create temporary MIGX config.');
    }
    $data['id'] = (int)$config->get('id');
    return $data;
};

$failures = array();

$create = array(
    'name' => '__modxmcp_migx_create__',
    'category' => 'parity',
    'published' => 1,
    'formtabs' => '[]',
    'columns' => '[]',
);
migx_compare(
    'migx_create_config',
    migx_tx($modx, new modxMCP($modx), 'migx_create_config', $create),
    migx_tx($modx, migx_legacy($modx), 'migx_create_config', $create),
    $failures
);

migx_compare(
    'migx_update_config',
    migx_tx(
        $modx,
        new modxMCP($modx),
        'migx_update_config',
        array('category' => 'after', 'published' => 0),
        $setup
    ),
    migx_tx(
        $modx,
        migx_legacy($modx),
        'migx_update_config',
        array('category' => 'after', 'published' => 0),
        $setup
    ),
    $failures
);

migx_compare(
    'migx_delete_config',
    migx_tx($modx, new modxMCP($modx), 'migx_delete_config', array(), $setup),
    migx_tx($modx, migx_legacy($modx), 'migx_delete_config', array(), $setup),
    $failures
);

if ($failures) {
    echo json_encode($failures, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}
echo "MIGX_MUTATION_PARITY_OK 3/3 actions\n";
