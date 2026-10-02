<?php
/** MODX 2 bootstrap used by build/install entrypoints. */
function modxmcp_bootstrap_modx2($configCore)
{
    require_once $configCore;
    require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

    $modx = new modX();
    $modx->initialize('mgr');
    if (method_exists($modx, 'getService')) {
        $modx->getService('error', 'error.modError');
    }
    return $modx;
}
