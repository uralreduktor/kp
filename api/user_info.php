<?php
/**
 * API для получения информации о текущем пользователе и его правах
 * Путь: /Proforma Invoise/api/user_info.php
 */

require_once __DIR__ . '/auth_utils.php';

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

$username = getCurrentUser();
$permissions = getUserPermissions();

http_response_code(200);
echo json_encode([
    'success' => true,
    'user' => [
        'username' => $username,
        'isAuthenticated' => $username !== null,
        'permissions' => $permissions
    ]
]);

