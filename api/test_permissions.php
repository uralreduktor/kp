<?php
/**
 * Test script to check write permissions
 */

$archiveDir = dirname(__DIR__) . '/@archiv 2025';

echo "<h1>Diagnostic Test</h1>";
echo "<pre>";
echo "Archive Directory: " . $archiveDir . "\n";
echo "Absolute Path: " . realpath($archiveDir) . "\n";
echo "Directory exists: " . (is_dir($archiveDir) ? 'YES' : 'NO') . "\n";
echo "Is writable: " . (is_writable($archiveDir) ? 'YES' : 'NO') . "\n";
echo "Current user: " . get_current_user() . "\n";
echo "PHP process user: " . posix_getpwuid(posix_geteuid())['name'] . "\n";
echo "\n";

// Try to create a test file
$testFile = $archiveDir . '/test_' . time() . '.txt';
echo "Attempting to write test file: " . basename($testFile) . "\n";

try {
    $result = file_put_contents($testFile, "Test data\n");
    if ($result !== false) {
        echo "✓ SUCCESS: File written successfully!\n";
        echo "File size: " . $result . " bytes\n";
        
        // Clean up
        if (file_exists($testFile)) {
            unlink($testFile);
            echo "✓ Test file cleaned up\n";
        }
    } else {
        echo "✗ ERROR: Failed to write file\n";
    }
} catch (Exception $e) {
    echo "✗ EXCEPTION: " . $e->getMessage() . "\n";
}

echo "\n";
echo "Permissions on archive directory:\n";
echo shell_exec('ls -la "' . dirname($archiveDir) . '" | grep "@archiv 2025"');

echo "</pre>";


