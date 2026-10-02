<?php
namespace ModxMcp\Tools;

interface ToolInterface
{
    public function name();
    public function group();
    public function isMutation();
    public function supports($context);
    public function execute($context, array $arguments);
}
