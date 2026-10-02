<?php
namespace MODX\Revolution\Processors\Resource { class Update {} }
namespace {
    $root = dirname(__DIR__) . '/core/components/modxmcp/src';
    require_once $root . '/Autoloader.php';
    \ModxMcp\Autoloader::register($root);

    class FakeModx2 { public function getVersionData() { return array('version' => '2.8.8-pl'); } }
    class FakeModx3 { public function getVersionData() { return array('version' => '3.2.4-pl'); } }

    $p2 = \ModxMcp\Platform\PlatformFactory::detect(new FakeModx2());
    $p3 = \ModxMcp\Platform\PlatformFactory::detect(new FakeModx3());

    if ($p2->key() !== 'modx2') { throw new \Exception('MODX2 detection failed'); }
    if ($p3->key() !== 'modx3') { throw new \Exception('MODX3 detection failed'); }
    if ($p2->className('resource') !== 'modResource') { throw new \Exception('MODX2 class map failed'); }
    if ($p3->className('resource') !== 'MODX\\Revolution\\modResource') { throw new \Exception('MODX3 class map failed'); }
    if ($p2->className('template_var_resource') !== 'modTemplateVarResource') { throw new \Exception('MODX2 TV value class map failed'); }
    if ($p3->className('template_var_resource') !== 'MODX\\Revolution\\modTemplateVarResource') { throw new \Exception('MODX3 TV value class map failed'); }
    if ($p2->processorTarget('resource/update') !== 'resource/update') { throw new \Exception('MODX2 processor map failed'); }
    if ($p3->processorTarget('resource/update') !== 'MODX\\Revolution\\Processors\\Resource\\Update') { throw new \Exception('MODX3 processor map failed'); }

    echo "PLATFORM_MAPPING_OK\n";
}
