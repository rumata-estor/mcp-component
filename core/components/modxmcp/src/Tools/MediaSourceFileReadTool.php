<?php
namespace ModxMcp\Tools;

class MediaSourceFileReadTool implements ToolInterface
{
    public function name() { return 'read_media_source_file'; }
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
        if (empty($data['path'])) {
            throw new \ModxMCPClientException('File path is required.');
        }

        $rootPath = MediaSourceSupport::rootPath($context, $source);
        $relativePath = FilesystemSupport::normalizeRelativePath($data['path']);
        $absolutePath = FilesystemSupport::joinPath($rootPath, $relativePath);
        if (!is_file($absolutePath)) {
            throw new \ModxMCPClientException("File not found: {$relativePath}");
        }

        return array_merge(
            array(
                'media_source' => MediaSourceSupport::normalize($context, $source, false),
                'path' => $relativePath,
            ),
            FilesystemSupport::readFileBounded($context->modx(), $absolutePath, $data)
        );
    }
}
