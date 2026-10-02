<?php
namespace ModxMcp\Tools;

class ResourceTvListTool implements ToolInterface
{
    public function name() { return 'get_resource_tvs'; }
    public function group() { return 'resource_tvs'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $resourceId = !empty($data['resource_id']) ? (int) $data['resource_id'] : 0;
        if ($resourceId <= 0) {
            throw new \ModxMCPClientException('resource_id is required.');
        }

        $modx = $context->modx();
        $platform = $context->platform();
        $resource = $modx->getObject($platform->className('resource'), $resourceId);
        if (!$resource) {
            throw new \ModxMCPClientException("Resource not found: {$resourceId}.");
        }

        $templateId = (int) $resource->get('template');
        $tvLinks = $modx->getCollection(
            $platform->className('template_var_template'),
            array('templateid' => $templateId)
        );
        $result = array();
        foreach ($tvLinks as $link) {
            $tv = $modx->getObject($platform->className('tv'), $link->get('tmplvarid'));
            if (!$tv) { continue; }
            $result[] = array(
                'id' => $tv->get('id'),
                'name' => $tv->get('name'),
                'caption' => $tv->get('caption'),
                'type' => $tv->get('type'),
                'value' => $resource->getTVValue($tv->get('name')),
            );
        }

        return array(
            'resource_id' => $resourceId,
            'template_id' => $templateId,
            'tvs' => $result,
        );
    }
}
