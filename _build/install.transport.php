<?php

use MODX\Revolution\modNamespace;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modX;
use MODX\Revolution\Transport\modTransportPackage;
/**
 * CLI-only TEST/DEV helper for installing or uninstalling a locally built transport package.
 * It never prints the API token and never changes modxmcp.enabled.
 *
 * Usage:
 *   php _build/install.transport.php
 *   php _build/install.transport.php --sig=modx3mcp-1.0.0-pl
 *   php _build/install.transport.php --action=uninstall --sig=modx3mcp-1.0.0-pl
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Transport verifier is CLI-only.\n");
}

set_time_limit(0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

$config = getenv('MODX_CONFIG_CORE');
if (!$config || !file_exists($config)) {
    $dir = dirname(__FILE__);
    for ($i = 0; $i < 12; $i++) {
        if (file_exists($dir . '/config.core.php')) { $config = $dir . '/config.core.php'; break; }
        $parent = dirname($dir); if ($parent === $dir) break; $dir = $parent;
    }
}
if (!$config) { die("config.core.php not found\n"); }
require_once $config;
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$modx = modX::getInstance();
$modx->initialize('mgr');

$signature = 'modx3mcp-1.0.0-pl';
$action = 'install';
foreach ($argv as $arg) {
    if (strpos($arg, '--sig=') === 0) {
        $signature = preg_replace('/[^a-zA-Z0-9._-]/', '', substr($arg, 6));
    } elseif (strpos($arg, '--action=') === 0) {
        $action = substr($arg, 9);
    }
}
if (!in_array($action, array('install', 'uninstall'), true)) {
    fwrite(STDERR, "Invalid --action. Use install or uninstall.\n");
    exit(2);
}

if ($action === 'uninstall') {
    $pkg = $modx->getObject(modTransportPackage::class, array('signature' => $signature));
    if (!$pkg) { echo "no package record for $signature\n"; exit; }
    $un = $pkg->uninstall();
    echo 'uninstall(): ' . ($un ? 'OK' : 'FAILED') . "\n";
    $pkg->remove();
    $modx->getCacheManager()->refresh();
    echo "package record removed\n";
    exit;
}

$package = $modx->getObject(modTransportPackage::class, array('signature' => $signature));
if (!$package) {
    $package = $modx->newObject(modTransportPackage::class);
    $package->set('signature', $signature);
    $package->set('state', 1);
    $package->set('created', date('Y-m-d H:i:s'));
    $package->set('workspace', 1);
    $sig = explode('-', $signature);
    $package->set('package_name', $sig[0]);
    $vparts = explode('.', isset($sig[1]) ? $sig[1] : '1.0.0');
    $package->set('version_major', isset($vparts[0]) ? $vparts[0] : 1);
    $package->set('version_minor', isset($vparts[1]) ? $vparts[1] : 0);
    $package->set('version_patch', isset($vparts[2]) ? $vparts[2] : 0);
    if (!empty($sig[2])) {
        $rel = preg_split('/([0-9]+)/', $sig[2], -1, PREG_SPLIT_DELIM_CAPTURE);
        $package->set('release', $rel[0]);
        $package->set('release_index', isset($rel[1]) ? $rel[1] : 0);
    }
    $package->save();
    echo "package record created\n";
} else {
    echo "package record exists\n";
}

$ok = $package->install();
echo 'install(): ' . ($ok ? 'OK' : 'FAILED') . "\n";

$modx->getCacheManager()->refresh();

$ns = $modx->getObject(modNamespace::class, array('name' => 'modxmcp'));
echo 'namespace modxmcp: ' . ($ns ? 'yes' : 'NO') . "\n";
echo 'modxmcp.* settings: ' . $modx->getCount(modSystemSetting::class, array('key:LIKE' => 'modxmcp.%')) . "\n";

$token = $modx->getObject(modSystemSetting::class, array('key' => 'modxmcp.api_token'));
$tv = $token ? (string) $token->get('value') : '';
echo 'api_token: ' . ($tv !== '' ? ('set, ' . strlen($tv) . ' chars') : 'EMPTY') . "\n";

$en = $modx->getObject(modSystemSetting::class, array('key' => 'modxmcp.enabled'));
echo 'enabled (default): ' . ($en ? var_export($en->get('value'), true) : '?') . "\n";

echo 'file assets/.../api.php: ' . (file_exists(MODX_ASSETS_PATH . 'components/modxmcp/api.php') ? 'yes' : 'NO') . "\n";
echo 'file core/.../modxmcp.class.php: ' . (file_exists(MODX_CORE_PATH . 'components/modxmcp/model/modxmcp.class.php') ? 'yes' : 'NO') . "\n";

echo "Token value is intentionally not printed by this verifier.\n";