<?php
namespace ModxMcp\Tools;

class PropertySetMutationSupport
{
    public static function elementClass($context, array $data)
    {
        if (!empty($data['element_class'])) {
            return ObjectSupport::normalizeCoreClass(
                $context->platform(),
                $data['element_class']
            );
        }
        $type = isset($data['element_type'])
            ? (string)$data['element_type']
            : '';
        if (!in_array(
            $type,
            array('snippet', 'chunk', 'template', 'plugin', 'tv'),
            true
        )) {
            throw new \ModxMCPClientException(
                'property set: element_class or a valid element_type '
                . '(snippet/chunk/template/plugin/tv) is required.'
            );
        }
        return $context->platform()->className($type);
    }

    public static function refresh($context)
    {
        $manager = $context->modx()->getCacheManager();
        if ($manager) { $manager->refresh(); }
    }
}
