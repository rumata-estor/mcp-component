<?php
namespace ModxMcp\Tools;

class MediaSourceDeleteTool implements ToolInterface
{
    public function name() { return 'delete_media_source'; }
    public function group() { return 'media'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        if (empty($data['id'])) {
            throw new \ModxMCPClientException('delete_media_source: id is required.');
        }
        $id = (int)$data['id'];
        $response = $context->platform()->runProcessor(
            $context->modx(),
            'source/remove',
            array('id' => $id)
        );
        if (!$response) {
            throw new \ModxMCPClientException('delete_media_source: no response.');
        }
        if ($response->isError()) {
            throw new \ModxMCPClientException(ProcessorSupport::error($response));
        }

        MediaSourceMutationSupport::refresh($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'source',
            array('id' => $id)
        );
        return array('deleted' => true, 'id' => $id);
    }
}
