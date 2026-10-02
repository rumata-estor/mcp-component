<?php
namespace ModxMcp\Tools;

class PropertySetDeleteTool implements ToolInterface
{
    public function name() { return 'delete_property_set'; }
    public function group() { return 'property_sets'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        if (empty($data['id'])) {
            throw new \ModxMCPClientException('delete_property_set: id is required.');
        }
        $class = $context->platform()->className('property_set');
        $propertySet = $context->modx()->getObject($class, (int)$data['id']);
        if (!$propertySet) {
            throw new \ModxMCPClientException(
                'delete_property_set: property set ' . (int)$data['id'] . ' not found.'
            );
        }
        $name = $propertySet->get('name');
        if (!$propertySet->remove()) {
            throw new \ModxMCPClientException('delete_property_set: remove failed.');
        }
        PropertySetMutationSupport::refresh($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'element',
            array('id' => (int)$data['id'], 'name' => $name)
        );
        return array(
            'deleted' => true,
            'id' => (int)$data['id'],
            'name' => $name,
        );
    }
}
