<?php
/**
 * Общие утилиты для генерации PDF
 * Используется в generate_pdf.php и generate_pdf_playwright.php
 */

/**
 * Форматирует число с двумя знаками после запятой
 * 
 * @param float $num Число для форматирования
 * @return string Отформатированное число
 */
function formatInvoiceNumber($num) {
    return number_format($num, 2, '.', ',');
}

/**
 * Форматирует дату в русский формат "DD месяц YYYY"
 * 
 * @param string $dateStr Дата в формате строки
 * @return string Отформатированная дата или 'N/A'
 */
function formatInvoiceDate($dateStr) {
    if (!$dateStr) return 'N/A';
    try {
        $date = new DateTime($dateStr);
        
        // Русские названия месяцев в именительном падеже
        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
        ];
        
        $day = (int)$date->format('d');
        $month = (int)$date->format('m');
        $year = $date->format('Y');
        
        return $day . ' ' . $months[$month] . ' ' . $year;
    } catch (Exception $e) {
        return 'N/A';
    }
}

/**
 * Форматирует телефон в кликабельную ссылку
 * 
 * @param string $phone Номер телефона
 * @return string HTML ссылка с tel: протоколом
 */
function formatPhoneLink($phone) {
    if (!$phone) return '';
    
    // Очищаем телефон от пробелов, скобок, дефисов для tel: ссылки
    $phoneClean = preg_replace('/[\s\(\)\-]/', '', $phone);
    
    // Если телефон начинается с +, оставляем как есть, иначе добавляем +
    if (strpos($phoneClean, '+') !== 0) {
        $phoneClean = '+' . $phoneClean;
    }
    
    return '<a href="tel:' . htmlspecialchars($phoneClean) . '" style="color: inherit; text-decoration: none;">' . htmlspecialchars($phone) . '</a>';
}

/**
 * Форматирует email в кликабельную ссылку
 * 
 * @param string $email Email адрес
 * @return string HTML ссылка с mailto: протоколом
 */
function formatEmailLink($email) {
    if (!$email) return '';
    
    return '<a href="mailto:' . htmlspecialchars($email) . '" style="color: inherit; text-decoration: none;">' . htmlspecialchars($email) . '</a>';
}

/**
 * Форматирует дату "Действительно до" в русский формат "DD месяц YYYY г."
 * 
 * @param string $dateStr Дата в формате строки (YYYY-MM-DD)
 * @return string Отформатированная дата или исходный текст, если это не дата
 */
function formatValidUntilDate($dateStr) {
    if (!$dateStr) return 'N/A';
    
    // Если это уже текст (не дата), возвращаем как есть
    if (strpos($dateStr, '-') === false && !preg_match('/^\d{4}-\d{2}-\d{2}/', $dateStr)) {
        return htmlspecialchars($dateStr);
    }
    
    try {
        $date = new DateTime($dateStr);
        
        // Русские названия месяцев
        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
        ];
        
        $day = (int)$date->format('d');
        $month = (int)$date->format('m');
        $year = $date->format('Y');
        
        return $day . ' ' . $months[$month] . ' ' . $year . ' г.';
    } catch (Exception $e) {
        // Если не удалось распарсить как дату, возвращаем исходный текст
        return htmlspecialchars($dateStr);
    }
}

/**
 * Конвертирует изображение в base64 data URI
 * Поддерживает PNG, JPG, GIF, SVG и другие форматы
 * Для SVG создает упрощенную версию без CSS переменных для лучшей совместимости с PDF
 * 
 * @param string $filename Путь к файлу изображения относительно базовой директории
 * @param string $baseDir Базовая директория проекта
 * @param bool $forPdf Если true, для SVG создается упрощенная версия без CSS переменных
 * @return string Data URI или пустая строка
 */
function imageToBase64($filename, $baseDir, $forPdf = false) {
    if (!$filename) return '';
    
    $filePath = $baseDir . '/' . $filename;
    
    if (!file_exists($filePath)) {
        error_log("Image file not found: $filePath");
        return '';
    }
    
    // Определяем расширение файла
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    
    // Определяем MIME тип на основе расширения
    $mimeTypes = [
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        'ico' => 'image/x-icon'
    ];
    
    $mimeType = $mimeTypes[$extension] ?? null;
    
    // Если MIME тип не определен по расширению, пытаемся определить через getimagesize
    if (!$mimeType) {
        $imageInfo = @getimagesize($filePath);
        $mimeType = $imageInfo ? $imageInfo['mime'] : 'image/png';
    }
    
    // Читаем содержимое файла
    $imageData = file_get_contents($filePath);
    
    if ($imageData === false) {
        error_log("Failed to read image file: $filePath");
        return '';
    }
    
    // Для SVG файлов обрабатываем специально
    if ($extension === 'svg') {
        // Проверяем, что файл начинается с XML или SVG тега
        $svgContent = trim($imageData);
        if (strpos($svgContent, '<svg') === false && strpos($svgContent, '<?xml') === false) {
            error_log("Invalid SVG file: $filePath");
            return '';
        }
        
        // Если для PDF, создаем упрощенную версию без CSS переменных
        if ($forPdf) {
            $svgContent = simplifySvgForPdf($svgContent);
            $imageData = $svgContent;
        }
    }
    
    return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
}

/**
 * Упрощает SVG для использования в PDF, убирая CSS переменные и media queries
 * Заменяет CSS переменные на конкретные цвета для светлой темы
 * 
 * @param string $svgContent Исходное содержимое SVG
 * @return string Упрощенное содержимое SVG
 */
function simplifySvgForPdf($svgContent) {
    // Убираем класс logo-shp из svg тега, если есть
    $svgContent = preg_replace('/<svg([^>]*)\s+class=["\']logo-shp["\']([^>]*)>/i', '<svg$1$2>', $svgContent);
    
    // Заменяем CSS переменные в стилях на конкретные значения для светлой темы
    // var(--logo-primary) -> #124981
    // var(--logo-secondary) -> #FEFEFE
    // var(--logo-gradient) -> url(#gradient_light)
    
    // Удаляем media queries из style тегов
    $svgContent = preg_replace('/@media\s+[^{]*\{[^}]*\}/s', '', $svgContent);
    
    // Заменяем CSS переменные в fill свойствах
    $svgContent = preg_replace('/fill:\s*var\(--logo-primary[^)]*\)/i', 'fill: #124981', $svgContent);
    $svgContent = preg_replace('/fill:\s*var\(--logo-secondary[^)]*\)/i', 'fill: #FEFEFE', $svgContent);
    $svgContent = preg_replace('/fill:\s*var\(--logo-gradient[^)]*\)/i', 'fill: url(#gradient_light)', $svgContent);
    
    // Заменяем var() в любых местах
    $svgContent = preg_replace('/var\(--logo-primary[^)]*\)/i', '#124981', $svgContent);
    $svgContent = preg_replace('/var\(--logo-secondary[^)]*\)/i', '#FEFEFE', $svgContent);
    $svgContent = preg_replace('/var\(--logo-gradient[^)]*\)/i', 'url(#gradient_light)', $svgContent);
    
    // Заменяем классы на прямые fill атрибуты в элементах path
    // fill атрибут в элементе имеет приоритет над CSS, поэтому просто добавляем его
    
    // .logo-primary -> добавляем fill="#124981" (переопределит CSS)
    $svgContent = preg_replace('/(<path[^>]*\s+class=["\'][^"\']*logo-primary[^"\']*["\'][^>]*)>/i', '$1 fill="#124981">', $svgContent);
    
    // .logo-secondary -> добавляем fill="#FEFEFE"
    $svgContent = preg_replace('/(<path[^>]*\s+class=["\'][^"\']*logo-secondary[^"\']*["\'][^>]*)>/i', '$1 fill="#FEFEFE">', $svgContent);
    
    // .logo-circle -> добавляем fill="url(#gradient_light)"
    $svgContent = preg_replace('/(<path[^>]*\s+class=["\'][^"\']*logo-circle[^"\']*["\'][^>]*)>/i', '$1 fill="url(#gradient_light)">', $svgContent);
    
    // Удаляем определения CSS переменных из style тегов
    $svgContent = preg_replace('/--logo-[^:]*:[^;]*;/', '', $svgContent);
    
    // Удаляем пустые style теги или style теги только с комментариями
    $svgContent = preg_replace('/<style[^>]*>\s*<!--[^>]*-->\s*<\/style>/i', '', $svgContent);
    $svgContent = preg_replace('/<style[^>]*>\s*<\/style>/i', '', $svgContent);
    
    return $svgContent;
}

/**
 * Находит путь к Node.js
 * 
 * @return string|null Путь к Node.js или null если не найден
 */
function findNodePath() {
    $possiblePaths = [
        '/usr/bin/node',
        '/usr/local/bin/node',
        trim(shell_exec('which node 2>/dev/null') ?: ''),
        'node'
    ];
    
    foreach ($possiblePaths as $path) {
        if (empty($path)) continue;
        $testCmd = escapeshellarg($path) . ' --version 2>&1';
        $testOutput = @shell_exec($testCmd);
        if ($testOutput && strpos($testOutput, 'v') === 0) {
            return $path;
        }
    }
    
    return null;
}

/**
 * Отправляет PDF файл в браузер
 * 
 * @param string $pdfPath Путь к PDF файлу
 * @param string $pdfFilename Имя файла для скачивания
 * @throws Exception Если файл не существует или недоступен
 */
function sendPdfToBrowser($pdfPath, $pdfFilename) {
    // Логирование перед отправкой
    error_log("sendPdfToBrowser: Starting. PDF path: $pdfPath, Filename: $pdfFilename");
    
    // Проверка существования и доступности файла
    if (!file_exists($pdfPath)) {
        error_log("sendPdfToBrowser: File not found: $pdfPath");
        throw new Exception('PDF file not found after generation: ' . $pdfPath);
    }
    
    if (!is_readable($pdfPath)) {
        error_log("sendPdfToBrowser: File not readable: $pdfPath");
        throw new Exception('PDF file is not readable: ' . $pdfPath);
    }
    
    $fileSize = filesize($pdfPath);
    if ($fileSize === false || $fileSize === 0) {
        error_log("sendPdfToBrowser: File size issue. Size: $fileSize");
        throw new Exception('PDF file is empty or size cannot be determined: ' . $pdfPath);
    }
    
    error_log("sendPdfToBrowser: File size OK: $fileSize bytes");
    
    // Отправка PDF в браузер
    if (!headers_sent()) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $pdfFilename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . $fileSize);
        error_log("sendPdfToBrowser: Headers sent");
    } else {
        error_log("sendPdfToBrowser: WARNING - Headers already sent!");
    }
    
    // Отключаем буферизацию вывода для больших файлов
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    error_log("sendPdfToBrowser: Reading file");
    readfile($pdfPath);
    error_log("sendPdfToBrowser: File sent successfully");
    exit;
}

/**
 * Генерирует номер инвойса в формате "DDMMYY-NN" (например, "251118-01")
 * 
 * @param string $orgCode Код организации (не используется, оставлен для совместимости)
 * @param string $archiveDir Директория архива для подсчета порядкового номера
 * @param string|null $date Дата в формате Y-m-d (если null, используется текущая дата)
 * @return string Номер инвойса
 */
function generateInvoiceNumberCode($orgCode, $archiveDir, $date = null) {
    // Используем текущую дату, если не указана
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    // Форматируем дату в формат DDMMYY
    $dateFormatted = date('dmy', strtotime($date));
    
    // Подсчитываем порядковый номер за текущую дату
    $sequenceNumber = getNextSequenceNumberForInvoice($orgCode, $dateFormatted, $archiveDir);
    
    // Формируем номер инвойса в формате DDMMYY-NN
    $invoiceNumber = sprintf('%s-%02d', 
        $dateFormatted, 
        $sequenceNumber
    );
    
    return $invoiceNumber;
}

/**
 * Получает следующий порядковый номер для инвойса за указанную дату
 * Сканирует JSON файлы в архиве для подсчета существующих номеров
 * 
 * @param string $orgCode Код организации (не используется, оставлен для совместимости)
 * @param string $dateFormatted Дата в формате DDMMYY (например, "251118")
 * @param string $archiveDir Директория архива
 * @return int Порядковый номер
 */
function getNextSequenceNumberForInvoice($orgCode, $dateFormatted, $archiveDir) {
    if (!is_dir($archiveDir)) {
        return 1;
    }
    
    // Паттерн для поиска номеров в формате DDMMYY-NN (например, "251118-01")
    $pattern = sprintf('/^%s-(\d+)$/i', 
        preg_quote($dateFormatted, '/')
    );
    
    $maxNumber = 0;
    $files = scandir($archiveDir);
    
    foreach ($files as $file) {
        // Проверяем только JSON файлы
        if (pathinfo($file, PATHINFO_EXTENSION) !== 'json') {
            continue;
        }
        
        $filePath = $archiveDir . '/' . $file;
        if (!is_file($filePath) || !is_readable($filePath)) {
            continue;
        }
        
        // Читаем содержимое JSON файла
        $content = @file_get_contents($filePath);
        if ($content === false) {
            continue;
        }
        
        $jsonData = @json_decode($content, true);
        if (!$jsonData || !isset($jsonData['number'])) {
            continue;
        }
        
        // Проверяем номер инвойса в формате DDMMYY-NN
        $invoiceNumber = trim($jsonData['number']);
        if (preg_match($pattern, $invoiceNumber, $matches)) {
            $number = (int)$matches[1];
            if ($number > $maxNumber) {
                $maxNumber = $number;
            }
        }
    }
    
    return $maxNumber + 1;
}

/**
 * Генерирует имя PDF файла в формате "Commercial Proposal SYP 2025_11_15 01"
 * 
 * @param string $orgCode Код организации (например, "SYP")
 * @param string $archiveDir Директория архива для подсчета порядкового номера
 * @param string|null $date Дата в формате Y-m-d (если null, используется текущая дата)
 * @return string Имя файла PDF
 */
function generatePdfFilename($orgCode, $archiveDir, $date = null, $label = 'Commercial Proposal') {
    // Используем текущую дату, если не указана
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    // Форматируем дату в формат YYYY_MM_DD
    $dateFormatted = date('Y_m_d', strtotime($date));
    
    // Подсчитываем порядковый номер за текущую дату
    $sequenceNumber = getNextSequenceNumber($orgCode, $dateFormatted, $archiveDir, $label);
    
    // Формируем имя файла
    $filename = sprintf('%s %s %s %02d.pdf', 
        $label,
        strtoupper($orgCode), 
        $dateFormatted, 
        $sequenceNumber
    );
    
    return $filename;
}

/**
 * Получает следующий порядковый номер для организации за указанную дату
 * 
 * @param string $orgCode Код организации
 * @param string $dateFormatted Дата в формате YYYY_MM_DD
 * @param string $archiveDir Директория архива
 * @return int Порядковый номер
 */
function getNextSequenceNumber($orgCode, $dateFormatted, $archiveDir, $label = 'Commercial Proposal') {
    if (!is_dir($archiveDir)) {
        return 1;
    }
    
    // Паттерн для поиска файлов: "Commercial Proposal SYP 2025_11_15 XX.pdf"
    $pattern = sprintf('/^%s %s %s (\d+)\.pdf$/i', 
        preg_quote($label, '/'),
        preg_quote($orgCode, '/'), 
        preg_quote($dateFormatted, '/')
    );
    
    $maxNumber = 0;
    $files = scandir($archiveDir);
    
    foreach ($files as $file) {
        if (preg_match($pattern, $file, $matches)) {
            $number = (int)$matches[1];
            if ($number > $maxNumber) {
                $maxNumber = $number;
            }
        }
    }
    
    return $maxNumber + 1;
}

/**
 * Форматирует банковские реквизиты в структурированный HTML
 * 
 * @param string $bankingDetails Текст банковских реквизитов
 * @return string HTML код с форматированными реквизитами
 */
function formatBankingDetails($bankingDetails) {
    if (!$bankingDetails || trim($bankingDetails) === '') {
        return '';
    }
    
    $html = '<div class="banking-details-list">';
    $lines = explode("\n", trim($bankingDetails));
    $hasItems = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }
        
        $hasItems = true;
        
        // Парсим строку в формате "Key: Value" или "Key Value"
        if (preg_match('/^(.+?):\s*(.+)$/', $line, $matches)) {
            $key = trim($matches[1]);
            $value = trim($matches[2]);
            $html .= '<div class="banking-detail-item">';
            $html .= '<span class="banking-detail-label">' . htmlspecialchars($key) . ':</span> ';
            $html .= '<span class="banking-detail-value">' . htmlspecialchars($value) . '</span>';
            $html .= '</div>';
        } else {
            // Если нет двоеточия, выводим как есть
            $html .= '<div class="banking-detail-item">';
            $html .= '<span class="banking-detail-value">' . htmlspecialchars($line) . '</span>';
            $html .= '</div>';
        }
    }
    
    $html .= '</div>';
    
    // Если не было элементов, возвращаем пустую строку
    if (!$hasItems) {
        return '';
    }
    
    return $html;
}

/**
 * Отправляет JSON ошибку
 * 
 * @param string $message Сообщение об ошибке
 * @param int $code HTTP код ответа
 * @param mixed $details Дополнительные детали ошибки
 */
function sendJsonError($message, $code = 500, $details = null) {
    // Очистка буфера вывода перед отправкой ошибки
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json');
    }
    
    $response = ['error' => $message];
    if ($details !== null) {
        $response['details'] = $details;
    }
    
    die(json_encode($response));
}

