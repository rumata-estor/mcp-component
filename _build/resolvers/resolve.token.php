<?php
/**
 * Generate modxmcp.api_token on install/upgrade when empty.
 * Shared by MODX 2 and MODX 3; never rotates an existing token.
 */
$success = true;

$modx = null;
foreach (array('modx', 'transport', 'object') as $__v) {
    if (!isset($$__v) || !is_object($$__v)) { continue; }
    $candidate = $$__v;
    if (is_a($candidate, 'modX') || is_a($candidate, 'MODX\\Revolution\\modX')) {
        $modx = $candidate;
        break;
    }
    if (isset($candidate->xpdo) && is_object($candidate->xpdo)
        && (is_a($candidate->xpdo, 'xPDO') || is_a($candidate->xpdo, 'xPDO\\xPDO'))) {
        $modx = $candidate->xpdo;
        break;
    }
}
if (!$modx && isset($GLOBALS['modx']) && is_object($GLOBALS['modx'])) {
    $candidate = $GLOBALS['modx'];
    if (is_a($candidate, 'modX') || is_a($candidate, 'MODX\\Revolution\\modX')) {
        $modx = $candidate;
    }
}
if (!$modx) { return true; }

$transportClass = class_exists('xPDO\\Transport\\xPDOTransport') ? 'xPDO\\Transport\\xPDOTransport' : 'xPDOTransport';
$packageActionKey = constant($transportClass . '::PACKAGE_ACTION');
$actionInstall = constant($transportClass . '::ACTION_INSTALL');
$actionUpgrade = constant($transportClass . '::ACTION_UPGRADE');
$action = isset($options[$packageActionKey]) ? $options[$packageActionKey] : '';

if ($action === $actionInstall || $action === $actionUpgrade) {
    $versionData = method_exists($modx, 'getVersionData') ? $modx->getVersionData() : array();
    $settingClass = isset($versionData['version']) && (int)$versionData['version'] >= 3
        ? 'MODX\\Revolution\\modSystemSetting'
        : 'modSystemSetting';
    $logClass = get_class($modx);
    $logError = defined($logClass . '::LOG_LEVEL_ERROR') ? constant($logClass . '::LOG_LEVEL_ERROR') : 3;
    $logInfo = defined($logClass . '::LOG_LEVEL_INFO') ? constant($logClass . '::LOG_LEVEL_INFO') : 1;

    $setting = $modx->getObject($settingClass, array('key' => 'modxmcp.api_token'));
    if (!$setting) {
        $modx->log($logError, '[modxMCP] Required system setting modxmcp.api_token was not created.');
        return false;
    }
    if (trim((string)$setting->get('value')) === '') {
        $bytes = false;
        if (function_exists('random_bytes')) {
            try {
                $bytes = random_bytes(32);
            } catch (Exception $e) {
                $bytes = false;
            } catch (Throwable $e) {
                $bytes = false;
            }
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $strong = false;
            $bytes = openssl_random_pseudo_bytes(32, $strong);
            if (!$strong) { $bytes = false; }
        }

        if (!is_string($bytes) || strlen($bytes) !== 32) {
            $modx->log($logError, '[modxMCP] Cannot generate a cryptographically secure API token.');
            return false;
        }

        $setting->set('value', bin2hex($bytes));
        if (!$setting->save()) {
            $modx->log($logError, '[modxMCP] Could not save generated modxmcp.api_token.');
            return false;
        }
        $modx->log(
            $logInfo,
            '[modxMCP] Generated modxmcp.api_token. The component is enabled; copy the token from System Settings (modxmcp) or Components > modxMCP into your MCP client.'
        );
    }
    if ($modx->getCacheManager()) {
        $modx->getCacheManager()->refresh();
    }
}

return $success;
