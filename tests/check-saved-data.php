<?php
/**
 * Проверка сохраненных данных в JSON файле
 * Использование: php tests/check-saved-data.php "@archiv 2025/251125-06_2025-11-25_16-15-11.json"
 */

$filename = $argv[1] ?? '';

if (empty($filename)) {
    echo "Использование: php tests/check-saved-data.php \"путь/к/файлу.json\"\n";
    exit(1);
}

$filepath = __DIR__ . '/../' . $filename;

if (!file_exists($filepath)) {
    echo "Файл не найден: $filepath\n";
    exit(1);
}

echo "=== Проверка сохраненных данных ===\n\n";
echo "Файл: $filename\n";
echo "Путь: $filepath\n\n";

$content = file_get_contents($filepath);
$data = json_decode($content, true);

if (!$data) {
    echo "Ошибка: Не удалось распарсить JSON\n";
    exit(1);
}

echo "=== Основная информация ===\n";
echo "Номер КП: " . ($data['number'] ?? 'не указан') . "\n";
echo "Дата: " . ($data['date'] ?? 'не указана') . "\n";
echo "Тип документа: " . ($data['documentType'] ?? 'не указан') . "\n";
echo "Номер торгов: " . ($data['tenderNumber'] ?? 'не указан') . "\n";
echo "Торговая площадка: " . ($data['tradingPlatform'] ?? 'не указана') . "\n";
echo "Ссылка на торги: " . ($data['tenderLink'] ?? 'не указана') . "\n\n";

echo "=== Позиции товаров ===\n";
if (isset($data['items']) && is_array($data['items'])) {
    echo "Количество позиций: " . count($data['items']) . "\n\n";
    
    foreach ($data['items'] as $index => $item) {
        echo "--- Позиция " . ($index + 1) . " ---\n";
        echo "  Описание товара (type): \"" . ($item['type'] ?? 'НЕ ЗАПОЛНЕНО') . "\"\n";
        echo "  Модель (name): \"" . ($item['name'] ?? '') . "\"\n";
        echo "  Количество: " . ($item['quantity'] ?? 0) . "\n";
        echo "  Единица измерения: " . ($item['unit'] ?? '') . "\n";
        echo "  Цена: " . ($item['price'] ?? 0) . "\n";
        echo "  Страна происхождения: " . ($item['countryOfOrigin'] ?? '') . "\n";
        
        // Проверка на дефолтные значения
        if (empty($item['type']) || $item['type'] === 'Описание товара') {
            echo "  ⚠ ПРОБЛЕМА: Поле 'type' пустое или содержит дефолтное значение!\n";
        }
        
        echo "\n";
    }
} else {
    echo "Позиции товаров не найдены или не являются массивом\n";
}

echo "=== Полный JSON ===\n";
echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

