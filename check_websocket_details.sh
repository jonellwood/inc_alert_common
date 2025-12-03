#!/bin/bash

# Script to identify WebSocket processes and their details
# Run on server to see what each process is actually doing

echo "=== WebSocket Process Details ==="
echo ""

# Find all PHP processes with websocket_client.php
PIDS=$(ps -ef | grep websocket_client.php | grep -v grep | awk '{print $2}')

for PID in $PIDS; do
    echo "--- PID: $PID ---"
    
    # Show full command line
    echo "Command:"
    ps -fp $PID | tail -1
    
    # Show working directory
    echo "Working Directory:"
    pwdx $PID 2>/dev/null || echo "  (unable to determine)"
    
    # Show which files it has open (look for logs)
    echo "Open Files (logs/sockets):"
    lsof -p $PID 2>/dev/null | grep -E "(log|sock|ESTABLISHED)" || echo "  (unable to determine)"
    
    echo ""
done

echo "=== Check Log Files ==="
echo ""
echo "websocket_client.log (last 5 lines):"
tail -5 /var/www/acotocad/redfive/logs/websocket_client.log 2>/dev/null || echo "  File not found or empty"

echo ""
echo "websocket_output.log (last 5 lines):"
tail -5 /var/www/acotocad/redfive/logs/websocket_output.log 2>/dev/null || echo "  File not found or empty"

echo ""
echo "=== Network Connections ==="
for PID in $PIDS; do
    echo "PID $PID connections:"
    lsof -p $PID 2>/dev/null | grep ESTABLISHED || echo "  No established connections"
done
