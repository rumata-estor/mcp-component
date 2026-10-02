<?php
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(2); }
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
if (!$config || !is_file($config)) { fwrite(STDERR, "config.core.php not found.\n"); exit(2); }
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
    $modx->setOption('modxmcp.auto_static', false);
} else {
    $modx->config['modxmcp.disabled_groups'] = '';
    $modx->config['modxmcp.auto_static'] = false;
}

$corePath = $modx->getOption('modxmcp.core_path', null, $modx->getOption('core_path') . 'components/modxmcp/');
$corePath = str_replace(
    array('{core_path}', '[[++core_path]]'),
    rtrim((string)$modx->getOption('core_path'), '/\\') . DIRECTORY_SEPARATOR,
    (string)$corePath
);
require_once $corePath . 'model/modxmcp.class.php';

function file_legacy($modx)
{
    $mcp = new modxMCP($modx);
    $property = new ReflectionProperty('modxMCP', 'modularRuntime');
    $property->setAccessible(true);
    $property->setValue($mcp, null);
    return $mcp;
}

function file_call($mcp, $action, array $data)
{
    try {
        $elementType = in_array($action, array('create_element', 'update_element', 'delete_element'), true)
            && isset($data['type'])
            ? (string)$data['type']
            : '';
        return array('ok' => true, 'value' => $mcp->processRequest($action, $elementType, $data));
    } catch (Throwable $e) {
        return array('ok' => false, 'error_class' => get_class($e), 'error' => $e->getMessage());
    }
}

function file_class($modx, $type)
{
    $map = array(
        'chunk' => live_class($modx, 'modChunk', 'MODX\Revolution\modChunk'),
        'snippet' => live_class($modx, 'modSnippet', 'MODX\Revolution\modSnippet'),
        'template' => live_class($modx, 'modTemplate', 'MODX\Revolution\modTemplate'),
        'plugin' => live_class($modx, 'modPlugin', 'MODX\Revolution\modPlugin'),
    );
    return $map[$type];
}

function file_field($type)
{
    if ($type === 'template') { return 'content'; }
    if ($type === 'plugin') { return 'plugincode'; }
    return 'snippet';
}

function file_name_field($type)
{
    return $type === 'template' ? 'templatename' : 'name';
}

function file_abs($modx, $relative)
{
    if ($relative === '') { return null; }
    return rtrim($modx->getOption('base_path'), '/\\') . '/' . ltrim($relative, '/\\');
}

function file_cleanup($modx, $type, $name)
{
    $objects = $modx->getCollection(file_class($modx, $type), array(file_name_field($type) => $name));
    foreach ($objects as $object) {
        $relative = (string)$object->get('static_file');
        $absolute = file_abs($modx, $relative);
        if ($absolute && is_file($absolute)) { @unlink($absolute); }
        if ($type === 'plugin') {
            $modx->removeCollection(
                live_class($modx, 'modPluginEvent', 'MODX\Revolution\modPluginEvent'),
                array('pluginid' => (int)$object->get('id'))
            );
        }
        $object->remove();
    }
}

function file_payload($type, $name)
{
    $content = array(
        'chunk' => "alpha\nbeta\ngamma",
        'snippet' => "\$x = 1;\nreturn \$x;\n// end",
        'template' => "<!-- alpha -->\n[[*content]]\n<!-- gamma -->",
        'plugin' => "/* alpha */\nreturn;\n/* gamma */",
    );
    $payload = array(
        'type' => $type,
        'name' => $name,
        'content' => $content[$type],
        'description' => 'file parity',
    );
    if ($type === 'plugin') { $payload['disabled'] = 1; }
    return $payload;
}

function file_normalize($value)
{
    if (!is_array($value)) { return $value; }
    $out = array();
    foreach ($value as $key => $item) {
        if ((string)$key === 'id') { continue; }
        $out[$key] = file_normalize($item);
    }
    ksort($out);
    return $out;
}

function file_lifecycle($modx, $mcp, $type, $name)
{
    file_cleanup($modx, $type, $name);

    $create = file_call($mcp, 'create_element', file_payload($type, $name));
    if (!$create['ok']) { throw new Exception("create failed: " . $create['error']); }
    $id = isset($create['value']['id']) ? (int)$create['value']['id'] : 0;
    if ($id <= 0) { throw new Exception('create returned no id'); }

    $object = $modx->getObject(file_class($modx, $type), $id, false);
    if (!$object) { throw new Exception('created object not found'); }
    $field = file_field($type);
    $original = (string)$object->get($field);
    $lines = explode("\n", str_replace("\r\n", "\n", $original));
    if (count($lines) !== 3) { throw new Exception('expected 3 content lines'); }

    $editDb = file_call(
        $mcp,
        'edit_element_lines',
        array(
            'type' => $type,
            'id' => $id,
            'edits' => array(
                array(
                    'start_line' => 2,
                    'end_line' => 2,
                    'replacement' => 'MIDDLE',
                    'expect' => $lines[1],
                ),
            ),
        )
    );
    if (!$editDb['ok']) { throw new Exception('DB edit failed: ' . $editDb['error']); }

    $object = $modx->getObject(file_class($modx, $type), $id, false);
    $dbContent = (string)$object->get($field);
    if (strpos($dbContent, "MIDDLE") === false) {
        throw new Exception('DB edit was not persisted');
    }

    $makeStatic = file_call(
        $mcp,
        'make_static',
        array('type' => $type, 'id' => $id)
    );
    if (!$makeStatic['ok']) { throw new Exception('make_static failed: ' . $makeStatic['error']); }
    $relative = isset($makeStatic['value']['static_file']) ? $makeStatic['value']['static_file'] : '';
    $absolute = file_abs($modx, $relative);
    if (!$absolute || !is_file($absolute)) { throw new Exception('static file was not created'); }
    if ((string)file_get_contents($absolute) !== $dbContent) {
        throw new Exception('static file content differs from DB content');
    }

    $staticLines = explode("\n", str_replace("\r\n", "\n", (string)file_get_contents($absolute)));
    $editStatic = file_call(
        $mcp,
        'edit_element_lines',
        array(
            'type' => $type,
            'id' => $id,
            'edits' => array(
                array(
                    'start_line' => 1,
                    'end_line' => 1,
                    'replacement' => 'FIRST',
                    'expect' => $staticLines[0],
                ),
            ),
        )
    );
    if (!$editStatic['ok']) { throw new Exception('static edit failed: ' . $editStatic['error']); }

    $fileContent = (string)file_get_contents($absolute);
    $fileComparable = preg_replace('/^<\?php\s*/', '', $fileContent);
    if (strpos($fileComparable, "FIRST") !== 0) {
        throw new Exception('static file edit was not persisted; head=' . json_encode(substr($fileContent, 0, 80)));
    }
    $object = $modx->getObject(file_class($modx, $type), $id, false);
    $dbComparable = preg_replace('/^<\?php\s*/', '', (string)$object->get($field));
    if (strpos($dbComparable, "FIRST") !== 0) {
        throw new Exception('DB mirror after static edit was not persisted');
    }

    $delete = file_call(
        $mcp,
        'delete_element',
        array('type' => $type, 'id' => $id)
    );
    if (!$delete['ok']) { throw new Exception('delete failed: ' . $delete['error']); }

    if (is_file($absolute)) { @unlink($absolute); }
    file_cleanup($modx, $type, $name);

    return array(
        'edit_db' => file_normalize($editDb),
        'make_static' => file_normalize($makeStatic),
        'edit_static' => file_normalize($editStatic),
    );
}

$onlyType = getenv('ELEMENT_TYPE');
$types = $onlyType ? array($onlyType) : array('chunk', 'snippet', 'template', 'plugin');
$failures = array();
$stamp = substr((string)time(), -6) . '-' . getmypid();

foreach ($types as $type) {
    $name = '__mcp_file_' . $type . '_' . $stamp;
    try {
        $modular = file_lifecycle($modx, new modxMCP($modx), $type, $name);
        echo "MODULAR_OK {$type}\n";
        $legacy = file_lifecycle($modx, file_legacy($modx), $type, $name);
        echo "LEGACY_OK {$type}\n";
        if ($modular !== $legacy) {
            $failures[] = array('type' => $type, 'modular' => $modular, 'legacy' => $legacy);
            echo "DIFF {$type}\n";
        } else {
            echo "OK {$type}\n";
        }
    } catch (Throwable $e) {
        $failures[] = array('type' => $type, 'exception' => get_class($e) . ': ' . $e->getMessage());
        echo "FAIL {$type}: " . $e->getMessage() . "\n";
    } finally {
        file_cleanup($modx, $type, $name);
    }
}

if (!empty($failures)) {
    echo json_encode($failures, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}

echo "ELEMENT_FILE_MUTATION_PARITY_OK " . count($types) . " type(s)\n";
