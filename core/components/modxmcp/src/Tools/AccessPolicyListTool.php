<?php
namespace ModxMcp\Tools;

class AccessPolicyListTool implements ToolInterface
{
    public function name() { return 'list_access_policies'; }
    public function group() { return 'access'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        return AclProcessorSupport::run(
            $context, 'security/access/policy/getlist', $data, true
        );
    }
}
