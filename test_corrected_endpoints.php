<?php
// Test the corrected webhook subscription endpoints
require_once __DIR__ . '/config/rapidsos_config.php';
require_once __DIR__ . '/lib/rapidsos_auth.php';
require_once __DIR__ . '/manage/subscriptions.php';

$subs = new RapidSOSSubscriptions();
echo "Testing RapidSOS Webhook Subscription with Corrected Endpoints\n";
echo "============================================================\n\n";

// Test listing subscriptions
echo "1. Listing existing subscriptions...\n";
$list = $subs->listSubscriptions();
echo "   Status: " . ($list['success'] ? 'SUCCESS' : 'FAILED') . "\n";
if ($list['success']) {
    echo "   Found: " . count($list['data']) . " subscription(s)\n";
    if (!empty($list['data'])) {
        print_r($list['data']);
    }
} else {
    echo "   Error: " . $list['error'] . "\n";
    echo "   HTTP Code: " . ($list['http_code'] ?? 'N/A') . "\n";
}
echo "\n";

// Test creating a subscription
echo "2. Creating new webhook subscription...\n";
$create = $subs->createSubscription();
echo "   Status: " . ($create['success'] ? 'SUCCESS' : 'FAILED') . "\n";
if ($create['success']) {
    echo "   Subscription created successfully!\n";
    print_r($create['data']);
} else {
    echo "   Error: " . $create['error'] . "\n";
    echo "   HTTP Code: " . ($create['http_code'] ?? 'N/A') . "\n";
}
echo "\n";

// Show connection details
echo "3. Configuration Details:\n";
$config = require __DIR__ . '/config/rapidsos_config.php';
echo "   Environment: " . $config['environment'] . "\n";
echo "   Webhook Base URL: " . $config['webhook_base_urls'][$config['environment']] . "\n";
echo "   OAuth Base URL: " . $config['base_urls'][$config['environment']] . "\n";
echo "   Target Webhook: " . $config['webhook_endpoint'] . "\n";
