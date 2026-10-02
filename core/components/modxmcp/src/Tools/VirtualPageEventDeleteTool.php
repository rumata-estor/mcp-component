<?php
namespace ModxMcp\Tools;

class VirtualPageEventDeleteTool implements ToolInterface
{
    public function name() { return 'virtualpage_delete_event'; }
    public function group() { return 'virtualpage'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $event = VirtualPageSupport::resolveObject($context, 'vpEvent', $data);
        $id = (int)$event->get('id');
        $name = $event->get('name');
        if (!$event->remove()) {
            throw new \ModxMCPClientException(
                'Could not delete vpEvent ' . $id . '.'
            );
        }
        VirtualPageSupport::clearCache($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'virtualpage',
            array('id' => $id, 'name' => $name)
        );
        return array('deleted' => true, 'id' => $id, 'name' => $name);
    }
}
