<?php
namespace ModxMcp\Tools;

class RefreshUrisTool implements ToolInterface
{
    public function name() { return 'refresh_uris'; }
    public function group() { return 'ops'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $response = $context->platform()->runProcessor(
            $context->modx(),
            'system/refreshuris',
            array()
        );
        if (!$response || $response->isError()) {
            throw new \ModxMCPClientException(
                $response ? ProcessorSupport::error($response) : 'refresh_uris: no response.'
            );
        }
        ElementMutationSupport::refreshCache($context);
        AuditSupport::log($context, $this->name(), 'system', array());
        return array('refreshed' => true);
    }
}
