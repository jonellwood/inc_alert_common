# Status Update Implementation Summary

## Problem Identified

When RapidSOS sends an alert, it triggers TWO webhook events:

1. `alert.new` - Initial alert with full data → Creates CAD entry (correct)
2. `alert.status_update` - Status change (e.g., "Accept") → Was creating duplicate CAD entry (incorrect)

**Example from logs:**

- Alert ID: `alert-f1326dff-0fd4-4fe8-9924-69a65bd31488`
- First entry (54b8c4d6...): alert.new → CFS 2025110013 ✅
- Second entry (55b8c4d6...): alert.status_update → CFS 2025110014 ❌ (duplicate)

## Solution Implemented

### 1. Status Update Detection

Added logic to detect `alert.status_update` events:

```php
// Determine if this is a status update
$eventType = $payload['event_type'] ?? $payload['event'] ?? 'unknown';
$isStatusUpdate = ($eventType === 'alert.status_update');
```

### 2. Updated Function Signature

Modified `forwardAlertToWriteToDB()` to accept status update flag:

```php
function forwardAlertToWriteToDB($alertData, $eventType, $isStatusUpdate = false)
```

### 3. Skip CAD Creation for Updates

Added early return for status updates to prevent duplicate CAD entries:

```php
if ($isStatusUpdate) {
    logWebhookActivity('status_update_received', [
        'alert_id' => $alertData['alert_id'] ?? $alertData['body']['alert_id'] ?? 'unknown',
        'status' => $alertData['body']['status'] ?? 'unknown'
    ]);
    
    // TODO: Update existing database record with new status
    // For now, just log and skip CAD creation
    return [
        'success' => true,
        'action' => 'status_update_logged',
        'message' => 'Status update received but not yet implemented'
    ];
}
```

### 4. Emergency Type Mapping

Created `mapEmergencyTypeToCallType()` function with Berkeley County mappings:

```php
function mapEmergencyTypeToCallType($rapidSOSEmergencyType)
{
    $mapping = [
        'FIRE' => '51 ALARMS - FIRE',
        'MEDICAL' => '32 UNKNOWN PROBLEM',
        'BURGLARY' => '104 ALARMS - LAW',
        'PANIC' => '104 ALARMS - LAW'
    ];
    
    $type = strtoupper(trim($rapidSOSEmergencyType));
    return $mapping[$type] ?? '32 UNKNOWN PROBLEM'; // Default to MEDICAL/UNKNOWN
}
```

### 5. Integrated Emergency Type Mapping

Updated `transformWebhookAlert()` to use the mapping:

```php
// Map to Southern Software CallTypeAlias
$transformed['emergency']['call_type_alias'] = mapEmergencyTypeToCallType($rapidSOSType);
```

## Current Status

✅ **Completed:**

- Status update detection implemented
- Duplicate CAD creation prevented
- Emergency type mapping function created
- CallTypeAlias field added to transformed data

⏳ **TODO - Next Steps:**

1. **Implement database update logic for status changes:**
   - Query database by `rapidsos_alert_id`
   - Update `status` and `last_updated_time` fields
   - Log update activity

2. **Research RapidSOS Alert Callbacks API:**
   - Send acknowledgment back to RapidSOS after CFS created
   - Include CFS number in callback
   - Prevent infinite callback loops

3. **Testing:**
   - Test with each emergency type (FIRE, MEDICAL, BURGLARY, PANIC)
   - Verify single database entry per alert
   - Verify status updates modify existing record
   - Test full round-trip: RapidSOS → CAD → RapidSOS callback

## Expected Behavior (After Full Implementation)

### Alert Flow

1. Emergency alert created in RapidSOS
2. `alert.new` webhook received → Create database entry + CAD call → Return CFS number
3. Send acknowledgment back to RapidSOS with CFS number
4. User accepts alert in RapidSOS interface
5. `alert.status_update` webhook received → Update database record (no new CAD entry)

### Database Schema Needed

- `rapidsos_alert_id` (unique identifier for correlation)
- `status` (current alert status)
- `last_updated_time` (timestamp of last update)
- `cfs_number` (Southern Software CFS number)

## Testing Commands

### Monitor webhook logs

```bash
tail -f /var/www/myberkeley/redfive/logs/webhook_debug.log
```

### Check database entries

```sql
SELECT rapidsos_alert_id, status, cfs_number, created_time, last_updated_time 
FROM alerts 
WHERE rapidsos_alert_id LIKE 'alert-%'
ORDER BY created_time DESC;
```

### Test with RapidSOS sandbox

1. Create demo alert in RapidSOS portal
2. Check logs for `alert.new` event
3. Verify CAD entry created
4. Click "Accept" in RapidSOS interface
5. Check logs for `alert.status_update` event
6. Verify NO new CAD entry created
7. Verify database record updated with new status

## Files Modified

- `/webhooks/rapidsos_webhook.php`:
  - Added `mapEmergencyTypeToCallType()` function
  - Updated alert processing loop to detect status updates
  - Modified `forwardAlertToWriteToDB()` signature and logic
  - Integrated emergency type mapping into transformation

## Related Documentation

- `ref/FIELD_MAPPING.md` - Field mapping RapidSOS → Southern Software
- `.github/copilot-instructions.md` - Complete project context
- `WEBHOOK_README.md` - Webhook implementation overview
