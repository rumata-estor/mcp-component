<?php
namespace ModxMcp\Tools;

class ElementListTool implements ToolInterface
{
    public function name() { return 'list_elements'; }
    public function group() { return 'elements'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $type = ElementSupport::type($data);
        $modx = $context->modx();
        $class = $context->platform()->className($type);
        $nameField = ElementSupport::nameField($type);
        $limit = isset($data['limit']) ? max(0, (int) $data['limit']) : 100;
        $start = isset($data['start']) ? max(0, (int) $data['start']) : 0;

        $query = $modx->newQuery($class);
        if (!empty($data['query'])) {
            $value = '%' . trim((string) $data['query']) . '%';
            if ($type === 'resource') {
                $query->where(array(array(
                    'pagetitle:LIKE' => $value,
                    'OR:longtitle:LIKE' => $value,
                    'OR:alias:LIKE' => $value,
                )));
            } else {
                $query->where(array($nameField . ':LIKE' => $value));
            }
        }
        $query->sortby($nameField, 'ASC');
        if ($limit > 0) { $query->limit($limit, $start); }

        $result = array();
        foreach ($modx->getCollection($class, $query) as $element) {
            $result[] = array(
                'id' => (int) $element->get('id'),
                'name' => $element->get($nameField),
            );
        }
        return $result;
    }
}
