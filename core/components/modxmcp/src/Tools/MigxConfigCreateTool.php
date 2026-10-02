<?php
namespace ModxMcp\Tools;

class MigxConfigCreateTool implements ToolInterface
{
    public function name() { return 'migx_create_config'; }
    public function group() { return 'migx'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        MigxSupport::load($context);
        if (empty($data['name'])) {
            throw new \ModxMCPClientException('migx_create_config: name is required.');
        }

        $config = $context->modx()->newObject('migxConfig');
        foreach (MigxMutationSupport::fields() as $field) {
            if (array_key_exists($field, $data)) {
                $config->set($field, $data[$field]);
            }
        }
        if (!$config->save()) {
            throw new \ModxMCPClientException('migx save_config: save failed.');
        }

        MigxMutationSupport::refresh($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'migx',
            array(
                'id' => (int)$config->get('id'),
                'name' => $config->get('name'),
            )
        );
        return array(
            'id' => (int)$config->get('id'),
            'name' => $config->get('name'),
            'category' => $config->get('category'),
        );
    }
}
