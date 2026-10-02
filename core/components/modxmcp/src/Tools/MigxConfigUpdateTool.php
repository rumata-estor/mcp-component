<?php
namespace ModxMcp\Tools;

class MigxConfigUpdateTool implements ToolInterface
{
    public function name() { return 'migx_update_config'; }
    public function group() { return 'migx'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        MigxSupport::load($context);
        if (empty($data['id'])) {
            throw new \ModxMCPClientException('migx_update_config: id is required.');
        }
        $config = $context->modx()->getObject('migxConfig', (int)$data['id']);
        if (!$config) {
            throw new \ModxMCPClientException(
                'migx_update_config: config ' . (int)$data['id'] . ' not found.'
            );
        }

        foreach (MigxMutationSupport::fields() as $field) {
            if (array_key_exists($field, $data)) {
                $config->set($field, $data[$field]);
            }
        }
        if (!$config->save()) {
            throw new \ModxMCPClientException('migx save_config: save failed.');
        }

        MigxMutationSupport::refresh($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'migx',
            array(
                'id' => (int)$config->get('id'),
                'name' => $config->get('name'),
            )
        );
        return array(
            'id' => (int)$config->get('id'),
            'name' => $config->get('name'),
            'category' => $config->get('category'),
        );
    }
}
