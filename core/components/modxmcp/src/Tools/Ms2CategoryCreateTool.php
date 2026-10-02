<?php
namespace ModxMcp\Tools;

class Ms2CategoryCreateTool implements ToolInterface
{
    public function name() { return 'ms2_create_category'; }
    public function group() { return 'minishop2'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        return MiniShop2MutationSupport::category($context, 'create', $data);
    }
}
