<?php
namespace ModxMcp\Tools;

class ComponentFileReadTool implements ToolInterface
{
    public function name() { return 'read_component_file'; }
    public function group() { return 'components'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        if (empty($data['name'])) {
            throw new \ModxMCPClientException('Component name is required.');
        }
        if (empty($data['path'])) {
            throw new \ModxMCPClientException('Component file path is required.');
        }

        $modx = $context->modx();
        $relativePath = FilesystemSupport::normalizeRelativePath($data['path']);

        foreach (ComponentSupport::scopes(isset($data['scope']) ? $data['scope'] : null) as $scope) {
            $componentRoot = ComponentSupport::rootPath($modx, $data['name'], $scope);
            if (!is_dir($componentRoot)) { continue; }

            $absolutePath = FilesystemSupport::joinPath($componentRoot, $relativePath);
            if (!is_file($absolutePath)) { continue; }

            return array_merge(
                array(
                    'component' => $data['name'],
                    'scope' => $scope,
                    'root_path' => $componentRoot,
                    'path' => $relativePath,
                ),
                FilesystemSupport::readFileBounded($modx, $absolutePath, $data)
            );
        }

        throw new \ModxMCPClientException(
            "Component file not found: {$data['name']} / {$relativePath}."
        );
    }
}
