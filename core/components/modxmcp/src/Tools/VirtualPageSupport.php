<?php
namespace ModxMcp\Tools;

class VirtualPageSupport
{
    public static function load($context)
    {
        $modx = $context->modx();
        $corePath = $modx->getOption(
            'virtualpage_core_path',
            null,
            $modx->getOption('core_path') . 'components/virtualpage/'
        );
        if (!is_dir($corePath)) {
            throw new \ModxMCPClientException(
                'Could not find VirtualPage component core path.'
            );
        }
        $modx->addPackage('virtualpage', $corePath . 'model/');
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

    public static function applyFilters($query, array $data, array $fields, $alias)
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)
                || $data[$field] === ''
                || $data[$field] === null) {
                continue;
            }
            $column = $alias !== '' ? $alias . '.' . $field : $field;
            $numeric = in_array(
                $field,
                array('id', 'active', 'type', 'entry', 'handler', 'event'),
                true
            );
            $key = $numeric ? $column : $column . ':LIKE';
            $value = $numeric
                ? (int)$data[$field]
                : '%' . (string)$data[$field] . '%';
            $query->where(array($key => $value));
        }
    }

    public static function resolveObject($context, $classKey, array $data)
    {
        self::load($context);
        $modx = $context->modx();
        if (!empty($data['id'])) {
            $object = $modx->getObject($classKey, (int)$data['id']);
        } elseif (!empty($data['name'])
            && in_array($classKey, array('vpEvent', 'vpHandler'), true)) {
            $object = $modx->getObject(
                $classKey,
                array('name' => (string)$data['name'])
            );
        } elseif ($classKey === 'vpRoute' && !empty($data['route'])) {
            $criteria = array('route' => (string)$data['route']);
            if (!empty($data['method'])) {
                $criteria['metod'] = self::normalizeMethod($data['method']);
            } elseif (!empty($data['metod'])) {
                $criteria['metod'] = self::normalizeMethod($data['metod']);
            }
            $object = $modx->getObject($classKey, $criteria);
        } else {
            $object = null;
        }
        if (!$object) {
            throw new \ModxMCPClientException(
                'VirtualPage object not found: ' . $classKey . '.'
            );
        }
        return $object;
    }

    public static function normalizeEvent($context, $event, $includeRoutes)
    {
        $result = $event->toArray();
        $result['id'] = (int)$result['id'];
        $result['rank'] = (int)$result['rank'];
        $result['active'] = (int)$result['active'];
        $result['route_count'] = $context->modx()->getCount(
            'vpRoute',
            array('event' => $event->get('id'))
        );
        if ($includeRoutes) {
            $routes = array();
            foreach ($event->getMany('Routes') as $route) {
                $routes[] = self::normalizeRoute($context, $route);
            }
            $result['routes'] = $routes;
        }
        return $result;
    }

    public static function normalizeHandler($context, $handler, $includeRoutes)
    {
        $result = $handler->toArray();
        $result['id'] = (int)$result['id'];
        $result['type'] = (int)$result['type'];
        $result['entry'] = (int)$result['entry'];
        $result['rank'] = (int)$result['rank'];
        $result['active'] = (int)$result['active'];
        $result['cache'] = (int)$result['cache'];
        $result['type_name'] = self::handlerTypeName($result['type']);
        $result['route_count'] = $context->modx()->getCount(
            'vpRoute',
            array('handler' => $handler->get('id'))
        );
        if ($includeRoutes) {
            $routes = array();
            foreach ($handler->getMany('Routes') as $route) {
                $routes[] = self::normalizeRoute($context, $route);
            }
            $result['routes'] = $routes;
        }
        return $result;
    }

    public static function normalizeRoute($context, $route)
    {
        $result = $route->toArray();
        $event = $route->getOne('Event');
        $handler = $route->getOne('Handler');
        $result['event_name'] = $event ? $event->get('name') : null;
        $result['handler_name'] = $handler ? $handler->get('name') : null;
        return self::normalizeRouteArray($result);
    }

    public static function normalizeRouteArray(array $row)
    {
        $properties = isset($row['properties']) ? $row['properties'] : array();
        if (is_string($properties)) {
            $decoded = json_decode($properties, true);
            $properties = is_array($decoded) ? $decoded : array();
        }
        return array(
            'id' => (int)$row['id'],
            'method' => isset($row['metod']) ? $row['metod'] : '',
            'metod' => isset($row['metod']) ? $row['metod'] : '',
            'route' => isset($row['route']) ? $row['route'] : '',
            'handler' => isset($row['handler']) ? (int)$row['handler'] : 0,
            'handler_name' => isset($row['handler_name'])
                ? $row['handler_name'] : null,
            'event' => isset($row['event']) ? (int)$row['event'] : 0,
            'event_name' => isset($row['event_name'])
                ? $row['event_name'] : null,
            'description' => isset($row['description'])
                ? $row['description'] : '',
            'rank' => isset($row['rank']) ? (int)$row['rank'] : 0,
            'active' => isset($row['active']) ? (int)$row['active'] : 0,
            'properties' => $properties,
        );
    }

    public static function normalizeMethod($method)
    {
        $parts = array_map('trim', explode(',', strtoupper((string)$method)));
        $parts = array_filter($parts);
        foreach ($parts as $part) {
            if (!in_array($part, array('GET', 'POST'), true)) {
                throw new \ModxMCPClientException(
                    'VirtualPage method must be GET, POST, or GET,POST.'
                );
            }
        }
        if (empty($parts)) {
            throw new \ModxMCPClientException(
                'VirtualPage method is required.'
            );
        }
        return implode(',', array_values(array_unique($parts)));
    }

    public static function handlerTypeName($type)
    {
        $map = array(
            0 => 'resource_forward',
            1 => 'snippet',
            2 => 'chunk',
            3 => 'dynamic_resource',
        );
        return isset($map[(int)$type]) ? $map[(int)$type] : 'unknown';
    }

    public static function corePath($context)
    {
        $modx = $context->modx();
        return $modx->getOption(
            'virtualpage_core_path',
            null,
            $modx->getOption('core_path') . 'components/virtualpage/'
        );
    }

    public static function service($context, $required = true)
    {
        $corePath = self::corePath($context);
        $service = $context->modx()->getService(
            'virtualpage',
            'virtualpage',
            $corePath . 'model/virtualpage/'
        );
        if (!$service && $required) {
            throw new \ModxMCPClientException(
                'Could not load VirtualPage service. Is VirtualPage installed on this MODX site?'
            );
        }
        return $service;
    }

    public static function preparePayload(array $data, array $allowedFields)
    {
        $payload = array();
        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $data)) { continue; }
            $value = $data[$field];
            if (in_array(
                $field,
                array('id', 'type', 'entry', 'rank', 'active', 'cache'),
                true
            )) {
                $value = (int)$value;
            }
            $payload[$field] = $value;
        }
        return $payload;
    }

    public static function prepareRoutePayload($context, array $data, $isUpdate)
    {
        $payload = self::preparePayload(
            $data,
            array(
                'route', 'handler', 'event', 'description',
                'rank', 'active', 'properties',
            )
        );
        if (array_key_exists('method', $data)) {
            $payload['metod'] = self::normalizeMethod($data['method']);
        } elseif (array_key_exists('metod', $data)) {
            $payload['metod'] = self::normalizeMethod($data['metod']);
        }

        $modx = $context->modx();
        if (!empty($data['event_name'])) {
            $event = $modx->getObject(
                'vpEvent',
                array('name' => (string)$data['event_name'])
            );
            if (!$event) {
                throw new \ModxMCPClientException(
                    'VirtualPage event not found: ' . $data['event_name'] . '.'
                );
            }
            $payload['event'] = (int)$event->get('id');
        }
        if (!empty($data['handler_name'])) {
            $handler = $modx->getObject(
                'vpHandler',
                array('name' => (string)$data['handler_name'])
            );
            if (!$handler) {
                throw new \ModxMCPClientException(
                    'VirtualPage handler not found: ' . $data['handler_name'] . '.'
                );
            }
            $payload['handler'] = (int)$handler->get('id');
        }

        if (array_key_exists('properties', $payload)
            && is_string($payload['properties'])) {
            $decoded = json_decode($payload['properties'], true);
            if ($payload['properties'] !== ''
                && json_last_error() !== JSON_ERROR_NONE) {
                throw new \ModxMCPClientException(
                    'properties must be a JSON object or an object payload.'
                );
            }
            $payload['properties'] = is_array($decoded) ? $decoded : array();
        }
        if (array_key_exists('properties', $payload)
            && !is_array($payload['properties'])) {
            throw new \ModxMCPClientException('properties must be an object.');
        }

        if (!empty($payload['route'])) {
            $payload['route'] = '/' . trim((string)$payload['route'], '/');
            if (!empty($data['route'])
                && substr((string)$data['route'], -1) === '/') {
                $payload['route'] .= '/';
            }
        }

        if (!$isUpdate) {
            foreach (array('route', 'metod', 'handler', 'event') as $field) {
                if (empty($payload[$field])) {
                    throw new \ModxMCPClientException(
                        $field . ' is required for VirtualPage route creation.'
                    );
                }
            }
            if (!array_key_exists('active', $payload)) {
                $payload['active'] = 1;
            }
        }

        if (!empty($payload['handler'])
            && !$modx->getObject('vpHandler', (int)$payload['handler'])) {
            throw new \ModxMCPClientException(
                'VirtualPage handler not found: ' . $payload['handler'] . '.'
            );
        }
        if (!empty($payload['event'])
            && !$modx->getObject('vpEvent', (int)$payload['event'])) {
            throw new \ModxMCPClientException(
                'VirtualPage event not found: ' . $payload['event'] . '.'
            );
        }

        return $payload;
    }

    public static function normalizeHandlerType($type)
    {
        if (is_string($type) && !is_numeric($type)) {
            $map = array(
                'resource' => 0,
                'snippet' => 1,
                'chunk' => 2,
                'dynamic_resource' => 3,
                'dynamic-resource' => 3,
                'template' => 3,
            );
            $key = strtolower(trim($type));
            if (!array_key_exists($key, $map)) {
                throw new \ModxMCPClientException(
                    'VirtualPage handler type must be 0, 1, 2, 3, '
                    . 'resource, snippet, chunk, or dynamic_resource.'
                );
            }
            return $map[$key];
        }
        $type = (int)$type;
        if (!in_array($type, array(0, 1, 2, 3), true)) {
            throw new \ModxMCPClientException(
                'VirtualPage handler type must be one of: 0, 1, 2, 3.'
            );
        }
        return $type;
    }

    public static function assertHandlerEntry($context, $type, $entry)
    {
        $entry = (int)$entry;
        if ($entry <= 0) { return; }
        $platform = $context->platform();
        $map = array(
            0 => $platform->className('resource'),
            1 => $platform->className('snippet'),
            2 => $platform->className('chunk'),
            3 => $platform->className('template'),
        );
        if (!empty($map[$type])
            && !$context->modx()->getObject($map[$type], $entry)) {
            throw new \ModxMCPClientException(
                'VirtualPage handler entry not found for type '
                . $type . ': ' . $entry . '.'
            );
        }
    }

    public static function assertUniqueRoute(
        $context,
        $route,
        $method,
        $excludeId = 0
    ) {
        $query = $context->modx()->newQuery('vpRoute');
        $query->where(array('route' => $route, 'metod' => $method));
        if ((int)$excludeId > 0) {
            $query->where(array('id:!=' => (int)$excludeId));
        }
        if ($context->modx()->getCount('vpRoute', $query) > 0) {
            throw new \ModxMCPClientException(
                'VirtualPage route already exists for '
                . $method . ' ' . $route . '.'
            );
        }
    }

    public static function ensurePluginEvent($context, $eventName)
    {
        $service = self::service($context, false);
        if ($service && method_exists($service, 'doEvent')) {
            return $service->doEvent('create', $eventName, 'vpEvent', 10);
        }

        $modx = $context->modx();
        $pluginClass = $context->platform()->className('plugin');
        $pluginEventClass = $context->platform()->className('plugin_event');

        $plugin = $modx->getObject(
            $pluginClass,
            array('name' => 'vpEvent')
        );
        if (!$plugin) { return false; }

        $event = $modx->getObject(
            $pluginEventClass,
            array(
                'pluginid' => $plugin->get('id'),
                'event' => $eventName,
            )
        );
        if (!$event) {
            $event = $modx->newObject($pluginEventClass);
            $event->set('pluginid', $plugin->get('id'));
            $event->set('event', $eventName);
        }
        $event->set('priority', 10);
        return $event->save();
    }

    public static function clearCache($context)
    {
        self::load($context);
        $service = self::service($context, false);
        if ($service && method_exists($service, 'clearCache')) {
            $service->clearCache(array('cache_key' => 'event/'));
        }
        $manager = $context->modx()->getCacheManager();
        if ($manager) {
            $manager->clean(array('cache_key' => 'default/virtualpage/'));
            $manager->refresh();
        }
        return array('cleared' => true);
    }

    public static function matchRoutePattern($pattern, $path)
    {
        $regex = preg_quote($pattern, '#');
        $names = array();
        if (strpos($pattern, '{') !== false) {
            $quoted = '';
            $offset = 0;
            if (preg_match_all(
                '/\{([^}:]+)(?::([^}]+))?\}/',
                $pattern,
                $matches,
                PREG_OFFSET_CAPTURE
            )) {
                foreach ($matches[0] as $i => $match) {
                    $quoted .= preg_quote(
                        substr($pattern, $offset, $match[1] - $offset),
                        '#'
                    );
                    $names[] = $matches[1][$i][0];
                    $subPattern = isset($matches[2][$i][0])
                        && $matches[2][$i][0] !== ''
                        ? $matches[2][$i][0]
                        : '[^/]+';
                    $quoted .= '(' . $subPattern . ')';
                    $offset = $match[1] + strlen($match[0]);
                }
                $quoted .= preg_quote(substr($pattern, $offset), '#');
                $regex = $quoted;
            }
        }
        if (!preg_match('#^' . $regex . '$#', $path, $matches)) {
            return false;
        }
        array_shift($matches);
        $result = array();
        foreach ($names as $i => $name) {
            $result[$name] = isset($matches[$i]) ? $matches[$i] : '';
        }
        return $result;
    }
}
