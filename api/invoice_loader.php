<?php
/**
 * Общие функции для загрузки данных инвойса
 * Используется в generate_pdf.php и generate_pdf_playwright.php
 */

/**
 * Загружает данные инвойса из JSON файла
 * 
 * @param string $filename Имя файла JSON
 * @return array Массив с данными: ['invoiceData' => ..., 'orgId' => ..., 'filename' => ...]
 * @throws Exception Если файл не найден или данные невалидны
 */
function loadInvoiceData($filename) {
    // Защита от path traversal
    $filename = basename($filename);
    
    $archiveDir = dirname(__DIR__) . '/@archiv 2025';
    $jsonFile = $archiveDir . '/' . $filename;
    
    // Проверка существования файла
    if (!file_exists($jsonFile)) {
        throw new Exception('Invoice file not found', 404);
    }
    
    // Загрузка данных
    $jsonData = file_get_contents($jsonFile);
    $invoiceData = json_decode($jsonData, true);
    
    if (!$invoiceData) {
        throw new Exception('Invalid JSON data', 400);
    }
    
    // Определение организации
    $orgId = $invoiceData['organizationId'] ?? 'syreducer';
    
    return [
        'invoiceData' => $invoiceData,
        'orgId' => $orgId,
        'filename' => $filename,
        'archiveDir' => $archiveDir
    ];
}

/**
 * Валидирует параметр filename из GET запроса
 * 
 * @return string Валидное имя файла
 * @throws Exception Если параметр отсутствует
 */
function validateFilenameParameter() {
    if (!isset($_GET['filename']) || empty($_GET['filename'])) {
        throw new Exception('Filename parameter is required', 400);
    }
    
    return basename($_GET['filename']);
}

