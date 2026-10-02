<?php
namespace ModxMcp\Tools;

class ElementViewTool implements ToolInterface
{
    public function name() { return 'view_element'; }
    public function group() { return 'elements'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        list($element, $type, $id, $map) = ElementContentSupport::resolve($context, $data);
        list($content, $isStatic) = ElementContentSupport::read($context, $element, $map);
        $lines = explode("\n", str_replace("\r\n", "\n", $content));
        $total = count($lines);
        $start = isset($data['start_line']) ? max(1, (int)$data['start_line']) : 1;
        $end = isset($data['end_line']) ? (int)$data['end_line'] : $total;
        if ($end > $total) { $end = $total; }
        if ($end < $start) { $end = $start; }
        $buffer = array();
        for ($i = $start; $i <= $end && $i <= $total; $i++) {
            $buffer[] = $i . "\t" . $lines[$i - 1];
        }
        return array(
            'type' => $type,
            'id' => $id,
            'name' => $element->get($type === 'template' ? 'templatename' : 'name'),
            'static' => $isStatic,
            'total_lines' => $total,
            'start_line' => $start,
            'end_line' => min($end, $total),
            'numbered' => implode("\n", $buffer),
        );
    }
}
