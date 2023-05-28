<?php

if (file_exists(__DIR__ . '/config.php')) {
    include(__DIR__ . '/config.php');
}

class API
{
    public static function query($url, $method = 'GET', $data = null) {
        $curl = curl_init(getenv('SEARCH_URL') . $url);
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
