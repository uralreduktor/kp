<?php
/**
 * Тестовый скрипт для проверки логирования парсера
 */

// Устанавливаем путь к логу
$logFile = __DIR__ . '/../api/error_log';

echo "=== Тест логирования парсера ===\n\n";
echo "Лог файл: $logFile\n\n";

// Тест 1: Проверка записи в лог
echo "1. Тест записи в error_log...\n";
error_log("TEST: Парсер логирование работает!");
echo "   ✓ Запись выполнена\n\n";

// Тест 2: Проверка записи в файл напрямую
echo "2. Тест записи напрямую в файл...\n";
file_put_contents($logFile, date('[Y-m-d H:i:s]') . " TEST: Прямая запись в файл\n", FILE_APPEND);
echo "   ✓ Запись выполнена\n\n";

// Тест 3: Вызов парсера через include
echo "3. Тест вызова парсера...\n";
echo "   URL: https://www.b2b-center.ru/market/priobretenie-reduktora-konveiera-ch-5a028-0-sztm-dlia-ao-mikhailovskii/tender-4242870/\n\n";

// Симулируем GET запрос
$_GET['url'] = 'https://www.b2b-center.ru/market/priobretenie-reduktora-konveiera-ch-5a028-0-sztm-dlia-ao-mikhailovskii/tender-4242870/';
$_SERVER['REQUEST_METHOD'] = 'GET';

// Захватываем вывод
ob_start();

// Включаем парсер
try {
    include __DIR__ . '/../api/parse-tender-data.php';
    $output = ob_get_clean();
    
    echo "   Ответ парсера:\n";
    $data = json_decode($output, true);
    if ($data) {
        echo "   - success: " . ($data['success'] ? 'true' : 'false') . "\n";
        echo "   - platform: " . ($data['platform'] ?? 'не указана') . "\n";
        if (isset($data['data']['items'])) {
            echo "   - items count: " . count($data['data']['items']) . "\n";
            if (count($data['data']['items']) > 0) {
                echo "   - first item: " . json_encode($data['data']['items'][0], JSON_UNESCAPED_UNICODE) . "\n";
            }
        } else {
            echo "   - items: не найдены\n";
        }
        if (isset($data['data']['tenderNumber'])) {
            echo "   - tenderNumber: " . $data['data']['tenderNumber'] . "\n";
        }
    } else {
        echo "   - Ошибка парсинга JSON\n";
        echo "   - Raw output: " . substr($output, 0, 200) . "...\n";
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "   ✗ Ошибка: " . $e->getMessage() . "\n";
}

echo "\n4. Проверка логов...\n";
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    $testLogs = array_filter(explode("\n", $logs), function($line) {
        return strpos($line, 'B2B-Center parser') !== false || strpos($line, 'TEST:') !== false;
    });
    
    if (count($testLogs) > 0) {
        echo "   Найдено записей: " . count($testLogs) . "\n";
        echo "   Последние 10 записей:\n";
        foreach (array_slice($testLogs, -10) as $log) {
            echo "   " . $log . "\n";
        }
    } else {
        echo "   ⚠ Записи от парсера не найдены в логе\n";
        echo "   Возможно, логирование не работает или парсер не выполнялся\n";
    }
} else {
    echo "   ⚠ Файл лога не найден: $logFile\n";
}

echo "\n=== Конец теста ===\n";

