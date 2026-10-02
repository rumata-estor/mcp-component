<?php
/**
 * System settings shipped with modxMCP.
 *
 * This file is intentionally MODX 2/3 neutral: transport data is shared,
 * while the concrete xPDO class name is selected from the running platform.
 */
$settings = array();

$defs = array(
    array('modxmcp.enabled', 1, 'combo-boolean', 'modxmcp:main'),
    array('modxmcp.api_token', '', 'textfield', 'modxmcp:main'),
    array('modxmcp.service_user_id', 0, 'textfield', 'modxmcp:main'),
    array('modxmcp.audit_log', 1, 'combo-boolean', 'modxmcp:main'),
    array('modxmcp.debug', 0, 'combo-boolean', 'modxmcp:main'),
    array('modxmcp.auto_static', 0, 'combo-boolean', 'modxmcp:main'),
    array('modxmcp.disabled_groups', 'versionx,virtualpage,minishop2,migx,access,property_sets,contexts,package_management,namespaces,lexicon', 'textfield', 'modxmcp:main'),
    array('modxmcp.allow_run_processor', 0, 'combo-boolean', 'modxmcp:security'),
    array('modxmcp.max_payload_bytes', 1048576, 'textfield', 'modxmcp:limits'),
    array('modxmcp.max_read_bytes', 262144, 'textfield', 'modxmcp:limits'),
    array('modxmcp.allow_root_filesystem_read', 0, 'combo-boolean', 'modxmcp:security'),
    array('modxmcp.require_https', 1, 'combo-boolean', 'modxmcp:security'),
    array('modxmcp.trust_proxy_https', 0, 'combo-boolean', 'modxmcp:security'),
    array('modxmcp.allowed_ips', '', 'textfield', 'modxmcp:security'),
    array('modxmcp.component_code_roots', 'core/components,assets/components', 'textfield', 'modxmcp:security'),
    array('modxmcp.core_path', '{core_path}components/modxmcp/', 'textfield', 'modxmcp:paths'),
);

$versionData = method_exists($modx, 'getVersionData') ? $modx->getVersionData() : array();
$modxMajor = isset($versionData['version']) ? (int)$versionData['version'] : 0;
$settingClass = $modxMajor >= 3 ? 'MODX\\Revolution\\modSystemSetting' : 'modSystemSetting';

foreach ($defs as $d) {
    $setting = $modx->newObject($settingClass);
    if (!$setting) {
        throw new RuntimeException('Could not create MODX system setting object for ' . $d[0]);
    }
    $setting->fromArray(array(
        'key' => $d[0],
        'value' => $d[1],
        'xtype' => $d[2],
        'namespace' => 'modxmcp',
        'area' => $d[3],
    ), '', true, true);
    $settings[$d[0]] = $setting;
}

unset($defs, $d, $versionData, $modxMajor, $settingClass);
return $settings;
