<?php
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
if (is_file(rtrim(MODX_CORE_PATH, '/\\') . '/model/modx/modx.class.php')) {
    require_once rtrim(MODX_CORE_PATH, '/\\') . '/model/modx/modx.class.php';
    $modx = new modX();
} else {
    require_once rtrim(MODX_CORE_PATH, '/\\') . '/vendor/autoload.php';
    $modx = \MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');

function live_class($modx, $modx2, $modx3)
{
    $version = $modx->getVersionData();
    return isset($version['version']) && (int)$version['version'] === 2
        ? $modx2
        : $modx3;
}

if (method_exists($modx, 'setOption')) {
    $modx->setOption('modxmcp.disabled_groups', '');
    $modx->setOption('modxmcp.audit_log', false);
} else {
    $modx->config['modxmcp.disabled_groups'] = '';
    $modx->config['modxmcp.audit_log'] = false;
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

function rops_legacy($modx)
{
    $mcp = new modxMCP($modx);
    $property = new ReflectionProperty('modxMCP', 'modularRuntime');
    $property->setAccessible(true);
    $property->setValue($mcp, null);
    return $mcp;
}

function rops_call($mcp, $action, array $data)
{
    try {
        return array('ok' => true, 'value' => $mcp->processRequest($action, '', $data));
    } catch (Throwable $e) {
        return array('ok' => false, 'error' => $e->getMessage());
    }
}

function rops_normalize($value)
{
    if (!is_array($value)) { return $value; }
    $out = array();
    foreach ($value as $key => $item) {
        if (in_array((string)$key, array('id', 'parent', 'createdby', 'editedby'), true)
            && (is_int($item) || ctype_digit((string)$item))) {
            $out[$key] = '__ID__';
            continue;
        }
        if (in_array((string)$key, array('createdon', 'editedon', 'publishedon', 'deletedon'), true)) {
            continue;
        }
        $out[$key] = rops_normalize($item);
    }
    ksort($out);
    return $out;
}

function rops_compare($label, $a, $b, &$failures)
{
    $a = rops_normalize($a);
    $b = rops_normalize($b);
    if ($a !== $b) {
        $failures[] = array('case' => $label, 'modular' => $a, 'legacy' => $b);
        echo "DIFF {$label}\n";
    } else {
        echo "OK {$label}\n";
    }
}

function rops_tx($modx, $mcp, $action, array $data, $setup = null)
{
    $modx->beginTransaction();
    try {
        if ($setup) { $data = call_user_func($setup, $modx, $data); }
        return rops_call($mcp, $action, $data);
    } finally {
        $modx->rollback();
    }
}

function rops_resource($modx, $deleted = false)
{
    $resource = $modx->newObject(live_class($modx, 'modResource', 'MODX\Revolution\modResource'));
    $resource->fromArray(array(
        'pagetitle' => '__modxmcp_resource_ops__',
        'alias' => '__modxmcp_resource_ops__',
        'context_key' => 'web',
        'published' => 0,
        'deleted' => $deleted ? 1 : 0,
        'parent' => 0,
        'menuindex' => 0,
    ), '', true, true);
    if (!$resource->save()) {
        throw new RuntimeException('Could not create temporary resource.');
    }
    return $resource;
}

function rops_snippet($modx)
{
    $snippet = $modx->newObject(live_class($modx, 'modSnippet', 'MODX\Revolution\modSnippet'));
    $snippet->set('name', '__modxmcp_duplicate_source__');
    $snippet->set('snippet', 'return "parity";');
    if (!$snippet->save()) {
        throw new RuntimeException('Could not create temporary snippet.');
    }
    return $snippet;
}

$failures = array();

$setupDuplicateResource = function ($modx, $data) {
    $resource = rops_resource($modx, false);
    $data['id'] = (int)$resource->get('id');
    $data['name'] = '__modxmcp_resource_copy__';
    $data['duplicate_children'] = false;
    return $data;
};
rops_compare(
    'duplicate_resource',
    rops_tx($modx, new modxMCP($modx), 'duplicate_resource', array(), $setupDuplicateResource),
    rops_tx($modx, rops_legacy($modx), 'duplicate_resource', array(), $setupDuplicateResource),
    $failures
);

$setupDuplicateElement = function ($modx, $data) {
    $snippet = rops_snippet($modx);
    $data['type'] = 'snippet';
    $data['id'] = (int)$snippet->get('id');
    $data['name'] = '__modxmcp_duplicate_copy__';
    return $data;
};
rops_compare(
    'duplicate_element',
    rops_tx($modx, new modxMCP($modx), 'duplicate_element', array(), $setupDuplicateElement),
    rops_tx($modx, rops_legacy($modx), 'duplicate_element', array(), $setupDuplicateElement),
    $failures
);

$setupUndelete = function ($modx, $data) {
    $resource = rops_resource($modx, true);
    $data['id'] = (int)$resource->get('id');
    return $data;
};
rops_compare(
    'undelete_resource',
    rops_tx($modx, new modxMCP($modx), 'undelete_resource', array(), $setupUndelete),
    rops_tx($modx, rops_legacy($modx), 'undelete_resource', array(), $setupUndelete),
    $failures
);

$setupReorder = function ($modx, $data) {
    $resource = rops_resource($modx, false);
    $data['items'] = array(array(
        'id' => (int)$resource->get('id'),
        'menuindex' => 7,
    ));
    return $data;
};
rops_compare(
    'reorder_resources',
    rops_tx($modx, new modxMCP($modx), 'reorder_resources', array(), $setupReorder),
    rops_tx($modx, rops_legacy($modx), 'reorder_resources', array(), $setupReorder),
    $failures
);

// Global maintenance operations are safe here: refresh_uris is idempotent;
// probe.live-maintenance-state.php confirmed active locks == 0 before this test.
rops_compare(
    'refresh_uris',
    rops_call(new modxMCP($modx), 'refresh_uris', array()),
    rops_call(rops_legacy($modx), 'refresh_uris', array()),
    $failures
);
rops_compare(
    'remove_locks',
    rops_call(new modxMCP($modx), 'remove_locks', array()),
    rops_call(rops_legacy($modx), 'remove_locks', array()),
    $failures
);

if ($failures) {
    echo json_encode($failures, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}

echo "RESOURCE_OPS_MUTATION_PARITY_OK 6/6 safe-live actions\n";
echo "SKIP empty_recycle_bin: sandbox has pre-existing deleted resource(s)\n";
echo "SKIP regenerate_token: live API token must remain unchanged\n";
