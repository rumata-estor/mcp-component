<?php
namespace ModxMcp\Tools;

class MediaFileDeleteTool implements ToolInterface
{
    public function name() { return 'delete_media_file'; }
    public function group() { return 'media'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $source = MediaSourceMutationSupport::resolveForFileOperation($context, $data);
        $path = isset($data['path']) ? (string)$data['path'] : '';
        if ($path === '') {
            throw new \ModxMCPClientException(
                'delete_media_file: "path" is required.'
            );
        }
        $result = $source->removeObject($path);
        if ($result === false) {
            throw new \ModxMCPClientException(
                'delete_media_file failed: '
                . MediaSourceMutationSupport::error($source, 'unknown error')
            );
        }
        MediaSourceMutationSupport::refresh($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'source',
            array('source' => (int)$source->get('id'), 'path' => $path)
        );
        return array('deleted' => true, 'path' => $path);
    }
}
