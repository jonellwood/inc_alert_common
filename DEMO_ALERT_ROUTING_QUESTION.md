# The Core Question: How Do Demo Alerts Link to Webhook Subscriptions?

## 🎯 The Fundamental Problem

We have **4 active webhook subscriptions** that are properly configured and pointing to our endpoint:

```
Subscription ID: 1664965
Subscription ID: 1664966
Subscription ID: 1672899
Subscription ID: 1672900

All → https://my.berkeleycountysc.gov/redfive/webhooks/rapidsos_webhook.php
All → Event types: alert.new, alert.status_update, alert.chat, etc.
All → Status: Active
All → Using credentials: A5sa18wIxv3P2tb7OAGGmdcrJgf63IOM
```

**BUT: When you create demo alerts in the RapidSOS sandbox portal, ZERO webhooks are delivered.**

## ❓ The Core Questions

### 1. **Account/Credential Association**

**Q:** "Are demo alerts linked to the credentials that created the webhook subscription?"

- We created subscriptions with: `A5sa18wIxv3P2tb7OAGGmdcrJgf63IOM`
- Demo portal logged in as: `gvalganon+berkeley@rapidsos.com`
- Agency: `Berkeley_Alerts` (`ID_BerkeleyAlerts`)
- **Do these all need to be explicitly linked?**

### 2. **Agency/PSAP Linking**

**Q:** "Are demo alerts tied to a specific PSAP/Agency ID?"

- Our Agency ID: `ID_BerkeleyAlerts`
- When creating demo alerts, do you select an agency?
- Do webhook subscriptions need to be "registered" to an agency?
- **Is there a step we missed to link our subscriptions to our agency?**

### 3. **Demo Alert Scope**

**Q:** "What scope/context do demo alerts operate in?"

```
Option A: Demo alerts are account-specific
  → Only go to subscriptions created by that account
  → Need to verify account linking

Option B: Demo alerts are agency-specific
  → Need to verify agency is associated with subscriptions
  → Maybe need to set agency ID on subscriptions?

Option C: Demo alerts are integration-specific
  → Only work with webhook integrations (not subscriptions)
  → We need Integration Management access

Option D: Demo alerts require explicit routing
  → There's a configuration step we haven't done
  → Maybe in UNITE portal under some menu?
```

### 4. **Subscription Metadata**

**Q:** "Is there metadata missing from our webhook subscriptions?"

Looking at our subscription payloads:

```json
{
  "id": 1664966,
  "url": "https://my.berkeleycountysc.gov/redfive/webhooks/rapidsos_webhook.php",
  "event_types": ["alert.new", "alert.status_update", ...],
  "created_time": 1759337587427,
  "last_updated_time": 1759337587427
}
```

**Is there a missing field like:**

- `agency_id`?
- `psap_id`?
- `account_id`?
- `integration_id`?
- `scope`?

### 5. **Integration vs Subscription**

**Q:** "What's the functional difference for receiving alerts?"

```
Webhook SUBSCRIPTIONS (what we have):
  - Endpoint: /v1/webhooks/subscriptions
  - We can create/manage these
  - 4 active subscriptions
  - NOT receiving demo alerts ❌

Webhook INTEGRATIONS (we can't access):
  - Endpoint: /v1/integrations
  - We get 401 Unauthorized
  - Returns webhookId, edxClientId, edxClientSecret
  - Would these receive demo alerts? ✓

Is this THE difference?
```

## 🔍 Diagnostic Evidence

### What We Know Works

1. ✅ Our webhook endpoint is accessible (tested from browser)
2. ✅ Our webhook endpoint is logging requests (tested with our own tool)
3. ✅ Webhook subscriptions are created successfully
4. ✅ OAuth authentication works
5. ✅ We can list/manage subscriptions via API

### What Definitely Doesn't Work

1. ❌ Demo alerts → No webhook deliveries to our endpoint
2. ❌ Demo alerts → No WebSocket events received
3. ❌ Integration Management API → 401 Unauthorized

### What We Need to Understand

1. ❓ How demo alerts are routed to webhook endpoints
2. ❓ What links a demo alert to a subscription
3. ❓ What we're missing in the configuration chain

## 🧪 Proposed Tests

### Test 1: Verify Subscription is "Listening"

**Can you (RapidSOS) see our subscriptions in your system?**

- Agency: `ID_BerkeleyAlerts`
- Credentials: `A5sa18wIxv3P2tb7OAGGmdcrJgf63IOM`
- Look up subscriptions in your admin panel
- Verify they're properly registered

### Test 2: Manual Webhook Delivery

**Can you manually trigger a webhook to our endpoint?**

- URL: `https://my.berkeleycountysc.gov/redfive/webhooks/rapidsos_webhook.php`
- We'll monitor: `tail -f logs/webhook_debug.log`
- Proves endpoint is reachable from RapidSOS servers
- Tests the delivery mechanism independently

### Test 3: Demo Alert with Monitoring

**Create a demo alert while we watch everything:**

1. You create demo alert in portal
2. We monitor:
   - Webhook logs
   - WebSocket connection
   - Subscription API
3. Tell us exactly what you did
4. We compare what we see vs. what should happen

### Test 4: Integration Creation (if needed)

**If subscriptions won't work for demo alerts:**

- Create a webhook integration for us, OR
- Give us the existing integration details, OR
- Grant us Integration Management API access

## 💡 Hypothesis

**Most Likely:** Demo alerts require a webhook **integration** (not just a subscription), and:

1. An integration already exists for our account
2. That integration has a `webhookId` we don't know
3. That integration generated `edxClientId` and `edxClientSecret`
4. Demo alerts go to THAT integration, not to subscriptions
5. We need you to provide those integration credentials

**Alternative:** Subscriptions CAN receive demo alerts, but:

1. There's a linking/association step we missed
2. Subscriptions need to be tied to the agency somehow
3. There's a configuration in the UNITE portal we haven't set
4. Demo alerts are account-scoped and we have an account mismatch

## ✅ What We Need From You

### Immediate Answer

**"Do demo alerts in the sandbox portal work with webhook SUBSCRIPTIONS, or do they require a webhook INTEGRATION?"**

If SUBSCRIPTIONS should work:

- What links a demo alert to a subscription?
- What are we missing in the configuration?
- Why aren't our 4 subscriptions receiving anything?

If INTEGRATION is required:

- Does one already exist for agency `ID_BerkeleyAlerts`?
- What is the `webhookId`?
- What are the `edxClientId` and `edxClientSecret`?
- Can you create one for us if it doesn't exist?

## 🎯 The Real Question

**Given:**

- Agency: `ID_BerkeleyAlerts`
- Credentials: `A5sa18wIxv3P2tb7OAGGmdcrJgf63IOM`
- 4 active webhook subscriptions
- Demo alerts created in portal

**When you create a demo alert, what determines WHERE it gets sent?**

That's what we need to understand to close this gap!

---

**This is the missing link in the entire chain.** Once we understand the routing mechanism, we can configure it correctly and start receiving demo alerts! 🚀
