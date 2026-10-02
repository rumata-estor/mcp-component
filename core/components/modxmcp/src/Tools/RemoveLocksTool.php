<?php
namespace ModxMcp\Tools;

class RemoveLocksTool implements ToolInterface
{
    public function name() { return 'remove_locks'; }
    public function group() { return 'ops'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $response = $context->platform()->runProcessor(
            $context->modx(),
            'system/remove_locks',
            array()
        );
        if (!$response || $response->isError()) {
            throw new \ModxMCPClientException(
                $response ? ProcessorSupport::error($response) : 'remove_locks: no response.'
            );
        }
        AuditSupport::log($context, $this->name(), 'system', array());
        return ProcessorSupport::normalize($response);
    }
}
