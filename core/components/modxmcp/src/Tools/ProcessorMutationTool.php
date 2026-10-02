<?php
namespace ModxMcp\Tools;

class ProcessorMutationTool implements ToolInterface
{
    private $toolName;
    private $spec;

    public function __construct($toolName, array $spec)
    {
        $this->toolName = (string)$toolName;
        $this->spec = $spec;
    }

    public function name() { return $this->toolName; }
    public function group() { return $this->spec['group']; }
    public function isMutation() { return true; }
    public function supports($context)
    {
        return $context && $context->modx() && $context->platform();
    }

    public function execute($context, array $data)
    {
        $route = isset($this->spec['route']) ? $this->spec['route'] : '';
        $props = $data;
        unset($props['action'], $props['elementType']);

        if ($route === 'acl') {
            unset($props['type']);
            $this->prepareAcl($context, $props);
            $this->loadLexicons(
                $context,
                array('core:default', 'core:user', 'core:access', 'core:policy', 'core:role')
            );
        } elseif ($route === 'context') {
            $this->prepareContext($props);
            $this->loadLexicons(
                $context,
                array('core:default', 'core:context', 'core:setting')
            );
        } elseif ($route === 'workspace') {
            $this->loadLexicons($context, array('core:default', 'core:workspaces'));
        }

        $response = $context->platform()->runProcessor(
            $context->modx(),
            $this->spec['processor'],
            $props
        );
        if (!$response) {
            throw new \ModxMCPClientException($this->noResponseMessage());
        }
        if ($response->isError()) {
            throw new \ModxMCPClientException(ProcessorSupport::error($response));
        }

        if ($route === 'acl' && $context->modx()->getCacheManager()) {
            $context->modx()->getCacheManager()->flushPermissions();
        }

        AuditSupport::log(
            $context,
            $this->toolName,
            $route === '' ? $this->group() : $route,
            $this->auditPayload($props)
        );
        return ProcessorSupport::normalize($response);
    }

    private function prepareContext(array &$props)
    {
        if (in_array(
            $this->toolName,
            array('create_context_setting', 'update_context_setting'),
            true
        )) {
            if (!isset($props['fk']) && isset($props['context_key'])) {
                $props['fk'] = $props['context_key'];
            }
            if (!isset($props['namespace'])) { $props['namespace'] = 'core'; }
        }
    }

    private function prepareAcl($context, array &$props)
    {
        if ($this->toolName === 'assign_resource_to_group') {
            if (isset($props['resource']) && ctype_digit((string)$props['resource'])) {
                $props['resource'] = 'n_' . $props['resource'];
            }
            if (isset($props['resourceGroup']) && ctype_digit((string)$props['resourceGroup'])) {
                $props['resourceGroup'] = 'n_' . $props['resourceGroup'];
            }
        }

        if ($this->toolName === 'create_user') {
            if (!empty($props['password'])) {
                if (empty($props['passwordgenmethod'])) { $props['passwordgenmethod'] = 'spec'; }
                if (!isset($props['specifiedpassword'])) { $props['specifiedpassword'] = $props['password']; }
                if (!isset($props['confirmpassword'])) { $props['confirmpassword'] = $props['password']; }
                unset($props['password']);
            } elseif (empty($props['specifiedpassword'])) {
                if (empty($props['passwordgenmethod'])) { $props['passwordgenmethod'] = 'g'; }
            }
            if (empty($props['passwordnotifymethod'])) {
                $props['passwordnotifymethod'] = 's';
            }
        }

        if (in_array(
            $this->toolName,
            array(
                'grant_context_access',
                'update_context_access',
                'grant_resourcegroup_access',
                'update_resourcegroup_access',
            ),
            true
        ) && empty($props['principal_class'])) {
            $props['principal_class'] = $context->platform()->className('user_group');
        }
    }

    private function loadLexicons($context, array $lexicons)
    {
        if (!empty($lexicons)) {
            call_user_func_array(array($context->modx()->lexicon, 'load'), $lexicons);
        }
    }

    private function auditPayload(array $props)
    {
        $keys = array(
            'id', 'key', 'context_key', 'name', 'namespace', 'topic', 'language',
            'usergroup', 'user', 'target', 'principal', 'resource', 'resourceGroup'
        );
        return array_intersect_key($props, array_flip($keys));
    }

    private function noResponseMessage()
    {
        $route = isset($this->spec['route']) ? $this->spec['route'] : '';
        if ($route === 'context') {
            return 'Context processor not found or returned nothing: ' . $this->spec['processor'];
        }
        if ($route === 'acl') {
            return 'ACL processor not found or returned nothing: ' . $this->spec['processor'];
        }
        if ($route === 'workspace') {
            return 'Workspace processor not found: ' . $this->spec['processor'];
        }
        return 'Processor not found or returned nothing: ' . $this->spec['processor'];
    }
}
