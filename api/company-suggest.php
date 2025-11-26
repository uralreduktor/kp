<?php
/**
 * API для поиска организации по названию через DaData
 * Возвращает информацию о компании, включая ИНН
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Загружаем конфигурацию
$config = require_once 'config.php';

// Получаем параметры запроса
$query = $_GET['query'] ?? $_POST['query'] ?? '';
$count = intval($_GET['count'] ?? $_POST['count'] ?? 5);

// Проверка обязательных параметров
if (empty($query)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Параметр query обязателен'
    ]);
    exit();
}

// Минимальная длина запроса
if (mb_strlen($query) < 3) {
    echo json_encode([
        'success' => true,
        'suggestions' => []
    ]);
    exit();
}

// Функция поиска организации через DaData
function findCompany($query, $count, $config) {
    $url = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/party';
    
    $data = [
        'query' => $query,
        'count' => min($count, 10),
        'status' => ['ACTIVE'] // Только действующие организации
    ];
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Token ' . $config['dadata_token']
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, $config['request_timeout'] ?? 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return [
            'success' => false,
            'error' => 'Ошибка соединения: ' . $curlError
        ];
    }
    
    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => 'Ошибка API: HTTP ' . $httpCode
        ];
    }
    
    $result = json_decode($response, true);
    
    if (!$result || !isset($result['suggestions'])) {
        return [
            'success' => false,
            'error' => 'Некорректный ответ от DaData'
        ];
    }
    
    // Форматируем результаты
    $suggestions = [];
    foreach ($result['suggestions'] as $suggestion) {
        $data = $suggestion['data'];
        $suggestions[] = [
            'value' => $suggestion['value'], // Полное название
            'inn' => $data['inn'] ?? null,
            'kpp' => $data['kpp'] ?? null,
            'ogrn' => $data['ogrn'] ?? null,
            'name' => $data['name']['full_with_opf'] ?? $data['name']['full'] ?? $suggestion['value'],
            'name_short' => $data['name']['short_with_opf'] ?? $data['name']['short'] ?? null,
            'address' => $data['address']['unrestricted_value'] ?? $data['address']['value'] ?? null,
            'management' => $data['management']['name'] ?? null,
            'type' => $data['type'] ?? null, // LEGAL или INDIVIDUAL
            'status' => $data['state']['status'] ?? null
        ];
    }
    
    return [
        'success' => true,
        'suggestions' => $suggestions
    ];
}

try {
    $result = findCompany($query, $count, $config);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Внутренняя ошибка сервера: ' . $e->getMessage()
    ]);
}
?>

