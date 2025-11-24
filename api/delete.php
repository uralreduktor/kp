<?php
/**
 * API для удаления проформа-инвойса
 * Путь: /Proforma Invoise/api/delete.php
 */

require_once __DIR__ . '/auth_utils.php';

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

// Проверка прав на удаление
$permissionCheck = checkPermission('delete');
if ($permissionCheck !== null) {
    http_response_code(403);
    echo json_encode($permissionCheck);
    exit;
}

// Получение данных
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$filename = $data['filename'] ?? '';

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

// Попытка исправить права доступа перед удалением (если файл не доступен для записи)
if (!is_writable($filepath)) {
    // Пытаемся установить права 0666 (чтение и запись для всех)
    @chmod($filepath, 0666);
    
    // Проверяем снова после изменения прав
    if (!is_writable($filepath)) {
        // Получаем информацию о правах доступа
        $perms = fileperms($filepath);
        $owner = fileowner($filepath);
        $group = filegroup($filepath);
        $ownerInfo = @posix_getpwuid($owner);
        $groupInfo = @posix_getgrgid($group);
        
        http_response_code(500);
        error_log("Delete failed: File not writable. Path: $filepath, Perms: " . substr(sprintf('%o', $perms), -4) . ", Owner: " . ($ownerInfo['name'] ?? $owner) . ", Group: " . ($groupInfo['name'] ?? $group));
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to delete invoice: Permission denied',
            'details' => [
                'filepath' => $filepath,
                'permissions' => substr(sprintf('%o', $perms), -4),
                'owner' => $ownerInfo['name'] ?? $owner,
                'group' => $groupInfo['name'] ?? $group,
                'is_writable' => is_writable($filepath),
                'is_readable' => is_readable($filepath)
            ]
        ]);
        exit;
    }
}

// Удаление файла
if (!@unlink($filepath)) {
    $error = error_get_last();
    http_response_code(500);
    error_log("Delete failed: unlink() error. Path: $filepath, Error: " . ($error['message'] ?? 'Unknown error'));
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to delete invoice',
        'details' => [
            'filepath' => $filepath,
            'php_error' => $error['message'] ?? 'Unknown error',
            'is_writable' => is_writable($filepath),
            'is_readable' => is_readable($filepath),
            'file_exists' => file_exists($filepath)
        ]
    ]);
    exit;
}

// Успешный ответ
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Invoice deleted successfully'
]);

