<?php
namespace ModxMcp\Tools;

class VersionXVersionListTool implements ToolInterface
{
    public function name() { return 'versionx_list_versions'; }
    public function group() { return 'versionx'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $meta = VersionXSupport::type($data);
        $contentId = VersionXSupport::positiveInt($data, 'content_id');
        $limit = !empty($data['limit']) ? max(1, min((int)$data['limit'], 100)) : 20;
        VersionXSupport::load($context);

        $query = $context->modx()->newQuery($meta['class']);
        $query->where(array('content_id' => $contentId));
        $query->sortby('saved', 'DESC');
        $query->sortby('version_id', 'DESC');
        $query->limit($limit);

        $items = array();
        foreach ($context->modx()->getCollection($meta['class'], $query) as $version) {
            $items[] = VersionXSupport::normalize($version, $meta, false);
        }
        return array(
            'type' => $data['type'],
            'content_id' => $contentId,
            'count' => count($items),
            'versions' => $items,
        );
    }
}
