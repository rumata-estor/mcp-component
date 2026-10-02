<?php

use MODX\Revolution\modChunk;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modX;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("MODX MCP endpoint smoke test is CLI-only.\n");
}

set_time_limit(0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

require_once __DIR__ . '/build.config.php';

$config = getenv('MODX_CONFIG_CORE');
if (!$config || !is_file($config)) {
    $dir = __DIR__;
    for ($i = 0; $i < 12; $i++) {
        $candidate = $dir . DIRECTORY_SEPARATOR . 'config.core.php';
        if (is_file($candidate)) {
            $config = $candidate;
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }
}
if (!$config || !is_file($config)) {
    fwrite(STDERR, "config.core.php not found; set MODX_CONFIG_CORE.\n");
    exit(2);
}


// Some MODX installations derive paths from $_SERVER['DOCUMENT_ROOT'] even in
// config.core.php. CLI normally leaves it empty. Support an explicit root and
// otherwise infer the MODX web root from config.core.php.
$documentRoot = trim((string)getenv('MODX_DOCUMENT_ROOT'));
if ($documentRoot !== '') {
    $documentRoot = rtrim($documentRoot, '/\\');
    if (!is_file($documentRoot . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
        fwrite(STDERR, "MODX_DOCUMENT_ROOT does not look like a MODX web root: {$documentRoot}\n");
        exit(2);
    }
    $_SERVER['DOCUMENT_ROOT'] = $documentRoot;
} elseif (PHP_SAPI === 'cli' && empty($_SERVER['DOCUMENT_ROOT'])) {
    $probe = dirname((string)(realpath($config) ?: $config));
    for ($i = 0; $i < 12; $i++) {
        $autoload = $probe . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (is_file($autoload)) {
            $_SERVER['DOCUMENT_ROOT'] = rtrim($probe, '/\\');
            break;
        }
        $parent = dirname($probe);
        if ($parent === $probe) {
            break;
        }
        $probe = $parent;
    }
}

require_once $config;
if (!defined('MODX_CORE_PATH') || !is_file(rtrim(MODX_CORE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
    fwrite(STDERR, "MODX bootstrap failed: MODX_CORE_PATH/vendor/autoload.php not found. Set MODX_DOCUMENT_ROOT when config.core.php depends on DOCUMENT_ROOT.\n");
    exit(2);
}
require_once rtrim(MODX_CORE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$modx = modX::getInstance();
$modx->initialize('mgr');

$settingsHashOnly = in_array('--settings-hash', $argv, true);
$readOnly = in_array('--read-only', $argv, true);
$settingsExport = '';
$settingsRestore = '';
$settingsCompare = '';
foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--settings-export=') === 0) {
        $settingsExport = substr($arg, strlen('--settings-export='));
    } elseif (strpos($arg, '--settings-restore=') === 0) {
        $settingsRestore = substr($arg, strlen('--settings-restore='));
    } elseif (strpos($arg, '--settings-compare=') === 0) {
        $settingsCompare = substr($arg, strlen('--settings-compare='));
    }
}

$settings = array();
foreach ($modx->getCollection(modSystemSetting::class, array('namespace' => 'modxmcp')) as $setting) {
    $settings[(string)$setting->get('key')] = (string)$setting->get('value');
}
ksort($settings, SORT_STRING);

if ($settingsExport !== '') {
    if (count($settings) > 16) {
        fwrite(STDERR, "Refusing settings snapshot with unexpected extra modxmcp settings: found " . count($settings) . ".\n");
        exit(3);
    }
    foreach (array_keys($settings) as $key) {
        if (strpos((string)$key, 'modxmcp.') !== 0) {
            fwrite(STDERR, "Unexpected setting key in snapshot source.\n");
            exit(3);
        }
    }
    $payload = json_encode(array(
        'format' => 'modx3mcp-settings-v1',
        'settings' => $settings,
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false || file_put_contents($settingsExport, $payload, LOCK_EX) === false) {
        fwrite(STDERR, "Could not write settings snapshot.\n");
        exit(3);
    }
    @chmod($settingsExport, 0600);
    echo 'SETTINGS_SNAPSHOT_OK count=' . count($settings) . PHP_EOL;
    exit(0);
}

if ($settingsRestore !== '') {
    $raw = @file_get_contents($settingsRestore);
    $snapshot = $raw !== false ? json_decode($raw, true) : null;
    if (!is_array($snapshot) || ($snapshot['format'] ?? '') !== 'modx3mcp-settings-v1' || !isset($snapshot['settings']) || !is_array($snapshot['settings'])) {
        fwrite(STDERR, "Invalid settings snapshot.\n");
        exit(3);
    }
    $restore = $snapshot['settings'];
    if (count($restore) === 0) {
        echo "SETTINGS_RESTORE_SKIP empty-snapshot\n";
        exit(0);
    }
    if (count($restore) > 16) {
        fwrite(STDERR, "Invalid settings snapshot count: " . count($restore) . ".\n");
        exit(3);
    }

    $modx->beginTransaction();
    try {
        foreach ($restore as $key => $value) {
            if (strpos((string)$key, 'modxmcp.') !== 0) {
                throw new RuntimeException("Unexpected setting key in snapshot.");
            }
            $setting = $modx->getObject(modSystemSetting::class, array('key' => $key, 'namespace' => 'modxmcp'));
            if (!$setting) {
                throw new RuntimeException("Setting missing during restore: {$key}");
            }
            $setting->set('value', (string)$value);
            if (!$setting->save()) {
                throw new RuntimeException("Could not restore setting: {$key}");
            }
        }
        if ($modx->commit() === false) {
            throw new RuntimeException('Settings restore transaction commit failed.');
        }
    } catch (Throwable $e) {
        $modx->rollback();
        fwrite(STDERR, "Settings restore failed: " . $e->getMessage() . "\n");
        exit(3);
    }
    if ($modx->getCacheManager()) {
        $modx->getCacheManager()->refresh();
    }
    echo 'SETTINGS_RESTORE_OK count=' . count($restore) . PHP_EOL;
    exit(0);
}

if ($settingsCompare !== '') {
    $raw = @file_get_contents($settingsCompare);
    $snapshot = $raw !== false ? json_decode($raw, true) : null;
    if (!is_array($snapshot) || ($snapshot['format'] ?? '') !== 'modx3mcp-settings-v1' || !isset($snapshot['settings']) || !is_array($snapshot['settings'])) {
        fwrite(STDERR, "Invalid settings snapshot for comparison.\n");
        exit(3);
    }
    $mismatch = array();
    foreach ($snapshot['settings'] as $key => $value) {
        if (!array_key_exists($key, $settings) || (string)$settings[$key] !== (string)$value) {
            $mismatch[] = (string)$key;
        }
    }
    if (!empty($mismatch)) {
        fwrite(STDERR, "SETTINGS_COMPARE_FAILED keys=" . implode(',', $mismatch) . "\n");
        exit(3);
    }
    echo 'SETTINGS_COMPARE_OK count=' . count($snapshot['settings']) . PHP_EOL;
    exit(0);
}

if ($settingsHashOnly) {
    if (count($settings) > 16) {
        fwrite(STDERR, "Unexpected modxmcp settings count: " . count($settings) . ".\n");
        exit(3);
    }
    echo 'SETTINGS_HASH=' . hash('sha256', json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . PHP_EOL;
    exit(0);
}

if (count($settings) !== 16) {
    fwrite(STDERR, "Expected 16 modxmcp settings, found " . count($settings) . ".\n");
    exit(3);
}

$token = isset($settings['modxmcp.api_token']) ? trim($settings['modxmcp.api_token']) : '';
if ($token === '') {
    fwrite(STDERR, "modxmcp.api_token is empty.\n");
    exit(4);
}

$siteUrl = trim((string)getenv('MODX_MCP_SMOKE_SITE_URL'));
if ($siteUrl === '') {
    $siteUrl = (string)$modx->getOption('site_url');
}
$siteUrl = rtrim($siteUrl, '/');
if (!preg_match('~^https?://~i', $siteUrl)) {
    fwrite(STDERR, "Smoke-test site URL is not an absolute HTTP(S) URL: {$siteUrl}\n");
    exit(5);
}
$endpoint = $siteUrl . '/assets/components/modxmcp/api.php';

function smokeHttpRequest($method, $url, $token = '', $payload = null) {
    $headers = array('Accept: application/json');
    $body = null;
    if ($token !== '') {
        $headers[] = 'X-MCP-Token: ' . $token;
    }
    if ($payload !== null) {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('Could not encode request JSON.');
        }
        $headers[] = 'Content-Type: application/json';
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $message = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('HTTP request failed: ' . $message);
        }
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return array($status, $responseBody);
    }

    $options = array(
        'http' => array(
            'method' => $method,
            'header' => implode("\r\n", $headers) . "\r\n",
            'ignore_errors' => true,
            'timeout' => 20,
        ),
    );
    if ($body !== null) {
        $options['http']['content'] = $body;
    }
    $context = stream_context_create($options);
    $responseBody = @file_get_contents($url, false, $context);
    if ($responseBody === false) {
        throw new RuntimeException('HTTP request failed and ext-curl is unavailable.');
    }
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('~\s(\d{3})\s~', $http_response_header[0], $m)) {
        $status = (int)$m[1];
    }
    return array($status, $responseBody);
}

function smokeDecode($status, $body, $label) {
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException("{$label}: non-JSON response (HTTP {$status}).");
    }
    if ($status < 200 || $status >= 300) {
        $message = isset($decoded['error']) ? $decoded['error'] : 'HTTP error';
        throw new RuntimeException("{$label}: HTTP {$status}: {$message}");
    }
    return $decoded;
}

function smokePost($endpoint, $token, $action, $type = '', array $data = array()) {
    $payload = array('action' => $action, 'data' => $data);
    if ($type !== '') {
        $payload['type'] = $type;
    }
    list($status, $body) = smokeHttpRequest('POST', $endpoint, $token, $payload);
    $decoded = smokeDecode($status, $body, $action);
    if (empty($decoded['success'])) {
        $message = isset($decoded['error']) ? $decoded['error'] : 'unknown MCP error';
        throw new RuntimeException("{$action}: {$message}");
    }
    return isset($decoded['data']) ? $decoded['data'] : null;
}

list($healthStatus, $healthBody) = smokeHttpRequest('GET', $endpoint);
$health = smokeDecode($healthStatus, $healthBody, 'health');
if (($health['version'] ?? '') !== PKG_VERSION || ($health['variant'] ?? '') !== 'modx3') {
    throw new RuntimeException(
        'health: version/variant mismatch: ' .
        json_encode(array('version' => $health['version'] ?? null, 'variant' => $health['variant'] ?? null))
    );
}
if (empty($health['enabled'])) {
    throw new RuntimeException('health: component reports enabled=false.');
}
echo "HEALTH_OK version=" . PKG_VERSION . " variant=modx3\n";

$actions = smokePost($endpoint, $token, 'list_actions');
$actionCount = 0;
if (is_array($actions)) {
    foreach ($actions as $groupActions) {
        if (is_array($groupActions)) {
            $actionCount += count($groupActions);
        }
    }
}
if ($actionCount !== 182) {
    throw new RuntimeException("list_actions: expected 182 actions, got {$actionCount}.");
}
echo "ACTIONS_OK count={$actionCount}\n";

$systemInfo = smokePost($endpoint, $token, 'system_info');
if (!is_array($systemInfo)) {
    throw new RuntimeException('system_info: unexpected response shape.');
}
echo "SYSTEM_INFO_OK\n";

if ($readOnly) {
    echo "MCP_ENDPOINT_READ_ONLY_SMOKE_OK\n";
    exit(0);
}

$smokeName = '__modx3mcp_smoke_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4));
$createdId = 0;
$content1 = 'MODX MCP smoke v1 ' . bin2hex(random_bytes(8));
$content2 = 'MODX MCP smoke v2 ' . bin2hex(random_bytes(8));

try {
    $created = smokePost($endpoint, $token, 'create_element', 'chunk', array(
        'name' => $smokeName,
        'content' => $content1,
    ));
    $createdId = is_array($created) && isset($created['id']) ? (int)$created['id'] : 0;
    if ($createdId <= 0) {
        throw new RuntimeException('create_element: chunk ID missing.');
    }

    $fetched = smokePost($endpoint, $token, 'get_element', 'chunk', array('id' => $createdId));
    $fetchedContent = is_array($fetched)
        ? (isset($fetched['snippet']) ? $fetched['snippet'] : ($fetched['content'] ?? null))
        : null;
    if ($fetchedContent !== $content1) {
        throw new RuntimeException('get_element: created chunk content mismatch.');
    }

    smokePost($endpoint, $token, 'update_element', 'chunk', array(
        'id' => $createdId,
        'content' => $content2,
    ));
    $updated = smokePost($endpoint, $token, 'get_element', 'chunk', array('id' => $createdId));
    $updatedContent = is_array($updated)
        ? (isset($updated['snippet']) ? $updated['snippet'] : ($updated['content'] ?? null))
        : null;
    if ($updatedContent !== $content2) {
        throw new RuntimeException('update_element: updated chunk content mismatch.');
    }

    $preview = smokePost($endpoint, $token, 'delete_element', 'chunk', array(
        'id' => $createdId,
        'dry_run' => true,
    ));
    if (!is_array($preview)) {
        throw new RuntimeException('delete_element dry_run: unexpected response shape.');
    }

    smokePost($endpoint, $token, 'delete_element', 'chunk', array('id' => $createdId));
    $createdId = 0;

    if ($modx->getCount(modChunk::class, array('name' => $smokeName)) !== 0) {
        throw new RuntimeException('delete_element: smoke chunk still exists in MODX.');
    }
    echo "MCP_CRUD_SMOKE_OK\n";
} finally {
    $leftover = $modx->getObject(modChunk::class, array('name' => $smokeName));
    if ($leftover) {
        $leftover->remove();
        if ($modx->getCacheManager()) {
            $modx->getCacheManager()->refresh();
        }
        fwrite(STDERR, "Smoke cleanup removed leftover chunk {$smokeName}.\n");
    }
}

echo "MCP_ENDPOINT_SMOKE_OK\n";