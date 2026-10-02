<?php
namespace ModxMcp\Tools;

class VersionXVersionRevertTool implements ToolInterface
{
    public function name() { return 'versionx_revert_version'; }
    public function group() { return 'versionx'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $meta = VersionXSupport::mutationMeta($context, $data);
        $contentId = VersionXSupport::positiveInt($data, 'content_id');
        $versionId = VersionXSupport::positiveInt($data, 'version_id');

        if (empty($data['confirm'])) {
            throw new \ModxMCPClientException(
                'confirm=true is required to revert a VersionX version.'
            );
        }

        VersionXSupport::load($context);
        $modx = $context->modx();
        $before = $modx->getObject($meta['content_class'], $contentId);
        $version = $modx->getObject($meta['class'], array(
            'content_id' => $contentId,
            'version_id' => $versionId,
        ));
        if (!$version) {
            throw new \ModxMCPClientException(
                'VersionX version not found: ' . $data['type']
                . ' content_id=' . $contentId
                . ' version_id=' . $versionId . '.'
            );
        }

        $response = $modx->runProcessor(
            $meta['processor'] . '/revert',
            array(
                'content_id' => $contentId,
                'version_id' => $versionId,
            ),
            array(
                'processors_path' => VersionXSupport::corePath($context)
                    . 'processors/mgr/',
            )
        );
        if (!$response || $response->isError()) {
            throw new \ModxMCPClientException(
                'VersionX revert failed: '
                . ($response
                    ? ProcessorSupport::error($response)
                    : 'No processor response.')
            );
        }

        $after = $modx->getObject($meta['content_class'], $contentId);
        AuditSupport::log(
            $context,
            $this->name(),
            (string)$data['type'],
            array(
                'content_id' => $contentId,
                'version_id' => $versionId,
            )
        );

        return array(
            'reverted' => true,
            'type' => $data['type'],
            'content_id' => $contentId,
            'version_id' => $versionId,
            'version' => VersionXSupport::normalize($version, $meta, false),
            'before_exists' => (bool)$before,
            'after_exists' => (bool)$after,
            'after' => $after ? array(
                'id' => $after->get('id'),
                'class_key' => $after->get('class_key'),
                'name' => $this->label($after),
            ) : null,
        );
    }

    private function label($object)
    {
        foreach (array('pagetitle', 'name', 'templatename', 'caption') as $field) {
            $value = $object->get($field);
            if ($value !== null && $value !== '') { return $value; }
        }
        return '';
    }
}
