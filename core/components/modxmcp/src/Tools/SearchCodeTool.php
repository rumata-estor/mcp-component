<?php
namespace ModxMcp\Tools;

class SearchCodeTool implements ToolInterface
{
    public function name() { return 'search_code'; }
    public function group() { return 'elements'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }
    public function execute($context, array $data) { return SearchSupport::search($context, $data); }
}
