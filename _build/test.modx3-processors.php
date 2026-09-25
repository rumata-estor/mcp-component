<?php

/**
 * Verify that every MODX core processor referenced by modxMCP resolves to a real
 * PSR-4 processor file in a checked-out MODX 3 tree.
 *
 * Usage:
 *   php _build/test.modx3-processors.php /path/to/modx/core/src/Revolution/Processors
 */

$processorsRoot = isset($argv[1]) ? rtrim($argv[1], '/\\') : '';
if ($processorsRoot === '' || !is_dir($processorsRoot)) {
    fwrite(STDERR, "Usage: php _build/test.modx3-processors.php /path/to/MODX/Processors\n");
    exit(2);
}

$modelFile = dirname(__DIR__) . '/core/components/modxmcp/model/modxmcp.class.php';
$model = file_get_contents($modelFile);
if ($model === false) {
    fwrite(STDERR, "Could not read {$modelFile}\n");
    exit(2);
}

$paths = array();

if (preg_match_all("/['\"]proc['\"]\\s*=>\\s*['\"]([^'\"]+)['\"]/", $model, $matches)) {
    foreach ($matches[1] as $path) {
        $paths[$path] = true;
    }
}

if (preg_match_all("/runCoreProcessor\\(\\s*['\"]([^'\"]+)['\"]/", $model, $matches)) {
    foreach ($matches[1] as $path) {
        $paths[$path] = true;
    }
}

$nameMap = array(
    'package_namespace' => 'PackageNamespace',
    'template_var'      => 'TemplateVar',
    'resourcegroup'     => 'ResourceGroup',
    'usergroup'         => 'UserGroup',
    'getlist'           => 'GetList',
    'getnodes'          => 'GetNodes',
    'getinfo'           => 'GetInfo',
    'emptyrecyclebin'   => 'EmptyRecycleBin',
    'refreshuris'       => 'RefreshUris',
    'remove_locks'      => 'RemoveLocks',
    'removeresource'    => 'RemoveResource',
    'updateresourcesin' => 'UpdateResourcesIn',
);

$toRelativeFile = static function ($processor) use ($nameMap) {
    $path = trim(str_replace('\\', '/', (string) $processor), '/');

    if (strpos($path, 'workspace/namespace/') === 0) {
        $path = 'workspace/package_namespace/' . substr($path, strlen('workspace/namespace/'));
    } elseif (strpos($path, 'element/tv/') === 0) {
        $path = 'element/template_var/' . substr($path, strlen('element/tv/'));
    }

    $parts = explode('/', $path);
    foreach ($parts as &$part) {
        $key = strtolower($part);
        $part = isset($nameMap[$key]) ? $nameMap[$key] : ucfirst($part);
    }
    unset($part);

    return implode('/', $parts) . '.php';
};

$missing = array();
$checked = 0;

ksort($paths);
foreach (array_keys($paths) as $path) {
    if (!preg_match('#^(context|security|workspace|resource|system|source)/#', $path)) {
        continue;
    }
    $relative = $toRelativeFile($path);
    ++$checked;
    if (!is_file($processorsRoot . '/' . $relative)) {
        $missing[] = $path . ' -> ' . $relative;
    }
}

/* Dynamic element CRUD routes assembled at runtime. */
$elementDirs = array(
    'chunk'    => 'Chunk',
    'snippet'  => 'Snippet',
    'template' => 'Template',
    'tv'       => 'TemplateVar',
    'category' => 'Category',
    'plugin'   => 'Plugin',
);
foreach ($elementDirs as $legacy => $directory) {
    foreach (array('Get', 'Create', 'Update', 'Remove') as $action) {
        $relative = 'Element/' . $directory . '/' . $action . '.php';
        ++$checked;
        if (!is_file($processorsRoot . '/' . $relative)) {
            $missing[] = 'element/' . $legacy . '/' . strtolower($action) . ' -> ' . $relative;
        }
    }
}

/* duplicate_element intentionally supports these five element types only. */
foreach (array('Chunk', 'Snippet', 'Template', 'TemplateVar', 'Plugin') as $directory) {
    $relative = 'Element/' . $directory . '/Duplicate.php';
    ++$checked;
    if (!is_file($processorsRoot . '/' . $relative)) {
        $missing[] = 'dynamic duplicate -> ' . $relative;
    }
}

foreach (array('Get', 'Create', 'Update', 'Delete', 'Duplicate', 'Undelete', 'EmptyRecycleBin') as $action) {
    $relative = 'Resource/' . $action . '.php';
    ++$checked;
    if (!is_file($processorsRoot . '/' . $relative)) {
        $missing[] = 'resource/' . strtolower($action) . ' -> ' . $relative;
    }
}

if ($missing) {
    fwrite(STDERR, "MODX 3 processor compatibility check FAILED:\n - " . implode("\n - ", $missing) . "\n");
    exit(1);
}

echo "MODX 3 processor compatibility check passed: {$checked} processor files found.\n";
