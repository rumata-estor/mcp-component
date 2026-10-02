<?php
namespace ModxMcp\Extras;

class IntegrationRegistry
{
    private $definitions = array(
        array(
            'key' => 'minishop2',
            'namespace' => 'minishop2',
            'label' => 'miniShop2',
            'note' => 'Dedicated tools: product options, products, links, categories, orders (ms2_*).',
        ),
        array(
            'key' => 'migx',
            'namespace' => 'migx',
            'label' => 'MIGX',
            'note' => 'Dedicated tools: MIGX configs CRUD (migx_*) + create MIGX-type TVs.',
        ),
        array(
            'key' => 'versionx',
            'namespace' => 'versionx',
            'label' => 'VersionX',
            'note' => 'Dedicated tools: element/resource history + rollback (versionx_*).',
        ),
        array(
            'key' => 'virtualpage',
            'namespace' => 'virtualpage',
            'label' => 'VirtualPage',
            'note' => 'Dedicated tools: events / handlers / routes CRUD + resolve (virtualpage_*).',
        ),
    );

    public function definitions()
    {
        return $this->definitions;
    }

    public function report($context)
    {
        $modx = $context->modx();
        $platform = $context->platform();
        $namespaceClass = $platform->className('namespace');
        $snippetClass = $platform->className('snippet');
        $packageClass = $platform->className('transport_package');
        $out = array();

        foreach ($this->definitions as $def) {
            $installed = (bool) $modx->getObject($namespaceClass, array('name' => $def['namespace']));
            if (!$installed && !empty($def['snippet'])) {
                $installed = (bool) $modx->getObject($snippetClass, array('name' => $def['snippet']));
            }

            $version = null;
            if ($installed) {
                $c = $modx->newQuery($packageClass);
                $c->where(array('package_name' => $def['label'], 'installed:!=' => null));
                $c->sortby('installed', 'DESC');
                $c->limit(1);
                $pkg = $modx->getObject($packageClass, $c);
                if ($pkg) {
                    $version = trim(
                        $pkg->get('version_major') . '.' .
                        $pkg->get('version_minor') . '.' .
                        $pkg->get('version_patch'),
                        '.'
                    );
                }
            }

            $out[] = array(
                'key' => $def['key'],
                'label' => $def['label'],
                'installed' => $installed,
                'version' => $version,
                'note' => $def['note'],
            );
        }

        return array('integrations' => $out);
    }
}
