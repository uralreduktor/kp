<?php
require_once __DIR__ . '/api/organization_parser.php';

$orgFile = __DIR__ . '/js/organizations.js';
$orgContent = file_get_contents($orgFile);
$orgId = 'vector';

echo "Testing parser for orgId: $orgId\n";

$orgData = parseOrganizationData($orgId, $orgContent);

echo "Result:\n";
print_r($orgData);

if (empty($orgData['name'])) {
    echo "FAILED to parse name\n";
} else {
    echo "SUCCESS: Found name: " . $orgData['name'] . "\n";
}

// Test regex manually
echo "\nRegex Debug:\n";
if (preg_match("/'{$orgId}':\s*\{([^}]+(?:\{[^}]*\}[^}]*)*)\}/s", $orgContent, $matches)) {
    echo "Main regex matched.\n";
    // echo "Block content: " . $matches[1] . "\n";
} else {
    echo "Main regex FAILED to match.\n";
}
?>

