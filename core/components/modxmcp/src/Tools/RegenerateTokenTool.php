<?php
namespace ModxMcp\Tools;

class RegenerateTokenTool implements ToolInterface
{
    public function name() { return 'regenerate_token'; }
    public function group() { return 'ops'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        try {
            $token = bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            throw new \ModxMCPClientException(
                'Cannot generate a cryptographically secure API token: ' . $e->getMessage()
            );
        }

        $modx = $context->modx();
        $class = $context->platform()->className('system_setting');
        $setting = $modx->getObject($class, array('key' => 'modxmcp.api_token'));
        if (!$setting) {
            $setting = $modx->newObject($class);
            $setting->fromArray(
                array(
                    'key' => 'modxmcp.api_token',
                    'namespace' => 'modxmcp',
                    'area' => 'modxmcp:main',
                    'xtype' => 'textfield',
                ),
                '',
                true,
                true
            );
        }
        $setting->set('value', $token);
        if (!$setting->save()) {
            throw new \ModxMCPClientException(
                'regenerate_token: could not save the new token.'
            );
        }

        $modx->reloadConfig();
        ElementMutationSupport::refreshCache($context);
        AuditSupport::log($context, $this->name(), 'system', array());
        return array('token' => $token);
    }
}
