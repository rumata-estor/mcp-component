<?php
namespace ModxMcp\Tools;

class MigxConfigDeleteTool implements ToolInterface
{
    public function name() { return 'migx_delete_config'; }
    public function group() { return 'migx'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        MigxSupport::load($context);
        if (empty($data['id'])) {
            throw new \ModxMCPClientException('migx_delete_config: id is required.');
        }
        $config = $context->modx()->getObject('migxConfig', (int)$data['id']);
        if (!$config) {
            throw new \ModxMCPClientException(
                'migx_delete_config: config ' . (int)$data['id'] . ' not found.'
            );
        }
        $name = $config->get('name');
        if (!$config->remove()) {
            throw new \ModxMCPClientException('migx_delete_config: remove failed.');
        }

        MigxMutationSupport::refresh($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'migx',
            array('id' => (int)$data['id'], 'name' => $name)
        );
        return array(
            'deleted' => true,
            'id' => (int)$data['id'],
            'name' => $name,
        );
    }
}
