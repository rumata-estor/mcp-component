<?php
namespace ModxMcp\Tools;

class ObjectSupport
{
    public static function normalizeCoreClass($platform, $class)
    {
        $class = trim((string)$class);
        if ($class === '' || strpos($class, 'MODX\\Revolution\\') === 0 || strpos($class, 'xPDO\\') === 0) {
            return $class;
        }
        $map = array(
            'modresource' => 'resource', 'modchunk' => 'chunk', 'modsnippet' => 'snippet',
            'modtemplate' => 'template', 'modtemplatevar' => 'tv', 'modplugin' => 'plugin',
            'modcategory' => 'category', 'moduser' => 'user', 'modusergroup' => 'user_group',
            'modcontext' => 'context', 'modsystemsetting' => 'system_setting',
            'modpropertyset' => 'property_set', 'modelementpropertyset' => 'element_property_set',
            'modnamespace' => 'namespace', 'sources.modmediasource' => 'media_source',
            'sources.modfilemediasource' => 'file_media_source',
            'sources.modmediasourceelement' => 'media_source_element',
            'transport.modtransportpackage' => 'transport_package',
            'transport.modtransportprovider' => 'transport_provider',
        );
        $key = strtolower($class);
        return isset($map[$key]) ? $platform->className($map[$key]) : $class;
    }
}
