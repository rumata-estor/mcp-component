<?php
use MODX\Revolution\modX;

if (PHP_SAPI !== 'cli') { exit(2); }

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

$corePath = $modx->getOption('modxmcp.core_path', null, $modx->getOption('core_path') . 'components/modxmcp/');
$corePath = str_replace(
    array('{core_path}', '[[++core_path]]'),
    rtrim((string)$modx->getOption('core_path'), '/\\') . DIRECTORY_SEPARATOR,
    (string)$corePath
);
require_once $corePath . 'model/modxmcp.class.php';

function vx_legacy($modx)
{
    $mcp = new modxMCP($modx);
    $property = new ReflectionProperty('modxMCP', 'modularRuntime');
    $property->setAccessible(true);
    $property->setValue($mcp, null);
    return $mcp;
}

function vx_call($mcp)
{
    try {
        return array(
            'ok' => true,
            'value' => $mcp->processRequest(
                'versionx_revert_version',
                '',
                array(
                    'type' => 'resource',
                    'content_id' => 999999,
                    'version_id' => 999999,
                    'confirm' => true,
                )
            ),
        );
    } catch (Throwable $e) {
        return array(
            'ok' => false,
            'class' => get_class($e),
            'error' => $e->getMessage(),
        );
    }
}

$modular = vx_call(new modxMCP($modx));
$legacy = vx_call(vx_legacy($modx));

if ($modular !== $legacy) {
    echo "VERSIONX_MUTATION_PARITY_FAIL\n";
    echo json_encode(
        array('modular' => $modular, 'legacy' => $legacy),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    ) . "\n";
    exit(1);
}

echo "VERSIONX_MUTATION_PARITY_OK safe not-found contract\n";
