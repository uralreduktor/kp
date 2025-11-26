<?php
/**
 * Прямой тест парсера данных торгов B2B-Center (без HTTP)
 */

// URL для тестирования
$testUrl = 'https://www.b2b-center.ru/market/priobretenie-reduktora-ch-5a028-0-sztm-dlia-ao-mikhailovskii/tender-4242870/';

echo "=== Тест парсера B2B-Center ===\n\n";
echo "URL: $testUrl\n\n";

// Определение платформы
$platform = null;
$urlLower = strtolower($testUrl);

if (strpos($urlLower, 'b2b-center.ru') !== false) {
    $platform = 'b2b-center';
}

echo "Определенная платформа: " . ($platform ?: 'не определена') . "\n\n";

// Извлечение номера торгов из URL
$tenderNumber = '';
if (preg_match('/tender-(\d+)/', $testUrl, $matches)) {
    $tenderNumber = $matches[1];
}
echo "Номер торгов из URL: $tenderNumber\n\n";

// Загрузка HTML страницы с позициями
$positionsUrl = rtrim($testUrl, '/') . '/?action=positions';

echo "URL страницы позиций: $positionsUrl\n\n";

// Используем cURL для получения HTML
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $positionsUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
]);

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "Ошибка cURL: $error\n";
    exit(1);
}

if ($httpCode !== 200) {
    echo "HTTP код: $httpCode\n";
    echo "Ответ (первые 500 символов):\n" . substr($html, 0, 500) . "\n";
    exit(1);
}

echo "HTML загружен успешно (размер: " . strlen($html) . " байт)\n\n";

// Парсинг HTML
libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
libxml_clear_errors();

$xpath = new DOMXPath($dom);

// Ищем строки с классом c2
$dataRows = $xpath->query("//tr[contains(@class, 'c2')] | //tr[@class='c2']");

echo "Найдено строк с классом c2: " . ($dataRows ? $dataRows->length : 0) . "\n\n";

$extractedItems = [];

if ($dataRows && $dataRows->length > 0) {
    foreach ($dataRows as $index => $row) {
        echo "--- Строка " . ($index + 1) . " ---\n";
        
        $cells = $xpath->query(".//td", $row);
        $cellArray = [];
        
        foreach ($cells as $cell) {
            $cellText = trim($cell->textContent);
            $cellArray[] = $cellText;
        }
        
        echo "Количество ячеек: " . count($cellArray) . "\n";
        
        for ($i = 0; $i < min(count($cellArray), 8); $i++) {
            $cellText = substr($cellArray[$i], 0, 50);
            echo "  [$i] $cellText\n";
        }
        
        // Извлекаем данные
        if (count($cellArray) >= 5) {
            $itemName = '';
            $quantity = '';
            
            // Колонка "Наименование позиции" (индекс 3)
            if (isset($cellArray[3]) && !empty(trim($cellArray[3]))) {
                $itemName = trim($cellArray[3]);
                echo "  → Используем индекс 3 для наименования\n";
            } elseif (isset($cellArray[1]) && !empty(trim($cellArray[1]))) {
                $itemName = trim($cellArray[1]);
                echo "  → Используем индекс 1 для наименования (fallback)\n";
            }
            
            // Количество (индекс 4)
            if (isset($cellArray[4]) && !empty(trim($cellArray[4]))) {
                $quantity = trim($cellArray[4]);
            }
            
            echo "\nИзвлеченные данные:\n";
            echo "  Наименование: '$itemName'\n";
            echo "  Количество (сырое): '$quantity'\n";
            
            // Очистка количества
            $quantityClean = preg_replace('/[^\d.,]/', '', $quantity);
            $quantityClean = str_replace(',', '.', $quantityClean);
            $quantityFloat = floatval($quantityClean);
            
            echo "  Количество (число): $quantityFloat\n";
            
            // Очистка наименования
            $itemNameClean = str_replace(["\n", "\r", "\t"], ' ', $itemName);
            $itemNameClean = preg_replace('/\s+/', ' ', trim($itemNameClean));
            
            if (!empty($itemNameClean) && strlen($itemNameClean) > 2 && $quantityFloat > 0) {
                echo "  ✓ Данные валидны!\n";
                
                $extractedItems[] = [
                    'name' => $itemNameClean,
                    'quantity' => $quantityFloat
                ];
            } else {
                echo "  ✗ Данные невалидны\n";
                echo "    - itemName пусто: " . (empty($itemNameClean) ? 'да' : 'нет') . "\n";
                echo "    - длина itemName: " . strlen($itemNameClean) . "\n";
                echo "    - quantity > 0: " . ($quantityFloat > 0 ? 'да' : 'нет') . "\n";
            }
        }
        
        echo "\n";
    }
} else {
    echo "Строки с классом c2 не найдены. Пробуем найти таблицы...\n\n";
    
    // Пробуем найти таблицы
    $tables = $xpath->query("//table");
    echo "Найдено таблиц: " . ($tables ? $tables->length : 0) . "\n";
    
    if ($tables && $tables->length > 0) {
        foreach ($tables as $tableIndex => $table) {
            echo "\n--- Таблица " . ($tableIndex + 1) . " ---\n";
            
            $rows = $xpath->query(".//tr", $table);
            echo "Строк в таблице: " . ($rows ? $rows->length : 0) . "\n";
            
            // Показываем первые 5 строк
            $showRows = min(5, $rows ? $rows->length : 0);
            for ($i = 0; $i < $showRows; $i++) {
                $row = $rows->item($i);
                $cells = $xpath->query(".//td | .//th", $row);
                
                echo "  Строка $i: ";
                $cellCount = 0;
                foreach ($cells as $cell) {
                    $text = trim($cell->textContent);
                    if (strlen($text) > 0) {
                        echo "[$cellCount]" . substr($text, 0, 30) . " | ";
                        $cellCount++;
                    }
                }
                echo "\n";
            }
        }
    }
}

echo "\n=== Результаты ===\n";
echo "Извлечено позиций: " . count($extractedItems) . "\n\n";

if (count($extractedItems) > 0) {
    echo "Данные для отправки в форму:\n";
    echo json_encode([
        'success' => true,
        'data' => [
            'tenderNumber' => $tenderNumber,
            'items' => $extractedItems
        ],
        'platform' => $platform
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
} else {
    echo "⚠ Позиции не найдены!\n";
}

echo "\n=== Конец теста ===\n";

