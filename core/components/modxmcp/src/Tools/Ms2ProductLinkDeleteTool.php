<?php
namespace ModxMcp\Tools;

class Ms2ProductLinkDeleteTool implements ToolInterface
{
    public function name() { return 'ms2_delete_product_link'; }
    public function group() { return 'minishop2'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $payload = MiniShop2Support::cleanPayload($data);
        $result = MiniShop2Support::runProcessor(
            $context,
            'mgr/product/productlink/remove',
            $payload
        );
        MiniShop2MutationSupport::auditProcessor(
            $context,
            $this->name(),
            $payload,
            array('id', 'link', 'master', 'slave', 'name', 'type')
        );
        return $result;
    }
}
