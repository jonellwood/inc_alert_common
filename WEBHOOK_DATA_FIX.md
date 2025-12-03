# Webhook Data Flow Fix - November 17, 2025

## Problem Identified

1. **Empty CAD Entries**: RapidSOS webhooks created database/CAD entries with no data
2. **Log Access Blocked**: Direct access to `.log` files returned 403 Forbidden
3. **Webhook Simulation**: Test created in old environment wasn't actually posting data

## Root Cause

The `transformWebhookAlert()` function was restructuring the RapidSOS payload into a custom format, but `writeToDB.php`'s `extractRapidSOSData()` function expects the original RapidSOS field structure:

- Expected: `alert_id`, `location.geodetic`, `location.civic`, `emergency_type.name`
- Was getting: Custom nested structure that didn't match

## Solution Implemented

### 1. Fixed Data Structure Mismatch

**Changed:** `webhooks/rapidsos_webhook.php` → `transformWebhookAlert()`

Instead of creating a custom structure, now preserve the original RapidSOS structure and add enhancements:

```php
function transformWebhookAlert($alertData, $eventType)
{
    // Extract data from 'body' if present (official webhook format)
    $data = isset($alertData['body']) ? $alertData['body'] : $alertData;
    
    // Start with the original data structure from RapidSOS
    $transformed = $data;
    
    // Add computed fields and enhancements
    $transformed['webhook_event_type'] = $eventType;
    $transformed['alert_id'] = $data['alert_id'] ?? $data['id'] ?? null;
    
    // Add emergency type mapping
    if (isset($data['emergency_type'])) {
        $transformed['call_type_alias'] = mapEmergencyTypeToCallType($rapidSOSType);
    }
    
    // Add timestamp conversions for logging
    $transformed['created_time_formatted'] = date('Y-m-d H:i:s', $data['created_time'] / 1000);
    
    return $transformed; // Returns RapidSOS structure + our enhancements
}
```

### 2. Wrapped Data in Alerts Array

**Changed:** `webhooks/rapidsos_webhook.php` → `forwardAlertToWriteToDB()`

The `writeToDB.php` expects payload with `alerts` array:

```php
// CRITICAL: writeToDB.php expects data wrapped in 'alerts' array
$payload = [
    'alerts' => [$transformedData]
];
```

### 3. Added Comprehensive Logging

**Changed:** `webhooks/rapidsos_webhook.php` → `forwardAlertToWriteToDB()`

Now logs BOTH the outgoing payload and the CAD response:

```php
// Log the payload being sent
$debugLogFile = __DIR__ . '/../api/webhook_to_cad_payload.log';
file_put_contents($debugLogFile, json_encode($debugEntry, JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND);

// ...curl request...

// Log the response
$responseLogFile = __DIR__ . '/../api/cad_response_debug.log';
file_put_contents($responseLogFile, json_encode($responseEntry, JSON_PRETTY_PRINT) . "\n---\n", FILE_APPEND);
```

### 4. Fixed Log File Access

**Created:** `.htaccess` files to allow log access

```apache
# /api/.htaccess
<Files "*.log">
    Require all granted
</Files>

# /logs/.htaccess  
<Files "*.log">
    Require all granted
</Files>
```

### 5. Created Alternative Log Viewer

**Created:** `view/log_viewer.php`

PHP-based log viewer that doesn't require direct `.log` file access:

- Access: `https://my.berkeleycountysc.gov/redfive/view/log_viewer.php`
- Features:
  - View any log file in `/logs/` or `/api/`
  - Filter by search term
  - Auto-parse JSON log entries
  - Color-coded entries (error, warning, success)
  - Real-time file stats

## How writeToDB.php Expects Data

### Expected Structure

```json
{
  "alerts": [
    {
      "alert_id": "alert-xxx",
      "location": {
        "geodetic": {
          "latitude": 32.7767,
          "longitude": -79.9311,
          "uncertainty_radius": 25
        },
        "civic": {
          "name": "123 Main St",
          "street_1": "123 Main St",
          "street_2": "Apt 101",
          "city": "Charleston",
          "state": "SC",
          "zip_code": "29401"
        }
      },
      "emergency_type": {
        "name": "MEDICAL",
        "display_name": "Medical Emergency"
      },
      "service_provider_name": "RapidSOS",
      "description": "Emergency description",
      "status": {
        "name": "NEW"
      },
      "covering_psap": {
        "id": "psap-id",
        "name": "Berkeley County 911"
      },
      "incident_time": 1700000000000,
      "created_time": 1700000000000,
      "last_updated_time": 1700000000000
    }
  ]
}
```

### Fields Extracted by writeToDB.php

From `extractRapidSOSData()`:

- `alert['alert_id']` → `sSourceId`, `sSourceReferenceNumber`
- `alert['location']['geodetic']` → `iLatitude`, `iLongitude`, `sLocationUncertainty`
- `alert['location']['civic']` → `sLocationName`, `sStreetAddress`, `sCity`, `sState`, etc.
- `alert['emergency_type']['name']` → `sEmergencyType`
- `alert['service_provider_name']` → `sServiceProviderName`
- `alert['description']` → `sDescription`
- `alert['status']['name']` → `sStatus`
- `alert['covering_psap']['name']` → `sAgency`

## Testing the Fix

### 1. Check New Log Files

```bash
# SSH to server
tail -f /var/www/myberkeley/redfive/api/webhook_to_cad_payload.log
tail -f /var/www/myberkeley/redfive/api/cad_response_debug.log
```

Or use the log viewer:

- <https://my.berkeleycountysc.gov/redfive/view/log_viewer.php?file=webhook_to_cad_payload.log>
- <https://my.berkeleycountysc.gov/redfive/view/log_viewer.php?file=cad_response_debug.log>

### 2. Trigger Test Webhook

Use RapidSOS sandbox to create a demo alert, then check:

1. `webhook_to_cad_payload.log` - Verify payload has correct structure
2. `cad_response_debug.log` - Verify successful response from Southern Software
3. `cad_debug.log` - Verify CAD call was made with data

### 3. Verify Database Entry

Check that the CAD call has all the expected fields populated:

- CallTypeAlias (should be mapped value like "51 ALARMS - FIRE")
- Latitude/Longitude coordinates
- Street address
- City, State
- Emergency description
- Service provider name

## Files Modified

1. `webhooks/rapidsos_webhook.php`:
   - Rewrote `transformWebhookAlert()` to preserve RapidSOS structure
   - Updated `forwardAlertToWriteToDB()` to wrap in `alerts` array
   - Added payload and response logging

2. `api/.htaccess` (created):
   - Allow access to `.log` and debug files

3. `logs/.htaccess` (created):
   - Allow access to `.log` files

4. `view/log_viewer.php` (created):
   - Alternative log file viewer

## New Log Files Generated

- `api/webhook_to_cad_payload.log` - Outgoing payloads to writeToDB.php
- `api/cad_response_debug.log` - Responses from writeToDB.php/CAD

## Expected Results

After this fix:
✅ CAD entries should have complete data (address, coordinates, description, etc.)
✅ Emergency types correctly mapped (FIRE, MEDICAL, BURGLARY, PANIC)
✅ Can view log files via `.htaccess` or log viewer
✅ Full visibility into webhook → CAD data flow

## Next Steps

1. Deploy changes to production server
2. Test with RapidSOS demo alert
3. Verify CAD entry has complete data
4. Review log files to confirm correct payload structure
5. Test all emergency type mappings

## Related Documentation

- `.github/copilot-instructions.md` - Complete project context
- `ref/FIELD_MAPPING.md` - Field mapping documentation
- `STATUS_UPDATE_IMPLEMENTATION.md` - Status update handling
