# iPad Broker Registration Fix (v25)

## Problem Analysis

One iPad showed `broker<N>` (correct POS registration) while another iPad showed only `OK` (stuck as `role: "unknown"`). This indicated a race condition in the broker registration flow.

### Root Cause

The issue was a **timing race condition** in the registration sequence:

1. When the WebSocket opened, both `registerBrokerUnknown()` and `registerBrokerClient()` were called **synchronously** in quick succession
2. On iPad Safari, the WebSocket might not be fully ready when `registerBrokerClient()` tried to send
3. The `try/catch` silently failed, and a retry was scheduled for 1 second later
4. Meanwhile, the broker had already responded to the first `registerBrokerUnknown()` message
5. The broker assigned an `id` to the first registration, but the client's label logic didn't update correctly

### Why It Affected iPad More Than Mac

- **Mac Safari**: WebSocket event delivery is reliable; both registrations succeed quickly
- **iPad Safari**: WebSocket event delivery can be flaky; the socket might not be fully ready when the second registration is attempted

## Solution (v25)

### Key Changes

1. **Separate Registration Phases**
   - `registerBrokerUnknown()` is called when the socket opens (role: "unknown")
   - `registerBrokerClient()` is called **only after successful login** (role: "pos")
   - This eliminates the race condition by ensuring they don't happen simultaneously

2. **Registration State Tracking**
   - Added `brokerRegisteredAsUnknown` flag to track if "unknown" registration was sent
   - Added `brokerRegisteredAsPos` flag to track if "pos" registration was sent
   - Prevents duplicate registrations

3. **Explicit Logging**
   - Added `DEBUG_BROKER` flag for conditional logging
   - Console logs show the exact sequence of registration attempts and responses
   - Helps diagnose registration issues on problematic devices

4. **Improved Retry Logic**
   - `registerBrokerClient()` checks socket state before attempting to send
   - If socket not ready, schedules a retry after 1 second
   - Retry timer is properly cleaned up on reconnect

5. **State Reset on Reconnect**
   - When broker disconnects, registration state is reset
   - Allows clean re-registration on the next connection
   - Prevents stale state from interfering with new connections

### Code Changes

**File: `modern/app.js`**

- Increment `APP_VERSION` to 25
- Add `DEBUG_BROKER` flag for logging
- Add registration state fields to `state` object:
  - `brokerRegisteredAsUnknown`
  - `brokerRegisteredAsPos`
  - `brokerRegistrationRetryTimer`
- Update `initBroker()`:
  - Only call `registerBrokerUnknown()` on socket open
  - Only call `registerBrokerClient()` if user already logged in
  - Add debug logging
- Update `registerBrokerUnknown()`:
  - Add state tracking
  - Add debug logging
  - Check socket state before sending
- Update `registerBrokerClient()`:
  - Add state tracking
  - Add debug logging
  - Improve retry logic with explicit state checks
- Update `bootstrap()`:
  - Call `registerBrokerClient()` after bootstrap completes
  - Ensures registration happens after login
- Update `resetClientState()`:
  - Reset `brokerRegisteredAsPos` flag
  - Clear registration retry timer
- Update `scheduleBrokerReconnect()`:
  - Reset both registration flags
  - Clear registration retry timer
  - Add debug logging

## Testing

### How to Verify the Fix

1. **On the problematic iPad**:
   - Open the browser's Web Inspector (Safari → Develop → [Device] → [App])
   - Look for console logs starting with `[BROKER]`
   - Expected sequence:
     ```
     [BROKER] Socket opened, registering as unknown
     [BROKER] Sending REGISTER unknown: {...}
     [BROKER] User already logged in, registering as POS
     [BROKER] Sending REGISTER pos: {...}
     [BROKER] REGISTERED received: id=<N>, label=broker<N>
     ```

2. **Check the broker status**:
   - Should show `broker<N>` instead of `OK`
   - Customer display should recognize the iPad as a POS

3. **Check the broker debug endpoint**:
   ```bash
   curl http://<SERVER-IP>:3077/clients
   ```
   - Should show the iPad with `role: "pos"` and a valid `id`

### Debugging

If the issue persists:

1. **Check browser console** for `[BROKER]` logs
2. **Check broker logs**:
   ```bash
   sudo journalctl -u ordersprinter-broker -n 50 | grep -i register
   ```
3. **Check network conditions**:
   - iPad might have poor WiFi signal
   - Try moving closer to the router
   - Try restarting the WiFi connection

## Deployment

1. Deploy v25 to production
2. Clear browser cache on the problematic iPad (or use incognito mode)
3. Test the registration flow
4. Monitor broker logs for any registration issues

## Future Improvements

- Consider adding a visual indicator in the UI showing registration status
- Add a "Reconnect to Broker" button for manual troubleshooting
- Implement exponential backoff for registration retries
- Add metrics/telemetry for registration success rates
