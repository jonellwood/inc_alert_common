# RapidSOS Follow-up Questions - Demo Alert Routing

## Context

- Using correct credentials: Alerts-Egress-Pre-Product (`A5sa18wIxv3P2tb7OAGGmdcrJgf63IOM`)
- Have 5 active webhook subscriptions
- WebSocket client connecting successfully
- **Problem**: Demo alerts not reaching our endpoint

## 🔍 Discovery from Webhook Subscriptions

We found this subscription in our account:

```json
{
  "id": 1660551,
  "created_time": 1758718214673,
  "url": "https://edx-sandbox.rapidsos.com/v1/alert-webhook-receiver/45da52538670407ea06b6bd653bbeb02",
  "event_types": [
    "alert.new",
    "alert.status_update",
    "alert.chat",
    "alert.disposition_update",
    "alert.multi_trip_signal",
    "alert.milestone",
    "alert.location_update",
    "location.prevalidation"
  ]
}
```

## ❓ Key Questions

### 1. Webhook Subscription #1660551

**Q:** "What is webhook subscription #1660551 that points to your sandbox URL?"

- URL: `https://edx-sandbox.rapidsos.com/v1/alert-webhook-receiver/45da52538670407ea06b6bd653bbeb02`
- Hash: `45da52538670407ea06b6bd653bbeb02`
- **Are demo alerts being sent to THIS subscription instead of ours?**

### 2. Multiple Subscriptions Behavior

**Q:** "We have 5 webhook subscriptions. How do demo alerts get routed?"

- Do they go to ALL subscriptions?
- Only the first one?
- Only specific ones?
- Our subscriptions pointing to our server:
  - ID: 1664965
  - ID: 1664966  
  - ID: 1672899
  - ID: 1672900

### 3. Demo Alert Creation

**Q:** "When you create a demo alert in the RapidSOS sandbox portal, what happens?"

- Does it automatically trigger webhooks?
- Does it stream to WebSocket connections?
- Is there a configuration step we're missing?

### 4. Subscription Testing

**Q:** "Can you verify which subscription(s) received demo alerts?"

- We can create a demo alert while on the call
- Monitor our endpoint: `https://my.berkeleycountysc.gov/redfive/webhooks/rapidsos_webhook.php`
- See if webhooks are being delivered

### 5. WebSocket vs Webhooks for Demo Alerts

**Q:** "Should demo alerts appear in both WebSocket stream AND webhook deliveries?"

- Our WebSocket connects successfully to: `wss://ws.edx-sandbox.rapidsos.com/v1`
- Using same Alerts-Egress credentials
- Subscribed to all alert event types
- Not receiving any events

## 📊 What We Have Working

1. ✅ OAuth authentication with Alerts-Egress credentials
2. ✅ Webhook subscription creation/management
3. ✅ WebSocket client connection
4. ✅ Webhook endpoint ready and logging: `webhooks/rapidsos_webhook.php`
5. ✅ Complete data processing pipeline to CAD system

## 🚨 What's Not Working

1. ❌ No webhook deliveries to our endpoint (zero logs)
2. ❌ No WebSocket events received
3. ❌ Demo alerts not reaching our systems

## 🔧 Live Testing During Call

We can run these monitors in real-time:

```bash
# Monitor webhook endpoint
tail -f logs/webhook_debug.log

# Monitor WebSocket client
php monitor_websocket.php

# Monitor authentication
tail -f logs/rapidsos_auth.log
```

Then create a demo alert and watch where it goes!

## 💡 Hypothesis

**Subscription #1660551** (the one pointing to RapidSOS's own URL) might be:

1. A default/test subscription created by RapidSOS
2. Catching all demo alerts before they reach our subscriptions
3. Need to be deleted or deprioritized
4. An integration receiver we should be using differently

## ✅ Confirmation Needed

Please confirm:

1. Which webhook subscription ID should receive demo alerts?
2. Should we delete subscription #1660551?
3. Is there a priority/routing order for multiple subscriptions?
4. Do demo alerts require special configuration beyond standard subscriptions?

---

**Bottom Line**: We have all infrastructure ready. We just need to understand the routing configuration for demo alerts to reach our webhook endpoint and/or WebSocket connection.
