#!/bin/bash
# Monitor for new alerts in real-time
# Run this WHILE creating a demo alert in the RapidSOS portal

echo "=== Starting Real-Time Alert Monitoring ==="
echo "Create a demo alert NOW in the RapidSOS portal..."
echo ""
echo "Monitoring:"
echo "  - Webhook logs (tail -f logs/webhook_debug.log)"
echo "  - Active alerts via API (polling every 5 seconds)"
echo ""
echo "Press Ctrl+C to stop"
echo ""

# Store the last alerts_until timestamp
LAST_TIMESTAMP=""

while true; do
    # Get current active alerts
    RESPONSE=$(php -r "
        require_once 'lib/rapidsos_auth.php';
        \$config = require 'config/rapidsos_config.php';
        \$auth = new RapidSOSAuth(\$config);
        \$token = \$auth->getAccessToken();
        
        \$ch = curl_init('https://edx-sandbox.rapidsos.com/v1/alerts');
        curl_setopt_array(\$ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . \$token,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 10
        ]);
        
        \$response = curl_exec(\$ch);
        curl_close(\$ch);
        echo \$response;
    ")
    
    # Extract alerts count and timestamp
    ALERT_COUNT=$(echo "$RESPONSE" | php -r "
        \$data = json_decode(file_get_contents('php://stdin'), true);
        echo isset(\$data['alerts']) ? count(\$data['alerts']) : 0;
    ")
    
    ALERTS_UNTIL=$(echo "$RESPONSE" | php -r "
        \$data = json_decode(file_get_contents('php://stdin'), true);
        echo isset(\$data['alerts_until']) ? \$data['alerts_until'] : '';
    ")
    
    # Check if timestamp changed (new alert!)
    if [ ! -z "$ALERTS_UNTIL" ] && [ "$ALERTS_UNTIL" != "$LAST_TIMESTAMP" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] 🚨 NEW ALERT DETECTED! Alert count: $ALERT_COUNT"
        echo "$RESPONSE" | php -r "
            \$data = json_decode(file_get_contents('php://stdin'), true);
            if (isset(\$data['alerts']) && is_array(\$data['alerts'])) {
                foreach (\$data['alerts'] as \$alert) {
                    echo '  Alert ID: ' . (\$alert['alert_id'] ?? 'unknown') . \"\n\";
                    echo '  Type: ' . (\$alert['emergency_type']['display_name'] ?? 'unknown') . \"\n\";
                    echo '  Status: ' . (\$alert['status']['display_name'] ?? 'unknown') . \"\n\";
                    echo '  Created: ' . (\$alert['created_time'] ?? 'unknown') . \"\n\";
                    echo \"\n\";
                }
            }
        "
        
        # Check webhook logs
        echo "  Checking webhook logs..."
        WEBHOOK_LOGS=$(tail -n 5 logs/webhook_debug.log 2>/dev/null || echo "No webhook logs")
        if [[ "$WEBHOOK_LOGS" == *"rapidsos"* ]] || [[ "$WEBHOOK_LOGS" == *"RapidSOS"* ]]; then
            echo "  ✓ WEBHOOK RECEIVED!"
        else
            echo "  ✗ No webhook received yet"
        fi
        echo ""
        
        LAST_TIMESTAMP="$ALERTS_UNTIL"
    else
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Monitoring... ($ALERT_COUNT active alerts, no changes)"
    fi
    
    sleep 5
done
