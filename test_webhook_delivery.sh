#!/bin/bash
# Test webhook and monitor for delivery

if [ -z "$1" ]; then
    echo "Usage: $0 <subscription_id>"
    echo ""
    echo "Get subscription ID from: php test_subscription_structure.php"
    exit 1
fi

SUBSCRIPTION_ID=$1

echo "=== Testing RapidSOS Webhook Delivery ==="
echo ""
echo "Testing subscription ID: $SUBSCRIPTION_ID"
echo ""
echo "Step 1: Triggering test webhook from RapidSOS..."
echo ""

php -r "
require_once 'lib/rapidsos_auth.php';
\$config = require 'config/rapidsos_config.php';
\$auth = new RapidSOSAuth(\$config);
\$token = \$auth->getAccessToken();

\$url = 'https://api-sandbox.rapidsos.com/v1/webhooks/test';

\$ch = curl_init(\$url);
curl_setopt_array(\$ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . \$token,
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode(['subscription_id' => $SUBSCRIPTION_ID])
]);

\$response = curl_exec(\$ch);
\$httpCode = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);
curl_close(\$ch);

echo \"RapidSOS Response: \$response\n\";
"

echo ""
echo "Step 2: Waiting 5 seconds for webhook delivery..."
sleep 5

echo ""
echo "Step 3: Checking server logs (last 30 seconds of entries)..."
echo ""
echo "Run this on your server:"
echo "  tail -50 /var/www/acotocad/redfive/logs/raw_webhook_catch_all.log | grep -A 50 '$(date -u +"%Y-%m-%d %H:%M")'"
echo ""
echo "Or just check the last entry timestamp to see if anything new arrived:"
echo "  tail -5 /var/www/acotocad/redfive/logs/raw_webhook_catch_all.log | grep timestamp"
