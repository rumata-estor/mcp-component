<?php

use MODX\Revolution\modX;
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.core.php';
require_once MODX_CORE_PATH . 'vendor/autoload.php';

if (!class_exists('ModxMCPClientException')) {
    /** Expected/validation error whose message is safe to return to the client. */
    class ModxMCPClientException extends Exception {}
}

$modx = modX::getInstance();
$modx->initialize('mgr'); 
$modx->setLogLevel(modX::LOG_LEVEL_ERROR);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$clientIp = isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';

$ipMatchesRule = static function ($ip, $rule) {
    $ip = trim((string) $ip);
    $rule = trim((string) $rule);
    if ($ip === '' || $rule === '') {
        return false;
    }

    if (strpos($rule, '/') === false) {
        $ipBinary = @inet_pton($ip);
        $ruleBinary = @inet_pton($rule);
        return $ipBinary !== false && $ruleBinary !== false && hash_equals($ruleBinary, $ipBinary);
    }

    list($subnet, $bitsRaw) = array_pad(explode('/', $rule, 2), 2, '');
    if ($bitsRaw === '' || !ctype_digit($bitsRaw)) {
        return false;
    }

    $ipBinary = @inet_pton($ip);
    $subnetBinary = @inet_pton(trim($subnet));
    if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
        return false;
    }

    $bits = (int) $bitsRaw;
    $maxBits = strlen($ipBinary) * 8;
    if ($bits < 0 || $bits > $maxBits) {
        return false;
    }

    $fullBytes = intdiv($bits, 8);
    $remainingBits = $bits % 8;
    if ($fullBytes > 0 && !hash_equals(substr($subnetBinary, 0, $fullBytes), substr($ipBinary, 0, $fullBytes))) {
        return false;
    }
    if ($remainingBits === 0) {
        return true;
    }

    $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
    return (ord($subnetBinary[$fullBytes]) & $mask) === (ord($ipBinary[$fullBytes]) & $mask);
};

$ipMatchesRules = static function ($ip, $csv) use ($ipMatchesRule) {
    foreach (explode(',', (string) $csv) as $rule) {
        if ($ipMatchesRule($ip, $rule)) {
            return true;
        }
    }
    return false;
};

// Lightweight unauthenticated health/version probe (GET) for client/server skew detection.
// Returns only non-sensitive info: component name, server build version, enabled flag.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $version = 'unknown';
    $corePath = $modx->getOption('modxmcp.core_path', null, $modx->getOption('core_path') . 'components/modxmcp/');
    $corePath = str_replace(
        array('{core_path}', '[[++core_path]]'),
        rtrim((string) $modx->getOption('core_path'), '/\\') . DIRECTORY_SEPARATOR,
        (string) $corePath
    );
    $modelFile = $corePath . 'model/modxmcp.class.php';
    if (file_exists($modelFile)) {
        require_once $modelFile;
        if (defined('modxMCP::VERSION')) { $version = modxMCP::VERSION; }
    }
    echo json_encode([
        'component' => 'modxMCP',
        'version'   => $version,
        'variant'   => (defined('modxMCP::VARIANT') ? modxMCP::VARIANT : 'unknown'),
        'enabled'   => (bool) $modx->getOption('modxmcp.enabled', null, false),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$isEnabled = $modx->getOption('modxmcp.enabled', null, false);
if (!$isEnabled) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'modxMCP is disabled.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// HTTPS enforcement. Direct HTTPS is trusted. X-Forwarded-Proto is trusted only
// when REMOTE_ADDR belongs to modxmcp.trusted_proxy_ips; otherwise any client could spoof it.
if ((bool) $modx->getOption('modxmcp.require_https', null, false)) {
    $directHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

    $forwardedHttps = false;
    $trustedProxyIps = trim((string) $modx->getOption('modxmcp.trusted_proxy_ips', null, ''));
    if (!$directHttps && $trustedProxyIps !== '' && $ipMatchesRules($clientIp, $trustedProxyIps)) {
        $forwardedProto = isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            ? strtolower(trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]))
            : '';
        $forwardedHttps = ($forwardedProto === 'https');
    }

    if (!$directHttps && !$forwardedHttps) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'HTTPS required (modxmcp.require_https).'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Optional client-IP allowlist (modxmcp.allowed_ips). Empty = allow all. Supports
// exact IPv4/IPv6 addresses and CIDR ranges. It intentionally matches REMOTE_ADDR,
// not X-Forwarded-For, so an untrusted client cannot spoof the source address.
$allowedIps = trim((string) $modx->getOption('modxmcp.allowed_ips', null, ''));
if ($allowedIps !== '' && !$ipMatchesRules($clientIp, $allowedIps)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden: client IP is not allowed (modxmcp.allowed_ips).'], JSON_UNESCAPED_UNICODE);
    exit;
}

$expectedToken = $modx->getOption('modxmcp.api_token', null, '');
$headers =[];
if (function_exists('getallheaders')) {
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
}

$receivedToken = '';
if (isset($headers['x-mcp-token'])) $receivedToken = trim($headers['x-mcp-token']);
elseif (isset($_SERVER['HTTP_X_MCP_TOKEN'])) $receivedToken = trim($_SERVER['HTTP_X_MCP_TOKEN']);

if (empty($expectedToken) || !hash_equals($expectedToken, $receivedToken)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$maxPayloadBytes = (int)$modx->getOption('modxmcp.max_payload_bytes', null, 1024 * 1024);
$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
if ($maxPayloadBytes > 0 && $contentLength > $maxPayloadBytes) {
    http_response_code(413);
    echo json_encode([
        'success' => false,
        'error' => 'Payload Too Large',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawInput = file_get_contents('php://input');
if ($rawInput === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Bad Request: request body is unreadable.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($maxPayloadBytes > 0 && strlen($rawInput) > $maxPayloadBytes) {
    http_response_code(413);
    echo json_encode([
        'success' => false,
        'error' => 'Payload Too Large',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'Invalid JSON encoding. Ensure your payload is strictly UTF-8 encoded.',
        'json_error' => json_last_error_msg()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$input || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Bad Request: Missing action.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $input['action'];
$type   = isset($input['type']) ? $input['type'] : '';
$data   = isset($input['data']) ? $input['data'] : [];

if (isset($input['name'])) $data['name'] = $input['name'];
if (isset($input['content'])) $data['content'] = $input['content'];
if (isset($input['id'])) $data['id'] = $input['id'];

try {
    $corePath = $modx->getOption('modxmcp.core_path', null, $modx->getOption('core_path') . 'components/modxmcp/');
    $corePath = str_replace(
        array('{core_path}', '[[++core_path]]'),
        rtrim((string) $modx->getOption('core_path'), '/\\') . DIRECTORY_SEPARATOR,
        (string) $corePath
    );
    require_once $corePath . 'model/modxmcp.class.php';
    
    $mcp = new modxMCP($modx);
    $result = $mcp->processRequest($action, $type, $data);

    // 'caps' = capability fingerprint; the client watches it to live-refresh its tool list.
    $caps = (string) $modx->getOption('modxmcp.disabled_groups', null, '');
    echo json_encode(['success' => true, 'data' => $result, 'caps' => $caps], JSON_UNESCAPED_UNICODE);
} catch (ModxMCPClientException $e) {
    // Expected, actionable error (validation, "not installed", "disabled", "not found").
    http_response_code(400);
    $caps = (string) $modx->getOption('modxmcp.disabled_groups', null, '');
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'caps' => $caps], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $errorId = uniqid('modxmcp_', true);
    $modx->log(
        modX::LOG_LEVEL_ERROR,
        sprintf(
            '[%s] MCP request failed. action=%s type=%s message=%s',
            $errorId,
            $action,
            $type,
            $e->getMessage()
        )
    );
    http_response_code(500);
    $debug = (bool)$modx->getOption('modxmcp.debug', null, false);
    $response = [
        'success' => false,
        'error' => 'Internal Server Error',
        'error_id' => $errorId,
    ];
    if ($debug) {
        $response['details'] = $e->getMessage();
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $errorId = uniqid('modxmcp_', true);
    $modx->log(
        modX::LOG_LEVEL_ERROR,
        sprintf(
            '[%s] MCP request failed. action=%s type=%s throwable=%s',
            $errorId,
            $action,
            $type,
            $e->getMessage()
        )
    );
    http_response_code(500);
    $debug = (bool)$modx->getOption('modxmcp.debug', null, false);
    $response = [
        'success' => false,
        'error' => 'Internal Server Error',
        'error_id' => $errorId,
    ];
    if ($debug) {
        $response['details'] = $e->getMessage();
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
