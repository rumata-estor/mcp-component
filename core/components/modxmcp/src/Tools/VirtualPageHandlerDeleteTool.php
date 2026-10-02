<?php
namespace ModxMcp\Tools;

class VirtualPageHandlerDeleteTool implements ToolInterface
{
    public function name() { return 'virtualpage_delete_handler'; }
    public function group() { return 'virtualpage'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $handler = VirtualPageSupport::resolveObject(
            $context,
            'vpHandler',
            $data
        );
        $id = (int)$handler->get('id');
        $name = $handler->get('name');
        if (!$handler->remove()) {
            throw new \ModxMCPClientException(
                'Could not delete vpHandler ' . $id . '.'
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
