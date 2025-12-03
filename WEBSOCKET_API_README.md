# RapidSOS WebSocket Events API v1.1.1 Integration

This document describes the implementation of the official RapidSOS WebSocket Events API v1.1.1 for emergency alert processing.

## Overview

The system now supports the official RapidSOS WebSocket Events API v1.1.1 format alongside the existing legacy formats. This ensures compatibility with both current and future RapidSOS integrations.

## Key Components

### 1. RapidSOSWebSocketMapper (`lib/rapidsos_websocket_mapper.php`)

This is the primary mapper for handling official WebSocket Events API payloads.

**Supported Event Types:**

- `alert.new` - New emergency alerts (primary event)
- `alert.status_update` - Alert status changes
- `alert.location_update` - Location updates
- `alert.disposition_update` - Disposition changes
- `alert.chat` - Chat messages between responders
- `alert.milestone` - Milestone events (dispatch, response, etc.)
- `alert.multi_trip_signal` - Multiple signal events

**Key Features:**

- Official payload structure handling (`event`/`body`/`timestamp`)
- Proper geodetic and civic location parsing
- Emergency type constant validation
- Status type validation
- Comprehensive logging for debugging

### 2. Updated Webhook Endpoint (`webhooks/rapidsos_webhook.php`)

The webhook endpoint now automatically detects payload format:

```php
if (isset($payload['event']) && isset($payload['body'])) {
    // Official WebSocket Events API v1.1.1 format
    $extractedData = $websocketMapper->extractWebSocketEvent($payload);
} else {
    // Legacy format
    $extractedData = $legacyMapper->extractAlertData($payload);
}
```

### 3. Configuration (`config/rapidsos_config.php`)

Updated with official event types from the WebSocket Events API specification:

```php
'default_events' => [
    'alert.new',                    // New alerts (replaces alert.created)
    'alert.status_update',          // Status changes
    'alert.location_update',        // Location updates
    'alert.disposition_update',     // Disposition changes
    'alert.chat',                   // Chat messages
    'alert.milestone',              // Milestone events
]
```

## Payload Structure

### Official WebSocket Events API Format

```json
{
  "event": "alert.new",
  "timestamp": 1703768400000,
  "body": {
    "alert_id": "12345678-1234-5678-9012-123456789012",
    "source_id": "rapidsos-mobile-app",
    "incident_time": 1703768400000,
    "created_time": 1703768410000,
    "last_updated_time": 1703768420000,
    "location": {
      "provided_location": "BOTH",
      "geodetic": {
        "latitude": 32.7767,
        "longitude": -79.9311,
        "uncertainty_radius": 25
      },
      "civic": {
        "name": "Charleston County Public Safety Complex",
        "street_1": "4045 Bridge View Dr",
        "city": "Charleston",
        "state": "SC",
        "country": "US",
        "zip_code": "29405"
      }
    },
    "emergency_type": {
      "name": "MEDICAL",
      "display_name": "Medical Emergency"
    },
    "status": {
      "name": "NEW",
      "display_name": "New Alert"
    }
  }
}
```

## Emergency Types (Official Constants)

The system now includes official emergency type constants:

- `BURGLARY` / `TEST_BURGLARY`
- `HOLDUP` / `TEST_HOLDUP`
- `SILENT_ALARM` / `TEST_SILENT_ALARM`
- `CRASH` / `TEST_CRASH`
- `MEDICAL` / `TEST_MEDICAL`
- `FIRE` / `TEST_FIRE`
- `CO` / `TEST_CO`
- `OTHER` / `TEST_OTHER`
- `ACTIVE_ASSAILANT` / `TEST_ACTIVE_ASSAILANT`
- `MOBILE_PANIC` / `TEST_MOBILE_PANIC`
- `TRAIN_DERAILMENT` / `TEST_TRAIN_DERAILMENT`

## Status Types (Official Constants)

- `NEW` - New alert
- `IGNORED` - Alert ignored
- `DISPATCH_REQUESTED` - Dispatch requested
- `ACCEPTED` - Alert accepted
- `DECLINED` - Alert declined
- `TIMEOUT` - Alert timed out
- `CANCELED` - Alert canceled

## Location Data Structure

The official API provides location data in two formats:

### Geodetic (Coordinates)

```json
{
  "latitude": 32.7767,
  "longitude": -79.9311,
  "uncertainty_radius": 25
}
```

### Civic (Address)

```json
{
  "name": "Charleston County Public Safety Complex",
  "street_1": "4045 Bridge View Dr",
  "street_2": "",
  "city": "Charleston",
  "state": "SC",
  "country": "US",
  "zip_code": "29405"
}
```

## Event Processing Flow

1. **Webhook Receives Payload** - Signature verified using HMAC-SHA256
2. **Format Detection** - Checks for `event`/`body` structure
3. **Event Processing** - Uses appropriate mapper based on format
4. **Data Extraction** - Extracts location, emergency type, status, etc.
5. **Data Transformation** - Converts to format expected by `writeToDB.php`
6. **Database Storage** - Forwards to existing database processing
7. **CAD Integration** - Posts to Southern Software CIM API

## Testing

### Unit Tests

```bash
php test/websocket_api_test.php
```

Tests all event types and data extraction.

### Webhook Simulation

```bash
php test/webhook_simulation.php
```

Simulates complete webhook processing flow.

## Migration Strategy

The system maintains backward compatibility:

1. **Automatic Detection** - No configuration changes needed
2. **Dual Support** - Both legacy and official formats supported
3. **Gradual Migration** - Can transition event subscriptions over time
4. **Logging** - Comprehensive logging shows which format is being used

## Deployment Notes

1. **Update Webhook Subscriptions** - Change event types to official format:
   - `alert.created` → `alert.new`
   - Add additional event types as needed

2. **Monitor Logs** - Check `logs/webhook_debug.log` for format detection

3. **Test Environment** - Use sandbox environment for testing official events

## Troubleshooting

### Common Issues

1. **Format Not Detected** - Check payload structure for `event`/`body` fields
2. **Event Type Unsupported** - Verify event type is in supported list
3. **Location Missing** - Check `provided_location` field value
4. **Timestamp Errors** - API uses milliseconds, system converts automatically

### Debug Logging

The system logs all payload processing:

```bash
tail -f logs/webhook_debug.log
```

Look for entries like:

- `format_detected`
- `data_mapped`
- `alert_processed`

## Benefits of Official API

1. **Standardized Structure** - Consistent payload format
2. **Rich Metadata** - More detailed emergency information
3. **Event Granularity** - Separate events for different update types
4. **Better Location Data** - Structured geodetic and civic information
5. **Status Tracking** - Official status values and transitions
6. **Future Compatibility** - Aligned with RapidSOS roadmap

## Next Steps

1. **Update Subscriptions** - Migrate to official event types
2. **Enhance UI** - Update dashboard to show additional data fields
3. **Status Processing** - Handle status update events for real-time tracking
4. **Chat Integration** - Process chat events for responder communication
5. **Milestone Tracking** - Use milestone events for response analytics
