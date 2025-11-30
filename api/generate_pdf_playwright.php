<?php
/**
 * API для генерации PDF проформа-инвойса на сервере через Playwright (Chrome Headless)
 * Путь: /Proforma Invoise/api/generate_pdf_playwright.php
 * 
 * Использование: GET запрос с параметром filename
 * Пример: generate_pdf_playwright.php?filename=invoice_123.json
 * 
 * Требования:
 * - Установить Node.js (версия 16+)
 * - Установить Playwright: npm install -g playwright && playwright install chromium
 * - Или локально: cd /var/www/kp && npm install playwright && npx playwright install chromium
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
    $filename = validateFilenameParameter();
    $loadedData = loadInvoiceData($filename);
    $orgId = $loadedData['orgId'];
    $invoiceData = $loadedData['invoiceData'];
    $archiveDir = $loadedData['archiveDir'];
    $documentType = (isset($_GET['document']) && $_GET['document'] === 'technical') ? 'technical' : 'commercial';
} catch (Exception $e) {
    $code = $e->getCode() ?: 400;
    sendJsonError($e->getMessage(), $code);
}

try {
    // Логирование начала генерации
    error_log("Starting PDF generation for filename: " . $filename);
    
    // Загружаем данные организации для получения кода
    $orgFile = dirname(__DIR__) . '/js/organizations.js';
    if (!file_exists($orgFile)) {
        error_log("Organizations file not found: " . $orgFile);
        throw new Exception('Organizations file not found');
    }
    $orgContent = file_get_contents($orgFile);
    require_once __DIR__ . '/organization_parser.php';
    $orgData = parseOrganizationData($orgId, $orgContent);
    $orgCode = !empty($orgData['code']) ? $orgData['code'] : strtoupper(substr($orgId, 0, 3));
    
    // Генерация HTML (используем стили для Playwright)
    $html = generateInvoiceHTML($invoiceData, $orgId, 'playwright', $documentType);
    
    // Создание временного файла для HTML
    $tempDir = sys_get_temp_dir() . '/playwright_pdf_' . uniqid();
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
    
    $htmlFile = $tempDir . '/invoice.html';
    file_put_contents($htmlFile, $html);
    
    // Генерация имени файла PDF в новом формате
    $invoiceDate = $invoiceData['date'] ?? null;
    $label = $documentType === 'technical' ? 'Technical Appendix' : 'Proforma Invoice';
    $pdfFilename = generatePdfFilename($orgCode, $archiveDir, $invoiceDate, $label);
    
    // Создаем PDF во временной директории (где есть права на запись)
    $tempPdfPath = $tempDir . '/' . $pdfFilename;
    $pdfPath = $archiveDir . '/' . $pdfFilename; // Финальный путь
    
    // Путь к Node.js скрипту
    $scriptDir = __DIR__;
    $nodeScript = $scriptDir . '/generate_pdf_playwright.js';
    
    // Проверка существования Node.js скрипта
    if (!file_exists($nodeScript)) {
        throw new Exception('Playwright script not found. Please create generate_pdf_playwright.js');
    }
    
    // Поиск пути к Node.js
    $nodePath = findNodePath();
    if (!$nodePath) {
        throw new Exception('Node.js not found. Please install Node.js or check PATH.');
    }
    
    // Вызов Node.js скрипта через exec
    // Передаем пути к HTML и PDF файлам
    // escapeshellarg уже добавляет кавычки, поэтому не нужно добавлять их вручную
    $command = sprintf(
        '%s %s %s %s 2>&1',
        escapeshellarg($nodePath),
        escapeshellarg($nodeScript),
        escapeshellarg($htmlFile),
        escapeshellarg($tempPdfPath) // Используем временный путь
    );
    
    // Установка переменных окружения для Playwright
    // Указываем путь к браузерам Playwright (может быть в разных местах)
    // Приоритет: сначала проверяем симлинк (доступен для веб-сервера), потом другие пути
    $playwrightCachePaths = [
        '/var/www/.cache/ms-playwright', // Приоритет: симлинк, доступен для веб-сервера
        '/home/serveradmin/.cache/ms-playwright', // Где точно установлен браузер (может быть недоступен для www-data)
        dirname(__DIR__) . '/.playwright', // Локальная установка в проекте
        getenv('HOME') . '/.cache/ms-playwright', // Домашняя директория текущего пользователя
        sys_get_temp_dir() . '/.cache/ms-playwright'
    ];
    
    $playwrightCachePath = null;
    $currentUser = function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'unknown';
    error_log("Searching for Playwright browser (current user: $currentUser, HOME: " . getenv('HOME') . ") in paths: " . implode(', ', $playwrightCachePaths));
    
    foreach ($playwrightCachePaths as $path) {
        error_log("Checking path: $path");
        $isDir = @is_dir($path);
        $isReadable = @is_readable($path);
        $realPath = @realpath($path);
        error_log("  Path exists: " . ($isDir ? 'yes' : 'no') . ", readable: " . ($isReadable ? 'yes' : 'no') . ", realpath: " . ($realPath ?: 'N/A'));
        
        if ($isDir && $isReadable) {
            // Проверяем браузер в разных версиях и структурах
            // Новая структура (версия 1200+): chromium_headless_shell-1200/chrome-headless-shell-linux64/chrome-headless-shell
            // Старая структура (версия <1200): chromium_headless_shell-1194/chrome-linux/headless_shell
            $browserFound = false;
            $browserPath = null;
            
            // Сначала проверяем новые версии (1200+) с новой структурой
            $newVersionPatterns = [
                '/chromium_headless_shell-1200/chrome-headless-shell-linux64/chrome-headless-shell',
                '/chromium_headless_shell-1201/chrome-headless-shell-linux64/chrome-headless-shell',
                '/chromium_headless_shell-1202/chrome-headless-shell-linux64/chrome-headless-shell',
            ];
            
            foreach ($newVersionPatterns as $pattern) {
                $testPath = $path . $pattern;
                if (@file_exists($testPath) && @is_executable($testPath)) {
                    $browserPath = $testPath;
                    $browserFound = true;
                    error_log("  Found browser (new structure): $testPath");
                    break;
                }
            }
            
            // Если не нашли в новой структуре, проверяем старую структуру
            if (!$browserFound) {
                $oldVersionPatterns = [
                    '/chromium_headless_shell-1194/chrome-linux/headless_shell',
                    '/chromium_headless_shell-1187/chrome-linux/headless_shell',
                    '/chromium_headless_shell-1181/chrome-linux/headless_shell',
                ];
                
                foreach ($oldVersionPatterns as $pattern) {
                    $testPath = $path . $pattern;
                    if (@file_exists($testPath) && @is_executable($testPath)) {
                        $browserPath = $testPath;
                        $browserFound = true;
                        error_log("  Found browser (old structure): $testPath");
                        break;
                    }
                }
            }
            
            if ($browserFound && $browserPath) {
                $browserRealPath = @realpath($browserPath);
                error_log("  Browser path: $browserPath");
                error_log("  Browser realpath: " . ($browserRealPath ?: 'N/A'));
                
                // Используем путь как есть - симлинк должен работать
                // Playwright сам разрешит симлинк при поиске браузера
                $playwrightCachePath = $path;
                error_log("Found Playwright browser at: $path");
                
                // Дополнительная проверка: если это симлинк, логируем реальный путь
                if ($browserRealPath && $browserRealPath !== $browserPath) {
                    error_log("  Browser is symlink, real path: $browserRealPath");
                }
                break;
            } else {
                error_log("  No browser found in this path");
            }
        } else {
            // Логируем причину недоступности
            if (!$isDir) {
                error_log("  Path is not a directory");
            }
            if (!$isReadable) {
                error_log("  Path is not readable (check permissions)");
            }
        }
    }
    
    // Если не нашли браузер, пробуем найти любую версию
    if (!$playwrightCachePath) {
        error_log("Browser not found in specific version, searching for any version...");
        foreach ($playwrightCachePaths as $path) {
            $isDir = @is_dir($path);
            $isReadable = @is_readable($path);
            if ($isDir && $isReadable) {
                // Ищем любую версию chromium_headless_shell в новой структуре (1200+)
                $newGlobPattern = $path . '/chromium_headless_shell-*/chrome-headless-shell-linux64/chrome-headless-shell';
                $newBrowsers = @glob($newGlobPattern);
                error_log("  Checking path (new structure): $path, found browsers: " . (is_array($newBrowsers) ? count($newBrowsers) : 'error'));
                
                if (!empty($newBrowsers) && @file_exists($newBrowsers[0]) && @is_executable($newBrowsers[0])) {
                    $playwrightCachePath = $path;
                    error_log("Found Playwright browser (new structure, any version) at: $path");
                    break;
                }
                
                // Ищем любую версию chromium_headless_shell в старой структуре (<1200)
                $oldGlobPattern = $path . '/chromium_headless_shell-*/chrome-linux/headless_shell';
                $oldBrowsers = @glob($oldGlobPattern);
                error_log("  Checking path (old structure): $path, found browsers: " . (is_array($oldBrowsers) ? count($oldBrowsers) : 'error'));
                
                if (!empty($oldBrowsers) && @file_exists($oldBrowsers[0]) && @is_executable($oldBrowsers[0])) {
                    $playwrightCachePath = $path;
                    error_log("Found Playwright browser (old structure, any version) at: $path");
                    break;
                }
            }
        }
    }
    
    if (!$playwrightCachePath) {
        error_log("WARNING: Playwright browser not found in any of the checked paths!");
    }
    
    $env = [
        'HOME' => getenv('HOME') ?: '/home/serveradmin',
        'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
        'NODE_PATH' => getenv('NODE_PATH') ?: ''
    ];
    
    // Устанавливаем путь к браузерам Playwright, если найден
    if ($playwrightCachePath) {
        // Если путь через симлинк, используем реальный путь для надежности
        // Playwright не всегда может пройти по симлинку при выполнении от www-data
        $finalPath = $playwrightCachePath;
        if (strpos($playwrightCachePath, '/var/www/.cache/ms-playwright') === 0) {
            // Это симлинк, получаем реальный путь к директории ms-playwright
            // Проверяем обе структуры (новую и старую)
            $testBrowserPaths = [
                // Новая структура (версия 1200+)
                $playwrightCachePath . '/chromium_headless_shell-1200/chrome-headless-shell-linux64/chrome-headless-shell',
                $playwrightCachePath . '/chromium_headless_shell-1201/chrome-headless-shell-linux64/chrome-headless-shell',
                // Старая структура (версия <1200)
                $playwrightCachePath . '/chromium_headless_shell-1194/chrome-linux/headless_shell',
                $playwrightCachePath . '/chromium_headless_shell-1187/chrome-linux/headless_shell',
            ];
            
            $realBrowserPath = null;
            foreach ($testBrowserPaths as $testPath) {
                $resolved = @realpath($testPath);
                if ($resolved) {
                    $realBrowserPath = $resolved;
                    error_log("Found browser path for symlink resolution: $testPath -> $resolved");
                    break;
                }
            }
            
            if ($realBrowserPath) {
                // Получаем путь к директории ms-playwright: /home/serveradmin/.cache/ms-playwright
                // Для новой структуры нужно на 3 уровня вверх, для старой - на 2
                $realMsPlaywrightPath = null;
                if (strpos($realBrowserPath, 'chrome-headless-shell-linux64') !== false) {
                    // Новая структура: .../chromium_headless_shell-1200/chrome-headless-shell-linux64/chrome-headless-shell
                    $realMsPlaywrightPath = dirname(dirname(dirname($realBrowserPath)));
                } else {
                    // Старая структура: .../chromium_headless_shell-1194/chrome-linux/headless_shell
                    $realMsPlaywrightPath = dirname(dirname(dirname($realBrowserPath)));
                }
                
                // Проверяем доступность реального пути
                $browserCheckPath = null;
                if (strpos($realBrowserPath, 'chrome-headless-shell-linux64') !== false) {
                    // Проверяем новую структуру
                    $browserCheckPath = $realMsPlaywrightPath . '/chromium_headless_shell-1200/chrome-headless-shell-linux64/chrome-headless-shell';
                } else {
                    // Проверяем старую структуру
                    $browserCheckPath = $realMsPlaywrightPath . '/chromium_headless_shell-1194/chrome-linux/headless_shell';
                }
                
                if ($realMsPlaywrightPath && @is_dir($realMsPlaywrightPath) && @is_readable($browserCheckPath)) {
                    $finalPath = $realMsPlaywrightPath;
                    error_log("Using real path instead of symlink: $finalPath (was: $playwrightCachePath)");
                    // При использовании реального пути устанавливаем HOME в /home/serveradmin
                    $env['HOME'] = '/home/serveradmin';
                    error_log("Setting HOME to /home/serveradmin for real path");
                } else {
                    error_log("Real path not accessible, using symlink: $playwrightCachePath");
                    // При использовании симлинка устанавливаем HOME в /var/www
                    $env['HOME'] = '/var/www';
                    error_log("Setting HOME to /var/www for symlink path");
                }
            } else {
                error_log("Could not resolve real path for symlink, using symlink: $playwrightCachePath");
                $env['HOME'] = '/var/www';
                error_log("Setting HOME to /var/www for symlink path");
            }
        }
        $env['PLAYWRIGHT_BROWSERS_PATH'] = $finalPath;
        error_log("Setting PLAYWRIGHT_BROWSERS_PATH to: $finalPath");
    } else {
        // Если путь не найден автоматически, пробуем домашнюю директорию serveradmin
        $serveradminCachePath = '/home/serveradmin/.cache/ms-playwright';
        error_log("Browser not found, trying fallback path: $serveradminCachePath");
        
        // Проверяем доступность директории
        $isDir = is_dir($serveradminCachePath);
        $isReadable = is_readable($serveradminCachePath);
        error_log("Fallback path check - is_dir: " . ($isDir ? 'yes' : 'no') . ", is_readable: " . ($isReadable ? 'yes' : 'no'));
        
        // Проверяем наличие браузера в fallback пути (обе структуры)
        if ($isDir && $isReadable) {
            // Проверяем новую структуру (1200+)
            $fallbackBrowserNew = glob($serveradminCachePath . '/chromium_headless_shell-*/chrome-headless-shell-linux64/chrome-headless-shell');
            // Проверяем старую структуру (<1200)
            $fallbackBrowserOld = glob($serveradminCachePath . '/chromium_headless_shell-*/chrome-linux/headless_shell');
            
            $fallbackBrowser = !empty($fallbackBrowserNew) ? $fallbackBrowserNew : $fallbackBrowserOld;
            
            if (!empty($fallbackBrowser) && file_exists($fallbackBrowser[0])) {
                $env['PLAYWRIGHT_BROWSERS_PATH'] = $serveradminCachePath;
                error_log("Using fallback PLAYWRIGHT_BROWSERS_PATH: $serveradminCachePath (browser found: " . $fallbackBrowser[0] . ")");
            } else {
                error_log("ERROR: Fallback path accessible but browser not found!");
            }
        } else {
            error_log("ERROR: Fallback path not accessible! is_dir: " . ($isDir ? 'yes' : 'no') . ", is_readable: " . ($isReadable ? 'yes' : 'no'));
            // В крайнем случае используем симлинк из /var/www/.cache/ms-playwright
            $symlinkPath = '/var/www/.cache/ms-playwright';
            if (@is_dir($symlinkPath)) {
                // Проверяем обе структуры
                $symlinkBrowserNew = @glob($symlinkPath . '/chromium_headless_shell-*/chrome-headless-shell-linux64/chrome-headless-shell');
                $symlinkBrowserOld = @glob($symlinkPath . '/chromium_headless_shell-*/chrome-linux/headless_shell');
                $symlinkBrowser = !empty($symlinkBrowserNew) ? $symlinkBrowserNew : $symlinkBrowserOld;
                
                if (!empty($symlinkBrowser) && @file_exists($symlinkBrowser[0])) {
                    $env['PLAYWRIGHT_BROWSERS_PATH'] = $symlinkPath;
                    error_log("Using symlink path as fallback: $symlinkPath (browser: " . $symlinkBrowser[0] . ")");
                } else {
                    error_log("WARNING: Symlink path exists but browser not found, setting anyway");
                    $env['PLAYWRIGHT_BROWSERS_PATH'] = $symlinkPath;
                }
            } elseif ($isDir) {
                $env['PLAYWRIGHT_BROWSERS_PATH'] = $serveradminCachePath;
                error_log("WARNING: Setting PLAYWRIGHT_BROWSERS_PATH despite readability issues: $serveradminCachePath");
            }
        }
    }
    
    // Всегда логируем финальное значение PLAYWRIGHT_BROWSERS_PATH
    if (isset($env['PLAYWRIGHT_BROWSERS_PATH'])) {
        error_log("Final PLAYWRIGHT_BROWSERS_PATH: " . $env['PLAYWRIGHT_BROWSERS_PATH']);
    } else {
        error_log("ERROR: PLAYWRIGHT_BROWSERS_PATH not set!");
    }
    
    $envString = '';
    foreach ($env as $key => $value) {
        $envString .= $key . '=' . escapeshellarg($value) . ' ';
    }
    
    $output = [];
    $returnCode = 0;
    
    // Логирование перед выполнением команды
    error_log("Executing Playwright command: " . $command);
    error_log("Environment: " . $envString);
    error_log("HTML file: " . $htmlFile);
    error_log("PDF output: " . $tempPdfPath);
    
    exec($envString . $command, $output, $returnCode);
    
    // Логирование результата
    error_log("Command return code: $returnCode");
    if (!empty($output)) {
        error_log("Command output: " . implode("\n", $output));
    }
    
    if ($returnCode !== 0) {
        $errorMsg = implode("\n", $output);
        error_log("Playwright command failed. Return code: $returnCode");
        error_log("Command: $command");
        error_log("Output: " . $errorMsg);
        // Очистка временных файлов при ошибке
        @unlink($htmlFile);
        @unlink($tempPdfPath);
        @rmdir($tempDir);
        throw new Exception("Playwright PDF generation failed: " . $errorMsg);
    }
    
    // Проверка существования созданного PDF во временной директории
    clearstatcache(true, $tempPdfPath);
    if (!file_exists($tempPdfPath)) {
        $errorDetails = "PDF file was not created at: $tempPdfPath\n";
        @unlink($htmlFile);
        @rmdir($tempDir);
        throw new Exception('PDF file was not created. ' . $errorDetails);
    }
    
    // Проверка и исправление прав доступа к директории архива
    $archiveDirPath = dirname($pdfPath);
    if (!is_dir($archiveDirPath)) {
        @unlink($htmlFile);
        @unlink($tempPdfPath);
        @rmdir($tempDir);
        throw new Exception("Archive directory does not exist: $archiveDirPath");
    }
    
    // Проверка прав на запись в директорию
    if (!is_writable($archiveDirPath)) {
        // Попытка установить права на запись (если возможно)
        @chmod($archiveDirPath, 0775);
        
        // Повторная проверка
        if (!is_writable($archiveDirPath)) {
            $realPath = realpath($archiveDirPath) ?: $archiveDirPath;
            $dirPerms = substr(sprintf('%o', fileperms($archiveDirPath)), -4);
            $dirOwner = function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($archiveDirPath))['name'] : 'unknown';
            $dirGroup = function_exists('posix_getgrgid') ? posix_getgrgid(filegroup($archiveDirPath))['name'] : 'unknown';
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
            
            @unlink($htmlFile);
            @unlink($tempPdfPath);
            @rmdir($tempDir);
            throw new Exception('Failed to save PDF to archive. ' . $errorDetails);
        }
    }
    
    // Перемещаем PDF из временной директории в архивную (через PHP, у которого есть права)
    // Сначала удаляем старый файл, если он существует
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
            @unlink($htmlFile);
            @unlink($tempPdfPath);
            @rmdir($tempDir);
            throw new Exception('Failed to save PDF to archive. ' . $errorDetails);
        }
    }
    
    // Используем rename вместо copy для атомарной операции
    if (!@rename($tempPdfPath, $pdfPath)) {
        // Если rename не сработал (например, разные файловые системы), пробуем copy
        if (!copy($tempPdfPath, $pdfPath)) {
            $errorDetails = "Failed to save PDF from $tempPdfPath to $pdfPath\n";
            $errorDetails .= "Source exists: " . (file_exists($tempPdfPath) ? 'Yes' : 'No') . "\n";
            $errorDetails .= "Source readable: " . (is_readable($tempPdfPath) ? 'Yes' : 'No') . "\n";
            $errorDetails .= "Destination dir exists: " . (is_dir(dirname($pdfPath)) ? 'Yes' : 'No') . "\n";
            $errorDetails .= "Destination dir writable: " . (is_writable(dirname($pdfPath)) ? 'Yes' : 'No') . "\n";
            if (file_exists($pdfPath)) {
                $errorDetails .= "Destination file exists: Yes (permissions: " . substr(sprintf('%o', fileperms($pdfPath)), -4) . ")\n";
            }
            @unlink($htmlFile);
            @unlink($tempPdfPath);
            @rmdir($tempDir);
            throw new Exception('Failed to save PDF to archive. ' . $errorDetails);
        }
        // Если copy сработал, удаляем временный файл
        @unlink($tempPdfPath);
    } else {
        // Если rename сработал, tempPdfPath уже не существует (файл перемещен)
        $tempPdfPath = null;
    }
    
    // Устанавливаем правильные права на созданный файл
    if (file_exists($pdfPath)) {
        @chmod($pdfPath, 0664);
    }
    
    // Очистка временных файлов
    @unlink($htmlFile);
    if ($tempPdfPath && file_exists($tempPdfPath)) {
        @unlink($tempPdfPath);
    }
    @rmdir($tempDir);
    
    // Отправка PDF в браузер
    sendPdfToBrowser($pdfPath, $pdfFilename);
    
} catch (Exception $e) {
    // Очистка буфера вывода перед отправкой ошибки
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Очистка в случае ошибки
    if (isset($htmlFile) && file_exists($htmlFile)) {
        @unlink($htmlFile);
    }
    if (isset($tempPdfPath) && file_exists($tempPdfPath)) {
        @unlink($tempPdfPath);
    }
    if (isset($tempDir) && is_dir($tempDir)) {
        // Удаляем все файлы в директории перед удалением самой директории
        $files = glob($tempDir . '/*');
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($tempDir);
    }
    
    // Логирование ошибки для отладки
    $errorLog = 'Playwright PDF generation error: ' . $e->getMessage();
    $errorLog .= "\nFile: " . $e->getFile() . ":" . $e->getLine();
    $errorLog .= "\nTrace: " . $e->getTraceAsString();
    if (isset($output)) {
        $errorLog .= "\nCommand output: " . implode("\n", $output);
    }
    if (isset($command)) {
        $errorLog .= "\nCommand: " . $command;
    }
    if (isset($htmlFile)) {
        $errorLog .= "\nHTML file: " . $htmlFile . " (exists: " . (file_exists($htmlFile) ? 'yes' : 'no') . ")";
    }
    if (isset($tempPdfPath)) {
        $errorLog .= "\nTemp PDF path: " . $tempPdfPath . " (exists: " . (file_exists($tempPdfPath) ? 'yes' : 'no') . ")";
    }
    if (isset($envString)) {
        $errorLog .= "\nEnvironment: " . $envString;
    }
    error_log($errorLog);
    
    // Отправка ошибки с более подробной информацией
    $details = [];
    if (isset($output)) {
        $details['command_output'] = implode("\n", $output);
    }
    if (isset($command)) {
        $details['command'] = $command;
    }
    if (isset($returnCode)) {
        $details['return_code'] = $returnCode;
    }
    if (isset($htmlFile)) {
        $details['html_file'] = $htmlFile;
        $details['html_file_exists'] = file_exists($htmlFile);
    }
    if (isset($tempPdfPath)) {
        $details['temp_pdf_path'] = $tempPdfPath;
        $details['temp_pdf_exists'] = file_exists($tempPdfPath);
    }
    
    sendJsonError($e->getMessage(), 500, !empty($details) ? $details : null);
}
