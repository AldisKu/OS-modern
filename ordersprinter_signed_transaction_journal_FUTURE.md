# Future Feature: Append-Only Signed Transaction Journal

**Status:** Future / not yet implemented. Recorded per request.

## Goal

Maintain a single, append-only ("endless") JSON journal that stores, for each
completed card transaction, the COMPLETE structured record:

- **Order data** — the OrderSprinter order/bill this payment settled
  (order id, table, items, amounts, tax, POS/cashier, timestamps).
- **Payment data** — requestId, terminalId, amountMinor, currency, result code,
  trace/receipt numbers, card scheme, masked PAN, AID, AS-Proc-Code, date/time.
- **Both terminal receipts** — the verbatim merchant AND customer receipt text
  (as pulled from the terminal via 06 20 / received via 06 D1/06 D3).

Each journal entry is **externally signed** (tamper-evidence), e.g. a detached
signature / hash chain so the sequence cannot be altered or reordered
undetectably.

## Sketch (to refine when built)

```
journal.jsonl  (one JSON object per line, append-only)
{
  "seq": 12345,
  "prevHash": "<sha256 of previous entry>",
  "ts": "2026-09-27T20:05:24+02:00",
  "order":   { ... full structured order ... },
  "payment": { requestId, terminalId, amountMinor, currency, resultCode,
               traceNumber, receiptNumber, cardName, cardPanMasked, aid, ... },
  "receipts": { "merchant": ["line", ...], "customer": ["line", ...] },
  "hash": "<sha256 of this entry incl. prevHash>",
  "sig":  "<external signature over hash>"
}
```

## Notes / open design points

- **Append-only + hash chain** (prevHash -> hash) gives tamper-evidence without
  a DB. External signing key kept outside the broker (KSV/TSE-like intent, but
  this is NOT a certified TSE — clarify legal scope before relying on it).
- The order data must be joined at payment time (broker currently does not have
  the full order; would come from the POS in the PAYMENT_REQUEST, or a POS->
  broker enrichment message, since the broker must not query the DB directly).
- Signing: detached signature per entry, or periodic signing of the chain head.
- Rotation/retention: single endless file vs. yearly files; the hash chain must
  span rotations.
- This is the accounting-grade record; the current text-file archive under
  modern/ remains a convenience copy only.

## Relationship to current implementation

Current (v64+): terminal receipts are saved as text files and, on "print all",
archived by moving the single text files into modern/receipts-archive/. That is
operational convenience only. This journal is the future, structured, signed
system of record.
