<?php
namespace ModxMcp\Tools;

class PropertySetAssignTool implements ToolInterface
{
    public function name() { return 'assign_property_set'; }
    public function group() { return 'property_sets'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        if (empty($data['element']) || empty($data['property_set'])) {
            throw new \ModxMCPClientException(
                'assign_property_set: element and property_set are required.'
            );
        }
        $class = PropertySetMutationSupport::elementClass($context, $data);
        $criteria = array(
            'element' => (int)$data['element'],
            'element_class' => $class,
            'property_set' => (int)$data['property_set'],
        );
        $linkClass = $context->platform()->className('element_property_set');
        if ($context->modx()->getObject($linkClass, $criteria)) {
            return array(
                'status' => 'already_assigned',
                'element' => (int)$data['element'],
                'property_set' => (int)$data['property_set'],
            );
        }

        $link = $context->modx()->newObject($linkClass);
        $link->fromArray($criteria, '', true, true);
        if (!$link->save()) {
            throw new \ModxMCPClientException('assign_property_set: save failed.');
        }
        PropertySetMutationSupport::refresh($context);
        AuditSupport::log($context, $this->name(), 'element', $criteria);
        return array_merge(array('status' => 'assigned'), $criteria);
    }
}
