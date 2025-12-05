<?php
// test_python_integration.php

require_once __DIR__ . '/api/connectors/AbstractConnector.php';

// Создаем тестовый класс, чтобы получить доступ к protected методу
class TestConnector extends AbstractConnector {
    public function parse($url) { return []; } // Заглушка для абстрактного метода
    
    public function testBrowserFetch($url) {
        return $this->fetchWithBrowser($url);
    }
}

$url = 'https://example.com'; 

echo "Testing Python Service Integration...\n";
echo "Target URL: $url\n";

$connector = new TestConnector();
$start = microtime(true);

$result = $connector->testBrowserFetch($url);

$duration = microtime(true) - $start;

if ($result) {
    echo "\n[SUCCESS] Content received!\n";
    echo "Length: " . strlen($result) . " bytes\n";
    echo "Duration: " . round($duration, 2) . "s\n";
    
    // Проверка контента
    if (stripos($result, 'Example Domain') !== false) {
        echo "Content check: Verified (contains 'Example Domain')\n";
    } else {
        echo "Content check: Warning (expected keyword not found)\n";
    }
} else {
    echo "\n[ERROR] Failed to fetch content.\n";
    echo "Check logs: /var/www/kp/api/error_log\n";
    echo "Check service: sudo journalctl -u kp-auth-backend -n 50\n";
}
