<?php
/** MODX 3 bootstrap used by build/install entrypoints. */
function modxmcp_bootstrap_modx3($configCore)
{
    require_once $configCore;
    $autoload = rtrim(MODX_CORE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('MODX 3 vendor/autoload.php not found.');
    }
    require_once $autoload;

    return \MODX\Revolution\modX::getInstance();
}
