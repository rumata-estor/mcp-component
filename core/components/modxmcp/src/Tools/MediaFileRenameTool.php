<?php
namespace ModxMcp\Tools;

class MediaFileRenameTool implements ToolInterface
{
    public function name() { return 'rename_media_file'; }
    public function group() { return 'media'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $source = MediaSourceMutationSupport::resolveForFileOperation($context, $data);
        $path = isset($data['path']) ? (string)$data['path'] : '';
        $newName = isset($data['new_name']) ? (string)$data['new_name'] : '';
        if ($path === '' || $newName === '') {
            throw new \ModxMCPClientException(
                'rename_media_file: "path" and "new_name" are required.'
            );
        }
        $result = $source->renameObject($path, $newName);
        if ($result === false) {
            throw new \ModxMCPClientException(
                'rename_media_file failed: '
                . MediaSourceMutationSupport::error($source, 'unknown error')
            );
        }
        MediaSourceMutationSupport::refresh($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'source',
            array(
                'source' => (int)$source->get('id'),
                'path' => $path,
                'new_name' => $newName,
            )
        );
        return array(
            'renamed' => true,
            'path' => $path,
            'new_name' => $newName,
        );
    }
}
