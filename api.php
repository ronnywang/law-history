<?php

include(__DIR__ . '/init.inc.php');
$uri = explode('?', $_SERVER['REQUEST_URI'])[0];

if (!preg_match('#/api/([^/]+)(.*)#', $uri, $matches)) {
    include(__DIR__ . '/web.php');
    exit;
}
$method = strtolower($matches[1]);
$matches[2] = ltrim($matches[2], '/');
$params = [];
if ($matches[2]) {
    $params = array_map('urldecode', explode('/', $matches[2]));
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
function json_output($obj) {
    echo json_encode($obj, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method == 'stat') {
    $cmd = [
        'aggs' => [
            'total_count' => [
                'value_count' => ['field' => '法律代碼'],
            ],
        ],
        'size' => 0,
    ];
    $obj = API::query('/law/_search', 'GET', json_encode($cmd));
    json_output($obj, JSON_UNESCAPED_UNICODE);
    exit;
} elseif ($method == 'law') {
    json_output(LawAPI::searchLaw($_GET));
} elseif ($method == 'bill') {
    json_output(BillAPI::searchBill($_GET));
} else if ($method == 'lawver') {
    json_output(LawAPI::searchLawVer($_GET));
} else if ($method == 'lawline') {
    json_output(LawAPI::searchLawLine($_GET));
} else if ($method == 'billdata') {
    json_output(BillAPI::getBillData($_GET['billNo']));
} else if ($method == 'billhtml') {
    echo (BillAPI::getBillHTML($_GET['billNo']));
} else if ($method == 'billidmap') {
    json_output(BillAPI::searchBillIDMap($_GET));
} else {
    readfile(__DIR__ . '/notfound.html');
    exit;
}
