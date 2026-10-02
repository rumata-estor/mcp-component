<?php
namespace ModxMcp\Tools;

class ResourceListTool implements ToolInterface
{
    public function name() { return 'list_resources'; }
    public function group() { return 'code_search'; }
    public function isMutation() { return false; }

    public function supports($context)
    {
        return $context && $context->modx() && $context->platform();
    }

    public function execute($context, array $data)
    {
        $modx = $context->modx();
        $resourceClass = $context->platform()->className('resource');

        $limit = isset($data['limit']) ? (int) $data['limit'] : 100;
        if ($limit < 1) { $limit = 1; }
        if ($limit > 500) { $limit = 500; }
        $start = isset($data['start']) ? max(0, (int) $data['start']) : 0;

        $c = $modx->newQuery($resourceClass);
        $and = array();
        if (isset($data['parent']) && $data['parent'] !== '') { $and['parent'] = (int) $data['parent']; }
        if (!empty($data['context'])) { $and['context_key'] = (string) $data['context']; }
        if (!empty($and)) { $c->where($and); }
        if (!empty($data['query'])) {
            $q = trim((string) $data['query']);
            $c->where(array(array(
                'pagetitle:LIKE' => '%' . $q . '%',
                'OR:alias:LIKE' => '%' . $q . '%',
                'OR:uri:LIKE' => '%' . $q . '%',
            )));
        }

        $total = $modx->getCount($resourceClass, $c);
        $c->sortby('parent', 'ASC');
        $c->sortby('menuindex', 'ASC');
        $c->limit($limit, $start);

        $rows = array();
        foreach ($modx->getCollection($resourceClass, $c) as $r) {
            $rows[] = array(
                'id' => (int) $r->get('id'),
                'pagetitle' => $r->get('pagetitle'),
                'alias' => $r->get('alias'),
                'uri' => $r->get('uri'),
                'parent' => (int) $r->get('parent'),
                'template' => (int) $r->get('template'),
                'published' => (bool) $r->get('published'),
                'isfolder' => (bool) $r->get('isfolder'),
                'class_key' => $r->get('class_key'),
                'context_key' => $r->get('context_key'),
            );
        }

        return array('total' => (int) $total, 'count' => count($rows), 'start' => $start, 'results' => $rows);
    }
}
