<?php
namespace ModxMcp\Platform;

class Modx3Platform extends AbstractPlatform
{
    protected $classes = array(
        'resource' => 'MODX\\Revolution\\modResource',
        'chunk' => 'MODX\\Revolution\\modChunk',
        'snippet' => 'MODX\\Revolution\\modSnippet',
        'template' => 'MODX\\Revolution\\modTemplate',
        'tv' => 'MODX\\Revolution\\modTemplateVar',
        'plugin' => 'MODX\\Revolution\\modPlugin',
        'category' => 'MODX\\Revolution\\modCategory',
        'user' => 'MODX\\Revolution\\modUser',
        'user_group' => 'MODX\\Revolution\\modUserGroup',
        'context' => 'MODX\\Revolution\\modContext',
        'system_setting' => 'MODX\\Revolution\\modSystemSetting',
        'property_set' => 'MODX\\Revolution\\modPropertySet',
        'element_property_set' => 'MODX\\Revolution\\modElementPropertySet',
        'namespace' => 'MODX\\Revolution\\modNamespace',
        'plugin_event' => 'MODX\\Revolution\\modPluginEvent',
        'event' => 'MODX\\Revolution\\modEvent',
        'template_var_resource' => 'MODX\\Revolution\\modTemplateVarResource',
        'template_var_template' => 'MODX\\Revolution\\modTemplateVarTemplate',
        'content_type' => 'MODX\\Revolution\\modContentType',
        'media_source' => 'MODX\\Revolution\\Sources\\modMediaSource',
        'file_media_source' => 'MODX\\Revolution\\Sources\\modFileMediaSource',
        'media_source_element' => 'MODX\\Revolution\\Sources\\modMediaSourceElement',
        'transport_package' => 'MODX\\Revolution\\Transport\\modTransportPackage',
        'transport_provider' => 'MODX\\Revolution\\Transport\\modTransportProvider',
    );

    public function key() { return 'modx3'; }
    public function majorVersion() { return 3; }

    public function supports($modx)
    {
        $version = method_exists($modx, 'getVersionData') ? $modx->getVersionData() : array();
        return isset($version['version']) && (int) $version['version'] === 3;
    }

    public function processorTarget($processor)
    {
        $processor = ltrim((string) $processor, '\\');
        if (strpos($processor, 'MODX\\Revolution\\Processors\\') === 0) {
            return $processor;
        }

        $path = $this->normalizeProcessor($processor);
        if ($path === '') {
            throw new \InvalidArgumentException('Empty MODX core processor path.');
        }

        if (strpos($path, 'workspace/namespace/') === 0) {
            $path = 'workspace/package_namespace/' . substr($path, strlen('workspace/namespace/'));
        } elseif (strpos($path, 'element/tv/') === 0) {
            $path = 'element/template_var/' . substr($path, strlen('element/tv/'));
        }

        $nameMap = array(
            'package_namespace' => 'PackageNamespace',
            'template_var' => 'TemplateVar',
            'resourcegroup' => 'ResourceGroup',
            'usergroup' => 'UserGroup',
            'getlist' => 'GetList',
            'getnodes' => 'GetNodes',
            'getinfo' => 'GetInfo',
            'emptyrecyclebin' => 'EmptyRecycleBin',
            'refreshuris' => 'RefreshUris',
            'remove_locks' => 'RemoveLocks',
            'removeresource' => 'RemoveResource',
            'updateresourcesin' => 'UpdateResourcesIn',
        );

        $parts = explode('/', $path);
        foreach ($parts as &$part) {
            $key = strtolower($part);
            $part = isset($nameMap[$key]) ? $nameMap[$key] : ucfirst($part);
        }
        unset($part);

        $class = 'MODX\\Revolution\\Processors\\' . implode('\\', $parts);
        if (!class_exists($class)) {
            throw new \RuntimeException('MODX 3 core processor class not found: ' . $class . ' (legacy path: ' . $processor . ').');
        }
        return $class;
    }

    public function runProcessor($modx, $processor, array $properties = array(), array $options = array())
    {
        if (!method_exists($modx, 'runProcessor')) {
            throw new \RuntimeException('MODX processor API is not available.');
        }
        return $modx->runProcessor($this->processorTarget($processor), $properties, $options);
    }
}
