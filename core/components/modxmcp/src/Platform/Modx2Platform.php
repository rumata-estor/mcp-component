<?php
namespace ModxMcp\Platform;

class Modx2Platform extends AbstractPlatform
{
    protected $classes = array(
        'resource' => 'modResource',
        'chunk' => 'modChunk',
        'snippet' => 'modSnippet',
        'template' => 'modTemplate',
        'tv' => 'modTemplateVar',
        'plugin' => 'modPlugin',
        'category' => 'modCategory',
        'user' => 'modUser',
        'user_group' => 'modUserGroup',
        'context' => 'modContext',
        'system_setting' => 'modSystemSetting',
        'property_set' => 'modPropertySet',
        'element_property_set' => 'modElementPropertySet',
        'namespace' => 'modNamespace',
        'plugin_event' => 'modPluginEvent',
        'event' => 'modEvent',
        'template_var_resource' => 'modTemplateVarResource',
        'template_var_template' => 'modTemplateVarTemplate',
        'content_type' => 'modContentType',
        'media_source' => 'sources.modMediaSource',
        'file_media_source' => 'sources.modFileMediaSource',
        'media_source_element' => 'sources.modMediaSourceElement',
        'transport_package' => 'transport.modTransportPackage',
        'transport_provider' => 'transport.modTransportProvider',
    );

    public function key() { return 'modx2'; }
    public function majorVersion() { return 2; }

    public function supports($modx)
    {
        $version = method_exists($modx, 'getVersionData') ? $modx->getVersionData() : array();
        return isset($version['version']) && (int) $version['version'] === 2;
    }

    public function processorTarget($processor)
    {
        return $this->normalizeProcessor($processor);
    }

    public function runProcessor($modx, $processor, array $properties = array(), array $options = array())
    {
        return $modx->runProcessor($this->processorTarget($processor), $properties, $options);
    }
}
