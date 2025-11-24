<?php
/**
 * API для загрузки конкретного проформа-инвойса
 * Путь: /Proforma Invoise/api/load.php?filename=xxx.json
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

// Получение имени файла
$filename = $_GET['filename'] ?? '';

if (empty($filename)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Filename parameter is required']);
    exit;
}

// Защита от path traversal
$filename = basename($filename);

// Директория с архивом
$archiveDir = dirname(__DIR__) . '/@archiv 2025';
$filepath = $archiveDir . '/' . $filename;

// Проверка существования файла
if (!file_exists($filepath)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Invoice not found']);
    exit;
}

// Чтение файла
$content = file_get_contents($filepath);
$data = json_decode($content, true);

if (!$data) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to parse invoice data']);
    exit;
}

// Успешный ответ
http_response_code(200);
echo json_encode([
    'success' => true,
    'data' => $data
]);

