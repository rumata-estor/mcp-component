<?php
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.core.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$modx = new modX();
$modx->initialize('mgr');
if (method_exists($modx, 'getService')) {
    $modx->getService('error', 'error.modError');
}
$modxmcpVariant = 'modx2';
require MODX_CORE_PATH . 'components/modxmcp/endpoint/api.common.php';
