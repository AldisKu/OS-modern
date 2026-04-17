# iPad Broker Registration Issue - Root Cause Analysis

## The Real Problem (Not What v25 Fixed)

### Symptom
- iPad A shows `broker<N>` (correct POS registration)
- iPad B shows `OK` (stuck as unknown)
- Customer display doesn't recognize iPad B as a POS

### Why v25 Didn't Fix It

v25 attempted to fix a race condition by separating registration phases. However, the real issue is **different and more fundamental**.

## Actual Root Cause

### The Sequence of Events

1. **iPad connects to broker**
   - Broker assigns `id=1` (or whatever)
   - Broker sends `HELLO` message

2. **iPad registers as "unknown"**
   - iPad sends: `{ type: "REGISTER", role: "unknown", deviceId: "..." }`
   - Broker updates: `ws.meta.role = "unknown"`
   - Broker sends back: `{ type: "REGISTERED", id: 1, list: getPosList(), ... }`
   - **Problem**: `getPosList()` only returns clients with `role === "pos"`
   - Since iPad is still `role: "unknown"`, the list is empty or doesn't include the iPad

3. **iPad logs in and registers as "pos"**
   - iPad sends: `{ type: "REGISTER", role: "pos", userId: "...", userName: "..." }`
   - Broker updates: `ws.meta.role = "pos"`
   - Broker sends back: `{ type: "REGISTERED", id: 1, list: getPosList(), ... }`
   - **Now** the list includes the iPad

4. **Customer display receives POS_LIST**
   - If it received the list when iPad was "unknown", it saw an empty list
   - If it received the list when iPad was "pos", it sees the iPad

### Why It Works on Mac but Not iPad

**Mac Safari**: WebSocket events are reliable and fast
- Both registrations happen quickly
- Customer display gets the POS_LIST after iPad is already "pos"
- Everything works

**iPad Safari**: WebSocket events can be delayed or flaky
- First registration might complete before second one starts
- Customer display might receive POS_LIST while iPad is still "unknown"
- Customer display shows "Keine Kasse online" (no POS online)
- iPad shows `OK` because it received the REGISTERED message with `id`
- But customer display doesn't see it as a POS

### The Timing Issue

The problem is **not** a race condition in the iPad's registration code. The problem is:

1. **Customer display connects and requests POS_LIST**
2. **Broker sends POS_LIST with current POS clients**
3. **If iPad hasn't registered as "pos" yet, it's not in the list**
4. **Customer display shows "Keine Kasse online"**
5. **iPad later registers as "pos"**
6. **But customer display never gets updated with the new POS_LIST**

## Why the Customer Display Doesn't Update

Looking at `customer.js`:

```javascript
ws.onmessage = (evt) => {
  let msg = null;
  try { msg = JSON.parse(evt.data); } catch (_) { return; }
  if (!msg) return;
  if (msg.type === "POS_LIST") {
    handlePosList(msg.list || []);
    return;
  }
  if (msg.type === "REGISTERED" && msg.list) {
    handlePosList(msg.list || []);
    return;
  }
  // ...
};
```

The customer display only updates the POS list when it receives:
1. A `POS_LIST` message
2. A `REGISTERED` message with a `list` field

Looking at the broker code:

```javascript
if (msg.type === "REGISTER") {
  ws.meta.role = msg.role || "unknown";
  // ...
  ws.send(JSON.stringify({ type: "REGISTERED", id: ws.meta.id, list: getPosList(), ts: Date.now() }));
  sendPosListToDisplays();
  return;
}
```

The broker:
1. Sends `REGISTERED` to the registering client (with the current POS list)
2. Calls `sendPosListToDisplays()` to notify all display clients

**The issue**: `sendPosListToDisplays()` sends the POS_LIST to display clients, but only if they're already subscribed to a specific POS. If the display hasn't subscribed yet, it won't receive the update!

Looking at `sendPosListToDisplays()`:

```javascript
function sendPosListToDisplays() {
  const list = getPosList();
  const payload = JSON.stringify({ type: "POS_LIST", list, ts: Date.now() });
  for (const ws of clients) {
    if (ws.readyState !== ws.OPEN) continue;
    if (!ws.meta || ws.meta.role !== "display") continue;
    if (ws.meta.targetPosId !== posId) continue;  // <-- PROBLEM!
    ws.send(payload);
  }
}
```

Wait, that's not right. Let me re-read it... Actually, `sendPosListToDisplays()` doesn't check `targetPosId`. It just sends to all display clients. So that's not the issue.

## The Real Issue: Timing of POS_LIST Updates

The actual problem is:

1. **Customer display connects** → sends `REGISTER` as "display"
2. **Broker sends `REGISTERED` with current POS list** → but no POS are online yet
3. **Customer display shows "Keine Kasse online"**
4. **iPad connects and registers as "unknown"** → broker sends `REGISTERED` to iPad
5. **Broker calls `sendPosListToDisplays()`** → but iPad is still "unknown", not in the list
6. **iPad registers as "pos"** → broker sends `REGISTERED` to iPad
7. **Broker calls `sendPosListToDisplays()`** → now iPad is in the list, display should get it

But on iPad Safari, step 5 might happen before step 6 completes, or the messages might arrive out of order.

## The Real Fix Needed

The issue is that the broker needs to:

1. **Send POS_LIST to all display clients whenever a POS registers** (not just when it's already "pos")
2. **Or: Send POS_LIST to all display clients whenever the POS list changes**
3. **Or: Have display clients periodically request the POS_LIST**

Currently, the broker only sends POS_LIST when:
- A display client registers
- A POS client registers (via `sendPosListToDisplays()`)

But `sendPosListToDisplays()` is called after the role is updated, so it should work...

Let me check if there's a bug in `sendPosListToDisplays()`:

```javascript
function sendPosListToDisplays() {
  const list = getPosList();
  const payload = JSON.stringify({ type: "POS_LIST", list, ts: Date.now() });
  for (const ws of clients) {
    if (ws.readyState !== ws.OPEN) continue;
    if (!ws.meta || ws.meta.role !== "display") continue;
    if (ws.meta.targetPosId !== posId) continue;  // <-- THIS LINE!
    ws.send(payload);
  }
}
```

**FOUND IT!** The line `if (ws.meta.targetPosId !== posId) continue;` is checking `posId`, but `posId` is not defined in this function! This is a bug!

This means `sendPosListToDisplays()` is never sending the POS_LIST to display clients because `posId` is undefined, so the condition `ws.meta.targetPosId !== posId` is always true (unless `targetPosId` is also undefined).

## The Fix

The `sendPosListToDisplays()` function should send to ALL display clients, not filter by `targetPosId`:

```javascript
function sendPosListToDisplays() {
  const list = getPosList();
  const payload = JSON.stringify({ type: "POS_LIST", list, ts: Date.now() });
  for (const ws of clients) {
    if (ws.readyState !== ws.OPEN) continue;
    if (!ws.meta || ws.meta.role !== "display") continue;
    // Remove the targetPosId check - send to all displays
    ws.send(payload);
  }
}
```

This way, whenever a POS registers or changes role, all display clients get the updated POS list.
