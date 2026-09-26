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
    $disableApi = static function () use ($modx) {
        $enabled = $modx->getObject(modSystemSetting::class, array('key' => 'modxmcp.enabled'));
        if ($enabled) {
            $enabled->set('value', 0);
            if (!$enabled->save()) {
                $modx->log(modX::LOG_LEVEL_ERROR, '[MODX3 MCP] Failed to persist modxmcp.enabled=0 while failing closed.');
            }
        }
    };

    $setting = $modx->getObject(modSystemSetting::class, array('key' => 'modxmcp.api_token'));
    if (!$setting) {
        $disableApi();
        $modx->log(modX::LOG_LEVEL_ERROR, '[MODX3 MCP] modxmcp.api_token setting is missing; the API was disabled.');
        return false;
    }
    if (trim((string) $setting->get('value')) === '') {
        try {
            $token = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            $disableApi();
            $modx->log(modX::LOG_LEVEL_ERROR, '[MODX3 MCP] Secure API token generation failed; the API was disabled.');
            return false;
        }
        $setting->set('value', $token);
        if (!$setting->save()) {
            $disableApi();
            $modx->log(modX::LOG_LEVEL_ERROR, '[MODX3 MCP] Could not save the generated API token; the API was disabled.');
            return false;
        }
        $modx->log(
            modX::LOG_LEVEL_INFO,
            '[MODX3 MCP] Generated modxmcp.api_token. Copy it from System Settings (modxmcp) or explicitly regenerate it from the CMP if you need a new visible value.'
        );
    }
    if ($modx->getCacheManager()) {
        $modx->getCacheManager()->refresh();
    }
}

return $success;