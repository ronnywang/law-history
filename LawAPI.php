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
            $terms[] = urlencode($k) . '=' . urlencode($v);
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
        if ($params['q']) {
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
        $ret->api_url = self::getAPIURL('/api/law', $api_params);
        foreach ($obj->hits->hits as $hit) {
            $record = $hit->_source;
            $records[] = $record;
        }
        $ret->data = $records;
        return $ret;
    }
}
