<?php

/**
 * Simple subscription checker - works correctly with RapidSOS API response format
 */

require_once __DIR__ . '/lib/rapidsos_auth.php';

$config = require __DIR__ . '/config/rapidsos_config.php';
$auth = new RapidSOSAuth($config);

echo "=== RapidSOS Webhook Subscriptions ===\n\n";

// Get token
echo "Authenticating...\n";
$token = $auth->getAccessToken();
if (!$token) {
    die("ERROR: Failed to get access token\n");
}
echo "✓ Authenticated\n\n";

$webhookBaseUrl = $config['webhook_base_urls'][$config['environment']];
$url = $webhookBaseUrl . '/v1/webhooks/subscriptions';

echo "Fetching from: $url\n\n";

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

echo "HTTP Status: $httpCode\n";

if ($httpCode !== 200) {
    echo "ERROR: Failed to fetch subscriptions\n";
    echo "Response: $response\n";
    exit(1);
}

// Parse response - RapidSOS returns a direct array
$subscriptions = json_decode($response, true);

if (!is_array($subscriptions)) {
    echo "ERROR: Invalid response format\n";
    echo "Response: $response\n";
    exit(1);
}

echo "\nTotal subscriptions: " . count($subscriptions) . "\n\n";

if (count($subscriptions) === 0) {
    echo "No subscriptions found.\n";
    exit(0);
}

// Display each subscription
foreach ($subscriptions as $i => $sub) {
    echo "Subscription #" . ($i + 1) . ":\n";
    echo "  ID: " . ($sub['id'] ?? 'N/A') . "\n";
    echo "  URL: " . ($sub['url'] ?? 'N/A') . "\n";

    if (isset($sub['event_types']) && is_array($sub['event_types'])) {
        echo "  Events (" . count($sub['event_types']) . "): " . implode(', ', $sub['event_types']) . "\n";
    } else {
        echo "  Events: N/A\n";
    }

    if (isset($sub['created_time'])) {
        $created = date('Y-m-d H:i:s', $sub['created_time'] / 1000);
        echo "  Created: $created\n";
    }

    // Check if it points to YOUR server
    if (isset($sub['url']) && strpos($sub['url'], 'my.berkeleycountysc.gov') !== false) {
        echo "  ✅ Points to YOUR server\n";
    } else {
        echo "  ⚠️  Points to different server\n";
    }

    echo "\n";
}

echo "Done!\n";
