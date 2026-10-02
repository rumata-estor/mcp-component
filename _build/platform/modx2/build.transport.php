<?php
/**
 * modxMCP — transport package builder.
 *
 * Run on a MODX 2.x install from CLI. It locates config.core.php by walking up
 * from this file, or use MODX_CONFIG_CORE to point at it explicitly.
 *
 *   MODX_CONFIG_CORE=/full/path/config.core.php php _build/build.transport.php
 *
 * Produces MODX_CORE_PATH/packages/<signature>.transport.zip.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("MODX MCP transport builder is CLI-only.\n");
}

set_time_limit(0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

require_once dirname(__FILE__) . '/build.config.php';

$root = dirname(dirname(__FILE__)) . '/';          // mcp-component/
$buildDir = $root . '_build/';

/* ---- locate MODX ---- */
$config = getenv('MODX_CONFIG_CORE');
if (!$config || !file_exists($config)) {
    $dir = dirname(__FILE__);
    for ($i = 0; $i < 12; $i++) {
        if (file_exists($dir . '/config.core.php')) { $config = $dir . '/config.core.php'; break; }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
}
if (!$config || !file_exists($config)) {
    die("modxMCP build: cannot find config.core.php. Set the MODX_CONFIG_CORE env var to its full path.\n");
}

// Some MODX installations derive paths from DOCUMENT_ROOT even in CLI.
$documentRoot = trim((string)getenv('MODX_DOCUMENT_ROOT'));
if ($documentRoot !== '') {
    $documentRoot = rtrim($documentRoot, '/\\');
    if (!is_file($documentRoot . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'model' . DIRECTORY_SEPARATOR . 'modx' . DIRECTORY_SEPARATOR . 'modx.class.php')) {
        fwrite(STDERR, "MODX_DOCUMENT_ROOT does not look like a MODX 2 web root: {$documentRoot}\n");
        exit(2);
    }
    $_SERVER['DOCUMENT_ROOT'] = $documentRoot;
} elseif (empty($_SERVER['DOCUMENT_ROOT'])) {
    $probe = dirname((string)(realpath($config) ?: $config));
    for ($i = 0; $i < 12; $i++) {
        $bootstrap = $probe . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'model' . DIRECTORY_SEPARATOR . 'modx' . DIRECTORY_SEPARATOR . 'modx.class.php';
        if (is_file($bootstrap)) {
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
    fwrite(STDERR, "MODX 2 bootstrap failed. Set MODX_DOCUMENT_ROOT when config.core.php depends on DOCUMENT_ROOT.\n");
    exit(2);
}
require_once rtrim(MODX_CORE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'model' . DIRECTORY_SEPARATOR . 'modx' . DIRECTORY_SEPARATOR . 'modx.class.php';

$modx = new modX();
$modx->initialize('mgr');
$versionData = $modx->getVersionData();
$fullVersion = isset($versionData['full_version']) ? (string)$versionData['full_version'] : '';
if ($fullVersion === '' || version_compare($fullVersion, '2.8.0', '<') || version_compare($fullVersion, '3.0.0', '>=')) {
    fwrite(STDERR, "MODX MCP for MODX 2 requires MODX Revolution >=2.8,<3; detected: " . ($fullVersion !== '' ? $fullVersion : 'unknown') . "\n");
    exit(3);
}

$desiredSignature = strtolower(PKG_NAME) . '-' . PKG_VERSION . '-' . PKG_RELEASE;
$installedPackage = $modx->getObject('transport.modTransportPackage', array('signature' => $desiredSignature));
if ($installedPackage && !empty($installedPackage->get('installed'))) {
    fwrite(
        STDERR,
        "Refusing to build {$desiredSignature} on a MODX installation where the same package signature is already installed. " .
        "Use a clean build MODX or uninstall that package first.\n"
    );
    exit(5);
}

$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');
$modx->log(modX::LOG_LEVEL_INFO, 'Building modxMCP ' . PKG_VERSION . '-' . PKG_RELEASE . ' ...');

$modx->loadClass('transport.modPackageBuilder', '', false, true);

$sources = array(
    'resolvers'     => $buildDir . 'resolvers/',
    'data'          => $buildDir . 'data/',
    'source_core'   => $root . 'core/components/' . PKG_NAMESPACE,
    'source_assets' => $root . 'assets/components/' . PKG_NAMESPACE,
    'docs'          => $root,
);

$builder = new modPackageBuilder($modx);
$builder->createPackage(PKG_NAMESPACE, PKG_VERSION, PKG_RELEASE);
$builder->registerNamespace(
    PKG_NAMESPACE,
    false,
    true,
    '{core_path}components/' . PKG_NAMESPACE . '/',
    '{assets_path}components/' . PKG_NAMESPACE . '/'
);

/* ---- system settings ---- */
$settings = include $sources['data'] . 'transport.settings.php';
if (is_array($settings) && !empty($settings)) {
    $attributes = array(
        xPDOTransport::UNIQUE_KEY    => 'key',
        xPDOTransport::PRESERVE_KEYS => true,
        xPDOTransport::UPDATE_OBJECT => false, // do not overwrite admin-edited settings on upgrade
    );
    $firstSettingVehicle = true;
    foreach ($settings as $setting) {
        $vehicle = $builder->createVehicle($setting, $attributes);
        if ($firstSettingVehicle) {
            $vehicle->resolve('php', array('source' => $sources['resolvers'] . 'resolve.permissions.php'));
            $firstSettingVehicle = false;
        }
        $builder->putVehicle($vehicle);
    }
    $modx->log(modX::LOG_LEVEL_INFO, 'Packaged ' . count($settings) . ' system settings.');
}

/* ---- manager menu (Components > modxMCP) ---- */
$menu = $modx->newObject('modMenu');
$menu->fromArray(array(
    'text'        => 'modxmcp',
    'parent'      => 'components',
    'description' => 'modxmcp_menu_desc',
    'icon'        => '',
    'menuindex'   => 0,
    'params'      => '',
    'handler'     => '',
    'action'      => 'index',
    'namespace'   => PKG_NAMESPACE,
), '', true, true);
$menuVehicle = $builder->createVehicle($menu, array(
    xPDOTransport::PRESERVE_KEYS => true,
    xPDOTransport::UPDATE_OBJECT => true,
    xPDOTransport::UNIQUE_KEY    => 'text',
    xPDOTransport::RELATED_OBJECTS => false,
));
$builder->putVehicle($menuVehicle);

/* Second screen: the dependency graph needs the full content region, so it gets its own
   manager action instead of sharing the settings page. */
$menuGraph = $modx->newObject('modMenu');
$menuGraph->fromArray(array(
    'text'        => 'modxmcp_graph',
    'parent'      => 'modxmcp',
    'description' => 'modxmcp_graph_desc',
    'icon'        => '',
    'menuindex'   => 1,
    'params'      => '',
    'handler'     => '',
    'action'      => 'graph',
    'namespace'   => PKG_NAMESPACE,
), '', true, true);
$menuGraphVehicle = $builder->createVehicle($menuGraph, array(
    xPDOTransport::PRESERVE_KEYS => true,
    xPDOTransport::UPDATE_OBJECT => true,
    xPDOTransport::UNIQUE_KEY    => 'text',
    xPDOTransport::RELATED_OBJECTS => false,
));
$builder->putVehicle($menuGraphVehicle);
$modx->log(modX::LOG_LEVEL_INFO, 'Packaged manager menus (Components > modxMCP, + Граф связей).');

/* ---- core files ---- */
$coreVehicle = $builder->createVehicle(
    array(
        'source' => $sources['source_core'],
        'target' => "return MODX_CORE_PATH . 'components/';",
    ),
    array(
        'vehicle_class' => 'xPDOFileVehicle',
        'new_file_permissions' => '0644',
        'new_folder_permissions' => '0755',
    )
);
$builder->putVehicle($coreVehicle);

/* ---- assets files (+ token resolver runs after files land) ---- */
$assetsVehicle = $builder->createVehicle(
    array(
        'source' => $sources['source_assets'],
        'target' => "return MODX_ASSETS_PATH . 'components/';",
    ),
    array(
        'vehicle_class' => 'xPDOFileVehicle',
        'new_file_permissions' => '0644',
        'new_folder_permissions' => '0755',
    )
);
$assetsVehicle->resolve('php', array('source' => $sources['resolvers'] . 'resolve.token.php'));
$assetsVehicle->resolve('php', array('source' => $sources['resolvers'] . 'resolve.integrations.php'));
$assetsVehicle->resolve('php', array('source' => $sources['resolvers'] . 'resolve.settings.php'));
$builder->putVehicle($assetsVehicle);
$modx->log(modX::LOG_LEVEL_INFO, 'Packaged core + assets files and install/uninstall resolvers.');

/* ---- package attributes ---- */
$builder->setPackageAttributes(array(
    'license'   => file_exists($sources['docs'] . 'LICENSE') ? file_get_contents($sources['docs'] . 'LICENSE') : 'MIT',
    'readme'    => file_exists($sources['docs'] . 'README.md') ? file_get_contents($sources['docs'] . 'README.md') : 'modxMCP — MCP endpoint for MODX.',
    'changelog' => file_exists($sources['docs'] . 'CHANGELOG.md') ? file_get_contents($sources['docs'] . 'CHANGELOG.md') : '',
    'requires'  => array(
        'modx' => '>=2.8.0,<3.0.0',
    ),
));

/* ---- pack ---- */
$modx->log(modX::LOG_LEVEL_INFO, 'Packing ...');
$builder->pack();

$signature = $builder->getSignature();
$packagesDir = rtrim(MODX_CORE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR;
$archivePath = $packagesDir . $signature . '.transport.zip';
$stagingPath = $packagesDir . $signature;

if (!is_file($archivePath)) {
    fwrite(STDERR, "Transport package was not created: {$archivePath}\n");
    exit(4);
}

if (file_exists($stagingPath)) {
    if (is_link($stagingPath) || !is_dir($stagingPath)) {
        fwrite(STDERR, "Refusing to remove unexpected package staging path: {$stagingPath}\n");
        exit(4);
    }
    $cacheManager = $modx->getCacheManager();
    if (!$cacheManager || !$cacheManager->deleteTree($stagingPath, array(
        'deleteTop' => true,
        'skipDirs' => false,
        'extensions' => array(),
    ))) {
        fwrite(STDERR, "Could not remove package staging directory: {$stagingPath}\n");
        exit(4);
    }
}
if (file_exists($stagingPath)) {
    fwrite(STDERR, "Package staging directory still exists after cleanup: {$stagingPath}\n");
    exit(4);
}

$modx->log(modX::LOG_LEVEL_INFO, 'DONE. Package: core/packages/' . $signature . '.transport.zip');
