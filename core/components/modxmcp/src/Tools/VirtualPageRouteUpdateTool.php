<?php
namespace ModxMcp\Tools;

class VirtualPageRouteUpdateTool implements ToolInterface
{
    public function name() { return 'virtualpage_update_route'; }
    public function group() { return 'virtualpage'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $route = VirtualPageSupport::resolveObject(
            $context,
            'vpRoute',
            $data
        );
        $payload = VirtualPageSupport::prepareRoutePayload(
            $context,
            $data,
            true
        );
        if (empty($payload)) {
            throw new \ModxMCPClientException(
                'No VirtualPage route fields to update.'
            );
        }

        $routePath = array_key_exists('route', $payload)
            ? $payload['route']
            : $route->get('route');
        $method = array_key_exists('metod', $payload)
            ? $payload['metod']
            : $route->get('metod');

        VirtualPageSupport::assertUniqueRoute(
            $context,
            $routePath,
            $method,
            (int)$route->get('id')
        );

        $route->fromArray($payload, '', true, true);
        if (!$route->save()) {
            throw new \ModxMCPClientException(
                'Could not update VirtualPage route.'
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
            array('id' => $route->get('id'))
        );
        return VirtualPageSupport::normalizeRoute($context, $route);
    }
}
