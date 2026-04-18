# Stable Client Name System (v33)

## Problem Solved
When the POS app is backgrounded and returns, the WebSocket reconnects with a **NEW broker ID**. The customer display still has the **OLD broker ID**, causing the connection to be lost. This is a critical issue because the user can't control when the app is backgrounded.

## Solution: Stable Client Name System

### How It Works

#### 1. POS App (app.js)
- **Generates** a stable client name on first login: `POS-XXXXXX` (6 random uppercase chars)
- **Stores** it in localStorage: `modern_client_name`
- **Sends** the client name to broker during registration (in addition to broker ID)
- **Displays** "Client: POS-XXXXXX" instead of "Broker: <id>" in the status bar

#### 2. Broker (server.js)
- **Tracks** client names in a `clientsByName` map
- **Routes** messages using client names (not temporary broker IDs)
- **Handles reconnection**: If a POS with the same client name reconnects, the broker closes the old connection and uses the new one
- **Cleans up** the mapping when POS logs out or disconnects

#### 3. Customer Display (customer.js & customer-legacy.js)
- **Stores** the selected POS's client name in localStorage: `customer_selected_client_name`
- **Displays** "Client: <name>" instead of "Broker: <id>"
- **Reconnects** using the stored client name when the display reconnects
- **Sends** both `posId` and `clientName` to broker for compatibility

### Key Benefits

1. **Persistent Connection**: Even if POS reconnects with a new broker ID, the display stays connected using the stored client name
2. **Automatic Recovery**: No manual intervention needed after app backgrounding
3. **Backward Compatible**: Still sends broker ID for fallback routing
4. **Per-Device**: Each POS device gets a unique, stable name that persists across sessions

### Technical Details

#### POS Registration Flow
```
POS App → ensureClientName() → localStorage.getItem("modern_client_name")
        → If not found: generate "POS-XXXXXX" and store
        → registerBrokerClient() sends: { type: "REGISTER", role: "pos", clientName: "POS-XXXXXX", ... }
        → Broker stores in clientsByName map
        → Display receives in POS_LIST with clientName field
```

#### Display Connection Flow
```
Display → loadSavedClientName() → localStorage.getItem("customer_selected_client_name")
       → subscribeToPos(posId, clientName)
       → Sends: { type: "SUBSCRIBE", posId, clientName }
       → Broker routes using clientName (primary) or posId (fallback)
```

#### Reconnection Flow
```
POS backgrounded → WebSocket closes → Reconnects with NEW broker ID
                → ensureClientName() returns SAME stored name
                → registerBrokerClient() sends SAME clientName
                → Broker closes old connection, uses new one
                → Display still has stored clientName
                → Connection re-established automatically
```

### Files Modified

1. **orders/orders/broker/server.js**
   - Added `clientsByName` map
   - Updated REGISTER handler to track client names
   - Updated SUBSCRIBE handler to route by client name
   - Updated POS_LOGOUT and close handlers to clean up mapping

2. **orders/orders/modern/app.js**
   - Added `ensureClientName()` function
   - Added `clientName` to state
   - Updated `registerBrokerClient()` to send client name
   - Updated REGISTERED handler to display "Client: <name>"
   - Incremented APP_VERSION to 33

3. **orders/orders/modern/customer.js**
   - Updated `handlePosList()` to show client names
   - Updated `subscribeToPos()` to accept and store client name
   - Added `loadSavedClientName()` function
   - Updated `savePosId()` to also save client name
   - Updated `clearSavedPosId()` to clear client name
   - Updated `showHash()` to display "Client: <name>"

4. **orders/orders/modern/customer-legacy.js**
   - Same changes as customer.js (ES5 compatible)

### Testing Checklist

- [ ] POS app generates stable client name on first login
- [ ] Client name persists in localStorage across sessions
- [ ] Display shows "Client: POS-XXXXXX" instead of "Broker: <id>"
- [ ] Display stores client name in localStorage
- [ ] POS backgrounding and return maintains connection
- [ ] Multiple POS devices each get unique client names
- [ ] Broker correctly routes messages using client names
- [ ] Legacy display works with client names
- [ ] Logout clears client name from display
- [ ] Broker logs show client name tracking

### Backward Compatibility

- Broker still sends broker ID for fallback routing
- Display can still connect using broker ID if needed
- No breaking changes to existing message formats
- Both modern and legacy displays updated

### Future Enhancements

- Add UI to show/reset client name
- Add broker admin endpoint to list active client names
- Add metrics for client name reconnections
- Consider adding client name to audit logs
