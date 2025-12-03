<?php

/**
 * Update webhook subscription to point to catch-all logger
 */

require_once __DIR__ . '/lib/rapidsos_auth.php';

$config = require __DIR__ . '/config/rapidsos_config.php';
$auth = new RapidSOSAuth($config);

echo "=== Update Subscription to Catch-All Logger ===\n\n";

// Get token
$token = $auth->getAccessToken();
if (!$token) {
    die("ERROR: Failed to get access token\n");
}

$webhookBaseUrl = $config['webhook_base_urls'][$config['environment']];

// The subscription ID we want to update
$subscriptionId = 1673611;

// New URL - the catch-all logger
$newUrl = 'https://my.berkeleycountysc.gov/redfive/webhooks/catch_all_logger.php';

echo "Updating subscription #$subscriptionId\n";
echo "New URL: $newUrl\n\n";

// Delete old subscription and create new one (RapidSOS API doesn't support PATCH/PUT)
echo "Step 1: Delete old subscription...\n";
$ch = curl_init($webhookBaseUrl . '/v1/webhooks/subscriptions/' . $subscriptionId);
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
    echo "✓ Deleted\n\n";
} else {
    echo "⚠ Delete returned HTTP $httpCode (may already be deleted)\n\n";
}

// Create new subscription with catch-all URL
echo "Step 2: Create new subscription with catch-all logger...\n";

$subscriptionData = [
    'url' => $newUrl,
    'event_types' => $config['default_events']
];

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
    echo "✓ SUCCESS!\n";
    echo "New Subscription ID: " . $newSub['id'] . "\n";
    echo "URL: " . $newSub['url'] . "\n";
    echo "Events: " . implode(', ', $newSub['event_types']) . "\n\n";

    echo "=== NEXT STEPS ===\n";
    echo "1. Upload catch_all_logger.php to your server:\n";
    echo "   /var/www/acotocad/redfive/webhooks/catch_all_logger.php\n\n";
    echo "2. Create a NEW demo alert in RapidSOS portal\n\n";
    echo "3. Check the catch-all log on your server:\n";
    echo "   tail -f /var/www/acotocad/redfive/logs/raw_webhook_catch_all.log\n\n";
    echo "4. If you see ANYTHING in that log, RapidSOS IS sending webhooks!\n";
    echo "   If the log stays empty, RapidSOS is NOT sending webhooks.\n";
} else {
    echo "✗ FAILED (HTTP $httpCode)\n";
    echo "Response: $response\n";
}
