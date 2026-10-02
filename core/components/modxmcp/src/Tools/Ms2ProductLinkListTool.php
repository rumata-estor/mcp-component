<?php
namespace ModxMcp\Tools;

class Ms2ProductLinkListTool implements ToolInterface
{
    public function name() { return 'ms2_list_product_links'; }
    public function group() { return 'minishop2'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $payload = MiniShop2Support::cleanPayload($data);
        if (!isset($payload['limit'])) { $payload['limit'] = 0; }
        return MiniShop2Support::runProcessor(
            $context, 'mgr/product/productlink/getlist', $payload
        );
    }
}
