<?php
/**
 * API для сохранения коммерческих предложений в JSON формате
 * Путь: /Proforma Invoise/api/save.php
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

// Директория для сохранения
$archiveDir = dirname(__DIR__) . '/@archiv 2025';

// Автоматическая генерация номера инвойса, если не указан или пустой
if (empty($data['number']) || trim($data['number']) === '') {
    require_once __DIR__ . '/pdf_utils.php';
    require_once __DIR__ . '/organization_parser.php';
    
    // Определяем код организации
    $orgId = $data['orgId'] ?? $data['organizationId'] ?? null;
    if ($orgId) {
        $orgFile = dirname(__DIR__) . '/js/organizations.js';
        if (file_exists($orgFile)) {
            $orgContent = file_get_contents($orgFile);
            $orgData = parseOrganizationData($orgId, $orgContent);
            $orgCode = !empty($orgData['code']) ? $orgData['code'] : strtoupper(substr($orgId, 0, 3));
            
            // Генерируем номер инвойса
            $invoiceDate = $data['date'] ?? null;
            $data['number'] = generateInvoiceNumberCode($orgCode, $archiveDir, $invoiceDate);
        }
    }
}

// Создание директории если не существует
if (!is_dir($archiveDir)) {
    $parentDir = dirname($archiveDir);
    if (!is_dir($parentDir)) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'Parent directory does not exist',
            'debug' => [
                'path' => $archiveDir,
                'parent' => $parentDir,
                'parent_exists' => file_exists($parentDir),
                'parent_is_dir' => is_dir($parentDir),
                'parent_writable' => is_writable($parentDir)
            ]
        ]);
        exit;
    }
    
    if (!mkdir($archiveDir, 0777, true)) {
        $lastError = error_get_last();
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to create archive directory',
            'debug' => [
                'path' => $archiveDir,
                'exists' => file_exists($archiveDir),
                'is_dir' => is_dir($archiveDir),
                'parent_exists' => file_exists($parentDir),
                'parent_writable' => is_writable($parentDir),
                'php_error' => $lastError ? $lastError['message'] : 'No error',
                'php_user' => function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'unknown'
            ]
        ]);
        exit;
    }
    // Устанавливаем права после создания (777 для доступа www-data)
    @chmod($archiveDir, 0777);
}

// Проверка прав на запись
if (!is_writable($archiveDir)) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Archive directory is not writable',
        'debug' => [
            'path' => $archiveDir,
            'realpath' => realpath($archiveDir),
            'permissions' => substr(sprintf('%o', fileperms($archiveDir)), -4),
            'owner' => posix_getpwuid(fileowner($archiveDir))['name'],
            'group' => posix_getgrgid(filegroup($archiveDir))['name'],
            'php_user' => posix_getpwuid(posix_geteuid())['name']
        ]
    ]);
    exit;
}

// Генерация имени файла
// Если указано имя файла для перезаписи, используем его
$filename = null;
if (!empty($data['_filename']) && is_string($data['_filename'])) {
    // Проверяем, что имя файла безопасно (только буквы, цифры, дефисы, подчеркивания, точки)
    $proposedFilename = basename($data['_filename']);
    if (preg_match('/^[a-zA-Z0-9._-]+\.json$/', $proposedFilename)) {
        // Для временных файлов preview всегда создаем файл с указанным именем
        if (strpos($proposedFilename, 'temp_preview_') === 0) {
            $filename = $proposedFilename;
        } else {
            // Для обычных файлов - перезаписываем, если имя валидно
            $filename = $proposedFilename;
        }
    }
}

// Если файл не указан или не существует, создаем новый
if (!$filename) {
    $invoiceNumber = preg_replace('/[^a-zA-Z0-9-_]/', '_', $data['number'] ?? 'invoice');
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "{$invoiceNumber}_{$timestamp}.json";
    $filepath = $archiveDir . '/' . $filename;
} else {
    $filepath = $archiveDir . '/' . $filename;
}

// Удаляем служебное поле из данных перед сохранением
unset($data['_filename']);

// Добавление метаданных
$data['_metadata'] = [
    'saved_at' => date('c'),
    'filename' => $filename,
    'version' => '1.0'
];

// Сохранение файла
$jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($jsonData === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to encode JSON data',
        'debug' => [
            'json_error' => json_last_error_msg(),
            'data_keys' => array_keys($data)
        ]
    ]);
    exit;
}

$writeResult = file_put_contents($filepath, $jsonData);

if ($writeResult === false) {
    http_response_code(500);
    $error_info = error_get_last();
    $phpUser = function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user();
    $dirPerms = substr(sprintf('%o', fileperms($archiveDir)), -4);
    $dirOwner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($archiveDir))['name'] : 'unknown';
    $dirGroup = function_exists('posix_getgrgid') ? posix_getgrgid(filegroup($archiveDir))['name'] : 'unknown';
    
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to save file',
        'debug' => [
            'filepath' => $filepath,
            'directory' => $archiveDir,
            'directory_exists' => is_dir($archiveDir),
            'directory_writable' => is_writable($archiveDir),
            'directory_permissions' => $dirPerms,
            'directory_owner' => $dirOwner . ':' . $dirGroup,
            'file_exists' => file_exists($filepath),
            'file_writable' => file_exists($filepath) ? is_writable($filepath) : 'N/A',
            'php_error' => $error_info ? $error_info['message'] : 'No PHP error',
            'php_user' => $phpUser,
            'disk_free_space' => disk_free_space($archiveDir),
            'json_size' => strlen($jsonData)
        ]
    ]);
    exit;
}

// Устанавливаем права на созданный файл
@chmod($filepath, 0666);

// Успешный ответ
http_response_code(200);
echo json_encode([
    'success' => true,
    'filename' => $filename,
    'filepath' => $filepath,
    'message' => 'Invoice saved successfully'
]);
