<?php

/**
 * Inspect Webhook Subscription #1660551
 * The mysterious subscription pointing to RapidSOS's own URL
 */

require_once __DIR__ . '/config/rapidsos_config.php';
require_once __DIR__ . '/lib/rapidsos_auth.php';

echo "=================================================\n";
echo "Webhook Subscription #1660551 Inspector\n";
echo "=================================================\n\n";

$config = require __DIR__ . '/config/rapidsos_config.php';
$auth = new RapidSOSAuth($config);

$token = $auth->getAccessToken();
if (!$token) {
    die("Failed to get access token\n");
}

echo "Looking at all webhook subscriptions...\n";
echo "----------------------------------------\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api-sandbox.rapidsos.com/v1/webhooks/subscriptions',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    die("Failed to get subscriptions: HTTP $httpCode\n");
}

$subscriptions = json_decode($response, true);

foreach ($subscriptions as $sub) {
    $id = $sub['id'];
    $url = $sub['url'];
    $created = date('Y-m-d H:i:s', $sub['created_time'] / 1000);
    $eventCount = count($sub['event_types']);

    echo "Subscription ID: $id\n";
    echo "Created: $created\n";
    echo "URL: $url\n";
    echo "Event Types: $eventCount events\n";

    // Check if this is the mysterious RapidSOS URL
    if (strpos($url, 'edx-sandbox.rapidsos.com') !== false) {
        echo "\n⚠️  THIS IS THE RAPIDSOS INTERNAL URL!\n";
        echo "This subscription points back to RapidSOS's own server.\n";
        echo "Hash: " . basename($url) . "\n";

        echo "\nEvent types:\n";
        foreach ($sub['event_types'] as $event) {
            echo "  - $event\n";
        }

        echo "\n🤔 HYPOTHESIS:\n";
        echo "This might be:\n";
        echo "1. A test/demo subscription created by RapidSOS\n";
        echo "2. Where demo alerts are currently being sent\n";
        echo "3. Should be deleted or updated to point to your endpoint\n";

        echo "\n💡 SUGGESTION:\n";
        echo "Ask RapidSOS if you should:\n";
        echo "- Delete this subscription (ID: $id)\n";
        echo "- Update it to point to your endpoint\n";
        echo "- Leave it alone (maybe it's needed for portal functionality)\n";
    } elseif (strpos($url, 'my.berkeleycountysc.gov') !== false) {
        echo "✅ This points to YOUR server - GOOD!\n";
    }

    echo "\n" . str_repeat("-", 50) . "\n\n";
}

echo "\n=================================================\n";
echo "SUMMARY\n";
echo "=================================================\n\n";

$yourSubs = array_filter($subscriptions, function ($sub) {
    return strpos($sub['url'], 'my.berkeleycountysc.gov') !== false;
});

$rapidsosSubs = array_filter($subscriptions, function ($sub) {
    return strpos($sub['url'], 'edx-sandbox.rapidsos.com') !== false;
});

echo "Subscriptions pointing to YOUR server: " . count($yourSubs) . "\n";
echo "Subscriptions pointing to RAPIDSOS server: " . count($rapidsosSubs) . "\n\n";

if (count($rapidsosSubs) > 0) {
    echo "⚠️  POTENTIAL ISSUE FOUND!\n\n";
    echo "There are " . count($rapidsosSubs) . " subscription(s) pointing to RapidSOS's\n";
    echo "own server. Demo alerts might be going THERE instead of to your endpoint.\n\n";

    echo "TEST THIS:\n";
    echo "1. Temporarily delete the RapidSOS subscription(s)\n";
    echo "2. Create a demo alert\n";
    echo "3. See if it reaches your endpoint\n\n";

    echo "To delete subscription #1660551:\n";
    echo "curl -X DELETE \\\n";
    echo "  'https://api-sandbox.rapidsos.com/v1/webhooks/subscriptions/1660551' \\\n";
    echo "  -H 'Authorization: Bearer YOUR_TOKEN'\n";
} else {
    echo "All subscriptions point to your server.\n";
    echo "The issue might be elsewhere in the configuration.\n";
}
