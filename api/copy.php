<?php
/**
 * API для копирования коммерческих предложений
 * Путь: /Proforma Invoise/api/copy.php
 * 
 * Параметры POST:
 * - filename: имя исходного файла
 * - documentType: тип документа ('regular' или 'tender')
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Только POST запросы
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Получение данных
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit;
}

// Проверка обязательных параметров
$sourceFilename = $data['filename'] ?? '';
$documentType = $data['documentType'] ?? 'regular';

if (empty($sourceFilename)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Filename parameter is required']);
    exit;
}

// Валидация типа документа
if (!in_array($documentType, ['regular', 'tender'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid documentType. Must be "regular" or "tender"']);
    exit;
}

// Защита от path traversal
$sourceFilename = basename($sourceFilename);

// Директория с архивом
$archiveDir = dirname(__DIR__) . '/@archiv 2025';
$sourceFilepath = $archiveDir . '/' . $sourceFilename;

// Проверка существования исходного файла
if (!file_exists($sourceFilepath)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Source invoice not found']);
    exit;
}

// Чтение исходного файла
$sourceContent = file_get_contents($sourceFilepath);
$invoiceData = json_decode($sourceContent, true);

if (!$invoiceData) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to parse source invoice data']);
    exit;
}

// Изменение типа документа
$invoiceData['documentType'] = $documentType;

// Обновляем дату на текущую
$invoiceData['date'] = date('Y-m-d');

// Очистка метаданных для создания нового файла
unset($invoiceData['_metadata']);

// Генерация нового имени файла
require_once __DIR__ . '/pdf_utils.php';
require_once __DIR__ . '/organization_parser.php';

// Определяем код организации
$orgId = $invoiceData['orgId'] ?? $invoiceData['organizationId'] ?? null;
$orgCode = 'INV'; // По умолчанию

if ($orgId) {
    $orgFile = dirname(__DIR__) . '/js/organizations.js';
    if (file_exists($orgFile)) {
        $orgContent = file_get_contents($orgFile);
        $orgData = parseOrganizationData($orgId, $orgContent);
        $orgCode = !empty($orgData['code']) ? $orgData['code'] : strtoupper(substr($orgId, 0, 3));
    }
}

// Генерируем новый номер инвойса
$invoiceDate = $invoiceData['date'];
$invoiceData['number'] = generateInvoiceNumberCode($orgCode, $archiveDir, $invoiceDate);

// Создание директории если не существует
if (!is_dir($archiveDir)) {
    $parentDir = dirname($archiveDir);
    if (!is_dir($parentDir)) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'Parent directory does not exist'
        ]);
        exit;
    }
    
    if (!mkdir($archiveDir, 0777, true)) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to create archive directory'
        ]);
        exit;
    }
    @chmod($archiveDir, 0777);
}

// Проверка прав на запись
if (!is_writable($archiveDir)) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Archive directory is not writable'
    ]);
    exit;
}

// Генерация имени нового файла
$invoiceNumber = preg_replace('/[^a-zA-Z0-9-_]/', '_', $invoiceData['number'] ?? 'invoice');
$timestamp = date('Y-m-d_H-i-s');
$newFilename = "{$invoiceNumber}_{$timestamp}.json";
$newFilepath = $archiveDir . '/' . $newFilename;

// Добавление метаданных
$invoiceData['_metadata'] = [
    'saved_at' => date('c'),
    'filename' => $newFilename,
    'version' => '1.0',
    'copied_from' => $sourceFilename
];

// Сохранение нового файла
$jsonData = json_encode($invoiceData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($jsonData === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to encode JSON data',
        'json_error' => json_last_error_msg()
    ]);
    exit;
}

$writeResult = file_put_contents($newFilepath, $jsonData);

if ($writeResult === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to save copied invoice'
    ]);
    exit;
}

// Устанавливаем права на созданный файл
@chmod($newFilepath, 0666);

// Успешный ответ
http_response_code(200);
echo json_encode([
    'success' => true,
    'filename' => $newFilename,
    'filepath' => $newFilepath,
    'message' => 'Invoice copied successfully',
    'documentType' => $documentType
]);


