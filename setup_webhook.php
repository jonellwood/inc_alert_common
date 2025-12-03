<?php
// Quick setup script to create RapidSOS webhook subscription

require_once __DIR__ . '/manage/subscriptions.php';

echo "RapidSOS Webhook Setup\n";
echo "=====================\n\n";

$subscriptions = new RapidSOSSubscriptions();

// Test connection first
echo "1. Testing RapidSOS connection...\n";
$connectionTest = $subscriptions->testConnection();

if (!$connectionTest['success']) {
    echo "❌ Connection failed: " . $connectionTest['error'] . "\n";
    exit(1);
}

echo "✅ Successfully connected to RapidSOS\n";
echo "   Token expires: " . $connectionTest['token_info']['expires_at'] . "\n\n";

// List existing subscriptions
echo "2. Checking existing subscriptions...\n";
$existing = $subscriptions->listSubscriptions();

if ($existing['success']) {
    if (empty($existing['data'])) {
        echo "   No existing subscriptions found.\n";
    } else {
        echo "   Found " . count($existing['data']) . " existing subscription(s):\n";
        foreach ($existing['data'] as $sub) {
            echo "   - ID: " . $sub['id'] . ", URL: " . $sub['url'] . "\n";
        }
    }
} else {
    echo "   ⚠️  Could not retrieve subscriptions: " . $existing['error'] . "\n";
}

echo "\n3. Creating new webhook subscription...\n";

// Create the subscription
$result = $subscriptions->createSubscription();

if ($result['success']) {
    echo "✅ Successfully created webhook subscription!\n";
    echo "   Subscription ID: " . $result['data']['id'] . "\n";
    echo "   Webhook URL: " . $result['data']['url'] . "\n";
    echo "   Events: " . implode(', ', $result['data']['event_types'] ?? []) . "\n";
    echo "   Created: " . date('Y-m-d H:i:s', $result['data']['created_time'] / 1000) . "\n";
} else {
    echo "❌ Failed to create subscription: " . $result['error'] . "\n";

    if (isset($result['details'])) {
        echo "   Details: " . json_encode($result['details'], JSON_PRETTY_PRINT) . "\n";
    }
}

echo "\n4. Webhook endpoint ready at:\n";
echo "   https://my.berkeleycountysc.gov/redfive/webhooks/rapidsos_webhook.php\n\n";

echo "Next steps:\n";
echo "- Deploy this code to your web server\n";
echo "- Ensure the webhook endpoint is accessible from the internet\n";
echo "- Monitor logs/webhook_debug.log for incoming webhooks\n";
echo "- Test with a RapidSOS alert or simulation\n";
