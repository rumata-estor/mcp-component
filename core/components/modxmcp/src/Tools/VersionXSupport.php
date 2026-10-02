<?php
namespace ModxMcp\Tools;

class VersionXSupport
{
    public static function type(array $data)
    {
        $types = array(
            'resource' => array('class' => 'vxResource', 'label' => 'title'),
            'chunk' => array('class' => 'vxChunk', 'label' => 'name'),
            'snippet' => array('class' => 'vxSnippet', 'label' => 'name'),
            'template' => array('class' => 'vxTemplate', 'label' => 'templatename'),
            'plugin' => array('class' => 'vxPlugin', 'label' => 'name'),
            'tv' => array('class' => 'vxTemplateVar', 'label' => 'name'),
        );
        $type = !empty($data['type']) ? strtolower((string)$data['type']) : '';
        if (empty($types[$type])) {
            throw new \ModxMCPClientException(
                'VersionX type must be one of: ' . implode(', ', array_keys($types)) . '.'
            );
        }
        return $types[$type];
    }

    public static function positiveInt(array $data, $key)
    {
        $value = !empty($data[$key]) ? (int)$data[$key] : 0;
        if ($value <= 0) {
            throw new \ModxMCPClientException(
                $key . ' is required and must be a positive integer.'
            );
        }
        return $value;
    }

    public static function corePath($context)
    {
        $modx = $context->modx();
        return $modx->getOption(
            'versionx.core_path',
            null,
            $modx->getOption('core_path') . 'components/versionx/'
        );
    }

    public static function mutationMeta($context, array $data)
    {
        $meta = self::type($data);
        $map = array(
            'resource' => array('processor' => 'resources', 'content_class' => 'resource'),
            'chunk' => array('processor' => 'chunks', 'content_class' => 'chunk'),
            'snippet' => array('processor' => 'snippets', 'content_class' => 'snippet'),
            'template' => array('processor' => 'templates', 'content_class' => 'template'),
            'plugin' => array('processor' => 'plugins', 'content_class' => 'plugin'),
            'tv' => array('processor' => 'templatevars', 'content_class' => 'tv'),
        );
        $type = strtolower((string)$data['type']);
        $meta['processor'] = $map[$type]['processor'];
        $meta['content_class'] = $context->platform()->className($map[$type]['content_class']);
        return $meta;
    }

    public static function load($context)
    {
        $modx = $context->modx();
        $corePath = self::corePath($context);
        $service = $modx->getService('versionx', 'VersionX', $corePath . 'model/');
        if (!$service) {
            throw new \ModxMCPClientException(
                'Could not load VersionX service. Is VersionX installed on this MODX site?'
            );
        }
        return $service;
    }

    public static function normalize($version, array $meta, $includePayload)
    {
        $result = array(
            'version_id' => (int)$version->get('version_id'),
            'content_id' => (int)$version->get('content_id'),
            'saved' => $version->get('saved'),
            'user' => (int)$version->get('user'),
            'mode' => $version->get('mode'),
            'marked' => (bool)$version->get('marked'),
            'label' => $version->get($meta['label']),
        );
        if ($version->get('class') !== null) {
            $result['class'] = $version->get('class');
        }
        if ($version->get('context_key') !== null) {
            $result['context_key'] = $version->get('context_key');
        }
        $content = self::content($version);
        if ($content !== null) {
            $result['content_length'] = strlen((string)$content);
            $result['content_preview'] = function_exists('mb_substr')
                ? mb_substr((string)$content, 0, 300, 'UTF-8')
                : substr((string)$content, 0, 300);
        }
        if ($includePayload) {
            $result['data'] = $version->toArray();
        }
        return $result;
    }

    private static function content($version)
    {
        foreach (array('content', 'snippet', 'plugincode') as $field) {
            $value = $version->get($field);
            if ($value !== null) { return $value; }
        }
        return null;
    }
}
