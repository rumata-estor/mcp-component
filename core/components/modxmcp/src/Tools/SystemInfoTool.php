<?php
namespace ModxMcp\Tools;

class SystemInfoTool implements ToolInterface
{
    public function name() { return 'system_info'; }
    public function group() { return 'ops'; }
    public function isMutation() { return false; }

    public function supports($context)
    {
        if (!$context || !$context->modx() || !$context->platform()) {
            return false;
        }
        $key = $context->platform()->key();
        return $key === 'modx2' || $key === 'modx3';
    }

    public function execute($context, array $arguments)
    {
        $modx = $context->modx();
        $v = method_exists($modx, 'getVersionData') ? $modx->getVersionData() : array();
        return array(
            'modx_version'    => isset($v['full_version']) ? $v['full_version'] : (isset($v['version']) ? $v['version'] : null),
            'modxmcp_version' => $context->connectorVersion(),
            'php_version'     => PHP_VERSION,
            'dbtype'          => $modx->getOption('dbtype'),
            'base_path'       => $modx->getOption('base_path'),
            'core_path'       => $modx->getOption('core_path'),
        );
    }
}
