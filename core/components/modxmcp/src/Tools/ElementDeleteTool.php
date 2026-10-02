<?php
namespace ModxMcp\Tools;

class ElementDeleteTool implements ToolInterface
{
    public function name() { return 'delete_element'; }
    public function group() { return 'elements'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $type = ElementSupport::type($data);
        ElementMutationSupport::loadLexicons($context);
        $prepared = ElementMutationSupport::prepare($context, $type, $data, $this->name());

        if (empty($prepared['id'])) {
            throw new \ModxMCPClientException(
                $type . ' not found by name or ID is missing.'
            );
        }
        $id = (int)$prepared['id'];

        if (!empty($prepared['dry_run'])) {
            return ElementMutationSupport::previewDelete($context, $type, $id);
        }

        $action = $type === 'resource' ? 'delete' : 'remove';
        $response = $context->platform()->runProcessor(
            $context->modx(),
            ElementSupport::processorBase($type) . $action,
            array('id' => $id)
        );
        if (!$response) {
            throw new \ModxMCPClientException('Delete failed: no processor response.');
        }
        if ($response->isError()) {
            throw new \ModxMCPClientException(
                'Delete failed: ' . ProcessorSupport::error($response)
            );
        }

        ElementMutationSupport::refreshCache($context);
        AuditSupport::log(
            $context,
            'delete_element',
            $type,
            array('id' => $id)
        );
        return 'Successfully deleted ' . $type . ' (ID: ' . $id . ').';
    }
}
