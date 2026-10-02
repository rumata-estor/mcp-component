<?php
namespace ModxMcp\Tools;

class VirtualPageHandlerListTool implements ToolInterface
{
    public function name() { return 'virtualpage_list_handlers'; }
    public function group() { return 'virtualpage'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        VirtualPageSupport::load($context);
        $query = $context->modx()->newQuery('vpHandler');
        VirtualPageSupport::applyFilters(
            $query, $data, array('id', 'name', 'type', 'entry', 'active'), ''
        );
        $query->sortby('rank', 'ASC');
        $query->sortby('id', 'ASC');
        $query->limit(
            VirtualPageSupport::limit($data),
            VirtualPageSupport::start($data)
        );
        $items = array();
        foreach ($context->modx()->getCollection('vpHandler', $query) as $handler) {
            $items[] = VirtualPageSupport::normalizeHandler(
                $context, $handler, !empty($data['include_routes'])
            );
        }
        return array('count' => count($items), 'handlers' => $items);
    }
}
