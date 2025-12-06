<?php
/**
 * CLI Script to render Invoice HTML from JSON input
 * Usage: php api/cli/render_invoice.php < input.json > output.html
 */

// Set error reporting to stderr so it doesn't pollute stdout HTML
ini_set('display_errors', 'stderr');
error_reporting(E_ALL);

// Correct path to include generator relative to api/cli/
require_once __DIR__ . '/../invoice_html_generator.php';

// Read JSON from STDIN
$input = file_get_contents('php://stdin');
if (!$input) {
    fwrite(STDERR, "Error: No input provided\n");
    exit(1);
}

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    fwrite(STDERR, "Error: Invalid JSON: " . json_last_error_msg() . "\n");
    exit(1);
}

try {
    $orgId = $data['organizationId'] ?? 'SYP';
    $docType = $data['documentType'] ?? 'commercial';
    // We use 'playwright' style by default as it matches what we want
    $html = generateInvoiceHTML($data, $orgId, 'playwright', $docType);
    echo $html;
} catch (Exception $e) {
    fwrite(STDERR, "Error rendering HTML: " . $e->getMessage() . "\n");
    exit(1);
}

