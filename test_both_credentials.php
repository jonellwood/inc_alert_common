<?php

/**
 * Test Both RapidSOS Integration Credentials
 * Based on Outbound Data configuration from UNITE portal
 */

echo "=================================================\n";
echo "RapidSOS Dual Credential Test\n";
echo "=================================================\n\n";

// From the Outbound Data screenshot
$credentials = [
    'rapidsos_portal' => [
        'name' => 'RapidSOS Portal',
        'client_id' => 'ZPJV9fNzSPPYc0u9BDqIXERUqCFOPDFI',
        'client_secret' => '7FIcNxRkq45b1UVf'
    ],
    'alerts_egress' => [
        'name' => 'Alerts-Egress-Pre-Product (Current)',
        'client_id' => 'A5sa18wIxv3P2tb7OAGGmdcrJgf63IOM',
        'client_secret' => 'qa4hIib7s713ihJZ'
    ]
];

foreach ($credentials as $key => $cred) {
    echo "Testing: {$cred['name']}\n";
    echo str_repeat("-", 50) . "\n";
    echo "Client ID: {$cred['client_id']}\n\n";

    // Test OAuth Token
    $tokenUrl = 'https://edx-sandbox.rapidsos.com/oauth/token';
    $postData = [
        'grant_type' => 'client_credentials',
        'client_id' => $cred['client_id'],
        'client_secret' => $cred['client_secret'],
        'scope' => 'alerts'
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $tokenUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($postData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        echo "✅ OAuth Token: SUCCESS\n";
        $tokenData = json_decode($response, true);
        $accessToken = $tokenData['access_token'] ?? null;
        echo "   Token: " . substr($accessToken, 0, 20) . "...\n";

        // Test webhook subscriptions
        echo "\nTesting Webhook Subscriptions API...\n";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api-sandbox.rapidsos.com/v1/webhooks/subscriptions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            echo "✅ Webhook Subscriptions: SUCCESS\n";
            $subscriptions = json_decode($response, true);
            echo "   Found " . count($subscriptions) . " subscription(s)\n";
        } else {
            echo "❌ Webhook Subscriptions: HTTP $httpCode\n";
        }

        // Test Integration Management API
        echo "\nTesting Integration Management API...\n";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://edx-sandbox.rapidsos.com/v1/integrations?limit=10',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            echo "✅ Integration Management: SUCCESS\n";
            $integrations = json_decode($response, true);
            if (isset($integrations['integrations'])) {
                echo "   Found " . count($integrations['integrations']) . " integration(s)\n";
                foreach ($integrations['integrations'] as $integration) {
                    echo "   - Webhook ID: " . ($integration['webhookId'] ?? 'N/A') . "\n";
                }
            }
        } elseif ($httpCode === 401) {
            echo "❌ Integration Management: UNAUTHORIZED\n";
            echo "   This credential doesn't have admin access\n";
        } else {
            echo "⚠️  Integration Management: HTTP $httpCode\n";
        }
    } else {
        echo "❌ OAuth Token: FAILED (HTTP $httpCode)\n";
        echo "   Response: $response\n";
    }

    echo "\n" . str_repeat("=", 50) . "\n\n";
}

echo "RECOMMENDATION:\n";
echo "---------------\n";
echo "1. If 'RapidSOS Portal' credentials show integrations,\n";
echo "   use those for WebSocket authentication\n\n";
echo "2. If demo alerts aren't flowing, ask RapidSOS:\n";
echo "   'Which integration receives demo alerts from the portal?'\n\n";
echo "3. Update config/rapidsos_config.php with the correct credentials\n";
echo "   based on which one has access to the integration you need\n";
