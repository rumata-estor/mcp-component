<?php
namespace ModxMcp\Tools;

class PackageSearchTool implements ToolInterface
{
    public function name() { return 'search_packages'; }
    public function group() { return 'package_management'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $providerId = isset($data['provider'])
            ? (int)$data['provider']
            : PackageSupport::defaultProviderId($context);
        if (!$providerId) {
            throw new \ModxMCPClientException('search_packages: no transport provider configured.');
        }
        $params = array(
            'provider' => $providerId,
            'query' => isset($data['query']) ? (string)$data['query'] : '',
            'limit' => isset($data['limit']) ? (int)$data['limit'] : 20,
            'start' => isset($data['start']) ? (int)$data['start'] : 0,
        );
        $response = $context->platform()->runProcessor(
            $context->modx(), 'workspace/packages/rest/getlist', $params
        );
        if (!$response || $response->isError()) {
            throw new \ModxMCPClientException(
                $response ? ProcessorSupport::error($response)
                    : 'search_packages: no response (provider unreachable?).'
            );
        }
        $result = PackageSupport::listResult($response);
        $result = array('provider' => $providerId) + $result;
        return $result;
    }
}
