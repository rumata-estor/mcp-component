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

function ms2_legacy($modx)
{
    $mcp = new modxMCP($modx);
    $property = new ReflectionProperty('modxMCP', 'modularRuntime');
    $property->setAccessible(true);
    $property->setValue($mcp, null);
    return $mcp;
}

function ms2_call($mcp, $action, array $data)
{
    try {
        return array(
            'ok' => true,
            'value' => $mcp->processRequest($action, '', $data),
        );
    } catch (Throwable $e) {
        return array(
            'ok' => false,
            'class' => get_class($e),
            'error' => $e->getMessage(),
        );
    }
}

function ms2_normalize($value)
{
    if (!is_array($value)) { return $value; }
    $out = array();
    foreach ($value as $key => $item) {
        if (in_array((string)$key, array('id', 'resource', 'createdby', 'editedby'), true)
            && (is_int($item) || ctype_digit((string)$item))) {
            $out[$key] = '__ID__';
            continue;
        }
        if (in_array((string)$key, array('createdon', 'editedon', 'publishedon'), true)) {
            continue;
        }
        $out[$key] = ms2_normalize($item);
    }
    ksort($out);
    return $out;
}

function ms2_compare($label, $a, $b, &$failures)
{
    $a = ms2_normalize($a);
    $b = ms2_normalize($b);
    if ($a !== $b) {
        $failures[] = array(
            'case' => $label,
            'modular' => $a,
            'legacy' => $b,
        );
        echo "DIFF {$label}\n";
    } else {
        echo "OK {$label}\n";
    }
}

function ms2_tx($modx, $mcp, $action, array $data)
{
    $modx->beginTransaction();
    try {
        $result = ms2_call($mcp, $action, $data);
    } finally {
        $modx->rollback();
        if ($modx->getCacheManager()) {
            $modx->getCacheManager()->refresh();
        }
    }
    return $result;
}

$failures = array();

// Positive create paths, each fully rolled back.
$option = array(
    'key' => '__modxmcp_parity_option__',
    'caption' => 'modxMCP parity option',
    'type' => 'textfield',
    'description' => 'temporary',
);
ms2_compare(
    'ms2_create_option',
    ms2_tx($modx, new modxMCP($modx), 'ms2_create_option', $option),
    ms2_tx($modx, ms2_legacy($modx), 'ms2_create_option', $option),
    $failures
);

$link = array(
    'name' => '__modxmcp_parity_link__',
    'type' => 'many_to_many',
    'description' => 'temporary',
);
ms2_compare(
    'ms2_create_link_type',
    ms2_tx($modx, new modxMCP($modx), 'ms2_create_link_type', $link),
    ms2_tx($modx, ms2_legacy($modx), 'ms2_create_link_type', $link),
    $failures
);

$category = array(
    'pagetitle' => '__modxmcp_parity_category__',
    'parent' => 0,
    'published' => 0,
    'alias' => '__modxmcp_parity_category__',
);
ms2_compare(
    'ms2_create_category',
    ms2_tx($modx, new modxMCP($modx), 'ms2_create_category', $category),
    ms2_tx($modx, ms2_legacy($modx), 'ms2_create_category', $category),
    $failures
);

// Safe missing-object contracts for the remaining mutations.
$cases = array(
    array('ms2_update_option', array('id' => 999999, 'caption' => 'x')),
    array('ms2_assign_option_to_category', array(
        'option_id' => 999999,
        'category_id' => 999999,
    )),
    array('ms2_update_link_type', array(
        'id' => 999999,
        'name' => 'x',
        'type' => 'many_to_many',
    )),
    array('ms2_delete_link_type', array('id' => 999999)),
    array('ms2_create_product_link', array(
        'link' => 999999,
        'master' => 999999,
        'slave' => 999998,
    )),
    array('ms2_delete_product_link', array(
        'link' => 999999,
        'master' => 999999,
        'slave' => 999998,
    )),
    array('ms2_update_category', array(
        'id' => 999999,
        'pagetitle' => 'x',
    )),
    array('ms2_update_order', array(
        'id' => 999999,
        'status' => 999999,
    )),
    array('ms2_update_product_options', array(
        'product_id' => 999999,
        'options' => array('__modxmcp_missing__' => 'x'),
    )),
);

foreach ($cases as $case) {
    list($action, $data) = $case;
    ms2_compare(
        $action,
        ms2_call(new modxMCP($modx), $action, $data),
        ms2_call(ms2_legacy($modx), $action, $data),
        $failures
    );
}

if ($failures) {
    echo json_encode(
        $failures,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    ) . "\n";
    exit(1);
}

echo "MINISHOP2_MUTATION_PARITY_OK 12/12 actions\n";
