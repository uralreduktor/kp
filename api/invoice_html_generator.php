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
function generateInvoiceHTML($data, $orgId, $styleType = 'playwright', $documentType = 'commercial') {
    // Нормализация данных для поддержки разных форматов JSON (старый и новый с вложенными объектами)
    
    // Обработка условий (commercialTerms)
    if (isset($data['commercialTerms']) && is_array($data['commercialTerms'])) {
        $terms = $data['commercialTerms'];
        $data['incoterm'] = $terms['incoterm'] ?? $data['incoterm'] ?? '';
        $data['deliveryPlace'] = $terms['deliveryPlace'] ?? $data['deliveryPlace'] ?? '';
        $data['deliveryTime'] = $terms['deliveryTime'] ?? $data['deliveryTime'] ?? '';
        $data['paymentTerms'] = $terms['paymentTerms'] ?? $data['paymentTerms'] ?? '';
        $data['warranty'] = $terms['warranty'] ?? $data['warranty'] ?? '';
    }

    // Обработка контактов (contact)
    if (isset($data['contact']) && is_array($data['contact'])) {
        $contact = $data['contact'];
        $data['contactPerson'] = $contact['person'] ?? $data['contactPerson'] ?? '';
        $data['position'] = $contact['position'] ?? $data['position'] ?? '';
    }

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
    
    $documentType = ($documentType === 'technical') ? 'technical' : 'commercial';
    
    if ($documentType === 'technical') {
        $css = getTechnicalAppendixStyles();
        $headerHtml = ($styleType === 'mpdf') ? getMpdfHeader($logoPath, $orgData) : getPlaywrightHeader($logoPath, $orgData);
        
        $proposalNumber = htmlspecialchars($data['number'] ?? '—');
        $proposalDate = formatInvoiceDate($data['date'] ?? '');
        $technicalSummary = trim($data['technicalSummary'] ?? '');
        if ($technicalSummary === '') {
            $technicalSummary = 'Технические параметры будут уточнены при заключении договора.';
        }
        $technicalSummaryHtml = nl2br(htmlspecialchars($technicalSummary));
        $technicalBindingNote = 'Техническое приложение является неотъемлемой частью основного коммерческого предложения.';
        
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $defaultTechText = 'Технические параметры будут уточнены при заключении договора.';
        $itemsRowsHtml = '';
        if (!empty($items)) {
            foreach ($items as $idx => $item) {
                $technicalDescription = trim($item['technicalDescription'] ?? '');
                if ($technicalDescription === '') {
                    $technicalDescription = $defaultTechText;
                }
                $itemsRowsHtml .= '<tr>
                    <td class="text-center">' . ($idx + 1) . '</td>
                    <td>
                        <div class="item-title">' . htmlspecialchars($item['description'] ?? $item['type'] ?? '—') . '</div>
                        ' . (!empty($item['model']) ? '<div class="item-meta">' . htmlspecialchars($item['model']) . '</div>' : '') . '
                        ' . (!empty($item['name']) ? '<div class="item-meta">' . htmlspecialchars($item['name']) . '</div>' : '') . '
                    </td>
                    <td class="text-center">' . htmlspecialchars($item['quantity'] ?? '') . '</td>
                    <td class="text-center">' . htmlspecialchars($item['unit'] ?? '') . '</td>
                    <td>' . nl2br(htmlspecialchars($technicalDescription)) . '</td>
                </tr>';
            }
        } else {
            $itemsRowsHtml = '<tr><td colspan="5" class="text-center">Нет позиций для отображения</td></tr>';
        }
        
        $reducerSpecs = is_array($data['reducerSpecs'] ?? null) ? $data['reducerSpecs'] : [];
        $reducerPdfFieldsDefaults = [
            'type' => true,
            'stages' => true,
            'torqueNm' => true,
            'ratio' => true,
            'housingMaterial' => true,
            'gearMaterial' => true,
            'bearings' => true,
            'additionalInfo' => true
        ];
        $pdfFields = $reducerPdfFieldsDefaults;
        if (isset($reducerSpecs['pdfFields']) && is_array($reducerSpecs['pdfFields'])) {
            $pdfFields = array_merge($reducerPdfFieldsDefaults, $reducerSpecs['pdfFields']);
        }
        
        $reducerRows = [];
        $typeValue = '';
        if (($reducerSpecs['type'] ?? '') === 'custom') {
            $typeValue = trim($reducerSpecs['customType'] ?? '');
        } else {
            $typeValue = trim($reducerSpecs['type'] ?? '');
        }
        if (!empty($pdfFields['type']) && $typeValue !== '') {
            $reducerRows[] = ['label' => 'Тип редуктора', 'value' => htmlspecialchars($typeValue)];
        }
        if (!empty($pdfFields['stages']) && ($reducerSpecs['stages'] ?? '') !== '') {
            $reducerRows[] = ['label' => 'Количество ступеней', 'value' => htmlspecialchars($reducerSpecs['stages']) . ' ступ.'];
        }
        if (!empty($pdfFields['torqueNm']) && ($reducerSpecs['torqueNm'] ?? '') !== '') {
            $reducerRows[] = ['label' => 'Номинальный крутящий момент', 'value' => htmlspecialchars($reducerSpecs['torqueNm']) . ' Н·м'];
        }
        if (!empty($pdfFields['ratio']) && ($reducerSpecs['ratio'] ?? '') !== '') {
            $reducerRows[] = ['label' => 'Передаточное отношение (U)', 'value' => htmlspecialchars($reducerSpecs['ratio'])];
        }
        if (!empty($pdfFields['housingMaterial']) && ($reducerSpecs['housingMaterial'] ?? '') !== '') {
            $housing = htmlspecialchars($reducerSpecs['housingMaterial']);
            $housingNote = trim($reducerSpecs['housingMaterialNote'] ?? '');
            if ($housingNote !== '') {
                $housing .= ' (' . htmlspecialchars($housingNote) . ')';
            }
            $reducerRows[] = ['label' => 'Материал корпуса', 'value' => $housing];
        }
        if (!empty($pdfFields['gearMaterial']) && ($reducerSpecs['gearMaterial'] ?? '') !== '') {
            $gearValue = htmlspecialchars($reducerSpecs['gearMaterial']);
            if (($reducerSpecs['gearMaterial'] ?? '') === 'custom') {
                $gearValue = htmlspecialchars($reducerSpecs['gearMaterialNote'] ?? '—');
            } elseif (($reducerSpecs['gearMaterialNote'] ?? '') !== '') {
                $gearValue .= ' (' . htmlspecialchars($reducerSpecs['gearMaterialNote']) . ')';
            }
            $reducerRows[] = ['label' => 'Материал зубчатой передачи', 'value' => $gearValue];
        }
        if (!empty($pdfFields['bearings'])) {
            $bearingsList = [];
            foreach ($reducerSpecs['bearings'] ?? [] as $bearing) {
                $bearing = trim($bearing);
                if ($bearing !== '') {
                    $bearingsList[] = htmlspecialchars($bearing);
                }
            }
            if (!empty($bearingsList)) {
                $reducerRows[] = ['label' => 'Устанавливаемые подшипники', 'value' => implode(', ', $bearingsList)];
            }
        }
        if (!empty($pdfFields['additionalInfo'])) {
            $additionalInfo = trim($reducerSpecs['additionalInfo'] ?? '');
            if ($additionalInfo !== '') {
                $reducerRows[] = ['label' => 'Прочая техническая информация', 'value' => nl2br(htmlspecialchars($additionalInfo))];
            }
        }
        
        $reducerRowsHtml = '';
        if (!empty($reducerRows)) {
            foreach ($reducerRows as $row) {
                $reducerRowsHtml .= '<tr>
                    <td class="tech-params-label">' . $row['label'] . '</td>
                    <td>' . $row['value'] . '</td>
                </tr>';
            }
        } else {
            $reducerRowsHtml = '<tr><td class="tech-params-label">Параметры</td><td>Нет выбранных параметров для отображения</td></tr>';
        }
        
        $contactPerson = htmlspecialchars($data['contactPerson'] ?? '');
        $position = htmlspecialchars($data['position'] ?? '');
        $signatureHtml = '';
        if ($contactPerson || $position || $stampPath || $signaturePath) {
            $signatureHtml = '<div class="signature signature-technical">
                <div class="signature-center">
                    <div class="signature-text">
                        <div style="margin-bottom: 5px; font-weight: bold;">' . ($contactPerson ?: '—') . '</div>
                        <div class="signature-position">' . ($position ?: '') . '</div>
                    </div>
                </div>
                <div class="signature-right">'
                    . (($stampPath || $signaturePath)
                        ? '<div class="signature-images">
                            ' . ($stampPath ? '<img src="' . htmlspecialchars($stampPath) . '" alt="Печать" class="signature-stamp" />' : '') . '
                            ' . ($signaturePath ? '<img src="' . htmlspecialchars($signaturePath) . '" alt="Подпись" class="signature-sign" />' : '') . '
                        </div>'
                        : '') . '
                </div>
            </div>';
        }
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Техническое приложение</title>
    <style>' . $css . '</style>
</head>
<body>
    <div class="tech-container">
        ' . $headerHtml . '
        <div class="tech-title">Техническое приложение</div>
        <div class="tech-subtitle">к Коммерческому Предложению № ' . $proposalNumber . ' от ' . $proposalDate . '</div>
        <div class="tech-section">
            <h3>Общий технический комментарий</h3>
            <div class="tech-summary">' . $technicalSummaryHtml . '</div>
        </div>
        <div class="tech-section">
            <h3>Технические параметры редуктора</h3>
            <table class="tech-params-table">
                <tbody>
                    ' . $reducerRowsHtml . '
                </tbody>
            </table>
        </div>
        <div class="tech-section">
            <h3>Позиции предложения</h3>
            <table class="tech-items-table">
                <thead>
                    <tr>
                        <th style="width:5%; text-align:center;">№</th>
                        <th style="width:40%;">Описание</th>
                        <th style="width:8%; text-align:center;">Кол-во</th>
                        <th style="width:7%; text-align:center;">Ед.</th>
                        <th style="text-align:center;">Техническое описание</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $itemsRowsHtml . '
                </tbody>
            </table>
        </div>
        <div class="tech-note">' . $technicalBindingNote . '</div>
        ' . $signatureHtml . '
    </div>
</body>
</html>';
        return $html;
    }
    
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
                <div style="font-size: 10pt; color: #374151;">' . htmlspecialchars($data['recipientAddress'] ?? '') . '</div>
                ' . (!empty($data['recipientINN']) ? '<div style="font-size: 10pt; color: #374151; margin-top: 3px;">ИНН: ' . htmlspecialchars($data['recipientINN']) . '</div>' : '') . '
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
            <td>
                <div style="font-weight: bold;">' . htmlspecialchars($item['description'] ?? $item['type'] ?? '') . '</div>
                ' . (!empty($item['model']) ? '<div style="font-size: 0.9em; color: #555;">' . htmlspecialchars($item['model']) . '</div>' : '') . '
                ' . (!empty($item['name']) ? '<div style="font-size: 0.9em; color: #333;">' . htmlspecialchars($item['name']) . '</div>' : '') . '
            </td>
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
            <p><strong>' . (in_array(trim($data['incoterm'] ?? ''), ['САМОВЫВОЗ', 'ФРАНКО-СКЛАД ПРОДАВЦА', 'ДО ТРАНСПОРТА ПОКУПАТЕЛЯ'], true) ? 'Срок готовности' : 'Срок поставки') . ':</strong> ' . htmlspecialchars($data['deliveryTime'] ?? '') . '</p>
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
            margin: 10px 0;
            color: #1e40af;
        }
        .document-info {
            display: flex;
             text-align: left;
            margin-bottom: 5px;
        }
        .bill-to {
            flex: 3;
        }
        .invoice-details {
            flex: 1;
            text-align: left;
            margin-left: 140px;
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
            margin: 10px 0;
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
            margin-top: 15px;
            padding: 10px;
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
            line-height: 1.5;
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
            margin-left: 50px;
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

function getTechnicalAppendixStyles() {
    return '
        * { box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            color: #111827;
            margin: 2;
            padding: 5px;
            
        }
        .tech-container {
            max-width: 227mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 3mm;
            border-radius: 3px;
            box-shadow: 0 1px 1px rgba(15, 23, 42, 0.08);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
        }
        .logo {
            max-height: 60px !important;
            width: auto;
        }
        .company-info {
            text-align: right;
            font-size: 9pt;
            color: #475569;
        }
        .tech-title {
            text-align: center;
            font-size: 18pt;
            font-weight: bold;
            color: #1e3a8a;
            margin: 10px 0 4px;
        }
        .tech-subtitle {
            text-align: center;
            font-size: 11pt;
            color: #0f172a;
            margin-bottom: 16px;
        }
        .tech-intro {
            text-align: center;
            font-size: 9.5pt;
            color: #475569;
        }
        .tech-section {
            margin-top: 24px;
        }
        .tech-section h3 {
            font-size: 12pt;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 8px;
        }
        .tech-summary {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
            line-height: 1.5;
        }
        .drawing-number {
            font-size: 9pt;
            color: #475569;
            margin: 4px 0 8px;
        }
        .tech-params-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #e2e8f0;
        }
        .tech-params-table td {
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
            vertical-align: top;
            font-size: 9.5pt;
        }
        .tech-params-label {
            width: 35%;
            background: #f8fafc;
            font-weight: 600;
            color: #0f172a;
        }
        .tech-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .tech-items-table th {
            background: #1d4ed8;
            color: #fff;
            text-align: left;
            font-size: 10pt;
            padding: 8px;
        }
        .tech-items-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 8px;
            font-size: 9.5pt;
            vertical-align: top;
        }
        .tech-items-table tr:last-child td {
            border-bottom: none;
        }
        .item-title {
            font-weight: 600;
            color: #1f2937;
        }
        .item-meta {
            font-size: 8.5pt;
            color: #64748b;
        }
        .text-center { text-align: center; }
        .tech-note {
            margin-top: 18px;
            padding: 10px 14px;
            background: #e0f2fe;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            font-size: 9.5pt;
            color: #0369a1;
        }
        .signature {
            margin-top: 18px;
            padding-top: 12px;
            display: flex;
            align-items: flex-start;
            justify-content: flex-start;
            gap: 40px;
        }
        .signature-center {
            flex: 0 0 auto;
            display: flex;
            align-items: flex-start;
        }
        .signature-right {
            flex: 0 0 auto;
        }
        .signature-text {
            text-align: left;
            color: #0f172a;
        }
        .signature-position {
            font-size: 9pt;
            color: #475569;
        }
        .signature-images {
            position: relative;
            display: inline-block;
            margin-top: -5px;
        }
        .signature-stamp {
            max-width: 130px;
            height: auto;
            opacity: 0.85;
        }
        .signature-sign {
            position: absolute;
            top: 18px;
            left: 18px;
            max-width: 100px;
            height: auto;
        }
        .signature-technical {
            margin-bottom: 0;
        }
        @page {
            margin: 5mm;
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

