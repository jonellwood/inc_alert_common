<?php
// Real-time WebSocket Monitor for Demo Meeting
// Run this during the meeting to show live WebSocket activity

echo "RapidSOS WebSocket Monitor\n";
echo "=========================\n";
echo "Monitoring WebSocket logs in real-time...\n";
echo "Press Ctrl+C to stop\n\n";

$logFile = __DIR__ . '/logs/websocket_client.log';
$authLogFile = __DIR__ . '/logs/rapidsos_auth.log';

if (!file_exists($logFile)) {
    echo "WebSocket log file not found: $logFile\n";
    echo "Make sure the WebSocket client is running.\n";
    exit(1);
}

// Show last 10 lines to start
echo "Recent WebSocket Activity:\n";
echo "--------------------------\n";
$recent = shell_exec("tail -10 '$logFile'");
echo $recent . "\n";

echo "Authentication Status:\n";
echo "---------------------\n";
if (file_exists($authLogFile)) {
    $authRecent = shell_exec("tail -5 '$authLogFile'");
    echo $authRecent . "\n";
}

// Check if WebSocket client is running
$psOutput = shell_exec("ps aux | grep websocket_client.php | grep -v grep");
if ($psOutput) {
    echo "✓ WebSocket client is running\n";
} else {
    echo "⚠ WebSocket client is NOT running\n";
    echo "Start it with: php websocket_client.php\n";
}

echo "\nLive monitoring (new messages will appear below):\n";
echo "================================================\n";

// Monitor for new log entries
$lastSize = filesize($logFile);
while (true) {
    clearstatcache();
    $currentSize = filesize($logFile);

    if ($currentSize > $lastSize) {
        // New content added
        $handle = fopen($logFile, 'r');
        fseek($handle, $lastSize);
        $newContent = fread($handle, $currentSize - $lastSize);
        fclose($handle);

        echo $newContent;
        $lastSize = $currentSize;
    }

    sleep(1);
}
