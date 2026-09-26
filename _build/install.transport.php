<?php

use MODX\Revolution\modNamespace;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modUser;
use MODX\Revolution\modX;
use MODX\Revolution\Transport\modTransportPackage;

/**
 * CLI helper for installing/uninstalling a built transport package during release tests.
 *
 * It is intentionally CLI-only. It never enables the API implicitly and never prints the
 * full API token unless --show-token is explicitly supplied.
 *
 * Usage:
 *   php _build/install.transport.php
 *   php _build/install.transport.php --signature=modxmcp3-1.9.0-pl
 *   php _build/install.transport.php --action=uninstall --signature=...
 *   php _build/install.transport.php --show-token
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
    fwrite(STDERR, "Cannot find config.core.php. Set MODX_CONFIG_CORE to its full path.\n");
    exit(2);
}

if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = dirname($config);
}

require_once $config;
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$modx = modX::getInstance();
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');

$signature = strtolower(PKG_NAME) . '-' . PKG_VERSION . '-' . PKG_RELEASE;
$action = 'install';
$showToken = false;

foreach ($argv as $arg) {
    if (strpos($arg, '--signature=') === 0) {
        $candidate = substr($arg, strlen('--signature='));
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $candidate)) {
            fwrite(STDERR, "Invalid package signature.\n");
            exit(2);
        }
        $signature = $candidate;
    } elseif (strpos($arg, '--action=') === 0) {
        $action = strtolower(substr($arg, strlen('--action=')));
    } elseif ($arg === '--show-token') {
        $showToken = true;
    }
}

if (!in_array($action, array('install', 'uninstall'), true)) {
    fwrite(STDERR, "--action must be install or uninstall.\n");
    exit(2);
}

$package = $modx->getObject(modTransportPackage::class, array('signature' => $signature));

if ($action === 'uninstall') {
    if (!$package) {
        fwrite(STDERR, "No package record for {$signature}.\n");
        exit(3);
    }

    $ok = $package->uninstall();
    echo 'uninstall(): ' . ($ok ? 'OK' : 'FAILED') . "\n";
    if (!$ok) {
        exit(1);
    }

    $modx->getCacheManager()->refresh();
    exit(0);
}

if (!$package) {
    $archive = MODX_CORE_PATH . 'packages/' . $signature . '.transport.zip';
    if (!is_file($archive)) {
        fwrite(STDERR, "Transport archive not found: {$archive}\n");
        exit(3);
    }

    $package = $modx->newObject(modTransportPackage::class);
    $package->set('signature', $signature);
    $package->set('state', 1);
    $package->set('created', date('Y-m-d H:i:s'));
    $package->set('workspace', 1);

    $parts = explode('-', $signature);
    $package->set('package_name', isset($parts[0]) ? $parts[0] : strtolower(PKG_NAME));
    $version = isset($parts[1]) ? $parts[1] : PKG_VERSION;
    $vparts = explode('.', $version);
    $package->set('version_major', isset($vparts[0]) ? (int)$vparts[0] : 0);
    $package->set('version_minor', isset($vparts[1]) ? (int)$vparts[1] : 0);
    $package->set('version_patch', isset($vparts[2]) ? (int)$vparts[2] : 0);

    if (isset($parts[2]) && $parts[2] !== '') {
        $releaseParts = preg_split('/([0-9]+)/', $parts[2], -1, PREG_SPLIT_DELIM_CAPTURE);
        $package->set('release', isset($releaseParts[0]) ? $releaseParts[0] : 'pl');
        $package->set('release_index', isset($releaseParts[1]) ? (int)$releaseParts[1] : 0);
    }

    if (!$package->save()) {
        fwrite(STDERR, "Failed to create transport package record.\n");
        exit(1);
    }
    echo "package record created\n";
} else {
    echo "package record exists\n";
}

$ok = $package->install();
echo 'install(): ' . ($ok ? 'OK' : 'FAILED') . "\n";
if (!$ok) {
    exit(1);
}

$modx->getCacheManager()->refresh();

$namespace = $modx->getObject(modNamespace::class, array('name' => 'modxmcp'));
echo 'namespace modxmcp: ' . ($namespace ? 'yes' : 'NO') . "\n";

$settingCount = $modx->getCount(modSystemSetting::class, array('key:LIKE' => 'modxmcp.%'));
echo 'modxmcp.* settings: ' . $settingCount . "\n";

$tokenSetting = $modx->getObject(modSystemSetting::class, array('key' => 'modxmcp.api_token'));
$token = $tokenSetting ? trim((string)$tokenSetting->get('value')) : '';
if ($token === '') {
    echo "api_token: EMPTY\n";
} elseif ($showToken) {
    echo "api_token: {$token}\n";
} else {
    $preview = strlen($token) > 12 ? substr($token, 0, 6) . '...' . substr($token, -4) : '[set]';
    echo 'api_token: set, ' . strlen($token) . " chars ({$preview})\n";
}

$enabledSetting = $modx->getObject(modSystemSetting::class, array('key' => 'modxmcp.enabled'));
$enabled = $enabledSetting ? (bool)$enabledSetting->get('value') : false;
echo 'enabled: ' . ($enabled ? 'yes' : 'no') . "\n";

$serviceSetting = $modx->getObject(modSystemSetting::class, array('key' => 'modxmcp.service_user_id'));
$serviceUserId = $serviceSetting ? (int)$serviceSetting->get('value') : 0;
$serviceUser = $serviceUserId > 0 ? $modx->getObject(modUser::class, $serviceUserId) : null;
$serviceUsable = $serviceUser && (bool)$serviceUser->get('active') && (bool)$serviceUser->get('sudo');
echo 'service_user_id: ' . ($serviceUserId > 0 ? (string)$serviceUserId : 'not configured') .
    ' (' . ($serviceUsable ? 'active sudo' : 'NOT USABLE') . ")\n";

echo 'file assets/.../api.php: ' .
    (is_file(MODX_ASSETS_PATH . 'components/modxmcp/api.php') ? 'yes' : 'NO') . "\n";
echo 'file core/.../modxmcp.class.php: ' .
    (is_file(MODX_CORE_PATH . 'components/modxmcp/model/modxmcp.class.php') ? 'yes' : 'NO') . "\n";

if (!$namespace || $settingCount === 0 || $token === '' || !$serviceUsable) {
    fwrite(STDERR, "Transport verification FAILED. Review the checks above before enabling/using the endpoint.\n");
    exit(1);
}

echo "Transport verification OK.\n";