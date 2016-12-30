<?php

/**
 * 内核
 */
class Core
{
    public static $ipfile = null;
    public static function WriteHeader($ch, $header)
    {
        if (stripos($header, 'chunked') === false) {
            header($header);
        }
        return strlen($header);
    }
    public static function getIp()
    {
        if (isset(self::$ipfile) && is_file(self::$ipfile)) {
            $file = file_get_contents(__DIR__ . '/ip.txt');
            $ips = preg_split('/[\r\n]+/sim', $file);
            return $ips[array_rand($ips, 1)];
        }
        return false;
    }
    public static function getUserHeader()
    {
        $map = [
            'HTTP_COOKIE',
            'HTTP_HOST',
            'HTTP_PROXY_CONNECTION',
            'HTTP_CONNECTION',
        ];
        $header = [];
        foreach ($_SERVER as $key => $value) {
            if (preg_match('/^HTTP_/sim', $key) && !in_array(strtoupper($key), $map)) {
                $k = self::Parse_header($key);
                $header[$k] = $k . ': ' . $value;
            }
        }
        $header['Connection'] = 'Connection: close';
        return $header;
    }
    public static function Parse_header($key)
    {
        $key = preg_replace('/^HTTP_/sim', '', $key);
        $key = explode('_', strtolower($key));
        $key = array_map(function ($value) {
            return ucwords($value);
        }, $key);
        $key = implode('-', $key);
        return $key;
    }
    //请求
    public static function curl($url, $cookie = false)
    {
        $curl_opts = [
            CURLOPT_URL => $url, //需要请求的地址
            CURLOPT_HEADERFUNCTION => ['self', 'WriteHeader'],
            // CURLOPT_WRITEFUNCTION => 'write_function',
            CURLOPT_COOKIE => isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : '', //Set Cookie
            CURLOPT_AUTOREFERER => false, // 自动设置跳转地址
            CURLOPT_FOLLOWLOCATION => false, //开启重定向
            CURLOPT_TIMEOUT => 30, //设置超时时间
            CURLOPT_RETURNTRANSFER => false, //返回不直接输出
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET',
            CURLOPT_HEADER => false, //设定是否输出页面内容
            CURLOPT_NOBODY => false, //是否设置为不显示html的body
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_DNS_CACHE_TIMEOUT => 600,
        ];
        if (!$ip = self::getIp()) {
            $headers = [
                'X_forwarded_for' => 'X_forwarded_for: ' . preg_replace('/\:\d+$/', '', $ip),
                'Client_ip' => 'Client_ip: ' . preg_replace('/\:\d+$/', '', $ip),
            ];
            $curl_opts[CURLOPT_PROXY] = $ip;
        }
        $header = array_merge(Core::getUserHeader(), $headers);
        $curl_opts[CURLOPT_HTTPHEADER] = $header;
        if (isset($_SERVER['REQUEST_METHOD'])) {
            switch (strtoupper($_SERVER['REQUEST_METHOD'])) {
                case 'HEAD':
                    $curl_opts[CURLOPT_NOBODY] = true;
                    break;
                case 'GET':
                    break;
                case 'POST':
                    $curl_opts[CURLOPT_POST] = true;
                    $curl_opts[CURLOPT_POSTFIELDS] = file_get_contents('php://input');
                    break;
                case 'PUT':
                    break;
                case 'DELETE':
                    $curl_opts[CURLOPT_POSTFIELDS] = file_get_contents('php://input');
                    break;
                case 'CONNECT':
                    exit;
                default:
                    echo 'Invalid Method';
                    exit(-1);
            }
        } else {
            echo 'Invalid Method';
            exit(-1);
        }
        $ch = curl_init(); //初始化curl
        curl_setopt_array($ch, $curl_opts);
        curl_exec($ch);
        curl_close($ch);
    }
    public static function Proxy()
    {
        $url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        if (empty($url)) {
            echo 'Request URI Error';
            exit(-1);
        }
        $ssl = false;
        if (!preg_match('/^http:\/\//sim', $url)) {
            $ssl = true;
            $url = "https://" . $url;
        }
        self::curl($url);
    }
}
Core::Proxy();
