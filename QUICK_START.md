# RapidSOS Webhook System - Quick Start Guide

## What We Built

A complete webhook-based emergency alert integration system that:

- Receives real-time emergency alerts from RapidSOS
- Authenticates using OAuth 2.0 with automatic token management
- Verifies webhook signatures for security
- Transforms webhook data and forwards to your existing alert processing
- Provides web interfaces for subscription management and testing

## Files Created

### Core System

- **`/config/rapidsos_config.php`** - Your RapidSOS credentials and settings
- **`/lib/rapidsos_auth.php`** - OAuth 2.0 authentication manager
- **`/webhooks/rapidsos_webhook.php`** - Main webhook endpoint

### Management & Testing

- **`/manage/subscriptions.php`** - Web interface for webhook subscriptions
- **`/test/webhook_tester.php`** - Testing utility for webhooks

### Documentation

- **`/WEBHOOK_README.md`** - Complete documentation

## Next Steps

### 1. Get Your Webhook Secret

You need to get the webhook secret from RapidSOS and update it in `/config/rapidsos_config.php`:

```php
'webhook_secret' => 'YOUR_ACTUAL_WEBHOOK_SECRET_FROM_RAPIDSOS',
```

### 2. Test the System

1. Open: `https://my.berkeleycountysc.gov/redfive/test/webhook_tester.php`
2. Click "Test Connection" to verify OAuth authentication
3. Send a test webhook to verify processing

### 3. Create Webhook Subscriptions

1. Open: `https://my.berkeleycountysc.gov/redfive/manage/subscriptions.php`
2. Verify connection status
3. Create a subscription for events: `alert.new`, `alert.status_update`

### 4. Monitor the System

Check log files for activity:

- `/logs/rapidsos_auth.log` - Authentication activity
- `/logs/rapidsos_subscriptions.log` - Subscription management
- `/logs/webhook_debug.log` - Incoming webhook events

## How It Works

```
RapidSOS Alert → Webhook → Signature Verification → Data Transformation → writeToDB.php → Database + CAD
```

1. **RapidSOS** sends webhooks to: `https://my.berkeleycountysc.gov/redfive/webhooks/rapidsos_webhook.php`
2. **Webhook endpoint** verifies signature and processes the alert
3. **Data is transformed** and forwarded to your existing `writeToDB.php`
4. **Existing logic** handles database storage and CAD posting

## Current Configuration

- **Environment**: Sandbox (for testing)
- **Client ID**: A5sa18wIxv3P2tb7OAGGmdcrJgf63IOM
- **Webhook URL**: <https://my.berkeleycountysc.gov/redfive/webhooks/rapidsos_webhook.php>
- **Target URL**: <https://my.berkeleycountysc.gov/redfive/api/writeToDB.php>

## Testing URLs

- **Subscription Management**: <https://my.berkeleycountysc.gov/redfive/manage/subscriptions.php>
- **Webhook Tester**: <https://my.berkeleycountysc.gov/redfive/test/webhook_tester.php>

## Ready to Use

The system is now ready to receive real-time emergency alerts from RapidSOS. Once you get the webhook secret and create subscriptions, alerts will flow automatically through your existing emergency response pipeline.
