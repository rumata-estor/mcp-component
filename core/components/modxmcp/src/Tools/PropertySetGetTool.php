<?php
namespace ModxMcp\Tools;

class PropertySetGetTool implements ToolInterface
{
    public function name() { return 'get_property_set'; }
    public function group() { return 'property_sets'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        if (empty($data['id'])) {
            throw new \ModxMCPClientException('get_property_set: id is required.');
        }
        $id = (int)$data['id'];
        $propertySet = $context->modx()->getObject(
            $context->platform()->className('property_set'),
            $id
        );
        if (!$propertySet) {
            throw new \ModxMCPClientException('get_property_set: property set ' . $id . ' not found.');
        }
        return $propertySet->toArray();
    }
}
