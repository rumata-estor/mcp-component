<?php

use MODX\Revolution\modNamespace;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modX;
use MODX\Revolution\Transport\modTransportPackage;

/**
 * CLI-only transport-package installer/verifier for release testing.
 *
 * Usage:
 *   MODX_CONFIG_CORE=/path/config.core.php php _build/install.transport.php
 *   MODX_CONFIG_CORE=/path/config.core.php php _build/install.transport.php --signature=modx3mcp-1.0.0-pl
 *   MODX_CONFIG_CORE=/path/config.core.php php _build/install.transport.php --action=uninstall
 *   ... --show-token
 *
 * The transport archive must already exist in MODX_CORE_PATH/packages/.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Transport test installer is CLI-only.\n");
}

set_time_limit(0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

require_once __DIR__ . '/build.config.php';

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
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }
}
if (!$config || !is_file($config)) {
    fwrite(STDERR, "config.core.php not found; set MODX_CONFIG_CORE.\n");
    exit(2);
}

require_once $config;
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$modx = modX::getInstance();
$modx->initialize('mgr');

$args = array_slice($argv, 1);
$options = array();
foreach ($args as $arg) {
    if ($arg === '--show-token') {
        $options['show-token'] = true;
        continue;
    }
    if (strpos($arg, '--') === 0 && strpos($arg, '=') !== false) {
        list($key, $value) = explode('=', substr($arg, 2), 2);
        $options[$key] = $value;
    }
}

$defaultSignature = strtolower(PKG_NAME) . '-' . PKG_VERSION . '-' . PKG_RELEASE;
$signature = isset($options['signature']) ? preg_replace('/[^a-zA-Z0-9._-]/', '', $options['signature']) : $defaultSignature;
$action = isset($options['action']) ? strtolower((string)$options['action']) : 'install';
if (!in_array($action, array('install', 'uninstall'), true)) {
    fwrite(STDERR, "Unsupported --action. Use install or uninstall.\n");
    exit(2);
}

$package = $modx->getObject(modTransportPackage::class, array('signature' => $signature));

if ($action === 'uninstall') {
    if (!$package) {
        fwrite(STDERR, "No installed package record for {$signature}.\n");
        exit(3);
    }
    $ok = $package->uninstall();
    echo 'uninstall(): ' . ($ok ? 'OK' : 'FAILED') . PHP_EOL;
    if (!$ok) {
        exit(1);
    }
    $package->remove();
    if ($modx->getCacheManager()) {
        $modx->getCacheManager()->refresh();
    }
    echo "package record removed\n";
    exit(0);
}

$archive = rtrim(MODX_CORE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . $signature . '.transport.zip';
if (!is_file($archive)) {
    fwrite(STDERR, "Transport archive not found: {$archive}\n");
    exit(4);
}

if (!$package) {
    $package = $modx->newObject(modTransportPackage::class);
    $package->set('signature', $signature);
    $package->set('state', 1);
    $package->set('created', date('Y-m-d H:i:s'));
    $package->set('workspace', 1);
    $package->set('package_name', PKG_NAME);
    $package->set('version_major', (int)explode('.', PKG_VERSION)[0]);
    $parts = array_pad(explode('.', PKG_VERSION), 3, 0);
    $package->set('version_minor', (int)$parts[1]);
    $package->set('version_patch', (int)$parts[2]);
    $package->set('release', PKG_RELEASE);
    $package->set('release_index', 0);
    if (!$package->save()) {
        fwrite(STDERR, "Could not create package record for {$signature}.\n");
        exit(5);
    }
    echo "package record created\n";
} else {
    echo "package record exists\n";
}

$ok = $package->install();
echo 'install(): ' . ($ok ? 'OK' : 'FAILED') . PHP_EOL;
if (!$ok) {
    exit(1);
}

if ($modx->getCacheManager()) {
    $modx->getCacheManager()->refresh();
}

$ns = $modx->getObject(modNamespace::class, array('name' => 'modxmcp'));
$countSettings = $modx->getCount(modSystemSetting::class, array('key:LIKE' => 'modxmcp.%'));
$tokenSetting = $modx->getObject(modSystemSetting::class, array('key' => 'modxmcp.api_token'));
$token = $tokenSetting ? trim((string)$tokenSetting->get('value')) : '';
$enabledSetting = $modx->getObject(modSystemSetting::class, array('key' => 'modxmcp.enabled'));

echo 'signature: ' . $signature . PHP_EOL;
echo 'namespace modxmcp: ' . ($ns ? 'yes' : 'NO') . PHP_EOL;
echo 'modxmcp.* settings: ' . $countSettings . PHP_EOL;
echo 'enabled: ' . ($enabledSetting ? var_export($enabledSetting->get('value'), true) : 'missing') . PHP_EOL;
echo 'api_token: ' . ($token !== '' ? ('set, ' . strlen($token) . ' chars') : 'EMPTY') . PHP_EOL;
if (!empty($options['show-token']) && $token !== '') {
    echo 'TOKEN=' . $token . PHP_EOL;
}
echo 'file assets/api.php: ' . (is_file(MODX_ASSETS_PATH . 'components/modxmcp/api.php') ? 'yes' : 'NO') . PHP_EOL;
echo 'file core/model: ' . (is_file(MODX_CORE_PATH . 'components/modxmcp/model/modxmcp.class.php') ? 'yes' : 'NO') . PHP_EOL;

if (!$ns || $countSettings < 1 || $token === '' ||
    !is_file(MODX_ASSETS_PATH . 'components/modxmcp/api.php') ||
    !is_file(MODX_CORE_PATH . 'components/modxmcp/model/modxmcp.class.php')) {
    fwrite(STDERR, "Transport verification FAILED.\n");
    exit(6);
}

echo "TRANSPORT_VERIFY_OK\n";