#!/bin/bash
# Fix permissions for all log files and debug files

LOG_DIR="/var/www/myberkeley/redfive"

echo "Fixing permissions for log files..."

# Create and fix permissions for all log files
sudo touch "$LOG_DIR/api/cad_debug.log"
sudo touch "$LOG_DIR/api/webhook_to_cad_payload.log"
sudo touch "$LOG_DIR/api/cad_response_debug.log"
sudo touch "$LOG_DIR/api/debug_record_id.txt"
sudo touch "$LOG_DIR/logs/rapidsos_callbacks.log"

# Set ownership to www-data (Apache user)
sudo chown www-data:www-data "$LOG_DIR/api/cad_debug.log"
sudo chown www-data:www-data "$LOG_DIR/api/webhook_to_cad_payload.log"
sudo chown www-data:www-data "$LOG_DIR/api/cad_response_debug.log"
sudo chown www-data:www-data "$LOG_DIR/api/debug_record_id.txt"
sudo chown www-data:www-data "$LOG_DIR/logs/rapidsos_callbacks.log"

# Set permissions to 666 (readable and writable by all)
sudo chmod 666 "$LOG_DIR/api/cad_debug.log"
sudo chmod 666 "$LOG_DIR/api/webhook_to_cad_payload.log"
sudo chmod 666 "$LOG_DIR/api/cad_response_debug.log"
sudo chmod 666 "$LOG_DIR/api/debug_record_id.txt"
sudo chmod 666 "$LOG_DIR/logs/rapidsos_callbacks.log"

echo "Done! Permissions fixed for all log files."
echo ""
echo "Listing permissions:"
ls -la "$LOG_DIR/api/cad_debug.log"
ls -la "$LOG_DIR/api/webhook_to_cad_payload.log"
ls -la "$LOG_DIR/api/cad_response_debug.log"
ls -la "$LOG_DIR/api/debug_record_id.txt"
ls -la "$LOG_DIR/logs/rapidsos_callbacks.log"
