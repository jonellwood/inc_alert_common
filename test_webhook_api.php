<?php
// Test RapidSOS webhook delivery API

require_once __DIR__ . '/config/rapidsos_config.php';
require_once __DIR__ . '/lib/rapidsos_auth.php';

$config = require __DIR__ . '/config/rapidsos_config.php';
$auth = new RapidSOSAuth($config);

try {
    $token = $auth->getAccessToken();
    $baseUrl = $config['webhook_base_urls'][$config['environment']];

    // Test 1: With just subscription_id
    echo "Test 1: Sending test webhook with subscription_id only\n";
    echo "=======================================================\n";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/v1/webhooks/test',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode(['subscription_id' => 1682247])
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";

    // Test 2: With subscription_id and event_type
    echo "Test 2: Sending test webhook with subscription_id and event_type\n";
    echo "================================================================\n";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/v1/webhooks/test',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'subscription_id' => 1682247,
            'event_type' => 'alert.new'
        ])
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n\n";

    echo "Check your webhook logs:\n";
    echo "  tail -20 logs/webhook_debug.log\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
