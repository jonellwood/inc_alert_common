# Postman Collection Variables - What We Have

## Variables from Screenshot

### ✅ **Variables We HAVE:**

1. **edxHttpApiUrl**
   - Value: `https://api-sandbox.rapidsos.com`
   - **We have this!** This is the webhook subscription API base URL
   - Confirms we should use `api-sandbox.rapidsos.com` (not `edx-sandbox.rapidsos.com`)

2. **edxAuthToken**
   - Value: `qT6TjAj87AYGdcBMUGrhSD2UX648`
   - You got this by posting to `https://api-sandbox.rapidsos.com/oauth/token`
   - Expires in: 3599 seconds (~1 hour)
   - **⚠️ IMPORTANT: This is from a DIFFERENT endpoint than we've been using!**

3. **psapAccountId**
   - Value: `ID_BerkeleyAlerts`
   - **We have this!** From the Agency Info screenshot

### ❓ **Variables We DON'T Have (Need from RapidSOS):**

4. **webhookId**
   - Empty in screenshot
   - **Purpose**: The integration webhook ID (not the subscription ID)
   - This would come from creating an integration via `/v1/integrations`

5. **edxClientId**
   - Empty in screenshot
   - **Purpose**: Generated EDX credentials for WebSocket
   - Returned when creating a webhook integration

6. **edxClientSecret**
   - Empty in screenshot
   - **Purpose**: Generated EDX credentials for WebSocket
   - Returned when creating a webhook integration

7. **psapQaTestClientId**
   - Empty in screenshot
   - **Purpose**: PSAP/Admin credentials to create integrations
   - Need from RapidSOS

8. **psapQaTestClientSecret**
   - Empty in screenshot
   - **Purpose**: PSAP/Admin credentials to create integrations
   - Need from RapidSOS

## 🚨 **CRITICAL DISCOVERY!**

### You Got a Token from a DIFFERENT Endpoint

**What you did:**

```
POST https://api-sandbox.rapidsos.com/oauth/token
```

**What we've been doing:**

```
POST https://edx-sandbox.rapidsos.com/oauth/token
```

**This is HUGE!** The Postman collection uses `api-sandbox.rapidsos.com` for OAuth, which is DIFFERENT from what we configured!

## 🔧 **Let's Test This Now:**

### Current Config Uses

- Base URL: `https://edx-sandbox.rapidsos.com`
- OAuth endpoint: `https://edx-sandbox.rapidsos.com/oauth/token`

### Postman Collection Uses

- Base URL: `https://api-sandbox.rapidsos.com`
- OAuth endpoint: `https://api-sandbox.rapidsos.com/oauth/token`

## 📊 **Comparison Table:**

| Variable | We Have? | Value/Source |
|----------|----------|--------------|
| `edxHttpApiUrl` | ✅ YES | `https://api-sandbox.rapidsos.com` |
| `edxAuthToken` | ✅ YES | Generated via OAuth (expires hourly) |
| `psapAccountId` | ✅ YES | `ID_BerkeleyAlerts` |
| `webhookId` | ❌ NO | Need to create integration OR get existing |
| `edxClientId` | ❌ NO | Generated from integration creation |
| `edxClientSecret` | ❌ NO | Generated from integration creation |
| `psapQaTestClientId` | ❌ NO | Need from RapidSOS admin |
| `psapQaTestClientSecret` | ❌ NO | Need from RapidSOS admin |

## 🎯 **What This Means:**

1. **The Postman collection expects you to use `api-sandbox.rapidsos.com`** for EVERYTHING
2. **We've been mixing endpoints** - using `edx-sandbox` for some things and `api-sandbox` for others
3. **This might be why webhooks aren't working!**

## 💡 **Questions for RapidSOS:**

1. **"Which OAuth endpoint should we use?"**
   - `https://edx-sandbox.rapidsos.com/oauth/token` (what we configured)
   - `https://api-sandbox.rapidsos.com/oauth/token` (what Postman uses)
   - Are they both valid? Do they return different tokens/scopes?

2. **"Can you provide the missing Postman variables?"**
   - `webhookId` - Does an integration already exist for us?
   - `edxClientId` / `edxClientSecret` - Generated EDX credentials
   - `psapQaTestClientId` / `psapQaTestClientSecret` - Admin credentials

3. **"What's the difference between these base URLs?"**
   - `edx-sandbox.rapidsos.com` - For what?
   - `api-sandbox.rapidsos.com` - For what?
   - Should we use one or both?

## 🧪 **Let's Test Both OAuth Endpoints:**

Want me to create a script that tests getting a token from BOTH endpoints to see if there's a difference?
