<?php
namespace ModxMcp\Tools;

class ContextListTool implements ToolInterface
{
    public function name() { return 'list_contexts'; }
    public function group() { return 'contexts'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        return ProcessorSupport::run(
            $context, 'context/getlist', $data, true,
            array('core:default', 'core:context', 'core:setting')
        );
    }
}
