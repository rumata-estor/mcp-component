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

function legacy_ps_mcp($modx)
{
    $mcp = new modxMCP($modx);
    $property = new ReflectionProperty('modxMCP', 'modularRuntime');
    $property->setAccessible(true);
    $property->setValue($mcp, null);
    return $mcp;
}

function ps_call($mcp, $action, array $data)
{
    try {
        return array('ok' => true, 'value' => $mcp->processRequest($action, '', $data));
    } catch (Throwable $e) {
        return array('ok' => false, 'error' => $e->getMessage());
    }
}

function ps_normalize($value)
{
    if (!is_array($value)) { return $value; }
    $out = array();
    foreach ($value as $key => $item) {
        if (in_array((string)$key, array('id', 'element', 'property_set'), true)
            && (is_int($item) || ctype_digit((string)$item))) {
            $out[$key] = '__ID__';
            continue;
        }
        $out[$key] = ps_normalize($item);
    }
    ksort($out);
    return $out;
}

function ps_compare($label, $a, $b, &$failures)
{
    $a = ps_normalize($a);
    $b = ps_normalize($b);
    if ($a !== $b) {
        $failures[] = array('case' => $label, 'modular' => $a, 'legacy' => $b);
        echo "DIFF {$label}\n";
    } else {
        echo "OK {$label}\n";
    }
}

function ps_tx($modx, $mcp, $action, array $data, $setup = null)
{
    $modx->beginTransaction();
    try {
        if ($setup) { $data = call_user_func($setup, $modx, $data); }
        return ps_call($mcp, $action, $data);
    } finally {
        $modx->rollback();
    }
}

function ps_setup_property_set($modx, array $data)
{
    $ps = $modx->newObject(\MODX\Revolution\modPropertySet::class);
    $ps->set('name', '__modxmcp_ps_existing__');
    $ps->set('description', 'before');
    $ps->save();
    $data['id'] = (int)$ps->get('id');
    return $data;
}

function ps_setup_assignment($modx, array $data, $assigned)
{
    $ps = $modx->newObject(\MODX\Revolution\modPropertySet::class);
    $ps->set('name', '__modxmcp_ps_assign__');
    $ps->save();

    $snippet = $modx->newObject(\MODX\Revolution\modSnippet::class);
    $snippet->set('name', '__modxmcp_ps_snippet__');
    $snippet->set('snippet', 'return true;');
    $snippet->save();

    if ($assigned) {
        $link = $modx->newObject(\MODX\Revolution\modElementPropertySet::class);
        $link->fromArray(array(
            'element' => (int)$snippet->get('id'),
            'element_class' => \MODX\Revolution\modSnippet::class,
            'property_set' => (int)$ps->get('id'),
        ), '', true, true);
        $link->save();
    }

    $data['element'] = (int)$snippet->get('id');
    $data['property_set'] = (int)$ps->get('id');
    $data['element_type'] = 'snippet';
    return $data;
}

$failures = array();

$create = array(
    'name' => '__modxmcp_ps_create__',
    'description' => 'parity',
    'properties' => array('foo' => array('value' => 'bar')),
);
ps_compare(
    'create_property_set',
    ps_tx($modx, new modxMCP($modx), 'create_property_set', $create),
    ps_tx($modx, legacy_ps_mcp($modx), 'create_property_set', $create),
    $failures
);

$setup = function ($modx, $data) { return ps_setup_property_set($modx, $data); };
ps_compare(
    'update_property_set',
    ps_tx($modx, new modxMCP($modx), 'update_property_set', array('description' => 'after'), $setup),
    ps_tx($modx, legacy_ps_mcp($modx), 'update_property_set', array('description' => 'after'), $setup),
    $failures
);
ps_compare(
    'delete_property_set',
    ps_tx($modx, new modxMCP($modx), 'delete_property_set', array(), $setup),
    ps_tx($modx, legacy_ps_mcp($modx), 'delete_property_set', array(), $setup),
    $failures
);

$setupAssign = function ($modx, $data) { return ps_setup_assignment($modx, $data, false); };
ps_compare(
    'assign_property_set',
    ps_tx($modx, new modxMCP($modx), 'assign_property_set', array(), $setupAssign),
    ps_tx($modx, legacy_ps_mcp($modx), 'assign_property_set', array(), $setupAssign),
    $failures
);

$setupUnassign = function ($modx, $data) { return ps_setup_assignment($modx, $data, true); };
ps_compare(
    'unassign_property_set',
    ps_tx($modx, new modxMCP($modx), 'unassign_property_set', array(), $setupUnassign),
    ps_tx($modx, legacy_ps_mcp($modx), 'unassign_property_set', array(), $setupUnassign),
    $failures
);

if ($modx->getObject(\MODX\Revolution\modPropertySet::class, array('name:LIKE' => '__modxmcp_ps_%'))) {
    fwrite(STDERR, "Temporary property set survived rollback\n");
    exit(1);
}
if ($failures) {
    echo json_encode($failures, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}
echo "PROPERTY_SET_MUTATION_PARITY_OK 5/5 actions\n";
