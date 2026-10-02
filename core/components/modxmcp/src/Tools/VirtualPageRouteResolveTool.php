<?php
namespace ModxMcp\Tools;

class VirtualPageRouteResolveTool implements ToolInterface
{
    public function name() { return 'virtualpage_resolve_route'; }
    public function group() { return 'virtualpage'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        VirtualPageSupport::load($context);
        $path = !empty($data['path'])
            ? (string)$data['path']
            : (!empty($data['uri']) ? (string)$data['uri'] : '');
        if ($path === '') {
            throw new \ModxMCPClientException('path or uri is required.');
        }
        $path = '/' . trim($path, '/');
        $rawPath = array_key_exists('path', $data) ? (string)$data['path'] : '';
        $rawUri = array_key_exists('uri', $data) ? (string)$data['uri'] : '';
        if (substr($rawPath, -1) === '/' || substr($rawUri, -1) === '/') {
            $path .= '/';
        }
        $method = !empty($data['method'])
            ? VirtualPageSupport::normalizeMethod($data['method'])
            : 'GET';

        $routes = (new VirtualPageRouteListTool())->execute(
            $context,
            array('active' => 1, 'limit' => 0)
        );
        foreach ($routes['routes'] as $route) {
            $methods = array_map(
                'trim',
                explode(',', strtoupper($route['method']))
            );
            if (!in_array($method, $methods, true)) {
                continue;
            }
            $match = VirtualPageSupport::matchRoutePattern(
                $route['route'],
                $path
            );
            if ($match === false) {
                continue;
            }
            $properties = is_array($route['properties'])
                ? $route['properties']
                : array();
            $placeholders = array_merge($match, $properties);
            $placeholders['uri'] = $path;

            return array(
                'found' => true,
                'path' => $path,
                'method' => $method,
                'route' => $route,
                'placeholders' => $placeholders,
                'placeholder_prefix' => $context->modx()->getOption(
                    'virtualpage_prefix_placeholder',
                    null,
                    'vp.'
                ),
            );
        }
        return array(
            'found' => false,
            'path' => $path,
            'method' => $method,
        );
    }
}
