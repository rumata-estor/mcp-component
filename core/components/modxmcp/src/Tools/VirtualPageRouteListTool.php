<?php
namespace ModxMcp\Tools;

class VirtualPageRouteListTool implements ToolInterface
{
    public function name() { return 'virtualpage_list_routes'; }
    public function group() { return 'virtualpage'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        VirtualPageSupport::load($context);
        $modx = $context->modx();
        $query = $modx->newQuery('vpRoute');
        $query->leftJoin('vpEvent', 'Event', 'Event.id = vpRoute.event');
        $query->leftJoin('vpHandler', 'Handler', 'Handler.id = vpRoute.handler');
        $query->select($modx->getSelectColumns('vpRoute', 'vpRoute'));
        $query->select(array(
            'event_name' => 'Event.name',
            'handler_name' => 'Handler.name',
        ));
        if (!empty($data['event_name'])) {
            $query->where(array('Event.name' => (string)$data['event_name']));
        }
        if (!empty($data['handler_name'])) {
            $query->where(array('Handler.name' => (string)$data['handler_name']));
        }
        VirtualPageSupport::applyFilters(
            $query,
            $data,
            array('id', 'route', 'handler', 'event', 'active'),
            'vpRoute'
        );
        if (!empty($data['method'])) {
            $query->where(array(
                'vpRoute.metod:LIKE' => '%'
                    . VirtualPageSupport::normalizeMethod($data['method']) . '%'
            ));
        }
        $query->sortby('vpRoute.rank', 'ASC');
        $query->sortby('vpRoute.id', 'ASC');
        $query->limit(
            VirtualPageSupport::limit($data),
            VirtualPageSupport::start($data)
        );

        $items = array();
        if ($query->prepare() && $query->stmt->execute()) {
            foreach ($query->stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $items[] = VirtualPageSupport::normalizeRouteArray($row);
            }
        }
        return array('count' => count($items), 'routes' => $items);
    }
}
