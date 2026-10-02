<?php
namespace ModxMcp\Tools;

class Ms2LinkTypeGetTool implements ToolInterface
{
    public function name() { return 'ms2_get_link_type'; }
    public function group() { return 'minishop2'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        return MiniShop2Support::runProcessor(
            $context,
            'mgr/settings/link/get',
            MiniShop2Support::cleanPayload($data)
        );
    }
}
