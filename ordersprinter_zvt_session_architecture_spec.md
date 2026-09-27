# Implementation Spec: Persistent ZVT TerminalSession + Per-Terminal Queue

**Status:** Design order (task #8). Builds on the event-driven receive path (task #1, done).

## Principle

One persistent broker-owned ZVT connection per physical terminal; one
Registration per connect/reconnect (NOT per payment); one serialized operation
queue per terminal. POS clients never open ZVT sessions — they submit jobs to
the broker.

```
POS 1 ─┐
POS 2 ─┼── Broker ── TerminalSession A ── A960 #1   (own queue)
POS 3 ─┘         └── TerminalSession B ── A960 #2   (own queue)
```

Two POS targeting the same terminal are serialized (FIFO) by that terminal's
queue. Different terminals run concurrently.

## Registration lifecycle

Register on a NEW logical session only: broker start, Wi-Fi/TCP loss, terminal
reboot, IP rediscovery, broker restart. A new payment does NOT re-register.
Never send 06 02 Log-Off between payments (Log-Off resets config + disables TLV).
ZVT allows authorisation without a preceding Registration, but we register once
per connection to set config byte / receipt behaviour / TLV capabilities.

## State machine (per terminal)

```
DISCONNECTED -> (TCP) -> REGISTERING -> (06 00 complete) -> READY
READY -> (job) -> BUSY -> (06 0F completion) -> READY
any socket failure -> DISCONNECTED -> reconnect/rediscover -> REGISTERING -> READY
```
POS systems do not participate — they only create jobs and receive job events.

## Per-terminal operation queue

Each TerminalSession has its own FIFO queue. Ops: payment, status-enquiry,
receipt pull (06 20). Serialized within a terminal; concurrent across terminals.
No global broker queue, no global lock.

A job owns the WHOLE ZVT transaction. Job states:
```
QUEUED -> STARTING -> PAYMENT_ACTIVE -> RESULT_RECEIVED -> RECEIPTS_RECEIVING -> COMPLETION_RECEIVED -> DONE
```
Terminal lock held until the entire sequence (incl. receipt pull via 06 20) is
complete, then the next job starts. Distinguish PAYMENT_APPROVED (financial)
from JOB_COMPLETE (incl. receipts). Completion (06 0F) = point where ECR regains
master rights = scheduler boundary for starting the next job.

Receipt pull stays INSIDE the same job — never release the terminal to another
POS between payment and its receipt retrieval.

## Status-enquiry

Keep the connection alive; ~60s status-enquiry as a LOW-priority queued op,
only when READY and queue empty. Never write 05 01 mid-payment.
```
if (state === READY && queue.length === 0 && statusPollDue) enqueueLowPriority(statusEnquiry);
```

## Payment submission (async job model)

POS -> broker: { requestId, posId, terminalId, amountMinor, currency }.
Broker replies fast: { jobId, state: "QUEUED", queuePosition }. Then status
events over WS: QUEUED -> STARTING -> WAITING_FOR_CARD -> PROCESSING -> APPROVED
-> RECEIVING_RECEIPT -> DONE. Payment belongs to the broker JOB, not the HTTP/WS
request — a POS disconnect must not interrupt the transaction.

## Idempotency

requestId is the idempotency key. Duplicate requestId (e.g. POS retry after
Wi-Fi blip) returns the EXISTING job, never a second authorisation. (Already
implemented in PaymentService.)

## Queue fairness / cancel

Default FIFO by arrival time. Cancel while QUEUED = remove job (no terminal
contact). Cancel while STARTING/PAYMENT_ACTIVE = proper ZVT Abort (06 B0).

## Concurrency rules (from event-driven receive, task #1)

- One permanent socket 'data' handler per session; rxBuffer + frameQueue +
  frameWaiters; promise-based waitFrame; reject-all-waiters on close/error;
  max-rx-buffer guard. (DONE in task #1.)
- Independent session per terminal; no dependency between terminals.
- Async waits OK (await), blocking not (no sleep loops, no sync fs/db, no global
  mutex, no polling).
- Deliver EVERY valid APDU to the session layer (04 FF, 04 0F, 06 D1, 06 D3,
  06 0F) — never silently drop; the state machine routes them.

## Single process

One Node process is correct for this scale. Do not spin a second broker just
because ZVT ops await responses. Consider process separation only for genuine
CPU/fault-isolation needs, and then via IPC with exactly one owner per terminal.

## Migration note

Current code uses connect-on-demand getSession() (connect+register+op+disconnect
per operation). Task #8 replaces that with persistent TerminalSession objects +
queue. Risk: rewrites the working payment core — test each op (poll, discovery,
payment, receipt pull) against the real A960 after.
