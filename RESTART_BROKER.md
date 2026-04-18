# Restart Broker Service (v33)

## Issue
The broker service is still running the old code (v32). It needs to be restarted to load the new v33 code with stable client name support.

## Steps to Restart Broker

### On the Server (192.168.0.33)

1. **SSH to server**
```bash
ssh root@192.168.0.33
```

2. **Restart broker service**
```bash
sudo systemctl restart ordersprinter-broker
```

3. **Verify broker is running**
```bash
sudo systemctl status ordersprinter-broker
```

4. **Check broker logs**
```bash
sudo journalctl -u ordersprinter-broker -n 50 -f
```

5. **Test broker health**
```bash
curl http://127.0.0.1:3077/health
```

## What Changed in v33

The broker now:
- Tracks client names in a `clientsByName` map
- Sends `clientName` in the REGISTERED message
- Includes `clientName` in the POS_LIST sent to displays
- Routes SUBSCRIBE messages using client names

## Expected Behavior After Restart

1. **POS App**
   - Shows "Client: POS-XXXXXX" in status bar (instead of "Broker: OK")
   - Generates stable client name on first login
   - Stores client name in localStorage

2. **Customer Display**
   - Receives POS_LIST with client names
   - Shows "Client: POS-XXXXXX" when connected
   - Stores client name in localStorage

3. **Broker Logs**
   - Should show: `REGISTER id=X role=pos clientName=POS-XXXXXX ...`
   - Should show: `SEND_POS_LIST to displays: 1 POS clients`

## Troubleshooting

If broker doesn't start:
1. Check logs: `sudo journalctl -u ordersprinter-broker -n 100`
2. Verify Node.js is installed: `node --version`
3. Check broker file exists: `ls -la /var/www/broker/server.js`
4. Check permissions: `ls -la /var/www/broker/`

If POS still shows "Broker: OK":
1. Hard refresh browser (Ctrl+Shift+R or Cmd+Shift+R)
2. Clear localStorage: Open DevTools → Application → Storage → Clear All
3. Reload page

If display still can't find POS:
1. Check broker logs for REGISTER messages
2. Verify POS_LIST is being sent to displays
3. Check display browser console for errors
