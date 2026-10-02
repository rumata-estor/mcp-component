<?php
/**
 * Log which supported MODX add-ons are present. Shared by MODX 2 and MODX 3.
 */
$success = true;

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
$actionInstall = constant($transportClass . '::ACTION_INSTALL');
$actionUpgrade = constant($transportClass . '::ACTION_UPGRADE');
$action = isset($options[$packageActionKey]) ? $options[$packageActionKey] : '';

if ($action === $actionInstall || $action === $actionUpgrade) {
    $versionData = method_exists($modx, 'getVersionData') ? $modx->getVersionData() : array();
    $namespaceClass = isset($versionData['version']) && (int)$versionData['version'] >= 3
        ? 'MODX\\Revolution\\modNamespace'
        : 'modNamespace';
    $logClass = get_class($modx);
    $logInfo = defined($logClass . '::LOG_LEVEL_INFO') ? constant($logClass . '::LOG_LEVEL_INFO') : 1;

    $known = array(
        'minishop2' => 'miniShop2 (ms2_*)',
        'migx' => 'MIGX (migx_*)',
        'versionx' => 'VersionX (versionx_*)',
        'virtualpage' => 'VirtualPage (virtualpage_*)',
    );
    $present = array();
    foreach ($known as $ns => $label) {
        if ($modx->getObject($namespaceClass, array('name' => $ns))) {
            $present[] = $label;
        }
    }
    $modx->log(
        $logInfo,
        '[modxMCP] Dedicated integrations detected: ' . (empty($present) ? 'none' : implode(', ', $present)) .
        '. Other add-ons (snippets/chunks) are handled via the generic element tools.'
    );
}

return $success;
