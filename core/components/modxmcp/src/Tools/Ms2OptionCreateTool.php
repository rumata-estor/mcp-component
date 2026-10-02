<?php
namespace ModxMcp\Tools;

class Ms2OptionCreateTool implements ToolInterface
{
    public function name() { return 'ms2_create_option'; }
    public function group() { return 'minishop2'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $payload = MiniShop2MutationSupport::optionPayload($data, false);
        $result = MiniShop2Support::runProcessor(
            $context,
            'mgr/settings/option/create',
            $payload
        );
        MiniShop2MutationSupport::auditProcessor(
            $context,
            $this->name(),
            $payload,
            array('key'),
            true
        );
        return $result;
    }
}
