<?php

include(__DIR__ . '/../init.inc.php');

$ret = LawAPI::searchLawVer(['type' => '議案']);
foreach ($ret->lawver as $d) {
    $obj = API::query('law', '/lawver/' . urlencode($d->_id), 'DELETE', '');
    print_r($obj);
}
