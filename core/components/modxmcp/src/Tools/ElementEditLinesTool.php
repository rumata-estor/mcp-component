<?php
namespace ModxMcp\Tools;

class ElementEditLinesTool implements ToolInterface
{
    public function name() { return 'edit_element_lines'; }
    public function group() { return 'elements'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        list($element, $type, $id, $map) = ElementContentSupport::resolve(
            $context,
            $data
        );
        $edits = isset($data['edits']) && is_array($data['edits'])
            ? $data['edits']
            : null;
        if (!$edits) {
            throw new \ModxMCPClientException(
                'edit_element_lines: a non-empty "edits" array is required.'
            );
        }

        list($content, $isStatic, $absolute) = ElementContentSupport::read(
            $context,
            $element,
            $map
        );
        $eol = "\n";
        $lines = $this->splitLines($content, $eol);
        $total = count($lines);
        $resolved = array();

        foreach ($edits as $index => $edit) {
            if (!is_array($edit) || !isset($edit['start_line'])) {
                throw new \ModxMCPClientException(
                    'edit #' . $index . ': start_line is required.'
                );
            }
            $start = (int)$edit['start_line'];
            $end = isset($edit['end_line'])
                ? (int)$edit['end_line']
                : $start;
            $insert = $end === $start - 1;
            $replacement = isset($edit['replacement'])
                ? (string)$edit['replacement']
                : '';
            $expect = array_key_exists('expect', $edit)
                ? (string)$edit['expect']
                : null;

            if ($insert) {
                if ($start < 1 || $start > $total + 1) {
                    throw new \ModxMCPClientException(
                        'edit #' . $index . ': insert position ' . $start
                        . ' out of range (1..' . ($total + 1) . ').'
                    );
                }
                $resolvedStart = $start;
                $resolvedEnd = $start - 1;
            } else {
                if ($end < $start) {
                    throw new \ModxMCPClientException(
                        'edit #' . $index . ': end_line < start_line.'
                    );
                }
                list($resolvedStart, $resolvedEnd) = $this->locateRange(
                    $lines,
                    $start,
                    $end,
                    $expect,
                    $index
                );
            }

            $replacementLines = $replacement === ''
                ? array()
                : explode(
                    "\n",
                    str_replace("\r\n", "\n", $replacement)
                );
            $resolved[] = array(
                's' => $resolvedStart,
                'e' => $resolvedEnd,
                'repl' => $replacementLines,
                'insert' => $insert,
            );
        }

        $covered = array();
        foreach ($resolved as $item) {
            if ($item['insert']) { continue; }
            for ($line = $item['s']; $line <= $item['e']; $line++) {
                if (isset($covered[$line])) {
                    throw new \ModxMCPClientException(
                        'edits overlap on line ' . $line . '.'
                    );
                }
                $covered[$line] = true;
            }
        }

        usort(
            $resolved,
            function ($a, $b) { return $b['s'] - $a['s']; }
        );
        foreach ($resolved as $item) {
            $offset = $item['s'] - 1;
            $length = $item['insert']
                ? 0
                : ($item['e'] - $item['s'] + 1);
            array_splice(
                $lines,
                $offset,
                $length,
                $item['repl']
            );
        }

        $newContent = implode($eol, $lines);
        $totalAfter = count($lines);

        return ElementMutationSupport::transaction(
            $context,
            function () use (
                $context,
                $element,
                $type,
                $id,
                $map,
                $isStatic,
                $absolute,
                $newContent,
                $edits,
                $total,
                $totalAfter
            ) {
                ElementContentSupport::write(
                    $context,
                    $element,
                    $type,
                    $map,
                    $isStatic,
                    $absolute,
                    $newContent
                );
                ElementMutationSupport::refreshCache($context);
                AuditSupport::log(
                    $context,
                    'edit_element_lines',
                    $type,
                    array('id' => $id, 'edits' => count($edits))
                );
                return array(
                    'type' => $type,
                    'id' => $id,
                    'static' => $isStatic,
                    'edits_applied' => count($edits),
                    'total_lines_before' => $total,
                    'total_lines_after' => $totalAfter,
                );
            }
        );
    }

    private function splitLines($content, &$eol)
    {
        $eol = strpos($content, "\r\n") !== false ? "\r\n" : "\n";
        return explode("\n", str_replace("\r\n", "\n", $content));
    }

    private function locateRange(
        array $lines,
        $start,
        $end,
        $expect,
        $index
    ) {
        $total = count($lines);
        if ($expect === null) {
            if ($start < 1 || $end > $total) {
                throw new \ModxMCPClientException(
                    'edit #' . $index . ': lines ' . $start . '-' . $end
                    . ' out of range (1..' . $total . ').'
                );
            }
            return array($start, $end);
        }

        $expectLines = explode(
            "\n",
            str_replace("\r\n", "\n", $expect)
        );
        $length = count($expectLines);
        if (
            $start >= 1
            && ($start + $length - 1) <= $total
            && array_slice($lines, $start - 1, $length) === $expectLines
        ) {
            return array($start, $start + $length - 1);
        }

        $matches = array();
        for ($i = 0; $i + $length <= $total; $i++) {
            if (array_slice($lines, $i, $length) === $expectLines) {
                $matches[] = $i + 1;
            }
        }
        if (count($matches) === 1) {
            return array(
                $matches[0],
                $matches[0] + $length - 1,
            );
        }
        if (count($matches) === 0) {
            throw new \ModxMCPClientException(
                'edit #' . $index
                . ': expected text not found (the lines have changed since you read them).'
            );
        }
        throw new \ModxMCPClientException(
            'edit #' . $index . ': expected text matches '
            . count($matches) . ' places; make it more specific.'
        );
    }
}
