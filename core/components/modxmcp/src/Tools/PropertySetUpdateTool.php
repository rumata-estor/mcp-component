<?php
namespace ModxMcp\Tools;

class PropertySetUpdateTool implements ToolInterface
{
    public function name() { return 'update_property_set'; }
    public function group() { return 'property_sets'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        if (empty($data['id'])) {
            throw new \ModxMCPClientException('update_property_set: id is required.');
        }
        $class = $context->platform()->className('property_set');
        $propertySet = $context->modx()->getObject($class, (int)$data['id']);
        if (!$propertySet) {
            throw new \ModxMCPClientException(
                'update_property_set: property set ' . (int)$data['id'] . ' not found.'
            );
        }
        foreach (array('name', 'description', 'category', 'properties') as $field) {
            if (array_key_exists($field, $data)) {
                $propertySet->set($field, $data[$field]);
            }
        }
        if (!$propertySet->save()) {
            throw new \ModxMCPClientException('save_property_set: save failed.');
        }
        PropertySetMutationSupport::refresh($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'element',
            array(
                'id' => (int)$propertySet->get('id'),
                'name' => $propertySet->get('name'),
            )
        );
        return array(
            'id' => (int)$propertySet->get('id'),
            'name' => $propertySet->get('name'),
        );
    }
}
