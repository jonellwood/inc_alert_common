<?php
// RapidSOS Configuration File
return [
    // Use the Alerts-Egress-Pre-Product credentials for OAuth
    'client_id' => 'A5sa18wIxv3P2tb7OAGGmdcrJgf63IOM',
    'client_secret' => 'qa4hIib7s713ihJZ',

    // Environment settings
    'environment' => 'sandbox', // Currently in sandbox/test environment
    'webhook_secret' => 'YOUR_WEBHOOK_SECRET_HERE', // TODO: Get this from RapidSOS

    // API Endpoints - Different base URLs for different APIs!
    'base_urls' => [
        'sandbox' => 'https://edx-sandbox.rapidsos.com',
        'production' => 'https://edx.rapidsos.com'
    ],

    // Alert Management API (for callbacks and status updates)
    'alert_management_base_url' => 'https://edx-sandbox.rapidsos.com',

    // Webhook Subscription API uses different base URL!
    'webhook_base_urls' => [
        'sandbox' => 'https://api-sandbox.rapidsos.com',
        'production' => 'https://api.rapidsos.com'
    ],

    // WebSocket URLs
    'websocket_urls' => [
        'sandbox' => 'wss://ws.edx-sandbox.rapidsos.com/v1',
        'production' => 'wss://ws.rapidsos.com/v1'
    ],

    // Your webhook endpoint where RapidSOS will send data
    'webhook_endpoint' => 'https://redfive.berkeleycountysc.gov/webhooks/rapidsos_webhook.php',

    // Target endpoint for processing alerts (your existing API)
    'target_endpoint' => 'https://redfive.berkeleycountysc.gov/api/writeToDB.php',

    // Webhook events to subscribe to (official WebSocket Events API v1.1.1)
    'default_events' => [
        'alert.new',                    // New alerts (replaces alert.created)
        'alert.status_update',          // Status changes
        'alert.location_update',        // Location updates
        'alert.disposition_update',     // Disposition changes
        'alert.chat',                   // Chat messages
        'alert.milestone',              // Milestone events
        // 'alert.multi_trip_signal'    // Multiple signal events
    ]
];
?>
];