<?php
namespace ModxMcp\Tools;

class MediaFolderCreateTool implements ToolInterface
{
    public function name() { return 'create_media_folder'; }
    public function group() { return 'media'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $source = MediaSourceMutationSupport::resolveForFileOperation($context, $data);
        $parent = isset($data['parent']) ? (string)$data['parent'] : '/';
        $name = isset($data['name']) ? (string)$data['name'] : '';
        if ($name === '') {
            throw new \ModxMCPClientException(
                'create_media_folder: "name" is required.'
            );
        }
        $result = $source->createContainer($name, $parent);
        if ($result === false) {
            throw new \ModxMCPClientException(
                'create_media_folder failed: '
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
                'parent' => $parent,
                'name' => $name,
            )
        );
        return array(
            'created' => true,
            'parent' => $parent,
            'name' => $name,
        );
    }
}
