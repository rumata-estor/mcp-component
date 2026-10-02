<?php
/**
 * Make an existing modxMCP component tree owner-writable before upgrade.
 * Shared by MODX 2 and MODX 3.
 */
$modx = null;
if (isset($transport) && is_object($transport) && isset($transport->xpdo) && is_object($transport->xpdo)) {
    $candidate = $transport->xpdo;
    if (is_a($candidate, 'xPDO') || is_a($candidate, 'xPDO\\xPDO')) {
        $modx = $candidate;
    }
}
if (!$modx) { return false; }

$transportClass = class_exists('xPDO\\Transport\\xPDOTransport') ? 'xPDO\\Transport\\xPDOTransport' : 'xPDOTransport';
$packageActionKey = constant($transportClass . '::PACKAGE_ACTION');
$actionUninstall = constant($transportClass . '::ACTION_UNINSTALL');
$actionInstall = constant($transportClass . '::ACTION_INSTALL');
$action = isset($options[$packageActionKey]) ? (int)$options[$packageActionKey] : $actionInstall;

if ($action === $actionUninstall) { return true; }

$logClass = get_class($modx);
$logError = defined($logClass . '::LOG_LEVEL_ERROR') ? constant($logClass . '::LOG_LEVEL_ERROR') : 3;

$roots = array(
    rtrim(MODX_CORE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'modxmcp',
    rtrim(MODX_ASSETS_PATH, '/\\') . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'modxmcp',
);

$makeOwnerWritable = null;
$makeOwnerWritable = function ($path) use (&$makeOwnerWritable, $modx, $logError) {
    if (!file_exists($path)) { return true; }
    if (is_link($path)) {
        $modx->log($logError, '[modxMCP] Refusing to change permissions through symlink: ' . $path);
        return false;
    }

    $mode = @fileperms($path);
    if ($mode === false) {
        $modx->log($logError, '[modxMCP] Could not read permissions: ' . $path);
        return false;
    }

    $current = $mode & 0777;
    $needed = is_dir($path) ? 0300 : 0200;
    if (($current & $needed) !== $needed && !@chmod($path, $current | $needed)) {
        $modx->log($logError, '[modxMCP] Could not make package path owner-writable: ' . $path);
        return false;
    }

    if (!is_dir($path)) { return true; }

    $items = @scandir($path);
    if ($items === false) {
        $modx->log($logError, '[modxMCP] Could not read package directory: ' . $path);
        return false;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') { continue; }
        if ($item === 'logs' && basename($path) === 'modxmcp') { continue; }
        if (!$makeOwnerWritable($path . DIRECTORY_SEPARATOR . $item)) { return false; }
    }
    return true;
};

foreach ($roots as $root) {
    if (!$makeOwnerWritable($root)) { return false; }
}

return true;
