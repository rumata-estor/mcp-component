<?php
/**
 * Preserve administrator-edited settings on install/upgrade and remove all
 * modxMCP-owned settings/menu/namespace records on explicit uninstall.
 * Shared by MODX 2 and MODX 3.
 */
$modx = null;
foreach (array('modx', 'transport', 'object') as $__v) {
    if (!isset($$__v) || !is_object($$__v)) { continue; }
    $candidate = $$__v;
    if (is_a($candidate, 'modX') || is_a($candidate, 'MODX\\Revolution\\modX')) {
        $modx = $candidate; break;
    }
    if (isset($candidate->xpdo) && is_object($candidate->xpdo)
        && (is_a($candidate->xpdo, 'xPDO') || is_a($candidate->xpdo, 'xPDO\\xPDO'))) {
        $modx = $candidate->xpdo; break;
    }
}
if (!$modx && isset($GLOBALS['modx']) && is_object($GLOBALS['modx'])
    && (is_a($GLOBALS['modx'], 'modX') || is_a($GLOBALS['modx'], 'MODX\\Revolution\\modX'))) {
    $modx = $GLOBALS['modx'];
}
if (!$modx) { return true; }

$transportClass = class_exists('xPDO\\Transport\\xPDOTransport') ? 'xPDO\\Transport\\xPDOTransport' : 'xPDOTransport';
$packageActionKey = constant($transportClass . '::PACKAGE_ACTION');
$actionUninstall = constant($transportClass . '::ACTION_UNINSTALL');
$action = isset($options[$packageActionKey]) ? $options[$packageActionKey] : '';

if ($action === $actionUninstall) {
    $versionData = method_exists($modx, 'getVersionData') ? $modx->getVersionData() : array();
    $isModx3 = isset($versionData['version']) && (int)$versionData['version'] >= 3;
    $menuClass = $isModx3 ? 'MODX\\Revolution\\modMenu' : 'modMenu';
    $settingClass = $isModx3 ? 'MODX\\Revolution\\modSystemSetting' : 'modSystemSetting';
    $namespaceClass = $isModx3 ? 'MODX\\Revolution\\modNamespace' : 'modNamespace';

    $logClass = get_class($modx);
    $logError = defined($logClass . '::LOG_LEVEL_ERROR') ? constant($logClass . '::LOG_LEVEL_ERROR') : 3;

    foreach (array('modxmcp_graph', 'modxmcp') as $menuText) {
        $menu = $modx->getObject($menuClass, array('text' => $menuText));
        if ($menu && !$menu->remove()) {
            $modx->log($logError, "[modxMCP] Could not remove manager menu {$menuText} during uninstall.");
            return false;
        }
    }

    $removed = $modx->removeCollection($settingClass, array('namespace' => 'modxmcp'));
    if ($removed === false) {
        $modx->log($logError, '[modxMCP] Could not remove modxmcp system settings during uninstall.');
        return false;
    }

    $namespace = $modx->getObject($namespaceClass, array('name' => 'modxmcp'));
    if ($namespace && !$namespace->remove()) {
        $modx->log($logError, '[modxMCP] Could not remove modxmcp namespace during uninstall.');
        return false;
    }

    if ($modx->getCacheManager()) {
        $modx->getCacheManager()->refresh();
    }
}

return true;
