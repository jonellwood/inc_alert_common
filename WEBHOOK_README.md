# RapidSOS Webhook Integration System

This system provides a complete webhook-based integration with RapidSOS's Emergency Data Exchange (EDX) platform for real-time emergency alert processing.

## Overview

The webhook system enables subscription-based alert ingestion, replacing the need for periodic polling. When emergency alerts are created or updated in RapidSOS, they are automatically pushed to your webhook endpoint for immediate processing.

## Architecture

```
RapidSOS EDX → Webhook Endpoint → Your Alert Processing → Database + CAD
```

1. **RapidSOS** sends webhook notifications when alerts are created/updated
2. **Webhook Endpoint** receives and verifies the payload
3. **Alert Processing** transforms the data and forwards to existing `writeToDB.php`
4. **Database + CAD** stores alert and posts to Southern Software CIM

## File Structure

```
/config/
  rapidsos_config.php          # API credentials and configuration
  
/lib/
  rapidsos_auth.php           # OAuth 2.0 authentication and signature verification
  
/manage/
  subscriptions.php           # Web interface for managing webhook subscriptions
  
/webhooks/
  rapidsos_webhook.php        # Main webhook endpoint that receives RapidSOS events
  
/test/
  webhook_tester.php          # Testing utility for webhook functionality
  
/logs/
  rapidsos_auth.log          # Authentication and token management logs
  rapidsos_subscriptions.log # Subscription management activity
  webhook_debug.log          # Incoming webhook event logs
```

## Setup Instructions

### 1. Configure Credentials

Update `/config/rapidsos_config.php` with your actual RapidSOS credentials:

```php
'client_id' => 'YOUR_CLIENT_ID',
'client_secret' => 'YOUR_CLIENT_SECRET',
'webhook_secret' => 'YOUR_WEBHOOK_SECRET', // Get from RapidSOS
```

### 2. Set Webhook URLs

Ensure your webhook endpoints are accessible:

- **Webhook Endpoint**: `https://my.berkeleycountysc.gov/inc_alert_common/webhooks/rapidsos_webhook.php`
- **Target Endpoint**: `https://my.berkeleycountysc.gov/inc_alert_common/api/writeToDB.php`

### 3. Create Webhook Subscriptions

Use the subscription management interface:

1. Open `https://my.berkeleycountysc.gov/inc_alert_common/manage/subscriptions.php`
2. Test the connection to verify authentication
3. Create a subscription for your webhook endpoint
4. Select events: `alert.created`, `alert.updated`

### 4. Test the System

Use the webhook tester to verify functionality:

1. Open `https://my.berkeleycountysc.gov/inc_alert_common/test/webhook_tester.php`
2. Test signature verification
3. Send test webhooks to verify processing

## Authentication

The system uses OAuth 2.0 Client Credentials flow:

1. **Token Management**: Automatic token acquisition and refresh
2. **Token Caching**: Tokens are cached to disk to avoid unnecessary requests
3. **Signature Verification**: HMAC-SHA256 verification of incoming webhooks

### Token Lifecycle

- Tokens are cached in `/tmp/rapidsos_token_cache.json`
- Automatic refresh when tokens expire
- Fallback authentication if cache is invalid

## Webhook Security

### Signature Verification

All incoming webhooks are verified using HMAC-SHA256:

```
X-RapidSOS-Signature: sha256=<signature>
```

The signature is computed as:

```
HMAC-SHA256(webhook_secret, request_body)
```

### Request Validation

1. **Method Check**: Only POST requests accepted
2. **Signature Check**: Required header `X-RapidSOS-Signature`
3. **Payload Validation**: JSON parsing and structure validation
4. **Event Processing**: Transform and forward to existing alert pipeline

## Event Types

### `alert.created`

New emergency alerts from RapidSOS

- Contains full caller, location, and emergency details
- Processed immediately and forwarded to CAD

### `alert.updated`

Updates to existing alerts

- May contain additional information or location updates
- Merged with existing alert data

### `alert.closed`

Alert resolution notifications

- Optional event for tracking alert lifecycle
- Can be used to update alert status in database

## Data Transformation

Incoming webhook data is transformed to match your existing API structure:

```php
// Webhook payload (from RapidSOS)
{
  "event": "alert.created",
  "data": {
    "id": "alert-123",
    "caller": {...},
    "location": {...},
    "emergency": {...}
  }
}

// Transformed payload (to writeToDB.php)
{
  "webhook_event_type": "alert.created",
  "rapidsos_alert_id": "alert-123",
  "caller": {...},
  "location": {...},
  "emergency": {...},
  "original_webhook_data": {...}
}
```

## Error Handling

### Authentication Errors

- Automatic token refresh
- Fallback to manual credential validation
- Detailed logging for troubleshooting

### Webhook Errors

- Invalid signatures rejected with 401
- Malformed payloads rejected with 400
- Processing errors logged and return 500

### Forwarding Errors

- Target endpoint failures logged
- Retry logic can be implemented if needed
- Original webhook data preserved for debugging

## Monitoring and Logging

### Log Files

1. **`/logs/rapidsos_auth.log`**
   - OAuth token requests and responses
   - Authentication failures and successes
   - Token cache operations

2. **`/logs/rapidsos_subscriptions.log`**
   - Subscription creation, updates, deletions
   - API calls to RapidSOS subscription endpoints
   - Management interface activity

3. **`/logs/webhook_debug.log`**
   - All incoming webhook requests
   - Signature verification results
   - Payload processing and forwarding results

### Monitoring Tips

- Check logs regularly for authentication issues
- Monitor webhook processing times
- Verify subscription status periodically
- Test webhook endpoint accessibility

## Troubleshooting

### Common Issues

1. **Authentication Failures**
   - Verify client credentials in config
   - Check token cache permissions
   - Test connection manually

2. **Signature Verification Fails**
   - Ensure webhook secret is correct
   - Verify RapidSOS is using the right secret
   - Check for payload modifications

3. **Webhooks Not Received**
   - Verify subscription is active
   - Check webhook endpoint accessibility
   - Review RapidSOS dashboard for delivery status

4. **Processing Errors**
   - Check target endpoint availability
   - Verify payload transformation logic
   - Review database connection issues

### Testing Commands

```bash
# Test webhook endpoint directly
curl -X POST https://my.berkeleycountysc.gov/inc_alert_common/webhooks/rapidsos_webhook.php \
  -H "Content-Type: application/json" \
  -H "X-RapidSOS-Signature: sha256=..." \
  -d '{"event":"alert.created","data":{...}}'

# Check log files
tail -f /path/to/logs/webhook_debug.log
tail -f /path/to/logs/rapidsos_auth.log

# Test subscription management
curl https://my.berkeleycountysc.gov/inc_alert_common/manage/subscriptions.php
```

## Environment Configuration

### Sandbox vs Production

The system is currently configured for RapidSOS sandbox environment:

```php
'environment' => 'sandbox',
'base_urls' => [
    'sandbox' => 'https://edx-sandbox.rapidsos.com',
    'production' => 'https://edx.rapidsos.com'
]
```

To switch to production:

1. Update `'environment' => 'production'`
2. Verify production credentials
3. Update webhook subscriptions
4. Test thoroughly before going live

### Required Permissions

Ensure proper file permissions:

- `/logs/` directory: writable by web server
- `/tmp/` access for token caching
- Configuration files: readable by web server

## API Integration

The webhook system integrates seamlessly with your existing emergency alert pipeline:

1. **Incoming Webhook** → `rapidsos_webhook.php`
2. **Data Transformation** → Format for existing API
3. **Alert Processing** → Forward to `writeToDB.php`
4. **Database Storage** → Same table structure
5. **CAD Integration** → Same Southern Software CIM posting

No changes required to existing database schema or CAD integration logic.

## Next Steps

1. **Configure webhook secret** from RapidSOS dashboard
2. **Create production subscriptions** when ready
3. **Monitor webhook delivery** and processing
4. **Set up alerting** for webhook failures
5. **Document deployment process** for production

## Support

For issues with:

- **RapidSOS API**: Contact RapidSOS support
- **Webhook processing**: Check logs and test utility
- **CAD integration**: Verify existing `writeToDB.php` functionality
- **Database issues**: Check connection and schema
