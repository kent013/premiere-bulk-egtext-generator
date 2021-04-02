<?php
namespace PremiereUtil;

use Dotenv\Dotenv;

class Config
{
    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->load();
    }
    public function __get($key)
    {
        $key = ltrim(strtoupper(preg_replace('/[A-Z]+/', '_\0', $key)), '_');
        return $_ENV[$key];
    }

    protected static $instance = null;
    public static function config() : Config{
        if(is_null(self::$instance)){
            self::$instance = new Config();
        }
        return self::$instance;
    }
}
