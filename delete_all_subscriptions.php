<?php

/**
 * Delete ALL webhook subscriptions - nuclear option
 */

require_once __DIR__ . '/lib/rapidsos_auth.php';

$config = require __DIR__ . '/config/rapidsos_config.php';
$auth = new RapidSOSAuth($config);

echo "=== DELETE ALL SUBSCRIPTIONS ===\n\n";

// Get token
echo "Authenticating...\n";
$token = $auth->getAccessToken();
if (!$token) {
    die("ERROR: Failed to get access token\n");
}
echo "✓ Authenticated\n\n";

$webhookBaseUrl = $config['webhook_base_urls'][$config['environment']];

// Get all subscriptions
echo "Fetching all subscriptions...\n";
$ch = curl_init($webhookBaseUrl . '/v1/webhooks/subscriptions');
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

if ($httpCode !== 200) {
    die("ERROR: Failed to list subscriptions (HTTP $httpCode)\n");
}

$subscriptions = json_decode($response, true);

// Handle both array response and object with 'subscriptions' key
if (!is_array($subscriptions)) {
    $subscriptions = [];
} elseif (isset($subscriptions['subscriptions'])) {
    $subscriptions = $subscriptions['subscriptions'];
}

echo "Found " . count($subscriptions) . " subscription(s)\n\n";

if (count($subscriptions) === 0) {
    die("No subscriptions to delete!\n");
}

// Delete each one
foreach ($subscriptions as $sub) {
    $subId = $sub['id'];
    echo "Deleting subscription #$subId... ";

    $ch = curl_init($webhookBaseUrl . '/v1/webhooks/subscriptions/' . $subId);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 204) {
        echo "✓ Deleted\n";
    } else {
        echo "✗ Failed (HTTP $httpCode)\n";
        if ($response) {
            echo "   Response: $response\n";
        }
    }
}

echo "\nAll done! All subscriptions deleted.\n";
