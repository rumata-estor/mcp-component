<?php
if(PHP_SAPI!=='cli'){http_response_code(403);die("CLI only.\n");}
require_once __DIR__.'/build.config.php';
$config=getenv('MODX_CONFIG_CORE');
if(!$config||!is_file($config)){fwrite(STDERR,"Set MODX_CONFIG_CORE.\n");exit(2);}
require_once $config;
require_once MODX_CORE_PATH.'model/modx/modx.class.php';
$modx=new modX();$modx->initialize('mgr');
$v=$modx->getVersionData();if(!isset($v['version'])||(int)$v['version']!==2){fwrite(STDERR,"MODX 2.x required.\n");exit(3);}
$settings=array();foreach($modx->getCollection('modSystemSetting',array('namespace'=>'modxmcp')) as $s){$settings[(string)$s->get('key')]=(string)$s->get('value');}ksort($settings,SORT_STRING);
if(in_array('--settings-hash',$argv,true)){echo 'SETTINGS_HASH='.hash('sha256',json_encode($settings)).PHP_EOL;exit(0);}
$readOnly=in_array('--read-only',$argv,true);
$tokenSetting=$modx->getObject('modSystemSetting',array('key'=>'modxmcp.api_token'));$token=$tokenSetting?trim((string)$tokenSetting->get('value')):'';
if(strlen($token)!==64){fwrite(STDERR,"Token missing.\n");exit(4);}
$base=rtrim((string)getenv('MODX_MCP_SMOKE_SITE_URL'),'/');if($base===''){$base=rtrim((string)$modx->getOption('site_url'),' /');}
$endpoint=$base.'/assets/components/modxmcp/api.php';
function smokeReq($method,$url,$token='',$payload=null){
 $ch=curl_init($url);curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);curl_setopt($ch,CURLOPT_CUSTOMREQUEST,$method);$h=array('Accept: application/json');
 if($token!=='')$h[]='X-MCP-Token: '.$token;
 if($payload!==null){$body=json_encode($payload);curl_setopt($ch,CURLOPT_POSTFIELDS,$body);$h[]='Content-Type: application/json';$h[]='Content-Length: '.strlen($body);}
 curl_setopt($ch,CURLOPT_HTTPHEADER,$h);$body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);if($body===false){$e=curl_error($ch);curl_close($ch);throw new RuntimeException($e);}curl_close($ch);
 $json=json_decode($body,true);if($status<200||$status>=300||!is_array($json))throw new RuntimeException('HTTP '.$status.' '.$body);if(isset($json['success'])&&!$json['success'])throw new RuntimeException($json['error']);
 return array_key_exists('data',$json)?$json['data']:$json;
}
function smokePost($e,$t,$a,$type='',$data=array()){return smokeReq('POST',$e,$t,array('action'=>$a,'type'=>$type,'data'=>$data));}
$health=smokeReq('GET',$endpoint);if((isset($health['variant'])?$health['variant']:'')!=='modx2'||(isset($health['version'])?$health['version']:'')!==PKG_VERSION)throw new RuntimeException('Health mismatch.');echo "HEALTH_OK variant=modx2\n";
$actions=smokePost($endpoint,$token,'list_actions');$count=0;foreach($actions as $g){if(is_array($g))$count+=count($g);}if($count!==182)throw new RuntimeException('Expected 182 actions, got '.$count);echo "ACTIONS_OK count=182\n";
$info=smokePost($endpoint,$token,'system_info');if(!is_array($info))throw new RuntimeException('system_info shape');echo "SYSTEM_INFO_OK\n";
if($readOnly){echo "MODX2_ENDPOINT_READ_ONLY_SMOKE_OK\n";exit(0);}
function smokeRandomHex($n){
 $raw=false;
 if(function_exists('random_bytes')){try{$raw=random_bytes($n);}catch(Exception $e){$raw=false;}catch(Throwable $e){$raw=false;}}
 elseif(function_exists('openssl_random_pseudo_bytes')){$strong=false;$raw=openssl_random_pseudo_bytes($n,$strong);if(!$strong)$raw=false;}
 if(!is_string($raw)||strlen($raw)!==$n)throw new RuntimeException('No secure random source.');
 return bin2hex($raw);
}
$name='__modx2mcp_smoke_'.gmdate('Ymd_His').'_'.smokeRandomHex(3);$c1='smoke1 '.smokeRandomHex(4);$c2='smoke2 '.smokeRandomHex(4);
try{
 $x=smokePost($endpoint,$token,'create_element','chunk',array('name'=>$name,'content'=>$c1));$id=(int)(isset($x['id'])?$x['id']:0);if($id<=0)throw new RuntimeException('create id');
 $x=smokePost($endpoint,$token,'get_element','chunk',array('id'=>$id));if((isset($x['snippet'])?$x['snippet']:(isset($x['content'])?$x['content']:null))!==$c1)throw new RuntimeException('create content');
 smokePost($endpoint,$token,'update_element','chunk',array('id'=>$id,'content'=>$c2));$x=smokePost($endpoint,$token,'get_element','chunk',array('id'=>$id));if((isset($x['snippet'])?$x['snippet']:(isset($x['content'])?$x['content']:null))!==$c2)throw new RuntimeException('update content');
 smokePost($endpoint,$token,'delete_element','chunk',array('id'=>$id,'dry_run'=>true));smokePost($endpoint,$token,'delete_element','chunk',array('id'=>$id));
 if($modx->getCount('modChunk',array('name'=>$name))!==0)throw new RuntimeException('chunk remains');echo "MCP_CRUD_SMOKE_OK\n";
}finally{$left=$modx->getObject('modChunk',array('name'=>$name));if($left){$left->remove();if($modx->getCacheManager())$modx->getCacheManager()->refresh();}}
echo "MODX2_ENDPOINT_SMOKE_OK\n";
