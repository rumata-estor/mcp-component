<?php
namespace ModxMcp\Tools;

class FilesystemSupport
{
    public static function normalizeRelativePath($path)
    {
        $path = str_replace('\\', '/', trim((string) $path));
        $path = trim($path, '/');
        if ($path === '') {
            return '';
        }

        $parts = array();
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                throw new \ModxMCPClientException('Path traversal is not allowed.');
            }
            $parts[] = $part;
        }
        return implode('/', $parts);
    }

    public static function joinPath($rootPath, $relativePath)
    {
        $absolutePath = $rootPath;
        if ($relativePath !== '') {
            $absolutePath .= DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        }

        $rootReal = realpath($rootPath);
        if ($rootReal === false) {
            $rootReal = self::normalizePath($rootPath);
        }

        $targetDir = file_exists($absolutePath)
            ? realpath($absolutePath)
            : realpath(dirname($absolutePath));
        if ($targetDir === false) {
            $targetDir = self::normalizePath(dirname($absolutePath));
        }

        if (!self::pathStartsWith($targetDir, $rootReal)) {
            throw new \ModxMCPClientException('Resolved path is outside of allowed root.');
        }

        return self::normalizePath($absolutePath);
    }

    public static function readFileBounded($modx, $absolutePath, array $data)
    {
        $total = (int) filesize($absolutePath);
        $maxAllowed = (int) $modx->getOption('modxmcp.max_read_bytes', null, 262144);
        if ($maxAllowed <= 0) { $maxAllowed = 262144; }

        $offset = isset($data['offset']) ? max(0, (int) $data['offset']) : 0;
        if ($offset > $total) { $offset = $total; }

        $requested = isset($data['bytes'])
            ? (int) $data['bytes']
            : (isset($data['length']) ? (int) $data['length'] : $maxAllowed);
        if ($requested <= 0 || $requested > $maxAllowed) {
            $requested = $maxAllowed;
        }

        $raw = ($total > 0 && $offset < $total)
            ? (string) file_get_contents($absolutePath, false, null, $offset, $requested)
            : '';
        $returned = strlen($raw);

        $mime = function_exists('mime_content_type')
            ? mime_content_type($absolutePath)
            : 'application/octet-stream';
        $isText = is_string($mime) && (
            strpos($mime, 'text/') === 0
            || strpos($mime, 'json') !== false
            || strpos($mime, 'xml') !== false
            || strpos($mime, 'javascript') !== false
            || strpos($mime, 'svg') !== false
            || strpos($mime, 'x-httpd-php') !== false
        );

        return array(
            'mime' => $mime,
            'size' => $total,
            'offset' => $offset,
            'returned_bytes' => $returned,
            'truncated' => ($offset + $returned) < $total,
            'encoding' => $isText ? 'utf-8' : 'base64',
            'content' => $isText ? $raw : base64_encode($raw),
        );
    }

    public static function normalizePath($path)
    {
        return rtrim(
            str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, (string) $path),
            DIRECTORY_SEPARATOR
        );
    }

    public static function isAbsolutePath($path)
    {
        return preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', (string) $path) === 1;
    }

    private static function pathStartsWith($path, $rootPath)
    {
        $path = self::normalizePath($path);
        $rootPath = self::normalizePath($rootPath);
        if ($path === $rootPath) {
            return true;
        }
        return strpos($path, $rootPath . DIRECTORY_SEPARATOR) === 0;
    }
}
