# Double Order Fix - V36

## Problem
On slow networks, users could click the "Arbeitsbon" (Print Work Slip) or "Bestellung beenden" (End Order) buttons multiple times before the request completed, resulting in duplicate orders being submitted.

## Solution
Implemented button disabling mechanism that prevents multiple submissions:

### Changes Made

1. **Added `orderSending` flag to state**
   - Tracks whether an order submission is in progress
   - Prevents re-entry into `sendOrder()` function

2. **Created `disableOrderButtons()` function**
   - Disables "Arbeitsbon" and "Bestellung beenden" buttons
   - Disables "Bestellen" buttons in product modal and price modal
   - Visual feedback: opacity 0.5, cursor changes to "not-allowed"

3. **Created `enableOrderButtons()` function**
   - Re-enables all order-related buttons
   - Restores normal opacity and cursor

4. **Updated `sendOrder()` function**
   - Checks `orderSending` flag at start - returns early if already sending
   - Sets flag to `true` and calls `disableOrderButtons()` before API call
   - On error: re-enables buttons and sets flag to `false` (allows retry)
   - On success: clears flag (buttons stay disabled until screen changes)

### Files Modified
- `modern/app.js` - Added flag, functions, and logic
- `modern/index.html` - Updated version from v35 to v36
- `modern/customer.html` - Updated version from v35 to v36

### Version Bump
- `APP_VERSION`: 35 → 36
- All HTML script/stylesheet references: v35 → v36

## Testing
1. Open order screen
2. Add products to cart
3. Click "Arbeitsbon" or "Bestellung beenden" button
4. Button should be disabled (grayed out, cursor changes)
5. Wait for order to complete
6. Button should be re-enabled (or screen changes to start)
7. Try clicking multiple times rapidly - only one order should be submitted

## Behavior
- **Normal network**: Buttons disabled for ~1-2 seconds during submission
- **Slow network**: Buttons remain disabled until order completes
- **Error**: Buttons re-enabled so user can retry
- **Success**: Buttons stay disabled as screen transitions to start screen

## Backward Compatibility
No breaking changes. This is a pure UX improvement that prevents accidental duplicate orders.
