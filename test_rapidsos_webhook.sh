#!/bin/bash
# Test RapidSOS webhook delivery - simple version

SUBSCRIPTION_ID=${1:-1682247}

echo "Testing RapidSOS webhook delivery for subscription $SUBSCRIPTION_ID..."
echo ""

cd /var/www/myberkeley/redfive 2>/dev/null || cd .

TOKEN=$(php -r '$config = require "config/rapidsos_config.php"; require "lib/rapidsos_auth.php"; $auth = new RapidSOSAuth($config); echo $auth->getAccessToken();')

echo "Sending test request..."
curl -X POST https://api-sandbox.rapidsos.com/v1/webhooks/test \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"subscription_id\": $SUBSCRIPTION_ID}"

echo ""
echo ""
echo "Now check your logs:"
echo "  tail -20 /var/www/myberkeley/redfive/logs/webhook_debug.log"
