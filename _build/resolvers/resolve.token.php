<?php

use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modX;
use xPDO\Transport\xPDOTransport;
use xPDO\xPDO;
/**
 * Resolver: generate a random modxmcp.api_token on install/upgrade if it's empty.
 * Leaves an existing token untouched (so upgrades don't rotate it).
 *
 * Works for both object and file vehicles: $modx is resolved defensively from
 * $transport / $object / globals (file-vehicle resolvers don't get an xPDOObject $object).
 *
 * @var mixed $transport
 * @var mixed $object
 * @var array $options
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
if ($action === xPDOTransport::ACTION_INSTALL || $action === xPDOTransport::ACTION_UPGRADE) {
    $setting = $modx->getObject(modSystemSetting::class, array('key' => 'modxmcp.api_token'));
    if (!$setting) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[modxMCP] Required system setting modxmcp.api_token was not created.');
        return false;
    }
    if (trim((string) $setting->get('value')) === '') {
        try {
            $token = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            $modx->log(
                modX::LOG_LEVEL_ERROR,
                '[modxMCP] Cannot generate a cryptographically secure API token: ' . $e->getMessage()
            );
            return false;
        }
        $setting->set('value', $token);
        if (!$setting->save()) {
            $modx->log(modX::LOG_LEVEL_ERROR, '[modxMCP] Could not save generated modxmcp.api_token.');
            return false;
        }
        $modx->log(
            modX::LOG_LEVEL_INFO,
            '[modxMCP] Generated modxmcp.api_token. The component is enabled; copy the token from System Settings (modxmcp) or Components > modxMCP into your MCP client.'
        );
    }
    if ($modx->getCacheManager()) {
        $modx->getCacheManager()->refresh();
    }
}

return $success;