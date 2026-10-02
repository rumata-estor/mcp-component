<?php
namespace ModxMcp\Tools;

class FindUsagesTool implements ToolInterface
{
    public function name() { return 'find_usages'; }
    public function group() { return 'elements'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $name = isset($data['name']) ? trim((string)$data['name']) : '';
        if ($name === '') {
            throw new \ModxMCPClientException('find_usages: "name" is required.');
        }
        $limit = isset($data['limit']) ? (int)$data['limit'] : 100;
        $search = SearchSupport::search($context, array('query' => $name, 'limit' => $limit));
        $usages = $search['results'];
        $modx = $context->modx();
        $templateClass = $context->platform()->className('template');
        $resourceClass = $context->platform()->className('resource');
        $template = $modx->getObject($templateClass, array('templatename' => $name));
        if (!$template) {
            return array('name' => $name, 'content_matches' => $usages);
        }

        $templateId = (int)$template->get('id');
        $query = $modx->newQuery($resourceClass, array('template' => $templateId));
        $total = $modx->getCount($resourceClass, $query);
        $query->limit($limit);
        $resources = array();
        foreach ($modx->getCollection($resourceClass, $query) as $resource) {
            $resources[] = array(
                'id' => (int)$resource->get('id'),
                'pagetitle' => $resource->get('pagetitle'),
                'uri' => $resource->get('uri'),
            );
        }
        return array(
            'name' => $name,
            'content_matches' => $usages,
            'template_id' => $templateId,
            'resources_using_template_total' => (int)$total,
            'resources_using_template' => $resources,
        );
    }
}
