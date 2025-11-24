<?php
/**
 * API для получения следующего номера инвойса
 * Путь: /Proforma Invoise/api/get_next_invoice_number.php
 * 
 * Параметры:
 * - orgId: ID организации (обязательно)
 * - date: Дата в формате Y-m-d (опционально, по умолчанию текущая дата)
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

// Получение параметров
$orgId = $_GET['orgId'] ?? null;
$date = $_GET['date'] ?? null;

if (!$orgId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'orgId parameter is required']);
    exit;
}

try {
    require_once __DIR__ . '/pdf_utils.php';
    require_once __DIR__ . '/organization_parser.php';
    
    // Директория архива
    $archiveDir = dirname(__DIR__) . '/@archiv 2025';
    
    // Загружаем данные организации
    $orgFile = dirname(__DIR__) . '/js/organizations.js';
    if (!file_exists($orgFile)) {
        throw new Exception('Organizations file not found');
    }
    
    $orgContent = file_get_contents($orgFile);
    $orgData = parseOrganizationData($orgId, $orgContent);
    
    if (empty($orgData['code'])) {
        // Если код не найден, используем первые 3 символа ID организации
        $orgCode = strtoupper(substr($orgId, 0, 3));
    } else {
        $orgCode = $orgData['code'];
    }
    
    // Генерируем следующий номер инвойса
    $invoiceNumber = generateInvoiceNumberCode($orgCode, $archiveDir, $date);
    
    // Успешный ответ
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'number' => $invoiceNumber,
        'orgCode' => $orgCode,
        'date' => $date ?? date('Y-m-d')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

