<?php
namespace ModxMcp\Extras;

interface ExtraInterface
{
    public function key();
    public function supports($context);
    public function register($registry, $context);
}
