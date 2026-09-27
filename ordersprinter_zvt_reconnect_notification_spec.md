# Implementation Spec: ZVT Terminal Reconnect Notification (06 E0)

**Status:** Implementation order
**Command:** ZVT Display Text `06 E0` (ZVT 13.07 §2.26)

## Objective

Whenever the broker successfully establishes or re-establishes a usable ZVT
connection to a configured payment terminal (a logical OFFLINE/CONNECTING →
READY transition — NOT merely opening a TCP socket), the terminal shall give an
immediate physical confirmation:

- display the terminal's configured logical name, e.g. `Terminal 1`
- display `POS verbunden`
- beep exactly once
- show the message for ~3 seconds

This is a non-payment action. It must not initiate a transaction, card reading,
authorization, or receipt operation.

## 1. Trigger semantics

Trigger only on `(previousLogicalState !== 'READY') && (newLogicalState === 'READY')`.

State model:
```
OFFLINE -> CONNECTING -> TCP_CONNECTED -> ZVT_INITIALIZING -> ZVT_VERIFIED -> READY
```

SHOULD trigger:
- broker start connects to a configured terminal -> READY
- terminal lost Wi-Fi then reconnects -> READY
- terminal got a new DHCP IP, rediscovery verified -> READY
- full discovery re-finds an existing configured terminal -> READY
- a new terminal is discovered AND accepted/saved -> READY

Do NOT trigger:
- periodic health/ping checks or every Status-Enquiry
- every payment / any command over an already-READY connection
- parser events, extra TCP packets, config re-reads
- repeated READY events for the same logical connection generation

## 2. Sequencing

Send `06 E0` only after TCP connect -> ZVT registration/init -> TID verified ->
terminal idle/READY. Never insert into an active transaction/recovery sequence
(06 01 authorization, reversal, refund, end-of-day, receipt transmission, card
interaction). If recovered mid-transaction, defer until idle/READY
(`pendingReconnectNotification`).

## 3. Command 06 E0

Optional bitmap fields:
```
F0 <display-duration>   -> use 03 (~3 s)
F1 <text line 1>        -> configured terminal name
F2 <text line 2>        -> "POS verbunden"
F9 <number of beeps>    -> use 01
```
Do not request keyboard input/confirmation. Do not use 06 E1/E2 or the obsolete
06 85.

## 4. Encoding

Plain 7-bit ASCII only. Sanitize the terminal name (NFKD, replace non-ASCII with
`?`, e.g. "Küche" -> "Kueche"/"K?che"), max ~20 chars. Do not transmit UTF-8.

## 5. ZVT LLVAR text length (two-byte Fx Fy)

Text fields F1..F8 use a TWO-byte LLVAR length: `0xF0|tens, 0xF0|ones`.
Examples: 3 -> `F0 F3`; 10 -> `F1 F0`; 13 -> `F1 F3`; 20 -> `F2 F0`.
```js
function encodeZvtLLVarLength(length) {
  if (!Number.isInteger(length) || length < 0 || length > 99)
    throw new Error(`Unsupported ZVT LLVAR length ${length}`);
  return Buffer.from([0xF0 | Math.floor(length / 10), 0xF0 | (length % 10)]);
}
function encodeDisplayLine(bitmap, text) {
  const data = Buffer.from(sanitizeDisplayText(text), "ascii");
  return Buffer.concat([Buffer.from([bitmap]), encodeZvtLLVarLength(data.length), data]);
}
```

## 6. Exact example ("Terminal 1" / "POS verbunden", 3 s, 1 beep)

```
06 E0 21
F0 03
F1 F1 F0 54 65 72 6D 69 6E 61 6C 20 31
F2 F1 F3 50 4F 53 20 76 65 72 62 75 6E 64 65 6E
F9 01
```
One line:
```
06 E0 21 F0 03 F1 F1 F0 54 65 72 6D 69 6E 61 6C 20 31 F2 F1 F3 50 4F 53 20 76 65 72 62 75 6E 64 65 6E F9 01
```
Payload = 33 bytes = 0x21.

## 7. Dynamic generation

Build via the broker's existing generic APDU encoder (CLASS=06, INSTR=E0,
payload). Do NOT add a second framing mechanism. Line 1 = terminal name so the
frame must be generated, not hard-coded.

## 8. Response handling

Expected: `80 00 00` only. Per ZVT no `06 0F` Completion follows `06 E0` — do NOT
wait for it (would block the queue). TX 06 E0 -> RX 80 00 00 -> DONE.

## 9. Failure isolation

`06 E0` is a convenience feature. If it is unsupported/ignored/times out, the
terminal MUST still be READY (not OFFLINE). Log it. Result model:
```js
{ terminalReady: true, reconnectNotification: { attempted: true, acknowledged: false, error: "timeout" } }
```
A TCP/socket failure while sending is different -> normal connection-loss handling.

## 10. No retry loop

One notification attempt per READY transition. On failure: log, continue. The
next genuine reconnect can try again (terminal may have beeped even if ACK lost).

## 11. Prevent duplicates

Use a per-terminal `connectionGeneration` incremented on each genuinely new
logical connection, and `lastNotifiedGeneration`. Notify once per generation.
Prevents duplicate beeps from multiple READY callbacks, UI refreshes, health
checks, duplicate network events, races.

## 12. Command-queue integration

Use the SAME serialized per-terminal ZVT command queue as payments. Never write
`06 E0` directly to the socket. Queue only when READY. Payments have higher
priority; never delay a customer payment for the cosmetic notification (payment
first, notification after, or drop it).

## 13. Display state

No need to explicitly clear the message after 3 s (`F0 03` handles it). A payment
starting sooner replaces the display naturally.

## 14. After initial discovery

Notify only after a discovered device is ACCEPTED/configured as a managed
terminal and reaches READY — not while scanning candidate IPs.

## 15. After MAC/IP rediscovery

Notify only after TID re-verified at the new IP and the managed ZVT connection is
READY. The beep confirms rediscovery succeeded AND the broker accepted this
physical terminal as the configured TID.

## 16. Logging

```json
{ "event": "terminal_reconnected", "terminalName": "Terminal 1", "tid": "12345678", "ip": "192.168.0.83", "port": 20011, "notification": "06E0", "displayDurationSeconds": 3, "beeps": 1 }
{ "event": "terminal_reconnect_notification_ack", "tid": "12345678", "result": "800000" }
{ "event": "terminal_reconnect_notification_failed", "tid": "12345678", "reason": "timeout" }
```
The failed event is NOT a registration failure.

## 17. Acceptance criteria

- A. Broker starts, terminal reachable -> READY -> one message + exactly one beep
- B. 10 min health polling -> no extra messages/beeps
- C. Wi-Fi disconnect -> OFFLINE
- D. Reconnect same IP -> READY -> one message/beep
- E. Reconnect new DHCP IP -> rediscovery same TID -> IP updated -> one message/beep
- F. New terminal discovered but not accepted -> no notification
- G. New terminal saved/activated -> one message/beep
- H. 06 E0 gets 80 00 00 -> complete immediately, no wait for 06 0F
- I. Terminal ignores 06 E0 -> stays READY, failure logged, payments work
- J. Payment in progress -> notification never inserted into the payment sequence
- K. Payment arrives while notification queued -> payment precedence
- L. Duplicate READY events -> only one notification per connection generation
