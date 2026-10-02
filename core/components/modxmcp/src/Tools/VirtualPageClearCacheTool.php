<?php
namespace ModxMcp\Tools;

class VirtualPageClearCacheTool implements ToolInterface
{
    public function name() { return 'virtualpage_clear_cache'; }
    public function group() { return 'virtualpage'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        return VirtualPageSupport::clearCache($context);
    }
}
