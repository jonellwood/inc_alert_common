<?php

/**
 * Test Integration Creation with Berkeley Agency ID
 * Based on info from RapidSOS UNITE portal
 */

require_once __DIR__ . '/manage/integrations.php';

echo "Berkeley County Integration Creation Test\n";
echo "==========================================\n\n";

// From the RapidSOS UNITE portal screenshot
$agencyId = 'ID_BerkeleyAlerts';
$agencyName = 'Berkeley_Alerts';

echo "Agency Information (from UNITE portal):\n";
echo "  FCC PSAP ID: $agencyId\n";
echo "  Agency Name: $agencyName\n";
echo "  State: South Carolina\n";
echo "  Client ID: A5sa18wIxv3P2tb7OAGGmdcrJgf63IOM\n\n";

echo "IMPORTANT: Your Alerts-Egress credentials work for BOTH:\n";
echo "  - RSOS credentials (in the request payload)\n";
echo "  - Admin credentials (for authentication)\n";
echo "  This means you CAN create integrations!\n\n";

$manager = new RapidSOSIntegrationManager();

// Try to create an integration
echo "Attempting to create webhook integration...\n";
echo "-------------------------------------------\n";

$result = $manager->createIntegration($agencyId);

echo "HTTP Status: " . ($result['http_code'] ?? 'N/A') . "\n";
echo "Success: " . ($result['success'] ? 'YES' : 'NO') . "\n\n";

if ($result['success']) {
    echo "✅ INTEGRATION CREATED SUCCESSFULLY!\n\n";

    if (isset($result['data'])) {
        echo "Integration Details:\n";
        echo json_encode($result['data'], JSON_PRETTY_PRINT) . "\n\n";

        // Save EDX credentials if returned
        if (isset($result['data']['edxClientId']) && isset($result['data']['edxClientSecret'])) {
            echo "🔑 EDX Credentials (USE THESE FOR WEBSOCKET!):\n";
            echo "   Client ID: " . $result['data']['edxClientId'] . "\n";
            echo "   Client Secret: " . $result['data']['edxClientSecret'] . "\n";
            echo "   Webhook ID: " . ($result['data']['webhookId'] ?? 'N/A') . "\n\n";

            echo "⚠️  IMPORTANT: Save these credentials!\n";
            echo "   Update config/rapidsos_config.php with EDX credentials\n";
            echo "   Use these for WebSocket authentication\n";
        }
    }
} else {
    echo "❌ INTEGRATION CREATION FAILED\n\n";

    if (isset($result['error'])) {
        echo "Error: " . $result['error'] . "\n";
    }

    if (isset($result['data'])) {
        echo "Response: " . json_encode($result['data'], JSON_PRETTY_PRINT) . "\n";
    }

    if (isset($result['raw_response'])) {
        echo "\nRaw Response:\n" . $result['raw_response'] . "\n";
    }

    echo "\nPossible reasons:\n";
    echo "1. Current credentials don't have Integration Management permissions\n";
    echo "2. Integration already exists for this agency\n";
    echo "3. Need admin/PSAP credentials instead of RSOS credentials\n\n";

    echo "TRY THIS: Check if integration already exists\n";
    echo "Run: php manage/integrations.php (to list integrations)\n";
}

echo "\n==========================================\n";
echo "Next steps based on the RapidSOS UNITE portal:\n\n";
echo "1. Check 'Outbound Data' menu in UNITE portal\n";
echo "   - May show webhook/integration configuration\n";
echo "   - May have EDX credentials displayed\n\n";
echo "2. Check 'Inbound Data' menu\n";
echo "   - May show data source configuration\n\n";
echo "3. If integration creation failed:\n";
echo "   - Ask RapidSOS if integration already exists\n";
echo "   - Request EDX credentials for existing integration\n";
echo "   - Verify Agency ID: $agencyId\n";
