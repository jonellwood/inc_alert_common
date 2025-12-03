#!/bin/bash
# RapidSOS WebSocket Keepalive Monitor
# Checks every minute if WebSocket is running, restarts if needed
# SAFE - No system changes, runs in user space

SCRIPT_DIR="/var/www/acotocad/redfive"
PID_FILE="$SCRIPT_DIR/logs/websocket.pid"
LOG_FILE="$SCRIPT_DIR/logs/websocket_keepalive.log"

# Check if already running
if [ -f "$PID_FILE" ]; then
    PID=$(cat "$PID_FILE")
    if ps -p $PID > /dev/null 2>&1; then
        # Still running, exit quietly
        exit 0
    fi
fi

# Not running, start it
cd "$SCRIPT_DIR"
nohup php websocket_client.php > logs/websocket_output.log 2>&1 &
NEW_PID=$!

# Save PID
echo $NEW_PID > "$PID_FILE"

# Log start
echo "[$(date '+%Y-%m-%d %H:%M:%S')] WebSocket client started with PID $NEW_PID" >> "$LOG_FILE"
