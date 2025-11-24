<?php
/**
 * Генератор HTML для инвойса
 * Единая функция для генерации HTML, используемая всеми методами генерации PDF
 * 
 * @param array $data Данные инвойса
 * @param string $orgId ID организации
 * @param string $styleType Тип стилей: 'playwright' (flexbox) или 'mpdf' (таблицы)
 * @return string HTML код инвойса
 */

if (!function_exists('generateInvoiceHTML')) {
function generateInvoiceHTML($data, $orgId, $styleType = 'playwright') {
    // Загрузка данных организации из organizations.js
    $orgFile = dirname(__DIR__) . '/js/organizations.js';
    if (!file_exists($orgFile)) {
        throw new Exception('Organizations file not found');
    }
    
    $orgContent = file_get_contents($orgFile);
    
    // Извлечение данных организации
    require_once __DIR__ . '/organization_parser.php';
    $orgData = parseOrganizationData($orgId, $orgContent);
    $bankingDetails = parseBankingDetails($orgId, $orgContent, $data['selectedBankId'] ?? null);
    
    // Определяем базовый URL для изображений
    $baseDir = dirname(__DIR__);
    
    // Конвертируем изображения в base64
    require_once __DIR__ . '/pdf_utils.php';
    // Для PDF используем упрощенную версию SVG (без CSS переменных)
    $isForPdf = ($styleType === 'mpdf');
    $logoPath = imageToBase64($orgData['logo'], $baseDir, $isForPdf);
    $stampPath = imageToBase64($orgData['stamp'], $baseDir, $isForPdf);
    $signaturePath = imageToBase64($orgData['signature'], $baseDir, $isForPdf);
    
    // Расчет итогов
    $total = 0;
    foreach ($data['items'] ?? [] as $item) {
        $total += ($item['quantity'] ?? 0) * ($item['price'] ?? 0);
    }
    
    // Расчет НДС: если итоговая сумма включает НДС, то НДС = Итого * 20 / 120 = Итого / 6
    $vat = $total / 6;

    // Определяем количество позиций для настройки блока подписи
    $itemCount = isset($data['items']) && is_array($data['items']) ? count($data['items']) : 0;
    $signatureMarginTop = 15;
    $signatureTextOffset = 10;
    $signatureTextTopOffset = 0;

    if ($itemCount === 3) {
        $signatureMarginTop = 0;
    } elseif ($itemCount >= 4) {
        $signatureMarginTop = -28;
        $signatureTextOffset = 35;
        $signatureTextTopOffset = 30;
    }
    
    $currency = $data['currency'] ?? 'RUB';
    
    // Выбираем CSS стили в зависимости от типа
    $css = ($styleType === 'mpdf') ? getMpdfStyles() : getPlaywrightStyles();
    $headerHtml = ($styleType === 'mpdf') ? getMpdfHeader($logoPath, $orgData) : getPlaywrightHeader($logoPath, $orgData);
    
    // Генерация HTML
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Коммерческое Предложение</title>
    <style>' . $css . '</style>
</head>
<body>
    <div class="container">
        ' . $headerHtml . '
        
        <div class="title">Коммерческое Предложение</div>
        
        <div class="document-info">
            <div class="bill-to">
                <div class="info-label">Плательщик:</div>
                <div class="info-value">' . htmlspecialchars($data['recipient'] ?? 'N/A') . '</div>
                <div style="font-size: 9pt; color: #374151;">' . htmlspecialchars($data['recipientAddress'] ?? '') . '</div>
                ' . (!empty($data['recipientINN']) ? '<div style="font-size: 9pt; color: #374151; margin-top: 3px;">ИНН: ' . htmlspecialchars($data['recipientINN']) . '</div>' : '') . '
            </div>
            <div class="invoice-details">
                <div class="info-row"><span class="info-label">Номер:</span> <span class="info-value">' . htmlspecialchars($data['number'] ?? 'N/A') . '</span></div>
                <div class="info-row"><span class="info-label">Дата:</span> <span class="info-value">' . formatInvoiceDate($data['date'] ?? '') . '</span></div>
                <div class="info-row"><span class="info-label">Валюта:</span> <span class="info-value">' . htmlspecialchars($currency) . '</span></div>
            </div>
        </div>
        
        <div class="intro-text">В ответ на Ваш запрос, на поставку продукции, готовы предложить следующее:</div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">No.</th>
                    <th style="min-width: 200px;">Описание Товара</th>
                    <th style="width: 15%;" class="text-center">Страна Производства</th>
                    <th style="width: 8%;" class="text-center">Кол-во</th>
                    <th style="width: 7%;" class="text-center">Ед.</th>
                    <th style="width: 11%;" class="text-center">Цена за ед.</th>
                    <th style="width: 12%;" class="text-right">Сумма</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($data['items'] ?? [] as $idx => $item) {
        $qty = $item['quantity'] ?? 0;
        $price = $item['price'] ?? 0;
        $amount = $qty * $price;
        
        $html .= '<tr>
            <td class="text-center">' . ($idx + 1) . '</td>
            <td>' . htmlspecialchars($item['type'] ?? '') . '</td>
            <td class="text-center">' . htmlspecialchars($item['countryOfOrigin'] ?? '') . '</td>
            <td class="text-center">' . $qty . '</td>
            <td class="text-center">' . htmlspecialchars($item['unit'] ?? '') . '</td>
            <td class="text-right">' . formatInvoiceNumber($price) . '</td>
            <td class="text-right">' . formatInvoiceNumber($amount) . '</td>
        </tr>';
    }
    
    $html .= '<tr class="total-row">
            <td colspan="6" class="text-right"><strong>Сумма в ' . htmlspecialchars($currency) . ':</strong></td>
            <td class="text-right"><strong>' . formatInvoiceNumber($total) . '</strong></td>
        </tr>
        <tr class="vat-row">
            <td colspan="6" class="text-right">В том числе НДС - 20%:</td>
            <td class="text-right">' . formatInvoiceNumber($vat) . '</td>
        </tr>
    </tbody>
    </table>
    
    <div class="terms-section">
        <div class="terms-left">
            <p><strong>Условия поставки:</strong> ' . htmlspecialchars($data['incoterm'] ?? '') . ' ' . htmlspecialchars($data['deliveryPlace'] ?? '') . '</p>
            <p><strong>Условия оплаты:</strong> ' . htmlspecialchars($data['paymentTerms'] ?? '') . '</p>
            <p><strong>Срок поставки:</strong> ' . htmlspecialchars($data['deliveryTime'] ?? '') . '</p>
        </div>
        <div class="terms-right">
            <p><strong>Гарантия:</strong> ' . htmlspecialchars($data['warranty'] ?? '') . '</p>
            <p><strong>Коммерческое Предложение действительно до:<br></strong> ' . formatValidUntilDate($data['validUntil'] ?? $data['validity'] ?? '') . '</p>
        </div>
    </div>
    
    ' . (!empty($data['remarks']) ? '
    <div class="remarks-section">
        <p><strong>Примечания:</strong> ' . nl2br(htmlspecialchars($data['remarks'])) . '</p>
    </div>
    ' : '') . '
    
    ' . (!empty($bankingDetails['bankName']) || !empty($bankingDetails['account']) ? '
    <div class="banking-section">
        <h3>Банковские реквизиты</h3>
        ' . formatBankingDetailsFromArray($bankingDetails) . '
    </div>
    ' : '') . '
    
    <div class="signature" style="margin-top: ' . $signatureMarginTop . 'px;">
        <div class="signature-center">
            <div class="signature-text" style="margin-left: ' . $signatureTextOffset . 'px; margin-top: ' . $signatureTextTopOffset . 'px;">
                <div style="margin-bottom: 5px; font-weight: bold;">' . htmlspecialchars($data['contactPerson'] ?? '') . '</div>
                <div style="font-size: 9pt; color: #374151;">' . htmlspecialchars($data['position'] ?? '') . '</div>
            </div>
        </div>
        <div class="signature-right">
            ' . (($stampPath || $signaturePath) ? '
            <div class="signature-images">
                ' . ($stampPath ? '<img src="' . htmlspecialchars($stampPath) . '" alt="Company Stamp" class="signature-stamp" />' : '') . '
                ' . ($signaturePath ? '<img src="' . htmlspecialchars($signaturePath) . '" alt="Authorized Signature" class="signature-sign" />' : '') . '
            </div>
            ' : '') . '
        </div>
    </div>
    </div>
        
</body>
</html>';
    
    return $html;
}

/**
 * Возвращает CSS стили для Playwright (flexbox)
 */
function getPlaywrightStyles() {
    return '
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt; 
            line-height: 1.4;
            color: #000;
            padding: 20px;
        }
        .container { 
            max-width: 210mm; 
            margin: 0 auto; 
            padding: 10mm 10mm 2px 10mm; /* Нижняя граница: 2px */
        }
        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            margin-bottom: 20px; 
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 15px;
        }
        .logo-section { 
            flex-shrink: 0;
            display: flex;
            align-items: center;
        }
        .logo { 
            max-height: 80px; 
            width: auto;
            object-fit: contain;
        }
        .company-info { 
            text-align: right; 
            font-size: 9pt; 
            color: #1F2937;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: center;
        }
        .company-info a {
            color: inherit;
            text-decoration: none;
        }
        .company-info a:hover {
            text-decoration: underline;
        }
        .company-name { 
            font-weight: bold; 
            font-size: 11pt; 
            margin-bottom: 5px;
        }
        .title {
            text-align: center;
            font-size: 18pt;
            font-weight: bold;
            margin: 20px 0;
            color: #1e40af;
        }
        .document-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .bill-to, .invoice-details {
            flex: 1;
        }
        .info-label {
            font-weight: bold;
            font-size: 9pt;
            color: #374151;
            margin-top: 10px;
        }
        .info-value {
            font-size: 10pt;
            margin-top: 3px;
        }
        .info-row {
            margin-top: 10px;
        }
        .info-row .info-label {
            margin-top: 0;
            margin-right: 5px;
        }
        .info-row .info-value {
            margin-top: 0;
        }
        .intro-text {
            font-size: 10pt;
            color: #1F2937;
            margin: 20px 0;
            line-height: 1.6;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: none;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #3b82f6;
            color: white;
            font-weight: bold;
            font-size: 9pt;
        }
        td {
            font-size: 9pt;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .total-row {
            background-color: #f3f4f6;
            font-weight: bold;
        }
        .vat-row {
            background-color: #f9fafb;
            font-size: 9pt;
        }
        .terms-section {
            margin-top: 20px;
            font-size: 9pt;
            line-height: 1.6;
            display: flex;
            justify-content: space-between;
            gap: 40px;
        }
        .terms-left, .terms-right {
            flex: 1;
        }
        .terms-section p {
            margin: 5px 0;
        }
        .remarks-section {
            margin-top: 20px;
            font-size: 9pt;
            line-height: 1.1;
            width: 100%;
        }
        .remarks-section p {
            margin: 0;
        }
        .banking-section {
            margin-top: 20px;
            padding: 15px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            overflow: visible; /* Позволяет signature заходить на блок */
            page-break-inside: avoid;
            break-inside: avoid-page;
            page-break-before: avoid;
            page-break-after: auto;
            position: relative;
            z-index: 1;
            margin-bottom: 0; /* Убираем нижний отступ для возможности перекрытия */
            padding-bottom: 0; /* Убираем нижний padding для лучшего перекрытия */
        }
        .banking-section h3 {
            font-size: 11pt;
            margin-bottom: 10px;
            color: #1e40af;
        }
        .banking-details-list {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.6;
        }
        .banking-detail-item {
            margin-bottom: 8px;
        }
        .banking-detail-label {
            font-weight: bold;
            color: #1F2937;
        }
        .banking-detail-value {
            color: #000;
        }
        .banking-detail-row {
            display: flex;
            gap: 30px;
            margin-bottom: 8px;
        }
        .banking-detail-col {
            flex: 1;
        }
        .signature {
            margin-top: 15px; /* Обычный отступ, если места достаточно */
            padding-top: 20px;
            display: flex;
            align-items: flex-start;
            justify-content: flex-start;
            position: relative;
            gap: 50px;
            margin-left: 250px;
            page-break-inside: avoid !important;
            break-inside: avoid-page !important;
            page-break-before: avoid !important;
            page-break-after: avoid !important; /* Не переносить на следующую страницу - это заставит использовать перекрытие если не хватает места */
            z-index: 2; /* Поверх банковских реквизитов */
            margin-bottom: 5px; /* Нижняя граница документа: 5px */
        }
        
        @page {
            margin-bottom: 5px; /* Нижняя граница страницы: 5px */
        }
        .signature-center {
            flex: 0 0 auto;
            display: flex;
            align-items: flex-start;
            margin-right: 5px;
        }
        .signature-right {
            flex: 0 0 auto;
        }
        .signature-images {
            position: relative;
            display: inline-block;
            margin-top: -10px;
        }
        .signature-stamp {
            max-width: 150px;
            height: auto;
            position: relative;
            z-index: 1;
        }
        .signature-sign {
            max-width: 120px;
            height: auto;
            position: absolute;
            top: 30px;
            left: 20px;
            z-index: 2;
        }
        .signature-text {
            text-align: center;
            position: relative;
            z-index: 3;
            text-align: left;
        }
        @media print {
            body { padding: 0; }
            .container { padding: 0; }
        }
    ';
}

/**
 * Возвращает CSS стили для mPDF (таблицы)
 */
function getMpdfStyles() {
    return '
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif; 
            font-size: 11pt; 
            line-height: 1.4;
            color: #000;
        }
        .container { max-width: 210mm; margin: 0 auto; padding: 10mm 10mm 2px 10mm; /* Нижняя граница: 2px */ }
        .header { 
            width: 100%;
            margin-bottom: 20px; 
            padding-bottom: 15px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: middle;
            padding: 0;
        }
        .logo-section { 
            width: 30%;
            vertical-align: middle;
        }
        .logo { 
            max-height: 80px; 
            width: auto;
            vertical-align: middle;
        }
        .company-info { 
            width: 70%;
            text-align: right; 
            font-size: 9pt; 
            color: #1F2937;
            vertical-align: middle;
        }
        .company-info a {
            color: inherit;
            text-decoration: none;
        }
        .company-info a:hover {
            text-decoration: underline;
        }
        .company-name { 
            font-weight: bold; 
            font-size: 11pt; 
            margin-bottom: 5px;
        }
        .title { 
            text-align: center; 
            font-size: 20pt; 
            font-weight: bold; 
            margin: 25px 0;
            color: #1e40af;
        }
        .document-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
        }
        .bill-to {
            flex: 1;
        }
        .invoice-details {
            flex: 1;
            text-align: right;
        }
        .info-label { font-size: 9pt; color: #374151; }
        .info-value { font-weight: bold; margin-bottom: 8px; }
        .info-row {
            margin-top: 10px;
        }
        .info-row .info-label {
            margin-right: 5px;
        }
        .info-row .info-value {
            margin-bottom: 0;
        }
        .intro-text {
            font-size: 10pt;
            color: #1F2937;
            margin: 20px 0;
            line-height: 1.6;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0; 
            font-size: 10pt;
        }
        th, td { 
            padding: 8px; 
        }
        th { 
            background-color: #e5e7eb; 
            font-weight: bold; 
            text-align: center;
        }
        td { text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row { 
            font-weight: bold; 
            background-color: #dbeafe; 
        }
        .vat-row {
            font-size: 9pt;
            background-color: #f0f0f0;
        }
        .terms-section {
            margin-top: 30px;
            padding: 15px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
        }
        .terms-left, .terms-right {
            flex: 1;
            margin-right: 20px;
        }
        .terms-right {
            margin-right: 0;
        }
        .terms-section p {
            margin-bottom: 8px;
        }
        .remarks-section {
            margin-top: 30px;
            font-size: 9pt;
            line-height: 1.6;
            width: 100%;
        }
        .remarks-section p {
            margin: 0;
        }
        .banking-section {
            margin-top: 30px;
            padding: 15px;
            background: #f0f0f0;
            border-top: 2px solid #3b82f6;
            overflow: visible; /* Позволяет signature заходить на блок */
            page-break-inside: avoid;
            break-inside: avoid-page;
            page-break-before: avoid;
            page-break-after: auto;
            position: relative;
            z-index: 1;
            margin-bottom: 0; /* Убираем нижний отступ для возможности перекрытия */
            padding-bottom: 0; /* Убираем нижний padding для лучшего перекрытия */
        }
        .banking-section h3 {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 10px;
            color: #1e40af;
        }
        .banking-details-list {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.6;
        }
        .banking-detail-item {
            margin-bottom: 8px;
        }
        .banking-detail-label {
            font-weight: bold;
            color: #1F2937;
        }
        .banking-detail-value {
            color: #000;
        }
        .banking-detail-row {
            display: flex;
            gap: 30px;
            margin-bottom: 8px;
        }
        .banking-detail-col {
            flex: 1;
        }
        .signature {
            margin-top: 15px; /* Обычный отступ, если места достаточно */
            padding-top: 20px;
            display: flex;
            align-items: flex-start;
            justify-content: flex-start;
            position: relative;
            page-break-inside: avoid !important;
            break-inside: avoid-page !important;
            page-break-before: avoid !important;
            page-break-after: avoid !important; /* Не переносить на следующую страницу - это заставит использовать перекрытие если не хватает места */
            z-index: 2; /* Поверх банковских реквизитов */
            margin-bottom: 5px; /* Нижняя граница документа: 5px */
        }
        
        @page {
            margin-bottom: 5px; /* Нижняя граница страницы: 5px */
        }
        .signature-center {
            flex: 0 0 auto;
            display: flex;
            align-items: flex-start;
            margin-right: 50px;
        }
        .signature-right {
            flex: 0 0 auto;
        }
        .signature-images {
            position: relative;
            display: inline-block;
            margin-top: -20px;
        }
        .signature-stamp {
            height: 96px;
            width: auto;
            opacity: 0.8;
            position: relative;
            z-index: 1;
        }
        .signature-sign {
            position: absolute;
            top: 10px;
            left: 0;
            height: 64px;
            width: auto;
            z-index: 2;
        }
        .signature-text {
            text-align: center;
            position: relative;
            z-index: 3;
        }
    ';
}

/**
 * Возвращает HTML заголовка для Playwright (flexbox)
 */
function getPlaywrightHeader($logoPath, $orgData) {
    return '<div class="header">
            <div class="logo-section">
                ' . ($logoPath ? '<img src="' . htmlspecialchars($logoPath) . '" alt="' . htmlspecialchars($orgData['name']) . '" class="logo" />' : '') . '
            </div>
            <div class="company-info">
                <div class="company-name">' . htmlspecialchars($orgData['name']) . '</div>
                ' . (!empty($orgData['INN_vektor']) ? '<div>ИНН: ' . htmlspecialchars($orgData['INN_vektor']) . '</div>' : '') . '
                <div>' . htmlspecialchars($orgData['address']) . '</div>
                <div>' . formatPhoneLink($orgData['phone'] ?? '') . '</div>
                <div>' . formatEmailLink($orgData['email'] ?? '') . '</div>
            </div>
        </div>';
}

/**
 * Возвращает HTML заголовка для mPDF (таблица)
 */
function getMpdfHeader($logoPath, $orgData) {
    return '<div class="header">
            <table class="header-table">
                <tr>
                    <td class="logo-section">
                        ' . ($logoPath ? '<img src="' . htmlspecialchars($logoPath) . '" alt="' . htmlspecialchars($orgData['name']) . '" class="logo" />' : '') . '
                    </td>
                    <td class="company-info">
                        <div class="company-name">' . htmlspecialchars($orgData['name']) . '</div>
                        ' . (!empty($orgData['INN_vektor']) ? '<div>ИНН: ' . htmlspecialchars($orgData['INN_vektor']) . '</div>' : '') . '
                        <div>' . htmlspecialchars($orgData['address']) . '</div>
                        <div>' . formatPhoneLink($orgData['phone'] ?? '') . '</div>
                        <div>' . formatEmailLink($orgData['email'] ?? '') . '</div>
                    </td>
                </tr>
            </table>
        </div>';
}

/**
 * Форматирует банковские реквизиты из массива в HTML
 * 
 * @param array $bankingDetails Массив с банковскими реквизитами
 * @return string HTML код с форматированными реквизитами
 */
function formatBankingDetailsFromArray($bankingDetails) {
    if (empty($bankingDetails) || (empty($bankingDetails['bankName']) && empty($bankingDetails['account']))) {
        return '';
    }
    
    $html = '<div class="banking-details-list">';
    
    if (!empty($bankingDetails['bankName'])) {
        $html .= '<div class="banking-detail-item">';
        $html .= '<span class="banking-detail-label">Банк:</span> ';
        $html .= '<span class="banking-detail-value">' . htmlspecialchars($bankingDetails['bankName']) . '</span>';
        $html .= '</div>';
    }
    
    if (!empty($bankingDetails['bankAddress'])) {
        $html .= '<div class="banking-detail-item">';
        $html .= '<span class="banking-detail-label">Адрес банка:</span> ';
        $html .= '<span class="banking-detail-value">' . htmlspecialchars($bankingDetails['bankAddress']) . '</span>';
        $html .= '</div>';
    }
    
    
    
    // БИК и Корреспондентский счет в одной строке в две колонки
    if (!empty($bankingDetails['bik']) || !empty($bankingDetails['correspondentAccount'])) {
        $html .= '<div class="banking-detail-item banking-detail-row">';
        if (!empty($bankingDetails['bik'])) {
            $html .= '<div class="banking-detail-col">';
            $html .= '<span class="banking-detail-label">БИК:</span> ';
            $html .= '<span class="banking-detail-value">' . htmlspecialchars($bankingDetails['bik']) . '</span>';
            $html .= '</div>';
        }
        if (!empty($bankingDetails['correspondentAccount'])) {
            $html .= '<div class="banking-detail-col">';
            $html .= '<span class="banking-detail-label">Корр. счет:</span> ';
            $html .= '<span class="banking-detail-value">' . htmlspecialchars($bankingDetails['correspondentAccount']) . '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    
    // Бенефициар и Счет в одной строке в две колонки
    if (!empty($bankingDetails['beneficiary']) || !empty($bankingDetails['account'])) {
        $html .= '<div class="banking-detail-item banking-detail-row">';
        if (!empty($bankingDetails['beneficiary'])) {
            $html .= '<div class="banking-detail-col">';
            $html .= '<span class="banking-detail-label">Бенефициар:</span> ';
            $html .= '<span class="banking-detail-value">' . htmlspecialchars($bankingDetails['beneficiary']) . '</span>';
            $html .= '</div>';
        }
        if (!empty($bankingDetails['account'])) {
            $html .= '<div class="banking-detail-col">';
            $html .= '<span class="banking-detail-label">Счет:</span> ';
            $html .= '<span class="banking-detail-value">' . htmlspecialchars($bankingDetails['account']) . '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}
} // Конец проверки function_exists

