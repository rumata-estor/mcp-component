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

function pkg_legacy($modx)
{
    $mcp = new modxMCP($modx);
    $property = new ReflectionProperty('modxMCP', 'modularRuntime');
    $property->setAccessible(true);
    $property->setValue($mcp, null);
    return $mcp;
}

function pkg_call($mcp, $action, array $data)
{
    try {
        return array('ok' => true, 'value' => $mcp->processRequest($action, '', $data));
    } catch (Throwable $e) {
        return array('ok' => false, 'error' => $e->getMessage());
    }
}

function pkg_normalize($value)
{
    if (!is_array($value)) { return $value; }
    $out = array();
    foreach ($value as $key => $item) {
        if ((string)$key === 'id' && (is_int($item) || ctype_digit((string)$item))) {
            $out[$key] = '__ID__';
            continue;
        }
        $out[$key] = pkg_normalize($item);
    }
    ksort($out);
    return $out;
}

function pkg_compare($label, $a, $b, &$failures)
{
    $a = pkg_normalize($a);
    $b = pkg_normalize($b);
    if ($a !== $b) {
        $failures[] = array('case' => $label, 'modular' => $a, 'legacy' => $b);
        echo "DIFF {$label}\n";
    } else {
        echo "OK {$label}\n";
    }
}

function pkg_tx($modx, $mcp, $action, array $data, $setup = null)
{
    $modx->beginTransaction();
    try {
        if ($setup) { $data = call_user_func($setup, $modx, $data); }
        return pkg_call($mcp, $action, $data);
    } finally {
        $modx->rollback();
    }
}

function pkg_setup_provider($modx, array $data)
{
    $provider = $modx->newObject(
        \MODX\Revolution\Transport\modTransportProvider::class
    );
    $provider->set('name', '__modxmcp_provider_existing__');
    $provider->set('service_url', 'https://example.invalid/');
    if (!$provider->save()) {
        throw new RuntimeException('Could not create temporary provider.');
    }
    $data['id'] = (int)$provider->get('id');
    return $data;
}

$failures = array();

$providerCreate = array(
    'name' => '__modxmcp_provider_create__',
    'service_url' => 'https://example.invalid/',
);
pkg_compare(
    'create_provider',
    pkg_tx($modx, new modxMCP($modx), 'create_provider', $providerCreate),
    pkg_tx($modx, pkg_legacy($modx), 'create_provider', $providerCreate),
    $failures
);

$setup = function ($modx, $data) { return pkg_setup_provider($modx, $data); };
pkg_compare(
    'update_provider',
    pkg_tx(
        $modx,
        new modxMCP($modx),
        'update_provider',
        array('name' => '__modxmcp_provider_updated__'),
        $setup
    ),
    pkg_tx(
        $modx,
        pkg_legacy($modx),
        'update_provider',
        array('name' => '__modxmcp_provider_updated__'),
        $setup
    ),
    $failures
);
pkg_compare(
    'delete_provider',
    pkg_tx($modx, new modxMCP($modx), 'delete_provider', array(), $setup),
    pkg_tx($modx, pkg_legacy($modx), 'delete_provider', array(), $setup),
    $failures
);

// Safe validation paths: no download/install/uninstall is attempted.
pkg_compare(
    'install_package:validation',
    pkg_call(new modxMCP($modx), 'install_package', array()),
    pkg_call(pkg_legacy($modx), 'install_package', array()),
    $failures
);
pkg_compare(
    'uninstall_package:validation',
    pkg_call(new modxMCP($modx), 'uninstall_package', array()),
    pkg_call(pkg_legacy($modx), 'uninstall_package', array()),
    $failures
);

// Safe positive install path: an already-installed package returns before network/download.
$provider = $modx->getObject(
    \MODX\Revolution\Transport\modTransportProvider::class,
    array('id:>' => 0)
);
$installed = $modx->getObject(
    \MODX\Revolution\Transport\modTransportPackage::class,
    array('installed:!=' => null)
);
if ($provider && $installed && $installed->get('package_name')) {
    $data = array(
        'package' => $installed->get('package_name'),
        'provider' => (int)$provider->get('id'),
    );
    pkg_compare(
        'install_package:already_installed',
        pkg_call(new modxMCP($modx), 'install_package', $data),
        pkg_call(pkg_legacy($modx), 'install_package', $data),
        $failures
    );
} else {
    echo "SKIP install_package:already_installed (no installed package/provider)\n";
}

if ($failures) {
    echo json_encode($failures, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}
echo "PACKAGE_MUTATION_PARITY_OK 5/5 actions\n";
