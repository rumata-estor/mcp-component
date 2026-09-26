<?php

use MODX\Revolution\modX;
use xPDO\Transport\xPDOTransport;
use xPDO\xPDO;

/**
 * Remove files that were shipped by older modxMCP versions but are no longer
 * part of MODX3 MCP. xPDOFileVehicle copies/overwrites payload files on upgrade
 * but does not prune files that disappeared from a newer package.
 *
 * Keep this list explicit. Never recursively delete component directories here:
 * runtime files such as audit logs may legitimately live alongside package files.
 */
$success = true;

$modx = null;
foreach (array('modx', 'transport', 'object') as $__v) {
    if (isset($$__v) && is_object($$__v)) {
        if ($$__v instanceof modX) { $modx = $$__v; break; }
        if (isset($$__v->xpdo) && $$__v->xpdo instanceof xPDO) { $modx = $$__v->xpdo; break; }
    }
}
if (!$modx && isset($GLOBALS['modx']) && $GLOBALS['modx'] instanceof modX) {
    $modx = $GLOBALS['modx'];
}
if (!$modx) {
    return true;
}

$action = isset($options[xPDOTransport::PACKAGE_ACTION]) ? $options[xPDOTransport::PACKAGE_ACTION] : '';
if ($action !== xPDOTransport::ACTION_INSTALL && $action !== xPDOTransport::ACTION_UPGRADE) {
    return $success;
}

$coreRoot = rtrim((string)$modx->getOption('core_path'), '/\\') . DIRECTORY_SEPARATOR .
    'components' . DIRECTORY_SEPARATOR . 'modxmcp' . DIRECTORY_SEPARATOR;

$obsolete = array(
    // MODX 2 processor replaced by the native MODX 3 Processor implementation.
    'processors/mgr/regeneratetoken.class.php',
);

foreach ($obsolete as $relative) {
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    if ($relative === '' || strpos($relative, '..') !== false) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[MODX3 MCP] Refused unsafe obsolete-file path: ' . $relative);
        return false;
    }

    $path = $coreRoot . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path) && !is_link($path)) {
        continue;
    }

    if (!@unlink($path)) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[MODX3 MCP] Could not remove obsolete package file: ' . $path);
        return false;
    }

    $modx->log(modX::LOG_LEVEL_INFO, '[MODX3 MCP] Removed obsolete package file: ' . $relative);
}

return $success;