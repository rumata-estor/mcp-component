<?php
namespace ModxMcp\Tools;

class ClearCacheTool implements ToolInterface
{
    public function name() { return 'clear_cache'; }
    public function group() { return 'ops'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $partitions = isset($data['partitions'])
            && is_array($data['partitions'])
            ? $data['partitions']
            : array();
        $manager = $context->modx()->getCacheManager();
        if (!empty($partitions)) {
            $providers = array();
            foreach ($partitions as $partition) {
                $providers[(string)$partition] = array();
            }
            $manager->refresh($providers);
        } else {
            $manager->refresh();
        }
        AuditSupport::log(
            $context,
            $this->name(),
            'system',
            array('partitions' => $partitions)
        );
        return array(
            'cleared' => true,
            'partitions' => !empty($partitions)
                ? $partitions
                : 'all',
        );
    }
}
