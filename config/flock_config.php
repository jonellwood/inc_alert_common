<?php
// Flock Safety LPR Alerts API Configuration
return [
    // OAuth credentials (client_credentials grant)
    'client_id' => 'CfDcZ19oi2zyujdBmSTr1f78rE8PcpaU',
    'client_secret' => 'ml7YQ41K--MLHJn8SwEDVdUbF9Gga9NLKlf4BUvww2LlJnGYJuVS5YgMQYHMCX1L',

    // Environment: 'production' or 'sandbox'
    'environment' => 'production',

    // API base URLs
    'base_urls' => [
        'production' => 'https://api.flocksafety.com',
        'sandbox' => 'https://dev-api.flocksafety.com',
    ],

    // OAuth audience per environment
    'audiences' => [
        'production' => 'com.flocksafety.integrations',
        'sandbox' => 'com.flocksafety.integrations.dev',
    ],

    // Webhook endpoint where Flock will POST LPR alerts
    'webhook_endpoint' => 'https://redfive.berkeleycountysc.gov/webhooks/flock_webhook.php',

    // Target endpoint for processing alerts (our writeToDB.php)
    'target_endpoint' => 'https://redfive.berkeleycountysc.gov/api/writeToDB.php',

    // Subscription name shown in Flock's system
    'subscription_name' => 'Berkeley County CAD - LPR Alerts',

    // API key for Flock to authenticate webhook deliveries to us
    // Flock will send this in the X-API-Key header on each webhook POST
    'webhook_api_key' => 'BCSOREDFIVE-FLOCK-LPR-7f3a9e2d1c4b8056',

    // Custom hotlist audience filter: 'organization' or 'any'
    'custom_hotlist_audience_filter' => 'organization',

    // Include alerts from cameras shared via First Responder Jurisdiction
    'enable_frj_alerts' => true,

    // CAD call type for all Flock BOLO alerts
    'cad_call_type' => 'FLOCK BOLO',
];
