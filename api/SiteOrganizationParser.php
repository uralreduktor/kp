<?php

class SiteOrganizationParser {
    private $fetcher;
    private $logger;

    /**
     * @param callable $fetcher Function that accepts a URL and returns HTML content
     * @param callable|null $logger Function that accepts a string message
     */
    public function __construct(callable $fetcher, callable $logger = null) {
        $this->fetcher = $fetcher;
        $this->logger = $logger ?? function($msg) {};
    }

    private function log($msg) {
        call_user_func($this->logger, "[SiteOrganizationParser] " . $msg);
    }

    private function cleanText($text) {
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private function getXPath($html) {
        if (!$html) return null;
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        @$dom->loadHTML($html);
        libxml_clear_errors();
        return new DOMXPath($dom);
    }

    /**
     * Main method to find organization details
     */
    public function parseOrganizer($xpath, $contextUrl) {
        $customerName = '';
        $customerLink = '';
        $customerINN = '';
        
        $labels = ["Организатор", "Заказчик", "Покупатель", "Продавец"];
        $ignoredNames = ["Опубликована", "Завершена", "Архив", "Отменена", "Прием заявок", "Дата окончания", "Дата публикации", "Статус"];
        
        foreach ($labels as $lbl) {
            $this->log("Searching for label: $lbl");
            
            // Find all text nodes containing the label
            // Fallback: also search for elements directly (sometimes text is directly in element but text() query misses it in complex structures?)
            // Actually //text() is robust, but let's add checks.
            $textNodes = $xpath->query("//text()[contains(., '$lbl')]");
            
            // If no text nodes found, try finding elements (less precise but fallback)
            if (!$textNodes || $textNodes->length === 0) {
                $elementNodes = $xpath->query("//*[contains(text(), '$lbl')]");
                if ($elementNodes && $elementNodes->length > 0) {
                    // Extract text nodes from these elements
                    $textNodes = [];
                    foreach ($elementNodes as $el) {
                        // Just use the element itself as a "text node proxy" for our logic
                        // Our logic expects $textNode->parentNode.
                        // If we have element, we can just pretend its first child text node is what we want,
                        // or adjust logic.
                        // Simpler: Just treat the element as the "parent" of the text we are looking for.
                        // The logic below uses $parent = $textNode->parentNode.
                        // If we pass an element as $textNode, its parent is the container.
                        // But we want $parent to be the element containing the text.
                        // So we create a fake object or just skip this complexity and rely on //text().
                        // Actually, let's just iterate elements and check their children.
                        
                        // Logic adaptation:
                        $parent = $el; 
                        $candidates = [];
                        
                        // 1. Sibling of element
                        $next = $parent->nextSibling;
                        while ($next && $next->nodeType !== XML_ELEMENT_NODE) {
                            $next = $next->nextSibling;
                        }
                        if ($next) $candidates[] = $next;

                        // 2. Same element (for links inside)
                        $candidates[] = $parent;
                        
                        // Process candidates (duplicated logic, but safe)
                        foreach ($candidates as $node) {
                            // ... (same loop as below)
                            // Extract this into a helper method to avoid duplication?
                            // For now, let's just trust //text() usually works.
                        }
                    }
                }
            }
            
            foreach ($textNodes as $textNode) {
                // Ignore if text is too long (likely a description)
                if (strlen($textNode->textContent) > 100) continue;
                
                $candidates = [];
                $parent = $textNode->parentNode;

                // 1. Sibling of parent (e.g. <div><span>Label</span></div><div>Value</div>)
                if ($parent) {
                    $next = $parent->nextSibling;
                    while ($next && $next->nodeType !== XML_ELEMENT_NODE) {
                        $next = $next->nextSibling;
                    }
                    if ($next) $candidates[] = $next;
                }
                
                // 2. Parent's Parent next sibling (Generalized)
                 if ($parent && $parent->parentNode) {
                     $grandParentNext = $parent->parentNode->nextSibling;
                     while ($grandParentNext && $grandParentNext->nodeType !== XML_ELEMENT_NODE) {
                        $grandParentNext = $grandParentNext->nextSibling;
                     }
                     if ($grandParentNext) $candidates[] = $grandParentNext;
                 }

                // 3. Check inside the same parent (e.g. <div>Label: <a>Value</a></div>)
                if ($parent) $candidates[] = $parent;

                foreach ($candidates as $node) {
                    // Search for links inside candidate
                    $links = $xpath->query(".//a", $node);
                    foreach ($links as $link) {
                        $href = $link->getAttribute('href');
                        // Расширяем логику проверки:
                        // Если ссылка содержит "firms", считаем её правильной
                        // Плюс, игнорируем пустые имена (иногда внутри <a> есть <span>)
                        
                        if ($this->isFirmLink($href)) {
                            $name = $this->cleanText($link->textContent);
                            
                            // Если текст ссылки пустой, попробуем найти title или вложенные элементы
                            if (empty($name)) {
                                $name = $link->getAttribute('title');
                            }
                            
                            // Проверяем, не является ли имя служебным словом
                            $isValidName = true;
                            foreach ($ignoredNames as $ignored) {
                                if (mb_stripos($name, $ignored) !== false) {
                                    $isValidName = false;
                                    break;
                                }
                            }
                            
                            if ($isValidName && strlen($name) > 2) {
                                $customerName = $name;
                                $customerLink = $href;
                                $this->log("Found valid organizer via link: $name ($href)");
                                break 3; // Found it!
                            }
                        }
                    }
                    
                    // Fallback to text if no link found yet
                    if (empty($customerName) && empty($customerLink)) {
                         $text = $this->cleanText($node->textContent);
                         $text = str_replace($lbl, '', $text);
                         $text = trim($text, " :-\t\n\r\0\x0B");
                         
                         $isIgnored = false;
                         foreach ($ignoredNames as $ignored) {
                             if (mb_stripos($text, $ignored) !== false) {
                                 $isIgnored = true;
                                 break;
                             }
                         }
                         
                         if (!empty($text) && !$isIgnored && strlen($text) > 2 && strlen($text) < 200) {
                             $customerName = $text;
                             $this->log("Found potential organizer text: $text");
                         }
                    }
                }
            }
        }
        
        // Try to find INN nearby on the current page
        if ($customerName) {
            $this->log("Organizer identified as: $customerName, looking for INN nearby...");
            $innPatterns = [
                "//text()[contains(., '$customerName')]/ancestor::*[contains(@class, 'organizer') or contains(@class, 'customer')]//text()[contains(., 'ИНН')]",
                "//text()[contains(., 'ИНН')]/following-sibling::text()[1]",
                "//*[contains(text(), 'ИНН')]/following-sibling::*[1]",
            ];
            
            foreach ($innPatterns as $pattern) {
                $innNodes = $xpath->query($pattern);
                if ($innNodes && $innNodes->length > 0) {
                    $innText = $this->cleanText($innNodes->item(0)->textContent);
                    if (preg_match('/(\d{10}|\d{12})/', $innText, $m)) {
                        $customerINN = $m[1];
                        $this->log("Found INN on tender page: $customerINN");
                        break;
                    }
                }
            }
        }
        
        // If link found, fetch it to get details (INN)
        if ($customerLink) {
             $this->fetchFirmDetails($customerLink, $customerINN);
        }

        return ['name' => $customerName, 'link' => $customerLink, 'inn' => $customerINN];
    }

    private function isFirmLink($href) {
        if (empty($href)) return false;
        $firmPatterns = [
            '/firms/', '/company/', '/org/',
            'action=company', 'action=view', 'view_org',
            '/app/next/firms/'
        ];
        foreach ($firmPatterns as $pattern) {
            if (stripos($href, $pattern) !== false) return true;
        }
        return false;
    }

    private function fetchFirmDetails($link, &$inn) {
        // If INN is already found, no need to fetch (unless we want to double check, but for now skip)
        if (!empty($inn)) return;

        $firmUrl = (strpos($link, 'http') === 0) ? $link : 'https://www.b2b-center.ru' . $link;
        $this->log("Loading firm page: $firmUrl");
        
        $firmHtml = call_user_func($this->fetcher, $firmUrl);
        
        // Log content length for debugging
        $this->log("Firm page content length: " . strlen($firmHtml));
        
        if ($firmHtml) {
            $firmXpath = $this->getXPath($firmHtml);
            
            // 1. Table Search
            $innNode = $firmXpath->query("//*[contains(text(), 'ИНН')]/following-sibling::td[1]");
            if ($innNode && $innNode->length > 0) {
                $inn = preg_replace('/\D/', '', $innNode->item(0)->textContent);
                $this->log("Found INN via table: " . $inn);
                return;
            }
            
            // 2. Sibling Search
            $innNode = $firmXpath->query("//*[contains(text(), 'ИНН')]/following-sibling::*[1]");
            if ($innNode && $innNode->length > 0) {
                $innText = $this->cleanText($innNode->item(0)->textContent);
                if (preg_match('/(\d{10}|\d{12})/', $innText, $m)) {
                    $inn = $m[1];
                    $this->log("Found INN via following element: " . $inn);
                    return;
                }
            }
            
            // 3. Same Element Search
            $innNode = $firmXpath->query("//*[contains(text(), 'ИНН')]");
            if ($innNode && $innNode->length > 0) {
                $innText = $this->cleanText($innNode->item(0)->textContent);
                if (preg_match('/ИНН[:\s]*(\d{10}|\d{12})/', $innText, $m)) {
                    $inn = $m[1];
                    $this->log("Found INN in same element: " . $inn);
                    return;
                }
            }
            
            // 4. Next.js Data Search
            // Улучшенная проверка скриптов: иногда ID не указан явно или используется другой ID
            // Поэтому ищем любой скрипт с JSON внутри, который похож на __NEXT_DATA__
            // Или просто ищем JSON структуры в любом месте HTML
            
            // 4.1 Strict NEXT_DATA search
            if (preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $firmHtml, $m)) {
                $jsonData = json_decode($m[1], true);
                if ($jsonData) {
                    $foundInn = $this->findKeyInArray($jsonData, 'inn');
                    if ($foundInn) {
                        $inn = $foundInn;
                        $this->log("Found INN in __NEXT_DATA__: " . $inn);
                        return;
                    }
                }
            }
            
            // 4.2 Loose JSON search inside scripts (for properties like "inn": "123")
            // This helps if the script ID changed or we are inside a different JS object
            if (preg_match_all('/<script[^>]*>(.*?)<\/script>/s', $firmHtml, $matches)) {
                foreach ($matches[1] as $scriptContent) {
                    if (preg_match('/"inn"\s*:\s*"?(\d{10}|\d{12})"?/i', $scriptContent, $m)) {
                        $inn = $m[1];
                        $this->log("Found INN in generic script JSON: " . $inn);
                        return;
                    }
                }
            }

            // 5. Regex Search (Global in HTML)
             if (preg_match('/"inn"\s*:\s*"?(\d{10}|\d{12})"?/i', $firmHtml, $m)) {
                $inn = $m[1];
                $this->log("Found INN in global JSON regex: " . $inn);
                return;
            }
        }
    }

    private function findKeyInArray($array, $keyToFind) {
        foreach ($array as $key => $value) {
            if (strtolower($key) === strtolower($keyToFind)) {
                if (is_string($value) || is_int($value)) {
                    if (preg_match('/^(\d{10}|\d{12})$/', (string)$value)) {
                        return (string)$value;
                    }
                }
            }
            if (is_array($value)) {
                $result = $this->findKeyInArray($value, $keyToFind);
                if ($result) return $result;
            }
        }
        return null;
    }
}
