<?php
// test_site_parser.php

require_once __DIR__ . '/api/connectors/B2BCenterConnector.php';
require_once __DIR__ . '/api/SiteOrganizationParser.php';

// Expose protected methods for testing
class TestConnector extends B2BCenterConnector {
    public function __construct() {
        parent::__construct([]);
    }
    
    public function publicFetch($url) {
        // Force fetchWithBrowser for debugging if curl fails or we want to test browser logic explicitly.
        // But here we want to test the full flow, so fetchContent is better as it contains the hybrid logic.
        return $this->fetchContent($url);
    }
}

$url = 'https://www.b2b-center.ru/app/market/postavka-valov/tender-4256022/';
echo "Target URL: $url\n\n";

$connector = new TestConnector();

// Create the parser with a fetcher callback
$parser = new SiteOrganizationParser(
    function($u) use ($connector) {
        // When the parser finds a link (e.g. firm profile), it calls this callback.
        // We want to use the same connector logic to fetch that firm profile.
        return $connector->publicFetch($u);
    },
    function($msg) {
        echo "$msg\n";
    }
);

// 1. Fetch Main Page
echo "1. Fetching main page...\n";
$html = $connector->publicFetch($url);

if (!$html) {
    die("Failed to fetch main URL.\n");
}

// 2. Prepare XPath
$dom = new DOMDocument();
@$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
$xpath = new DOMXPath($dom);

// 3. Run Parser
echo "2. Running SiteOrganizationParser...\n";
$result = $parser->parseOrganizer($xpath, $url);

echo "\n------------------------------------------------\n";
echo "RESULT:\n";
print_r($result);
echo "------------------------------------------------\n";

if (!empty($result['inn']) && $result['inn'] == '4205000908') {
    echo "SUCCESS: INN 4205000908 found!\n";
} else {
    echo "FAILURE: INN 4205000908 NOT found.\n";
    // Debug: Dump HTML snippet around "Организатор"
    echo "\nDEBUG: Searching for 'Организатор' in HTML...\n";
    if (mb_stripos($html, 'Организатор') !== false) {
        $pos = mb_stripos($html, 'Организатор');
        echo "Found 'Организатор' at pos $pos. Context:\n";
        echo mb_substr($html, $pos, 500) . "\n";
    } else {
        echo "'Организатор' NOT found in HTML content.\n";
        echo "Content start: " . substr($html, 0, 500) . "\n";
    }
}
