<?php
namespace ModxMcp\Tools;

class ProviderUpdateTool implements ToolInterface
{
    public function name() { return 'update_provider'; }
    public function group() { return 'package_management'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $props = $data;
        unset($props['action'], $props['elementType']);
        if (empty($props['id'])) {
            throw new \ModxMCPClientException('update_provider: id is required.');
        }
        $response = $context->platform()->runProcessor(
            $context->modx(),
            'workspace/providers/update',
            $props
        );
        if (!$response || $response->isError()) {
            throw new \ModxMCPClientException(
                $response
                    ? ProcessorSupport::error($response)
                    : 'provider: no response.'
            );
        }
        AuditSupport::log(
            $context,
            $this->name(),
            'provider',
            array_intersect_key(
                $props,
                array_flip(array('id', 'name', 'service_url'))
            )
        );
        return ProcessorSupport::normalize($response);
    }
}
