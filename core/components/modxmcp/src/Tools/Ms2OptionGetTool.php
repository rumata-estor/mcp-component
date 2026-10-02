<?php
namespace ModxMcp\Tools;

class Ms2OptionGetTool implements ToolInterface
{
    public function name() { return 'ms2_get_option'; }
    public function group() { return 'minishop2'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        return MiniShop2Support::runProcessor(
            $context,
            'mgr/settings/option/get',
            array('id' => MiniShop2Support::positiveInt($data, 'id'))
        );
    }
}
