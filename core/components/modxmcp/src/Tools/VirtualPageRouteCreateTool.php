<?php
namespace ModxMcp\Tools;

class VirtualPageRouteCreateTool implements ToolInterface
{
    public function name() { return 'virtualpage_create_route'; }
    public function group() { return 'virtualpage'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        VirtualPageSupport::load($context);
        $payload = VirtualPageSupport::prepareRoutePayload(
            $context,
            $data,
            false
        );
        VirtualPageSupport::assertUniqueRoute(
            $context,
            $payload['route'],
            $payload['metod']
        );
        if (!array_key_exists('rank', $payload)) {
            $payload['rank'] = $context->modx()->getCount('vpRoute');
        }

        $route = $context->modx()->newObject('vpRoute');
        $route->fromArray($payload, '', true, true);
        if (!$route->save()) {
            throw new \ModxMCPClientException(
                'Could not save VirtualPage route.'
            );
        }

        if ($event = $route->getOne('Event')) {
            VirtualPageSupport::ensurePluginEvent(
                $context,
                $event->get('name')
            );
        }
        VirtualPageSupport::clearCache($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'virtualpage_route',
            array('id' => $route->get('id'), 'route' => $route->get('route'))
        );
        return VirtualPageSupport::normalizeRoute($context, $route);
    }
}
