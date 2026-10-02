<?php
namespace ModxMcp\Tools;

class ComponentFilesTool implements ToolInterface
{
    public function name() { return 'get_component_files'; }
    public function group() { return 'components'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        if (empty($data['name'])) {
            throw new \ModxMCPClientException('Component name is required.');
        }

        $modx = $context->modx();
        $relativePath = !empty($data['path'])
            ? FilesystemSupport::normalizeRelativePath($data['path'])
            : '';
        $results = array();

        foreach (ComponentSupport::scopes(isset($data['scope']) ? $data['scope'] : null) as $scope) {
            $componentRoot = ComponentSupport::rootPath($modx, $data['name'], $scope);
            if (!is_dir($componentRoot)) { continue; }

            $absolutePath = FilesystemSupport::joinPath($componentRoot, $relativePath);
            if (!is_dir($absolutePath)) { continue; }

            $entries = array();
            foreach (scandir($absolutePath) as $entry) {
                if ($entry === '.' || $entry === '..') { continue; }
                $entryAbsolute = $absolutePath . DIRECTORY_SEPARATOR . $entry;
                $entryRelative = ltrim(
                    str_replace('\\', '/', ($relativePath ? $relativePath . '/' : '') . $entry),
                    '/'
                );
                $entries[] = array(
                    'name' => $entry,
                    'path' => $entryRelative,
                    'is_dir' => is_dir($entryAbsolute),
                    'size' => is_file($entryAbsolute) ? filesize($entryAbsolute) : null,
                    'modified_on' => filemtime($entryAbsolute),
                );
            }

            usort($entries, function ($a, $b) {
                if ($a['is_dir'] === $b['is_dir']) {
                    return strcmp($a['name'], $b['name']);
                }
                return $a['is_dir'] ? -1 : 1;
            });

            $results[] = array(
                'scope' => $scope,
                'component' => $data['name'],
                'root_path' => $componentRoot,
                'path' => $relativePath,
                'entries' => $entries,
            );
        }

        if (empty($results)) {
            throw new \ModxMCPClientException("Component not found or path unavailable: {$data['name']}.");
        }

        return count($results) === 1 ? $results[0] : $results;
    }
}
