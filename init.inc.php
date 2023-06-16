<?php

if (file_exists(__DIR__ . '/config.php')) {
    include(__DIR__ . '/config.php');
}
include(__DIR__ . '/LawAPI.php');
include(__DIR__ . '/BillAPI.php');

class API
{
    public static function query($type, $url, $method = 'GET', $data = null) {
        if ($type == 'bill') {
            $curl = curl_init(getenv('BILL_SEARCH_URL') . $url);
        } else {
            $curl = curl_init(getenv('LAW_SEARCH_URL') . $url);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        if (!is_null($method)) {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            if (!is_null($data)) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            }
        }
        $content = curl_exec($curl);
        curl_close($curl);
        if (!$obj = json_decode($content)) {
            throw new Exception('json error');
        }
        return $obj;
    }
}
