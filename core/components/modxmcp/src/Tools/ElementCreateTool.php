<?php
namespace ModxMcp\Tools;

class ElementCreateTool implements ToolInterface
{
    public function name() { return 'create_element'; }
    public function group() { return 'elements'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $type = ElementSupport::type($data);
        ElementMutationSupport::loadLexicons($context);
        $prepared = ElementMutationSupport::prepare($context, $type, $data, $this->name());

        $createData = $prepared;
        unset(
            $createData['events'],
            $createData['templates'],
            $createData['input_properties'],
            $createData['media_source'],
            $createData['field_type']
        );
        $createData = ElementMutationSupport::filterData($type, $createData);

        return ElementMutationSupport::transaction(
            $context,
            function () use ($context, $type, $prepared, $createData) {
                $response = $context->platform()->runProcessor(
                    $context->modx(),
                    ElementSupport::processorBase($type) . 'create',
                    $createData
                );
                if (!$response) {
                    throw new \ModxMCPClientException('Create failed: no processor response.');
                }
                if ($response->isError()) {
                    throw new \ModxMCPClientException(
                        'Create failed: ' . ProcessorSupport::error($response)
                    );
                }

                $object = $response->getObject();
                $id = isset($object['id']) ? (int)$object['id'] : 0;

                if ($type === 'tv' && $id > 0) {
                    ElementMutationSupport::handleTvRelations(
                        $context,
                        $id,
                        $prepared
                    );
                }
                if ($type === 'plugin' && $id > 0) {
                    ElementMutationSupport::handlePluginEvents(
                        $context,
                        $id,
                        $prepared
                    );
                }
                if ($id > 0 && ElementMutationSupport::shouldAutoStatic($context, $type)) {
                    ElementMutationSupport::makeStatic($context, $type, $id);
                }

                ElementMutationSupport::refreshCache($context);
                AuditSupport::log(
                    $context,
                    'create_element',
                    $type,
                    array('id' => $id > 0 ? $id : null)
                );
                return $object;
            }
        );
    }
}
