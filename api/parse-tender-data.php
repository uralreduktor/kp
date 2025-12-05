<?php
/**
 * API для парсинга данных торгов по ссылке
 * Путь: /api/parse-tender-data.php?url=...
 */

require_once __DIR__ . '/connectors/ConnectorFactory.php';

// Вспомогательная функция для логирования
function parser_log($message) {
    $logFile = __DIR__ . '/error_log';
    $timestamp = date('[Y-m-d H:i:s]');
    $logMessage = "$timestamp Parser: $message\n";
    error_log($logMessage);
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Пытается определить Incoterm на основе текста условий поставки.
 * Используется для постобработки результатов
 */
function detect_delivery_incoterm_from_text($text) {
    if (!$text) return null;
    $textLower = mb_strtolower($text);
    $incotermCodeMap = [
        'DDP' => 'С ОПЛАТОЙ ДОСТАВКИ И СТРАХОВАНИЯ',
        'DAP' => 'ДО АДРЕСА ПОКУПАТЕЛЯ БЕЗ РАЗГРУЗКИ',
        'FCA' => 'ФРАНКО-СКЛАД ПРОДАВЦА',
        'EXW' => 'САМОВЫВОЗ',
        'CPT' => 'ФРАНКО-ТЕРМИНАЛ ТК'
    ];
    $rules = [
        'DDP' => [
            'patterns' => ['/DDP/i', '/включая\s+все\s+налоги/u', '/с\s+уплатой\s+всех\s+пошлин/u', '/с\s+растаможк/u'],
            'reason' => 'Указано условие DDP'
        ],
        'DAP' => [
            'patterns' => ['/поставка\s+на\s+место\s+назначения/u', '/доставка\s+должна\s+быть\s+включена/u', '/доставка\s+до\s+.*заказчика/u', '/поставка\s+до\s+.*заказчика/u'],
            'reason' => 'Есть формулировка о доставке до места назначения покупателя (DAP)'
        ],
        'FCA' => [
            'patterns' => ['/франко[-\s]+склад\s+продавца/u', '/франко[-\s]+завод/u', '/франко[-\s]+.*поставщика/u'],
            'reason' => 'Указан франко-склад или франко-завод (FCA)'
        ],
        'EXW' => [
            'patterns' => ['/самовывоз/u', '/со\s+склада\s+(?:продавца|поставщика)/u', '/забор\s+со\s+склада/u'],
            'reason' => 'Указан самовывоз или отгрузка со склада продавца (EXW)'
        ],
        'CPT' => [
            'patterns' => ['/доставка\s+до\s+терминала/u', '/доставка\s+до\s+станции/u'],
            'reason' => 'Указана доставка до терминала или станции (CPT)'
        ]
    ];

    foreach ($rules as $incoterm => $rule) {
        foreach ($rule['patterns'] as $pattern) {
            if (preg_match($pattern, $textLower)) {
                return [
                    'incoterm' => $incotermCodeMap[$incoterm] ?? $incoterm,
                    'reason' => $rule['reason']
                ];
            }
        }
    }
    return null;
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$url = $_GET['url'] ?? '';

// URL decode if encoded
if (strpos($url, '%') !== false) {
    $url = urldecode($url);
}

// Clean URL - remove trailing slash if it's not root
if (strlen($url) > 1) {
    $url = rtrim($url, '/');
}

if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid URL is required']);
    exit;
}

// Proxy to Python Service
// We do this to avoid PHP timeouts and complexity.
// The Python service handles everything (browser, stealth, parsing).
$apiUrl = 'http://127.0.0.1:8001/api/parsing/tender?url=' . urlencode($url);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 180); // 3 minutes timeout for complex parsing
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200 || $response === false) {
    parser_log("Python proxy failed: $error (HTTP $httpCode). Falling back to legacy PHP parser.");
    // Fallback to legacy PHP code below
} else {
    // Python service succeeded
    header('Content-Type: application/json');
    
    // Logging Python response structure
    $decoded = json_decode($response, true);
    if ($decoded && isset($decoded['data'])) {
        $inn = $decoded['data']['recipientINN'] ?? 'null';
        $name = $decoded['data']['recipient'] ?? 'null';
        $itemsCount = isset($decoded['data']['items']) ? count($decoded['data']['items']) : 0;
        parser_log("Python proxy success. Recipient: $name, INN: $inn, Items: $itemsCount");
    } else {
        parser_log("Python proxy returned non-JSON or unexpected format: " . substr($response, 0, 200));
    }
    
    echo $response;
    exit;
}

try {
    // ... legacy PHP code (now unreachable) ...
    $connector = ConnectorFactory::create($url);
    $connector->login(); // Пытаемся авторизоваться (если есть креды)
    
    parser_log("Processing URL: $url with " . get_class($connector));
    
    $data = $connector->parse($url);
    
    // Постобработка: DaData и Incoterm
    // 1. Incoterm
    if (!empty($data['deliveryConditions']) && empty($data['deliveryIncoterm'])) {
        $inc = detect_delivery_incoterm_from_text($data['deliveryConditions']);
        if ($inc) {
            $data['deliveryIncoterm'] = $inc['incoterm'];
            $data['deliveryIncotermReason'] = $inc['reason'];
        }
    }
    
    // 2. DaData (если нет ИНН, но есть название)
    if (empty($data['recipientINN']) && !empty($data['recipient'])) {
        $config = @include(__DIR__ . '/config.php');
        if ($config && !empty($config['dadata_token'])) {
            $dadataUrl = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/party';
            $reqData = ['query' => $data['recipient'], 'count' => 5, 'status' => ['ACTIVE']];
            
            $ch = curl_init($dadataUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($reqData),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Token ' . $config['dadata_token']],
                CURLOPT_TIMEOUT => 10
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);
            
            if ($resp) {
                $suggestions = json_decode($resp, true);
                if (!empty($suggestions['suggestions'])) {
                    $data['companySuggestions'] = [];
                    $deliveryRegion = '';
                    if (!empty($data['deliveryAddress']) && preg_match('/([\w\-]+(?:ая|ий|ой|ский|ская)\s+(?:область|край|республика)|г\.\s*\w+|Москва|Санкт-Петербург)/ui', $data['deliveryAddress'], $rm)) {
                        $deliveryRegion = $rm[1];
                    }
                    
                    foreach ($suggestions['suggestions'] as $sugg) {
                        $score = 0;
                        $compRegion = $sugg['data']['address']['data']['region_with_type'] ?? '';
                        if ($deliveryRegion && $compRegion && mb_stripos($compRegion, $deliveryRegion) !== false) {
                            $score = 100;
                        }
                        
                        $data['companySuggestions'][] = [
                            'inn' => $sugg['data']['inn'],
                            'name' => $sugg['value'],
                            'address' => $sugg['data']['address']['value'],
                            'matchScore' => $score
                        ];
                    }
                    
                    usort($data['companySuggestions'], function($a, $b) {
                                            return $b['matchScore'] - $a['matchScore'];
                                        });
                                        
                    if (!empty($data['companySuggestions']) && $data['companySuggestions'][0]['matchScore'] >= 80) {
                        $best = $data['companySuggestions'][0];
                        $data['recipientINN'] = $best['inn'];
                        $data['recipientAddress'] = $best['address'];
                        $data['autoMatchedCompany'] = true;
                    }
                }
            }
        }
    }
    
    $result = [
        'success' => true,
        'data' => $data,
        'platform' => get_class($connector)
    ];

} catch (Exception $e) {
    parser_log("Error: " . $e->getMessage());
    $result = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
