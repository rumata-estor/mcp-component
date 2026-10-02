<?php
namespace ModxMcp\Tools;

class ListActionsTool implements ToolInterface
{
    public function name() { return 'list_actions'; }
    public function group() { return 'ops'; }
    public function isMutation() { return false; }
    public function supports($context)
    {
        return $context && $context->legacy()
            && method_exists($context->legacy(), 'getSupportedActions');
    }

    public function execute($context, array $data)
    {
        return $context->legacy()->getSupportedActions();
    }
}
