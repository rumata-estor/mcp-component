<?php
namespace ModxMcp\Tools;

class CheckIntegrationsTool implements ToolInterface
{
    public function name() { return 'check_integrations'; }
    public function group() { return 'components'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->extras() && $context->platform(); }
    public function execute($context, array $arguments) { return $context->extras()->report($context); }
}
