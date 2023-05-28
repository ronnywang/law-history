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
}

$uri = explode('?', $_SERVER['REQUEST_URI'])[0];
if ($uri == '/' or $uri == '') {
    include(__DIR__ . '/page/main.php');
    exit;
} else if (preg_match('#^/law/(.*)$#', $uri, $matches)) {
    Param::set('law_id', $matches[1]);
    include(__DIR__ . '/page/law.php');
    exit;
}
