<?php
namespace ModxMcp\Tools;

class ComponentSupport
{
    public static function codeRoots($modx)
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

            if (!FilesystemSupport::isAbsolutePath($resolved)) {
                $resolved = rtrim($modx->getOption('base_path'), '/\\')
                    . DIRECTORY_SEPARATOR . ltrim($resolved, '/\\');
            }

            $scope = stripos(str_replace('\\', '/', $configuredRoot), 'assets/components') !== false
                ? 'assets'
                : 'core';
            $scopePaths[$scope] = FilesystemSupport::normalizePath($resolved);
        }

        return $scopePaths;
    }

    public static function scopes($scope = null)
    {
        if ($scope === null || $scope === '' || $scope === 'all') {
            return array('core', 'assets');
        }
        if (!in_array($scope, array('core', 'assets'), true)) {
            throw new \ModxMCPClientException('Component scope must be one of: core, assets, all.');
        }
        return array($scope);
    }

    public static function rootPath($modx, $componentName, $scope)
    {
        $roots = self::codeRoots($modx);
        if (empty($roots[$scope])) {
            throw new \ModxMCPClientException("Component code root is not configured for scope: {$scope}.");
        }

        $safeName = FilesystemSupport::normalizeRelativePath($componentName);
        if (strpos($safeName, '/') !== false) {
            throw new \ModxMCPClientException('Component name must be a single directory name.');
        }

        return FilesystemSupport::joinPath($roots[$scope], $safeName);
    }
}
