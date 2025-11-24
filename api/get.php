<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    $id = $_GET['id'] ?? '';
    
    if (empty($id)) {
        throw new Exception('ID parameter is required');
    }
    
    // Безопасность: убираем потенциально опасные символы
    $id = preg_replace('/[^a-zA-Z0-9\-_]/', '', $id);
    
    $archiveDir = dirname(__DIR__) . '/@archiv 2025';
    $filename = $archiveDir . '/' . $id . '.json';
    
    if (!file_exists($filename)) {
        http_response_code(404);
        throw new Exception('Invoice not found');
    }
    
    $content = file_get_contents($filename);
    $data = json_decode($content, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON file');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    
} catch (Exception $e) {
    http_response_code(isset($filename) && !file_exists($filename) ? 404 : 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

