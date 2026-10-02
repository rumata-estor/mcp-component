<?php
namespace ModxMcp\Tools;

class ElementGetTool implements ToolInterface
{
    public function name() { return 'get_element'; }
    public function group() { return 'elements'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $type = ElementSupport::type($data);
        $id = ElementSupport::resolveId($context, $type, $data);
        if (!$id) {
            throw new \ModxMCPClientException($type . ' not found by name or ID is missing.');
        }

        $response = $context->platform()->runProcessor(
            $context->modx(),
            ElementSupport::processorBase($type) . 'get',
            array('id' => $id)
        );
        if ($response->isError()) {
            throw new \ModxMCPClientException(ElementSupport::processorError($response));
        }

        $result = $response->getObject();
        if ($type === 'tv') {
            $result['templates'] = ElementSupport::tvTemplates($context, $id);
            $result['field_type'] = $result['type'];
        }
        if ($type === 'plugin') {
            $result['events'] = ElementSupport::pluginEvents($context, $id);
        }
        return $result;
    }
}
