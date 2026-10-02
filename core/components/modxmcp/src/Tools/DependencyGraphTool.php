<?php
namespace ModxMcp\Tools;

class DependencyGraphTool implements ToolInterface
{
    public function name() { return 'dependency_graph'; }
    public function group() { return 'elements'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        return DependencyGraphService::build($context, $data);
    }
}
