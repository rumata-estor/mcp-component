<?php
namespace ModxMcp\Tools;

class ResourceReorderTool implements ToolInterface
{
    public function name() { return 'reorder_resources'; }
    public function group() { return 'elements'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $items = isset($data['items']) && is_array($data['items'])
            ? $data['items']
            : null;
        if (!$items) {
            throw new \ModxMCPClientException(
                'reorder_resources: "items" (list of {id, menuindex, parent?}) is required.'
            );
        }

        $results = array();
        foreach ($items as $item) {
            if (empty($item['id']) || !isset($item['menuindex'])) {
                $results[] = array(
                    'id' => isset($item['id']) ? (int)$item['id'] : null,
                    'status' => 'skipped (need id + menuindex)',
                );
                continue;
            }

            $update = array(
                'type' => 'resource',
                'id' => (int)$item['id'],
                'menuindex' => (int)$item['menuindex'],
            );
            if (isset($item['parent'])) {
                $update['parent'] = (int)$item['parent'];
            }

            try {
                (new ElementUpdateTool())->execute($context, $update);
                $results[] = array(
                    'id' => (int)$item['id'],
                    'status' => 'ok',
                    'menuindex' => (int)$item['menuindex'],
                );
            } catch (\Exception $e) {
                $results[] = array(
                    'id' => (int)$item['id'],
                    'status' => 'error',
                    'error' => $e->getMessage(),
                );
            }
        }

        ElementMutationSupport::refreshCache($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'resource',
            array('count' => count($results))
        );
        return array('count' => count($results), 'results' => $results);
    }
}
