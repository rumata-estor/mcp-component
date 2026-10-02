<?php
namespace ModxMcp\Legacy;

/**
 * Temporary bridge: lets new Tool classes delegate to the proven monolith while
 * actions are migrated one by one. The monolith remains authoritative until a
 * tool is explicitly moved.
 */
class LegacyActionAdapter
{
    private $legacy;

    public function __construct($legacy)
    {
        $this->legacy = $legacy;
    }

    public function invoke($action, array $arguments = array())
    {
        if (!method_exists($this->legacy, 'process')) {
            throw new \RuntimeException('Legacy MODX MCP object has no process() method.');
        }
        return $this->legacy->process($action, $arguments);
    }
}
