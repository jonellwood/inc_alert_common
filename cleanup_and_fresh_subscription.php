<?php

/**
 * Clean up all existing subscriptions and create ONE fresh subscription
 * This gives us a clean slate for testing
 */

require_once __DIR__ . '/lib/rapidsos_auth.php';

$config = require __DIR__ . '/config/rapidsos_config.php';
$auth = new RapidSOSAuth($config);

echo "=== Webhook Subscription Cleanup & Fresh Start ===\n\n";

// Step 1: Get access token
echo "1. Authenticating...\n";
$token = $auth->getAccessToken();
if (!$token) {
    die("ERROR: Failed to get access token\n");
}
echo "   ✓ Authenticated\n\n";

// Step 2: List all current subscriptions
echo "2. Listing current subscriptions...\n";
$webhookBaseUrl = $config['webhook_base_urls'][$config['environment']];

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

$data = json_decode($response, true);
$subscriptions = $data['subscriptions'] ?? [];

echo "   Found " . count($subscriptions) . " existing subscription(s)\n\n";

// Step 3: Delete all existing subscriptions
if (count($subscriptions) > 0) {
    echo "3. Deleting all existing subscriptions...\n";

    foreach ($subscriptions as $sub) {
        $subId = $sub['id'];
        echo "   Deleting subscription #$subId... ";

        $ch = curl_init($webhookBaseUrl . '/v1/webhooks/subscriptions/' . $subId);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token
            ]
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 204) {
            echo "✓ Deleted\n";
        } else {
            echo "✗ Failed (HTTP $httpCode)\n";
        }
    }
    echo "\n";
} else {
    echo "3. No existing subscriptions to delete\n\n";
}

// Step 4: Create ONE fresh subscription
echo "4. Creating ONE fresh webhook subscription...\n";

$subscriptionData = [
    'url' => $config['webhook_endpoint'],
    'event_types' => $config['default_events']
];

echo "   URL: " . $subscriptionData['url'] . "\n";
echo "   Events: " . implode(', ', $subscriptionData['event_types']) . "\n";

$ch = curl_init($webhookBaseUrl . '/v1/webhooks/subscriptions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($subscriptionData)
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 201) {
    $newSub = json_decode($response, true);
    echo "\n   ✓ SUCCESS! New subscription created:\n";
    echo "   ID: " . $newSub['id'] . "\n";
    echo "   Created: " . date('Y-m-d H:i:s') . "\n";
    echo "\n";
} else {
    echo "\n   ✗ FAILED (HTTP $httpCode)\n";
    echo "   Response: $response\n\n";
}

// Step 5: Verify
echo "5. Verifying - listing all subscriptions:\n";

$ch = curl_init($webhookBaseUrl . '/v1/webhooks/subscriptions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]
]);

$response = curl_exec($ch);
$data = json_decode($response, true);
$subscriptions = $data['subscriptions'] ?? [];

echo "   Total subscriptions: " . count($subscriptions) . "\n";

if (count($subscriptions) === 1) {
    echo "   ✓ Perfect! Exactly ONE subscription as expected\n";
} else {
    echo "   ⚠ Warning: Expected 1 subscription, found " . count($subscriptions) . "\n";
}

echo "\n=== Cleanup Complete! ===\n";
echo "\nNext steps:\n";
echo "1. Create a NEW demo alert in RapidSOS portal\n";
echo "2. Monitor with: ./monitor_new_alerts.sh\n";
echo "3. Check webhook logs on server: tail -f logs/webhook_debug.log\n";
echo "4. See if fresh subscription receives the NEW alert\n";
