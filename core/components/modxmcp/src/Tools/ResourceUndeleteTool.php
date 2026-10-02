<?php
namespace ModxMcp\Tools;

class ResourceUndeleteTool implements ToolInterface
{
    public function name() { return 'undelete_resource'; }
    public function group() { return 'elements'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        if (empty($data['id'])) {
            throw new \ModxMCPClientException('undelete_resource: "id" is required.');
        }
        $id = (int)$data['id'];
        $response = $context->platform()->runProcessor(
            $context->modx(),
            'resource/undelete',
            array('id' => $id)
        );
        if (!$response || $response->isError()) {
            throw new \ModxMCPClientException(
                $response
                    ? ProcessorSupport::error($response)
                    : 'undelete_resource: no response.'
            );
        }
        ElementMutationSupport::refreshCache($context);
        AuditSupport::log($context, $this->name(), 'resource', array('id' => $id));
        return array('undeleted' => true, 'id' => $id);
    }
}
