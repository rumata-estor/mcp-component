<?php
namespace ModxMcp\Tools;

class MigxConfigGetTool implements ToolInterface
{
    public function name() { return 'migx_get_config'; }
    public function group() { return 'migx'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        MigxSupport::load($context);
        if (empty($data['id'])) {
            throw new \ModxMCPClientException('migx_get_config: id is required.');
        }
        $config = $context->modx()->getObject('migxConfig', (int)$data['id']);
        if (!$config) {
            throw new \ModxMCPClientException(
                'migx_get_config: config ' . (int)$data['id'] . ' not found.'
            );
        }
        return $config->toArray();
    }
}
