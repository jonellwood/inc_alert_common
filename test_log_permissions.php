<?php
// Test script to verify log file permissions
// Save this as test_log_permissions.php on your server

$logFile = __DIR__ . '/logs/permission_test.log';
$testMessage = date('Y-m-d H:i:s') . " - Permission test from web server\n";

echo "Testing log file permissions...\n";
echo "Log file path: " . $logFile . "\n";

// Test writing to log file
if (file_put_contents($logFile, $testMessage, FILE_APPEND)) {
    echo "✅ SUCCESS: Web server can write to log files!\n";
    echo "Test message written to: " . $logFile . "\n";

    // Check if file was created with correct permissions
    $perms = fileperms($logFile);
    echo "File permissions: " . substr(sprintf('%o', $perms), -4) . "\n";
} else {
    echo "❌ ERROR: Cannot write to log file!\n";
    echo "Check directory permissions and ownership.\n";
}

// Test creating logs directory if it doesn't exist
$logsDir = __DIR__ . '/logs';
if (!is_dir($logsDir)) {
    if (mkdir($logsDir, 0775, true)) {
        echo "✅ Created logs directory\n";
    } else {
        echo "❌ Cannot create logs directory\n";
    }
} else {
    echo "✅ Logs directory exists\n";
    $perms = fileperms($logsDir);
    echo "Directory permissions: " . substr(sprintf('%o', $perms), -4) . "\n";
}
