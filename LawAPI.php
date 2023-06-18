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
            $record->_id = $hit->_id;
            $records[] = $record;
        }
        $ret->data = $records;
        return $ret;
    }

    public static function equalStr($a, $b)
    {
        $std_str = function($s) {
            $s = preg_replace('#\s#', '', $s);
            return $s;
        };
        $a = $std_str($a);
        $b = $std_str($b);
        return $a == $b;
    }

    public static function updateBillData($params)
    {
        list(, $billNo) = explode('-', $params['ver']);

        $bulk_insert_pool = [];

        try {
            $bill_data = BillAPI::getBillData($billNo);
        } catch (Exception $e) {
            return false;
        }
        $bill_type = $bill_data->docData->{'立法種類'};
        if ($bill_type == '修正條文') {
            $action = '修正';
        }
        $commit_at = date('Ymd', strtotime($bill_data->docData->created_at));

        $ret = LawAPI::searchLaw(['law_id' => $params['law_id']]);
        if (!$law_data = $ret->data[0]) {
            return false;
        }
        $lawver = new StdClass;
        $lawver->{'法律代碼'} = $params['law_id'];
        $lawver->{'版本種類'} = '議案';
        $lawver->{'法律版本代碼'} = $params['ver'];
        $lawver->{'前版本代碼'} = null;
        $lawver->{'日期'} = intval($commit_at);
        $lawver->{'動作'} = $action;
        $lawver->{'法律名稱'} = $title;
        $lawver->{'議案資料'} = $bill_data;

        $title = $bill_data->detail->{'提案單位/提案委員'} . $bill_data->detail->{'議案名稱'};
        $title = preg_replace('#^本院委員#', '', $title);
        $title = preg_replace('#，請審議案。$#', '', $title);

        $bill_type = $bill_data->docData->{'立法種類'};
        if ($bill_type == '修正條文') {
            foreach ($bill_data->docData->{'修正記錄'} as $record) {
                list($lineno, $content) = explode('　', $record['現行條文'], 2);

                $ret = LawAPI::searchLawLine(['law_id' => $law_data->{'法律代碼'}, 'line_no' => $lineno]);
                $lawline = null;
                foreach (array_reverse($ret->lawline) as $check_lawline) {
                    if ($check_lawline->{'日期'} > $commit_at) {
                        continue;
                    }
                    if (!self::equalStr($check_lawline->{'內容'}, $content)) {
                        continue;
                    }

                    if (is_null($lawver->{'前版本代碼'})) {
                        $lawver->{'前版本代碼'} = $check_lawline->{'法律版本代碼'};
                    } elseif ($lawver->{'前版本代碼'} != $check_lawline->{'法律版本代碼'}) {
                        throw new Exception('版本代碼好像不太一樣？');
                    }
                    $lawline = $check_lawline;
                    unset($check_lawline->_id);
                    unset($check_lawline->_prev_id);
                    $lawline->{'法律版本代碼'} = $params['ver'];
                    $lawline->{'日期'} = intval($commit_at);
                    $lawline->{'動作'} = $action;
                    $lawline->{'內容'} = explode('　', $record['修正條文'])[1];
                    $lawline->{'前法版本'} = $lawline->{'此法版本'};
                    $lawline->{'此法版本'} = $params['ver'];
                    $lawline->{'說明'} = $record['說明'];
					$bulk_insert_pool[] = ['lawline', implode('-', [$lawline->{'法律代碼'}, $lawline->{'法律版本代碼'}, $lawline->{'法條代碼'}]), $lawline];
					break;
                }
                if (is_null($lawline)) {
                    // TODO: 找不到對應的法律
                    return false;
                }
            }
        }
		$bulk_insert_pool[] = ['lawver', implode('-', [$lawver->{'法律代碼'}, $lawver->{'法律版本代碼'}]), $lawver];
        foreach ($bulk_insert_pool as $bulk_insert) {
            self::dbBulkInsert($bulk_insert[0], $bulk_insert[1], $bulk_insert[2]);
        }
        self::dbBulkCommit();
        return true;
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
        if (array_key_exists('law_id', $params) and $params['law_id']) {
            $api_params['law_id'] = $params['law_id'];
            $cmd['query']['bool']['must'][] = ['term' => ['法律代碼' => $params['law_id']]];
        }
        if (array_key_exists('ver', $params) and $params['ver']) {
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

        // 如果找不到資料並且是 bill-xxxx 開頭的，嘗試線上直接更新資料
        if (!count($obj->hits->hits) and array_key_exists('ver', $params) and strpos($params['ver'], 'bill-') === 0) {
            if (self::updateBillData($params)) {
                return self::searchLawVer($params);
            }
        }

        foreach ($obj->hits->hits as $hit) {
            $record = $hit->_source;
            $record->_id = $hit->_id;
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
        if (array_key_exists('id', $params) and $params['id']) {
            $api_params['id'] = $params['id'];
            $cmd['query']['bool']['must'][] = ['ids' => ['values' => $params['id']]];
        }
        if (array_key_exists('law_id', $params) and $params['law_id']) {
            $api_params['law_id'] = $params['law_id'];
            $cmd['query']['bool']['must'][] = ['term' => ['法律代碼' => $params['law_id']]];
        }
        if (array_key_exists('line_no', $params) and $params['line_no']) {
            $api_params['line_no'] = $params['line_no'];
            $cmd['query']['bool']['must'][] = ['term' => ['條號' => $params['line_no']]];
        }
        if (array_key_exists('ver', $params) and $params['ver']) {
            $api_params['ver'] = $params['ver'];
            $cmd['query']['bool']['must'][] = ['term' => ['法律版本代碼' => $params['ver']]];
        }
        if (array_key_exists('lawline_id', $params) and $params['lawline_id']) {
            $api_params['lawline_id'] = $params['lawline_id'];
            $cmd['query']['bool']['must'][] = ['term' => ['法條代碼' => $params['lawline_id']]];
        }
        if (array_key_exists('q', $params) and $params['q']) {
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

    public static $_db_bulk_pool = [];

    public static function dbBulkCommit($mapping = null)
    {
        if (is_null($mapping)) {
            $mappings = array_keys(self::$_db_bulk_pool);
        } else {
            $mappings = [$mapping];
        }
        foreach ($mappings as $mapping) {
			$ret = API::query('law', "/{$mapping}/_bulk", 'PUT', self::$_db_bulk_pool[$mapping]);
            $ids = [];
            foreach ($ret->items as $command) {
                foreach ($command as $action => $result) {
                    if ($result->status == 200 or $result->status == 201) {
                        $ids[] = $result->_id;
                        continue;
                    }
                    print_r($result);
                    exit;
                }
            }

            error_log(sprintf("bulk commit, update (%d) %s", count($ids), mb_strimwidth(implode(',', $ids), 0, 200)));
            self::$_db_bulk_pool[$mapping] = '';
        }
    }

    public static function dbBulkInsert($mapping, $id, $data)
    {
        if (!array_key_exists($mapping, self::$_db_bulk_pool)) {
            self::$_db_bulk_pool[$mapping] = '';
        }
        self::$_db_bulk_pool[$mapping] .=
            json_encode(array(
                'update' => array('_id' => $id),
            )) . "\n"
            . json_encode(array(
                'doc' => $data,
                'doc_as_upsert' => true,
            )) . "\n";
        if (strlen(self::$_db_bulk_pool[$mapping]) > 1000000) {
            self::dbBulkCommit($mapping);
        }
    }
}
