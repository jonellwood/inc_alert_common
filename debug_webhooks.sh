#!/bin/bash
# Webhook debugging script - run on server

echo "=== RapidSOS Webhook Debugging ==="
echo ""

# Check if subscription exists
echo "1. Current Subscriptions:"
echo "========================="
php test_subscription_structure.php
echo ""

# Check webhook endpoint is accessible
echo "2. Webhook Endpoint Test:"
echo "========================="
curl -I https://my.berkeleycountysc.gov/redfive/webhooks/rapidsos_webhook.php
echo ""

# Check recent webhook logs
echo "3. Recent Webhook Activity (last 20 lines):"
echo "==========================================="
if [ -f logs/webhook_debug.log ]; then
    tail -20 logs/webhook_debug.log
else
    echo "No webhook_debug.log found"
fi
echo ""

# Check auth logs
echo "4. Recent Auth Activity (last 10 lines):"
echo "========================================"
if [ -f logs/rapidsos_auth.log ]; then
    tail -10 logs/rapidsos_auth.log
else
    echo "No rapidsos_auth.log found"
fi
echo ""

# Check subscription logs
echo "5. Recent Subscription Activity (last 10 lines):"
echo "==============================================="
if [ -f logs/rapidsos_subscriptions.log ]; then
    tail -10 logs/rapidsos_subscriptions.log
else
    echo "No rapidsos_subscriptions.log found"
fi
echo ""

# Check file permissions
echo "6. File Permissions:"
echo "==================="
ls -la webhooks/rapidsos_webhook.php 2>/dev/null || echo "rapidsos_webhook.php not found"
ls -la logs/*.log 2>/dev/null || echo "No log files found"
echo ""

# Check Apache error log (if accessible)
echo "7. Recent Apache Errors (last 10 lines):"
echo "========================================"
if [ -f /var/log/apache2/error.log ]; then
    sudo tail -10 /var/log/apache2/error.log | grep -i rapidsos || echo "No RapidSOS errors in Apache log"
elif [ -f /var/log/httpd/error_log ]; then
    sudo tail -10 /var/log/httpd/error_log | grep -i rapidsos || echo "No RapidSOS errors in Apache log"
else
    echo "Apache error log not found or not accessible"
fi
echo ""

echo "=== Key Questions ==="
echo "1. Is your subscription URL correct?"
echo "   Should be: https://my.berkeleycountysc.gov/redfive/webhooks/rapidsos_webhook.php"
echo ""
echo "2. Can RapidSOS reach your server?"
echo "   Check firewall rules for incoming HTTPS traffic"
echo ""
echo "3. Try manual webhook test:"
echo "   curl -X POST https://my.berkeleycountysc.gov/redfive/webhooks/rapidsos_webhook.php \\"
echo "     -H 'Content-Type: application/json' \\"
echo "     -d '{\"test\": \"data\"}'"
