<?php

/**
 * RapidSOS Integration Diagnostic Tool
 * 
 * This script tests different credential scenarios to help identify
 * which credentials should be used for which API endpoints.
 */

require_once __DIR__ . '/config/rapidsos_config.php';
require_once __DIR__ . '/lib/rapidsos_auth.php';

echo "=================================================\n";
echo "RapidSOS Integration Diagnostic Tool\n";
echo "=================================================\n\n";

$config = require __DIR__ . '/config/rapidsos_config.php';
$auth = new RapidSOSAuth($config);

// Test 1: Get OAuth Token
echo "TEST 1: OAuth Token Generation\n";
echo "--------------------------------\n";
echo "Using credentials:\n";
echo "  Client ID: " . $config['client_id'] . "\n";
echo "  Environment: " . $config['environment'] . "\n\n";

$token = $auth->getAccessToken();
if ($token) {
    echo "✅ SUCCESS: Obtained access token\n";
    echo "   Token (first 20 chars): " . substr($token, 0, 20) . "...\n";

    $tokenInfo = $auth->getTokenInfo();
    if ($tokenInfo) {
        echo "   Scope: " . ($tokenInfo['scope'] ?? 'Not specified') . "\n";
        echo "   Expires in: " . $tokenInfo['expires_in_seconds'] . " seconds\n";
    }
} else {
    echo "❌ FAILED: Could not obtain access token\n";
    echo "   Check your client_id and client_secret\n";
}
echo "\n";

// Test 2: Integration Management API
echo "TEST 2: Integration Management API\n";
echo "-----------------------------------\n";
echo "Endpoint: https://edx-sandbox.rapidsos.com/v1/integrations\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://edx-sandbox.rapidsos.com/v1/integrations?limit=10',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "✅ SUCCESS: Can list integrations\n";
    $data = json_decode($response, true);
    if (isset($data['integrations'])) {
        echo "   Found " . count($data['integrations']) . " integration(s)\n";
        foreach ($data['integrations'] as $integration) {
            echo "   - Webhook ID: " . ($integration['webhookId'] ?? 'N/A') . "\n";
        }
    }
} elseif ($httpCode === 401) {
    echo "❌ UNAUTHORIZED: Current credentials don't have Integration Management access\n";
    echo "   You need ADMIN/PSAP credentials for this endpoint\n";
} else {
    echo "⚠️  HTTP $httpCode: " . substr($response, 0, 200) . "\n";
}
echo "\n";

// Test 3: Webhook Subscription API
echo "TEST 3: Webhook Subscription API\n";
echo "---------------------------------\n";
echo "Endpoint: https://api-sandbox.rapidsos.com/v1/webhooks/subscriptions\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api-sandbox.rapidsos.com/v1/webhooks/subscriptions',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "✅ SUCCESS: Can access webhook subscriptions\n";
    $data = json_decode($response, true);
    echo "   Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
} elseif ($httpCode === 401) {
    echo "❌ UNAUTHORIZED: Current credentials don't have Webhook Subscription access\n";
} elseif ($httpCode === 404) {
    echo "⚠️  NOT FOUND: Endpoint doesn't exist or not available\n";
} else {
    echo "⚠️  HTTP $httpCode: " . substr($response, 0, 200) . "\n";
}
echo "\n";

// Test 4: WebSocket Token Validation
echo "TEST 4: WebSocket Connection Readiness\n";
echo "---------------------------------------\n";
echo "WebSocket URL: " . $config['websocket_urls'][$config['environment']] . "\n\n";

if ($token) {
    echo "✅ Have access token for WebSocket authentication\n";
    echo "   Can attempt WebSocket connection with current credentials\n";
} else {
    echo "❌ No access token - cannot connect to WebSocket\n";
}
echo "\n";

// Summary
echo "=================================================\n";
echo "SUMMARY & RECOMMENDATIONS\n";
echo "=================================================\n\n";

echo "Based on the RapidSOS Postman collections, here's what you need:\n\n";

echo "1. ADMIN CREDENTIALS (for Integration Management)\n";
echo "   - Purpose: Create/manage webhook integrations\n";
echo "   - Endpoint: /v1/integrations\n";
echo "   - Status: ";
if ($httpCode === 200) {
    echo "✅ You have these!\n";
} else {
    echo "❌ You need these from RapidSOS\n";
}
echo "\n";

echo "2. RSOS/ALERTS CREDENTIALS (what you currently have)\n";
echo "   - Client ID: " . $config['client_id'] . "\n";
echo "   - Purpose: Used IN the integration creation payload\n";
echo "   - Status: ✅ You have these\n";
echo "\n";

echo "3. EDX CREDENTIALS (generated from integration)\n";
echo "   - Purpose: WebSocket/webhook authentication\n";
echo "   - How to get: Created when you make a webhook integration\n";
echo "   - Status: ❓ Need to create or retrieve integration\n";
echo "\n";

echo "NEXT STEPS:\n";
echo "-----------\n";
echo "1. Contact RapidSOS and ask for:\n";
echo "   - Admin/PSAP credentials for Integration Management API\n";
echo "   - Your PSAP/Agency ID\n";
echo "   - OR check if integration already exists and get its EDX credentials\n";
echo "\n";
echo "2. Once you have admin creds, create integration:\n";
echo "   POST /v1/integrations with your RSOS credentials\n";
echo "\n";
echo "3. Use the returned EDX credentials for:\n";
echo "   - WebSocket connections\n";
echo "   - Webhook authentication\n";
echo "\n";

echo "For more details, see: RAPIDSOS_INTEGRATION_SETUP.md\n";
echo "=================================================\n";
