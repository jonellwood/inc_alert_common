# Running RapidSOS WebSocket Client as a Service

## Quick Answer: Use systemd (Best for Production)

Upload `rapidsos-websocket.service` to your server, then:

```bash
sudo cp rapidsos-websocket.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable rapidsos-websocket
sudo systemctl start rapidsos-websocket
sudo systemctl status rapidsos-websocket
```

---

# RapidSOS WebSocket Client Setup Guide

## Quick Setup for RapidSOS WebSocket Events API

### Option 1: Use Built-in Implementation (Recommended for Testing)

The current WebSocket client includes a demo/simulation mode that works immediately:

```bash
# Test the WebSocket client
php websocket_client.php
```

### Option 2: Full WebSocket Library (Production Ready)

For production use, install a proper WebSocket client library:

```bash
# Install Composer (if not already installed)
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Install WebSocket client library
composer require textalk/websocket-client

# Or alternatively, use ReactPHP
composer require react/socket react/http
```

### Required PHP Extensions

Ensure these PHP extensions are enabled:

- `curl` (for authentication)
- `json` (for payload processing)
- `pcntl` (for signal handling)
- `sockets` (for WebSocket connections)

### Configuration

1. Update your `config/rapidsos_config.php` with proper credentials
2. Ensure your RapidSOS account has WebSocket access enabled
3. Verify firewall allows outbound connections to `ws.edx-sandbox.rapidsos.com:443`

### Testing

1. Visit the WebSocket Manager: `websocket_manager.php`
2. Click "Start WebSocket Client"
3. Monitor logs for connection status
4. Test with RapidSOS demo portal events

### Integration Notes

- WebSocket events use the SAME payload format as webhooks
- All existing data processing code works without changes
- WebSocket events will be stored in the same database tables
- CAD integration continues to work automatically

### Troubleshooting

- Check `logs/websocket_client.log` for connection issues
- Verify OAuth 2.0 token is valid in RapidSOS auth logs
- Ensure WebSocket event types are properly configured
- Test network connectivity to RapidSOS WebSocket servers
