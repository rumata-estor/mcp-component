<?php
namespace ModxMcp\Tools;

class MediaSourceFilesTool implements ToolInterface
{
    public function name() { return 'list_media_source_files'; }
    public function group() { return 'media'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $source = MediaSourceSupport::resolve($context, $data);
        if (!$source) {
            throw new \ModxMCPClientException('Media source not found.');
        }
        MediaSourceSupport::assertReadAllowed($context, $source);

        $rootPath = MediaSourceSupport::rootPath($context, $source);
        $relativePath = !empty($data['path'])
            ? FilesystemSupport::normalizeRelativePath($data['path'])
            : '';
        $absolutePath = FilesystemSupport::joinPath($rootPath, $relativePath);

        if (!is_dir($absolutePath)) {
            throw new \ModxMCPClientException("Directory not found: {$relativePath}");
        }

        $entries = array();
        foreach (scandir($absolutePath) as $entry) {
            if ($entry === '.' || $entry === '..') { continue; }
            $entryAbsolute = $absolutePath . DIRECTORY_SEPARATOR . $entry;
            $entryRelative = ltrim(
                str_replace('\\', '/', ($relativePath ? $relativePath . '/' : '') . $entry),
                '/'
            );
            $entries[] = array(
                'name' => $entry,
                'path' => $entryRelative,
                'is_dir' => is_dir($entryAbsolute),
                'size' => is_file($entryAbsolute) ? filesize($entryAbsolute) : null,
                'modified_on' => filemtime($entryAbsolute),
            );
        }

        usort($entries, function ($a, $b) {
            if ($a['is_dir'] === $b['is_dir']) {
                return strcmp($a['name'], $b['name']);
            }
            return $a['is_dir'] ? -1 : 1;
        });

        return array(
            'media_source' => MediaSourceSupport::normalize($context, $source, false),
            'path' => $relativePath,
            'entries' => $entries,
        );
    }
}
