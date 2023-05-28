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
} else if ($method == 'lawver') {
    json_output(LawAPI::searchLawVer($_GET));
} else if ($method == 'lawline') {
    $page = max($_GET['page'], 1);
    $cmd = [
        'query' => [
            'bool' => [
                'must' => [],
                'filter' => [],
            ],
        ],
        'sort' => ['順序' => 'asc'],
        'size' => 100,
        'from' => 100 * $page - 100,
    ];
    if ($_GET['law_id']) {
        $cmd['query']['bool']['must'][] = ['term' => ['法律代碼' => $_GET['law_id']]];
    }
    if ($_GET['ver']) {
        $cmd['query']['bool']['must'][] = ['term' => ['法律版本代碼' => $_GET['ver']]];
    }
    if ($_GET['lawline_id']) {
        $cmd['query']['bool']['must'][] = ['term' => ['法條代碼' => $_GET['lawline_id']]];
    }
    if ($_GET['q']) {
        $cmd['query']['bool']['must'][] = [
            'match_phrase' => [
                '內容' => $_GET['q'],
            ]
        ];
    }

    $obj = API::query('/lawline/_search', 'GET', json_encode($cmd));
    $records = new StdClass;
    $records->query = $cmd['query'];
    $records->page = $page;
    $records->total = $obj->hits->total;
    $records->total_page = ceil($obj->hits->total / 100);
    $records->lawline= [];
    $meets = array();
    foreach ($obj->hits->hits as $hit) {
        $record = $hit->_source;
        $records->lawline[] = $record;
    }
    json_output($records, JSON_UNESCAPED_UNICODE);
    exit;
} else {
    readfile(__DIR__ . '/notfound.html');
    exit;
}
