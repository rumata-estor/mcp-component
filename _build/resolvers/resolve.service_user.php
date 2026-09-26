<?php

use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modUser;
use MODX\Revolution\modX;
use xPDO\Transport\xPDOTransport;
use xPDO\xPDO;

/**
 * Resolve modxmcp.service_user_id without assuming that administrator is user #1.
 *
 * On install/upgrade:
 * - keep an existing active sudo user;
 * - otherwise prefer the current Manager user when it is active + sudo;
 * - otherwise auto-select only when the site has exactly one active sudo user;
 * - if selection is ambiguous/impossible, disable the API and leave service_user_id = 0.
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

$setting = $modx->getObject(modSystemSetting::class, array('key' => 'modxmcp.service_user_id'));
if (!$setting) {
    return $success;
}

$isUsable = static function ($user) {
    return $user instanceof modUser && (bool)$user->get('active') && (bool)$user->get('sudo');
};

$currentId = (int)$setting->get('value');
if ($currentId > 0) {
    $configured = $modx->getObject(modUser::class, $currentId);
    if ($isUsable($configured)) {
        return $success;
    }
}

$candidate = null;
if (isset($modx->user) && $isUsable($modx->user) && (int)$modx->user->get('id') > 0) {
    $candidate = $modx->user;
} else {
    $q = $modx->newQuery(modUser::class);
    $q->where(array('active' => 1, 'sudo' => 1));
    $q->sortby('id', 'ASC');
    $q->limit(2);
    $sudoUsers = array_values($modx->getCollection(modUser::class, $q));
    if (count($sudoUsers) === 1 && $isUsable($sudoUsers[0])) {
        $candidate = $sudoUsers[0];
    }
}

if ($candidate) {
    $candidateId = (int)$candidate->get('id');
    $setting->set('value', $candidateId);
    $setting->save();
    $modx->log(modX::LOG_LEVEL_INFO, '[modxMCP] service_user_id configured automatically: user #' . $candidateId . '.');
    if ($modx->getCacheManager()) {
        $modx->getCacheManager()->refresh();
    }
    return $success;
}

$setting->set('value', 0);
$setting->save();
$enabled = $modx->getObject(modSystemSetting::class, array('key' => 'modxmcp.enabled'));
if ($enabled) {
    $enabled->set('value', 0);
    $enabled->save();
}
$modx->log(
    modX::LOG_LEVEL_WARN,
    '[modxMCP] Could not choose an active sudo service user safely. The API was disabled. Set modxmcp.service_user_id explicitly, then enable modxmcp.enabled.'
);
if ($modx->getCacheManager()) {
    $modx->getCacheManager()->refresh();
}

return $success;