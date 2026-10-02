<?php
namespace ModxMcp\Tools;

class ProviderListTool implements ToolInterface
{
    public function name() { return 'list_providers'; }
    public function group() { return 'package_management'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $response = $context->platform()->runProcessor(
            $context->modx(), 'workspace/providers/getlist', array('limit' => 0)
        );
        if (!$response || $response->isError()) {
            throw new \ModxMCPClientException(
                $response ? ProcessorSupport::error($response) : 'list_providers: no response.'
            );
        }
        return PackageSupport::listResult($response);
    }
}
