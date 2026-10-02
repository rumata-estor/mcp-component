<?php
namespace ModxMcp\Tools;

class MediaSourceSupport
{
    public static function resolve($context, array $data)
    {
        $modx = $context->modx();
        $class = $context->platform()->className('media_source');

        if (!empty($data['id'])) {
            return $modx->getObject($class, (int) $data['id']);
        }
        if (!empty($data['name'])) {
            return $modx->getObject($class, array('name' => $data['name']));
        }
        return null;
    }

    public static function normalize($context, $source, $includeProperties = true)
    {
        $result = array(
            'id' => $source->get('id'),
            'name' => $source->get('name'),
            'class_key' => $source->get('class_key'),
            'description' => $source->get('description'),
        );

        if ($includeProperties) {
            $result['properties'] = self::normalizeProperties($source->get('properties'));
            try {
                $result['root_path'] = self::rootPath($context, $source);
            } catch (\Exception $e) {
                $result['root_path'] = null;
                $result['root_path_error'] = $e->getMessage();
            }
        }

        return $result;
    }

    public static function rootPath($context, $source)
    {
        $modx = $context->modx();
        $basePath = self::propertyValue($source->get('properties'), 'basePath');

        if (($basePath === '' || $basePath === null) && method_exists($source, 'initialize')) {
            try {
                $source->initialize();
            } catch (\Exception $e) {
                // Continue with the same fallback chain as the legacy implementation.
            }
        }

        if (($basePath === '' || $basePath === null) && method_exists($source, 'getProperty')) {
            $basePath = $source->getProperty('basePath');
        }
        if (($basePath === '' || $basePath === null) && method_exists($source, 'getBasePath')) {
            $basePath = $source->getBasePath();
        }
        if (($basePath === '' || $basePath === null) && method_exists($source, 'getBases')) {
            $bases = $source->getBases('');
            if (is_array($bases) && !empty($bases['path'])) {
                $basePath = $bases['path'];
            }
        }
        if (($basePath === '' || $basePath === null) && self::isFilesystem($source)) {
            $basePath = $modx->getOption('assets_path');
        }

        if ($basePath === '' || $basePath === null) {
            throw new \ModxMCPClientException("Media source {$source->get('id')} does not define basePath.");
        }

        $resolved = self::resolvePlaceholders($modx, (string) $basePath);
        if ($resolved === '' && self::isFilesystem($source)) {
            $resolved = (string) $modx->getOption('assets_path');
        }

        if (!FilesystemSupport::isAbsolutePath($resolved)) {
            $resolved = rtrim($modx->getOption('base_path'), '/\\')
                . DIRECTORY_SEPARATOR . ltrim($resolved, '/\\');
        }

        return FilesystemSupport::normalizePath($resolved);
    }

    private static function resolvePlaceholders($modx, $path)
    {
        return strtr((string) $path, array(
            '{base_path}' => $modx->getOption('base_path'),
            '{core_path}' => $modx->getOption('core_path'),
            '{assets_path}' => $modx->getOption('assets_path'),
            '[[++base_path]]' => $modx->getOption('base_path'),
            '[[++core_path]]' => $modx->getOption('core_path'),
            '[[++assets_path]]' => $modx->getOption('assets_path'),
        ));
    }

    private static function normalizeProperties($properties)
    {
        if (!is_array($properties)) {
            return $properties;
        }

        $normalized = array();
        foreach ($properties as $key => $value) {
            if (is_array($value) && array_key_exists('value', $value) && count($value) === 1) {
                $normalized[$key] = $value['value'];
                continue;
            }
            $normalized[$key] = is_array($value)
                ? self::normalizeProperties($value)
                : $value;
        }
        return $normalized;
    }

    private static function propertyValue($properties, $key)
    {
        if (!is_array($properties) || !array_key_exists($key, $properties)) {
            return '';
        }
        $value = $properties[$key];
        if (is_array($value) && array_key_exists('value', $value)) {
            return $value['value'];
        }
        return $value;
    }

    public static function assertReadAllowed($context, $source)
    {
        if (self::isFilesystem($source)) {
            $allow = (bool) $context->modx()->getOption('modxmcp.allow_root_filesystem_read', null, false);
            if (!$allow) {
                throw new \ModxMCPClientException(
                    'Filesystem media source browsing is disabled by modxmcp.allow_root_filesystem_read. '
                    . 'Use component read tools for installed package code.'
                );
            }
        }
    }

    private static function isFilesystem($source)
    {
        $classKey = (string) $source->get('class_key');
        $name = (string) $source->get('name');
        return stripos($classKey, 'File') !== false || strcasecmp($name, 'Filesystem') === 0;
    }
}
