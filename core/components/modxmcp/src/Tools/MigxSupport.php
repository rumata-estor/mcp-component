<?php
namespace ModxMcp\Tools;

class MigxSupport
{
    public static function load($context)
    {
        $modx = $context->modx();
        $core = $modx->getOption(
            'migx.core_path',
            null,
            $modx->getOption('core_path') . 'components/migx/'
        );
        $modx->addPackage('migx', $core . 'model/');
        if (!$modx->loadClass('migxConfig')) {
            throw new \ModxMCPClientException(
                'MIGX is not installed (migxConfig class not found).'
            );
        }
    }

    public static function limit(array $data)
    {
        if (array_key_exists('limit', $data)) {
            $limit = (int)$data['limit'];
            if ($limit === 0) { return 0; }
            return max(1, min($limit, 500));
        }
        return 100;
    }

    public static function start(array $data)
    {
        return !empty($data['start']) ? max(0, (int)$data['start']) : 0;
    }
}
