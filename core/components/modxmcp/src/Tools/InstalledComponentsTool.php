<?php
namespace ModxMcp\Tools;

class InstalledComponentsTool implements ToolInterface
{
    public function name() { return 'list_installed_components'; }
    public function group() { return 'components'; }
    public function isMutation() { return false; }

    public function supports($context)
    {
        return $context && $context->modx();
    }

    public function execute($context, array $arguments)
    {
        $modx = $context->modx();
        $components = array();

        foreach ($this->codeRoots($modx) as $scope => $rootPath) {
            if (!is_dir($rootPath)) { continue; }

            foreach (scandir($rootPath) as $entry) {
                if ($entry === '.' || $entry === '..') { continue; }
                $componentPath = $rootPath . DIRECTORY_SEPARATOR . $entry;
                if (!is_dir($componentPath)) { continue; }

                if (!isset($components[$entry])) {
                    $components[$entry] = array(
                        'name' => $entry,
                        'scopes' => array(),
                    );
                }
                $components[$entry]['scopes'][$scope] = array(
                    'path' => $this->normalizePath($componentPath),
                );
            }
        }

        ksort($components, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values($components);
    }

    private function codeRoots($modx)
    {
        $configuredRoots = (string) $modx->getOption(
            'modxmcp.component_code_roots',
            null,
            'core/components,assets/components'
        );

        $scopePaths = array();
        foreach (explode(',', $configuredRoots) as $configuredRoot) {
            $configuredRoot = trim($configuredRoot);
            if ($configuredRoot === '') { continue; }

            $resolved = strtr($configuredRoot, array(
                '{base_path}' => $modx->getOption('base_path'),
                '{core_path}' => $modx->getOption('core_path'),
                '{assets_path}' => $modx->getOption('assets_path'),
                '[[++base_path]]' => $modx->getOption('base_path'),
                '[[++core_path]]' => $modx->getOption('core_path'),
                '[[++assets_path]]' => $modx->getOption('assets_path'),
            ));

            if (!$this->isAbsolutePath($resolved)) {
                $resolved = rtrim($modx->getOption('base_path'), '/\\')
                    . DIRECTORY_SEPARATOR . ltrim($resolved, '/\\');
            }

            $scope = stripos(str_replace('\\', '/', $configuredRoot), 'assets/components') !== false
                ? 'assets'
                : 'core';
            $scopePaths[$scope] = $this->normalizePath($resolved);
        }

        return $scopePaths;
    }

    private function normalizePath($path)
    {
        return rtrim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, (string) $path), DIRECTORY_SEPARATOR);
    }

    private function isAbsolutePath($path)
    {
        return preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', (string) $path) === 1;
    }
}
