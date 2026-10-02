<?php
namespace ModxMcp\Tools;

class VirtualPageEventListTool implements ToolInterface
{
    public function name() { return 'virtualpage_list_events'; }
    public function group() { return 'virtualpage'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        VirtualPageSupport::load($context);
        $query = $context->modx()->newQuery('vpEvent');
        VirtualPageSupport::applyFilters(
            $query, $data, array('id', 'name', 'active'), ''
        );
        $query->sortby('rank', 'ASC');
        $query->sortby('id', 'ASC');
        $query->limit(
            VirtualPageSupport::limit($data),
            VirtualPageSupport::start($data)
        );
        $items = array();
        foreach ($context->modx()->getCollection('vpEvent', $query) as $event) {
            $items[] = VirtualPageSupport::normalizeEvent(
                $context, $event, !empty($data['include_routes'])
            );
        }
        return array('count' => count($items), 'events' => $items);
    }
}
