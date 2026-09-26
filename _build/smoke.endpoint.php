<?php

use MODX\Revolution\modChunk;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modX;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("MODX3 MCP endpoint smoke test is CLI-only.\n");
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

require_once $config;
require_once MODX_CORE_PATH . 'vendor/autoload.php';

$modx = modX::getInstance();
$modx->initialize('mgr');

$settingsHashOnly = in_array('--settings-hash', $argv, true);
$readOnly = in_array('--read-only', $argv, true);

$settings = array();
$query = $modx->newQuery(modSystemSetting::class);
$query->where(array('namespace' => 'modxmcp'));
$query->sortby('key', 'ASC');
foreach ($modx->getCollection(modSystemSetting::class, $query) as $setting) {
    $settings[(string)$setting->get('key')] = (string)$setting->get('value');
}
if (count($settings) !== 16) {
    fwrite(STDERR, "Expected 16 modxmcp settings, found " . count($settings) . ".\n");
    exit(3);
}

if ($settingsHashOnly) {
    echo 'SETTINGS_HASH=' . hash('sha256', json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . PHP_EOL;
    exit(0);
}

$token = isset($settings['modxmcp.api_token']) ? trim($settings['modxmcp.api_token']) : '';
if ($token === '') {
    fwrite(STDERR, "modxmcp.api_token is empty.\n");
    exit(4);
}

$siteUrl = rtrim((string)$modx->getOption('site_url'), '/');
if (!preg_match('~^https?://~i', $siteUrl)) {
    fwrite(STDERR, "MODX site_url is not an absolute HTTP(S) URL: {$siteUrl}\n");
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
$content1 = 'MODX3 MCP smoke v1 ' . bin2hex(random_bytes(8));
$content2 = 'MODX3 MCP smoke v2 ' . bin2hex(random_bytes(8));

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