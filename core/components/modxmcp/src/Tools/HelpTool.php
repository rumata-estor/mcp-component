<?php
namespace ModxMcp\Tools;

class HelpTool implements ToolInterface
{
    public function name() { return 'help'; }
    public function group() { return 'ops'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $dir = rtrim($context->modx()->getOption('core_path'), '/') . '/components/modxmcp/docs/';
        $topic = isset($data['topic']) ? preg_replace('/[^a-z0-9_]/', '', strtolower((string)$data['topic'])) : '';
        $available = array();
        foreach ((array)glob($dir . '*.md') as $file) { $available[] = basename($file, '.md'); }
        sort($available);
        if ($topic === '' || $topic === 'index') {
            $index = @file_get_contents($dir . 'index.md');
            return array('topics' => $available, 'index' => ($index !== false) ? $index : '');
        }
        $file = $dir . $topic . '.md';
        if (!file_exists($file)) {
            return array('error' => "Unknown help topic '{$topic}'.", 'topics' => $available);
        }
        return array('topic' => $topic, 'content' => (string)@file_get_contents($file));
    }
}
