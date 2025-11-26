<?php
/**
 * API для получения списка сохраненных коммерческих предложений
 * Путь: /Proforma Invoise/api/list.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Только GET запросы
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Директория с архивом
$archiveDir = dirname(__DIR__) . '/@archiv 2025';

// Проверка существования директории
if (!is_dir($archiveDir)) {
    http_response_code(200);
    echo json_encode(['success' => true, 'invoices' => []]);
    exit;
}

// Получение списка JSON файлов
$files = glob($archiveDir . '/*.json');
if ($files === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to read directory']);
    exit;
}

// Сортировка по времени изменения (новые первыми)
usort($files, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

// Формирование списка
$invoices = [];
foreach ($files as $file) {
    $content = file_get_contents($file);
    $data = json_decode($content, true);
    
    if ($data) {
        $invoices[] = [
            'filename' => basename($file),
            'number' => $data['number'] ?? 'N/A',
            'date' => $data['date'] ?? 'N/A',
            'recipient' => $data['recipient'] ?? 'N/A',
            'currency' => $data['currency'] ?? 'RUB',
            'total' => isset($data['items']) ? array_reduce($data['items'], function($sum, $item) {
                return $sum + ($item['quantity'] * $item['price']);
            }, 0) : 0,
            'organizationId' => $data['organizationId'] ?? null, // ВАЖНО: для фильтрации по организациям
            'documentType' => $data['documentType'] ?? 'regular', // Тип документа: 'regular' или 'tender'
            'saved_at' => $data['_metadata']['saved_at'] ?? date('c', filemtime($file)),
            'filesize' => filesize($file)
        ];
    }
}

// Успешный ответ
http_response_code(200);
echo json_encode([
    'success' => true,
    'invoices' => $invoices,
    'count' => count($invoices)
]);
