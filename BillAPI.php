<?php

include(__DIR__ . '/Parser.php');

class BillAPI
{
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
