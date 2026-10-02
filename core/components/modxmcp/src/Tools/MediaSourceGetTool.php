<?php
namespace ModxMcp\Tools;

class MediaSourceGetTool implements ToolInterface
{
    public function name() { return 'get_media_source'; }
    public function group() { return 'media'; }
    public function isMutation() { return false; }

    public function supports($context)
    {
        return $context && $context->modx() && $context->platform();
    }

    public function execute($context, array $data)
    {
        $source = MediaSourceSupport::resolve($context, $data);
        if (!$source) {
            throw new \ModxMCPClientException('Media source not found.');
        }
        return MediaSourceSupport::normalize($context, $source, true);
    }
}
