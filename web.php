<?php

$uri = explode('?', $_SERVER['REQUEST_URI'])[0];
if ($uri == '/' or $uri == '') {
    include(__DIR__ . '/page/main.php');
    exit;
}
