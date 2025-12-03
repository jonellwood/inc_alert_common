# Berkeley County Emergency Services - WebSocket Integration Status

## Current Setup Summary

### WebSocket Client Configuration

- **Environment**: Sandbox (`edx-sandbox.rapidsos.com`)
- **WebSocket URL**: `wss://ws.edx-sandbox.rapidsos.com/v1`
- **OAuth Token URL**: `https://edx-sandbox.rapidsos.com/oauth/token`
- **Client Library**: textalk/websocket (PHP)
- **Authentication**: OAuth 2.0 Client Credentials with scope "alerts"

### Event Subscriptions

Currently subscribed to these event types:

- `alert.new`
- `alert.status_update`
- `alert.location_update`
- `alert.disposition_update`
- `alert.chat`
- `alert.milestone`
- `alert.multi_trip_signal`

### Connection Status

✅ **OAuth Authentication**: Working - successfully obtaining access tokens
✅ **WebSocket Connection**: Working - connecting to `wss://ws.edx-sandbox.rapidsos.com/v1`
✅ **SSL/TLS**: Working - proper certificate validation
❓ **Demo Alerts**: Not receiving alerts created in RapidSOS demo portal

### Current Log Output

```
[2025-01-01 XX:XX:XX] Starting RapidSOS WebSocket client...
[2025-01-01 XX:XX:XX] Access token obtained successfully
[2025-01-01 XX:XX:XX] Connecting to WebSocket: wss://ws.edx-sandbox.rapidsos.com/v1
[2025-01-01 XX:XX:XX] Event types: alert.new, alert.status_update, alert.location_update, alert.disposition_update, alert.chat, alert.milestone, alert.multi_trip_signal
[2025-01-01 XX:XX:XX] WebSocket connection established successfully
[2025-01-01 XX:XX:XX] WebSocket client started successfully - listening for events...
```

### Questions for Investigation

1. **Demo Alert Routing**: Do demo alerts created in the portal automatically route to active WebSocket connections?

2. **Emergency Session Requirement**: Is the `/v2/emergency-data/session` endpoint required for receiving demo alerts?

3. **Environment Consistency**: Are demo portal and WebSocket using the same sandbox environment?

4. **Authentication Scope**: Does our "alerts" scope include demo alert permissions?

5. **Event Type Coverage**: Are demo alerts sent as `alert.new` events?

### Test Capabilities Available

- Real-time WebSocket log monitoring
- OAuth token verification
- Connection status checking
- Manual alert simulation (if endpoints provided)

### Integration Goals

- Receive real-time emergency alerts from RapidSOS
- Process alerts through existing database/CAD system
- Maintain reliable 24/7 emergency services integration
