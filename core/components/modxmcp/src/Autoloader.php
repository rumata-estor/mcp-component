<?php
namespace ModxMcp;

class Autoloader
{
    public static function register($basePath)
    {
        $basePath = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR;
        spl_autoload_register(function ($class) use ($basePath) {
            $prefix = 'ModxMcp\\';
            if (strpos($class, $prefix) !== 0) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file = $basePath . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}
