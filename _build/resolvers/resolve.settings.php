<?php

use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modX;
use xPDO\Transport\xPDOTransport;
use xPDO\xPDO;

/**
 * Keep administrator-edited modxmcp.* values intact on install/upgrade, but remove
 * the component's settings on an explicit transport-package uninstall.
 */
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
if ($action === xPDOTransport::ACTION_UNINSTALL) {
    $removed = $modx->removeCollection(modSystemSetting::class, array('namespace' => 'modxmcp'));
    if ($removed === false) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[MODX3 MCP] Could not remove modxmcp system settings during uninstall.');
        return false;
    }
    if ($modx->getCacheManager()) {
        $modx->getCacheManager()->refresh();
    }
}

return true;