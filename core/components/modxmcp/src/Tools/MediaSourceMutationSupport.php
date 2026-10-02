<?php
namespace ModxMcp\Tools;

class MediaSourceMutationSupport
{
    public static function resolveForFileOperation($context, array $data)
    {
        $selector = array();
        if (isset($data['source']) && $data['source'] !== '') {
            if (is_numeric($data['source'])) {
                $selector['id'] = (int)$data['source'];
            } else {
                $selector['name'] = (string)$data['source'];
            }
        } elseif (!empty($data['id'])) {
            $selector['id'] = (int)$data['id'];
        } elseif (!empty($data['name'])) {
            $selector['name'] = (string)$data['name'];
        }

        $source = MediaSourceSupport::resolve($context, $selector);
        if (!$source) {
            throw new \ModxMCPClientException(
                'Media source not found (provide "source" = id or name).'
            );
        }
        $source->initialize();
        return $source;
    }

    public static function error($source, $fallback)
    {
        $errors = method_exists($source, 'getErrors')
            ? $source->getErrors()
            : array();
        if (is_array($errors) && !empty($errors)) {
            $parts = array();
            foreach ($errors as $key => $value) {
                $parts[] = is_string($key)
                    ? $key . ': ' . $value
                    : (string)$value;
            }
            return implode('; ', $parts);
        }
        return $fallback;
    }

    public static function mergeProperties($context, $id, array $map)
    {
        $class = $context->platform()->className('media_source');
        $source = $context->modx()->getObject($class, (int)$id);
        if (!$source) {
            throw new \ModxMCPClientException(
                'media source ' . (int)$id . ' not found for properties update.'
            );
        }

        $current = $source->getProperties();
        if (!is_array($current)) { $current = array(); }
        foreach ($map as $key => $value) {
            if (isset($current[$key]) && is_array($current[$key])) {
                $current[$key]['value'] = $value;
            } else {
                $current[$key] = array(
                    'name' => $key,
                    'desc' => '',
                    'type' => 'textfield',
                    'options' => array(),
                    'value' => $value,
                    'area' => '',
                );
            }
        }
        $source->setProperties($current);
        if (!$source->save()) {
            throw new \ModxMCPClientException(
                'Could not save media source ' . (int)$id . ' properties.'
            );
        }
    }

    public static function refresh($context)
    {
        $manager = $context->modx()->getCacheManager();
        if ($manager) { $manager->refresh(); }
    }
}
