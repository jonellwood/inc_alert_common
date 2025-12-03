# URGENT: Demo Alerts Not Reaching Webhook Endpoint

## Current Status (Updated - Oct 15, 2025)

### ✅ What's Working

- Webhook endpoint accessible and logging: `my.berkeleycountysc.gov/redfive/webhooks/rapidsos_webhook.php`
- 4 clean webhook subscriptions pointing to our endpoint (IDs: 1664965, 1664966, 1672899, 1672900)
- Correct credentials: Alerts-Egress-Pre-Product (`A5sa18wIxv3P2tb7OAGGmdcrJgf63IOM`)
- Deleted rogue subscription #1660551 that pointed to RapidSOS internal URL

### ❌ What's NOT Working

- **ZERO webhook deliveries from RapidSOS**
- Webhook debug log shows NO requests from RapidSOS servers
- Only test requests from our internal testing tool appear in logs
- Demo alerts created in portal not triggering any webhooks

## 🚨 Critical Questions for RapidSOS

### 1. **How Do Demo Alerts Work?**

**Q:** "When we create a demo alert in the RapidSOS sandbox portal, what EXACTLY should happen?"

- Should it automatically trigger webhooks to active subscriptions?
- Do we need to configure something else first?
- Are demo alerts account-specific? (Do they only go to certain credentials?)

### 2. **Webhook Subscription Verification**

**Q:** "Can you verify our webhook subscriptions are configured correctly?"

- We have 4 active subscriptions
- All point to: `https://my.berkeleycountysc.gov/redfive/webhooks/rapidsos_webhook.php`
- All use Alerts-Egress credentials
- **WHY aren't they receiving any webhooks?**

### 3. **Network/Firewall Check**

**Q:** "Can RapidSOS servers reach our webhook endpoint?"

- Our endpoint is publicly accessible (confirmed via browser)
- Can you try sending a test webhook to our URL while we monitor logs?
- Any IP ranges we should whitelist?

### 4. **Account/Environment Verification**

**Q:** "Are demo alerts in the portal associated with our account/credentials?"

- Portal login: <gvalganon+berkeley@rapidsos.com>
- Agency: Berkeley_Alerts (ID_BerkeleyAlerts)
- Credentials: Alerts-Egress-Pre-Product
- **Do these all need to match for webhooks to work?**

### 5. **Webhook Secret/Validation**

**Q:** "Is there a webhook secret or validation URL we need to configure?"

- Some webhook systems require:
  - Initial validation/handshake
  - Shared secret for signatures
  - Endpoint verification
- Do we need to set this up in the portal?

### 6. **Alternative: WebSocket for Demo Alerts**

**Q:** "Should we use WebSocket instead of webhooks for demo alerts?"

- Our WebSocket client connects successfully
- Using same Alerts-Egress credentials
- Subscribed to all alert event types
- **Also receiving ZERO events** - why?

## 🧪 Live Test Proposal

During the call, let's do this:

1. **You create a demo alert** in your portal
2. **We monitor in real-time:**

   ```bash
   # Terminal 1: Webhook logs
   tail -f logs/webhook_debug.log
   
   # Terminal 2: WebSocket logs  
   php monitor_websocket.php
   
   # Terminal 3: RapidSOS auth logs
   tail -f logs/rapidsos_auth.log
   ```

3. **Tell us exactly what you did** to create the demo alert
4. **We see immediately** if anything arrives

## 📊 Evidence We Have

### Webhook Endpoint Logs (webhook_debug.log)

```json
// Only shows our OWN test requests, nothing from RapidSOS
{"timestamp":"2025-10-14 13:10:47","action":"incoming_request",
 "user_agent":"Berkeley-County-Webhook-Tester/1.0"}
```

### Active Subscriptions

```
ID: 1664965 - Created: 2025-10-01 16:52:44
ID: 1664966 - Created: 2025-10-01 16:53:07  
ID: 1672899 - Created: 2025-10-14 13:42:49
ID: 1672900 - Created: 2025-10-14 13:42:59
```

All point to our endpoint, all have 6 event types subscribed.

## 💡 Possible Root Causes

1. **Demo alerts require different setup than we think**
   - Maybe they're not meant to trigger webhooks?
   - Only for portal UI testing?

2. **Account/credential mismatch**
   - Demo portal using different account than our webhooks?
   - Subscriptions not linked to demo tool?

3. **Missing configuration step**
   - Something in UNITE portal we haven't configured?
   - Webhook delivery disabled by default?

4. **Sandbox environment limitation**
   - Demo alerts don't work in sandbox?
   - Need production environment?

5. **RapidSOS can't reach our endpoint**
   - Network/firewall blocking their servers?
   - Need to whitelist IPs?

## ✅ What We Need from You

1. **Send a test webhook** to our endpoint while we watch logs
2. **Verify our subscriptions** are configured correctly in your system
3. **Explain the demo alert workflow** - step by step what should happen
4. **Check if demo alerts are linked** to our credentials/account
5. **Tell us if we're missing any configuration** in the portal

---

**Bottom Line**: We have all the infrastructure. Endpoint is accessible. Subscriptions are active. But ZERO webhooks are being delivered. We need to understand WHY.
