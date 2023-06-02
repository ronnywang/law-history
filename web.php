<?php

class Param
{
    protected static $values = null;
    public static function set($k, $v)
    {
        self::$values[$k] = $v;
    }

    public static function get($k)
    {
        return self::$values[$k];
    }

    protected static $_apis = null;
    public static function addAPI($url, $reason)
    {
        if (is_null(self::$_apis)) {
            self::$_apis = [];
        }
        self::$_apis[] = [$url, $reason];
    }

    public static function getAPIs()
    {
        return self::$_apis;
    }
}

$uri = explode('?', $_SERVER['REQUEST_URI'])[0];
if ($uri == '/' or $uri == '') {
    include(__DIR__ . '/page/main.php');
    exit;
} else if (preg_match('#^/law/([^/]*)(/(.*))?$#', $uri, $matches)) {
    Param::set('law_id', $matches[1]);
    if ($matches[2]) {
        Param::set('ver', urldecode($matches[3]));
    }
    include(__DIR__ . '/page/law.php');
    exit;
}
