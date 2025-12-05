<?php

require_once __DIR__ . '/B2BCenterConnector.php';
require_once __DIR__ . '/GenericConnector.php';

class ConnectorFactory {
    public static function create($url) {
        $host = parse_url($url, PHP_URL_HOST);
        $host = preg_replace('/^www\./', '', $host);
        
        // Загружаем креды
        $allCreds = @include(__DIR__ . '/../platform_credentials.php');
        $creds = [];
        
        // Ищем креды для хоста (включая поддомены)
        if (is_array($allCreds)) {
            foreach ($allCreds as $domain => $c) {
                if ($host === $domain || strpos($host, '.' . $domain) !== false) {
                    $creds = $c;
                    break;
                }
            }
        }
        
        // Выбор коннектора
        if (strpos($host, 'b2b-center.ru') !== false) {
            return new B2BCenterConnector($creds);
        }
        
        // Можно добавить другие специализированные коннекторы
        /*
        if (strpos($host, 'tender.pro') !== false) {
            return new TenderProConnector($creds);
        }
        */
        
        // Fallback
        return new GenericConnector($creds);
    }
}

