<?php

class LawAPI
{
    public static function getAPIURL($prefix, $params)
    {
        if (!$params) {
            return $prefix;
        }
        $terms = [];
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $e) {
                    $terms[] = urlencode($k) . '[]=' . urlencode($e);
                }
            } else {
                $terms[] = urlencode($k) . '=' . urlencode($v);
            }
        }
        return $prefix . '?' . implode('&', $terms);
    }

    public static function searchLaw($params)
    {
        $api_params = [];
        if (array_key_exists('page', $params)) {
            $api_params['page'] = $page = max(intval($params['page']), 1);
        } else {
            $page = 1;
        }
        if (array_key_exists('limit', $params)) {
            $api_params['limit'] = $limit = max(intval($params['limit']), 1);
        } else {
            $limit = 100;
        }
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
        if ($params['law_id']) {
            $api_params['law_id'] = $params['law_id'];
            $cmd['query'] = [
                'term' => ['法律代碼' => $params['law_id']],
            ];
        }
        if (array_key_exists('q', $params) and $params['q']) {
            $api_params['q'] = $params['q'];
            $cmd['query']['bool']['should'][] = [
                'match_phrase' => [
                    '最新名稱' => $params['q'],
                ]
            ];
            $cmd['query']['bool']['should'][] = [
                'match_phrase' => [
                    '其他名稱' => $params['q'],
                ]
            ];
        }
        try {
            $obj = API::query('law', '/law/_search', 'GET', json_encode($cmd));
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
        $ret->api_url = self::getAPIURL('/api/law', $api_params);
        foreach ($obj->hits->hits as $hit) {
            $record = $hit->_source;
            $records[] = $record;
        }
        $ret->data = $records;
        return $ret;
    }

    public static function searchLawVer($params)
    {
        $api_params = [];
        if (array_key_exists('page', $params)) {
            $api_params['page'] = $page = max(intval($params['page']), 1);
        } else {
            $page = 1;
        }
        if (array_key_exists('limit', $params)) {
            $api_params['limit'] = $limit = max(intval($params['limit']), 1);
        } else {
            $limit = 100;
        }
        $cmd = [
            'sort' => ['日期' => 'asc'],
            'size' => $limit,
            'from' => $limit * $page - $limit,
            'query' => [
                'bool' => [
                    'must' => [],
                    'filter' => [],
                ],
            ],
        ];
        if ($params['law_id']) {
            $api_params['law_id'] = $params['law_id'];
            $cmd['query']['bool']['must'][] = ['term' => ['法律代碼' => $params['law_id']]];
        }
        if ($params['ver']) {
            $api_params['ver'] = $params['ver'];
            $cmd['query']['bool']['must'][] = ['term' => ['法律版本代碼' => $params['ver']]];
        }
        if (array_key_exists('type', $params) and $params['type']) {
            $api_params['type'] = $params['type'];
            $cmd['query']['bool']['must'][] = ['term' => ['版本種類' => $params['type']]];
        }
        $obj = API::query('law', '/lawver/_search', 'GET', json_encode($cmd));
        $records = new StdClass;
        $records->page = $page;
        $records->total = $obj->hits->total;
        $records->total_page = ceil($obj->hits->total / 100);
        $records->api_url = self::getAPIURL('/api/lawver', $api_params);
        $records->lawver = [];
        $records->bill_id = [];
        foreach ($obj->hits->hits as $hit) {
            $record = $hit->_source;
            if (property_exists($record, '修訂歷程')) {
                foreach ($record->{'修訂歷程'} as $idx1 => $data) {
                    if (!property_exists($data, '關係文書')) {
                        continue;
                    }
                    foreach ($data->{'關係文書'} as $idx2 => $relbook) {
                        if (preg_match('#:(LCEWA[0-9_]+)#', $relbook[1], $matches)) {
                            $records->bill_id[] = $matches[1];
                            $record->{'修訂歷程'}[$idx1]->{'關係文書'}[$idx2][2] = $matches[1];
                        }
                    }
                }
            }
            $records->lawver[] = $record;
        }
        return $records;
    }

    public static function searchLawLine($params)
    {
        $api_params = [];
        if (array_key_exists('page', $params)) {
            $api_params['page'] = $page = max(intval($params['page']), 1);
        } else {
            $page = 1;
        }
        if (array_key_exists('limit', $params)) {
            $api_params['limit'] = $limit = max(intval($params['limit']), 1);
        } else {
            $limit = 300;
        }

        $cmd = [
            'query' => [
                'bool' => [
                    'must' => [],
                    'filter' => [],
                ],
            ],
            'sort' => ['順序' => 'asc', '日期' => 'asc'],
            'size' => $limit,
            'from' => $limit* $page - $limit,
        ];
        if ($params['id']) {
            $api_params['id'] = $params['id'];
            $cmd['query']['bool']['must'][] = ['ids' => ['values' => $params['id']]];
        }
        if ($params['law_id']) {
            $api_params['law_id'] = $params['law_id'];
            $cmd['query']['bool']['must'][] = ['term' => ['法律代碼' => $params['law_id']]];
        }
        if ($params['line_no']) {
            $api_params['line_no'] = $params['line_no'];
            $cmd['query']['bool']['must'][] = ['term' => ['條號' => $params['line_no']]];
        }
        if ($params['ver']) {
            $api_params['ver'] = $params['ver'];
            $cmd['query']['bool']['must'][] = ['term' => ['法律版本代碼' => $params['ver']]];
        }
        if ($params['lawline_id']) {
            $api_params['lawline_id'] = $params['lawline_id'];
            $cmd['query']['bool']['must'][] = ['term' => ['法條代碼' => $params['lawline_id']]];
        }
        if ($params['q']) {
            $api_params['q'] = $params['q'];
            $cmd['query']['bool']['must'][] = [
                'match_phrase' => [
                    '內容' => $params['q'],
                ]
            ];
        }

        $obj = API::query('law', '/lawline/_search', 'GET', json_encode($cmd));
        $records = new StdClass;
        $records->query = $cmd['query'];
        $records->page = $page;
        $records->total = $obj->hits->total;
        $records->total_page = ceil($obj->hits->total / 100);
        $records->api_url = self::getAPIURL('/api/lawline', $api_params);
        $records->lawline= [];
        foreach ($obj->hits->hits as $hit) {
            $record = $hit->_source;
            $record->_id = $hit->_id;
            if ($record->{'前法版本'}) {
                $record->_prev_id = "{$record->{'法律代碼'}}-{$record->{'前法版本'}}-{$record->{'法條代碼'}}";
            }
            $records->lawline[] = $record;
        }
        return $records;
    }
}
