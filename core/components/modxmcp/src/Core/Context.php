<?php
namespace ModxMcp\Core;

class Context
{
    private $modx;
    private $platform;
    private $legacy;
    private $extras;

    public function __construct($modx, $platform, $legacy = null, $extras = null)
    {
        $this->modx = $modx;
        $this->platform = $platform;
        $this->legacy = $legacy;
        $this->extras = $extras;
    }

    public function modx() { return $this->modx; }
    public function platform() { return $this->platform; }
    public function legacy() { return $this->legacy; }
    public function extras() { return $this->extras; }

    public function connectorVersion()
    {
        if ($this->legacy !== null) {
            $class = get_class($this->legacy);
            $constant = $class . '::VERSION';
            if (defined($constant)) {
                return constant($constant);
            }
        }
        return null;
    }
}
