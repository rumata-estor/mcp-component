<?php
$root = dirname(__DIR__) . '/core/components/modxmcp/src';
require_once $root . '/Autoloader.php';
\ModxMcp\Autoloader::register($root);

class FakeContextProcessor {}
class_alias('FakeContextProcessor', 'MODX\\Revolution\\Processors\\Context\\GetList');
class_alias('FakeContextProcessor', 'MODX\\Revolution\\Processors\\Context\\Get');
class_alias('FakeContextProcessor', 'MODX\\Revolution\\Processors\\Context\\Setting\\GetList');
class_alias('FakeContextProcessor', 'MODX\\Revolution\\Processors\\Context\\Setting\\Get');
class FakeWorkspaceProcessor {}
class_alias('FakeWorkspaceProcessor', 'MODX\\Revolution\\Processors\\Workspace\\PackageNamespace\\GetList');
class_alias('FakeWorkspaceProcessor', 'MODX\\Revolution\\Processors\\Workspace\\Lexicon\\GetList');
class_alias('FakeWorkspaceProcessor', 'MODX\\Revolution\\Processors\\Workspace\\Lexicon\\Topic\\GetList');
class_alias('FakeWorkspaceProcessor', 'MODX\\Revolution\\Processors\\Workspace\\Providers\\GetList');
class_alias('FakeWorkspaceProcessor', 'MODX\\Revolution\\Processors\\Workspace\\Packages\\Rest\\GetList');

class FakeResponse {
    private $payload;
    public function __construct($payload) { $this->payload = $payload; }
    public function getResponse() { return json_encode($this->payload); }
    public function getObject() { return isset($this->payload['object']) ? $this->payload['object'] : array(); }
    public function isError() { return false; }
    public function getMessage() { return ''; }
    public function hasFieldErrors() { return false; }
    public function getFieldErrors() { return array(); }
}
class FakeLexicon { public function load() {} }

class FakeModx {
    public $lexicon;
    public $calls = array();
    public function __construct() { $this->lexicon = new FakeLexicon(); }
    public function getVersionData() { return array('version' => '3.2.4-pl', 'full_version' => '3.2.4-pl'); }
    public function getOption($key) {
        $values = array(
            'dbtype' => 'mysql',
            'base_path' => '/var/www/',
            'core_path' => '/var/www/core/',
        );
        return isset($values[$key]) ? $values[$key] : null;
    }
    public function runProcessor($processor, $properties = array(), $options = array()) {
        $this->calls[] = array($processor, $properties);
        if (strpos($processor, 'Workspace\\Providers\\GetList') !== false) {
            return new FakeResponse(array('success' => true, 'total' => 1, 'results' => array(array('id' => 1, 'name' => 'modx.com'))));
        }
        if (strpos($processor, 'Workspace\\Packages\\Rest\\GetList') !== false) {
            return new FakeResponse(array('success' => true, 'total' => 0, 'results' => array()));
        }
        if (strpos($processor, 'Workspace\\PackageNamespace\\GetList') !== false) {
            return new FakeResponse(array('success' => true, 'total' => 1, 'results' => array(array('name' => 'core'))));
        }
        if (strpos($processor, 'Workspace\\Lexicon\\Topic\\GetList') !== false) {
            return new FakeResponse(array('success' => true, 'total' => 1, 'results' => array(array('name' => 'default'))));
        }
        if (strpos($processor, 'Workspace\\Lexicon\\GetList') !== false) {
            return new FakeResponse(array('success' => true, 'total' => 1, 'results' => array(array('name' => 'site_name', 'value' => 'Site'))));
        }
        if (substr($processor, -15) === 'Context\\GetList') {
            return new FakeResponse(array('success' => true, 'total' => 1, 'results' => array(array('key' => 'web'))));
        }
        if (substr($processor, -11) === 'Context\\Get') {
            return new FakeResponse(array('success' => true, 'object' => array('key' => 'web')));
        }
        if (substr($processor, -23) === 'Context\\Setting\\GetList') {
            return new FakeResponse(array('success' => true, 'total' => 1, 'results' => array(array('key' => 'site_name', 'context_key' => 'web'))));
        }
        if (substr($processor, -19) === 'Context\\Setting\\Get') {
            return new FakeResponse(array('success' => true, 'object' => array('key' => 'site_name', 'context_key' => 'web')));
        }
        throw new Exception('Unexpected processor: ' . $processor);
    }
}
class FakeLegacy {
    const VERSION = '1.0.1-test';
    public function getCapabilities() { return array('ok' => true, 'source' => 'legacy'); }
    public function getSupportedActions() { return array('ops' => array('list_actions')); }
}

$runtime = new \ModxMcp\Core\Runtime(new FakeModx(), new FakeLegacy());
if ($runtime->context()->platform()->key() !== 'modx3') { throw new Exception('MODX 3 platform detection failed'); }

$caps = $runtime->registry()->get('get_capabilities');
if (!$caps || $caps->isMutation() || !$caps->supports($runtime->context())) { throw new Exception('Capabilities tool registration failed'); }
$result = $caps->execute($runtime->context(), array());
if (empty($result['ok']) || $result['source'] !== 'legacy') { throw new Exception('Bad capabilities result'); }
$actions = $runtime->registry()->get('list_actions')->execute($runtime->context(), array());
if (empty($actions['ops']) || $actions['ops'][0] !== 'list_actions') { throw new Exception('Bad list_actions result'); }

$info = $runtime->registry()->get('system_info');
if (!$info || $info->isMutation() || !$info->supports($runtime->context())) { throw new Exception('SystemInfo tool registration failed'); }
$result = $info->execute($runtime->context(), array());
if ($result['modx_version'] !== '3.2.4-pl') { throw new Exception('Bad MODX version'); }
if ($result['modxmcp_version'] !== '1.0.1-test') { throw new Exception('Bad connector version'); }
if ($result['dbtype'] !== 'mysql') { throw new Exception('Bad DB type'); }

$listContexts = $runtime->registry()->get('list_contexts')->execute($runtime->context(), array());
if ($listContexts['total'] !== 1 || $listContexts['results'][0]['key'] !== 'web') { throw new Exception('Bad context list result'); }
$getContext = $runtime->registry()->get('get_context')->execute($runtime->context(), array('key' => 'web'));
if ($getContext['key'] !== 'web') { throw new Exception('Bad context get result'); }
$listSettings = $runtime->registry()->get('list_context_settings')->execute($runtime->context(), array('context_key' => 'web'));
if ($listSettings['total'] !== 1 || $listSettings['results'][0]['key'] !== 'site_name') { throw new Exception('Bad context setting list result'); }
$getSetting = $runtime->registry()->get('get_context_setting')->execute($runtime->context(), array('context_key' => 'web', 'key' => 'site_name'));
if ($getSetting['key'] !== 'site_name') { throw new Exception('Bad context setting get result'); }
$listNamespaces = $runtime->registry()->get('list_namespaces')->execute($runtime->context(), array());
if ($listNamespaces['total'] !== 1 || $listNamespaces['results'][0]['name'] !== 'core') { throw new Exception('Bad namespace list result'); }
$listLexiconEntries = $runtime->registry()->get('list_lexicon_entries')->execute($runtime->context(), array('namespace' => 'core', 'topic' => 'default'));
if ($listLexiconEntries['total'] !== 1 || $listLexiconEntries['results'][0]['name'] !== 'site_name') { throw new Exception('Bad lexicon entry list result'); }
$listLexiconTopics = $runtime->registry()->get('list_lexicon_topics')->execute($runtime->context(), array('namespace' => 'core'));
if ($listLexiconTopics['total'] !== 1 || $listLexiconTopics['results'][0]['name'] !== 'default') { throw new Exception('Bad lexicon topic list result'); }
$listProviders = $runtime->registry()->get('list_providers')->execute($runtime->context(), array());
if ($listProviders['total'] !== 1 || $listProviders['results'][0]['name'] !== 'modx.com') { throw new Exception('Bad provider list result'); }
$searchPackages = $runtime->registry()->get('search_packages')->execute($runtime->context(), array('provider' => 1, 'query' => '__none__'));
if ($searchPackages['provider'] !== 1 || $searchPackages['total'] !== 0) { throw new Exception('Bad package search result'); }
foreach ($runtime->context()->modx()->calls as $call) {
    if (substr($call[0], -7) === 'GetList'
        && strpos($call[0], 'Packages\\Rest\\GetList') === false
        && (!isset($call[1]['limit']) || $call[1]['limit'] !== 0)) {
        throw new Exception('Unbounded list processor must default limit=0');
    }
}

foreach (array(
    'project_overview',
    'list_resources',
    'check_integrations',
    'list_system_settings',
    'get_system_setting',
    'list_tv_input_types',
    'suggest_tv_type',
    'list_installed_components',
    'list_media_sources',
    'get_media_source',
    'list_media_source_files',
    'read_media_source_file',
    'get_resource_tvs',
    'list_tv_values',
    'get_component_files',
    'read_component_file',
    'list_elements',
    'get_context_setting',
    'list_context_settings',
    'get_context',
    'list_contexts',
    'get_element',
    'list_namespaces',
    'list_lexicon_entries',
    'list_lexicon_topics',
    'list_property_sets',
    'get_property_set',
    'list_providers',
    'search_packages',
    'list_users',
    'get_user',
    'list_user_groups',
    'get_user_group',
    'list_user_group_members',
    'list_roles',
    'get_role',
    'list_access_policies',
    'list_access_policy_templates',
    'list_access_permissions',
    'list_resource_groups',
    'list_context_access',
    'list_resourcegroup_access',
    'help',
    'list_actions',
    'read_error_log',
    'read_audit_log',
    'describe_object',
    'search_code',
    'find_usages',
    'view_element',
    'dependency_graph',
    'versionx_list_versions',
    'versionx_get_version',
    'migx_list_configs',
    'migx_get_config',
    'virtualpage_list_events',
    'virtualpage_get_event',
    'virtualpage_list_handlers',
    'virtualpage_get_handler',
    'virtualpage_list_routes',
    'virtualpage_get_route',
    'virtualpage_resolve_route',
    'ms2_list_link_types',
    'ms2_get_link_type',
    'ms2_list_product_links',
    'ms2_list_categories',
    'ms2_list_orders',
    'ms2_get_order',
    'ms2_list_option_types',
    'ms2_list_options',
    'ms2_get_option',
    'ms2_get_product_options',
) as $name) {
    if (!$runtime->registry()->get($name)) { throw new Exception('Missing registered tool: ' . $name); }
}
if (count($runtime->registry()->all()) !== 74) { throw new Exception('Unexpected tool count'); }
echo "MODULAR_CORE_OK\n";
