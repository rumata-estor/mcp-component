<?php


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


// Some MODX installations build all paths from $_SERVER['DOCUMENT_ROOT'] even in
// config.core.php. CLI normally leaves it empty, which would turn paths into /core/,
// /assets/, etc. Allow an explicit root and otherwise infer it from config.core.php.
$documentRoot = trim((string)getenv('MODX_DOCUMENT_ROOT'));
if ($documentRoot !== '') {
    $documentRoot = rtrim($documentRoot, '/\\');
    if (!is_file($documentRoot . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'model' . DIRECTORY_SEPARATOR . 'modx' . DIRECTORY_SEPARATOR . 'modx.class.php')) {
        fwrite(STDERR, "MODX_DOCUMENT_ROOT does not look like a MODX web root: {$documentRoot}\n");
        exit(2);
    }
    $_SERVER['DOCUMENT_ROOT'] = $documentRoot;
} elseif (PHP_SAPI === 'cli' && empty($_SERVER['DOCUMENT_ROOT'])) {
    $probe = dirname((string)(realpath($config) ?: $config));
    for ($i = 0; $i < 12; $i++) {
        $autoload = $probe . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'model' . DIRECTORY_SEPARATOR . 'modx' . DIRECTORY_SEPARATOR . 'modx.class.php';
        if (is_file($autoload)) {
            $_SERVER['DOCUMENT_ROOT'] = rtrim($probe, '/\\');
            break;
        }
        $parent = dirname($probe);
        if ($parent === $probe) {
            break;
        }
        $probe = $parent;
    }
}

require_once $config;
if (!defined('MODX_CORE_PATH') || !is_file(rtrim(MODX_CORE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'model' . DIRECTORY_SEPARATOR . 'modx' . DIRECTORY_SEPARATOR . 'modx.class.php')) {
    fwrite(STDERR, "MODX bootstrap failed: MODX_CORE_PATH/model/modx/modx.class.php not found. Set MODX_DOCUMENT_ROOT when config.core.php depends on DOCUMENT_ROOT.\n");
    exit(2);
}
require_once rtrim(MODX_CORE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'model' . DIRECTORY_SEPARATOR . 'modx' . DIRECTORY_SEPARATOR . 'modx.class.php';

$modx = new modX();
$modx->initialize('mgr');
if (PHP_SAPI === 'cli' && (!isset($_SESSION) || !is_array($_SESSION))) {
    $_SESSION = array();
}
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');

$versionData = $modx->getVersionData();
$fullVersion = isset($versionData['full_version']) ? (string)$versionData['full_version'] : '';
if ($fullVersion === '' || version_compare($fullVersion, '2.8.0', '<') || version_compare($fullVersion, '3.0.0', '>=')) {
    fwrite(STDERR, "modxMCP for MODX 2 requires MODX Revolution >=2.8,<3; detected: " . ($fullVersion !== '' ? $fullVersion : 'unknown') . "\n");
    exit(3);
}

$sourceCore = $root . 'core/components/modxmcp';
$sourceAssets = $root . 'assets/components/modxmcp';
$targetCore = rtrim(MODX_CORE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'modxmcp';
$targetAssets = rtrim(MODX_ASSETS_PATH, '/\\') . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'modxmcp';

if (!is_dir($sourceCore) || !is_dir($sourceAssets)) {
    fwrite(STDERR, "Source component directories are missing. Run this script from the modxMCP repository.\n");
    exit(2);
}

$removeTree = static function ($path) use (&$removeTree) {
    if (is_link($path) || is_file($path)) {
        if (!unlink($path)) {
            throw new RuntimeException("Cannot remove file: {$path}");
        }
        return;
    }
    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path);
    if ($items === false) {
        throw new RuntimeException("Cannot read directory for cleanup: {$path}");
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

function modxmcpSecureRandomBytes($length) {
    if (function_exists('random_bytes')) {
        try { return random_bytes($length); } catch (Exception $e) { /* fallback below */ }
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        $strong = false;
        $bytes = openssl_random_pseudo_bytes($length, $strong);
        if ($strong && is_string($bytes) && strlen($bytes) === $length) { return $bytes; }
    }
    throw new RuntimeException('No cryptographically secure random source available.');
}

// Prepare complete replacement trees first, then swap them into place. This avoids
// leaving a half-copied component if a copy fails. Previous trees remain available
// until the whole installer has completed successfully.
try {
    $deployId = getmypid() . '-' . bin2hex(modxmcpSecureRandomBytes(6));
} catch (Exception $e) {
    fwrite(STDERR, "Cannot create a secure deployment id: " . $e->getMessage() . "\n");
    exit(1);
}

$coreParent = dirname($targetCore);
$assetsParent = dirname($targetAssets);
$stageCore = $coreParent . DIRECTORY_SEPARATOR . '.modxmcp-stage-' . $deployId;
$stageAssets = $assetsParent . DIRECTORY_SEPARATOR . '.modxmcp-stage-' . $deployId;
$backupCore = $coreParent . DIRECTORY_SEPARATOR . '.modxmcp-previous-' . $deployId;
$backupAssets = $assetsParent . DIRECTORY_SEPARATOR . '.modxmcp-previous-' . $deployId;

$hadCore = file_exists($targetCore) || is_link($targetCore);
$hadAssets = file_exists($targetAssets) || is_link($targetAssets);
$coreTouched = false;
$assetsTouched = false;
$deployCommitted = false;
$dbTransactionOpen = false;

$pathExists = static function ($path) {
    return file_exists($path) || is_link($path);
};

$restoreTree = static function ($target, $backup, $hadOriginal) use ($removeTree, $pathExists) {
    try {
        if ($pathExists($target)) {
            $removeTree($target);
        }
        if ($hadOriginal && $pathExists($backup)) {
            if (!rename($backup, $target)) {
                throw new RuntimeException("Cannot restore {$backup} -> {$target}");
            }
        }
    } catch (Exception $rollbackError) {
        fwrite(STDERR, "HEADLESS ROLLBACK FAILED: " . $rollbackError->getMessage() . "\n");
    }
};

register_shutdown_function(static function () use (
    &$deployCommitted,
    &$dbTransactionOpen,
    &$coreTouched,
    &$assetsTouched,
    $targetCore,
    $targetAssets,
    $backupCore,
    $backupAssets,
    $stageCore,
    $stageAssets,
    $hadCore,
    $hadAssets,
    $restoreTree,
    $removeTree,
    $pathExists,
    $modx
) {
    if ($deployCommitted) {
        return;
    }

    if ($dbTransactionOpen) {
        try {
            $modx->rollback();
        } catch (Exception $rollbackError) {
            fwrite(STDERR, "HEADLESS DB ROLLBACK FAILED: " . $rollbackError->getMessage() . "\n");
        }
        $dbTransactionOpen = false;
    }

    // Restore assets first, then core, reversing the deployment order.
    if ($assetsTouched) {
        $restoreTree($targetAssets, $backupAssets, $hadAssets);
    }
    if ($coreTouched) {
        $restoreTree($targetCore, $backupCore, $hadCore);
    }

    foreach (array($stageCore, $stageAssets) as $stage) {
        if ($pathExists($stage)) {
            try {
                $removeTree($stage);
            } catch (Exception $cleanupError) {
                fwrite(STDERR, "HEADLESS STAGING CLEANUP FAILED: " . $cleanupError->getMessage() . "\n");
            }
        }
    }
});

try {
    foreach (array($stageCore, $stageAssets, $backupCore, $backupAssets) as $transient) {
        if ($pathExists($transient)) {
            throw new RuntimeException("Transient deployment path already exists: {$transient}");
        }
    }
    if (is_link($targetCore) || is_link($targetAssets)) {
        throw new RuntimeException('Refusing to deploy into a symlinked component directory.');
    }

    $copyTree($sourceCore, $stageCore);
    $copyTree($sourceAssets, $stageAssets);

    // Preserve the runtime audit log across headless updates.
    $runtimeLogs = $targetCore . DIRECTORY_SEPARATOR . 'logs';
    if (is_dir($runtimeLogs)) {
        $copyTree($runtimeLogs, $stageCore . DIRECTORY_SEPARATOR . 'logs');
    }

    if ($hadCore) {
        if (!rename($targetCore, $backupCore)) {
            throw new RuntimeException("Cannot move current core component to backup.");
        }
        $coreTouched = true;
    }
    if (!rename($stageCore, $targetCore)) {
        throw new RuntimeException("Cannot activate staged core component.");
    }
    $coreTouched = true;

    if ($hadAssets) {
        if (!rename($targetAssets, $backupAssets)) {
            throw new RuntimeException("Cannot move current assets component to backup.");
        }
        $assetsTouched = true;
    }
    if (!rename($stageAssets, $targetAssets)) {
        throw new RuntimeException("Cannot activate staged assets component.");
    }
    $assetsTouched = true;
} catch (Exception $e) {
    fwrite(STDERR, "File deployment failed: " . $e->getMessage() . "\n");
    exit(1);
}

try {
    $modx->beginTransaction();
    $dbTransactionOpen = true;
} catch (Exception $e) {
    fwrite(STDERR, "Could not start MODX database transaction: " . $e->getMessage() . "\n");
    exit(1);
}

$namespace = $modx->getObject('modNamespace', array('name' => 'modxmcp'));
if (!$namespace) {
    $namespace = $modx->newObject('modNamespace');
    $namespace->set('name', 'modxmcp');
}
$namespace->set('path', '{core_path}components/modxmcp/');
$namespace->set('assets_path', '{assets_path}components/modxmcp/');
if (!$namespace->save()) {
    fwrite(STDERR, "Failed to save modxmcp namespace.\n");
    exit(1);
}

// Keep the CLI installation functionally aligned with the transport package:
// the component and dependency graph must be available in MODX Manager as well.
$menus = array(
    'modxmcp' => array(
        'parent' => 'components',
        'description' => 'modxmcp_menu_desc',
        'menuindex' => 0,
        'action' => 'index',
    ),
    'modxmcp_graph' => array(
        'parent' => 'modxmcp',
        'description' => 'modxmcp_graph_desc',
        'menuindex' => 1,
        'action' => 'graph',
    ),
);
foreach ($menus as $text => $definition) {
    $menu = $modx->getObject('modMenu', array('text' => $text));
    if (!$menu) {
        $menu = $modx->newObject('modMenu');
        $menu->set('text', $text);
    }
    $menu->fromArray(array(
        'parent' => $definition['parent'],
        'description' => $definition['description'],
        'icon' => '',
        'menuindex' => $definition['menuindex'],
        'params' => '',
        'handler' => '',
        'action' => $definition['action'],
        'namespace' => 'modxmcp',
    ), '', true, true);
    if (!$menu->save()) {
        fwrite(STDERR, "Failed to save manager menu: {$text}\n");
        exit(1);
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
    'modxmcp.trust_proxy_https' => array(0, 'combo-boolean', 'modxmcp:security'),
    'modxmcp.allowed_ips' => array('', 'textfield', 'modxmcp:security'),
    'modxmcp.component_code_roots' => array('core/components,assets/components', 'textfield', 'modxmcp:security'),
    'modxmcp.core_path' => array('{core_path}components/modxmcp/', 'textfield', 'modxmcp:paths'),
);

foreach ($settings as $key => $definition) {
    $setting = $modx->getObject('modSystemSetting', array('key' => $key));
    if (!$setting) {
        $setting = $modx->newObject('modSystemSetting');
        $setting->set('key', $key);
        $setting->set('value', $definition[0]);
    }
    $setting->set('xtype', $definition[1]);
    $setting->set('namespace', 'modxmcp');
    $setting->set('area', $definition[2]);

    if (!$setting->save()) {
        fwrite(STDERR, "Failed to save system setting: {$key}\n");
        exit(1);
    }
}

$tokenSetting = $modx->getObject('modSystemSetting', array('key' => 'modxmcp.api_token'));
if (!$tokenSetting) {
    fwrite(STDERR, "Required system setting modxmcp.api_token is missing after installation.\n");
    exit(1);
}
$token = trim((string) $tokenSetting->get('value'));
$tokenGenerated = false;
if ($token === '') {
    try {
        $token = bin2hex(modxmcpSecureRandomBytes(32));
    } catch (Exception $e) {
        fwrite(STDERR, "Cannot generate a cryptographically secure API token: " . $e->getMessage() . "\n");
        exit(1);
    }
    $tokenSetting->set('value', $token);
    if (!$tokenSetting->save()) {
        fwrite(STDERR, "Failed to save generated API token.\n");
        exit(1);
    }
    $tokenGenerated = true;
}

$enabled = $modx->getObject('modSystemSetting', array('key' => 'modxmcp.enabled'));
if (!$enabled) {
    fwrite(STDERR, "Required system setting modxmcp.enabled is missing after installation.\n");
    exit(1);
}
if ((string) $enabled->get('value') === '') {
    $enabled->set('value', 1);
    if (!$enabled->save()) {
        fwrite(STDERR, "Failed to initialize modxmcp.enabled.\n");
        exit(1);
    }
}

try {
    if ($modx->commit() === false) {
        throw new RuntimeException('MODX database transaction commit returned false.');
    }
    $dbTransactionOpen = false;
} catch (Exception $e) {
    fwrite(STDERR, "Could not commit MODX database transaction: " . $e->getMessage() . "\n");
    exit(1);
}

// File swap and all MODX object writes are now committed. From this point the
// shutdown handler must not restore the previous component tree.
$deployCommitted = true;
foreach (array($backupCore, $backupAssets, $stageCore, $stageAssets) as $transient) {
    if ($pathExists($transient)) {
        try {
            $removeTree($transient);
        } catch (Exception $cleanupError) {
            fwrite(STDERR, "HEADLESS POST-INSTALL CLEANUP WARNING: " . $cleanupError->getMessage() . "\n");
        }
    }
}

if ($modx->getCacheManager()) {
    $modx->getCacheManager()->refresh();
}

$siteUrl = rtrim((string) $modx->getOption('site_url'), '/');
$endpoint = $siteUrl . '/assets/components/modxmcp/api.php';

echo "\nmodxMCP headless install/update complete.\n";
echo "Package Manager record created by this installer: no (manual/headless install)\n";
echo "Manager menu created/updated: yes\n";
echo "Core files: {$targetCore}\n";
echo "Assets files: {$targetAssets}\n";
echo "Endpoint: {$endpoint}\n";
$showToken = in_array('--show-token', $argv, true);
if ($showToken) {
    echo "Token: {$token}\n";
} else {
    $preview = strlen($token) > 12 ? substr($token, 0, 6) . '...' . substr($token, -4) : '[set]';
    echo "Token: {$preview} (use --show-token to print the full value)\n";
}
echo "Variant: modx2\n";