<?php

require_once __DIR__ . '/AbstractConnector.php';
require_once __DIR__ . '/../SiteOrganizationParser.php';

class B2BCenterConnector extends AbstractConnector {
    
    public function login() {
        if (empty($this->credentials['login']) || empty($this->credentials['password'])) {
            return false;
        }
        return true;
    }

    public function parse($url) {
        $data = [];
        
        // 1. Подготовка URL (positions)
        // По умолчанию пробуем взять страницу с позициями, чтобы распарсить товары
        $targetUrl = $url;
        if (strpos($url, 'action=positions') === false) {
            $cleanUrl = rtrim($url, '/');
            $sep = (strpos($cleanUrl, '?') !== false) ? '&' : '?';
            $targetUrl = $cleanUrl . $sep . 'action=positions';
        }
        
        // 2. Загрузка страницы (сначала позиции)
        $html = $this->fetchContent($targetUrl);
        
        if (!$html) return $data;
        
        $xpath = $this->getXPath($html);
        
        // 3. Парсинг данных (Tender Number)
        if (preg_match('/tender-(\d+)/i', $url, $m)) {
            $data['tenderNumber'] = $m[1];
        } elseif (preg_match('/id=(\d+)/i', $url, $m)) {
            $data['tenderNumber'] = $m[1];
        }
        
        // 4. Товары (items)
        $items = $this->parseItems($xpath);
        if (!empty($items)) {
            $data['items'] = $items;
            $data['itemName'] = $items[0]['name'];
            $data['quantity'] = $items[0]['quantity'];
        }
        
        // 5. Организатор / Заказчик / ИНН
        // Инициализируем новый парсер
        $fetcher = function($u) { return $this->fetchContent($u); };
        $logger = function($msg) { $this->log($msg); };
        
        $orgParser = new SiteOrganizationParser($fetcher, $logger);
        
        // Сначала ищем на текущей странице (action=positions)
        $organizerInfo = $orgParser->parseOrganizer($xpath, $targetUrl);
        
        // Если не нашли имя, пробуем загрузить главную страницу
        if (empty($organizerInfo['name']) && strpos($targetUrl, 'action=positions') !== false) {
             $this->log("Organizer not found on positions page. Fetching main page...");
             $mainUrl = str_replace(['?action=positions', '&action=positions'], '', $targetUrl);
             if (strpos($url, 'action=positions') === false) {
                 $mainUrl = $url;
             }
             
             $mainHtml = $this->fetchContent($mainUrl);
             if ($mainHtml) {
                 $mainXpath = $this->getXPath($mainHtml);
                 $organizerInfo = $orgParser->parseOrganizer($mainXpath, $mainUrl);
             }
        }
        
        // Маппинг результатов
        if (!empty($organizerInfo['name'])) {
            $data['recipient'] = $organizerInfo['name'];
        }
        
        if (!empty($organizerInfo['inn'])) {
            $data['recipientINN'] = $organizerInfo['inn'];
            $this->log("Final INN determined: " . $organizerInfo['inn']);
        }
        
        return $data;
    }
    
    protected function fetchContent($url) {
        echo "[DEBUG] fetchContent called for $url\n";
        // Сначала пробуем cURL
        $html = $this->request($url);
        
        echo "[DEBUG] cURL size: " . strlen($html) . "\n";

        // Проверка качества контента от cURL
        $isSuspicious = false;
        
        // 1. Проверка по размеру (обычно страница тендера > 20KB)
        if (!$html || strlen($html) < 20000) {
            $isSuspicious = true;
            $this->log("Suspicious content size: " . strlen($html));
            echo "[DEBUG] Suspicious content size detected (< 20000)\n";
        }
        
        // 2. Проверка по ключевым словам (если размер > 5000, но < 20000, или просто для надежности)
        if ($html && strlen($html) > 5000) {
            $keywords = ['Организатор', 'Заказчик', 'Покупатель', 'Продавец', 'tender_description', 'table', 'tr', 'td'];
            $found = false;
            foreach ($keywords as $kw) {
                if (mb_stripos($html, $kw) !== false) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $isSuspicious = true;
                $this->log("Keywords not found in content");
                echo "[DEBUG] Keywords not found in content\n";
            }
        }

        // Если cURL вернул подозрительно мало данных или ошибку, пробуем браузер
        if ($isSuspicious || $this->lastHttpCode !== 200) {
            $this->log("cURL result suspicious, trying browser...");
            echo "[DEBUG] Trying browser...\n";
            
            // Для B2B-Center добавляем специфический селектор, чтобы дождаться загрузки карточки организатора
            $waitForSelector = null;
            if (strpos($url, 'b2b-center.ru') !== false) {
                // Ждем либо ссылку на фирму, либо таблицу с информацией
                $waitForSelector = '.organizer-information, .customer-information, a[href*="/firms/"], .tender-description';
            }
            
            $browserHtml = $this->fetchWithBrowser($url, $waitForSelector);
            
            echo "[DEBUG] Browser returned size: " . strlen($browserHtml) . "\n";
            
            if ($browserHtml && strlen($browserHtml) > strlen($html)) {
                 $html = $browserHtml;
                 $this->log("Browser fetch successful. New size: " . strlen($html));
                 echo "[DEBUG] Browser fetch successful. Using browser content.\n";
            } else {
                 $this->log("Browser fetch failed or returned smaller content. Keeping cURL result.");
                 echo "[DEBUG] Browser fetch failed or returned smaller content. Keeping cURL result.\n";
            }
        }
        return $html;
    }
    
    private function parseItems($xpath) {
        $items = [];
        $dataRows = $xpath->query("//tr[contains(@class, 'c2')] | //tr[@class='c2']");
        if (!$dataRows || $dataRows->length === 0) {
            $dataRows = $xpath->query("//table//tr[td[position()>1]]");
        }
        
        if ($dataRows) {
            foreach ($dataRows as $row) {
                $cells = $xpath->query(".//td", $row);
                $cellArray = [];
                foreach ($cells as $cell) $cellArray[] = $this->cleanText($cell->textContent);
                
                $itemName = '';
                $qty = 0;
                
                if (count($cellArray) >= 4) {
                    if (!empty($cellArray[3])) $itemName = $cellArray[3];
                    elseif (!empty($cellArray[1])) $itemName = $cellArray[1];
                    
                    if (!empty($cellArray[4])) {
                        $qStr = str_replace(',', '.', preg_replace('/[^\d.,]/', '', $cellArray[4]));
                        $qty = floatval($qStr);
                    }
                }
                
                if ($itemName && $qty > 0) {
                    $items[] = [
                        'name' => $itemName,
                        'quantity' => $qty
                    ];
                }
            }
        }
        return $items;
    }
}
