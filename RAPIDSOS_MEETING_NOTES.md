# RapidSOS Meeting - Key Questions & Findings

## 📊 Current Status

### ✅ What's Working

- OAuth authentication successful
- Access token generation: `A5sa18wIxv3P2tb7OAGGmdcrJgf63IOM`
- **5 Active webhook subscriptions** (IDs: 1664965, 1660551, 1664966, 1672900, 1672899)
- WebSocket client can connect to `wss://ws.edx-sandbox.rapidsos.com/v1`
- All endpoints correctly configured

### ❌ What's Not Working

- Demo alerts created in RapidSOS portal don't reach our webhook endpoint
- Demo alerts don't appear in WebSocket stream  
- **Zero webhook deliveries** (no logs on our server)

## 🎯 Critical Questions for RapidSOS

### 1. Demo Alert Routing

**Q:** "When we create demo alerts in the RapidSOS sandbox portal, where do they get sent?"

- Do they automatically route to active webhook subscriptions?
- Do they automatically stream to active WebSocket connections?
- Or do we need to configure something else?

### 2. Webhook Subscription Configuration

**Q:** "We have 5 active webhook subscriptions. Which one receives demo alerts?"

- Subscription 1664966: Our main endpoint
- Subscription 1660551: Points to `edx-sandbox.rapidsos.com/v1/alert-webhook-receiver/...`
- **Are demo alerts being sent to a different subscription than our endpoint?**

### 3. Integration vs Subscription

**Q:** "Your Postman collections show /v1/integrations endpoint, but we can't access it with our credentials. Do we need this?"

- We're using `/v1/webhooks/subscriptions` (which works)
- Is there a difference between "webhook integration" and "webhook subscription"?
- Do demo alerts only work with integrations, not subscriptions?

### 4. The Mystery Webhook (ID: 1660551)

**Q:** "Subscription 1660551 points to a RapidSOS URL with hash `45da52538670407ea06b6bd653bbeb02`. What is this?"

```
https://edx-sandbox.rapidsos.com/v1/alert-webhook-receiver/45da52538670407ea06b6bd653bbeb02
```

- Is this catching our demo alerts?
- Is this hash an integration ID or webhook ID?
- How do we access/manage this?

### 5. Credential Clarification

**Q:** "We have credentials `A5sa18wIxv3P2tb7OAGGmdcrJgf63IOM`. What can these do?"

- They work for OAuth tokens
- They work for webhook subscriptions  
- They DON'T work for /v1/integrations endpoint
- Are these "RSOS/Alerts" credentials or "EDX" credentials?
- Do we need different credentials for demo alerts?

### 6. WebSocket Events

**Q:** "Our WebSocket connects successfully but receives no events. Why?"

- Connected to: `wss://ws.edx-sandbox.rapidsos.com/v1`
- Subscribed to: `alert.new`, `alert.status_update`, etc.
- Authenticated with valid Bearer token
- **Do demo alerts trigger WebSocket events?**

### 7. Agency/PSAP ID

**Q:** "What is our PSAP/Agency ID in your system?"

- Needed for integration creation (if required)
- Might be needed to associate demo alerts with our account

## 📋 What We've Built

1. **Webhook Endpoint**: `https://my.berkeleycountysc.gov/redfive/webhooks/rapidsos_webhook.php`
   - HMAC signature verification ready
   - Logging all requests
   - Integrated with database and CAD system

2. **WebSocket Client**: Production-ready
   - Using textalk/websocket library
   - OAuth 2.0 authentication
   - Event processing pipeline
   - Background service capability

3. **Multiple Testing Tools**:
   - Credential diagnostic
   - Integration manager
   - Subscription manager
   - Real-time monitoring

## 🔍 Possible Issues

### Theory 1: Demo Alerts Go to Integration, Not Subscription

- Your Postman shows `/v1/integrations` workflow
- We're using `/v1/webhooks/subscriptions`
- Maybe demo alerts only work with integrations?

### Theory 2: We Have an Integration But Don't Know It

- Subscription 1660551 has that RapidSOS URL
- Maybe that IS an integration and demo alerts go there?
- We need the EDX credentials for that integration

### Theory 3: Environment/Account Mismatch

- Demo alerts in portal tied to specific account/environment
- Our subscriptions/connections in different scope?
- Need to verify account linking

## 🚀 What We Need from RapidSOS

1. **Immediate**:
   - Confirm where demo alerts are being sent right now
   - Explain the hash `45da52538670407ea06b6bd653bbeb02`
   - Tell us if we need integration vs subscription for demo alerts

2. **Configuration**:
   - If integration needed: provide admin credentials OR retrieve existing integration's EDX credentials
   - If subscription works: explain why no webhooks are being delivered
   - Our PSAP/Agency ID

3. **Testing**:
   - Can you send a test webhook to our endpoint while we're on the call?
   - Can you verify our WebSocket connection is receiving events?
   - Help us trigger a demo alert and watch it flow through

## 📞 Live Testing Plan

During the call, we can:

1. Run real-time webhook monitoring: `tail -f logs/webhook_debug.log`
2. Run WebSocket monitoring: `php monitor_websocket.php`
3. Create demo alert in portal
4. Watch both monitors to see where it goes
5. Identify the missing link immediately

---

**Bottom Line**: We have all the infrastructure built. We just need to understand the correct routing configuration for demo alerts in the sandbox environment.
