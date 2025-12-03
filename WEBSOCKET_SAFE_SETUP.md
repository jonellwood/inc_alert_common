# Safe WebSocket Setup (No System Changes Required)

## ✅ Recommended: Cron Job Method (SAFEST)

This runs in **user space only** - no system services, no risk to other apps!

### Step 1: Upload the keepalive script

```bash
# On your server
cd /var/www/acotocad/redfive
chmod +x websocket_keepalive.sh
```

### Step 2: Add to your user's crontab (NOT system crontab)

```bash
# Edit your crontab
crontab -e

# Add this line to check every minute:
* * * * * /var/www/acotocad/redfive/websocket_keepalive.sh
```

**That's it!** No sudo, no system changes, no risk.

### How it works

- Cron runs the script every minute
- Script checks if WebSocket is running
- If not running, starts it automatically
- If already running, does nothing
- Logs everything to `logs/websocket_keepalive.log`

### Managing it

```bash
# Check if it's running
cat /var/www/acotocad/redfive/logs/websocket.pid
ps -p $(cat /var/www/acotocad/redfive/logs/websocket.pid)

# View logs
tail -f /var/www/acotocad/redfive/logs/websocket_client.log
tail -f /var/www/acotocad/redfive/logs/websocket_keepalive.log

# Stop it manually (it will restart in 1 minute unless you remove from cron)
kill $(cat /var/www/acotocad/redfive/logs/websocket.pid)

# Stop it permanently
crontab -e  # Remove the line you added
kill $(cat /var/www/acotocad/redfive/logs/websocket.pid)
```

---

## Alternative: Manual Screen Session (For Testing)

If you just want to test first:

```bash
# Start a screen session
screen -S rapidsos

# Run the client
cd /var/www/acotocad/redfive
php websocket_client.php

# Detach: Press Ctrl+A, then D

# Re-attach later
screen -r rapidsos

# Kill it
screen -X -S rapidsos quit
```

---

## Why This is Safe

✅ **No sudo required** - Runs as your user
✅ **No system files modified** - Everything in your app folder
✅ **No services installed** - Just a cron job
✅ **Easy to remove** - Delete cron line, kill process
✅ **Won't affect other apps** - Completely isolated
✅ **Server reboot safe** - Cron starts it automatically after reboot

---

## Comparison

| Method | Safety | Auto-Restart | Survives Reboot | Needs Sudo |
|--------|--------|--------------|-----------------|------------|
| **Cron + Keepalive** | ✅ Very Safe | ✅ Yes (1 min) | ✅ Yes | ❌ No |
| systemd Service | ⚠️ System-level | ✅ Yes (instant) | ✅ Yes | ✅ Yes |
| Screen Session | ✅ Safe | ❌ No | ❌ No | ❌ No |
| nohup | ✅ Safe | ❌ No | ❌ No | ❌ No |

---

## Recommended Approach

1. **Test with screen first** (2 minutes to set up)
2. **If it works, switch to cron** (permanent, safe solution)
3. **Never need systemd** (avoid system changes on shared server)

The cron method gives you 99% of systemd's benefits with ZERO risk! 🎯
