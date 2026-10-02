<?php
namespace ModxMcp\Tools;

class VirtualPageRouteGetTool implements ToolInterface
{
    public function name() { return 'virtualpage_get_route'; }
    public function group() { return 'virtualpage'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $route = VirtualPageSupport::resolveObject($context, 'vpRoute', $data);
        return VirtualPageSupport::normalizeRoute($context, $route);
    }
}
