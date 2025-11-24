<?php
/**
 * Парсер данных организации из organizations.js
 * Используется в generateInvoiceHTML для извлечения данных организации
 */

/**
 * Извлекает данные организации из organizations.js
 * 
 * @param string $orgId ID организации
 * @param string $orgContent Содержимое файла organizations.js
 * @return array Массив с данными организации
 */
function parseOrganizationData($orgId, $orgContent) {
    $orgData = [
        'name' => '',
        'address' => '',
        'phone' => '',
        'email' => '',
        'INN_vektor' => '',
        'logo' => '',
        'stamp' => '',
        'signature' => '',
        'code' => ''
    ];
    
    // Ищем блок организации по ID (улучшенный regex для вложенных объектов)
    if (preg_match("/'{$orgId}':\s*\{([^}]+(?:\{[^}]*\}[^}]*)*)\}/s", $orgContent, $matches)) {
        $orgBlock = $matches[1];
        
        // Поддерживаем оба формата: 'key': и key:
        if (preg_match("/(?:'name'|name):\s*'([^']+)'/", $orgBlock, $matches)) {
            $orgData['name'] = $matches[1];
        }
        if (preg_match("/(?:'address'|address):\s*'([^']+)'/", $orgBlock, $matches)) {
            $orgData['address'] = $matches[1];
        }
        if (preg_match("/(?:'phone'|phone):\s*'([^']+)'/", $orgBlock, $matches)) {
            $orgData['phone'] = $matches[1];
        }
        if (preg_match("/(?:'email'|email):\s*'([^']+)'/", $orgBlock, $matches)) {
            $orgData['email'] = $matches[1];
        }
        // Извлекаем ИНН организации (поддерживаем оба формата)
        if (preg_match("/(?:'INN_vektor'|INN_vektor):\s*'([^']+)'/", $orgBlock, $matches)) {
            $orgData['INN_vektor'] = $matches[1];
        }
        if (preg_match("/(?:'logo'|logo):\s*'([^']+)'/", $orgBlock, $matches)) {
            $orgData['logo'] = $matches[1];
        }
        if (preg_match("/(?:'stamp'|stamp):\s*'([^']+)'/", $orgBlock, $matches)) {
            $orgData['stamp'] = $matches[1];
        }
        if (preg_match("/(?:'signature'|signature):\s*'([^']+)'/", $orgBlock, $matches)) {
            $orgData['signature'] = $matches[1];
        }
        // Извлекаем код организации (поддерживаем оба формата)
        if (preg_match("/(?:'code'|code):\s*'([^']+)'/", $orgBlock, $matches)) {
            $orgData['code'] = $matches[1];
        }
    }
    
    return $orgData;
}

/**
 * Извлекает банковские реквизиты организации
 * 
 * @param string $orgId ID организации
 * @param string $orgContent Содержимое файла organizations.js
 * @param string|null $selectedBankId ID выбранного банка (опционально)
 * @return array Массив с банковскими реквизитами или пустой массив
 */
function parseBankingDetails($orgId, $orgContent, $selectedBankId = null) {
    $bankingDetails = [
        'bankName' => '',
        'bankAddress' => '',
        'account' => '',
        'bik' => '',
        'correspondentAccount' => '',
        'beneficiary' => ''
    ];
    
    $escapedOrgId = preg_quote($orgId, '/');
    
    // Ищем блок организации по ID (улучшенный regex для вложенных объектов)
    if (preg_match("/'{$escapedOrgId}':\s*\{([^}]+(?:\{[^}]*\}[^}]*)*)\}/s", $orgContent, $matches)) {
        $orgBlock = $matches[1];
        
        // Извлекаем отдельные поля банковских реквизитов
        // Поддерживаем оба формата: 'key': и key:
        if (preg_match("/(?:'bankName'|bankName):\s*'([^']+)'/", $orgBlock, $matches)) {
            $bankingDetails['bankName'] = $matches[1];
        }
        if (preg_match("/(?:'bankAddress'|bankAddress):\s*'([^']+)'/", $orgBlock, $matches)) {
            $bankingDetails['bankAddress'] = $matches[1];
        }
        if (preg_match("/(?:'account'|account):\s*'([^']+)'/", $orgBlock, $matches)) {
            $bankingDetails['account'] = $matches[1];
        }
        if (preg_match("/(?:'bik'|bik):\s*'([^']+)'/", $orgBlock, $matches)) {
            $bankingDetails['bik'] = $matches[1];
        }
        if (preg_match("/(?:'correspondentAccount'|correspondentAccount):\s*'([^']+)'/", $orgBlock, $matches)) {
            $bankingDetails['correspondentAccount'] = $matches[1];
        }
        if (preg_match("/(?:'beneficiary'|beneficiary):\s*'([^']+)'/", $orgBlock, $matches)) {
            $bankingDetails['beneficiary'] = $matches[1];
        }
    }
    
    // Если не нашли новые поля, проверяем старый формат bankingDetails (для обратной совместимости)
    if (empty($bankingDetails['bankName'])) {
        $escapedOrgId = preg_quote($orgId, '/');
        
        // Находим позицию начала блока организации
        $orgStartPattern = "/'{$escapedOrgId}':\s*\{/";
        if (preg_match($orgStartPattern, $orgContent, $startMatch, PREG_OFFSET_CAPTURE)) {
            $startPos = $startMatch[0][1];
            $startBracePos = $startMatch[0][1] + strlen($startMatch[0][0]) - 1;
            
            // Находим закрывающую скобку блока организации (учитываем вложенность)
            $braceCount = 1;
            $pos = $startBracePos + 1;
            $endPos = strlen($orgContent);
            
            while ($pos < $endPos && $braceCount > 0) {
                $char = $orgContent[$pos];
                if ($char === '{') {
                    $braceCount++;
                } elseif ($char === '}') {
                    $braceCount--;
                }
                $pos++;
            }
            
            if ($braceCount === 0) {
                // Извлекаем блок организации
                $orgBlock = substr($orgContent, $startPos, $pos - $startPos);
                
                // Ищем старый формат bankingDetails.ru
                $bankingPatterns = [
                    "/bankingDetails:\s*\{.*?ru:\s*`([^`]+)`/s",
                    "/bankingDetails:\s*\{.*?'ru':\s*`([^`]+)`/s"
                ];
                
                foreach ($bankingPatterns as $pattern) {
                    if (preg_match($pattern, $orgBlock, $matches)) {
                        // Парсим старый формат строки в отдельные поля
                        $oldFormat = trim($matches[1]);
                        if (preg_match('/Банк:\s*(.+?)(?:\n|$)/', $oldFormat, $m)) {
                            $bankingDetails['bankName'] = trim($m[1]);
                        }
                        if (preg_match('/Адрес банка:\s*(.+?)(?:\n|$)/', $oldFormat, $m)) {
                            $bankingDetails['bankAddress'] = trim($m[1]);
                        }
                        if (preg_match('/Счет:\s*(.+?)(?:\n|$)/', $oldFormat, $m)) {
                            $bankingDetails['account'] = trim($m[1]);
                        }
                        if (preg_match('/БИК:\s*(.+?)(?:\n|$)/', $oldFormat, $m)) {
                            $bankingDetails['bik'] = trim($m[1]);
                        }
                        if (preg_match('/Корр\.\s*счет:\s*(.+?)(?:\n|$)/', $oldFormat, $m)) {
                            $bankingDetails['correspondentAccount'] = trim($m[1]);
                        }
                        if (preg_match('/Бенефициар:\s*(.+?)(?:\n|$)/', $oldFormat, $m)) {
                            $bankingDetails['beneficiary'] = trim($m[1]);
                        }
                        break;
                    }
                }
            }
        }
    }
    
    return $bankingDetails;
}

