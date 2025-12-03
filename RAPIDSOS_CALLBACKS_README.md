# RapidSOS Alert Callbacks Implementation

## Overview

This implementation sends status updates and dispositions back to RapidSOS after processing emergency alerts, creating a bidirectional communication flow.

## How It Works

### Automatic Callback Flow

```
1. RapidSOS Alert webhook received → webhooks/rapidsos_webhook.php
2. Data transformed and sent to → api/writeToDB.php
3. Database entry created
4. CAD call created via Southern Software API
5. CFS number received
6. Callback sent to RapidSOS:
   - Status: ACCEPTED
   - Disposition: DISPATCHED
   - Note: "Alert accepted by Berkeley County 911 - CFS Number: 2025110015"
```

### RapidSOS Alert States

**Statuses** (PSAP can set):

- `ACCEPTED` - PSAP has received and accepted the alert
- `DECLINED` - PSAP cannot handle this alert

**Dispositions** (PSAP can set):

- `DISPATCHED` - Units dispatched to location
- `ENROUTE` - Units en route to location
- `ON_SCENE` - Units arrived at location
- `CLEARED_NO_REPORT` - Cleared without report
- `CLEARED_WITH_REPORT` - Cleared with report filed
- `CLOSED` - Incident closed
- `CANCELED` - Alert canceled
- `PREEMPTED` - Alert preempted by another agency

## API Details

**Endpoint:** `PATCH https://edx-sandbox.rapidsos.com/v1/alerts/{alert_id}`

**Authentication:** OAuth 2.0 Bearer token

**Request Payload:**

```json
{
  "status": "ACCEPTED",
  "disposition": "DISPATCHED"
}
```

**Response:**

```json
{
  "status": {
    "name": "ACCEPTED",
    "display_name": "Accepted"
  },
  "disposition": {
    "name": "DISPATCHED",
    "display_name": "Dispatched"
  },
  "last_updated_time": 1700000000000
}
```

## Files Created/Modified

### New Files

1. **`lib/rapidsos_callbacks.php`**
   - Class: `RapidSOSCallbacks`
   - Methods:
     - `acceptAlert($alertId, $cfsNumber)` - Mark alert as accepted
     - `setDisposition($alertId, $disposition, $cfsNumber)` - Update disposition
     - `declineAlert($alertId, $reason)` - Decline an alert
     - `acceptAndDispatch($alertId, $cfsNumber)` - Combined accept + dispatch (default)
   - Logging: `logs/rapidsos_callbacks.log`

### Modified Files

1. **`api/writeToDB.php`**
   - Added `require_once` for callbacks library
   - Added `sendRapidSOSCallback()` function
   - Integrated callback after successful CAD posting
   - Validates alert ID and source system before sending

## Usage Examples

### Automatic (Current Implementation)

Callbacks are sent automatically after successful CAD entry creation:

```php
// In writeToDB.php after CAD success:
sendRapidSOSCallback($record, $cfsNumber);
```

### Manual Usage

You can also use the callbacks library directly:

```php
require_once __DIR__ . '/lib/rapidsos_callbacks.php';

$callbacks = new RapidSOSCallbacks();

// Accept an alert
$result = $callbacks->acceptAlert('alert-xxx-xxx-xxx', 'CFS-2025110015');

// Set disposition
$result = $callbacks->setDisposition('alert-xxx-xxx-xxx', 'ENROUTE', 'CFS-2025110015');

// Accept and dispatch (combined)
$result = $callbacks->acceptAndDispatch('alert-xxx-xxx-xxx', 'CFS-2025110015');

// Decline an alert
$result = $callbacks->declineAlert('alert-xxx-xxx-xxx', 'Outside jurisdiction');
```

### Response Format

```php
[
    'success' => true,
    'http_code' => 200,
    'response' => [
        'status' => ['name' => 'ACCEPTED', 'display_name' => 'Accepted'],
        'disposition' => ['name' => 'DISPATCHED', 'display_name' => 'Dispatched'],
        'last_updated_time' => 1700000000000
    ],
    'error' => null
]
```

## Testing

### 1. Test the Callback Class

```bash
# SSH to server
cd /var/www/myberkeley/redfive
php test/test_rapidsos_callbacks.php
```

### 2. Monitor Callback Logs

```bash
# Real-time monitoring
tail -f /var/www/myberkeley/redfive/logs/rapidsos_callbacks.log

# Or use log viewer
https://my.berkeleycountysc.gov/redfive/view/log_viewer.php?file=rapidsos_callbacks.log
```

### 3. End-to-End Test

1. Create demo alert in RapidSOS sandbox
2. Monitor webhook receipt:

   ```bash
   tail -f /var/www/myberkeley/redfive/logs/webhook_debug.log
   ```

3. Check CAD entry created:

   ```bash
   tail -f /var/www/myberkeley/redfive/api/cad_debug.log
   ```

4. Check callback sent:

   ```bash
   tail -f /var/www/myberkeley/redfive/logs/rapidsos_callbacks.log
   ```

5. Verify in RapidSOS sandbox portal:
   - Alert status should change to "ACCEPTED"
   - Disposition should show "DISPATCHED"
   - CFS number should appear in notes/history

## Error Handling

### Callback Failures Don't Block CAD Creation

The callback is sent **after** successful CAD creation, so even if the callback fails:

- ✅ Database entry is created
- ✅ CAD entry is created
- ✅ CFS number is returned
- ❌ RapidSOS won't be notified (logged for retry)

### Retry Strategy (Future Enhancement)

Currently callbacks are attempted once. Future improvements could include:

- Retry queue for failed callbacks
- Exponential backoff
- Manual retry via admin interface

## Logging

All callback activity is logged to `logs/rapidsos_callbacks.log`:

```json
{
  "timestamp": "2025-11-17 10:30:45",
  "alert_id": "alert-f1326dff-0fd4-4fe8-9924-69a65bd31488",
  "action": "Alert accepted by Berkeley County 911 - CFS Number: 2025110015",
  "payload": {
    "status": "ACCEPTED"
  },
  "http_code": 200,
  "response": "{...}",
  "curl_error": null
}
---
```

## Production Considerations

### 1. Move to Production Environment

When moving from sandbox to production:

```php
// In config/rapidsos_config.php
'alert_management_base_url' => 'https://edx.rapidsos.com' // Remove "-sandbox"
```

### 2. Monitor Callback Success Rate

Create alerts for failed callbacks:

```sql
-- Check for failed callbacks in last 24 hours
SELECT COUNT(*) FROM IncomingAlertData 
WHERE dtCadPostedDateTime > DATEADD(hour, -24, GETUTCDATE())
AND sSourceSystem = 'RapidSOS'
-- Add check for callback failures
```

### 3. Rate Limiting

RapidSOS API has rate limits. Current implementation includes:

- 0.5 second delay between accept and dispatch calls
- Single retry only (no retry loop)
- Error logging for monitoring

## Permissions

The integration is configured as a **PSAP integration**, which allows:

- ✅ Set status to `ACCEPTED` or `DECLINED`
- ✅ Set dispositions (DISPATCHED, ENROUTE, ON_SCENE, etc.)
- ✅ Set decline_reason when declining
- ❌ Cannot set Central Station statuses (DISPATCH_REQUESTED, IGNORED, CANCELED)

## Next Steps

1. **Deploy callback implementation** to production server
2. **Test with live RapidSOS demo alerts**
3. **Monitor callback logs** for success/failure patterns
4. **Implement retry queue** for failed callbacks (future)
5. **Add admin interface** to manually update dispositions (future)
6. **Integrate with CAD updates** - Send disposition updates when units go enroute/on-scene (future)

## Related Documentation

- RapidSOS Alert Management API v1.1.0: `Alert_Management_API_v_1_1_0.json`
- Webhook implementation: `WEBHOOK_DATA_FIX.md`
- Status update handling: `STATUS_UPDATE_IMPLEMENTATION.md`
- Project overview: `.github/copilot-instructions.md`
