<?php
namespace ModxMcp\Tools;

class SearchSupport
{
    public static function map($context)
    {
        $platform = $context->platform();
        return array(
            'chunk' => array('class' => $platform->className('chunk'), 'content' => 'snippet', 'name' => 'name'),
            'snippet' => array('class' => $platform->className('snippet'), 'content' => 'snippet', 'name' => 'name'),
            'template' => array('class' => $platform->className('template'), 'content' => 'content', 'name' => 'templatename'),
            'plugin' => array('class' => $platform->className('plugin'), 'content' => 'plugincode', 'name' => 'name'),
            'tv' => array('class' => $platform->className('tv'), 'content' => 'default_text', 'name' => 'name'),
            'resource' => array('class' => $platform->className('resource'), 'content' => 'content', 'name' => 'pagetitle'),
        );
    }

    public static function search($context, array $data)
    {
        $query = isset($data['query']) ? trim((string)$data['query']) : '';
        if ($query === '') {
            throw new \ModxMCPClientException('search_code: "query" is required.');
        }
        $limit = isset($data['limit']) ? (int)$data['limit'] : 50;
        if ($limit < 1) { $limit = 1; }
        if ($limit > 200) { $limit = 200; }
        $caseSensitive = !empty($data['case_sensitive']);
        $map = self::map($context);
        $types = (isset($data['types']) && is_array($data['types']) && !empty($data['types']))
            ? $data['types']
            : array('chunk', 'snippet', 'template', 'plugin', 'tv', 'resource');
        $modx = $context->modx();
        $results = array();

        foreach ($types as $type) {
            if (!isset($map[$type]) || count($results) >= $limit) { continue; }
            $m = $map[$type];
            $isElement = ($type !== 'resource');

            $queryObject = $modx->newQuery($m['class']);
            $queryObject->where(array(array(
                $m['content'] . ':LIKE' => '%' . $query . '%',
                'OR:' . $m['name'] . ':LIKE' => '%' . $query . '%',
            )));
            if ($isElement) { $queryObject->where(array('static' => 0)); }
            $queryObject->limit($limit);
            foreach ($modx->getCollection($m['class'], $queryObject) as $object) {
                if (count($results) >= $limit) { break; }
                $results[] = self::hit(
                    $type, $object, $m, $query,
                    (string)$object->get($m['content']), $caseSensitive
                );
            }

            if ($isElement && count($results) < $limit) {
                $staticQuery = $modx->newQuery($m['class']);
                $staticQuery->where(array('static' => 1));
                $needle = $caseSensitive ? $query : strtolower($query);
                foreach ($modx->getCollection($m['class'], $staticQuery) as $object) {
                    if (count($results) >= $limit) { break; }
                    $content = (string)$object->getContent();
                    $name = (string)$object->get($m['name']);
                    $haystack = $caseSensitive ? ($content . "\n" . $name) : strtolower($content . "\n" . $name);
                    if (strpos($haystack, $needle) !== false) {
                        $results[] = self::hit($type, $object, $m, $query, $content, $caseSensitive);
                    }
                }
            }
        }

        return array('query' => $query, 'count' => count($results), 'results' => $results);
    }

    private static function hit($type, $object, array $map, $query, $content, $caseSensitive)
    {
        $nameField = $map['name'];
        $name = (string)$object->get($nameField);
        $hayContent = $caseSensitive ? $content : strtolower($content);
        $needle = $caseSensitive ? $query : strtolower($query);
        $pos = strpos($hayContent, $needle);
        $field = ($pos !== false) ? $map['content'] : $nameField;
        $snippet = '';
        $line = null;
        $lineText = null;
        if ($pos !== false) {
            $start = max(0, $pos - 60);
            $snippet = substr($content, $start, strlen($query) + 120);
            $snippet = trim(preg_replace('/\s+/', ' ', $snippet));
            if ($start > 0) { $snippet = '…' . $snippet; }
            $lineStart = strrpos(substr($content, 0, $pos), "\n");
            $lineStart = ($lineStart === false) ? 0 : $lineStart + 1;
            $lineEnd = strpos($content, "\n", $pos);
            if ($lineEnd === false) { $lineEnd = strlen($content); }
            $line = substr_count($content, "\n", 0, $lineStart) + 1;
            $lineText = rtrim(substr($content, $lineStart, $lineEnd - $lineStart), "\r");
        }
        return array(
            'type' => $type,
            'id' => (int)$object->get('id'),
            'name' => $name,
            'matched_field' => $field,
            'static' => $type !== 'resource' ? (bool)$object->get('static') : false,
            'line' => $line,
            'line_text' => $lineText,
            'snippet' => $snippet,
        );
    }
}
