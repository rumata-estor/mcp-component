<?php
namespace ModxMcp\Tools;

class AuditSupport
{
    public static function log($context, $action, $type, array $payload = array())
    {
        $modx = $context->modx();
        if (!(bool)$modx->getOption('modxmcp.audit_log', null, true)) { return; }

        $userId = $modx->user ? (int)$modx->user->get('id') : null;
        $modx->log(
            defined('modX::LOG_LEVEL_INFO') ? \modX::LOG_LEVEL_INFO : 1,
            sprintf(
                '[modxmcp] action=%s type=%s service_user=%s payload=%s',
                $action,
                $type,
                $userId === null ? 'unknown' : $userId,
                json_encode($payload, JSON_UNESCAPED_UNICODE)
            )
        );

        try {
            $dir = rtrim($modx->getOption('core_path'), '/') . '/components/modxmcp/logs';
            if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
            $entry = array(
                'ts' => date('c'),
                'action' => $action,
                'type' => $type,
                'user' => $userId,
                'payload' => $payload,
            );
            @file_put_contents(
                $dir . '/audit.log',
                json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n",
                FILE_APPEND | LOCK_EX
            );
        } catch (\Exception $e) {
            // Auditing must never break the mutation.
        }
    }
}
