<?php
namespace ModxMcp\Tools;

class UserListTool implements ToolInterface
{
    public function name() { return 'list_users'; }
    public function group() { return 'access'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        return AclProcessorSupport::run(
            $context, 'security/user/getlist', $data, true
        );
    }
}
