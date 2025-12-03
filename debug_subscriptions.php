<?php

/**
 * Debug: Check what's really happening with subscriptions
 */

require_once __DIR__ . '/lib/rapidsos_auth.php';

$config = require __DIR__ . '/config/rapidsos_config.php';
$auth = new RapidSOSAuth($config);

echo "=== Subscription API Debug ===\n\n";

// Get token
$token = $auth->getAccessToken();
$webhookBaseUrl = $config['webhook_base_urls'][$config['environment']];

echo "Webhook Base URL: $webhookBaseUrl\n";
echo "Full URL: $webhookBaseUrl/v1/webhooks/subscriptions\n\n";

// Try different endpoints
$endpoints = [
    'api-sandbox' => 'https://api-sandbox.rapidsos.com/v1/webhooks/subscriptions',
    'edx-sandbox' => 'https://edx-sandbox.rapidsos.com/v1/webhooks/subscriptions'
];

foreach ($endpoints as $name => $url) {
    echo "Testing $name: $url\n";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "  HTTP Status: $httpCode\n";

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $count = isset($data['subscriptions']) ? count($data['subscriptions']) : 'N/A';
        echo "  Subscriptions found: $count\n";

        if ($count > 0) {
            foreach ($data['subscriptions'] as $sub) {
                echo "    - ID: " . $sub['id'] . " (" . substr($sub['url'], 0, 50) . "...)\n";
            }
        }
    } else {
        echo "  Response: " . substr($response, 0, 200) . "\n";
    }

    echo "\n";
}
