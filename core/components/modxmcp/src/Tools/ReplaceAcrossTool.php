<?php
namespace ModxMcp\Tools;

class ReplaceAcrossTool implements ToolInterface
{
    public function name() { return 'replace_across'; }
    public function group() { return 'code_search'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $find = isset($data['find']) ? (string)$data['find'] : '';
        if ($find === '') {
            throw new \ModxMCPClientException(
                'replace_across: "find" is required.'
            );
        }
        if (!array_key_exists('replacement', $data)) {
            throw new \ModxMCPClientException(
                'replace_across: "replacement" is required (use "" to delete the string).'
            );
        }

        $replacement = (string)$data['replacement'];
        $caseSensitive = array_key_exists('case_sensitive', $data)
            ? !empty($data['case_sensitive'])
            : true;
        $dryRun = !empty($data['dry_run']);
        $allowed = array('chunk', 'snippet', 'template', 'plugin');
        $types = isset($data['types']) && is_array($data['types']) && $data['types']
            ? array_values(array_filter(
                $data['types'],
                function ($type) use ($allowed) {
                    return in_array($type, $allowed, true);
                }
            ))
            : $allowed;

        if (!$types) {
            throw new \ModxMCPClientException(
                'replace_across: types must be among chunk, snippet, template, plugin.'
            );
        }

        $limit = isset($data['limit']) ? max(1, (int)$data['limit']) : 200;
        $hits = SearchSupport::search(
            $context,
            array(
                'query' => $find,
                'types' => $types,
                'limit' => $limit,
                'case_sensitive' => $caseSensitive,
            )
        );

        $results = array();
        $totalOccurrences = 0;

        foreach ($hits['results'] as $hit) {
            $type = $hit['type'];
            if (!in_array($type, $allowed, true)) { continue; }

            $map = ElementContentSupport::map($context, $type);
            $element = $context->modx()->getObject(
                $map['class'],
                (int)$hit['id'],
                false
            );
            if (!$element) { continue; }

            list($content, $isStatic, $absolute) = ElementContentSupport::read(
                $context,
                $element,
                $map
            );

            $count = $caseSensitive
                ? substr_count($content, $find)
                : substr_count(strtolower($content), strtolower($find));
            if ($count === 0) { continue; }

            if (!$dryRun) {
                $newContent = $caseSensitive
                    ? str_replace($find, $replacement, $content)
                    : str_ireplace($find, $replacement, $content);

                ElementMutationSupport::transaction(
                    $context,
                    function () use (
                        $context,
                        $element,
                        $type,
                        $map,
                        $isStatic,
                        $absolute,
                        $newContent
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
                        return true;
                    }
                );

                AuditSupport::log(
                    $context,
                    $this->name(),
                    $type,
                    array(
                        'id' => (int)$hit['id'],
                        'occurrences' => $count,
                    )
                );
            }

            $row = array(
                'type' => $type,
                'id' => (int)$hit['id'],
                'name' => $hit['name'],
                'occurrences' => $count,
                'static' => $isStatic,
            );

            if ($dryRun) {
                $lines = explode(
                    "\n",
                    str_replace("\r\n", "\n", $content)
                );
                $needle = $caseSensitive ? $find : strtolower($find);
                $preview = array();

                foreach ($lines as $index => $line) {
                    $haystack = $caseSensitive ? $line : strtolower($line);
                    if (strpos($haystack, $needle) !== false) {
                        $preview[] = array(
                            'line' => $index + 1,
                            'line_text' => $line,
                        );
                        if (count($preview) >= 5) { break; }
                    }
                }
                $row['preview'] = $preview;
            }

            $results[] = $row;
            $totalOccurrences += $count;
        }

        if (!$dryRun && $results) {
            ElementMutationSupport::refreshCache($context);
        }

        return array(
            'find' => $find,
            'replacement' => $replacement,
            'case_sensitive' => $caseSensitive,
            'dry_run' => $dryRun,
            'elements' => count($results),
            'total_occurrences' => $totalOccurrences,
            'capped_at' => $limit,
            'results' => $results,
        );
    }
}
