<?php
namespace ModxMcp\Registry;

class MutationProcessorCatalog
{
    public static function specs()
    {
        return array(
            'create_user' => array('group' => 'access', 'processor' => 'security/user/create', 'route' => 'acl'),
            'update_user' => array('group' => 'access', 'processor' => 'security/user/update', 'route' => 'acl'),
            'delete_user' => array('group' => 'access', 'processor' => 'security/user/delete', 'route' => 'acl'),
            'create_user_group' => array('group' => 'access', 'processor' => 'security/group/create', 'route' => 'acl'),
            'update_user_group' => array('group' => 'access', 'processor' => 'security/group/update', 'route' => 'acl'),
            'delete_user_group' => array('group' => 'access', 'processor' => 'security/group/remove', 'route' => 'acl'),
            'add_user_to_group' => array('group' => 'access', 'processor' => 'security/group/user/create', 'route' => 'acl'),
            'update_group_member' => array('group' => 'access', 'processor' => 'security/group/user/update', 'route' => 'acl'),
            'remove_user_from_group' => array('group' => 'access', 'processor' => 'security/group/user/remove', 'route' => 'acl'),
            'create_role' => array('group' => 'access', 'processor' => 'security/role/create', 'route' => 'acl'),
            'update_role' => array('group' => 'access', 'processor' => 'security/role/update', 'route' => 'acl'),
            'delete_role' => array('group' => 'access', 'processor' => 'security/role/remove', 'route' => 'acl'),
            'create_access_policy' => array('group' => 'access', 'processor' => 'security/access/policy/create', 'route' => 'acl'),
            'update_access_policy' => array('group' => 'access', 'processor' => 'security/access/policy/update', 'route' => 'acl'),
            'delete_access_policy' => array('group' => 'access', 'processor' => 'security/access/policy/remove', 'route' => 'acl'),
            'create_access_policy_template' => array('group' => 'access', 'processor' => 'security/access/policy/template/create', 'route' => 'acl'),
            'update_access_policy_template' => array('group' => 'access', 'processor' => 'security/access/policy/template/update', 'route' => 'acl'),
            'delete_access_policy_template' => array('group' => 'access', 'processor' => 'security/access/policy/template/remove', 'route' => 'acl'),
            'create_resource_group' => array('group' => 'access', 'processor' => 'security/resourcegroup/create', 'route' => 'acl'),
            'update_resource_group' => array('group' => 'access', 'processor' => 'security/resourcegroup/update', 'route' => 'acl'),
            'delete_resource_group' => array('group' => 'access', 'processor' => 'security/resourcegroup/remove', 'route' => 'acl'),
            'assign_resource_to_group' => array('group' => 'access', 'processor' => 'security/resourcegroup/updateresourcesin', 'route' => 'acl'),
            'remove_resource_from_group' => array('group' => 'access', 'processor' => 'security/resourcegroup/removeresource', 'route' => 'acl'),
            'grant_context_access' => array('group' => 'access', 'processor' => 'security/access/usergroup/context/create', 'route' => 'acl'),
            'update_context_access' => array('group' => 'access', 'processor' => 'security/access/usergroup/context/update', 'route' => 'acl'),
            'revoke_context_access' => array('group' => 'access', 'processor' => 'security/access/usergroup/context/remove', 'route' => 'acl'),
            'grant_resourcegroup_access' => array('group' => 'access', 'processor' => 'security/access/usergroup/resourcegroup/create', 'route' => 'acl'),
            'update_resourcegroup_access' => array('group' => 'access', 'processor' => 'security/access/usergroup/resourcegroup/update', 'route' => 'acl'),
            'revoke_resourcegroup_access' => array('group' => 'access', 'processor' => 'security/access/usergroup/resourcegroup/remove', 'route' => 'acl'),

            'create_context' => array('group' => 'contexts', 'processor' => 'context/create', 'route' => 'context'),
            'update_context' => array('group' => 'contexts', 'processor' => 'context/update', 'route' => 'context'),
            'delete_context' => array('group' => 'contexts', 'processor' => 'context/remove', 'route' => 'context'),
            'create_context_setting' => array('group' => 'contexts', 'processor' => 'context/setting/create', 'route' => 'context'),
            'update_context_setting' => array('group' => 'contexts', 'processor' => 'context/setting/update', 'route' => 'context'),
            'delete_context_setting' => array('group' => 'contexts', 'processor' => 'context/setting/remove', 'route' => 'context'),

            'create_namespace' => array('group' => 'namespaces', 'processor' => 'workspace/namespace/create', 'route' => 'workspace'),
            'update_namespace' => array('group' => 'namespaces', 'processor' => 'workspace/namespace/update', 'route' => 'workspace'),
            'delete_namespace' => array('group' => 'namespaces', 'processor' => 'workspace/namespace/remove', 'route' => 'workspace'),
            'set_lexicon_entry' => array('group' => 'lexicon', 'processor' => 'workspace/lexicon/create', 'route' => 'workspace'),
            'revert_lexicon_entry' => array('group' => 'lexicon', 'processor' => 'workspace/lexicon/revert', 'route' => 'workspace'),
        );
    }
}
