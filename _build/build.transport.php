<?php

use MODX\Revolution\modMenu;
use MODX\Revolution\modX;
use MODX\Revolution\Transport\modPackageBuilder;
use xPDO\Transport\xPDOFileVehicle;
use xPDO\Transport\xPDOTransport;
/**
 * MODX3 MCP — CLI-only transport package builder.
 *
 * Run on a MODX 3.x install. It locates config.core.php by walking up from this
 * file, or use MODX_CONFIG_CORE to point at it explicitly.
 *
 *   MODX_CONFIG_CORE=/full/path/config.core.php php _build/build.transport.php
 *
 * Produces MODX_CORE_PATH/packages/<signature>.transport.zip.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("MODX3 MCP transport builder is CLI-only.\n");
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

// Some MODX installations build all paths from $_SERVER['DOCUMENT_ROOT'] even in
// config.core.php. CLI normally leaves it empty, which would turn paths into /core/,
// /assets/, etc. Allow an explicit root and otherwise infer it from config.core.php.
$documentRoot = trim((string)getenv('MODX_DOCUMENT_ROOT'));
if ($documentRoot !== '') {
    $documentRoot = rtrim($documentRoot, '/\\');
    if (!is_file($documentRoot . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
        fwrite(STDERR, "MODX_DOCUMENT_ROOT does not look like a MODX web root: {$documentRoot}\n");
        exit(2);
    }
    $_SERVER['DOCUMENT_ROOT'] = $documentRoot;
} elseif (PHP_SAPI === 'cli' && empty($_SERVER['DOCUMENT_ROOT'])) {
    $probe = dirname((string)(realpath($config) ?: $config));
    for ($i = 0; $i < 12; $i++) {
        $autoload = $probe . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
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
if (!defined('MODX_CORE_PATH') || !is_file(rtrim(MODX_CORE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
    fwrite(STDERR, "MODX bootstrap failed: MODX_CORE_PATH/vendor/autoload.php not found. Set MODX_DOCUMENT_ROOT when config.core.php depends on DOCUMENT_ROOT.\n");
    exit(2);
}
require_once rtrim(MODX_CORE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$modx = modX::getInstance();
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');
$modx->log(modX::LOG_LEVEL_INFO, 'Building modxMCP ' . PKG_VERSION . '-' . PKG_RELEASE . ' ...');

$sources = array(
    'resolvers'     => $buildDir . 'resolvers/',
    'data'          => $buildDir . 'data/',
    'source_core'   => $root . 'core/components/' . PKG_NAMESPACE,
    'source_assets' => $root . 'assets/components/' . PKG_NAMESPACE,
    'docs'          => $root,
);

$builder = new modPackageBuilder($modx);
$builder->createPackage(PKG_NAME, PKG_VERSION, PKG_RELEASE);
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
    foreach ($settings as $setting) {
        $vehicle = $builder->createVehicle($setting, $attributes);
        $builder->putVehicle($vehicle);
    }
    $modx->log(modX::LOG_LEVEL_INFO, 'Packaged ' . count($settings) . ' system settings.');
}

/* ---- manager menu (Components > modxMCP) ---- */
$menu = $modx->newObject(modMenu::class);
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
$menuGraph = $modx->newObject(modMenu::class);
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
    array('vehicle_class' => xPDOFileVehicle::class)
);
$builder->putVehicle($coreVehicle);

/* ---- assets files (+ token resolver runs after files land) ---- */
$assetsVehicle = $builder->createVehicle(
    array(
        'source' => $sources['source_assets'],
        'target' => "return MODX_ASSETS_PATH . 'components/';",
    ),
    array('vehicle_class' => xPDOFileVehicle::class)
);
$assetsVehicle->resolve('php', array('source' => $sources['resolvers'] . 'resolve.token.php'));
$assetsVehicle->resolve('php', array('source' => $sources['resolvers'] . 'resolve.integrations.php'));
$assetsVehicle->resolve('php', array('source' => $sources['resolvers'] . 'resolve.settings.php'));
$builder->putVehicle($assetsVehicle);
$modx->log(modX::LOG_LEVEL_INFO, 'Packaged core + assets files and install/uninstall resolvers.');

/* ---- package attributes ---- */
$builder->setPackageAttributes(array(
    'license'   => file_exists($sources['docs'] . 'LICENSE') ? file_get_contents($sources['docs'] . 'LICENSE') : 'MIT',
    'readme'    => file_exists($sources['docs'] . 'README.md') ? file_get_contents($sources['docs'] . 'README.md') : 'MODX3 MCP — MCP endpoint for MODX Revolution 3.',
    'changelog' => file_exists($sources['docs'] . 'CHANGELOG.md') ? file_get_contents($sources['docs'] . 'CHANGELOG.md') : '',
    'requires'  => array(
        'modx' => '>=3.0.0,<4.0.0',
    ),
));

/* ---- pack ---- */
$modx->log(modX::LOG_LEVEL_INFO, 'Packing ...');
$builder->pack();

$signature = $builder->getSignature();
$modx->log(modX::LOG_LEVEL_INFO, 'DONE. Package: core/packages/' . $signature . '.transport.zip');