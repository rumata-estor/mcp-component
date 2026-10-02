<?php
namespace ModxMcp\Tools;

class UserGroupGetTool implements ToolInterface
{
    public function name() { return 'get_user_group'; }
    public function group() { return 'access'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        if ($id <= 0) {
            throw new \ModxMCPClientException('get_user_group: id is required.');
        }
        $group = $context->modx()->getObject($context->platform()->className('user_group'), $id);
        if (!$group) {
            throw new \ModxMCPClientException('User group not found: ' . $id . '.');
        }
        return $group->toArray();
    }
}
