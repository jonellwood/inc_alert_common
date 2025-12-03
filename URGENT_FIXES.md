# URGENT FIXES - CallTypeAlias & Callback Issues

## Date: November 17, 2025

## Problems Identified

1. **CallTypeAlias appears empty in CAD entries**
   - Database field `sCallType` is being saved correctly
   - But when retrieved for CAD payload, appears to be NULL
   - Fallback to hardcoded "104 ALARMS - LAW" occurring

2. **RapidSOS Callbacks Not Being Sent**
   - No entries in `rapidsos_callbacks.log`
   - Alerts timing out (45 second window)
   - Mission-critical: Must accept alerts within time limit

3. **Apache 403 Forbidden on /logs/ directory**
   - .htaccess not working properly
   - Need alternative log viewing method

## Fixes Applied

### 1. Added Debug Logging for CallTypeAlias

**File:** `api/writeToDB.php`

Added logging BEFORE CAD payload is built:

```php
// Debug: Log the sCallType value to diagnose CallTypeAlias issues
$debugCallType = $record['sCallType'] ?? 'NULL';
error_log("CAD Payload - Record sCallType: '{$debugCallType}' | Emergency Type: '" . ($record['sEmergencyType'] ?? 'NULL') . "'");
```

Also added CallTypeAlias to CAD debug log:

```php
"CallTypeAlias: " . ($cadData['CallTypeAlias'] ?? 'NULL') . "\n" .
```

### 2. Moved Callback to Execute IMMEDIATELY

**File:** `api/writeToDB.php`

Callback now happens BEFORE database update:

```php
// CRITICAL: Send callback to RapidSOS IMMEDIATELY (before database update)
// Callbacks must happen within ~45 seconds or alert times out
sendRapidSOSCallback($record, $cfsNumber);

// Then update database...
```

**Why:** Database updates are secondary. CAD creation + callback are mission-critical.

### 3. Updated Project Documentation

**File:** `.github/copilot-instructions.md`

Added critical priority section emphasizing:

- CAD creation is #1 priority
- RapidSOS callback must happen within 45 seconds
- Database is secondary/bonus functionality

## Next Steps for Deployment

### 1. Upload Modified Files

```
/Users/jonathanellwood/inc_alert_common/api/writeToDB.php
/Users/jonathanellwood/inc_alert_common/.github/copilot-instructions.md
```

### 2. Create Fresh RapidSOS Test Alert

Immediately after deployment:

1. Go to RapidSOS sandbox portal
2. Create a new demo alert (FIRE type to test CallTypeAlias mapping)
3. **Monitor in real-time:**

```bash
# Terminal 1: Watch error logs for debug output
tail -f /var/www/myberkeley/redfive/error_log

# Terminal 2: Watch CAD debug
tail -f /var/www/myberkeley/redfive/api/cad_debug.log

# Terminal 3: Watch callback attempts
tail -f /var/www/myberkeley/redfive/logs/rapidsos_callbacks.log
```

### 3. Check What You'll See

#### If CallTypeAlias is Working

```
error_log: CAD Payload - Record sCallType: '51 ALARMS - FIRE' | Emergency Type: 'Fire'
cad_debug.log: CallTypeAlias: 51 ALARMS - FIRE
```

#### If CallTypeAlias Still NULL

```
error_log: CAD Payload - Record sCallType: 'NULL' | Emergency Type: 'Fire'
cad_debug.log: CallTypeAlias: 104 ALARMS - LAW
```

→ This means `call_type_alias` is not being extracted from webhook properly

#### If Callback is Working

```
rapidsos_callbacks.log: {
  "timestamp": "2025-11-17 14:35:12",
  "alert_id": "alert-xxx",
  "action": "Alert accepted...",
  "http_code": 200
}
```

#### If Callback Fails

```
error_log: sendRapidSOSCallback: Invalid or missing alert ID: NULL
```

or

```
rapidsos_callbacks.log: {
  "http_code": 401,  // Auth failure
  "error": "..."
}
```

## Debugging CallTypeAlias Issue

### Possible Causes

1. **Webhook transformation not adding `call_type_alias`**
   - Check: `webhooks/rapidsos_webhook.php` → `transformWebhookAlert()`
   - Should add: `$transformed['call_type_alias'] = mapEmergencyTypeToCallType($rapidSOSType);`

2. **Database column doesn't exist or is wrong name**
   - Check: SQL Server `IncomingAlertData` table schema
   - Should have: `sCallType` column

3. **Extraction not happening**
   - Check: `api/writeToDB.php` → `extractRapidSOSData()`
   - Should have: `$extracted['sCallType'] = $alert['call_type_alias'] ?? null;`

### Quick Test

After deployment, check the webhook payload log:

```bash
tail -f /var/www/myberkeley/redfive/api/webhook_to_cad_payload.log
```

Look for:

```json
{
  "payload": {
    "alerts": [{
      "call_type_alias": "51 ALARMS - FIRE",  // <-- Should be here
      "emergency_type": { "name": "FIRE" }
    }]
  }
}
```

If `call_type_alias` is missing from payload, the webhook transformation is not working.

## Debugging Callback Issue

### Possible Causes

1. **Alert ID not valid format**
   - Must be: `alert-{uuid}`
   - Check error_log for: "Invalid or missing alert ID"

2. **OAuth token failure**
   - Check: `logs/rapidsos_auth.log`
   - Test: `php test/test_rapidsos_credentials.php`

3. **Wrong API endpoint**
   - Sandbox: `https://edx-sandbox.rapidsos.com`
   - Check: `config/rapidsos_config.php`

4. **Callback happening but failing**
   - Check: `logs/rapidsos_callbacks.log` for HTTP error codes
   - 401 = auth issue
   - 403 = permission issue (PSAP permissions needed)
   - 404 = alert not found (wrong alert ID)

## Critical Timing

```
Alert Created in RapidSOS
    ↓
Webhook Sent (t=0)
    ↓
Webhook Received by our system (t=~1s)
    ↓
Data Transformed (t=~2s)
    ↓
CAD Entry Created (t=~3-5s)
    ↓
*** CALLBACK MUST HAPPEN HERE ***  (t=~5-10s)
    ↓
Database Updated (t=~10-15s)
    ↓
----------------------------------------
RapidSOS Timeout if no callback by t=~45s
```

**Current implementation:** Callback happens immediately after CAD creation, before database update.

## Files Modified

1. `api/writeToDB.php`
   - Added sCallType debug logging
   - Added CallTypeAlias to CAD debug log
   - Moved callback before database update

2. `.github/copilot-instructions.md`
   - Added critical mission priorities section
   - Emphasized CAD > Callback > Database priority order

## Related Files (Not Modified But Important)

- `lib/rapidsos_callbacks.php` - Callback implementation
- `webhooks/rapidsos_webhook.php` - Where `call_type_alias` should be added
- `config/rapidsos_config.php` - API configuration

## Success Criteria

After deployment and test:

✅ **CallTypeAlias shows correct value in CAD**

- FIRE → "51 ALARMS - FIRE"
- MEDICAL → "32 UNKNOWN PROBLEM"
- BURGLARY/PANIC → "104 ALARMS - LAW"

✅ **Callback logs show successful acceptance**

- HTTP 200 response
- Alert status updated in RapidSOS portal

✅ **Timing under 10 seconds**

- Webhook → CAD → Callback < 10 seconds total

✅ **No alert timeout errors in RapidSOS**
