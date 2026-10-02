<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); die("CLI only.\n"); }
set_time_limit(0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
require_once __DIR__ . '/build.config.php';

$config = getenv('MODX_CONFIG_CORE');
if (!$config || !is_file($config)) {
    $dir = __DIR__;
    for ($i=0; $i<12; $i++) {
        $candidate = $dir . DIRECTORY_SEPARATOR . 'config.core.php';
        if (is_file($candidate)) { $config = $candidate; break; }
        $parent = dirname($dir); if ($parent === $dir) break; $dir = $parent;
    }
}
if (!$config || !is_file($config)) { fwrite(STDERR,"config.core.php not found; set MODX_CONFIG_CORE.\n"); exit(2); }
require_once $config;
if (!defined('MODX_CORE_PATH') || !is_file(MODX_CORE_PATH . 'model/modx/modx.class.php')) {
    fwrite(STDERR,"MODX 2 bootstrap failed.\n"); exit(2);
}
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
$modx = new modX();
$modx->initialize('mgr');
$v = $modx->getVersionData();
if (!isset($v['version']) || (int)$v['version'] !== 2) { fwrite(STDERR,"MODX 2.x required.\n"); exit(3); }

$args = array_slice($argv,1); $options=array();
foreach ($args as $arg) {
    if (strpos($arg,'--')===0 && strpos($arg,'=')!==false) {
        list($k,$val)=explode('=',substr($arg,2),2); $options[$k]=$val;
    }
}
$signature = isset($options['signature']) ? preg_replace('/[^a-zA-Z0-9._-]/','',$options['signature']) : strtolower(PKG_NAME).'-'.PKG_VERSION.'-'.PKG_RELEASE;
$action = isset($options['action']) ? strtolower((string)$options['action']) : 'install';
if (!in_array($action,array('install','uninstall'),true)) { fwrite(STDERR,"Unsupported --action.\n"); exit(2); }
$package = $modx->getObject('transport.modTransportPackage',array('signature'=>$signature));

if ($action === 'uninstall') {
    if (!$package) { fwrite(STDERR,"Package record not found: {$signature}\n"); exit(3); }
    $ok=$package->uninstall(); echo 'uninstall(): '.($ok?'OK':'FAILED').PHP_EOL; if(!$ok) exit(1);
    if($modx->getCacheManager()) $modx->getCacheManager()->refresh();
    $left=array();
    if($modx->getCount('modSystemSetting',array('namespace'=>'modxmcp'))>0) $left[]='settings';
    foreach(array('modxmcp_graph','modxmcp') as $text){ if($modx->getObject('modMenu',array('text'=>$text))) $left[]='menu:'.$text; }
    if($modx->getObject('modNamespace',array('name'=>'modxmcp'))) $left[]='namespace';
    if(file_exists(MODX_CORE_PATH.'components/modxmcp')) $left[]='core files';
    if(file_exists(MODX_ASSETS_PATH.'components/modxmcp')) $left[]='assets files';
    if($left){fwrite(STDERR,"Uninstall leftovers: ".implode(', ',$left)."\n");exit(7);}
    if(!$package->remove()){fwrite(STDERR,"Could not remove package record.\n");exit(8);}
    echo "MODX2_TRANSPORT_UNINSTALL_OK\n"; exit(0);
}

$archive = rtrim(MODX_CORE_PATH,'/\\').DIRECTORY_SEPARATOR.'packages'.DIRECTORY_SEPARATOR.$signature.'.transport.zip';
if(!is_file($archive)){fwrite(STDERR,"Transport archive not found: {$archive}\n");exit(4);}
if(!$package){
    $package=$modx->newObject('transport.modTransportPackage');
    $package->set('signature',$signature);
    $package->set('state',1);
    $package->set('workspace',1);
    $package->set('package_name',PKG_NAME);
    if(!$package->save()){fwrite(STDERR,"Could not create package record.\n");exit(5);}
}
$ok=$package->install(); echo 'install(): '.($ok?'OK':'FAILED').PHP_EOL; if(!$ok) exit(1);
if($modx->getCacheManager()) $modx->getCacheManager()->refresh();
$token=$modx->getObject('modSystemSetting',array('key'=>'modxmcp.api_token'));
$checks=array(
    (bool)$modx->getObject('modNamespace',array('name'=>'modxmcp')),
    (int)$modx->getCount('modSystemSetting',array('key:LIKE'=>'modxmcp.%'))===16,
    (bool)$modx->getObject('modMenu',array('text'=>'modxmcp')),
    (bool)$modx->getObject('modMenu',array('text'=>'modxmcp_graph')),
    $token && strlen(trim((string)$token->get('value')))===64,
    is_file(MODX_ASSETS_PATH.'components/modxmcp/api.php'),
    is_file(MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php'),
);
if(in_array(false,$checks,true)){fwrite(STDERR,"Transport verification failed.\n");exit(6);}
$model=@file_get_contents(MODX_CORE_PATH.'components/modxmcp/model/modxmcp.class.php');
if(!is_string($model)||!preg_match("/const\\s+VERSION\\s*=\\s*'([^']+)'/",$model,$m)||$m[1]!==PKG_VERSION){fwrite(STDERR,"Model version mismatch.\n");exit(6);}
echo "MODX2_TRANSPORT_VERIFY_OK\n";
