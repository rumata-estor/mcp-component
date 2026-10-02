<?php
namespace ModxMcp\Tools;

class UserGetTool implements ToolInterface
{
    public function name() { return 'get_user'; }
    public function group() { return 'access'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        return AclProcessorSupport::run(
            $context, 'security/user/get', $data, false
        );
    }
}
