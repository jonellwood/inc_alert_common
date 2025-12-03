<?php
// Quick test to see the actual subscription response structure from RapidSOS API

require_once __DIR__ . '/config/rapidsos_config.php';
require_once __DIR__ . '/lib/rapidsos_auth.php';

$config = require __DIR__ . '/config/rapidsos_config.php';
$auth = new RapidSOSAuth($config);
$baseUrl = $config['webhook_base_urls'][$config['environment']];

try {
    $token = $auth->getAccessToken();

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . '/v1/webhooks/subscriptions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Code: $httpCode\n\n";
    echo "Raw Response:\n";
    echo $response . "\n\n";

    $decoded = json_decode($response, true);
    echo "Decoded Response:\n";
    print_r($decoded);

    echo "\n\nResponse Structure Analysis:\n";
    if (is_array($decoded) && count($decoded) > 0) {
        $first = $decoded[0];
        echo "First subscription keys: " . implode(', ', array_keys($first)) . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
