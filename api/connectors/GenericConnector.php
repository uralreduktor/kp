<?php

require_once __DIR__ . '/AbstractConnector.php';

class GenericConnector extends AbstractConnector {
    
    public function parse($url) {
        $data = [];
        $html = $this->request($url);
        if (!$html) return $data;
        
        $xpath = $this->getXPath($html);
        
        // 1. Номер тендера из URL
        if (preg_match('/(\d{6,})/', $url, $m)) {
            $data['tenderNumber'] = $m[1];
        }
        
        // 2. Товары - ищем таблицы
        // Эвристика: таблица с заголовками "Наименование" и "Количество"
        $tables = $xpath->query("//table");
        foreach ($tables as $table) {
            $rows = $xpath->query(".//tr", $table);
            $nameIdx = -1;
            $qtyIdx = -1;
            
            // Анализ заголовков
            foreach ($rows as $rIdx => $row) {
                $cells = $xpath->query(".//th | .//td", $row);
                $cellTexts = [];
                foreach ($cells as $c) $cellTexts[] = mb_strtolower($this->cleanText($c->textContent));
                
                foreach ($cellTexts as $i => $txt) {
                    if (strpos($txt, 'наименование') !== false) $nameIdx = $i;
                    if (strpos($txt, 'количество') !== false || strpos($txt, 'кол-во') !== false) $qtyIdx = $i;
                }
                
                if ($nameIdx >= 0 && $qtyIdx >= 0) {
                    // Парсим данные (начиная со следующей строки)
                    $items = [];
                    $dataRows = $xpath->query(".//tr", $table); // заново берем все строки
                    
                    for ($k = $rIdx + 1; $k < $dataRows->length; $k++) {
                        $dRow = $dataRows->item($k);
                        $dCells = $xpath->query(".//td", $dRow);
                        
                        if ($dCells->length > max($nameIdx, $qtyIdx)) {
                            $name = $this->cleanText($dCells->item($nameIdx)->textContent);
                            $qStr = $this->cleanText($dCells->item($qtyIdx)->textContent);
                            $qVal = floatval(str_replace(',', '.', preg_replace('/[^\d.,]/', '', $qStr)));
                            
                            if ($name && $qVal > 0) {
                                $items[] = ['name' => $name, 'quantity' => $qVal];
                            }
                        }
                    }
                    
                    if (!empty($items)) {
                        $data['items'] = $items;
                        $data['itemName'] = $items[0]['name'];
                        $data['quantity'] = $items[0]['quantity'];
                        break 2; // Нашли и распарсили
                    }
                }
            }
        }
        
        // 3. Заказчик
        $labels = ["Заказчик", "Организатор", "Покупатель", "Customer", "Organizer"];
        foreach ($labels as $lbl) {
            // Ищем: Label -> Sibling
            $nodes = $xpath->query("//*[contains(text(), '$lbl')]/following-sibling::*[1]");
            if ($nodes && $nodes->length > 0) {
                $txt = $this->cleanText($nodes->item(0)->textContent);
                if (mb_strlen($txt) > 3 && mb_strlen($txt) < 200) {
                    $data['recipient'] = $txt;
                    break;
                }
            }
        }
        
        // ИНН пробуем Regex по всей странице
        if (preg_match('/ИНН[:\s]*(\d{10}|\d{12})/', $html, $m)) {
            $data['recipientINN'] = $m[1];
        }
        
        return $data;
    }
}

