<?php
namespace ModxMcp\Tools;

class DescribeObjectTool implements ToolInterface
{
    public function name() { return 'describe_object'; }
    public function group() { return 'ops'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $class = isset($data['class']) ? trim((string)$data['class']) : '';
        if ($class === '') {
            throw new \ModxMCPClientException('describe_object: "class" is required (e.g. modResource, or alias "resource").');
        }
        $aliases = array(
            'chunk' => 'chunk', 'snippet' => 'snippet', 'template' => 'template',
            'plugin' => 'plugin', 'tv' => 'tv', 'resource' => 'resource',
            'category' => 'category', 'user' => 'user', 'usergroup' => 'user_group',
            'context' => 'context', 'setting' => 'system_setting',
        );
        $key = strtolower($class);
        if (isset($aliases[$key])) {
            $class = $context->platform()->className($aliases[$key]);
        } else {
            $class = ObjectSupport::normalizeCoreClass($context->platform(), $class);
        }
        $modx = $context->modx();
        $meta = $modx->getFieldMeta($class);
        if (empty($meta)) {
            if (!$modx->loadClass($class)) {
                throw new \ModxMCPClientException("describe_object: unknown class '{$class}'. For add-on classes load the package first (e.g. via a known action).");
            }
            $meta = $modx->getFieldMeta($class);
        }
        if (empty($meta)) {
            throw new \ModxMCPClientException("describe_object: no field metadata for '{$class}'.");
        }
        $fields = array();
        foreach ($meta as $name => $def) {
            $fields[] = array(
                'field' => $name,
                'phptype' => isset($def['phptype']) ? $def['phptype'] : null,
                'dbtype' => isset($def['dbtype']) ? $def['dbtype'] : null,
                'null' => isset($def['null']) ? (bool)$def['null'] : null,
                'default' => array_key_exists('default', $def) ? $def['default'] : null,
            );
        }
        return array('class' => $class, 'primary_key' => $modx->getPK($class), 'fields' => $fields);
    }
}
