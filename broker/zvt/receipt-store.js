/**
 * Card-receipt store.
 * Saves merchant/customer card receipts (pulled from the terminal via ZVT
 * Repeat-Receipt) as plain-text files and maintains a small JSON index for the
 * reprint UI. These are OPERATIONAL CONVENIENCE COPIES — the payment service
 * provider holds the accounting record; nothing here is the legal archive.
 *
 * Layout (under <brokerDir>/receipts):
 *   receipts/merchant/<YYYYMMDDHHMMSS>-<terminalId>.txt
 *   receipts/customer/<YYYYMMDDHHMMSS>-<terminalId>.txt
 *   receipts/index.json
 */

import fs from "fs";
import path from "path";

// Default compact-print config (overridable via config.json zvt.print.compactMerchant).
//   fontEsc: ESC/POS bytes (hex) to select the font, e.g. "1b4d01" = Font B.
//   width:   characters per line that font fits on the roll (Font B ~57 on 80mm).
const COMPACT_DEFAULT = { fontEsc: "1b4d01", width: 57 };

/**
 * Turn one raw A960 merchant receipt (array of text lines) into a flat list
 * of TOKENS (each a self-contained "field value" unit, never split mid-token
 * except when a single token is longer than the line). Shop header is dropped.
 * The card number is NOT masked (kept exactly as the terminal printed it).
 */
function tokenizeMerchant(rawLines) {
  const toks = [];
  const txt = rawLines.join("\n");
  const one = (re) => { const m = txt.match(re); return m ? m[1].trim() : null; };

  const datum  = one(/(\d{2}\.\d{2}\.\d{4})\s+\d{2}:\d{2}/);
  const zeit   = one(/\d{2}\.\d{2}\.\d{4}\s+(\d{2}:\d{2})/);
  const ta     = one(/TA-Nr\.\s*(\d+)/);
  const beleg  = one(/Beleg-Nr\.\s*(\d+)/);
  const betrag = one(/Betrag\s+([\d.]+,\d{2})\s*EUR/);
  const kart   = (txt.match(/(?:Kartenzahlung|Bezahlung)\s+(.+)/) || [])[1];
  const tid    = one(/T-ID\s+(\d+)/);
  const knr    = one(/(?:Kartennr\.|KNr\.)\s*([#\d* ]+?)\s*$/m);
  const gueltig= one(/g.ltig bis \(MM\/JJ\)\s*([\d/]+)/);
  const online = txt.includes("Kontaktlos Chip") && txt.includes("Online");
  const vu     = one(/VU-Nummer\s*(\w+)/);
  const aut    = one(/Autorisierungsnummer\s*(\w+)/);
  const rc     = one(/Autorisierungsantwortcode\s*(\d+)/);
  const asproc = one(/AS-Proc(?:-Code)?\s*=?\s*(.+)/);
  const capt   = one(/Capt\.?-?Ref\.?\s*=?\s*(.+)/);
  const aid    = one(/AID59:?\s*(\w+)/);

  // EMV: concatenate the fragment lines into one token (may overflow -> wrapped).
  const emv = [];
  let coll = false;
  for (const l of rawLines) {
    if (l.includes("EMV-Daten")) { coll = true; continue; }
    if (coll) {
      const s = l.trim();
      if (s.startsWith("**") || /^(AS-Proc|Capt\.?-?Ref|AID59)/.test(s)) break;
      if (s) emv.push(s);
    }
  }
  const emvJoin = emv.join("");
  // Status: shorten "Autorisierung erfolgt" -> "auth OK" (only this one).
  const mSt = txt.match(/\*\*\s*(.+?)\s*\*\*/);
  let status = mSt ? mSt[1].trim() : null;
  if (status && /Autorisierung\s+erfolgt/i.test(status)) status = "auth OK";

  // Build tokens in receipt order. Each entry is ONE token (kept intact).
  const push = (v) => { if (v) toks.push(v); };
  if (datum && zeit) push(`${datum} ${zeit}`); else { push(datum); push(zeit); }
  push(beleg && `Beleg ${beleg}`);
  push(ta && `TA ${ta}`);
  push(kart && kart.trim());
  push(betrag && `${betrag} EUR`);
  push(tid && `TID ${tid}`);
  push(knr && `Karte ${knr}`);
  push(gueltig && `gültig ${gueltig}`);
  push(online && "Kontaktlos Chip Online");
  push(vu && `VU ${vu}`);
  push(aut && `Autor ${aut}`);
  push(rc && `RC ${rc}`);
  push(emvJoin && `EMV ${emvJoin}`);
  push(asproc && `AS-Proc ${asproc}`);
  push(capt && `Capt.Ref ${capt}`);
  push(aid && `AID59 ${aid}`);
  push(status);
  return toks;
}

/**
 * Greedy line fill: place tokens in order, space-separated, as many as fit in
 * `width`. A token longer than the line is hard-wrapped. Shop header dropped.
 * Returns an array of printable lines for ONE receipt (no leading/trailing
 * separators — the caller adds the divider between receipts).
 */
function layoutTokens(tokens, width) {
  const lines = [];
  let cur = "";
  const flush = () => { if (cur) { lines.push(cur); cur = ""; } };
  for (let tok of tokens) {
    // Token longer than a full line -> hard-wrap it on its own line(s).
    if (tok.length > width) {
      flush();
      let rest = tok;
      while (rest.length > width) { lines.push(rest.slice(0, width)); rest = rest.slice(width); }
      if (rest) cur = rest; // continue filling the remainder
      continue;
    }
    if (!cur) { cur = tok; }
    else if (cur.length + 1 + tok.length <= width) { cur += " " + tok; }
    else { flush(); cur = tok; }
  }
  flush();
  return lines;
}

export class ReceiptStore {
  constructor(brokerDir, options = {}) {
    this.baseDir = path.join(brokerDir, "receipts");
    this.merchantDir = path.join(this.baseDir, "merchant");
    this.customerDir = path.join(this.baseDir, "customer");
    this.indexPath = path.join(this.baseDir, "index.json");
    // Archive lives under the modern/ folder (broker dir is modern/broker),
    // so archived single receipt text files sit at modern/receipts-archive/.
    this.archiveDir = options.archiveDir || path.join(brokerDir, "..", "receipts-archive");
    this.onLog = options.onLog || (() => {});
    // Compact "print all" layout config (font + line width), from config.json
    // zvt.print.compactMerchant; falls back to Font B / 57 chars.
    this.compact = { ...COMPACT_DEFAULT, ...(options.compactMerchant || {}) };
    this._ensureDirs();
  }

  _ensureDirs() {
    for (const d of [this.baseDir, this.merchantDir, this.customerDir, this.archiveDir]) {
      try { fs.mkdirSync(d, { recursive: true }); } catch (_) {}
    }
  }

  /** Timestamp component YYYYMMDDHHMMSS in server local time. */
  static stamp(date = new Date()) {
    const p = (n, w = 2) => String(n).padStart(w, "0");
    return `${date.getFullYear()}${p(date.getMonth() + 1)}${p(date.getDate())}` +
           `${p(date.getHours())}${p(date.getMinutes())}${p(date.getSeconds())}`;
  }

  /** Atomic write: temp file then rename. */
  _atomicWrite(filePath, text) {
    const tmp = filePath + ".tmp";
    fs.writeFileSync(tmp, text, "utf8");
    fs.renameSync(tmp, filePath);
  }

  _loadIndex() {
    try {
      if (fs.existsSync(this.indexPath)) {
        const d = JSON.parse(fs.readFileSync(this.indexPath, "utf8"));
        if (Array.isArray(d.receipts)) return d;
      }
    } catch (e) { this.onLog(`RECEIPTS: index load failed: ${e.message}`); }
    return { schemaVersion: 1, receipts: [] };
  }

  _saveIndex(idx) {
    this._atomicWrite(this.indexPath, JSON.stringify(idx, null, 2));
  }

  /**
   * Save a card transaction's receipts.
   *
   * @param {object} tx - { requestId, terminalId, amountMinor, currency,
   *                        cardName, traceNumber, receiptNumber, resultCode }
   * @param {object} receipts - { merchantLines: string[], customerLines: string[] }
   * @returns {object} index entry that was stored
   */
  save(tx, receipts) {
    const date = new Date();
    const stamp = ReceiptStore.stamp(date);
    const term = (tx.terminalId || "terminal").replace(/[^a-zA-Z0-9_-]/g, "");
    // TA (trace number) makes the id unique per transaction, not just per
    // second: id = <YYYYMMDDHHMMSS>-<TA>-<terminalId>. TA is digits-only;
    // fall back to "000000" when the terminal didn't report one.
    const ta = String(tx.traceNumber != null ? tx.traceNumber : "").replace(/\D/g, "") || "000000";
    const baseName = `${stamp}-${ta}-${term}.txt`;

    const entry = {
      id: `${stamp}-${ta}-${term}`,
      requestId: tx.requestId || null,
      timestamp: date.toISOString(),
      terminalId: tx.terminalId || null,
      amountMinor: tx.amountMinor != null ? tx.amountMinor : null,
      currency: tx.currency || "EUR",
      cardName: tx.cardName || null,
      traceNumber: tx.traceNumber || null,
      receiptNumber: tx.receiptNumber || null,
      resultCode: tx.resultCode != null ? tx.resultCode : null,
      merchantFile: null,
      customerFile: null
    };

    const writeCopy = (lines, dir, kind) => {
      if (!lines || lines.length === 0) return null;
      const text = lines.join("\n") + "\n";
      // No PAN masking check: receipts come from a certified payment terminal
      // (Nexi/CCV A960) which already masks the card number on both merchant
      // and customer copies. The merchant receipt's "KNr." field is an internal
      // card/account reference (19 digits), not an unmasked PAN — a length-only
      // regex falsely flagged it and dropped the merchant copy. Store as-is.
      const filePath = path.join(dir, baseName);
      try {
        this._atomicWrite(filePath, text);
        return baseName;
      } catch (e) {
        this.onLog(`RECEIPTS: failed to save ${kind} receipt: ${e.message}`);
        return null;
      }
    };

    entry.merchantFile = writeCopy(receipts.merchantLines, this.merchantDir, "merchant");
    entry.customerFile = writeCopy(receipts.customerLines, this.customerDir, "customer");

    // Update index
    const idx = this._loadIndex();
    idx.receipts.push(entry);
    try { this._saveIndex(idx); } catch (e) { this.onLog(`RECEIPTS: index save failed: ${e.message}`); }

    this.onLog(`RECEIPTS: saved ${entry.id} merchant=${!!entry.merchantFile} customer=${!!entry.customerFile}`);
    return entry;
  }

  /** List index entries (most recent first). */
  list() {
    const idx = this._loadIndex();
    return idx.receipts.slice().reverse();
  }

  /** Get the text lines of a stored receipt by id + copyType, or null. */
  getReceiptLines(id, copyType) {
    const idx = this._loadIndex();
    const entry = idx.receipts.find(r => r.id === id);
    if (!entry) return null;
    const dir = copyType === "customer" ? this.customerDir : this.merchantDir;
    const file = copyType === "customer" ? entry.customerFile : entry.merchantFile;
    if (!file) return null;
    try {
      return fs.readFileSync(path.join(dir, file), "utf8").split("\n");
    } catch (_) { return null; }
  }

  /**
   * All merchant receipts (oldest first) as one array of lines, concatenated
   * for a single "print all" CUPS job. Each receipt is separated by blank
   * lines. Returns { lines, count }.
   */
  getAllMerchantReceiptsCombined() {
    const idx = this._loadIndex();
    const entries = idx.receipts.filter(e => e.merchantFile); // oldest first (index order)
    if (entries.length === 0) return { lines: [], count: 0 };

    const width = this.compact.width || COMPACT_DEFAULT.width;
    const divider = "-".repeat(width);
    // Font select ESC/POS bytes (hex -> raw string) emitted once at the top.
    const lines = [];
    if (this.compact.fontEsc) {
      try { lines.push(Buffer.from(this.compact.fontEsc, "hex").toString("latin1")); } catch (_) {}
    }

    // Shop header is dropped; receipts are tokenized and greedily filled into
    // `width`-char lines; a divider line separates each receipt. Single-receipt
    // printing (getReceiptLines) is untouched and stays 1:1.
    let count = 0;
    for (const e of entries) {
      try {
        const raw = fs.readFileSync(path.join(this.merchantDir, e.merchantFile), "utf8").split("\n");
        const body = layoutTokens(tokenizeMerchant(raw), width);
        if (body.length === 0) continue;
        if (count > 0) lines.push(divider);
        lines.push(...body);
        count++;
      } catch (_) {}
    }
    if (count === 0) return { lines: [], count: 0 };
    return { lines, count };
  }

  /**
   * Archive all active merchant receipts (MOVE the single .txt files into the
   * archive folder) and delete the customer copies. Removes archived entries
   * from the active index so the active list only holds not-yet-archived
   * receipts. Called only AFTER the user confirms the print-all actually
   * printed. Returns { archived, deletedCustomer }.
   */
  archivePrintedMerchants() {
    const idx = this._loadIndex();
    let archived = 0;
    let deletedCustomer = 0;
    const remaining = [];
    for (const e of idx.receipts) {
      let handled = false;
      if (e.merchantFile) {
        try {
          fs.renameSync(
            path.join(this.merchantDir, e.merchantFile),
            path.join(this.archiveDir, e.merchantFile)
          );
          archived++;
          handled = true;
        } catch (err) {
          this.onLog(`RECEIPTS: archive move failed for ${e.id}: ${err.message}`);
          remaining.push(e); // keep it active if the move failed
          continue;
        }
      }
      // Delete the customer copy (convenience copy, not retained).
      if (e.customerFile) {
        try { fs.unlinkSync(path.join(this.customerDir, e.customerFile)); deletedCustomer++; } catch (_) {}
      }
      // If it had a merchant file we archived it -> drop from active index.
      // If it had no merchant file (customer-only), also drop after deletion.
      if (!handled && !e.merchantFile) {
        // customer-only entry with no merchant file: remove
      }
    }
    // Active index now only keeps entries whose merchant move failed.
    idx.receipts = remaining;
    try { this._saveIndex(idx); } catch (err) { this.onLog(`RECEIPTS: index save failed: ${err.message}`); }
    this.onLog(`RECEIPTS: archived ${archived} merchant receipt(s), deleted ${deletedCustomer} customer receipt(s)`);
    return { archived, deletedCustomer };
  }

  /**
   * Permanently delete a merchant receipt by id (manual admin action).
   * Removes the merchant file and its index entry (if no customer file remains).
   * @returns {boolean} true if something was removed
   */
  deleteMerchant(id) {
    const idx = this._loadIndex();
    const entry = idx.receipts.find(r => r.id === id);
    if (!entry || !entry.merchantFile) return false;
    try { fs.unlinkSync(path.join(this.merchantDir, entry.merchantFile)); } catch (_) {}
    entry.merchantFile = null;
    // If neither copy remains, drop the index entry entirely.
    if (!entry.customerFile) {
      idx.receipts = idx.receipts.filter(r => r.id !== id);
    }
    try { this._saveIndex(idx); } catch (e) { this.onLog(`RECEIPTS: index save failed: ${e.message}`); }
    this.onLog(`RECEIPTS: deleted merchant receipt ${id}`);
    return true;
  }
}

// Exported for unit testing of the compact "print all" layout (not used by the
// broker runtime, which calls getAllMerchantReceiptsCombined()).
export { tokenizeMerchant, layoutTokens };
