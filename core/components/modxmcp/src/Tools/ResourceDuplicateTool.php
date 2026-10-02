<?php
namespace ModxMcp\Tools;

class ResourceDuplicateTool implements ToolInterface
{
    public function name() { return 'duplicate_resource'; }
    public function group() { return 'elements'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        if (empty($data['id'])) {
            throw new \ModxMCPClientException('duplicate_resource: "id" is required.');
        }
        $props = array('id' => (int)$data['id']);
        if (!empty($data['name'])) { $props['name'] = (string)$data['name']; }
        if (isset($data['duplicate_children'])) {
            $props['duplicate_children'] = (bool)$data['duplicate_children'];
        }
        if (!empty($data['published_mode'])) {
            $props['published_mode'] = (string)$data['published_mode'];
        }

        $response = $context->platform()->runProcessor(
            $context->modx(),
            'resource/duplicate',
            $props
        );
        if (!$response || $response->isError()) {
            throw new \ModxMCPClientException(
                $response
                    ? ProcessorSupport::error($response)
                    : 'duplicate_resource: no response.'
            );
        }
        ElementMutationSupport::refreshCache($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'resource',
            array('id' => (int)$data['id'])
        );
        return ProcessorSupport::normalize($response);
    }
}
