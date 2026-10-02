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
