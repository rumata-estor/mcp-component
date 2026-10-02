<?php
namespace ModxMcp\Platform;

interface PlatformInterface
{
    public function key();
    public function majorVersion();
    public function supports($modx);
    public function className($logicalName);
    public function processorTarget($processor);
    public function runProcessor($modx, $processor, array $properties = array(), array $options = array());
}
