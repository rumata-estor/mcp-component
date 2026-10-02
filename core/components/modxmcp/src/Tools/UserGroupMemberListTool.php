<?php
namespace ModxMcp\Tools;

class UserGroupMemberListTool implements ToolInterface
{
    public function name() { return 'list_user_group_members'; }
    public function group() { return 'access'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        return AclProcessorSupport::run(
            $context, 'security/group/user/getlist', $data, true
        );
    }
}
