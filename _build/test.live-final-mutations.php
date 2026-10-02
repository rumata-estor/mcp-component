<?php
use MODX\Revolution\modX;

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
require_once rtrim(MODX_CORE_PATH, '/\\') . '/vendor/autoload.php';

$modx = modX::getInstance();
$modx->initialize('mgr');
if (method_exists($modx, 'setOption')) {
    $modx->setOption('modxmcp.disabled_groups', '');
    $modx->setOption('modxmcp.audit_log', false);
} else {
    $modx->config['modxmcp.disabled_groups'] = '';
    $modx->config['modxmcp.audit_log'] = false;
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

function final_legacy($modx)
{
    $mcp = new modxMCP($modx);
    $property = new ReflectionProperty('modxMCP', 'modularRuntime');
    $property->setAccessible(true);
    $property->setValue($mcp, null);
    return $mcp;
}

function final_call($mcp, $action, array $data)
{
    try {
        return array(
            'ok' => true,
            'value' => $mcp->processRequest($action, '', $data),
        );
    } catch (Throwable $e) {
        return array(
            'ok' => false,
            'class' => get_class($e),
            'error' => $e->getMessage(),
        );
    }
}

function final_normalize($value)
{
    if (!is_array($value)) { return $value; }
    $out = array();
    foreach ($value as $key => $item) {
        if ((string)$key === 'id' && (is_int($item) || ctype_digit((string)$item))) {
            $out[$key] = '__ID__';
            continue;
        }
        $out[$key] = final_normalize($item);
    }
    ksort($out);
    return $out;
}

function final_compare($label, $a, $b, &$failures)
{
    $a = final_normalize($a);
    $b = final_normalize($b);
    if ($a !== $b) {
        $failures[] = array('case' => $label, 'modular' => $a, 'legacy' => $b);
        echo "DIFF {$label}\n";
    } else {
        echo "OK {$label}\n";
    }
}

function final_remove_resource($modx, $id)
{
    $resource = $modx->getObject(\MODX\Revolution\modResource::class, (int)$id);
    if ($resource) { $resource->remove(); }
}

function final_make_resource($modx)
{
    $resource = $modx->newObject(\MODX\Revolution\modResource::class);
    $resource->fromArray(array(
        'pagetitle' => '__modxmcp_bulk_final__',
        'alias' => '__modxmcp_bulk_final__',
        'context_key' => 'web',
        'parent' => 0,
        'published' => 0,
        'template' => 0,
    ), '', true, true);
    if (!$resource->save()) {
        throw new RuntimeException('Could not create temporary resource.');
    }
    return $resource;
}

function final_bulk_run($modx, $mcp, $dryRun)
{
    $resource = final_make_resource($modx);
    $id = (int)$resource->get('id');
    try {
        $result = final_call(
            $mcp,
            'bulk_resources',
            array(
                'operation' => 'publish',
                'ids' => array($id),
                'dry_run' => $dryRun,
            )
        );
        if (!$dryRun) {
            $resource = $modx->getObject(\MODX\Revolution\modResource::class, $id);
            $published = $resource ? (int)$resource->get('published') : -1;
            $result['post_published'] = $published;
        }
        return $result;
    } finally {
        final_remove_resource($modx, $id);
        if ($modx->getCacheManager()) { $modx->getCacheManager()->refresh(); }
    }
}

function final_remove_chunk($modx, $name)
{
    $chunk = $modx->getObject(
        \MODX\Revolution\modChunk::class,
        array('name' => $name)
    );
    if ($chunk) { $chunk->remove(); }
}

function final_replace_run($modx, $mcp, $dryRun)
{
    $name = '__modxmcp_replace_final__';
    final_remove_chunk($modx, $name);

    $chunk = $modx->newObject(\MODX\Revolution\modChunk::class);
    $chunk->set('name', $name);
    $chunk->set('snippet', "alpha __MODXMCP_FINAL_NEEDLE__ omega\nsecond line");
    $chunk->set('static', 0);
    if (!$chunk->save()) {
        throw new RuntimeException('Could not create temporary chunk.');
    }

    try {
        $result = final_call(
            $mcp,
            'replace_across',
            array(
                'find' => '__MODXMCP_FINAL_NEEDLE__',
                'replacement' => '__MODXMCP_FINAL_REPLACED__',
                'types' => array('chunk'),
                'case_sensitive' => true,
                'dry_run' => $dryRun,
                'limit' => 10,
            )
        );
        if (!$dryRun) {
            $chunk = $modx->getObject(
                \MODX\Revolution\modChunk::class,
                array('name' => $name)
            );
            $content = $chunk ? (string)$chunk->get('snippet') : '';
            $result['post_replaced'] =
                strpos($content, '__MODXMCP_FINAL_REPLACED__') !== false;
            $result['post_old_absent'] =
                strpos($content, '__MODXMCP_FINAL_NEEDLE__') === false;
        }
        return $result;
    } finally {
        final_remove_chunk($modx, $name);
        if ($modx->getCacheManager()) { $modx->getCacheManager()->refresh(); }
    }
}

$failures = array();

final_compare(
    'bulk_resources dry_run',
    final_bulk_run($modx, new modxMCP($modx), true),
    final_bulk_run($modx, final_legacy($modx), true),
    $failures
);
final_compare(
    'bulk_resources write',
    final_bulk_run($modx, new modxMCP($modx), false),
    final_bulk_run($modx, final_legacy($modx), false),
    $failures
);
final_compare(
    'replace_across dry_run',
    final_replace_run($modx, new modxMCP($modx), true),
    final_replace_run($modx, final_legacy($modx), true),
    $failures
);
final_compare(
    'replace_across write',
    final_replace_run($modx, new modxMCP($modx), false),
    final_replace_run($modx, final_legacy($modx), false),
    $failures
);

if ($failures) {
    echo json_encode(
        $failures,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    ) . "\n";
    exit(1);
}

echo "FINAL_MUTATION_PARITY_OK 4 checks across 2/2 final actions\n";
