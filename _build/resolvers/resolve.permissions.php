<?php

use MODX\Revolution\modX;
use xPDO\Transport\xPDOTransport;
use xPDO\xPDO;

/**
 * Ensure an existing MODX3 MCP tree can be overwritten during a transport upgrade.
 *
 * Some hosts expose restrictive or unusual default file modes. In particular, a
 * previous install can leave package-owned files without the owner-write bit, which
 * makes xPDOCacheManager::copyFile() fail before it gets a chance to apply the new
 * package permissions. Only the MODX3 MCP component trees are touched, and runtime
 * logs are deliberately skipped.
 */
$modx = isset($transport) && is_object($transport) && isset($transport->xpdo) && $transport->xpdo instanceof xPDO
    ? $transport->xpdo
    : null;
if (!$modx) {
    return false;
}

$action = isset($options[xPDOTransport::PACKAGE_ACTION])
    ? (int)$options[xPDOTransport::PACKAGE_ACTION]
    : xPDOTransport::ACTION_INSTALL;

if ($action === xPDOTransport::ACTION_UNINSTALL) {
    return true;
}

$roots = array(
    rtrim(MODX_CORE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'modxmcp',
    rtrim(MODX_ASSETS_PATH, '/\\') . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'modxmcp',
);

$makeOwnerWritable = null;
$makeOwnerWritable = static function ($path) use (&$makeOwnerWritable, $modx) {
    if (!file_exists($path)) {
        return true;
    }
    if (is_link($path)) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[MODX3 MCP] Refusing to change permissions through symlink: ' . $path);
        return false;
    }

    $mode = @fileperms($path);
    if ($mode === false) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[MODX3 MCP] Could not read permissions: ' . $path);
        return false;
    }

    $current = $mode & 0777;
    $needed = is_dir($path) ? 0300 : 0200;
    if (($current & $needed) !== $needed && !@chmod($path, $current | $needed)) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[MODX3 MCP] Could not make package path owner-writable: ' . $path);
        return false;
    }

    if (!is_dir($path)) {
        return true;
    }

    $items = @scandir($path);
    if ($items === false) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[MODX3 MCP] Could not read package directory: ' . $path);
        return false;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        // Runtime audit logs are not shipped by the transport package and may be sensitive.
        if ($item === 'logs' && basename($path) === 'modxmcp') {
            continue;
        }
        if (!$makeOwnerWritable($path . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    return true;
};

foreach ($roots as $root) {
    if (!$makeOwnerWritable($root)) {
        return false;
    }
}

return true;
