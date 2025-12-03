<?php

/**
 * Delete Subscription #1660551 - The RapidSOS Internal Receiver
 * This subscription is likely intercepting demo alerts
 */

require_once __DIR__ . '/config/rapidsos_config.php';
require_once __DIR__ . '/lib/rapidsos_auth.php';

echo "=================================================\n";
echo "Delete Subscription #1660551\n";
echo "=================================================\n\n";

echo "⚠️  WARNING: This will delete the subscription pointing to:\n";
echo "https://edx-sandbox.rapidsos.com/v1/alert-webhook-receiver/...\n\n";

echo "This subscription might be catching demo alerts that should\n";
echo "be going to your endpoint instead.\n\n";

echo "Type 'yes' to proceed with deletion: ";
$input = trim(fgets(STDIN));

if (strtolower($input) !== 'yes') {
    die("Deletion cancelled.\n");
}

$config = require __DIR__ . '/config/rapidsos_config.php';
$auth = new RapidSOSAuth($config);

$token = $auth->getAccessToken();
if (!$token) {
    die("Failed to get access token\n");
}

echo "\nDeleting subscription #1660551...\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api-sandbox.rapidsos.com/v1/webhooks/subscriptions/1660551',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 204 || $httpCode === 200) {
    echo "✅ SUCCESS! Subscription #1660551 has been deleted.\n\n";
    echo "Next steps:\n";
    echo "1. Create a demo alert in the RapidSOS portal\n";
    echo "2. Monitor your webhook endpoint:\n";
    echo "   tail -f logs/webhook_debug.log\n";
    echo "3. Check if the alert reaches your server!\n\n";

    echo "If alerts still don't arrive, the issue is elsewhere.\n";
} elseif ($httpCode === 404) {
    echo "⚠️  Subscription #1660551 not found (already deleted?)\n";
} else {
    echo "❌ Deletion failed: HTTP $httpCode\n";
    echo "Response: $response\n";
}
