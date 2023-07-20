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
            $url = str_replace('{prefix}', getenv('BILL_ELASTIC_PREFIX'), $url);
            $curl = curl_init(getenv('BILL_ELASTIC_URL') . $url);
            $_user = getenv('BILL_ELASTIC_USER');
            $_password = getenv('BILL_ELASTIC_PASSWORD');
        } else {
            $url = str_replace('{prefix}', getenv('LAW_ELASTIC_PREFIX'), $url);
            $curl = curl_init(getenv('LAW_ELASTIC_URL') . $url);
            $_user = getenv('LAW_ELASTIC_USER');
            $_password = getenv('LAW_ELASTIC_PASSWORD');
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERPWD, $_user . ':' . $_password);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
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
