# RapidSOS Integration Setup Guide

## Based on Official Postman Collections

## 🔑 Understanding the Credential System

RapidSOS uses a **three-tier credential system**:

### 1. Admin/PSAP Credentials

- **Purpose**: Create and manage webhook integrations
- **Where to get them**: From your RapidSOS account representative
- **Postman variable**: `{{psapQaTestClientId}}` / `{{psapQaTestClientSecret}}`
- **Also need**: `{{psapAccountId}}` (your agency/PSAP ID)

### 2. RSOS/Alerts Credentials (YOU HAVE THESE)

- **Purpose**: Used IN the integration payload
- **Current value**: `A5sa18wIxv3P2tb7OAGGmdcrJgf63IOM`
- **Postman variable**: `{{rsosClientId}}` / `{{rsosClientSecret}}`
- **Note**: "Alerts-Egress-Pre-Product" credentials

### 3. EDX Credentials (GENERATED)

- **Purpose**: WebSocket and webhook authentication
- **Where they come from**: Returned when you create an integration
- **Postman variable**: `{{edxClientId}}` / `{{edxClientSecret}}`
- **Note**: These are what you should use for WebSocket connections!

## 🚀 The Proper Setup Workflow

### Step 1: Create a Webhook Integration

**You need from RapidSOS:**

- Admin client ID and secret
- Your PSAP/Agency ID

**API Call:**

```http
POST https://edx-sandbox.rapidsos.com/v1/integrations
Authorization: Bearer <ADMIN_TOKEN>
Content-Type: application/json

{
    "rsosClientId": "A5sa18wIxv3P2tb7OAGGmdcrJgf63IOM",
    "rsosClientSecret": "qa4hIib7s713ihJZ",
    "rsosAgencyId": "YOUR_PSAP_AGENCY_ID"
}
```

**Response:**

```json
{
    "webhookId": "webhook-abc-123",
    "edxClientId": "generated-client-id",
    "edxClientSecret": "generated-client-secret"
}
```

### Step 2: Update Integration with Event Types

**API Call:**

```http
PATCH https://edx-sandbox.rapidsos.com/v1/integrations/{webhookId}
Authorization: Bearer <ADMIN_TOKEN>
Content-Type: application/json

{
    "eventTypes": [
        "alert.new",
        "alert.status_update",
        "alert.disposition_update",
        "alert.location_update",
        "alert.chat",
        "alert.milestone",
        "alert.multi_trip_signal"
    ]
}
```

### Step 3: Configure WebSocket with EDX Credentials

Update your `config/rapidsos_config.php`:

```php
'client_id' => 'GENERATED_EDX_CLIENT_ID',
'client_secret' => 'GENERATED_EDX_CLIENT_SECRET',
```

**Important**: Use the EDX credentials from Step 1, NOT your original RSOS credentials!

### Step 4: Connect WebSocket

Now your WebSocket client should work:

```bash
php websocket_client.php
```

## 📋 What to Ask RapidSOS Support For

Contact your RapidSOS representative and ask for:

1. **Admin/Management Credentials**
   - Admin client ID
   - Admin client secret
   - Purpose: "To create and manage webhook integrations via the Integration Management API"

2. **Your Agency/PSAP ID**
   - This identifies your organization in their system
   - Format: Usually something like `NY_123` or similar

3. **Confirm Your Current Credentials**
   - Verify that `A5sa18wIxv3P2tb7OAGGmdcrJgf63IOM` is correct
   - Ask what these credentials are authorized to do

## 🔍 Alternative: Check if Integration Already Exists

It's possible an integration was already created for you. Ask RapidSOS to:

1. Check if a webhook integration exists for your account
2. If yes, provide the `webhookId`
3. Retrieve the EDX client credentials for that integration

Then you can get them via:

```http
GET https://edx-sandbox.rapidsos.com/v1/integrations/{webhookId}
Authorization: Bearer <ADMIN_TOKEN>
```

This will return the EDX credentials you need.

## 🎯 Summary

**Current Issue**: You're trying to use RSOS credentials for everything, but you need:

- **Admin credentials** → Create/manage integrations
- **RSOS credentials** → Payload for integration creation
- **EDX credentials** → WebSocket/webhook authentication (generated from integration)

**Next Steps**:

1. Contact RapidSOS for admin credentials and agency ID
2. Create webhook integration (or get existing one's EDX credentials)
3. Update config with EDX credentials
4. WebSocket should then receive demo alerts!

## 📞 Questions for RapidSOS

1. "Can you provide admin/management API credentials to create webhook integrations?"
2. "What is our PSAP/Agency ID in your system?"
3. "Does a webhook integration already exist for our account? If so, what's the webhookId?"
4. "If an integration exists, can you provide the EDX client ID and secret for it?"
5. "Do our current credentials (A5sa18wIxv3P2tb7OAGGmdcrJgf63IOM) have the necessary permissions?"
