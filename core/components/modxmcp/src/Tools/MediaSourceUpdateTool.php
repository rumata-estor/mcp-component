<?php
namespace ModxMcp\Tools;

class MediaSourceUpdateTool implements ToolInterface
{
    public function name() { return 'update_media_source'; }
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

        if (empty($props['id'])) {
            throw new \ModxMCPClientException('update_media_source: id is required.');
        }

        $response = $context->platform()->runProcessor(
            $context->modx(),
            'source/update',
            $props
        );
        if (!$response) {
            throw new \ModxMCPClientException('media source: no response.');
        }
        if ($response->isError()) {
            throw new \ModxMCPClientException(ProcessorSupport::error($response));
        }

        $id = (int)$props['id'];
        if ($userProps !== null) {
            MediaSourceMutationSupport::mergeProperties($context, $id, $userProps);
        }

        MediaSourceMutationSupport::refresh($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'source',
            array(
                'id' => $id,
                'name' => isset($props['name']) ? $props['name'] : null,
            )
        );
        return array(
            'id' => $id,
            'properties_set' => $userProps !== null
                ? array_keys($userProps)
                : array(),
        );
    }
}
