<?php
namespace ModxMcp\Tools;

class SystemSettingGetTool implements ToolInterface
{
    public function name() { return 'get_system_setting'; }
    public function group() { return 'system'; }
    public function isMutation() { return false; }

    public function supports($context)
    {
        return $context && $context->modx() && $context->platform();
    }

    public function execute($context, array $data)
    {
        $modx = $context->modx();
        $class = $context->platform()->className('system_setting');

        $setting = null;
        if (!empty($data['key'])) {
            $setting = $modx->getObject($class, array('key' => $data['key']));
        } elseif (!empty($data['id'])) {
            $setting = $modx->getObject($class, array('id' => (int) $data['id']));
        }

        if (!$setting) {
            throw new \ModxMCPClientException('System setting not found.');
        }
        return SystemSettingListTool::normalize($setting);
    }
}
