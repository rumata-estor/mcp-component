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
if (!$config || !is_file($config)) {
    fwrite(STDERR, "config.core.php not found.\n");
    exit(2);
}
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
if (method_exists($modx, 'setOption')) {
    $modx->setOption('modxmcp.disabled_groups', '');
    $modx->setOption('modxmcp.auto_static', false);
} else {
    $modx->config['modxmcp.disabled_groups'] = '';
    $modx->config['modxmcp.auto_static'] = false;
}

$corePath = $modx->getOption(
    'modxmcp.core_path',
    null,
    $modx->getOption('core_path') . 'components/modxmcp/'
);
$corePath = str_replace(
    array('{core_path}', '[[++core_path]]'),
    rtrim((string)$modx->getOption('core_path'), '/\\') . DIRECTORY_SEPARATOR,
    (string)$corePath
);
require_once $corePath . 'model/modxmcp.class.php';

function element_legacy($modx)
{
    $mcp = new modxMCP($modx);
    $property = new ReflectionProperty('modxMCP', 'modularRuntime');
    $property->setAccessible(true);
    $property->setValue($mcp, null);
    return $mcp;
}

function element_call($mcp, $action, $type, array $data)
{
    try {
        return array(
            'ok' => true,
            'value' => $mcp->processRequest($action, $type, $data),
        );
    } catch (Throwable $e) {
        return array(
            'ok' => false,
            'error_class' => get_class($e),
            'error' => $e->getMessage(),
        );
    }
}

function element_clean($value)
{
    if (is_string($value)) {
        return preg_replace('/\(ID: \d+\)/', '(ID: __ID__)', $value);
    }
    if (!is_array($value)) { return $value; }
    $drop = array(
        'id' => true, 'template_id' => true, 'createdon' => true, 'editedon' => true, 'deletedon' => true,
        'publishedon' => true, 'publishedby' => true, 'editedby' => true,
        'createdby' => true, 'deletedby' => true, 'uri' => true,
    );
    $out = array();
    foreach ($value as $key => $item) {
        if (isset($drop[(string)$key])) { continue; }
        $out[$key] = element_clean($item);
    }
    ksort($out);
    return $out;
}

function element_class($modx, $type)
{
    $version = $modx->getVersionData();
    $isModx2 = isset($version['version']) && (int)$version['version'] === 2;
    $map = $isModx2
        ? array(
            'chunk' => 'modChunk',
            'snippet' => 'modSnippet',
            'template' => 'modTemplate',
            'resource' => 'modResource',
            'tv' => 'modTemplateVar',
            'category' => 'modCategory',
            'plugin' => 'modPlugin',
        )
        : array(
            'chunk' => 'MODX\Revolution\modChunk',
            'snippet' => 'MODX\Revolution\modSnippet',
            'template' => 'MODX\Revolution\modTemplate',
            'resource' => 'MODX\Revolution\modResource',
            'tv' => 'MODX\Revolution\modTemplateVar',
            'category' => 'MODX\Revolution\modCategory',
            'plugin' => 'MODX\Revolution\modPlugin',
        );
    return $map[$type];
}

function element_relation_class($modx, $type)
{
    $version = $modx->getVersionData();
    $isModx2 = isset($version['version']) && (int)$version['version'] === 2;
    if ($type === 'plugin_event') {
        return $isModx2 ? 'modPluginEvent' : 'MODX\Revolution\modPluginEvent';
    }
    if ($type === 'tv_template') {
        return $isModx2 ? 'modTemplateVarTemplate' : 'MODX\Revolution\modTemplateVarTemplate';
    }
    throw new InvalidArgumentException('Unknown relation type: ' . $type);
}

function element_name_field($type)
{
    if ($type === 'template') { return 'templatename'; }
    if ($type === 'resource') { return 'pagetitle'; }
    if ($type === 'category') { return 'category'; }
    return 'name';
}

function element_cleanup($modx, $type, $name)
{
    $class = element_class($modx, $type);
    $field = element_name_field($type);
    $objects = $modx->getCollection($class, array($field => $name));
    foreach ($objects as $object) {
        $id = (int)$object->get('id');
        if ($type === 'plugin') {
            $modx->removeCollection(
                element_relation_class($modx, 'plugin_event'),
                array('pluginid' => $id)
            );
        }
        if ($type === 'tv') {
            $modx->removeCollection(
                element_relation_class($modx, 'tv_template'),
                array('tmplvarid' => $id)
            );
        }
        $object->remove();
    }
}

function element_payload($type, $name)
{
    $payload = array('type' => $type, 'name' => $name);
    if ($type === 'chunk') {
        $payload['content'] = "<p>modxMCP parity chunk</p>";
        $payload['description'] = 'parity create';
    } elseif ($type === 'snippet') {
        $payload['content'] = "return 'modxmcp-parity';";
        $payload['description'] = 'parity create';
    } elseif ($type === 'template') {
        $payload['content'] = "<!doctype html><title>modxMCP parity</title>";
        $payload['description'] = 'parity create';
    } elseif ($type === 'resource') {
        $payload['content'] = "modxMCP parity resource";
        $payload['published'] = 0;
        $payload['hidemenu'] = 1;
        $payload['alias'] = strtolower(str_replace('_', '-', trim($name, '_')));
        $payload['context_key'] = 'web';
    } elseif ($type === 'tv') {
        $payload['caption'] = 'modxMCP parity TV';
        $payload['field_type'] = 'text';
        $payload['description'] = 'parity create';
    } elseif ($type === 'category') {
        $payload['parent'] = 0;
    } elseif ($type === 'plugin') {
        $payload['content'] = "return;";
        $payload['disabled'] = 1;
        $payload['description'] = 'parity create';
    }
    return $payload;
}

function element_update_payload($type, $id, $name)
{
    $payload = array('type' => $type, 'id' => $id);
    if ($type === 'resource') {
        $payload['longtitle'] = 'modxMCP parity updated';
    } elseif ($type === 'category') {
        $payload['category'] = $name . '-updated';
    } elseif ($type === 'tv') {
        $payload['caption'] = 'modxMCP parity TV updated';
    } else {
        $payload['description'] = 'parity updated';
    }
    return $payload;
}

function element_assert_state($modx, $type, $id, $phase)
{
    $object = $modx->getObject(element_class($modx, $type), (int)$id, false);
    if ($phase === 'deleted') {
        if ($type === 'resource') {
            if (!$object || !(bool)$object->get('deleted')) {
                throw new Exception("resource delete state not set for ID {$id}");
            }
            $object->remove();
            return;
        }
        if ($object) {
            throw new Exception("{$type} {$id} still exists after delete");
        }
        return;
    }
    if (!$object) {
        throw new Exception("{$type} {$id} missing after {$phase}");
    }
    if ($phase === 'updated') {
        if ($type === 'resource' && $object->get('longtitle') !== 'modxMCP parity updated') {
            throw new Exception("resource update not persisted");
        }
        if ($type === 'category' && substr((string)$object->get('category'), -8) !== '-updated') {
            throw new Exception("category update not persisted");
        }
        if ($type === 'tv' && $object->get('caption') !== 'modxMCP parity TV updated') {
            throw new Exception("TV update not persisted");
        }
        if (in_array($type, array('chunk', 'snippet', 'template', 'plugin'), true)
            && $object->get('description') !== 'parity updated') {
            throw new Exception("{$type} update not persisted");
        }
    }
}

function element_lifecycle($modx, $mcp, $type, $name)
{
    element_cleanup($modx, $type, $name);
    element_cleanup($modx, $type, $name . '-updated');

    $create = element_call($mcp, 'create_element', $type, element_payload($type, $name));
    if (!$create['ok']) {
        throw new Exception("create {$type} failed: " . $create['error']);
    }
    $id = isset($create['value']['id']) ? (int)$create['value']['id'] : 0;
    if ($id <= 0) {
        throw new Exception("create {$type} returned no id");
    }
    element_assert_state($modx, $type, $id, 'created');

    $dry = element_call(
        $mcp,
        'delete_element',
        $type,
        array('type' => $type, 'id' => $id, 'dry_run' => true)
    );
    if (!$dry['ok'] || empty($dry['value']['dry_run'])) {
        throw new Exception("dry-run delete {$type} failed");
    }

    $update = element_call(
        $mcp,
        'update_element',
        $type,
        element_update_payload($type, $id, $name)
    );
    if (!$update['ok']) {
        throw new Exception("update {$type} failed: " . $update['error']);
    }
    element_assert_state($modx, $type, $id, 'updated');

    $delete = element_call(
        $mcp,
        'delete_element',
        $type,
        array('type' => $type, 'id' => $id)
    );
    if (!$delete['ok']) {
        throw new Exception("delete {$type} failed: " . $delete['error']);
    }
    element_assert_state($modx, $type, $id, 'deleted');

    element_cleanup($modx, $type, $name);
    element_cleanup($modx, $type, $name . '-updated');

    return array(
        'create' => element_clean($create),
        'dry' => element_clean($dry),
        'update' => element_clean($update),
        'delete' => element_clean($delete),
    );
}

$onlyType = getenv('ELEMENT_TYPE');
$types = $onlyType ? array($onlyType) : array('chunk', 'snippet', 'template', 'resource', 'tv', 'category', 'plugin');
$failures = array();
$stamp = substr((string)time(), -6) . '-' . getmypid();

foreach ($types as $type) {
    $name = '__mcp_p_' . $type . '_' . $stamp;
    try {
        $modular = element_lifecycle($modx, new modxMCP($modx), $type, $name);
        $legacy = element_lifecycle($modx, element_legacy($modx), $type, $name);

        if ($modular !== $legacy) {
            $failures[] = array(
                'type' => $type,
                'modular' => $modular,
                'legacy' => $legacy,
            );
            echo "DIFF {$type}\n";
        } else {
            echo "OK {$type}\n";
        }
    } catch (Throwable $e) {
        $failures[] = array(
            'type' => $type,
            'exception' => get_class($e) . ': ' . $e->getMessage(),
        );
        echo "FAIL {$type}: " . $e->getMessage() . "\n";
    } finally {
        element_cleanup($modx, $type, $name);
        element_cleanup($modx, $type, $name . '-updated');
    }
}

if (!empty($failures)) {
    echo json_encode($failures, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}

echo "ELEMENT_MUTATION_PARITY_OK " . count($types) . " type(s) create-update-dryrun-delete\n";
