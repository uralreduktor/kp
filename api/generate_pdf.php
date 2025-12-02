<?php
/**
 * API для генерации PDF проформа-инвойса на сервере
 * Путь: /Proforma Invoise/api/generate_pdf.php
 * 
 * Использование: GET запрос с параметром filename
 * Пример: generate_pdf.php?filename=invoice_123.json
 * 
 * Требования:
 * - Установить mPDF: composer require mpdf/mpdf
 * - Или установить wkhtmltopdf: apt-get install wkhtmltopdf
 */

// Настройка логирования ошибок
ini_set('error_log', __DIR__ . '/error_log');
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Подключаем общие модули
require_once __DIR__ . '/invoice_loader.php';
require_once __DIR__ . '/invoice_html_generator.php';
require_once __DIR__ . '/pdf_utils.php';

// Загрузка данных инвойса
try {
    error_log('generate_pdf.php: Starting with filename=' . ($_GET['filename'] ?? 'NOT SET'));
    $filename = validateFilenameParameter();
    error_log('generate_pdf.php: Filename validated: ' . $filename);
    $loadedData = loadInvoiceData($filename);
    $orgId = $loadedData['orgId'];
    $invoiceData = $loadedData['invoiceData'];
    $archiveDir = $loadedData['archiveDir'];
    error_log('generate_pdf.php: Invoice data loaded successfully');
    $documentType = (isset($_GET['document']) && $_GET['document'] === 'technical') ? 'technical' : 'commercial';
} catch (Exception $e) {
    $code = $e->getCode() ?: 400;
    error_log('generate_pdf.php error: ' . $e->getMessage());
    error_log('generate_pdf.php error trace: ' . $e->getTraceAsString());
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $code
    ]);
    exit;
}

// Проверка доступности Playwright (приоритетный метод)
$playwrightScript = __DIR__ . '/generate_pdf_playwright.js';
$playwrightPhpScript = __DIR__ . '/generate_pdf_playwright.php';
$usePlaywright = false;

// Проверяем наличие Node.js и Playwright скрипта
if (file_exists($playwrightScript)) {
    $nodePath = findNodePath();
    if ($nodePath) {
        $usePlaywright = true;
    }
}

// Если Playwright доступен, используем его
if ($usePlaywright && file_exists($playwrightPhpScript)) {
    // Логирование для отладки
    error_log("Using Playwright for PDF generation");
    require_once $playwrightPhpScript;
    exit; // Скрипт Playwright сам обработает запрос
} else {
    // Логирование для отладки
    $debugInfo = "Playwright not available. ";
    $debugInfo .= "usePlaywright=" . ($usePlaywright ? 'true' : 'false') . ", ";
    $debugInfo .= "JS script exists=" . (file_exists($playwrightScript) ? 'true' : 'false') . ", ";
    $debugInfo .= "PHP script exists=" . (file_exists($playwrightPhpScript) ? 'true' : 'false');
    error_log($debugInfo);
}

// Попытка использовать mPDF (через Composer) как fallback
$mpdfPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($mpdfPath)) {
    require_once $mpdfPath;
    
    $tempDir = null;
    try {
        // Загружаем данные организации для получения кода
        $orgFile = dirname(__DIR__) . '/js/organizations.js';
        if (!file_exists($orgFile)) {
            throw new Exception('Organizations file not found');
        }
        $orgContent = file_get_contents($orgFile);
        require_once __DIR__ . '/organization_parser.php';
        $orgData = parseOrganizationData($orgId, $orgContent);
        $orgCode = !empty($orgData['code']) ? $orgData['code'] : strtoupper(substr($orgId, 0, 3));
        
        // Генерация HTML (используем стили для mPDF)
        $html = generateInvoiceHTML($invoiceData, $orgId, 'mpdf', $documentType);
        
        // Создание PDF
        // Используем системную временную директорию для mPDF
        $tempDir = sys_get_temp_dir() . '/mpdf_' . uniqid();
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => $tempDir, // Используем системную временную директорию
        ]);
        
        $mpdf->WriteHTML($html);
        
        $archiveDir = dirname(__DIR__) . '/@archiv 2025'; // Директория архива (с пробелом в названии)
        
        // Генерация имени файла PDF в новом формате
        $invoiceDate = $invoiceData['date'] ?? null;
        $label = $documentType === 'technical' ? 'Technical Appendix' : 'Commercial Proposal';
        $pdfFilename = generatePdfFilename($orgCode, $archiveDir, $invoiceDate, $label);
        $pdfPath = $archiveDir . '/' . $pdfFilename;
        
        // Проверка и исправление прав доступа к директории архива
        if (!is_dir($archiveDir)) {
            throw new Exception("Archive directory does not exist: $archiveDir");
        }
        
        // Проверка прав на запись в директорию
        if (!is_writable($archiveDir)) {
            // Попытка установить права на запись (если возможно)
            @chmod($archiveDir, 0775);
            
            // Повторная проверка
            if (!is_writable($archiveDir)) {
                $realPath = realpath($archiveDir) ?: $archiveDir;
                $dirPerms = substr(sprintf('%o', fileperms($archiveDir)), -4);
                $dirOwner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($archiveDir))['name'] : 'unknown';
                $dirGroup = function_exists('posix_getgrgid') ? posix_getgrgid(filegroup($archiveDir))['name'] : 'unknown';
                $phpUser = function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'unknown';
                
                $errorDetails = "Archive directory is not writable\n";
                $errorDetails .= "Directory path: $realPath\n";
                $errorDetails .= "Directory permissions: $dirPerms\n";
                $errorDetails .= "Directory owner: $dirOwner:$dirGroup\n";
                $errorDetails .= "PHP process user: $phpUser\n";
                $errorDetails .= "\nSOLUTION:\n";
                $errorDetails .= "Run the following command as root or with sudo:\n";
                $errorDetails .= "  sudo chown -R www-data:www-data '$realPath'\n";
                $errorDetails .= "  sudo chmod 775 '$realPath'\n";
                $errorDetails .= "\nOr if you want to keep current owner, change group to www-data:\n";
                $errorDetails .= "  sudo chgrp -R www-data '$realPath'\n";
                $errorDetails .= "  sudo chmod 775 '$realPath'\n";
                
                throw new Exception('Failed to save PDF to archive. ' . $errorDetails);
            }
        }
        
        // Удаляем старый файл, если он существует
        if (file_exists($pdfPath)) {
            // Проверяем права на существующий файл перед удалением
            if (!is_writable($pdfPath)) {
                // Пытаемся установить права на запись
                @chmod($pdfPath, 0664);
            }
            if (!@unlink($pdfPath)) {
                $errorDetails = "Cannot delete existing PDF file: $pdfPath\n";
                $errorDetails .= "File permissions: " . substr(sprintf('%o', fileperms($pdfPath)), -4) . "\n";
                $errorDetails .= "File owner: " . (function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($pdfPath))['name'] : 'unknown') . "\n";
                throw new Exception('Failed to save PDF to archive. ' . $errorDetails);
            }
        }
        
        // Сохранение PDF на сервере
        $mpdf->Output($pdfPath, 'F'); // F = file (сохранение на сервере)
        
        // Устанавливаем правильные права на созданный файл
        if (file_exists($pdfPath)) {
            @chmod($pdfPath, 0664);
        }
        
        // Отправка PDF в браузер
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $pdfFilename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($pdfPath));
        
        readfile($pdfPath); // Отправка сохраненного файла
        
        // Очистка временной директории после использования
        if ($tempDir && is_dir($tempDir)) {
            array_map('unlink', glob("$tempDir/*"));
            @rmdir($tempDir);
        }
        
        exit;
        
    } catch (Exception $e) {
        // Очистка временной директории в случае ошибки
        if ($tempDir && is_dir($tempDir)) {
            array_map('unlink', glob("$tempDir/*"));
            @rmdir($tempDir);
        }
        
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'PDF generation failed',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        exit;
    }
}

// Fallback: используем wkhtmltopdf если доступен
$wkhtmltopdfPath = '/usr/bin/wkhtmltopdf';
if (file_exists($wkhtmltopdfPath)) {
    $html = generateInvoiceHTML($invoiceData, $orgId, 'playwright', $documentType);
    $htmlPath = sys_get_temp_dir() . '/' . uniqid('invoice_') . '.html';
    $pdfPath = sys_get_temp_dir() . '/' . uniqid('invoice_') . '.pdf';
    
    file_put_contents($htmlPath, $html);
    
    $command = escapeshellcmd($wkhtmltopdfPath) . ' ' .
               '--page-size A4 ' .
               '--margin-top 10mm --margin-bottom 10mm ' .
               '--margin-left 10mm --margin-right 10mm ' .
               '--disable-smart-shrinking ' .
               '--encoding UTF-8 ' .
               '--quiet ' .
               escapeshellarg($htmlPath) . ' ' .
               escapeshellarg($pdfPath) . ' 2>&1';
    
    exec($command, $output, $returnCode);
    unlink($htmlPath);
    
    if ($returnCode === 0 && file_exists($pdfPath)) {
        // Загружаем данные организации для получения кода
        $orgFile = dirname(__DIR__) . '/js/organizations.js';
        if (file_exists($orgFile)) {
            $orgContent = file_get_contents($orgFile);
            require_once __DIR__ . '/organization_parser.php';
            $orgData = parseOrganizationData($orgId, $orgContent);
            $orgCode = !empty($orgData['code']) ? $orgData['code'] : strtoupper(substr($orgId, 0, 3));
            $archiveDir = dirname(__DIR__) . '/@archiv 2025';
            $invoiceDate = $invoiceData['date'] ?? null;
            $label = $documentType === 'technical' ? 'Technical Appendix' : 'Commercial Proposal';
            $pdfFilename = generatePdfFilename($orgCode, $archiveDir, $invoiceDate, $label);
        } else {
            $pdfFilename = str_replace('.json', '.pdf', $filename);
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $pdfFilename . '"');
        header('Content-Length: ' . filesize($pdfPath));
        readfile($pdfPath);
        unlink($pdfPath);
        exit;
    }
}

// Если ничего не доступно, возвращаем JSON ошибку
http_response_code(503);
header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'error' => 'PDF library not available',
    'message' => 'Please install mPDF (via Composer) or wkhtmltopdf',
    'install_mpdf' => 'composer require mpdf/mpdf',
    'install_wkhtmltopdf' => 'apt-get install wkhtmltopdf',
    'fallback_url' => '../pi.html?filename=' . urlencode($filename) . '&print=1'
]);
exit;

