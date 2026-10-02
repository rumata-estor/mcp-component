<?php
namespace ModxMcp\Tools;

class ElementContentSupport
{
    public static function map($context, $type)
    {
        $map = array(
            'chunk' => array('class' => 'chunk', 'field' => 'snippet'),
            'snippet' => array('class' => 'snippet', 'field' => 'snippet'),
            'template' => array('class' => 'template', 'field' => 'content'),
            'plugin' => array('class' => 'plugin', 'field' => 'plugincode'),
        );
        if (!isset($map[$type])) {
            throw new \ModxMCPClientException('type must be one of: chunk, snippet, template, plugin.');
        }
        $map[$type]['class'] = $context->platform()->className($map[$type]['class']);
        return $map[$type];
    }

    public static function resolve($context, array $data)
    {
        $type = isset($data['type']) ? (string)$data['type'] : '';
        $map = self::map($context, $type);
        $id = ElementSupport::resolveId($context, $type, $data);
        if ($id <= 0) {
            throw new \ModxMCPClientException('Element not found (provide id or name).');
        }
        $element = $context->modx()->getObject($map['class'], $id, false);
        if (!$element) {
            throw new \ModxMCPClientException($type . ' ' . $id . ' not found.');
        }
        return array($element, $type, $id, $map);
    }

    public static function read($context, $element, array $map)
    {
        $isStatic = (bool)$element->get('static');
        $absolute = null;
        if ($isStatic) {
            $relative = (string)$element->get('static_file');
            if ($relative !== '') {
                $modx = $context->modx();
                $relative = strtr($relative, array(
                    '{base_path}' => $modx->getOption('base_path'),
                    '{core_path}' => $modx->getOption('core_path'),
                    '{assets_path}' => $modx->getOption('assets_path'),
                    '[[++base_path]]' => $modx->getOption('base_path'),
                    '[[++core_path]]' => $modx->getOption('core_path'),
                    '[[++assets_path]]' => $modx->getOption('assets_path'),
                ));
                $absolute = FilesystemSupport::isAbsolutePath($relative)
                    ? $relative
                    : rtrim($modx->getOption('base_path'), '/\\') . '/' . ltrim($relative, '/\\');
            }
            if ($absolute !== null && is_file($absolute)) {
                return array((string)file_get_contents($absolute), true, $absolute);
            }
        }
        return array((string)$element->get($map['field']), $isStatic, $absolute);
    }

    public static function write(
        $context,
        $element,
        $type,
        array $map,
        $isStatic,
        $absolute,
        $content
    ) {
        if ($isStatic && $absolute !== null) {
            $directory = dirname($absolute);
            if (!is_dir($directory) && !@mkdir($directory, 0755, true)) {
                throw new \ModxMCPClientException(
                    'cannot create directory ' . $directory
                );
            }
            if (@file_put_contents($absolute, $content) === false) {
                throw new \ModxMCPClientException(
                    'cannot write static file ' . $absolute
                );
            }
        }

        $data = $element->toArray();
        $data[$map['field']] = $content;
        $data = ElementMutationSupport::filterData($type, $data);
        $response = $context->platform()->runProcessor(
            $context->modx(),
            ElementSupport::processorBase($type) . 'update',
            $data
        );
        if (!$response || $response->isError()) {
            throw new \ModxMCPClientException(
                'element save failed: '
                . ($response
                    ? ProcessorSupport::error($response)
                    : 'no response.')
            );
        }
    }
}
