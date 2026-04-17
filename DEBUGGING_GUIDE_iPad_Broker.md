# Debugging Guide: iPad Broker Registration Issue

## Current Status

- **v25**: Added registration state tracking and debug logging to POS app
- **Broker logging**: Added detailed logging to see POS_LIST size at each step
- **Issue**: Still not resolved - need to gather actual logs to diagnose

## How to Debug

### Step 1: Check Broker Logs

Deploy the latest broker code and watch the logs while testing:

```bash
# SSH to server
ssh user@server

# Watch broker logs in real-time
sudo journalctl -u ordersprinter-broker -f

# Or check recent logs
sudo journalctl -u ordersprinter-broker -n 100
```

### Step 2: Expected Log Sequence (Working Case - Mac)

When Mac Safari connects and registers:

```
CONNECT id=1 remote=192.168.1.100 origin=http://192.168.1.50:8080
REGISTER id=1 role=unknown deviceId=POS-ABC1 user= remote=192.168.1.100 origin=http://192.168.1.50:8080 | POS_LIST: 0
SEND_POS_LIST to displays: 0 POS clients
REGISTER id=1 role=pos deviceId=POS-ABC1 user=John remote=192.168.1.100 origin=http://192.168.1.50:8080 | POS_LIST: 1
SEND_POS_LIST to displays: 1 POS clients
```

When customer display connects:

```
CONNECT id=2 remote=192.168.1.101 origin=http://192.168.1.50:8080
REGISTER id=2 role=display deviceId= user= remote=192.168.1.101 origin=http://192.168.1.50:8080 | POS_LIST: 1
SEND_POS_LIST to displays: 1 POS clients
REQUEST_POS_LIST from id=2 | POS_LIST: 1
```

### Step 3: Expected Log Sequence (Broken Case - iPad)

If iPad shows `OK` instead of `broker<N>`, the logs might show:

**Scenario A: iPad registers as "unknown" but never upgrades to "pos"**
```
CONNECT id=1 remote=192.168.1.102 origin=http://192.168.1.50:8080
REGISTER id=1 role=unknown deviceId=POS-XYZ2 user= remote=192.168.1.102 origin=http://192.168.1.50:8080 | POS_LIST: 0
SEND_POS_LIST to displays: 0 POS clients
[No second REGISTER as "pos" - this is the problem!]
```

**Scenario B: iPad registers but customer display doesn't see it**
```
CONNECT id=1 remote=192.168.1.102 origin=http://192.168.1.50:8080
REGISTER id=1 role=unknown deviceId=POS-XYZ2 user= remote=192.168.1.102 origin=http://192.168.1.50:8080 | POS_LIST: 0
SEND_POS_LIST to displays: 0 POS clients
REGISTER id=1 role=pos deviceId=POS-XYZ2 user=John remote=192.168.1.102 origin=http://192.168.1.50:8080 | POS_LIST: 1
SEND_POS_LIST to displays: 1 POS clients
[But customer display still shows "Keine Kasse online"]
```

### Step 4: Check iPad Browser Console

On the iPad, open Safari Web Inspector:

1. **On Mac**: Safari → Develop → [iPad Name] → [App]
2. **On iPad**: Settings → Safari → Advanced → Web Inspector (enable)
3. Look for `[BROKER]` logs showing registration sequence

Expected sequence:
```
[BROKER] Socket opened, registering as unknown
[BROKER] Sending REGISTER unknown: {type: "REGISTER", role: "unknown", deviceId: "POS-XYZ2"}
[BROKER] REGISTERED received: id=1, label=broker1
[BROKER] bootstrap complete, registering as POS
[BROKER] Sending REGISTER pos: {type: "REGISTER", role: "pos", deviceId: "POS-XYZ2", userId: "123", userName: "John"}
[BROKER] REGISTERED received: id=1, label=broker1
```

### Step 5: Check Customer Display Browser Console

On the customer display, open browser console and look for:

```
POS_LIST received: [{id: 1, deviceId: "POS-XYZ2", userName: "John"}]
```

Or if it's showing "Keine Kasse online":

```
POS_LIST received: []
```

### Step 6: Check Broker Health Endpoint

From any machine on the network:

```bash
curl http://server-ip:3077/health
# Should return: {"status":"OK","clients":2}

curl http://server-ip:3077/clients
# Should return list of all connected clients with their roles
```

Example output:
```json
{
  "status": "OK",
  "clients": [
    {
      "id": 1,
      "role": "pos",
      "deviceId": "POS-XYZ2",
      "userId": "123",
      "userName": "John",
      "targetPosId": null,
      "origin": "http://192.168.1.50:8080",
      "remote": "192.168.1.102"
    },
    {
      "id": 2,
      "role": "display",
      "deviceId": "",
      "userId": "",
      "userName": "",
      "targetPosId": 1,
      "origin": "http://192.168.1.50:8080",
      "remote": "192.168.1.101"
    }
  ],
  "ts": 1713456789000
}
```

## Possible Issues and Solutions

### Issue 1: iPad Never Registers as "pos"

**Symptom**: Broker logs show only `role=unknown`, never `role=pos`

**Cause**: iPad login failed or bootstrap didn't complete

**Solution**:
1. Check iPad browser console for login errors
2. Check if `state.user` is set after login
3. Verify PHP API is returning correct bootstrap data

### Issue 2: iPad Registers as "pos" but Customer Display Doesn't See It

**Symptom**: Broker logs show `role=pos` and `POS_LIST: 1`, but customer display shows "Keine Kasse online"

**Cause**: Customer display connected before iPad registered, and didn't receive the update

**Solution**:
1. Check if customer display received `SEND_POS_LIST` message
2. Check if customer display's `onmessage` handler is processing `POS_LIST` messages
3. Try refreshing customer display after iPad registers

### Issue 3: WebSocket Connection Issues

**Symptom**: Broker logs show `CONNECT` but no `REGISTER` message

**Cause**: WebSocket connection established but registration message not sent

**Solution**:
1. Check iPad browser console for `[BROKER]` logs
2. Check if socket is actually OPEN before sending REGISTER
3. Check for mixed content warnings (HTTP vs HTTPS)

### Issue 4: Network/Firewall Issues

**Symptom**: iPad can't connect to broker at all

**Cause**: Firewall blocking port 3077, or broker URL is wrong

**Solution**:
1. Check broker URL in `modernapi.php?cmd=config`
2. Verify port 3077 is open on server
3. Try connecting from iPad to `http://server-ip:3077/health`

## Testing Checklist

- [ ] Deploy latest broker code with logging
- [ ] Deploy latest POS app (v25) with debug logging
- [ ] Test on Mac Safari (should work)
- [ ] Test on iPad Safari (check if issue reproduces)
- [ ] Collect broker logs during iPad test
- [ ] Collect iPad browser console logs
- [ ] Check broker `/clients` endpoint
- [ ] Check customer display browser console
- [ ] Verify broker URL is correct (not localhost)
- [ ] Verify port 3077 is accessible from iPad

## Next Steps

1. **Gather logs** from the problematic iPad test
2. **Analyze logs** to identify where the sequence breaks
3. **Implement fix** based on what the logs reveal
4. **Test fix** on iPad to verify it works

The logs will tell us exactly what's happening and where the problem is.
