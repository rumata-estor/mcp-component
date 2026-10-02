<?php
namespace ModxMcp\Tools;

class VirtualPageHandlerGetTool implements ToolInterface
{
    public function name() { return 'virtualpage_get_handler'; }
    public function group() { return 'virtualpage'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $handler = VirtualPageSupport::resolveObject($context, 'vpHandler', $data);
        return VirtualPageSupport::normalizeHandler($context, $handler, true);
    }
}
