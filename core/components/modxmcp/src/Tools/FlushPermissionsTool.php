<?php
namespace ModxMcp\Tools;

class FlushPermissionsTool implements ToolInterface
{
    public function name() { return 'flush_permissions'; }
    public function group() { return 'access'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $manager = $context->modx()->getCacheManager();
        $ok = $manager ? $manager->flushPermissions() : false;
        AuditSupport::log(
            $context,
            $this->name(),
            'system',
            array()
        );
        return array('flushed' => (bool)$ok);
    }
}
