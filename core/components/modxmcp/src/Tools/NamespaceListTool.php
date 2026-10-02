<?php
namespace ModxMcp\Tools;

class NamespaceListTool implements ToolInterface
{
    public function name() { return 'list_namespaces'; }
    public function group() { return 'namespaces'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        return ProcessorSupport::run(
            $context, 'workspace/namespace/getlist', $data, true,
            array('core:default', 'core:workspaces')
        );
    }
}
