#!/bin/bash
# Test webhook endpoint accessibility

echo "Testing webhook endpoint..."
curl -X POST https://my.berkeleycountysc.gov/redfive/webhooks/rapidsos_webhook.php \
  -H "Content-Type: application/json" \
  -d '{"test": "connection"}' \
  -v

echo ""
echo "If you see a response, the endpoint is accessible!"
echo "Check the logs/webhook_debug.log file for the test entry."