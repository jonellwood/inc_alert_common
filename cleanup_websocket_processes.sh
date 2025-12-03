#!/bin/bash

# Cleanup script for multiple WebSocket processes
# Run this on the server to kill old processes and start fresh

echo "Current WebSocket processes:"
ps -ef | grep websocket_client.php | grep -v grep

echo ""
echo "Killing all WebSocket processes..."
pkill -f websocket_client.php

echo ""
echo "Waiting 2 seconds..."
sleep 2

echo ""
echo "Verifying all processes are stopped..."
ps -ef | grep websocket_client.php | grep -v grep

echo ""
echo "Removing old PID file..."
rm -f /var/www/myberkeley/redfive/logs/websocket.pid

echo ""
echo "Starting fresh WebSocket client..."
cd /var/www/myberkeley/redfive
nohup php websocket_client.php > logs/websocket_output.log 2>&1 &

echo ""
echo "New PID: $(cat logs/websocket.pid 2>/dev/null || echo 'PID file not created yet, wait a moment')"

echo ""
echo "Tailing log (Ctrl+C to exit)..."
sleep 2
tail -f logs/websocket_client.log
