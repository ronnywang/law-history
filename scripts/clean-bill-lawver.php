<?php

include(__DIR__ . '/../init.inc.php');

$ret = LawAPI::searchLawVer(['type' => '議案']);
foreach ($ret->lawver as $d) {
    $obj = API::query('law', '/lawver/' . urlencode($d->_id), 'DELETE', '');
    print_r($obj);
}

$ret = API::query('law', '/lawline/_search', 'POST', json_encode([
    'query' => ['prefix' => ['法律版本代碼' => 'bill-']],
    'size' => 100,
]));
foreach ($ret->hits->hits as $hit) {
    $obj = API::query('law', '/lawline/' . urlencode($hit->_id), 'DELETE', '');
    print_r($obj);
}
$ret = API::query('law', '/_flush', 'POST', '');
