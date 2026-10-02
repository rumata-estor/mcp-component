<?php
namespace ModxMcp\Tools;

class ProviderDeleteTool implements ToolInterface
{
    public function name() { return 'delete_provider'; }
    public function group() { return 'package_management'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        if (empty($data['id'])) {
            throw new \ModxMCPClientException('delete_provider: id is required.');
        }
        $id = (int)$data['id'];
        $response = $context->platform()->runProcessor(
            $context->modx(),
            'workspace/providers/remove',
            array('id' => $id)
        );
        if (!$response || $response->isError()) {
            throw new \ModxMCPClientException(
                $response
                    ? ProcessorSupport::error($response)
                    : 'delete_provider: no response.'
            );
        }
        AuditSupport::log(
            $context,
            $this->name(),
            'provider',
            array('id' => $id)
        );
        return array('deleted' => true, 'id' => $id);
    }
}
