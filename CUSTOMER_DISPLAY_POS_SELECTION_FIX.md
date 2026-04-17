# Customer Display POS Selection Fix

## Problem

When the customer display was connected to a single POS and another POS came online, the display would automatically switch to the POS selection screen, interrupting the current display.

### Scenario
1. Customer display starts → finds 1 POS online → connects to it ✓
2. Another POS comes online → customer display switches to selection screen ✗
3. User has to manually select the POS again

This was disruptive and not user-friendly.

## Solution

### Logic Changes

**Before:**
- Whenever POS list changed, show selection screen if multiple POS available

**After:**
- If already connected to a POS, stay connected (don't interrupt)
- Only show selection screen if:
  1. No POS are online → show "Keine Kasse online"
  2. Multiple POS available AND not yet connected → show selection
  3. Connected POS went offline → show selection

### UI Changes

**Before:**
- Top of page showed "customer.js" (filename)

**After:**
- Top of page shows connected POS ID (e.g., "broker1")
- POS ID is clickable to open selection menu
- Allows manual POS switching without interrupting display

## Implementation Details

### Modified Functions

**`showHash()`**
- Changed from showing filename to showing connected POS ID
- Made POS ID clickable with `onclick = openPosSelector`
- Falls back to filename if not connected

**`handlePosList(list)`**
- Added check: if already connected, don't show selection screen
- Verify connected POS is still in the list
- Only show selection if:
  - No POS online
  - Multiple POS AND not connected
  - Connected POS went offline

**`subscribeToPos(posId)`**
- Added call to `showHash()` after subscribing
- Updates the display to show the connected POS ID

**`openPosSelector()`** (new)
- Requests fresh POS list from broker
- Allows user to manually switch POS by clicking the POS ID

## User Experience

### Scenario 1: Single POS (Normal Case)
1. Display starts → finds 1 POS → connects automatically
2. Another POS comes online → display stays connected (no interruption)
3. User can click "broker1" at top to switch POS if desired

### Scenario 2: Multiple POS (Manual Selection)
1. Display starts → finds 2+ POS → shows selection menu
2. User selects a POS → display connects
3. Another POS comes online → display stays connected
4. User can click "broker1" at top to switch to different POS

### Scenario 3: POS Goes Offline
1. Display connected to POS 1
2. POS 1 goes offline → display shows selection menu
3. User selects POS 2 → display connects to POS 2

## Testing

### Test Case 1: Single POS
- [ ] Start customer display with 1 POS online
- [ ] Verify it connects automatically
- [ ] Bring another POS online
- [ ] Verify display stays connected (no interruption)
- [ ] Verify top shows "broker1" (or appropriate ID)

### Test Case 2: Multiple POS
- [ ] Start customer display with 2+ POS online
- [ ] Verify selection menu appears
- [ ] Select a POS
- [ ] Verify display connects
- [ ] Bring another POS online
- [ ] Verify display stays connected

### Test Case 3: Manual Switching
- [ ] Display connected to POS 1
- [ ] Click "broker1" at top
- [ ] Verify selection menu appears
- [ ] Select POS 2
- [ ] Verify display switches to POS 2

### Test Case 4: POS Goes Offline
- [ ] Display connected to POS 1
- [ ] Stop POS 1
- [ ] Verify display shows selection menu
- [ ] Verify "Keine Kasse online" if no other POS available

## Code Changes

**File:** `modern/customer.js`

- Modified `showHash()` function
- Modified `handlePosList()` function
- Modified `subscribeToPos()` function
- Added `openPosSelector()` function

## Deployment

1. Deploy latest customer.js
2. Clear browser cache on customer display devices
3. Test the scenarios above
4. Monitor for any issues

## Benefits

- ✓ No interruption when new POS comes online
- ✓ Clear indication of which POS is connected
- ✓ Easy manual switching via clickable POS ID
- ✓ Better user experience
- ✓ Reduced confusion about which POS is active
