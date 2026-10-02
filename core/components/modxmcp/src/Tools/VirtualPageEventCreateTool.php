<?php
namespace ModxMcp\Tools;

class VirtualPageEventCreateTool implements ToolInterface
{
    public function name() { return 'virtualpage_create_event'; }
    public function group() { return 'virtualpage'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        VirtualPageSupport::load($context);
        $payload = VirtualPageSupport::preparePayload(
            $data,
            array('name', 'description', 'rank', 'active')
        );
        if (empty($payload['name'])) {
            throw new \ModxMCPClientException(
                'name is required for VirtualPage event creation.'
            );
        }
        if ($context->modx()->getObject('vpEvent', array('name' => $payload['name']))) {
            throw new \ModxMCPClientException(
                'VirtualPage event already exists: ' . $payload['name'] . '.'
            );
        }
        if (!array_key_exists('active', $payload)) { $payload['active'] = 1; }
        if (!array_key_exists('rank', $payload)) {
            $payload['rank'] = $context->modx()->getCount('vpEvent');
        }

        $event = $context->modx()->newObject('vpEvent');
        $event->fromArray($payload, '', true, true);
        if (!$event->save()) {
            throw new \ModxMCPClientException('Could not save VirtualPage event.');
        }

        VirtualPageSupport::clearCache($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'virtualpage_event',
            array('id' => $event->get('id'), 'name' => $event->get('name'))
        );
        return VirtualPageSupport::normalizeEvent($context, $event, true);
    }
}
