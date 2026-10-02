<?php
namespace ModxMcp\Tools;

class PackageUninstallTool implements ToolInterface
{
    public function name() { return 'uninstall_package'; }
    public function group() { return 'package_management'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $signature = isset($data['signature'])
            ? (string)$data['signature']
            : '';
        if ($signature === '') {
            throw new \ModxMCPClientException(
                'uninstall_package: "signature" is required '
                . '(e.g. migx-2.13.0-pl).'
            );
        }

        $response = $context->platform()->runProcessor(
            $context->modx(),
            'workspace/packages/uninstall',
            array('signature' => $signature)
        );
        if (!$response || $response->isError()) {
            throw new \ModxMCPClientException(
                $response
                    ? ProcessorSupport::error($response)
                    : 'uninstall_package: no response.'
            );
        }

        $manager = $context->modx()->getCacheManager();
        if ($manager) { $manager->refresh(); }
        AuditSupport::log(
            $context,
            $this->name(),
            'system',
            array('signature' => $signature)
        );
        return ProcessorSupport::normalize($response);
    }
}
