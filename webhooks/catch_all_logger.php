<?php

/**
 * Ultra-Simple Webhook Logger
 * Logs EVERYTHING that hits this endpoint - no validation, no processing
 * Use this to see what RapidSOS is actually sending (if anything)
 */

// Log file
$logFile = __DIR__ . '/../logs/raw_webhook_catch_all.log';
$logDir = dirname($logFile);

// Create logs directory if needed
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// Capture EVERYTHING
$data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'UNKNOWN',
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
    'remote_port' => $_SERVER['REMOTE_PORT'] ?? 'UNKNOWN',
    'http_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'UNKNOWN',
    'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 0,
    'headers' => getallheaders(),
    'raw_body' => file_get_contents('php://input'),
    'get_params' => $_GET,
    'post_params' => $_POST,
    'server_vars' => $_SERVER
];

// Log it
file_put_contents(
    $logFile,
    "=== NEW REQUEST ===" . PHP_EOL .
        json_encode($data, JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL,
    FILE_APPEND
);

// Always return 200 OK
http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'message' => 'Request logged successfully',
    'timestamp' => time()
]);
