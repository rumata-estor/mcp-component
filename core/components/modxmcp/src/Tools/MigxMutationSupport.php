<?php
namespace ModxMcp\Tools;

class MigxMutationSupport
{
    public static function fields()
    {
        return array(
            'name', 'formtabs', 'contextmenus', 'actionbuttons',
            'columnbuttons', 'filters', 'extended', 'permissions',
            'fieldpermissions', 'columns', 'category', 'published',
        );
    }

    public static function refresh($context)
    {
        $manager = $context->modx()->getCacheManager();
        if ($manager) { $manager->refresh(); }
    }
}
