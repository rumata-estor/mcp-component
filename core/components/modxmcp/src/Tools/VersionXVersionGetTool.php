<?php
namespace ModxMcp\Tools;

class VersionXVersionGetTool implements ToolInterface
{
    public function name() { return 'versionx_get_version'; }
    public function group() { return 'versionx'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $meta = VersionXSupport::type($data);
        $contentId = VersionXSupport::positiveInt($data, 'content_id');
        $versionId = VersionXSupport::positiveInt($data, 'version_id');
        VersionXSupport::load($context);

        $version = $context->modx()->getObject($meta['class'], array(
            'content_id' => $contentId,
            'version_id' => $versionId,
        ));
        if (!$version) {
            throw new \ModxMCPClientException(
                'VersionX version not found: ' . $data['type']
                . ' content_id=' . $contentId . ' version_id=' . $versionId . '.'
            );
        }
        return VersionXSupport::normalize($version, $meta, true);
    }
}
