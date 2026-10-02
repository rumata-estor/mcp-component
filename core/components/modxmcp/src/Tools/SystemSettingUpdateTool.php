<?php
namespace ModxMcp\Tools;

class SystemSettingUpdateTool implements ToolInterface
{
    public function name() { return 'update_system_setting'; }
    public function group() { return 'system'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $setting = $this->resolve($context, $data);
        if (!$setting) {
            throw new \ModxMCPClientException('System setting not found.');
        }

        foreach (array('key', 'value', 'xtype', 'namespace', 'area') as $field) {
            if (array_key_exists($field, $data)) {
                $setting->set($field, $data[$field]);
            }
        }
        if (!$setting->save()) {
            throw new \ModxMCPClientException(
                'Failed to update system setting.'
            );
        }

        $context->modx()->cacheManager->refresh();
        AuditSupport::log(
            $context,
            $this->name(),
            'system_setting',
            array('key' => $setting->get('key'))
        );
        return SystemSettingListTool::normalize($setting);
    }

    private function resolve($context, array $data)
    {
        $class = $context->platform()->className('system_setting');
        if (!empty($data['key'])) {
            return $context->modx()->getObject(
                $class,
                array('key' => $data['key'])
            );
        }
        if (!empty($data['id'])) {
            return $context->modx()->getObject(
                $class,
                array('id' => (int)$data['id'])
            );
        }
        return null;
    }
}
