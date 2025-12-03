<?php

/**
 * Test the "Initialize State" phase from the RapidSOS integration diagram
 * This may be required BEFORE WebSocket subscriptions work
 */

require_once __DIR__ . '/lib/rapidsos_auth.php';

$config = require __DIR__ . '/config/rapidsos_config.php';
$auth = new RapidSOSAuth($config);

echo "=== Testing RapidSOS 'Initialize State' Phase ===\n\n";

// Get OAuth token
echo "1. Authenticating...\n";
$token = $auth->getAccessToken();
if (!$token) {
    die("ERROR: Failed to get access token\n");
}
echo "   ✓ Got access token\n\n";

// Test 1: GET /v1/alerts (Active Alerts)
echo "2. Testing GET /v1/alerts (Active Alerts)...\n";
$alertsUrl = 'https://edx-sandbox.rapidsos.com/v1/alerts';

$ch = curl_init($alertsUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "   HTTP Status: $httpCode\n";
if ($error) {
    echo "   Error: $error\n";
}
if ($httpCode === 200) {
    echo "   ✓ SUCCESS!\n";
    $data = json_decode($response, true);
    echo "   Response:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n";

    if (isset($data['alerts']) && is_array($data['alerts'])) {
        echo "\n   Found " . count($data['alerts']) . " active alert(s)\n";
    }
} else {
    echo "   ✗ FAILED\n";
    echo "   Response: $response\n";
}

echo "\n" . str_repeat('=', 70) . "\n\n";

// Test 2: Check if the diagram suggests we need to poll this endpoint
echo "3. Understanding the flow...\n";
echo "   According to the diagram:\n";
echo "   - Initialize State: GET /v1/alerts (note: 'Polling this API is forbidden')\n";
echo "   - This might mean we call it ONCE to initialize, not repeatedly\n";
echo "   - Then we Subscribe for WebSocket Notifications\n";
echo "   - Then we Dispatch Alerts\n\n";

echo "4. Key Question:\n";
echo "   Does calling GET /v1/alerts somehow 'activate' our account for receiving alerts?\n";
echo "   Is this why demo alerts aren't reaching our WebSocket?\n\n";

echo "=== Test Complete ===\n";
