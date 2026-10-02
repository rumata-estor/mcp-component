<?php
namespace ModxMcp\Tools;

class MediaFolderDeleteTool implements ToolInterface
{
    public function name() { return 'delete_media_folder'; }
    public function group() { return 'media'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $source = MediaSourceMutationSupport::resolveForFileOperation($context, $data);
        $path = isset($data['path'])
            ? trim((string)$data['path'], '/')
            : '';
        if ($path === '') {
            throw new \ModxMCPClientException(
                'delete_media_folder: "path" is required '
                . '(refusing to remove the source root).'
            );
        }
        $result = $source->removeContainer($path);
        if ($result === false) {
            throw new \ModxMCPClientException(
                'delete_media_folder failed: '
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
