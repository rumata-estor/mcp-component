<?php
namespace ModxMcp\Tools;

class ErrorLogReadTool implements ToolInterface
{
    public function name() { return 'read_error_log'; }
    public function group() { return 'ops'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $path = rtrim($context->modx()->getOption('core_path'), '/') . '/cache/logs/error.log';
        if (!file_exists($path)) {
            return array('total' => 0, 'lines' => array(), 'path' => $path);
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) { $lines = array(); }
        $limit = isset($data['limit']) ? max(1, (int)$data['limit']) : 100;
        $tail = array_slice($lines, -$limit);
        return array('total' => count($tail), 'lines' => $tail);
    }
}
