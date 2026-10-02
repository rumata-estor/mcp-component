<?php
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.core.php';
$autoload = rtrim(MODX_CORE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'error' => 'MODX 3 bootstrap failed'));
    exit;
}
require_once $autoload;

$modxClass = 'MODX\\Revolution\\modX';
$modx = $modxClass::getInstance();
$modx->initialize('mgr');
$modxmcpVariant = 'modx3';
require MODX_CORE_PATH . 'components/modxmcp/endpoint/api.common.php';
