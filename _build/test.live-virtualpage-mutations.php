<?php
use MODX\Revolution\modX;

if (PHP_SAPI !== 'cli') { exit(2); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

$config = getenv('MODX_CONFIG_CORE');
if (!$config || !is_file($config)) {
    $dir = __DIR__;
    for ($i = 0; $i < 12; $i++) {
        $candidate = $dir . DIRECTORY_SEPARATOR . 'config.core.php';
        if (is_file($candidate)) { $config = $candidate; break; }
        $parent = dirname($dir);
        if ($parent === $dir) { break; }
        $dir = $parent;
    }
}
if (!$config || !is_file($config)) { exit(2); }

if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $probe = dirname((string)(realpath($config) ?: $config));
    for ($i = 0; $i < 12; $i++) {
        if (is_file($probe . '/core/vendor/autoload.php')) {
            $_SERVER['DOCUMENT_ROOT'] = rtrim($probe, '/\\');
            break;
        }
        $parent = dirname($probe);
        if ($parent === $probe) { break; }
        $probe = $parent;
    }
}

require_once $config;
require_once rtrim(MODX_CORE_PATH, '/\\') . '/vendor/autoload.php';

$modx = modX::getInstance();
$modx->initialize('mgr');
if (method_exists($modx, 'setOption')) {
    $modx->setOption('modxmcp.disabled_groups', '');
    $modx->setOption('modxmcp.audit_log', false);
} else {
    $modx->config['modxmcp.disabled_groups'] = '';
    $modx->config['modxmcp.audit_log'] = false;
}

$corePath = $modx->getOption(
    'modxmcp.core_path',
    null,
    $modx->getOption('core_path') . 'components/modxmcp/'
);
$corePath = str_replace(
    array('{core_path}', '[[++core_path]]'),
    rtrim((string)$modx->getOption('core_path'), '/\\') . DIRECTORY_SEPARATOR,
    (string)$corePath
);
require_once $corePath . 'model/modxmcp.class.php';

$vpCore = $modx->getOption(
    'virtualpage_core_path',
    null,
    $modx->getOption('core_path') . 'components/virtualpage/'
);
$vpInstalled = is_dir($vpCore);
if ($vpInstalled) {
    $modx->addPackage('virtualpage', $vpCore . 'model/');
}

function vp_legacy($modx)
{
    $mcp = new modxMCP($modx);
    $property = new ReflectionProperty('modxMCP', 'modularRuntime');
    $property->setAccessible(true);
    $property->setValue($mcp, null);
    return $mcp;
}

function vp_call($mcp, $action, array $data)
{
    try {
        return array(
            'ok' => true,
            'value' => $mcp->processRequest($action, '', $data),
        );
    } catch (Throwable $e) {
        return array(
            'ok' => false,
            'error' => $e->getMessage(),
        );
    }
}

function vp_normalize($value)
{
    if (!is_array($value)) { return $value; }

    $out = array();
    foreach ($value as $key => $item) {
        if (in_array(
            (string)$key,
            array('id', 'handler', 'event'),
            true
        ) && (is_int($item) || ctype_digit((string)$item))) {
            $out[$key] = '__ID__';
            continue;
        }
        $out[$key] = vp_normalize($item);
    }
    ksort($out);
    return $out;
}

function vp_compare($label, $a, $b, &$failures)
{
    $a = vp_normalize($a);
    $b = vp_normalize($b);
    if ($a !== $b) {
        $failures[] = array(
            'case' => $label,
            'modular' => $a,
            'legacy' => $b,
        );
        echo "DIFF {$label}\n";
    } else {
        echo "OK {$label}\n";
    }
}

function vp_tx($modx, $mcp, $action, array $data, $setup = null)
{
    $modx->beginTransaction();
    try {
        if ($setup) {
            $data = call_user_func($setup, $modx, $data);
        }
        return vp_call($mcp, $action, $data);
    } finally {
        $modx->rollback();
    }
}

function vp_make_event($modx, $name)
{
    $event = $modx->newObject('vpEvent');
    $event->fromArray(
        array(
            'name' => $name,
            'description' => 'parity',
            'rank' => 0,
            'active' => 1,
        ),
        '',
        true,
        true
    );
    if (!$event->save()) {
        throw new RuntimeException('Could not create temporary vpEvent.');
    }
    return $event;
}

function vp_make_handler($modx, $name)
{
    $handler = $modx->newObject('vpHandler');
    $handler->fromArray(
        array(
            'name' => $name,
            'type' => 3,
            'entry' => 0,
            'content' => '<p>parity</p>',
            'description' => 'parity',
            'cache' => 0,
            'rank' => 0,
            'active' => 1,
        ),
        '',
        true,
        true
    );
    if (!$handler->save()) {
        throw new RuntimeException('Could not create temporary vpHandler.');
    }
    return $handler;
}

function vp_make_route($modx, $routePath)
{
    $event = vp_make_event(
        $modx,
        '__modxmcp_vp_route_event_' . md5($routePath)
    );
    $handler = vp_make_handler(
        $modx,
        '__modxmcp_vp_route_handler_' . md5($routePath)
    );

    $route = $modx->newObject('vpRoute');
    $route->fromArray(
        array(
            'metod' => 'GET',
            'route' => $routePath,
            'handler' => (int)$handler->get('id'),
            'event' => (int)$event->get('id'),
            'description' => 'parity',
            'rank' => 0,
            'active' => 1,
            'properties' => array('source' => 'setup'),
        ),
        '',
        true,
        true
    );
    if (!$route->save()) {
        throw new RuntimeException('Could not create temporary vpRoute.');
    }

    return array($event, $handler, $route);
}

$failures = array();

if (!$vpInstalled) {
    $absenceCases = array(
        array('virtualpage_create_event', array('name' => '__test__')),
        array('virtualpage_update_event', array('id' => 999999, 'description' => 'x')),
        array('virtualpage_delete_event', array('id' => 999999)),
        array('virtualpage_create_handler', array('name' => '__test__')),
        array('virtualpage_update_handler', array('id' => 999999, 'description' => 'x')),
        array('virtualpage_delete_handler', array('id' => 999999)),
        array(
            'virtualpage_create_route',
            array(
                'route' => '/__test__',
                'method' => 'GET',
                'handler' => 999999,
                'event' => 999999,
            )
        ),
        array('virtualpage_update_route', array('id' => 999999, 'route' => '/__test__')),
        array('virtualpage_delete_route', array('id' => 999999)),
        array('virtualpage_clear_cache', array()),
    );

    foreach ($absenceCases as $case) {
        list($action, $data) = $case;
        vp_compare(
            $action . ':component_absent',
            vp_call(new modxMCP($modx), $action, $data),
            vp_call(vp_legacy($modx), $action, $data),
            $failures
        );
    }

    if ($failures) {
        echo json_encode(
            $failures,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ) . "\n";
        exit(1);
    }

    echo "VIRTUALPAGE_MUTATION_ABSENCE_PARITY_OK 10/10 actions\n";
    exit(0);
}

// Event create/update/delete.
$eventCreate = array(
    'name' => '__modxmcp_vp_event_create__',
    'description' => 'parity',
    'active' => 1,
);
vp_compare(
    'virtualpage_create_event',
    vp_tx(
        $modx,
        new modxMCP($modx),
        'virtualpage_create_event',
        $eventCreate
    ),
    vp_tx(
        $modx,
        vp_legacy($modx),
        'virtualpage_create_event',
        $eventCreate
    ),
    $failures
);

$setupEvent = function ($modx, $data) {
    $event = vp_make_event($modx, '__modxmcp_vp_event_existing__');
    $data['id'] = (int)$event->get('id');
    return $data;
};
vp_compare(
    'virtualpage_update_event',
    vp_tx(
        $modx,
        new modxMCP($modx),
        'virtualpage_update_event',
        array('description' => 'after', 'active' => 0),
        $setupEvent
    ),
    vp_tx(
        $modx,
        vp_legacy($modx),
        'virtualpage_update_event',
        array('description' => 'after', 'active' => 0),
        $setupEvent
    ),
    $failures
);
vp_compare(
    'virtualpage_delete_event',
    vp_tx(
        $modx,
        new modxMCP($modx),
        'virtualpage_delete_event',
        array(),
        $setupEvent
    ),
    vp_tx(
        $modx,
        vp_legacy($modx),
        'virtualpage_delete_event',
        array(),
        $setupEvent
    ),
    $failures
);

// Handler create/update/delete.
$handlerCreate = array(
    'name' => '__modxmcp_vp_handler_create__',
    'type' => 'dynamic_resource',
    'entry' => 0,
    'content' => '<p>created</p>',
    'description' => 'parity',
    'cache' => 0,
    'active' => 1,
);
vp_compare(
    'virtualpage_create_handler',
    vp_tx(
        $modx,
        new modxMCP($modx),
        'virtualpage_create_handler',
        $handlerCreate
    ),
    vp_tx(
        $modx,
        vp_legacy($modx),
        'virtualpage_create_handler',
        $handlerCreate
    ),
    $failures
);

$setupHandler = function ($modx, $data) {
    $handler = vp_make_handler(
        $modx,
        '__modxmcp_vp_handler_existing__'
    );
    $data['id'] = (int)$handler->get('id');
    return $data;
};
vp_compare(
    'virtualpage_update_handler',
    vp_tx(
        $modx,
        new modxMCP($modx),
        'virtualpage_update_handler',
        array('description' => 'after', 'cache' => 1),
        $setupHandler
    ),
    vp_tx(
        $modx,
        vp_legacy($modx),
        'virtualpage_update_handler',
        array('description' => 'after', 'cache' => 1),
        $setupHandler
    ),
    $failures
);
vp_compare(
    'virtualpage_delete_handler',
    vp_tx(
        $modx,
        new modxMCP($modx),
        'virtualpage_delete_handler',
        array(),
        $setupHandler
    ),
    vp_tx(
        $modx,
        vp_legacy($modx),
        'virtualpage_delete_handler',
        array(),
        $setupHandler
    ),
    $failures
);

// Route create/update/delete.
$setupRouteCreate = function ($modx, $data) {
    $event = vp_make_event(
        $modx,
        '__modxmcp_vp_route_create_event__'
    );
    $handler = vp_make_handler(
        $modx,
        '__modxmcp_vp_route_create_handler__'
    );
    $data['event'] = (int)$event->get('id');
    $data['handler'] = (int)$handler->get('id');
    return $data;
};
$routeCreate = array(
    'route' => '/__modxmcp-vp-create__/{slug}',
    'method' => 'GET',
    'description' => 'parity',
    'properties' => array('kind' => 'parity'),
    'active' => 1,
);
vp_compare(
    'virtualpage_create_route',
    vp_tx(
        $modx,
        new modxMCP($modx),
        'virtualpage_create_route',
        $routeCreate,
        $setupRouteCreate
    ),
    vp_tx(
        $modx,
        vp_legacy($modx),
        'virtualpage_create_route',
        $routeCreate,
        $setupRouteCreate
    ),
    $failures
);

$setupRoute = function ($modx, $data) {
    list($event, $handler, $route) = vp_make_route(
        $modx,
        '/__modxmcp-vp-existing__'
    );
    $data['id'] = (int)$route->get('id');
    return $data;
};
vp_compare(
    'virtualpage_update_route',
    vp_tx(
        $modx,
        new modxMCP($modx),
        'virtualpage_update_route',
        array(
            'route' => '/__modxmcp-vp-updated__/',
            'method' => 'POST',
            'properties' => array('kind' => 'after'),
        ),
        $setupRoute
    ),
    vp_tx(
        $modx,
        vp_legacy($modx),
        'virtualpage_update_route',
        array(
            'route' => '/__modxmcp-vp-updated__/',
            'method' => 'POST',
            'properties' => array('kind' => 'after'),
        ),
        $setupRoute
    ),
    $failures
);
vp_compare(
    'virtualpage_delete_route',
    vp_tx(
        $modx,
        new modxMCP($modx),
        'virtualpage_delete_route',
        array(),
        $setupRoute
    ),
    vp_tx(
        $modx,
        vp_legacy($modx),
        'virtualpage_delete_route',
        array(),
        $setupRoute
    ),
    $failures
);

// Safe/idempotent cache operation.
vp_compare(
    'virtualpage_clear_cache',
    vp_call(
        new modxMCP($modx),
        'virtualpage_clear_cache',
        array()
    ),
    vp_call(
        vp_legacy($modx),
        'virtualpage_clear_cache',
        array()
    ),
    $failures
);

if ($failures) {
    echo json_encode(
        $failures,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    ) . "\n";
    exit(1);
}

echo "VIRTUALPAGE_MUTATION_PARITY_OK 10/10 actions\n";
