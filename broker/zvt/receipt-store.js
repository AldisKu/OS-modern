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

// Reject anything that looks like an unmasked PAN (13-19 consecutive digits).
// Masked forms like "############5373" or "**** 5373" are fine.
const UNMASKED_PAN = /(?<!\d)\d{13,19}(?!\d)/;

export class ReceiptStore {
  constructor(brokerDir, options = {}) {
    this.baseDir = path.join(brokerDir, "receipts");
    this.merchantDir = path.join(this.baseDir, "merchant");
    this.customerDir = path.join(this.baseDir, "customer");
    this.indexPath = path.join(this.baseDir, "index.json");
    this.onLog = options.onLog || (() => {});
    this._ensureDirs();
  }

  _ensureDirs() {
    for (const d of [this.baseDir, this.merchantDir, this.customerDir]) {
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
    const baseName = `${stamp}-${term}.txt`;

    const entry = {
      id: `${stamp}-${term}`,
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
      if (UNMASKED_PAN.test(text)) {
        // Defensive: never persist anything resembling a full PAN.
        this.onLog(`RECEIPTS: refusing to save ${kind} receipt — possible unmasked PAN detected`);
        return null;
      }
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
}
