<?php
/**
 * API для парсинга данных торгов по ссылке
 * Путь: /api/parse-tender-data.php?url=...
 */

// Вспомогательная функция для логирования в файл
function parser_log($message) {
    $logFile = __DIR__ . '/error_log';
    $timestamp = date('[Y-m-d H:i:s]');
    $logMessage = "$timestamp B2B-Center parser: $message\n";
    error_log($logMessage);
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Пытается определить Incoterm на основе текста условий поставки.
 *
 * @param string $text
 * @return array|null
 */
function detect_delivery_incoterm_from_text($text) {
    if (!$text) {
        return null;
    }

    $textLower = mb_strtolower($text);

    // Отображение международных обозначений на внутренние коды из справочника
    $incotermCodeMap = [
        'DDP' => 'С ОПЛАТОЙ ДОСТАВКИ И СТРАХОВАНИЯ',
        'DAP' => 'ДО АДРЕСА ПОКУПАТЕЛЯ БЕЗ РАЗГРУЗКИ',
        'FCA' => 'ФРАНКО-СКЛАД ПРОДАВЦА',
        'EXW' => 'САМОВЫВОЗ',
        'CPT' => 'ФРАНКО-ТЕРМИНАЛ ТК'
    ];

    $rules = [
        'DDP' => [
            'patterns' => [
                '/DDP/i',
                '/включая\s+все\s+налоги/u',
                '/с\s+уплатой\s+всех\s+пошлин/u',
                '/с\s+растаможк/u'
            ],
            'reason' => 'Указано условие DDP'
        ],
        'DAP' => [
            'patterns' => [
                '/поставка\s+на\s+место\s+назначения/u',
                '/доставка\s+должна\s+быть\s+включена/u',
                '/доставка\s+до\s+.*заказчика/u',
                '/поставка\s+до\s+.*заказчика/u'
            ],
            'reason' => 'Есть формулировка о доставке до места назначения покупателя (DAP)'
        ],
        'FCA' => [
            'patterns' => [
                '/франко[-\s]+склад\s+продавца/u',
                '/франко[-\s]+завод/u',
                '/франко[-\s]+.*поставщика/u'
            ],
            'reason' => 'Указан франко-склад или франко-завод (FCA)'
        ],
        'EXW' => [
            'patterns' => [
                '/самовывоз/u',
                '/со\s+склада\s+(?:продавца|поставщика)/u',
                '/забор\s+со\s+склада/u'
            ],
            'reason' => 'Указан самовывоз или отгрузка со склада продавца (EXW)'
        ],
        'CPT' => [
            'patterns' => [
                '/доставка\s+до\s+терминала/u',
                '/доставка\s+до\s+станции/u'
            ],
            'reason' => 'Указана доставка до терминала или станции (CPT)'
        ]
    ];

    foreach ($rules as $incoterm => $rule) {
        foreach ($rule['patterns'] as $pattern) {
            if (preg_match($pattern, $textLower)) {
                $mappedIncoterm = $incotermCodeMap[$incoterm] ?? $incoterm;
                return [
                    'incoterm' => $mappedIncoterm,
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

// Получение URL
$url = $_GET['url'] ?? '';

if (empty($url)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'URL parameter is required']);
    exit;
}

// Валидация URL
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid URL']);
    exit;
}

// Инициализация результата
$result = [
    'success' => false,
    'data' => [],
    'platform' => null
];

// Определение торговой площадки по URL
$platform = null;
$urlLower = strtolower($url);

if (strpos($urlLower, 'b2b-center.ru') !== false) {
    $platform = 'b2b-center';
} elseif (strpos($urlLower, 'tender.pro') !== false) {
    $platform = 'tender-pro';
} elseif (strpos($urlLower, 'sberbank-ast.ru') !== false) {
    $platform = 'sberbank-ast';
} elseif (strpos($urlLower, 'rts-tender.ru') !== false) {
    $platform = 'rts-tender';
} elseif (strpos($urlLower, 'roseltorg.ru') !== false || strpos($urlLower, 'eetp.ru') !== false) {
    $platform = 'eetp';
} elseif (strpos($urlLower, 'zakazrf.ru') !== false) {
    $platform = 'zakazrf';
} elseif (strpos($urlLower, 'fabrikant.ru') !== false) {
    $platform = 'fabrikant';
}

$result['platform'] = $platform;

// Попытка извлечения данных из URL
$parsedUrl = parse_url($url);
$path = $parsedUrl['path'] ?? '';
$extractedData = [];

// Определение торговой площадки по URL
$urlLower = strtolower($url);
$isB2BCenter = strpos($urlLower, 'b2b-center.ru') !== false;

// Извлечение номера торгов для B2B-Center
$tenderNumber = null;
if ($isB2BCenter) {
    // Паттерн для B2B-Center: /tender-4242870/ или ?id=4250788
    if (preg_match('/tender-(\d+)/i', $path, $matches)) {
        $tenderNumber = $matches[1];
        $extractedData['tenderNumber'] = $tenderNumber;
    } elseif (preg_match('/id=(\d+)/i', $parsedUrl['query'] ?? '', $matches)) {
        $tenderNumber = $matches[1];
        $extractedData['tenderNumber'] = $tenderNumber;
    }
} else {
    // Общий паттерн для других площадок
    if (preg_match('/(?:tender|lot|id|number)[\/=](\d+)/i', $path . ($parsedUrl['query'] ?? ''), $matches)) {
        $tenderNumber = $matches[1];
        $extractedData['tenderNumber'] = $tenderNumber;
    }
}

// Попытка парсинга HTML страницы (только для разрешенных доменов)
$allowedDomains = ['b2b-center.ru', 'tender.pro', 'sberbank-ast.ru', 'rts-tender.ru'];
$domain = parse_url($url, PHP_URL_HOST);
// Убираем www. из домена для проверки
$domainWithoutWww = preg_replace('/^www\./', '', $domain);

if ($domain && in_array($domainWithoutWww, $allowedDomains)) {
    parser_log("Domain $domain is allowed, starting HTML parsing");
    
    // Для B2B-Center сначала загружаем страницу с позициями
    $positionsUrl = $url;
    parser_log("isB2BCenter: " . ($isB2BCenter ? 'true' : 'false') . ", original URL: $url");
    
    if ($isB2BCenter && strpos($url, 'action=positions') === false) {
        // Добавляем параметр для страницы позиций
        // Проверяем, есть ли уже параметры в URL (знак ?)
        $cleanUrl = rtrim($url, '/');
        if (strpos($cleanUrl, '?') !== false) {
            // URL уже содержит параметры - добавляем через &
            $positionsUrl = $cleanUrl . '&action=positions';
        } else {
            // URL без параметров - добавляем через ?
            $positionsUrl = $cleanUrl . '?action=positions';
        }
        parser_log("B2B-Center detected, adding positions URL: $positionsUrl");
    } else if ($isB2BCenter) {
        parser_log("B2B-Center URL already has action=positions: $positionsUrl");
    }
    
    // Используем cURL для получения HTML
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $positionsUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1'
        ],
        CURLOPT_ENCODING => '' // Автоматическая декомпрессия
    ]);
    
    $html = @curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Логирование для отладки (можно убрать в продакшене)
    parser_log("URL=$positionsUrl, HTTP=$httpCode, Error=$curlError, HTML size=" . strlen($html));
    
    // Если не удалось загрузить страницу позиций, пробуем основную страницу
    if (!$html || $httpCode !== 200) {
        parser_log("Failed to load positions page, trying main page. HTTP=$httpCode");
        
        // Пробуем загрузить основную страницу тендера
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => ''
        ]);
        
        $html = @curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Логируем результат попытки загрузки основной страницы
        if ($html && $httpCode === 200) {
            parser_log("Main page loaded successfully. Size: " . strlen($html) . " bytes");
        } else {
            parser_log("Failed to load main page. HTTP=$httpCode, HTML size=" . strlen($html ?: ''));
        }
    }
    
    // Логируем финальный результат загрузки HTML перед парсингом
    parser_log("Before parsing - HTML exists: " . ($html ? 'yes' : 'no') . ", HTTP code: $httpCode, HTML size: " . strlen($html ?: ''));
    
    if ($html && $httpCode === 200) {
        parser_log("HTML loaded successfully. Size: " . strlen($html) . " bytes");
        
        // Создаем DOMDocument для парсинга
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        // Используем правильную кодировку
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Специальная обработка для B2B-Center
        if ($isB2BCenter) {
            parser_log("Starting HTML parsing. HTML size: " . strlen($html));
            
            // Извлечение данных из таблицы позиций
            // Сначала пробуем найти строки с классом c2 (специфично для B2B-Center)
            $dataRows = $xpath->query("//tr[contains(@class, 'c2')] | //tr[@class='c2']");
            
            parser_log("Found " . ($dataRows ? $dataRows->length : 0) . " rows with class 'c2'");
            
            // Если не нашли через класс c2, пробуем другие варианты
            if (!$dataRows || $dataRows->length === 0) {
                // Пробуем найти все строки таблицы
                $allTableRows = $xpath->query("//table//tr");
                parser_log("Found " . ($allTableRows ? $allTableRows->length : 0) . " total table rows");
                
                // Пробуем найти строки с данными (не заголовки)
                $dataRows = $xpath->query("//table//tr[td[position()>1]]");
                parser_log("Found " . ($dataRows ? $dataRows->length : 0) . " data rows (with multiple cells)");
            }
            
            if ($dataRows && $dataRows->length > 0) {
                // Нашли строки данных напрямую
                foreach ($dataRows as $row) {
                    $cells = $xpath->query(".//td", $row);
                    $cellArray = [];
                    
                    foreach ($cells as $cell) {
                        $cellText = trim($cell->textContent);
                        $cellArray[] = $cellText;
                    }
                    
                    // В B2B-Center структура: № | Наименование | Закупщик | Наименование позиции | Количество | Ед.изм | Цена | Общая стоимость
                    // Индексы: 0=№, 1=Наименование, 2=Закупщик, 3=Наименование позиции, 4=Количество, 5=Ед.изм, 6=Цена, 7=Общая стоимость
                    // Используем колонку "Наименование позиции" (индекс 3) и "Количество" (индекс 4)
                    
                    parser_log("Processing row " . ($index + 1) . " - has " . count($cellArray) . " cells");
                    if (count($cellArray) > 0) {
                        parser_log("Row " . ($index + 1) . " first 8 cells: " . json_encode(array_slice($cellArray, 0, 8), JSON_UNESCAPED_UNICODE));
                    }
                    
                    if (count($cellArray) >= 5) {
                        // Пробуем разные варианты индексов
                        $itemName = '';
                        $quantity = '';
                        
                        // Вариант 1: колонка "Наименование позиции" (обычно индекс 3)
                        if (isset($cellArray[3]) && !empty(trim($cellArray[3]))) {
                            $itemName = trim($cellArray[3]);
                            parser_log("Found item name at index 3: '$itemName'");
                        } 
                        // Вариант 2: колонка "Наименование" (обычно индекс 1)
                        elseif (isset($cellArray[1]) && !empty(trim($cellArray[1]))) {
                            $itemName = trim($cellArray[1]);
                            parser_log("Found item name at index 1: '$itemName'");
                        }
                        // Вариант 3: пробуем найти любую колонку с текстом, похожим на название товара
                        else {
                            foreach ($cellArray as $idx => $cellText) {
                                $cleanText = trim($cellText);
                                // Пропускаем номера, пустые строки, короткие строки
                                if (strlen($cleanText) > 10 && !preg_match('/^\d+$/', $cleanText) && !preg_match('/^(шт|кг|м|м2|м3)$/i', $cleanText)) {
                                    $itemName = $cleanText;
                                    parser_log("Found item name at index $idx: '$itemName'");
                                    break;
                                }
                            }
                        }
                        
                        // Количество обычно в индексе 4
                        if (isset($cellArray[4]) && !empty(trim($cellArray[4]))) {
                            $quantity = trim($cellArray[4]);
                            parser_log("Found quantity at index 4: '$quantity'");
                        }
                        // Пробуем найти количество в других колонках
                        else {
                            foreach ($cellArray as $idx => $cellText) {
                                $cleanText = trim($cellText);
                                // Ищем число
                                if (preg_match('/^(\d+(?:[.,]\d+)?)/', $cleanText, $matches)) {
                                    $quantity = $matches[1];
                                    parser_log("Found quantity at index $idx: '$quantity'");
                                    break;
                                }
                            }
                        }
                        
                        // Очищаем данные наименования
                        $itemName = str_replace(["\n", "\r", "\t"], ' ', $itemName);
                        $itemName = preg_replace('/\s+/', ' ', trim($itemName));
                        
                        // Извлекаем количество (может быть с единицами измерения)
                        $quantity = preg_replace('/[^\d.,]/', '', $quantity);
                        $quantity = str_replace(',', '.', $quantity);
                        
                        // Проверяем, что есть и название, и количество
                        // Убираем проверку на числовое значение количества, так как может быть "1" как строка
                        $quantityFloat = floatval($quantity);
                        
                        parser_log("Processing item - name: '$itemName', quantity: '$quantity' -> $quantityFloat");
                        
                        if (!empty($itemName) && strlen($itemName) > 2 && !empty($quantity) && $quantityFloat > 0) {
                            if (!isset($extractedData['items'])) {
                                $extractedData['items'] = [];
                            }
                            $extractedData['items'][] = [
                                'name' => $itemName,
                                'quantity' => $quantityFloat
                            ];
                            
                            parser_log("Added item: " . json_encode($extractedData['items'][count($extractedData['items'])-1], JSON_UNESCAPED_UNICODE));
                            
                            // Если это первая позиция, используем её для автозаполнения
                            if (count($extractedData['items']) === 1) {
                                $extractedData['itemName'] = $itemName;
                                $extractedData['quantity'] = $quantityFloat;
                            }
                        } else {
                            parser_log("Item validation failed - name empty: " . (empty($itemName) ? 'yes' : 'no') . ", name length: " . strlen($itemName) . ", quantity: $quantityFloat");
                        }
                    }
                }
            }
            
            // Логируем результат после первого прохода
            $itemsCount = isset($extractedData['items']) ? count($extractedData['items']) : 0;
            parser_log("After first pass, found $itemsCount items");
            
            // Если не нашли через класс c2, пробуем стандартный способ с заголовками
            if (!isset($extractedData['items']) || count($extractedData['items']) === 0) {
                parser_log("Trying alternative method with table headers");
                $tables = $xpath->query("//table");
                
                foreach ($tables as $table) {
                    $rows = $xpath->query(".//tr", $table);
                    
                    // Ищем строку заголовка с "Наименование позиции" и "Количество"
                    $headerFound = false;
                    $nameColIndex = -1;
                    $qtyColIndex = -1;
                    
                    $rowIndex = 0;
                    foreach ($rows as $row) {
                        $cells = $xpath->query(".//td | .//th", $row);
                        $cellArray = [];
                        
                        // Преобразуем NodeList в массив для удобства работы
                        foreach ($cells as $cell) {
                            $cellText = trim($cell->textContent);
                            $cellArray[] = $cellText;
                        }
                        
                        // Пропускаем пустые строки
                        if (empty(array_filter($cellArray))) {
                            $rowIndex++;
                            continue;
                        }
                        
                        // Ищем заголовки (может быть не в первой строке)
                        if (!$headerFound) {
                            foreach ($cellArray as $colIndex => $cellText) {
                                $cellTextLower = mb_strtolower($cellText, 'UTF-8');
                                // Ищем колонку с наименованием позиции (приоритет)
                                if (stripos($cellTextLower, 'наименование позиции') !== false) {
                                    $nameColIndex = $colIndex;
                                }
                                // Ищем колонку с количеством
                                if (stripos($cellTextLower, 'количество') !== false ||
                                    stripos($cellTextLower, 'кол-во') !== false ||
                                    stripos($cellTextLower, 'кол во') !== false) {
                                    $qtyColIndex = $colIndex;
                                }
                            }
                            
                            // Если не нашли "Наименование позиции", ищем просто "Наименование"
                            if ($nameColIndex < 0) {
                                foreach ($cellArray as $colIndex => $cellText) {
                                    $cellTextLower = mb_strtolower($cellText, 'UTF-8');
                                    if (stripos($cellTextLower, 'наименование') !== false ||
                                        stripos($cellTextLower, 'название') !== false) {
                                        $nameColIndex = $colIndex;
                                        break;
                                    }
                                }
                            }
                            
                            if ($nameColIndex >= 0 && $qtyColIndex >= 0) {
                                $headerFound = true;
                                $rowIndex++;
                                continue; // Пропускаем строку заголовка
                            }
                        }
                        
                        // Если заголовки найдены, извлекаем данные из строк данных
                        if ($headerFound) {
                            if (isset($cellArray[$nameColIndex]) && isset($cellArray[$qtyColIndex])) {
                                $itemName = $cellArray[$nameColIndex];
                                $quantity = $cellArray[$qtyColIndex];
                                
                                // Очищаем данные наименования
                                $itemName = str_replace(["\n", "\r", "\t"], ' ', $itemName);
                                $itemName = preg_replace('/\s+/', ' ', trim($itemName));
                                
                                // Извлекаем количество (может быть с единицами измерения)
                                $quantity = preg_replace('/[^\d.,]/', '', $quantity);
                                $quantity = str_replace(',', '.', $quantity);
                                
                                // Проверяем, что есть и название, и количество
                                if (!empty($itemName) && strlen($itemName) > 2 && !empty($quantity) && is_numeric($quantity)) {
                                    if (!isset($extractedData['items'])) {
                                        $extractedData['items'] = [];
                                    }
                                    $extractedData['items'][] = [
                                        'name' => $itemName,
                                        'quantity' => floatval($quantity)
                                    ];
                                    
                                    // Если это первая позиция, используем её для автозаполнения
                                    if (count($extractedData['items']) === 1) {
                                        $extractedData['itemName'] = $itemName;
                                        $extractedData['quantity'] = floatval($quantity);
                                    }
                                }
                            }
                        }
                        
                        $rowIndex++;
                    }
                    
                    if ($headerFound && isset($extractedData['items']) && count($extractedData['items']) > 0) {
                        parser_log("Found items using table headers method");
                        break; // Нашли нужную таблицу и извлекли данные
                    }
                }
            }
            
            // Логируем результат после второго прохода
            $itemsCount = isset($extractedData['items']) ? count($extractedData['items']) : 0;
            parser_log("After second pass, found $itemsCount items");
            
            // Если не нашли данные в таблице, пробуем альтернативные способы
            if (!isset($extractedData['items']) || count($extractedData['items']) === 0) {
                parser_log("Trying alternative methods (divs, lists)");
                // Пробуем найти данные в div-блоках или списках
                $positionDivs = $xpath->query("//div[contains(@class, 'position')] | //div[contains(@class, 'item')] | //li[contains(@class, 'position')]");
                
                foreach ($positionDivs as $div) {
                    $text = trim($div->textContent);
                    // Ищем паттерны типа "Редуктор ..." и количество
                    if (preg_match('/([А-Яа-яЁё\w\s\-\.]+(?:\s+[А-Яа-яЁё\w\s\-\.]+)*)\s*[:\-]?\s*(\d+(?:[.,]\d+)?)/u', $text, $matches)) {
                        $itemName = trim($matches[1]);
                        $quantity = floatval(str_replace(',', '.', $matches[2]));
                        
                        if (strlen($itemName) > 3 && $quantity > 0) {
                            if (!isset($extractedData['items'])) {
                                $extractedData['items'] = [];
                            }
                            $extractedData['items'][] = [
                                'name' => $itemName,
                                'quantity' => $quantity
                            ];
                            
                            if (count($extractedData['items']) === 1) {
                                $extractedData['itemName'] = $itemName;
                                $extractedData['quantity'] = $quantity;
                            }
                        }
                    }
                }
            }
            
            parser_log("Finished parsing HTML. Items found: " . (isset($extractedData['items']) ? count($extractedData['items']) : 0));
            
            // Также пытаемся найти заказчика на основной странице
            if (strpos($url, '?action=positions') === false) {
                $mainCh = curl_init();
                curl_setopt($mainCh, CURLOPT_URL, $url);
                curl_setopt($mainCh, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($mainCh, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($mainCh, CURLOPT_TIMEOUT, 10);
                curl_setopt($mainCh, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
                curl_setopt($mainCh, CURLOPT_SSL_VERIFYPEER, false);
                
                $mainHtml = @curl_exec($mainCh);
                curl_close($mainCh);
                
                if ($mainHtml) {
                    libxml_use_internal_errors(true);
                    $mainDom = new DOMDocument();
                    @$mainDom->loadHTML(mb_convert_encoding($mainHtml, 'HTML-ENTITIES', 'UTF-8'));
                    libxml_clear_errors();
                    
                    $mainXpath = new DOMXPath($mainDom);
                    
                    // Ищем заказчика и ссылку на его страницу
                    $customerNodes = $mainXpath->query("//*[contains(text(), 'Заказчик')]/following-sibling::*[1] | //*[contains(text(), 'Заказчики')]/following-sibling::*[1]");
                    if ($customerNodes && $customerNodes->length > 0) {
                        $customerNode = $customerNodes->item(0);
                        $customerText = trim($customerNode->textContent);
                        if (strlen($customerText) > 3 && strlen($customerText) < 200) {
                            $extractedData['recipient'] = $customerText;
                        }
                        
                        // Ищем ссылку на страницу заказчика (/firms/...)
                        $customerLink = null;
                        $linkNodes = $mainXpath->query(".//a[contains(@href, '/firms/')]", $customerNode);
                        if ($linkNodes && $linkNodes->length > 0) {
                            $customerLink = $linkNodes->item(0)->getAttribute('href');
                        } else {
                            // Попробуем найти ссылку рядом
                            $linkNodes = $mainXpath->query("//*[contains(text(), 'Заказчик')]/following-sibling::*//a[contains(@href, '/firms/')] | //*[contains(text(), 'Заказчики')]/following-sibling::*//a[contains(@href, '/firms/')]");
                            if ($linkNodes && $linkNodes->length > 0) {
                                $customerLink = $linkNodes->item(0)->getAttribute('href');
                            }
                        }
                        
                        // Если нашли ссылку на заказчика, загружаем его страницу для получения ИНН
                        if ($customerLink) {
                            parser_log("Found customer link: $customerLink");
                            
                            // Формируем полный URL
                            $customerUrl = $customerLink;
                            if (strpos($customerLink, 'http') !== 0) {
                                $customerUrl = 'https://www.b2b-center.ru' . $customerLink;
                            }
                            
                            // Загружаем страницу заказчика
                            $customerCh = curl_init();
                            curl_setopt_array($customerCh, [
                                CURLOPT_URL => $customerUrl,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_TIMEOUT => 15,
                                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                                CURLOPT_SSL_VERIFYPEER => false,
                                CURLOPT_ENCODING => ''
                            ]);
                            
                            $customerHtml = @curl_exec($customerCh);
                            $customerHttpCode = curl_getinfo($customerCh, CURLINFO_HTTP_CODE);
                            curl_close($customerCh);
                            
                            parser_log("Customer page loaded: HTTP=$customerHttpCode, size=" . strlen($customerHtml ?: ''));
                            
                            if ($customerHtml && $customerHttpCode === 200) {
                                // Парсим страницу заказчика для извлечения ИНН
                                libxml_use_internal_errors(true);
                                $customerDom = new DOMDocument();
                                @$customerDom->loadHTML(mb_convert_encoding($customerHtml, 'HTML-ENTITIES', 'UTF-8'));
                                libxml_clear_errors();
                                
                                $customerXpath = new DOMXPath($customerDom);
                                
                                // Ищем ИНН - обычно в таблице или рядом с текстом "ИНН"
                                $innPatterns = [
                                    "//*[contains(text(), 'ИНН')]/following-sibling::td[1]",
                                    "//*[contains(text(), 'ИНН')]/following-sibling::*[1]",
                                    "//td[contains(text(), 'ИНН')]/following-sibling::td[1]",
                                    "//th[contains(text(), 'ИНН')]/following-sibling::td[1]",
                                    "//*[@class='inn' or @id='inn']"
                                ];
                                
                                foreach ($innPatterns as $pattern) {
                                    $innNodes = $customerXpath->query($pattern);
                                    if ($innNodes && $innNodes->length > 0) {
                                        $innText = trim($innNodes->item(0)->textContent);
                                        // ИНН должен быть 10 или 12 цифр
                                        $innClean = preg_replace('/\D/', '', $innText);
                                        if (strlen($innClean) === 10 || strlen($innClean) === 12) {
                                            $extractedData['recipientINN'] = $innClean;
                                            parser_log("Found INN: $innClean");
                                            break;
                                        }
                                    }
                                }
                                
                                // Альтернативный поиск ИНН в тексте страницы
                                if (!isset($extractedData['recipientINN'])) {
                                    if (preg_match('/ИНН[:\s]*(\d{10}|\d{12})/i', $customerHtml, $innMatch)) {
                                        $extractedData['recipientINN'] = $innMatch[1];
                                        parser_log("Found INN via regex: " . $innMatch[1]);
                                    }
                                }
                            }
                        }
                        
                        // Ищем адрес места поставки для уточнения региона
                        $deliveryAddress = '';
                        $deliveryRegion = '';
                        $deliveryNodes = $mainXpath->query("//td[contains(@class, 'fname') and contains(text(), 'Адрес места поставки')]/following-sibling::td[1]");
                        if ($deliveryNodes && $deliveryNodes->length > 0) {
                            $deliveryAddress = trim($deliveryNodes->item(0)->textContent);
                            $extractedData['deliveryAddress'] = $deliveryAddress;
                            parser_log("Found delivery address: $deliveryAddress");
                            
                            // Извлекаем регион из адреса
                            if (preg_match('/([\w\-]+(?:ая|ий|ой|ский|ская)\s+(?:область|край|республика)|г\.\s*\w+|Москва|Санкт-Петербург)/ui', $deliveryAddress, $regionMatch)) {
                                $deliveryRegion = $regionMatch[1];
                                parser_log("Extracted region: $deliveryRegion");
                            }
                        }

                        // Анализируем условия поставки для предложения Incoterm
                        $deliveryConditionsNodes = $mainXpath->query("//td[contains(@class, 'fname') and contains(text(), 'Условия поставки')]/following-sibling::td[1]");
                        if ($deliveryConditionsNodes && $deliveryConditionsNodes->length > 0) {
                            $deliveryConditions = trim($deliveryConditionsNodes->item(0)->textContent);
                            if ($deliveryConditions !== '') {
                                $extractedData['deliveryConditions'] = $deliveryConditions;
                                parser_log("Found delivery conditions: $deliveryConditions");

                                $detectedIncoterm = detect_delivery_incoterm_from_text($deliveryConditions);
                                if ($detectedIncoterm) {
                                    $extractedData['deliveryIncoterm'] = $detectedIncoterm['incoterm'];
                                    $extractedData['deliveryIncotermReason'] = $detectedIncoterm['reason'];
                                    parser_log("Detected delivery Incoterm: {$detectedIncoterm['incoterm']} ({$detectedIncoterm['reason']})");
                                }
                            }
                        }
                        
                        // Если ИНН не найден на странице заказчика, ищем через DaData API по названию
                        if (!isset($extractedData['recipientINN']) && !empty($extractedData['recipient'])) {
                            parser_log("INN not found on customer page, trying DaData API for: " . $extractedData['recipient']);
                            
                            // Загружаем конфигурацию для DaData
                            $config = @include(__DIR__ . '/config.php');
                            if ($config && !empty($config['dadata_token'])) {
                                $dadataUrl = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/party';
                                $dadataData = [
                                    'query' => $extractedData['recipient'],
                                    'count' => 10, // Получаем несколько вариантов
                                    'status' => ['ACTIVE']
                                ];
                                
                                $dadataCh = curl_init($dadataUrl);
                                curl_setopt_array($dadataCh, [
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_POST => true,
                                    CURLOPT_POSTFIELDS => json_encode($dadataData),
                                    CURLOPT_HTTPHEADER => [
                                        'Content-Type: application/json',
                                        'Accept: application/json',
                                        'Authorization: Token ' . $config['dadata_token']
                                    ],
                                    CURLOPT_TIMEOUT => 10
                                ]);
                                
                                $dadataResponse = @curl_exec($dadataCh);
                                $dadataHttpCode = curl_getinfo($dadataCh, CURLINFO_HTTP_CODE);
                                curl_close($dadataCh);
                                
                                if ($dadataResponse && $dadataHttpCode === 200) {
                                    $dadataResult = json_decode($dadataResponse, true);
                                    
                                    if (!empty($dadataResult['suggestions'])) {
                                        $companySuggestions = [];
                                        $bestMatch = null;
                                        $bestMatchScore = 0;
                                        
                                        foreach ($dadataResult['suggestions'] as $suggestion) {
                                            $data = $suggestion['data'];
                                            $companyAddress = $data['address']['value'] ?? '';
                                            $companyRegion = $data['address']['data']['region_with_type'] ?? '';
                                            
                                            // Рассчитываем совпадение с адресом поставки
                                            $matchScore = 0;
                                            if ($deliveryRegion && $companyRegion) {
                                                if (mb_stripos($companyRegion, $deliveryRegion) !== false || 
                                                    mb_stripos($deliveryRegion, $companyRegion) !== false) {
                                                    $matchScore = 100;
                                                } elseif ($deliveryAddress && mb_stripos($companyAddress, $deliveryAddress) !== false) {
                                                    $matchScore = 80;
                                                }
                                            }
                                            
                                            // Проверяем город из адреса поставки
                                            if ($matchScore === 0 && $deliveryAddress) {
                                                if (preg_match('/г\.\s*(\w+)/ui', $deliveryAddress, $cityMatch)) {
                                                    $deliveryCity = $cityMatch[1];
                                                    if (mb_stripos($companyAddress, $deliveryCity) !== false) {
                                                        $matchScore = 90;
                                                    }
                                                }
                                            }
                                            
                                            $companyInfo = [
                                                'inn' => $data['inn'] ?? null,
                                                'kpp' => $data['kpp'] ?? null,
                                                'name' => $data['name']['short_with_opf'] ?? $data['name']['full_with_opf'] ?? $suggestion['value'],
                                                'nameFull' => $data['name']['full_with_opf'] ?? $suggestion['value'],
                                                'address' => $companyAddress,
                                                'region' => $companyRegion,
                                                'matchScore' => $matchScore
                                            ];
                                            
                                            $companySuggestions[] = $companyInfo;
                                            
                                            // Запоминаем лучшее совпадение
                                            if ($matchScore > $bestMatchScore) {
                                                $bestMatchScore = $matchScore;
                                                $bestMatch = $companyInfo;
                                            }
                                        }
                                        
                                        // Сортируем по matchScore (убывание)
                                        usort($companySuggestions, function($a, $b) {
                                            return $b['matchScore'] - $a['matchScore'];
                                        });
                                        
                                        // Возвращаем варианты для выбора
                                        $extractedData['companySuggestions'] = $companySuggestions;
                                        parser_log("Found " . count($companySuggestions) . " company suggestions");
                                        
                                        // Если есть хорошее совпадение (>= 80), автоматически заполняем
                                        if ($bestMatch && $bestMatchScore >= 80) {
                                            $extractedData['recipientINN'] = $bestMatch['inn'];
                                            $extractedData['recipientAddress'] = $bestMatch['address'];
                                            $extractedData['autoMatchedCompany'] = true;
                                            parser_log("Auto-matched company with score $bestMatchScore: " . $bestMatch['name'] . ", INN: " . $bestMatch['inn']);
                                        } else {
                                            parser_log("No strong match found, returning suggestions for user selection");
                                        }
                                    }
                                } else {
                                    parser_log("DaData API failed: HTTP=$dadataHttpCode");
                                }
                            } else {
                                parser_log("DaData config not found or token missing");
                            }
                        }
                    }
                }
            }
        } else {
            // Обработка для других площадок
            $recipientSelectors = [
                "//*[contains(@class, 'customer')]",
                "//*[contains(@class, 'organiz')]",
                "//*[contains(@class, 'zakazchik')]",
                "//*[contains(text(), 'Заказчик')]/following-sibling::*[1]",
                "//*[contains(text(), 'Организатор')]/following-sibling::*[1]",
                "//h1",
                "//h2"
            ];
            
            foreach ($recipientSelectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes && $nodes->length > 0) {
                    $text = trim($nodes->item(0)->textContent);
                    if (strlen($text) > 5 && strlen($text) < 200) {
                        $extractedData['recipient'] = $text;
                        break;
                    }
                }
            }
            
            // Попытка найти номер торгов в тексте страницы
            if (!$tenderNumber) {
                $textContent = $dom->textContent;
                if (preg_match('/№?\s*[:\-]?\s*(\d{4,})/i', $textContent, $matches)) {
                    $tenderNumber = $matches[1];
                    $extractedData['tenderNumber'] = $tenderNumber;
                }
            }
            
            parser_log("Finished parsing HTML (other platforms). Items found: " . (isset($extractedData['items']) ? count($extractedData['items']) : 0));
        }
    } else {
        // HTML не загружен или ошибка HTTP
        parser_log("HTML parsing skipped - HTML not loaded or HTTP error. HTML exists: " . ($html ? 'yes' : 'no') . ", HTTP code: $httpCode");
    }
} else {
    parser_log("HTML parsing skipped - domain $domain not in allowed list");
}

// Если удалось извлечь данные, возвращаем успех
if (!empty($extractedData)) {
    // Логируем финальный результат
    $itemsCount = isset($extractedData['items']) ? count($extractedData['items']) : 0;
    parser_log("Final result - items count: $itemsCount, extractedData keys: " . implode(', ', array_keys($extractedData)));
    
    $result['success'] = true;
    $result['data'] = $extractedData;
    
    if ($itemsCount > 0) {
        parser_log("Returning success with $itemsCount items");
    } else {
        parser_log("WARNING - Returning success but NO ITEMS found!");
    }
} else {
    // Возвращаем успех даже если данных нет, но определили платформу
    parser_log("No data extracted, but platform identified. Returning empty data.");
    $result['success'] = true;
    $result['data'] = [];
}

http_response_code(200);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

