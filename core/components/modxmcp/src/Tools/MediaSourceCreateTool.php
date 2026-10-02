<?php
namespace ModxMcp\Tools;

class MediaSourceCreateTool implements ToolInterface
{
    public function name() { return 'create_media_source'; }
    public function group() { return 'media'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $props = $data;
        unset($props['action'], $props['elementType']);
        $userProps = isset($props['properties']) && is_array($props['properties'])
            ? $props['properties']
            : null;
        unset($props['properties']);

        if (empty($props['name'])) {
            throw new \ModxMCPClientException('create_media_source: name is required.');
        }
        if (empty($props['class_key'])) {
            $props['class_key'] = $context->platform()->className('file_media_source');
        }

        $response = $context->platform()->runProcessor(
            $context->modx(),
            'source/create',
            $props
        );
        if (!$response) {
            throw new \ModxMCPClientException('media source: no response.');
        }
        if ($response->isError()) {
            throw new \ModxMCPClientException(ProcessorSupport::error($response));
        }

        $object = $response->getObject();
        $id = is_array($object) && isset($object['id'])
            ? (int)$object['id']
            : 0;
        if ($userProps !== null && $id > 0) {
            MediaSourceMutationSupport::mergeProperties($context, $id, $userProps);
        }

        MediaSourceMutationSupport::refresh($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'source',
            array('id' => $id, 'name' => $props['name'])
        );
        return array(
            'id' => $id,
            'properties_set' => $userProps !== null
                ? array_keys($userProps)
                : array(),
        );
    }
}
