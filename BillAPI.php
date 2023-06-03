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
        $obj = API::query('/billidmap/_search', 'GET', json_encode($cmd));
        $records = new StdClass;
        $records->api_url = LawAPI::getAPIURL('/api/billidmap', $api_params);
        $records->map = [];
        foreach ($obj->hits->hits as $hit) {
            $records->map[$hit->_id] = $hit->_source->billNo;
        }
        return $records;
    }

    public static function getBillData($billNo)
    {
        $ret = new StdClass;
        $content = gzdecode(file_get_contents("https://lydata.ronny-s3.click/bill-html/{$billNo}.gz"));
        $ret->detail = Parser::parseBillDetail($billNo, $content);

        $content = gzdecode(file_get_contents("https://lydata.ronny-s3.click/bill-doc-parsed/html/{$billNo}.doc.gz"));
        if (!$content) {
            $content = gzdecode(file_get_contents("https://lydata.ronny-s3.click/bill-doc-parsed/html/{$billNo}-0.doc.gz"));
        }

        if ($content) {
            $obj = json_decode($content);
            $content = (base64_decode($obj->content));
            $ret->docData = Parser::parseBillDoc($billNo, $content);
        }
        return $ret;
    }
}
