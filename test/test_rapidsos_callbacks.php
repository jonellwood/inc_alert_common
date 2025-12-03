<?php

/**
 * Test script for RapidSOS Alert Callbacks
 * 
 * Tests the callback functionality with a real or simulated alert ID
 */

require_once __DIR__ . '/../lib/rapidsos_callbacks.php';

echo "=== RapidSOS Alert Callbacks Test ===\n\n";

// Get alert ID from command line or use a test ID
$alertId = $argv[1] ?? null;

if (!$alertId) {
    echo "Usage: php test_rapidsos_callbacks.php <alert_id>\n";
    echo "Example: php test_rapidsos_callbacks.php alert-f1326dff-0fd4-4fe8-9924-69a65bd31488\n\n";
    echo "Or run in interactive mode:\n";
    exit(1);
}

// Validate alert ID format
if (!RapidSOSCallbacks::isValidAlertId($alertId)) {
    echo "❌ Invalid alert ID format. Expected: alert-{uuid}\n";
    echo "Example: alert-f1326dff-0fd4-4fe8-9924-69a65bd31488\n";
    exit(1);
}

echo "Testing with Alert ID: {$alertId}\n\n";

try {
    $callbacks = new RapidSOSCallbacks();
    $testCfsNumber = 'TEST-' . date('Ymd-His');

    // Test 1: Accept Alert
    echo "Test 1: Accepting alert...\n";
    $result = $callbacks->acceptAlert($alertId, $testCfsNumber);

    if ($result['success']) {
        echo "✅ Successfully accepted alert\n";
        echo "   HTTP Code: {$result['http_code']}\n";
        if ($result['response']) {
            echo "   Status: " . ($result['response']['status']['name'] ?? 'N/A') . "\n";
            echo "   Updated: " . date('Y-m-d H:i:s', ($result['response']['last_updated_time'] ?? 0) / 1000) . "\n";
        }
    } else {
        echo "❌ Failed to accept alert\n";
        echo "   Error: " . ($result['error'] ?? 'Unknown') . "\n";
        echo "   HTTP Code: " . ($result['http_code'] ?? 'N/A') . "\n";
    }
    echo "\n";

    // Wait before next call
    sleep(1);

    // Test 2: Set Disposition to DISPATCHED
    echo "Test 2: Setting disposition to DISPATCHED...\n";
    $result = $callbacks->setDisposition($alertId, 'DISPATCHED', $testCfsNumber);

    if ($result['success']) {
        echo "✅ Successfully set disposition\n";
        echo "   HTTP Code: {$result['http_code']}\n";
        if ($result['response']) {
            echo "   Disposition: " . ($result['response']['disposition']['name'] ?? 'N/A') . "\n";
            echo "   Display: " . ($result['response']['disposition']['display_name'] ?? 'N/A') . "\n";
        }
    } else {
        echo "❌ Failed to set disposition\n";
        echo "   Error: " . ($result['error'] ?? 'Unknown') . "\n";
        echo "   HTTP Code: " . ($result['http_code'] ?? 'N/A') . "\n";
    }
    echo "\n";

    // Test 3: Check log file
    echo "Test 3: Checking callback logs...\n";
    $logFile = __DIR__ . '/../logs/rapidsos_callbacks.log';

    if (file_exists($logFile)) {
        $logSize = filesize($logFile);
        echo "✅ Log file exists\n";
        echo "   Location: {$logFile}\n";
        echo "   Size: " . number_format($logSize) . " bytes\n";
        echo "   Last 10 lines:\n";
        echo "   " . str_repeat('-', 60) . "\n";

        $lines = file($logFile);
        $lastLines = array_slice($lines, -10);
        foreach ($lastLines as $line) {
            echo "   " . trim($line) . "\n";
        }
    } else {
        echo "⚠️  Log file not found (may not have been created yet)\n";
    }
    echo "\n";

    echo "=== Test Complete ===\n\n";
    echo "Summary:\n";
    echo "- Alert ID: {$alertId}\n";
    echo "- Test CFS Number: {$testCfsNumber}\n";
    echo "- Check logs: tail -f {$logFile}\n";
    echo "- Check RapidSOS portal for status updates\n";
} catch (Exception $e) {
    echo "❌ Exception occurred: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
