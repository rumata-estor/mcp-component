<?php
namespace ModxMcp\Tools;

class ResourceTvUpdateTool implements ToolInterface
{
    public function name() { return 'update_resource_tvs'; }
    public function group() { return 'resource_tvs'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $resourceId = !empty($data['resource_id'])
            ? (int)$data['resource_id']
            : 0;
        if ($resourceId <= 0) {
            throw new \ModxMCPClientException('resource_id is required.');
        }
        if (empty($data['tvs']) || !is_array($data['tvs'])) {
            throw new \ModxMCPClientException(
                'tvs payload must be a non-empty object/array.'
            );
        }

        $resource = $context->modx()->getObject(
            $context->platform()->className('resource'),
            $resourceId
        );
        if (!$resource) {
            throw new \ModxMCPClientException(
                'Resource not found: ' . $resourceId . '.'
            );
        }

        foreach ($data['tvs'] as $name => $value) {
            $resource->setTVValue($name, $value);
        }

        $context->modx()->cacheManager->refresh();
        AuditSupport::log(
            $context,
            $this->name(),
            'resource_tv',
            array(
                'resource_id' => $resourceId,
                'tv_keys' => array_keys($data['tvs']),
            )
        );
        return (new ResourceTvListTool())->execute(
            $context,
            array('resource_id' => $resourceId)
        );
    }
}
