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
            $ret->docData = Parser::parseBillDoc($billNo, $content);
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
        $proposal = preg_replace('#^本院委員#', '', $proposal);
        $proposal = preg_replace('#^本院#', '', $proposal);
        $proposal = preg_replace('#等\d+人$#', '', $proposal);
        if (preg_match('#「(.*)」，請審議案。$#u', $title, $matches)) {
            $title = $matches[1];
        }
        if ($lawname) {
            if (strpos($title, $lawname) === 0) {
                $title = substr($title, strlen($lawname));
            }
        }
        if (strpos($title, '報告併案審查') === 0) {
            $title = '報告併案審查';
        }
        $title = preg_replace('#條文修正草案$#', '', $title);
        if ($title == '部分') {
            $title = '部分條文';
        }
        return $proposal . '-' . $title;
    }
}
