<?php

include(__DIR__ . '/Parser.php');

class BillAPI
{
    public static function searchBillIDMap($params)
    {
        $api_params = [];
        $cmd = [
            'size' => 10000,
        ];
        if ($params['id']) {
            $api_params['id'] = $params['id'];
            if (is_scalar($params['id'])) {
                $params['id'] = [$params['id']];
            }
            $cmd['query']['ids'] = ['values' => $params['id']];
        }
        $obj = API::query('bill', '/billidmap/_search', 'GET', json_encode($cmd));
        $records = new StdClass;
        $records->api_url = LawAPI::getAPIURL('/api/billidmap', $api_params);
        $records->map = new StdClass;
        foreach ($obj->hits->hits as $hit) {
            $records->map->{$hit->_id} = $hit->_source->billNo;
        }
        return $records;
    }

    public static function getBillData($billNo)
    {
        $ret = new StdClass;
        if (!$content = file_get_contents("https://lydata.ronny-s3.click/bill-html/{$billNo}.gz")) {
            throw new Exception("{$billNo} html not found");
        }
        if (!$content = gzdecode($content)) {
            throw new Exception("{$billNo} gunzip failed");
        }
        $ret->detail = Parser::parseBillDetail($billNo, $content);

        $content = gzdecode(file_get_contents("https://lydata.ronny-s3.click/bill-doc-parsed/html/{$billNo}.doc.gz"));
        if (!$content) {
            $content = gzdecode(file_get_contents("https://lydata.ronny-s3.click/bill-doc-parsed/html/{$billNo}-0.doc.gz"));
        }

        if ($content) {
            $obj = json_decode($content);
            $content = (base64_decode($obj->content));
            try {
                $ret->docData = Parser::parseBillDoc($billNo, $content);
            } catch (Exception $e) {
                $ret->docData = '';
                $ret->doc_error = $e->getMessage();
            }
        }
        return $ret;
    }

    public static function getBillHTML($billNo)
    {
        $ret = new StdClass;
        $content = gzdecode(file_get_contents("https://lydata.ronny-s3.click/bill-doc-parsed/html/{$billNo}.doc.gz"));
        if (!$content) {
            $content = gzdecode(file_get_contents("https://lydata.ronny-s3.click/bill-doc-parsed/html/{$billNo}-0.doc.gz"));
        }

        if ($content) {
            $obj = json_decode($content);
            $content = (base64_decode($obj->content));
            $content = preg_replace('#<img src="([^"]*)" name="DW\d+" alt="DW\d+" align="left" hspace="12" width="\d+"/>\n#', '', $content);
            return $content;
        }
        return '';
    }

    public static function getBillName($proposal, $title, $lawname = '')
    {
        if (!$proposal) {
            $proposal = $title;
        }
        $proposal = preg_replace('#^本院委員#', '', $proposal);
        $proposal = preg_replace('#^本院#', '', $proposal);
        $proposal = preg_replace('#「.*$#', '', $proposal);
        $proposal = preg_replace('#等\d+人$#', '', $proposal);
        $proposal = preg_replace('#報告併案審查.*$#', '', $proposal);
        if (strpos($title, '報告併案審查') !== false ) {
            $title = '報告併案審查';
        }
        if (preg_match('#「(.*)」，請審議案。$#u', $title, $matches)) {
            $title = $matches[1];
        } elseif (preg_match('#「(.*)」案。#', $title, $matches)) {
            $title = $matches[1];
        }
        if (strpos($proposal, '考試院') === 0) {
            $proposal = '考試院';
        }
        if ($lawname) {
            if (strpos($title, $lawname) === 0) {
                $title = substr($title, strlen($lawname));
            }
        }
        $title = preg_replace('#條文修正草案$#', '', $title);
        if ($title == '部分') {
            $title = '部分條文';
        }
        return $proposal . '-' . $title;
    }

    public static function searchBill($params)
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
            'sort' => ['last_time' => 'desc'],
        ];
        if ($params['bill_id']) {
            $api_params['bill_id'] = $params['bill_id'];
            $cmd['query'] = [
                'term' => ['法律代碼' => $params['bill_id']],
            ];
        }
        if (array_key_exists('q', $params) and $params['q']) {
            $api_params['q'] = $params['q'];
            $cmd['query']['bool']['should'][] = [
                'match_phrase' => [
                    '議案名稱' => $params['q'],
                ]
            ];
            $cmd['query']['bool']['should'][] = [
                'match_phrase' => [
                    '提案單位/提案委員' => $params['q'],
                ]
            ];
        }
        try {
            $obj = API::query('bill', '/bill/_search', 'GET', json_encode($cmd));
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
        $ret->api_url = LawAPI::getAPIURL('/api/bill', $api_params);
        foreach ($obj->hits->hits as $hit) {
            $record = $hit->_source;
            $records[] = $record;
        }
        $ret->data = $records;
        return $ret;
    }

    public static function getLawNameFromTableName($tablename)
    {
        if (preg_match('#「?(.*(條例|法))(第.*條|部分).*條文修正草案(條文)?對照表#u', $tablename, $matches)) {
            $lawname = $matches[1];
        } elseif (preg_match('#「(.*)草案」條文對照表#', $tablename, $matches)) {
            $lawname = $matches[1];
        } elseif (preg_match('#(.*)(第.*條)修正草案條文對照表$#', $tablename, $matches)) {
            $lawname = $matches[1];
        } elseif (preg_match('#(.*)修正草案(條文)?對照表#', $tablename, $matches)) {
            $lawname = $matches[1];

        } elseif (preg_match('#(.*)增訂(.*)條文草案#', $tablename, $matches)) {
            $lawname = $matches[1];
        } else {
            throw new Exception("未知的對照表標題: " . $tablename);
        }
        $lawname = preg_replace('#修正$#', '', $lawname);
        return $lawname;
    }
}
