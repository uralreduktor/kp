<?php

abstract class AbstractConnector {
    protected $credentials;
    protected $logFile;
    protected $cookieFile;
    protected $lastHtml;
    protected $lastHttpCode;
    protected $lastUrl;

    public function __construct($credentials = []) {
        $this->credentials = $credentials;
        $this->logFile = __DIR__ . '/../error_log';
        // Создаем временный файл для кук
        $this->cookieFile = sys_get_temp_dir() . '/cookie_' . uniqid() . '.txt';
    }

    public function __destruct() {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    protected function log($message) {
        $timestamp = date('[Y-m-d H:i:s]');
        $class = get_class($this);
        $logMessage = "$timestamp $class: $message\n";
        error_log($logMessage);
        @file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Выполняет авторизацию на площадке.
     */
    public function login() {
        return true;
    }

    /**
     * Парсит данные торгов по URL
     */
    abstract public function parse($url);

    /**
     * Выполняет HTTP запрос
     */
    protected function request($url, $postData = [], $headers = []) {
        $ch = curl_init();
        
        $defaultHeaders = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ];
        
        $mergedHeaders = array_merge($defaultHeaders, $headers);
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $mergedHeaders,
            CURLOPT_ENCODING => '',
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile
        ];
        
        if (!empty($postData)) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($postData);
        }
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $this->lastHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->lastUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            $this->log("Request failed: $url. Error: $error");
            return false;
        }
        
        $this->log("Request success: $url. HTTP Code: " . $this->lastHttpCode . ". Size: " . strlen($response));
        $this->lastHtml = $response;
        
        return $response;
    }

    /**
     * Выполняет запрос через Python Parsing Service (FastAPI + Playwright)
     * Используется для сложных сайтов с JS-рендерингом и обхода защит
     * @param string $url
     * @param string|null $waitForSelector CSS селектор для ожидания (опционально)
     */
    protected function fetchWithBrowser($url, $waitForSelector = null) {
        $this->log("Fetching with Python Parsing Service: $url");
        
        // URL сервиса (FastAPI)
        $apiUrl = 'http://127.0.0.1:8001/api/parsing/parse';
        
        $requestData = [
            'url' => $url,
            'use_stealth' => true,
            'render_js' => true,
            'timeout' => 60000
        ];
        
        if ($waitForSelector) {
            $requestData['wait_for_selector'] = $waitForSelector;
        }
        
        $payload = json_encode($requestData);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload)
            ],
            CURLOPT_TIMEOUT => 90 // Увеличенный таймаут (60с на парсинг + запас)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            $this->log("Parsing Service failed: HTTP $httpCode. Error: $error. Response: " . substr($response, 0, 200));
            return false;
        }

        $data = json_decode($response, true);
        
        if (empty($data) || empty($data['content'])) {
            $this->log("Parsing Service returned empty content or invalid JSON");
            return false;
        }

        $this->log("Parsing Service success. Size: " . strlen($data['content']));
        
        $this->lastHtml = $data['content'];
        $this->lastHttpCode = $data['status_code'];
        
        return $this->lastHtml;
    }

    protected function getXPath($html) {
        if (!$html) return null;
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        @$dom->loadHTML($html);
        libxml_clear_errors();
        return new DOMXPath($dom);
    }
    
    protected function cleanText($text) {
        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
