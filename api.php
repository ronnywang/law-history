<?php

include(__DIR__ . '/init.inc.php');
$uri = explode('?', $_SERVER['REQUEST_URI'])[0];

if (!preg_match('#/api/([^/]+)(.*)#', $uri, $matches)) {
    readfile(__DIR__ . '/notfound.html');
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
    $page = @max($_GET['page'], 1);
    $limit = @intval($_GET['limit']) ?: 100;
    $cmd = [
        'query' => [
            'bool' => [
                'must' => [],
                'filter' => [],
            ],
        ],
        'size' => $limit,
        'from' => $limit * $page - $limit,
    ];
    if ($_GET['q']) {
        $cmd['query']['bool']['should'][] = [
            'match_phrase' => [
                '最新名稱' => $_GET['q'],
            ]
        ];
        $cmd['query']['bool']['should'][] = [
            'match_phrase' => [
                '其他名稱' => $_GET['q'],
            ]
        ];
    }
    try {
        $obj = API::query('/law/_search', 'GET', json_encode($cmd));
    } catch (Exception $e) {
        echo $e->getMessage();
        exit;
    }

    $records = array();
    $ret = new StdClass;
    $ret->total = $obj->hits->total;
    $ret->limit = $limit;
    $ret->totalpage = ceil($ret->total / $ret->limit);
    $ret->page = $page;
    foreach ($obj->hits->hits as $hit) {
        $record = $hit->_source;
        $records[] = $record;
    }
    $ret->data = $records;
    json_output($ret);
    exit;
} else if ($method == 'lawver') {
    $page = max($_GET['page'], 1);
    $cmd = [
        'sort' => ['日期' => 'asc'],
        'size' => 100,
        'from' => 100 * $page - 100,
    ];
    if ($_GET['law_id']) {
        $cmd['query']['term'] = ['法律代碼' => $_GET['law_id']];
    }
    $obj = API::query('/lawver/_search', 'GET', json_encode($cmd));
    $records = new StdClass;
    $records->page = $page;
    $records->total = $obj->hits->total;
    $records->total_page = ceil($obj->hits->total / 100);
    $records->lawver= [];
    $meets = array();
    foreach ($obj->hits->hits as $hit) {
        $record = $hit->_source;
        $records->lawver[] = $record;
    }
    json_output($records, JSON_UNESCAPED_UNICODE);
    exit;
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
