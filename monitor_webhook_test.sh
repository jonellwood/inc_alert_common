#!/bin/bash
# Monitor webhook logs in real-time while triggering test

echo "Starting webhook monitor..."
echo "This will trigger a test webhook and watch for delivery"
echo ""

# Start tailing log in background
tail -f /var/www/myberkeley/redfive/logs/webhook_debug.log 2>/dev/null | grep -v "Berkeley-County-Webhook-Tester\|curl\|Mozilla" &
TAIL_PID=$!

echo "Monitoring for webhooks from RapidSOS (filtering out test traffic)..."
echo "Press Ctrl+C to stop"
echo ""
sleep 2

echo "Triggering test webhook..."
php test_webhook_api.php 2>&1 | grep "Test 2" -A 3

echo ""
echo "Waiting 30 seconds for webhook delivery..."
echo "(Watch above for incoming requests)"
sleep 30

kill $TAIL_PID 2>/dev/null
echo ""
echo "Monitor stopped. If you didn't see any RapidSOS requests, they cannot reach your server."
