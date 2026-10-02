<?php
namespace ModxMcp\Tools;

class PackageSupport
{
    public static function defaultProviderId($context)
    {
        $modx = $context->modx();
        $class = $context->platform()->className('transport_provider');
        $query = $modx->newQuery($class);
        $query->where(array('name:=' => 'modx.com', 'OR:name:=' => 'modxcms.com'));
        $provider = $modx->getObject($class, $query);
        if (!$provider) {
            $provider = $modx->getObject($class, array('id:>' => 0));
        }
        return $provider ? (int)$provider->get('id') : 0;
    }

    public static function listResult($response)
    {
        $decoded = json_decode($response->getResponse(), true);
        return array(
            'total' => isset($decoded['total']) ? (int)$decoded['total'] : 0,
            'results' => isset($decoded['results']) ? $decoded['results'] : array(),
        );
    }
}
