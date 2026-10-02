<?php
namespace ModxMcp\Tools;

class SystemSettingDeleteTool implements ToolInterface
{
    public function name() { return 'delete_system_setting'; }
    public function group() { return 'system'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $class = $context->platform()->className('system_setting');
        $setting = null;
        if (!empty($data['key'])) {
            $setting = $context->modx()->getObject(
                $class,
                array('key' => $data['key'])
            );
        } elseif (!empty($data['id'])) {
            $setting = $context->modx()->getObject(
                $class,
                array('id' => (int)$data['id'])
            );
        }
        if (!$setting) {
            throw new \ModxMCPClientException('System setting not found.');
        }

        $key = $setting->get('key');
        if (!$setting->remove()) {
            throw new \ModxMCPClientException(
                'Failed to delete system setting: ' . $key . '.'
            );
        }
        $context->modx()->cacheManager->refresh();
        AuditSupport::log(
            $context,
            $this->name(),
            'system_setting',
            array('key' => $key)
        );
        return 'Successfully deleted system setting (' . $key . ').';
    }
}
