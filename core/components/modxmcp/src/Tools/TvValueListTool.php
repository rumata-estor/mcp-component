<?php
namespace ModxMcp\Tools;

class TvValueListTool implements ToolInterface
{
    public function name() { return 'list_tv_values'; }
    public function group() { return 'resource_tvs'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $modx = $context->modx();
        $platform = $context->platform();
        $tvClass = $platform->className('tv');
        $valueClass = $platform->className('template_var_resource');

        $tvId = isset($data['tv_id']) ? (int) $data['tv_id'] : (isset($data['id']) ? (int) $data['id'] : 0);
        $tvName = isset($data['tv_name']) ? trim((string) $data['tv_name']) : (isset($data['name']) ? trim((string) $data['name']) : '');

        $tv = null;
        if ($tvId > 0) {
            $tv = $modx->getObject($tvClass, $tvId);
        } elseif ($tvName !== '') {
            $tv = $modx->getObject($tvClass, array('name' => $tvName));
        } else {
            throw new \ModxMCPClientException('tv_id or tv_name is required.');
        }
        if (!$tv) {
            throw new \ModxMCPClientException('TV not found.');
        }

        $tvId = (int) $tv->get('id');
        $start = !empty($data['start']) ? max(0, (int) $data['start']) : 0;
        $limit = array_key_exists('limit', $data) ? max(1, min((int) $data['limit'], 500)) : 100;
        $criteria = array('tmplvarid' => $tvId);
        $total = (int) $modx->getCount($valueClass, $criteria);

        $query = $modx->newQuery($valueClass);
        $query->where($criteria);
        $query->sortby('contentid', 'ASC');
        $query->limit($limit, $start);

        $values = array();
        foreach ($modx->getCollection($valueClass, $query) as $row) {
            $values[] = array(
                'resource_id' => (int) $row->get('contentid'),
                'value' => $row->get('value'),
            );
        }

        return array(
            'tv' => array(
                'id' => $tvId,
                'name' => (string) $tv->get('name'),
                'caption' => (string) $tv->get('caption'),
            ),
            'total' => $total,
            'count' => count($values),
            'start' => $start,
            'limit' => $limit,
            'values' => $values,
        );
    }
}
