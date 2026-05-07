# Payment Terminal Integration Contract

## Overview

Integration between OrderSprinter Modern POS and Nexi SmartPOS A920 payment terminal(s) via the broker service.

## Hardware & Software

- **Terminal:** Nexi SmartPOS A920
- **Payment App:** CCV app2pay (uses OPI protocol)
- **Other apps on terminal:** ccvstore, airviewer, app2pay+, secpos evo
- **Connection:** TCP/IP over local WiFi network
- **Terminal handles:** Card reading, PIN entry, authorization, receipt printing

## Protocol: OPI (Open Payment Initiative) — XML-based

The CCV app2pay app on the terminal speaks OPI. Confirmed via CCV developer documentation (PaymentApi: "Execute an OPI payment with a terminal").

### Communication format
- TCP socket connection to terminal IP:port
- Each message prefixed with 4-byte big-endian length (network byte order)
- XML payload (ISO-8859-1 encoding)
- Synchronous request/response per connection

### Message flow for a payment
1. **Login** → ServiceRequest (RequestType="Login")
2. **CardPayment** → CardServiceRequest (RequestType="CardPayment", TotalAmount)
3. **Response** ← CardServiceResponse (OverallResult="Success" / "Aborted" / "Failure")
4. **Logout** → ServiceRequest (RequestType="Logoff")

### Example messages

**Login:**
```xml
<?xml version="1.0" encoding="ISO-8859-1"?>
<ServiceRequest RequestType="Login" WorkstationID="1" RequestID="1">
   <POSdata>
      <POSTimeStamp>2026-05-07T12:00:00+02:00</POSTimeStamp>
   </POSdata>
</ServiceRequest>
```

**Payment (€12.50):**
```xml
<?xml version="1.0" encoding="ISO-8859-1"?>
<CardServiceRequest RequestType="CardPayment" WorkstationID="1" RequestID="2">
   <POSdata>
      <POSTimeStamp>2026-05-07T12:00:01+02:00</POSTimeStamp>
   </POSdata>
   <TotalAmount>12.50</TotalAmount>
</CardServiceRequest>
```

**Logout:**
```xml
<?xml version="1.0" encoding="ISO-8859-1"?>
<ServiceRequest RequestType="Logoff" WorkstationID="1" RequestID="3">
   <POSdata>
      <POSTimeStamp>2026-05-07T12:00:30+02:00</POSTimeStamp>
   </POSdata>
</ServiceRequest>
```

### Response parsing
- `OverallResult="Success"` → payment approved
- `OverallResult="Aborted"` → customer/terminal cancelled
- `OverallResult="Failure"` → declined or error

## Architecture

### Broker as payment handler

The existing WebSocket broker (`broker/server.js`) will be extended to handle payment terminal communication.

**Why the broker:**
- Already running as a persistent Node.js service on the server
- Already handles POS ↔ Display communication via WebSocket
- TCP socket connections to terminals require a persistent process (not suitable for PHP request/response)
- Broker can manage multiple terminal connections simultaneously
- POS clients already have a WebSocket connection to the broker

**Flow:**
```
iPad (POS client) → WebSocket → Broker → TCP socket → Nexi Terminal
iPad (POS client) ← WebSocket ← Broker ← TCP socket ← Nexi Terminal
```

### Message flow

1. POS client sends `PAYMENT_REQUEST` via WebSocket to broker
2. Broker looks up terminal assignment for that POS (from config)
3. Broker opens TCP connection to terminal (or reuses existing)
4. Broker sends Login → CardPayment → receives response → Logout
5. Broker sends `PAYMENT_RESULT` back to POS client via WebSocket

### Multi-terminal support

Multiple POS devices can each be mapped to their own terminal, or share one.

## Configuration

All payment terminal configuration stored in a single JSON file on the server, read by the broker at startup.

**File:** `payment-config.json` (alongside broker)

```json
{
  "enabled": true,
  "protocol": "opi",
  "terminals": [
    {
      "id": "terminal-1",
      "name": "Kasse Theke",
      "ip": "192.168.0.50",
      "port": 20002
    },
    {
      "id": "terminal-2",
      "name": "Kasse Terrasse",
      "ip": "192.168.0.51",
      "port": 20002
    }
  ],
  "posMapping": {
    "POS-C851": "terminal-1",
    "POS-KTQN": "terminal-2"
  },
  "defaultTerminal": "terminal-1",
  "timeoutMs": 60000,
  "cardPaymentIds": [2, 3, 5]
}
```

**Fields:**
- `enabled` — global kill switch for payment integration
- `protocol` — `"opi"` (future-proof for other protocols)
- `terminals[]` — list of physical terminals with network details (unlimited)
- `posMapping` — maps POS clientName to a specific terminal (unlimited pairs)
- `defaultTerminal` — fallback if POS has no explicit mapping
- `timeoutMs` — how long to wait for terminal response before timeout
- `cardPaymentIds` — list of OrderSprinter payment type IDs that trigger terminal communication (admin decides which are card payments)

**Note:** Later phases will add a GUI in the admin panel to manage this config. Phase 1 is manual JSON editing.

## Scope

### In scope (Phase 1)
- Send payment amount from POS to terminal when card payment button is tapped
- POS enters waiting mode during terminal interaction
- Receive success/failure response from terminal via broker
- On success: POS completes payment (save bill, optional work receipt print)
- On failure: POS returns to payment menu
- Terminal prints payment receipt itself
- Support unlimited POS → terminal mappings via config
- Configuration via JSON file (manual editing)
- Admin defines which payment type IDs are card payments

### Phase 2 (later)
- Admin GUI for terminal configuration
- Refunds / reversals via terminal
- Reconciliation / end-of-day via integration
- Connection health monitoring / auto-reconnect
- Status indicator in POS UI (terminal reachable yes/no)

## Open Questions

1. **Terminal IP and port** → Static IP will be assigned. Port to confirm from app2pay settings (typically 20002).
2. ~~**Multiple terminals?**~~ → Resolved: unlimited POS-Terminal pairs (see config below).
3. ~~**When should payment be triggered?**~~ → Resolved: see Payment Flow below.
4. **Amount format confirmation** → OPI typically uses decimal with dot (12.50). Need to verify with CCV app2pay.

## Deployment Model

- Currently 2 POS terminals in one shop
- App will be published for other merchants → must support **unlimited POS-Terminal pairs**
- Configuration GUI for terminal setup planned for later phase
- Phase 1: JSON config file, manually edited

## Payment Flow

### Setup (one-time, admin)
1. Admin configures payment types in the system (existing OrderSprinter feature)
2. A setup routine writes all payment types into the modern app config file
3. Admin can mark which payment types are **card payments** (trigger terminal) vs **non-card** (cash, voucher, etc.)
4. POS-to-terminal mapping is configured in `payment-config.json`

### Runtime (per transaction)
1. Cashier selects items, creates bill, enters payment screen
2. Payment buttons are shown (cash, EC-Karte, Kreditkarte, etc. — as configured)
3. Cashier taps a **card payment** button
4. POS client enters **waiting mode** (UI locked, shows "Warte auf Terminal...")
5. POS sends `PAYMENT_REQUEST` to broker via WebSocket (amount + payment type)
6. Broker opens TCP/OPI connection to the mapped terminal
7. Broker sends Login → CardPayment → waits for terminal response
8. Terminal handles card interaction + prints payment receipt

**If terminal responds OK:**
- Broker sends `PAYMENT_RESULT: SUCCESS` to POS client
- POS exits waiting mode
- POS completes the payment (saves bill, optionally prints work receipt)

**If terminal responds NOT OK (declined/aborted/timeout):**
- Broker sends `PAYMENT_RESULT: FAILED` (with reason) to POS client
- POS exits waiting mode
- POS returns to payment menu (cashier can retry or choose different payment method)

### Sequence diagram
```
POS Client          Broker              Terminal
    |                  |                    |
    |--PAYMENT_REQ---->|                    |
    |  (waiting mode)  |---TCP connect----->|
    |                  |---Login XML------->|
    |                  |<--Login OK---------|
    |                  |---CardPayment----->|
    |                  |     (terminal shows "Insert card")
    |                  |     (customer taps/inserts card)
    |                  |     (terminal prints receipt)
    |                  |<--Result OK/FAIL---|
    |                  |---Logout---------->|
    |                  |<--Logout OK--------|
    |<-PAYMENT_RESULT--|---TCP close------->|
    |  (exit waiting)  |                    |
    |                  |                    |
```

## References

- CCV Developer Portal: https://developer.myccv.eu/documentation/
- CCV PaymentApi (OPI): https://developer.myccv.eu/reference/mapi/eu/ccvlab/mapi/core/api/PaymentApi.html
- Nexi SmartPOS Integration Options: https://developer.nexigroup.com/smartpos/en-EU/docs/integration-options/
- OPI Reference Implementation (PHP): https://gist.github.com/t-oster/a1bed2bb82d566bbdb90706d521a8f83


---

## Implementation Context (for new session)

### Repository & Branch

- **Repo:** `orders-v35` (local), origin is `orders/orders` which pushes to `git@github.com:AldisKu/orders.git`
- **Branch:** `v35-work` (this is the production branch, currently at v41)
- **Server:** `ubuntu@192.168.0.33`
- **Server git folder:** `/home/ubuntu/ordersprinter` (tracks `origin/v35-work`)
- **Server live files:**
  - `/var/www/html/modern/app.js` ← from `modern/app.js`
  - `/var/www/html/php/modernapi.php` ← from `php/modernapi.php`
  - `/var/www/html/broker/server.js` ← from `broker/server.js`
  - `/var/www/html/modern/config.json` ← from `modern/config.json`
- **Broker systemd service:** `ordersprinter-broker` (runs `broker/server.js` via Node.js on port 3077)

### Key Files to Modify

| File | Role | What to change |
|------|------|----------------|
| `broker/server.js` | WebSocket broker + state poller | Add OPI TCP client, handle `PAYMENT_REQUEST` / `PAYMENT_RESULT` messages, load `payment-config.json` |
| `modern/app.js` | POS client (runs on iPad) | In `paydeskPay()`: intercept card payment IDs, send `PAYMENT_REQUEST` via broker WebSocket, show waiting UI, handle `PAYMENT_RESULT` |
| `modern/config.json` | Client config | Add `cardPaymentIds` array (or load from server config endpoint) |
| `broker/payment-config.json` | NEW file | Terminal IPs, ports, POS mappings (see config section above) |

### Existing Payment Code in `modern/app.js`

The payment flow lives in function `paydeskPay(paymentId, print)` (around line 2244):

```javascript
async function paydeskPay(paymentId, print) {
  // Collects receipt item IDs, calls API "paydesk_pay" with paymentid
  // On success: refreshes table, sends ebon to display, optionally prints work receipt
  // On failure: shows alert, refreshes
}
```

**Payment buttons** are rendered in `loadPayments()` (around line 2216):
- Fetches payment types from `api("payments")`
- Filters by `state.config.showpayment{N}` flags
- Renders two buttons per payment type: one for pay, one for "Bondruck" (work receipt)
- Each button calls `paydeskPay(paymentId, print)`

**Integration point:** Before calling `api("paydesk_pay", ...)`, check if `paymentId` is in `cardPaymentIds`. If yes → send `PAYMENT_REQUEST` to broker, enter waiting mode, and only call `paydesk_pay` after receiving `PAYMENT_RESULT: SUCCESS`.

### Existing Broker Code in `broker/server.js`

- WebSocket server on port 3077
- Clients register with `{ type: "REGISTER", role: "pos"|"display", clientName: "POS-XXXX" }`
- Messages are routed by `ws.meta.role` and `ws.meta.id` / `ws.meta.clientName`
- Polls PHP backend every 4 seconds for state changes
- Sends `UPDATE_REQUIRED` to all clients when state changes

**Integration point:** Add a new message handler for `msg.type === "PAYMENT_REQUEST"`:
1. Look up `ws.meta.clientName` in `paymentConfig.posMapping`
2. Find terminal IP/port from `paymentConfig.terminals`
3. Open TCP socket, send OPI Login + CardPayment XML
4. Wait for response (with timeout from config)
5. Send `{ type: "PAYMENT_RESULT", success: true/false, reason: "..." }` back to the requesting WebSocket

### WebSocket Message Formats (proposed)

**POS → Broker:**
```json
{
  "type": "PAYMENT_REQUEST",
  "amount": "12.50",
  "paymentId": 2,
  "tableid": 5
}
```

**Broker → POS:**
```json
{
  "type": "PAYMENT_RESULT",
  "success": true,
  "reason": "",
  "terminalId": "terminal-1"
}
```

Or on failure:
```json
{
  "type": "PAYMENT_RESULT",
  "success": false,
  "reason": "Aborted",
  "terminalId": "terminal-1"
}
```

### OPI TCP Implementation Notes (Node.js)

```javascript
const net = require('net');

function sendOPI(ip, port, xmlPayload) {
  return new Promise((resolve, reject) => {
    const socket = net.createConnection({ host: ip, port }, () => {
      const buf = Buffer.alloc(4);
      buf.writeUInt32BE(xmlPayload.length);
      socket.write(buf);
      socket.write(xmlPayload);
    });
    let data = Buffer.alloc(0);
    socket.on('data', (chunk) => {
      data = Buffer.concat([data, chunk]);
      // First 4 bytes = length, rest = XML response
      if (data.length >= 4) {
        const expectedLen = data.readUInt32BE(0);
        if (data.length >= 4 + expectedLen) {
          resolve(data.slice(4, 4 + expectedLen).toString());
          socket.end();
        }
      }
    });
    socket.on('error', reject);
    socket.setTimeout(60000, () => { socket.destroy(); reject(new Error('timeout')); });
  });
}
```

### Deploy Workflow

1. Make changes in `orders-v35` on branch `v35-work`
2. Commit with version bump (next is v42)
3. `git push origin v35-work` (pushes to local `orders/orders`)
4. `git -C orders/orders push origin v35-work` (pushes to GitHub)
5. On server: `cd /home/ubuntu/ordersprinter && git pull origin v35-work`
6. Copy files: `sudo cp modern/app.js /var/www/html/modern/app.js && sudo cp php/modernapi.php /var/www/html/php/modernapi.php && sudo cp broker/server.js /var/www/html/broker/server.js`
7. Restart broker: `sudo systemctl restart ordersprinter-broker`
8. Set ownership: `sudo chown www-data:www-data /var/www/html/modern/app.js /var/www/html/php/modernapi.php`

### Server Environment

- **OS:** Ubuntu 22.04 (hostname: kasse3)
- **Web server:** Apache 2.4.52 with PHP (mod_php or prefork)
- **Database:** MariaDB 10.6.23 (database: `ordersprinter`, user: `os_`)
- **Node.js:** runs broker as systemd service
- **POS clients:** iPads connecting via Safari/PWA over WiFi
- **Current POS devices:** POS-C851 (192.168.0.124), POS-KTQN (192.168.0.118)

### Testing Approach

1. First test: broker connects to terminal IP via TCP and sends Login XML → verify Login response
2. Second test: send a small amount (€0.01) CardPayment → verify terminal shows card prompt
3. Third test: full flow from iPad → broker → terminal → response → iPad
4. Use the Broker Debug menu (already in app.js) to verify WebSocket connectivity
