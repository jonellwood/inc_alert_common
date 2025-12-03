# Deployment Checklist - Webhook Data Fix

## Files to Upload to Server

### Modified Files

- [ ] `webhooks/rapidsos_webhook.php` - Fixed data transformation and added logging

### New Files  

- [ ] `api/.htaccess` - Allow log file access
- [ ] `logs/.htaccess` - Allow log file access
- [ ] `view/log_viewer.php` - Alternative log viewer interface

### Documentation (optional)

- [ ] `WEBHOOK_DATA_FIX.md` - Complete fix documentation
- [ ] `STATUS_UPDATE_IMPLEMENTATION.md` - Status update handling docs

## Pre-Deployment Checklist

- [ ] Backup current `webhooks/rapidsos_webhook.php` on server
- [ ] Ensure `/api/` directory is writable for new log files
- [ ] Verify FileZilla connection to my.berkeleycountysc.gov
- [ ] Have SSH access ready for log monitoring

## Deployment Steps

### 1. Upload Modified Files

```bash
# Upload via FileZilla to:
/var/www/myberkeley/redfive/webhooks/rapidsos_webhook.php
```

### 2. Upload New Files

```bash
# Upload via FileZilla to:
/var/www/myberkeley/redfive/api/.htaccess
/var/www/myberkeley/redfive/logs/.htaccess
/var/www/myberkeley/redfive/view/log_viewer.php
```

### 3. Set Permissions

```bash
# SSH to server and run:
cd /var/www/myberkeley/redfive

# Make sure log files can be written
chmod 666 api/webhook_to_cad_payload.log 2>/dev/null || touch api/webhook_to_cad_payload.log && chmod 666 api/webhook_to_cad_payload.log
chmod 666 api/cad_response_debug.log 2>/dev/null || touch api/cad_response_debug.log && chmod 666 api/cad_response_debug.log

# Verify permissions
ls -la api/*.log
ls -la logs/*.log
ls -la api/.htaccess
ls -la logs/.htaccess
```

## Post-Deployment Testing

### 1. Test Log File Access

Try accessing logs directly (should work now):

- <https://my.berkeleycountysc.gov/redfive/api/cad_debug.log>
- <https://my.berkeleycountysc.gov/redfive/logs/webhook_debug.log>

Or use log viewer:

- <https://my.berkeleycountysc.gov/redfive/view/log_viewer.php>

### 2. Trigger Test Webhook

1. Go to RapidSOS sandbox portal
2. Create a demo alert (MEDICAL, FIRE, BURGLARY, or PANIC)
3. Monitor logs in real-time:

```bash
# SSH to server
cd /var/www/myberkeley/redfive

# Watch webhook activity
tail -f logs/webhook_debug.log

# In another terminal, watch payload being sent to CAD
tail -f api/webhook_to_cad_payload.log

# In another terminal, watch CAD response
tail -f api/cad_response_debug.log
```

### 3. Verify CAD Entry Created

Check that the CAD entry has all data:

- ✅ CallTypeAlias correctly mapped (51 ALARMS - FIRE, 32 UNKNOWN PROBLEM, etc.)
- ✅ Latitude/Longitude populated
- ✅ Street address populated
- ✅ City, State populated
- ✅ Description populated
- ✅ Service provider name populated

### 4. Verify No Duplicates

Ensure only ONE database entry created for `alert.new` event (status updates should be logged but not create new CAD entry)

## Verification Commands

### Check Latest Webhook

```bash
tail -20 /var/www/myberkeley/redfive/logs/webhook_debug.log | grep "alert_received"
```

### Check Latest Payload Sent to CAD

```bash
tail -50 /var/www/myberkeley/redfive/api/webhook_to_cad_payload.log
```

### Check CAD Response

```bash
tail -50 /var/www/myberkeley/redfive/api/cad_response_debug.log
```

### Check Southern Software CAD Debug

```bash
tail -50 /var/www/myberkeley/redfive/api/cad_debug.log
```

## Troubleshooting

### If .htaccess doesn't work (still 403)

The server might have `AllowOverride None` set. Check Apache config or use log viewer instead:

- <https://my.berkeleycountysc.gov/redfive/view/log_viewer.php>

### If logs show empty payload

Check that:

1. `transformWebhookAlert()` is preserving the RapidSOS structure
2. Payload is wrapped in `alerts` array
3. Check `webhook_to_cad_payload.log` to see what's being sent

### If CAD entry still has no data

Check `api/cad_response_debug.log` for errors from writeToDB.php:

- Look for HTTP status code (should be 200)
- Check for error messages in response
- Verify payload structure matches expected format

### If status updates create duplicates

Verify the `isStatusUpdate` flag is being set correctly:

```bash
grep "status_update_received" /var/www/myberkeley/redfive/logs/webhook_debug.log
```

## Rollback Plan

If issues occur:

1. Restore backup of `webhooks/rapidsos_webhook.php`
2. Remove new `.htaccess` files
3. Document the issue
4. Check logs for specific error messages

## Success Criteria

- [ ] Log files accessible (via direct URL or log viewer)
- [ ] Webhook received and logged
- [ ] Payload sent to CAD has complete data structure
- [ ] CAD entry created with all fields populated
- [ ] Emergency type correctly mapped
- [ ] No duplicate entries on status updates
- [ ] CFS number returned successfully

## Next Steps After Success

1. Test all emergency types (FIRE, MEDICAL, BURGLARY, PANIC)
2. Implement database update logic for status changes
3. Research RapidSOS Alert Callbacks API
4. Move from sandbox to production environment
5. Enable webhook signature verification

## Support URLs

- Log Viewer: <https://my.berkeleycountysc.gov/redfive/view/log_viewer.php>
- Dashboard: <https://my.berkeleycountysc.gov/redfive/>
- Subscription Manager: <https://my.berkeleycountysc.gov/redfive/manage/subscriptions.php>
