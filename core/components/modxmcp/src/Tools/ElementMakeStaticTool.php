<?php
namespace ModxMcp\Tools;

class ElementMakeStaticTool implements ToolInterface
{
    public function name() { return 'make_static'; }
    public function group() { return 'elements'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        if (isset($data['items']) && is_array($data['items'])) {
            $results = array();
            foreach ($data['items'] as $item) {
                $type = isset($item['type']) ? (string)$item['type'] : '';
                $id = isset($item['id']) ? (int)$item['id'] : 0;
                try {
                    $results[] = ElementMutationSupport::makeStatic(
                        $context,
                        $type,
                        $id
                    );
                } catch (\Exception $e) {
                    $results[] = array(
                        'type' => $type,
                        'id' => $id,
                        'error' => $e->getMessage(),
                    );
                }
            }
            ElementMutationSupport::refreshCache($context);
            AuditSupport::log(
                $context,
                'make_static',
                'batch',
                array('count' => count($results))
            );
            return array(
                'count' => count($results),
                'results' => $results,
            );
        }

        $result = ElementMutationSupport::makeStatic(
            $context,
            isset($data['type']) ? (string)$data['type'] : '',
            isset($data['id']) ? (int)$data['id'] : 0
        );
        ElementMutationSupport::refreshCache($context);
        AuditSupport::log(
            $context,
            'make_static',
            $result['type'],
            array('id' => $result['id'])
        );
        return $result;
    }
}
