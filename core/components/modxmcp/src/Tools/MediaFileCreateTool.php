<?php
namespace ModxMcp\Tools;

class MediaFileCreateTool implements ToolInterface
{
    public function name() { return 'create_media_file'; }
    public function group() { return 'media'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $source = MediaSourceMutationSupport::resolveForFileOperation($context, $data);
        $dir = isset($data['path']) ? trim((string)$data['path'], '/') : '';
        $dir = $dir === '' ? '/' : $dir . '/';
        $name = isset($data['name']) ? (string)$data['name'] : '';
        if ($name === '') {
            throw new \ModxMCPClientException(
                'create_media_file: "name" is required.'
            );
        }
        $result = $source->createObject(
            $dir,
            $name,
            isset($data['content']) ? (string)$data['content'] : ''
        );
        if ($result === false) {
            throw new \ModxMCPClientException(
                'create_media_file failed: '
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
                'path' => $dir,
                'name' => $name,
            )
        );
        return array(
            'created' => true,
            'path' => rtrim($dir, '/') . '/' . $name,
        );
    }
}
