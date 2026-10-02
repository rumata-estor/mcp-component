<?php
namespace ModxMcp\Tools;

class AclProcessorSupport
{
    public static function run($context, $processor, array $data, $isList)
    {
        $props = $data;
        unset($props['action'], $props['elementType'], $props['type']);
        return ProcessorSupport::run(
            $context,
            $processor,
            $props,
            $isList,
            array('core:default', 'core:user', 'core:access', 'core:policy', 'core:role')
        );
    }
}
