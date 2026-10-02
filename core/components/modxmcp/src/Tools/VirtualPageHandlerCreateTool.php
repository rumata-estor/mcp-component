<?php
namespace ModxMcp\Tools;

class VirtualPageHandlerCreateTool implements ToolInterface
{
    public function name() { return 'virtualpage_create_handler'; }
    public function group() { return 'virtualpage'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        VirtualPageSupport::load($context);
        $payload = VirtualPageSupport::preparePayload(
            $data,
            array(
                'name', 'type', 'entry', 'content', 'description',
                'cache', 'rank', 'active',
            )
        );
        if (empty($payload['name'])) {
            throw new \ModxMCPClientException(
                'name is required for VirtualPage handler creation.'
            );
        }
        if ($context->modx()->getObject('vpHandler', array('name' => $payload['name']))) {
            throw new \ModxMCPClientException(
                'VirtualPage handler already exists: ' . $payload['name'] . '.'
            );
        }

        if (!array_key_exists('type', $payload)) { $payload['type'] = 3; }
        $payload['type'] = VirtualPageSupport::normalizeHandlerType(
            $payload['type']
        );
        if (!array_key_exists('entry', $payload)) { $payload['entry'] = 0; }
        VirtualPageSupport::assertHandlerEntry(
            $context,
            $payload['type'],
            $payload['entry']
        );
        if (!array_key_exists('active', $payload)) { $payload['active'] = 1; }
        if (!array_key_exists('cache', $payload)) { $payload['cache'] = 0; }
        if (!array_key_exists('rank', $payload)) {
            $payload['rank'] = $context->modx()->getCount('vpHandler');
        }

        $handler = $context->modx()->newObject('vpHandler');
        $handler->fromArray($payload, '', true, true);
        if (!$handler->save()) {
            throw new \ModxMCPClientException(
                'Could not save VirtualPage handler.'
            );
        }

        VirtualPageSupport::clearCache($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'virtualpage_handler',
            array('id' => $handler->get('id'), 'name' => $handler->get('name'))
        );
        return VirtualPageSupport::normalizeHandler($context, $handler, true);
    }
}
