<?php
namespace ModxMcp\Tools;

class ElementUpdateTool implements ToolInterface
{
    public function name() { return 'update_element'; }
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

        $current = $context->platform()->runProcessor(
            $context->modx(),
            ElementSupport::processorBase($type) . 'get',
            array('id' => $prepared['id'])
        );
        if (!$current) {
            throw new \ModxMCPClientException('No processor response.');
        }
        if ($current->isError()) {
            throw new \ModxMCPClientException(ProcessorSupport::error($current));
        }

        $updateData = array_merge($current->getObject(), $prepared);
        unset(
            $updateData['events'],
            $updateData['templates'],
            $updateData['input_properties'],
            $updateData['media_source'],
            $updateData['field_type']
        );
        $updateData = ElementMutationSupport::filterData($type, $updateData);

        return ElementMutationSupport::transaction(
            $context,
            function () use ($context, $type, $prepared, $updateData) {
                $response = $context->platform()->runProcessor(
                    $context->modx(),
                    ElementSupport::processorBase($type) . 'update',
                    $updateData
                );
                if (!$response) {
                    throw new \ModxMCPClientException('Update failed: no processor response.');
                }
                if ($response->isError()) {
                    throw new \ModxMCPClientException(
                        'Update failed: ' . ProcessorSupport::error($response)
                    );
                }

                $id = (int)$prepared['id'];
                if ($type === 'tv') {
                    ElementMutationSupport::handleTvRelations(
                        $context,
                        $id,
                        $prepared
                    );
                }
                if ($type === 'plugin') {
                    ElementMutationSupport::handlePluginEvents(
                        $context,
                        $id,
                        $prepared
                    );
                }
                if (ElementMutationSupport::shouldAutoStatic($context, $type)) {
                    ElementMutationSupport::makeStatic($context, $type, $id);
                }

                ElementMutationSupport::refreshCache($context);
                AuditSupport::log(
                    $context,
                    'update_element',
                    $type,
                    array('id' => $id)
                );
                return 'Successfully updated ' . $type . ' (ID: ' . $id . ').';
            }
        );
    }
}
