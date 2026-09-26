<?php

use MODX\Revolution\modMenu;
use MODX\Revolution\modNamespace;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modUser;
use MODX\Revolution\modX;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Headless installer is CLI-only. Run: php _build/install.headless.php\n");
}

set_time_limit(0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

$root = dirname(__DIR__) . DIRECTORY_SEPARATOR;
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

// Some hosting environments leave DOCUMENT_ROOT empty for CLI processes,
// while config.core.php derives MODX_CORE_PATH from it.
if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = dirname($config);
}

require_once $config;
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$modx = modX::getInstance();
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');

$requestedServiceUserId = null;
foreach ($argv as $i => $arg) {
    if (strpos($arg, '--service-user-id=') === 0) {
        $requestedServiceUserId = (int)substr($arg, strlen('--service-user-id='));
        break;
    }
    if ($arg === '--service-user-id' && isset($argv[$i + 1])) {
        $requestedServiceUserId = (int)$argv[$i + 1];
        break;
    }
}

$isUsableServiceUser = static function ($user) {
    return $user instanceof modUser && (bool)$user->get('active') && (bool)$user->get('sudo');
};

$resolvedServiceUser = null;
if ($requestedServiceUserId !== null) {
    if ($requestedServiceUserId <= 0) {
        fwrite(STDERR, "--service-user-id must be a positive MODX user ID.\n");
        exit(2);
    }
    $resolvedServiceUser = $modx->getObject(modUser::class, $requestedServiceUserId);
    if (!$isUsableServiceUser($resolvedServiceUser)) {
        fwrite(STDERR, "Requested service user must exist, be active and have sudo=1.\n");
        exit(2);
    }
} else {
    $existingServiceSetting = $modx->getObject(modSystemSetting::class, array('key' => 'modxmcp.service_user_id'));
    $existingServiceUserId = $existingServiceSetting ? (int)$existingServiceSetting->get('value') : 0;
    if ($existingServiceUserId > 0) {
        $existingServiceUser = $modx->getObject(modUser::class, $existingServiceUserId);
        if ($isUsableServiceUser($existingServiceUser)) {
            $resolvedServiceUser = $existingServiceUser;
        }
    }

    if (!$resolvedServiceUser) {
        $q = $modx->newQuery(modUser::class);
        $q->where(array('active' => 1, 'sudo' => 1));
        $q->sortby('id', 'ASC');
        $q->limit(2);
        $sudoUsers = array_values($modx->getCollection(modUser::class, $q));
        if (count($sudoUsers) === 1 && $isUsableServiceUser($sudoUsers[0])) {
            $resolvedServiceUser = $sudoUsers[0];
        }
    }
}

if (!$resolvedServiceUser) {
    fwrite(
        STDERR,
        "Cannot choose a service user safely. Re-run with --service-user-id=<ID> for an active sudo MODX user.\n"
    );
    exit(2);
}
$resolvedServiceUserId = (int)$resolvedServiceUser->get('id');

$sourceCore = $root . 'core/components/modxmcp';
$sourceAssets = $root . 'assets/components/modxmcp';
$targetCore = rtrim(MODX_CORE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'modxmcp';
$targetAssets = rtrim(MODX_ASSETS_PATH, '/\\') . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'modxmcp';

if (!is_dir($sourceCore) || !is_dir($sourceAssets)) {
    fwrite(STDERR, "Source component directories are missing. Run this script from the modxMCP repository.\n");
    exit(2);
}

$removeTree = static function ($path) use (&$removeTree) {
    if (is_dir($path) && !is_link($path)) {
        $items = scandir($path);
        if ($items === false) {
            throw new RuntimeException("Cannot read directory: {$path}");
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $removeTree($path . DIRECTORY_SEPARATOR . $item);
        }
        if (!rmdir($path)) {
            throw new RuntimeException("Cannot remove directory: {$path}");
        }
        return;
    }

    if (file_exists($path) || is_link($path)) {
        if (!unlink($path)) {
            throw new RuntimeException("Cannot remove file: {$path}");
        }
    }
};

$isPreservedPath = static function ($relative, array $prefixes) {
    $relative = trim(str_replace('\\', '/', $relative), '/');
    foreach ($prefixes as $prefix) {
        $prefix = trim(str_replace('\\', '/', $prefix), '/');
        if ($prefix === '') {
            continue;
        }
        if ($relative === $prefix || strpos($relative, $prefix . '/') === 0) {
            return true;
        }
    }
    return false;
};

$pruneTree = static function ($sourceRoot, $targetRoot, $relative, array $preservePrefixes) use (&$pruneTree, $removeTree, $isPreservedPath) {
    if (!is_dir($targetRoot)) {
        return;
    }

    $targetDir = $relative === ''
        ? $targetRoot
        : $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (!is_dir($targetDir)) {
        return;
    }

    $items = scandir($targetDir);
    if ($items === false) {
        throw new RuntimeException("Cannot read directory: {$targetDir}");
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $childRelative = ltrim(($relative === '' ? '' : $relative . '/') . $item, '/');
        if ($isPreservedPath($childRelative, $preservePrefixes)) {
            continue;
        }

        $sourcePath = $sourceRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $childRelative);
        $targetPath = $targetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $childRelative);

        if (!file_exists($sourcePath) && !is_link($sourcePath)) {
            $removeTree($targetPath);
            continue;
        }

        if (is_dir($sourcePath) !== is_dir($targetPath)) {
            $removeTree($targetPath);
            continue;
        }

        if (is_dir($targetPath)) {
            $pruneTree($sourceRoot, $targetRoot, $childRelative, $preservePrefixes);
        }
    }
};

$copyTree = static function ($source, $target) use (&$copyTree) {
    if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
        throw new RuntimeException("Cannot create directory: {$target}");
    }

    $items = scandir($source);
    if ($items === false) {
        throw new RuntimeException("Cannot read directory: {$source}");
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $src = $source . DIRECTORY_SEPARATOR . $item;
        $dst = $target . DIRECTORY_SEPARATOR . $item;

        if (is_dir($src)) {
            $copyTree($src, $dst);
        } else {
            if (!copy($src, $dst)) {
                throw new RuntimeException("Cannot copy {$src} -> {$dst}");
            }
        }
    }
};

$backupRoot = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'modx3mcp-headless-' . uniqid('', true);
$coreExisted = is_dir($targetCore);
$assetsExisted = is_dir($targetAssets);

$namespaceBefore = $modx->getObject(modNamespace::class, array('name' => 'modxmcp'));
$namespaceSnapshot = $namespaceBefore ? array(
    'exists' => true,
    'path' => (string)$namespaceBefore->get('path'),
    'assets_path' => (string)$namespaceBefore->get('assets_path'),
) : array('exists' => false);

$menuFields = array('text', 'parent', 'action', 'description', 'namespace', 'permissions', 'menuindex', 'params', 'handler', 'icon');
$menuSnapshots = array();
foreach (array('modxmcp', 'modxmcp_graph') as $menuKey) {
    $menuObject = $modx->getObject(modMenu::class, array('text' => $menuKey));
    if (!$menuObject) {
        $menuSnapshots[$menuKey] = array('exists' => false);
        continue;
    }
    $snapshot = array('exists' => true);
    foreach ($menuFields as $field) {
        $snapshot[$field] = $menuObject->get($field);
    }
    $menuSnapshots[$menuKey] = $snapshot;
}

$settingsSnapshot = array();
$existingSettings = $modx->getCollection(modSystemSetting::class, array('key:LIKE' => 'modxmcp.%'));
foreach ($existingSettings as $existingSetting) {
    $key = (string)$existingSetting->get('key');
    $settingsSnapshot[$key] = array(
        'value' => $existingSetting->get('value'),
        'xtype' => $existingSetting->get('xtype'),
        'namespace' => $existingSetting->get('namespace'),
        'area' => $existingSetting->get('area'),
    );
}

try {
    if (!mkdir($backupRoot, 0700, true) && !is_dir($backupRoot)) {
        throw new RuntimeException("Cannot create temporary backup directory: {$backupRoot}");
    }
    if ($coreExisted) {
        $copyTree($targetCore, $backupRoot . DIRECTORY_SEPARATOR . 'core');
    }
    if ($assetsExisted) {
        $copyTree($targetAssets, $backupRoot . DIRECTORY_SEPARATOR . 'assets');
    }
} catch (Throwable $e) {
    try {
        $removeTree($backupRoot);
    } catch (Throwable $cleanupError) {
        // Best effort only; no installation mutation has happened yet.
    }
    fwrite(STDERR, "Cannot create pre-install backup: " . $e->getMessage() . "\n");
    exit(1);
}

$rollback = static function () use (
    $modx,
    $targetCore,
    $targetAssets,
    $backupRoot,
    $coreExisted,
    $assetsExisted,
    $settingsSnapshot,
    $namespaceSnapshot,
    $menuSnapshots,
    $menuFields,
    $removeTree,
    $copyTree
) {
    // Restore component files exactly to the pre-install existence state.
    if (file_exists($targetCore) || is_link($targetCore)) {
        $removeTree($targetCore);
    }
    if ($coreExisted) {
        $copyTree($backupRoot . DIRECTORY_SEPARATOR . 'core', $targetCore);
    }

    if (file_exists($targetAssets) || is_link($targetAssets)) {
        $removeTree($targetAssets);
    }
    if ($assetsExisted) {
        $copyTree($backupRoot . DIRECTORY_SEPARATOR . 'assets', $targetAssets);
    }

    // Remove settings created by the failed run, then restore all previous modxmcp.* values.
    $currentSettings = $modx->getCollection(modSystemSetting::class, array('key:LIKE' => 'modxmcp.%'));
    foreach ($currentSettings as $currentSetting) {
        $key = (string)$currentSetting->get('key');
        if (!array_key_exists($key, $settingsSnapshot)) {
            $currentSetting->remove();
        }
    }
    foreach ($settingsSnapshot as $key => $data) {
        $setting = $modx->getObject(modSystemSetting::class, array('key' => $key));
        if (!$setting) {
            $setting = $modx->newObject(modSystemSetting::class);
            $setting->set('key', $key);
        }
        $setting->set('value', $data['value']);
        $setting->set('xtype', $data['xtype']);
        $setting->set('namespace', $data['namespace']);
        $setting->set('area', $data['area']);
        $setting->save();
    }

    $namespace = $modx->getObject(modNamespace::class, array('name' => 'modxmcp'));
    if (!empty($namespaceSnapshot['exists'])) {
        if (!$namespace) {
            $namespace = $modx->newObject(modNamespace::class);
            $namespace->set('name', 'modxmcp');
        }
        $namespace->set('path', $namespaceSnapshot['path']);
        $namespace->set('assets_path', $namespaceSnapshot['assets_path']);
        $namespace->save();
    } elseif ($namespace) {
        $namespace->remove();
    }

    foreach ($menuSnapshots as $menuKey => $snapshot) {
        $menu = $modx->getObject(modMenu::class, array('text' => $menuKey));
        if (!empty($snapshot['exists'])) {
            if (!$menu) {
                $menu = $modx->newObject(modMenu::class);
            }
            foreach ($menuFields as $field) {
                $menu->set($field, $snapshot[$field]);
            }
            $menu->save();
        } elseif ($menu) {
            $menu->remove();
        }
    }

    if ($modx->getCacheManager()) {
        $modx->getCacheManager()->refresh();
    }
};

try {
    // Keep runtime audit logs, but remove obsolete shipped component files.
    $pruneTree($sourceCore, $targetCore, '', array('logs'));
    $pruneTree($sourceAssets, $targetAssets, '', array());
    $copyTree($sourceCore, $targetCore);
    $copyTree($sourceAssets, $targetAssets);

$namespace = $modx->getObject(modNamespace::class, array('name' => 'modxmcp'));
if (!$namespace) {
    $namespace = $modx->newObject(modNamespace::class);
    $namespace->set('name', 'modxmcp');
}
$namespace->set('path', '{core_path}components/modxmcp/');
$namespace->set('assets_path', '{assets_path}components/modxmcp/');
if (!$namespace->save()) {
    throw new RuntimeException("Failed to save modxmcp namespace.");
}

$menus = array(
    'modxmcp' => array(
        'text' => 'modxmcp',
        'parent' => 'components',
        'description' => 'modxmcp_menu_desc',
        'icon' => '',
        'menuindex' => 0,
        'params' => '',
        'handler' => '',
        'action' => 'index',
        'namespace' => 'modxmcp',
        'permissions' => 'settings',
    ),
    'modxmcp_graph' => array(
        'text' => 'modxmcp_graph',
        'parent' => 'modxmcp',
        'description' => 'modxmcp_graph_desc',
        'icon' => '',
        'menuindex' => 1,
        'params' => '',
        'handler' => '',
        'action' => 'graph',
        'namespace' => 'modxmcp',
        'permissions' => 'settings',
    ),
);
foreach ($menus as $menuKey => $menuData) {
    $menu = $modx->getObject(modMenu::class, array('text' => $menuKey));
    if (!$menu) {
        $menu = $modx->newObject(modMenu::class);
    }
    foreach ($menuData as $field => $value) {
        $menu->set($field, $value);
    }
    if (!$menu->save()) {
        throw new RuntimeException("Failed to save manager menu: {$menuKey}");
    }
}

$settings = array(
    'modxmcp.enabled' => array(1, 'combo-boolean', 'modxmcp:main'),
    'modxmcp.api_token' => array('', 'textfield', 'modxmcp:main'),
    'modxmcp.service_user_id' => array(0, 'textfield', 'modxmcp:main'),
    'modxmcp.audit_log' => array(1, 'combo-boolean', 'modxmcp:main'),
    'modxmcp.debug' => array(0, 'combo-boolean', 'modxmcp:main'),
    'modxmcp.auto_static' => array(0, 'combo-boolean', 'modxmcp:main'),
    'modxmcp.disabled_groups' => array(
        'versionx,virtualpage,minishop2,migx,access,property_sets,contexts,package_management,namespaces,lexicon',
        'textfield',
        'modxmcp:main'
    ),
    'modxmcp.allow_run_processor' => array(0, 'combo-boolean', 'modxmcp:security'),
    'modxmcp.max_payload_bytes' => array(1048576, 'textfield', 'modxmcp:limits'),
    'modxmcp.max_read_bytes' => array(262144, 'textfield', 'modxmcp:limits'),
    'modxmcp.allow_root_filesystem_read' => array(0, 'combo-boolean', 'modxmcp:security'),
    'modxmcp.require_https' => array(1, 'combo-boolean', 'modxmcp:security'),
    'modxmcp.allowed_ips' => array('', 'textfield', 'modxmcp:security'),
    'modxmcp.trusted_proxy_ips' => array('', 'textfield', 'modxmcp:security'),
    'modxmcp.component_code_roots' => array('core/components,assets/components', 'textfield', 'modxmcp:security'),
    'modxmcp.core_path' => array('{core_path}components/modxmcp/', 'textfield', 'modxmcp:paths'),
);

foreach ($settings as $key => $definition) {
    $setting = $modx->getObject(modSystemSetting::class, array('key' => $key));
    if (!$setting) {
        $setting = $modx->newObject(modSystemSetting::class);
        $setting->set('key', $key);
        $setting->set('value', $definition[0]);
    }
    $setting->set('xtype', $definition[1]);
    $setting->set('namespace', 'modxmcp');
    $setting->set('area', $definition[2]);

    if (!$setting->save()) {
        throw new RuntimeException("Failed to save system setting: {$key}");
    }
}

$serviceUserSetting = $modx->getObject(modSystemSetting::class, array('key' => 'modxmcp.service_user_id'));
if (!$serviceUserSetting) {
    throw new RuntimeException("Failed to load modxmcp.service_user_id after creating settings.");
}
$serviceUserSetting->set('value', $resolvedServiceUserId);
if (!$serviceUserSetting->save()) {
    throw new RuntimeException("Failed to save modxmcp.service_user_id.");
}

$tokenSetting = $modx->getObject(modSystemSetting::class, array('key' => 'modxmcp.api_token'));
$token = $tokenSetting ? trim((string) $tokenSetting->get('value')) : '';
$tokenGenerated = false;
if ($token === '') {
    try {
        $token = bin2hex(random_bytes(32));
    } catch (Throwable $e) {
        throw new RuntimeException("Secure API token generation failed; installation aborted.", 0, $e);
    }
    $tokenSetting->set('value', $token);
    if (!$tokenSetting->save()) {
        throw new RuntimeException("Failed to save generated API token.");
    }
    $tokenGenerated = true;
}

$enabled = $modx->getObject(modSystemSetting::class, array('key' => 'modxmcp.enabled'));
if ($enabled && (string) $enabled->get('value') === '') {
    $enabled->set('value', 1);
    if (!$enabled->save()) {
        throw new RuntimeException("Failed to save modxmcp.enabled.");
    }
}

if ($modx->getCacheManager()) {
    $modx->getCacheManager()->refresh();
}
} catch (Throwable $e) {
    $rollbackError = null;
    try {
        $rollback();
    } catch (Throwable $restoreException) {
        $rollbackError = $restoreException->getMessage();
    }

    $message = "Headless install/update failed and rollback was attempted: " . $e->getMessage();
    if ($rollbackError !== null) {
        $message .= " | ROLLBACK ERROR: " . $rollbackError;
    }
    fwrite(STDERR, $message . "\n");
    exit(1);
}

try {
    $removeTree($backupRoot);
} catch (Throwable $cleanupError) {
    fwrite(STDERR, "Warning: install succeeded, but temporary backup cleanup failed: " . $cleanupError->getMessage() . "\n");
}

$siteUrl = rtrim((string) $modx->getOption('site_url'), '/');
$endpointPath = '/assets/components/modxmcp/api.php';

echo "\nmodxMCP headless install/update complete.\n";
echo "Package Manager record created by this installer: no\n";
echo "Manager menu created/updated by this installer: yes\n";
echo "Core files: {$targetCore}\n";
echo "Assets files: {$targetAssets}\n";
echo "Endpoint path: {$endpointPath}\n";
if ($siteUrl !== '') {
    echo "Endpoint from MODX site_url: {$siteUrl}{$endpointPath}\n";
}
$showToken = in_array('--show-token', $argv, true);
if ($showToken) {
    echo "Token: {$token}\n";
} else {
    $preview = strlen($token) > 12 ? substr($token, 0, 6) . '...' . substr($token, -4) : '[set]';
    $state = $tokenGenerated ? 'generated' : 'existing';
    echo "Token: {$preview} ({$state}; use --show-token to print the full value)\n";
}
echo "Service user: #{$resolvedServiceUserId}\n";
echo "Variant: modx3\n";