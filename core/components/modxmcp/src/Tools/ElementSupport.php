<?php
namespace ModxMcp\Tools;

class ElementSupport
{
    private static $types = array(
        'chunk', 'snippet', 'template', 'resource', 'tv', 'category', 'plugin'
    );

    public static function type(array $data)
    {
        $type = isset($data['type']) ? (string) $data['type'] : '';
        if (!in_array($type, self::$types, true)) {
            throw new \ModxMCPClientException('Invalid element type: ' . $type . '.');
        }
        return $type;
    }

    public static function nameField($type)
    {
        $map = array(
            'template' => 'templatename',
            'resource' => 'pagetitle',
            'category' => 'category',
        );
        return isset($map[$type]) ? $map[$type] : 'name';
    }

    public static function processorBase($type)
    {
        $map = array(
            'chunk' => 'element/chunk/',
            'snippet' => 'element/snippet/',
            'template' => 'element/template/',
            'resource' => 'resource/',
            'tv' => 'element/tv/',
            'category' => 'element/category/',
            'plugin' => 'element/plugin/',
        );
        return $map[$type];
    }

    public static function resolveId($context, $type, array $data)
    {
        if (!empty($data['id'])) { return (int) $data['id']; }
        if (empty($data['name'])) { return 0; }

        $modx = $context->modx();
        $class = $context->platform()->className($type);
        $name = (string) $data['name'];

        if ($type === 'resource') {
            foreach (array('alias', 'uri', 'pagetitle') as $field) {
                $obj = $modx->getObject($class, array($field => $name));
                if ($obj) { return (int) $obj->get('id'); }
            }
            return 0;
        }

        $obj = $modx->getObject($class, array(self::nameField($type) => $name));
        return $obj ? (int) $obj->get('id') : 0;
    }

    public static function tvTemplates($context, $tvId)
    {
        $links = $context->modx()->getCollection(
            $context->platform()->className('template_var_template'),
            array('tmplvarid' => (int) $tvId)
        );
        $result = array();
        foreach ($links as $link) { $result[] = $link->get('templateid'); }
        return $result;
    }

    public static function pluginEvents($context, $pluginId)
    {
        $links = $context->modx()->getCollection(
            $context->platform()->className('plugin_event'),
            array('pluginid' => (int) $pluginId)
        );
        $result = array();
        foreach ($links as $link) { $result[] = $link->get('event'); }
        return $result;
    }

    public static function processorError($response)
    {
        $error = $response->getMessage();
        if ($response->hasFieldErrors()) {
            foreach ($response->getFieldErrors() as $fieldError) {
                $error .= " | Field '" . $fieldError->field . "': " . $fieldError->message;
            }
        }
        return $error ? $error : 'Unknown error.';
    }
}
