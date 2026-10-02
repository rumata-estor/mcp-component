<?php
namespace ModxMcp\Tools;

class AuditLogReadTool implements ToolInterface
{
    public function name() { return 'read_audit_log'; }
    public function group() { return 'ops'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $path = rtrim($context->modx()->getOption('core_path'), '/') . '/components/modxmcp/logs/audit.log';
        if (!file_exists($path)) { return array('total' => 0, 'entries' => array()); }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) { $lines = array(); }
        $filterAction = isset($data['action']) ? (string)$data['action'] : '';
        $entries = array();
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (!is_array($entry)) { continue; }
            if ($filterAction !== '' && (!isset($entry['action']) || $entry['action'] !== $filterAction)) { continue; }
            $entries[] = $entry;
        }
        $limit = isset($data['limit']) ? max(1, (int)$data['limit']) : 100;
        $entries = array_slice($entries, -$limit);
        return array('total' => count($entries), 'entries' => $entries);
    }
}
