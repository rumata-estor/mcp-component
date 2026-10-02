<?php
namespace ModxMcp\Tools;

class ElementDuplicateTool implements ToolInterface
{
    public function name() { return 'duplicate_element'; }
    public function group() { return 'elements'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $type = isset($data['type']) ? (string)$data['type'] : '';
        if (!in_array($type, array('chunk', 'snippet', 'template', 'plugin', 'tv'), true)) {
            throw new \ModxMCPClientException(
                'duplicate_element: type must be one of chunk, snippet, template, plugin, tv.'
            );
        }
        if (empty($data['id'])) {
            throw new \ModxMCPClientException('duplicate_element: "id" is required.');
        }

        $props = array('id' => (int)$data['id']);
        if (!empty($data['name'])) { $props['name'] = (string)$data['name']; }

        $response = $context->platform()->runProcessor(
            $context->modx(),
            'element/' . $type . '/duplicate',
            $props
        );
        if (!$response || $response->isError()) {
            throw new \ModxMCPClientException(
                $response
                    ? ProcessorSupport::error($response)
                    : 'duplicate_element: no response.'
            );
        }
        ElementMutationSupport::refreshCache($context);
        AuditSupport::log(
            $context,
            $this->name(),
            $type,
            array('id' => (int)$data['id'])
        );
        return ProcessorSupport::normalize($response);
    }
}
