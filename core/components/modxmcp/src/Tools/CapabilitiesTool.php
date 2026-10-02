<?php
namespace ModxMcp\Tools;

class CapabilitiesTool implements ToolInterface
{
    public function name() { return 'get_capabilities'; }
    public function group() { return 'ops'; }
    public function isMutation() { return false; }

    public function supports($context)
    {
        return $context && $context->legacy() && method_exists($context->legacy(), 'getCapabilities');
    }

    public function execute($context, array $arguments)
    {
        return $context->legacy()->getCapabilities();
    }
}
