<?php
namespace ModxMcp\Registry;

use ModxMcp\Tools\ToolInterface;

class ToolRegistry
{
    private $tools = array();

    public function register(ToolInterface $tool)
    {
        $name = $tool->name();
        if (isset($this->tools[$name])) {
            throw new \RuntimeException('Duplicate tool: ' . $name);
        }
        $this->tools[$name] = $tool;
        return $this;
    }

    public function get($name)
    {
        return isset($this->tools[$name]) ? $this->tools[$name] : null;
    }

    public function all()
    {
        return $this->tools;
    }

    public function available($context)
    {
        $out = array();
        foreach ($this->tools as $name => $tool) {
            if ($tool->supports($context)) {
                $out[$name] = $tool;
            }
        }
        return $out;
    }
}
