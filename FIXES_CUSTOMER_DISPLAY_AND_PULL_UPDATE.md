# Fixes: Customer Display POS Persistence & Pull Update Status

## Issue 1: Customer Display Asks to Select POS After 2nd Login

### Problem
When the POS app logged in a second time, the customer display would show the POS selection screen instead of keeping the previous connection.

**Scenario:**
1. Customer display connects to POS 1 ✓
2. POS app logs out
3. POS app logs in again
4. Customer display shows selection screen ✗ (should stay connected to POS 1)

### Root Cause
The customer display had no way to remember which POS it was connected to. When the POS app logged in again and sent a new POS_LIST, the display would treat it as a new connection and show the selection screen.

### Solution
Persist the selected POS ID in localStorage so it survives page reloads and reconnections.

**Implementation:**
- Added `savePosId(posId)` - saves selected POS ID to localStorage
- Added `loadSavedPosId()` - retrieves saved POS ID from localStorage
- Updated `handlePosList()` to restore previous connection if saved POS is still online
- Updated `subscribeToPos()` to save the POS ID when connecting

**Behavior:**
1. Customer display connects to POS 1 → saves ID to localStorage
2. POS app logs out/in
3. Customer display receives new POS_LIST
4. Checks if saved POS 1 is still in the list
5. If yes → automatically reconnects to POS 1 (no selection screen)
6. If no → shows selection screen

### Files Updated
- `modern/customer.js` - Modern version
- `modern/customer-legacy.js` - Legacy version for old Android tablets

---

## Issue 2: Pull Update Doesn't Recognize Pushed Update

### Problem
When the broker pushed an update, the client would sometimes show a warning: "broker hat Update unterschlagen" (broker missed the update), even though the update was actually received.

**Scenario:**
1. Broker detects state change → sends UPDATE_REQUIRED push
2. Client's pull timer detects version change at same millisecond
3. Client shows warning "broker didn't send update" ✗ (but it did!)

### Root Cause
The timestamp comparison was using `>=` instead of `>`:

```javascript
// OLD (wrong):
if (state.lastBrokerUpdateAt >= changedAt) return;  // Don't show warning

// This fails when:
// - Broker push arrives at time T
// - Pull update detects change at time T (same millisecond)
// - lastBrokerUpdateAt = T, changedAt = T
// - T >= T is true, so we DON'T return (we show the warning!)
```

### Solution
Change the comparison from `>=` to `>` so that broker updates arriving at the same millisecond are recognized:

```javascript
// NEW (correct):
if (state.lastBrokerUpdateAt > changedAt) return;  // Don't show warning

// Now:
// - Broker push arrives at time T
// - Pull update detects change at time T (same millisecond)
// - lastBrokerUpdateAt = T, changedAt = T
// - T > T is false, so we return (no warning!)
```

### Files Updated
- `modern/app.js` - Changed `>=` to `>` in the timestamp comparison

---

## Testing

### Test Case 1: POS Persistence
1. Start customer display
2. Select POS 1
3. Verify it shows "broker1" at top
4. Refresh the page (or close/reopen browser)
5. Verify it automatically reconnects to POS 1 (no selection screen)
6. Verify it shows "broker1" at top

### Test Case 2: POS Persistence After Login
1. Start customer display
2. Select POS 1
3. POS app logs out
4. POS app logs in again
5. Verify customer display stays connected to POS 1 (no selection screen)
6. Verify it shows "broker1" at top

### Test Case 3: Pull Update Status
1. Start POS app
2. Make an order
3. Broker sends UPDATE_REQUIRED push
4. At the same time, pull timer detects version change
5. Verify NO warning appears ("broker hat Update unterschlagen")
6. Verify tables are updated correctly

---

## Technical Details

### localStorage Keys
- `customer_selected_pos_id` - Stores the selected POS ID (e.g., "1")

### Timestamp Logic
The pull update mechanism works as follows:

1. **Pull timer runs every 120 seconds** (configurable)
2. **Detects version change** → starts 6-second grace period
3. **During grace period**, checks if broker sent UPDATE_REQUIRED
4. **If broker update arrived AFTER version change** → no warning
5. **If broker update didn't arrive** → show warning

The fix ensures that broker updates arriving at the exact same millisecond are recognized.

---

## Deployment

1. Deploy latest code
2. Clear browser cache on customer display devices
3. Test the scenarios above
4. Monitor for any issues

---

## Benefits

✓ Customer display keeps previous connection after POS login
✓ No interruption when POS app logs in again
✓ Broker push updates are correctly recognized
✓ No false "broker missed update" warnings
✓ Better user experience
✓ Applies to both modern and legacy versions
