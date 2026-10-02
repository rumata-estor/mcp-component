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

$corePath = $modx->getOption('modxmcp.core_path', null, $modx->getOption('core_path') . 'components/modxmcp/');
$corePath = str_replace(
    array('{core_path}', '[[++core_path]]'),
    rtrim((string)$modx->getOption('core_path'), '/\\') . DIRECTORY_SEPARATOR,
    (string)$corePath
);
require_once $corePath . 'model/modxmcp.class.php';

function media_legacy($modx)
{
    $mcp = new modxMCP($modx);
    $property = new ReflectionProperty('modxMCP', 'modularRuntime');
    $property->setAccessible(true);
    $property->setValue($mcp, null);
    return $mcp;
}

function media_call($mcp, $action, array $data)
{
    try {
        return array('ok' => true, 'value' => $mcp->processRequest($action, '', $data));
    } catch (Throwable $e) {
        return array('ok' => false, 'error' => $e->getMessage());
    }
}

function media_normalize($value)
{
    if (!is_array($value)) { return $value; }
    $out = array();
    foreach ($value as $key => $item) {
        if ((string)$key === 'id' && (is_int($item) || ctype_digit((string)$item))) {
            $out[$key] = '__ID__';
            continue;
        }
        $out[$key] = media_normalize($item);
    }
    ksort($out);
    return $out;
}

function media_compare($label, $a, $b, &$failures)
{
    $a = media_normalize($a);
    $b = media_normalize($b);
    if ($a !== $b) {
        $failures[] = array('case' => $label, 'modular' => $a, 'legacy' => $b);
        echo "DIFF {$label}\n";
    } else {
        echo "OK {$label}\n";
    }
}

function media_tx($modx, $mcp, $action, array $data, $setup = null)
{
    $modx->beginTransaction();
    try {
        if ($setup) { $data = call_user_func($setup, $modx, $data); }
        return media_call($mcp, $action, $data);
    } finally {
        $modx->rollback();
    }
}

function media_make_source($modx, $name, $basePath)
{
    $class = live_class($modx, 'sources.modFileMediaSource', 'MODX\Revolution\Sources\modFileMediaSource');
    $source = $modx->newObject($class);
    $source->set('name', $name);
    $source->set('class_key', $class);
    $source->set('description', 'modxMCP parity source');
    if (!$source->save()) {
        throw new RuntimeException('Could not create temporary media source.');
    }
    $source->setProperties(array(
        'basePath' => array(
            'name' => 'basePath',
            'desc' => '',
            'type' => 'textfield',
            'options' => array(),
            'value' => $basePath,
            'area' => '',
        ),
        'basePathRelative' => array(
            'name' => 'basePathRelative',
            'desc' => '',
            'type' => 'combo-boolean',
            'options' => array(),
            'value' => false,
            'area' => '',
        ),
        'baseUrl' => array(
            'name' => 'baseUrl',
            'desc' => '',
            'type' => 'textfield',
            'options' => array(),
            'value' => '',
            'area' => '',
        ),
        'baseUrlRelative' => array(
            'name' => 'baseUrlRelative',
            'desc' => '',
            'type' => 'combo-boolean',
            'options' => array(),
            'value' => false,
            'area' => '',
        ),
    ));
    $source->save();
    return $source;
}

function media_rm_tree($path)
{
    if (!file_exists($path)) { return; }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: array() as $name) {
        if ($name === '.' || $name === '..') { continue; }
        media_rm_tree($path . DIRECTORY_SEPARATOR . $name);
    }
    @rmdir($path);
}

$root = rtrim($modx->getOption('assets_path'), '/\\')
    . DIRECTORY_SEPARATOR . 'cache'
    . DIRECTORY_SEPARATOR . 'modxmcp-media-parity';
$sourceName = '__modxmcp_media_parity__';
$sourceClass = live_class($modx, 'sources.modMediaSource', 'MODX\Revolution\Sources\modMediaSource');

$cleanup = function () use ($modx, $root, $sourceName, $sourceClass) {
    media_rm_tree($root);
    $source = $modx->getObject($sourceClass, array('name' => $sourceName));
    if ($source) { $source->remove(); }
};
register_shutdown_function($cleanup);
$cleanup();
@mkdir($root, 0755, true);

$failures = array();

// Source create/update/delete in DB transactions.
$create = array(
    'name' => '__modxmcp_media_create__',
    'description' => 'parity',
    'properties' => array(
        'basePath' => $root . DIRECTORY_SEPARATOR,
        'basePathRelative' => false,
    ),
);
media_compare(
    'create_media_source',
    media_tx($modx, new modxMCP($modx), 'create_media_source', $create),
    media_tx($modx, media_legacy($modx), 'create_media_source', $create),
    $failures
);

$setupSource = function ($modx, $data) use ($root) {
    $source = media_make_source($modx, '__modxmcp_media_existing__', $root . DIRECTORY_SEPARATOR);
    $data['id'] = (int)$source->get('id');
    return $data;
};
media_compare(
    'update_media_source',
    media_tx(
        $modx,
        new modxMCP($modx),
        'update_media_source',
        array('description' => 'after', 'properties' => array('basePath' => $root . DIRECTORY_SEPARATOR)),
        $setupSource
    ),
    media_tx(
        $modx,
        media_legacy($modx),
        'update_media_source',
        array('description' => 'after', 'properties' => array('basePath' => $root . DIRECTORY_SEPARATOR)),
        $setupSource
    ),
    $failures
);
media_compare(
    'delete_media_source',
    media_tx($modx, new modxMCP($modx), 'delete_media_source', array(), $setupSource),
    media_tx($modx, media_legacy($modx), 'delete_media_source', array(), $setupSource),
    $failures
);

// One isolated source for real filesystem operations.
$source = media_make_source($modx, $sourceName, $root . DIRECTORY_SEPARATOR);
$sourceId = (int)$source->get('id');

function media_reset_root($root)
{
    media_rm_tree($root);
    @mkdir($root, 0755, true);
}

function media_pair($modx, $root, $sourceId, $label, $action, array $data, $prepare, &$failures)
{
    $data['source'] = $sourceId;

    media_reset_root($root);
    if ($prepare) { call_user_func($prepare, $root); }
    $a = media_call(new modxMCP($modx), $action, $data);

    media_reset_root($root);
    if ($prepare) { call_user_func($prepare, $root); }
    $b = media_call(media_legacy($modx), $action, $data);

    media_compare($label, $a, $b, $failures);
}

media_pair(
    $modx, $root, $sourceId,
    'create_media_folder',
    'create_media_folder',
    array('parent' => '/', 'name' => 'folder'),
    null,
    $failures
);
media_pair(
    $modx, $root, $sourceId,
    'create_media_file',
    'create_media_file',
    array('path' => '', 'name' => 'file.txt', 'content' => 'alpha'),
    null,
    $failures
);
media_pair(
    $modx, $root, $sourceId,
    'update_media_file',
    'update_media_file',
    array('path' => 'file.txt', 'content' => 'beta'),
    function ($root) { file_put_contents($root . '/file.txt', 'alpha'); },
    $failures
);
media_pair(
    $modx, $root, $sourceId,
    'rename_media_file',
    'rename_media_file',
    array('path' => 'old.txt', 'new_name' => 'new.txt'),
    function ($root) { file_put_contents($root . '/old.txt', 'alpha'); },
    $failures
);
media_pair(
    $modx, $root, $sourceId,
    'delete_media_file',
    'delete_media_file',
    array('path' => 'file.txt'),
    function ($root) { file_put_contents($root . '/file.txt', 'alpha'); },
    $failures
);
media_pair(
    $modx, $root, $sourceId,
    'delete_media_folder',
    'delete_media_folder',
    array('path' => 'folder'),
    function ($root) { @mkdir($root . '/folder', 0755, true); },
    $failures
);

$cleanup();

if (file_exists($root) || $modx->getObject($sourceClass, array('name' => $sourceName))) {
    fwrite(STDERR, "Temporary media artifacts survived cleanup\n");
    exit(1);
}
if ($failures) {
    echo json_encode($failures, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}
echo "MEDIA_MUTATION_PARITY_OK 9/9 actions\n";
