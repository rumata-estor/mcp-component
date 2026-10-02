<?php
namespace ModxMcp\Tools;

class MigxConfigListTool implements ToolInterface
{
    public function name() { return 'migx_list_configs'; }
    public function group() { return 'migx'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        MigxSupport::load($context);
        $modx = $context->modx();
        $query = $modx->newQuery('migxConfig');
        $query->where(array('deleted' => 0));
        if (!empty($data['query'])) {
            $query->where(array(
                'name:LIKE' => '%' . $data['query'] . '%',
                'OR:category:LIKE' => '%' . $data['query'] . '%',
            ));
        }
        if (!empty($data['category'])) {
            $query->where(array('category' => (string)$data['category']));
        }
        $total = $modx->getCount('migxConfig', $query);
        $query->sortby('name', 'ASC');
        $limit = MigxSupport::limit($data);
        if ($limit > 0) {
            $query->limit($limit, MigxSupport::start($data));
        }
        $rows = array();
        foreach ($modx->getCollection('migxConfig', $query) as $config) {
            $rows[] = array(
                'id' => (int)$config->get('id'),
                'name' => $config->get('name'),
                'category' => $config->get('category'),
                'published' => (int)$config->get('published'),
            );
        }
        return array('total' => $total, 'results' => $rows);
    }
}
