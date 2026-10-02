<?php
namespace ModxMcp\Tools;

class PropertySetCreateTool implements ToolInterface
{
    public function name() { return 'create_property_set'; }
    public function group() { return 'property_sets'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        if (empty($data['name'])) {
            throw new \ModxMCPClientException('create_property_set: name is required.');
        }
        $class = $context->platform()->className('property_set');
        $propertySet = $context->modx()->newObject($class);
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
