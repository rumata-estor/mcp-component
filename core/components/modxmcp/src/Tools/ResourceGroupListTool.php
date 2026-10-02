<?php
namespace ModxMcp\Tools;

class ResourceGroupListTool implements ToolInterface
{
    public function name() { return 'list_resource_groups'; }
    public function group() { return 'access'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        return AclProcessorSupport::run(
            $context, 'security/resourcegroup/getlist', $data, true
        );
    }
}
