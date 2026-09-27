/**
 * ZVT Protocol Codec
 * Handles BCD encoding/decoding, APDU framing, and TLV parsing.
 * Reference: ECR-Interface ZVT-Protocol, Revision 13.07
 */

// --- Receipt text decoding ---
// This A960 emits print-line / print-text-block text in a Latin-1 / CP850
// compatible encoding where German characters map directly: 0xC4=Ä, 0xD6=Ö,
// 0xDC=Ü, 0xE4=ä, 0xF6=ö, 0xFC=ü, 0xDF=ß (confirmed on the wire: "HÄNDLERBELEG"
// arrives with 0xC4 for Ä). Decoding as ASCII mangled these, and the old
// latin1+ASCII-strip DELETED them. We decode as latin1 (which is exactly this
// mapping for the German set) and drop NUL padding, preserving umlauts.
function decodeReceiptText(buf) {
  return Buffer.from(buf).toString("latin1").replace(/\x00/g, "");
}

// --- BCD Encoding/Decoding ---

/**
 * Encode a decimal string to BCD bytes.
 * "123456" → [0x12, 0x34, 0x56]
 */
export function bcdEncode(decimalStr) {
  // Pad to even length
  if (decimalStr.length % 2 !== 0) decimalStr = "0" + decimalStr;
  const bytes = [];
  for (let i = 0; i < decimalStr.length; i += 2) {
    const hi = parseInt(decimalStr[i], 10);
    const lo = parseInt(decimalStr[i + 1], 10);
    if (isNaN(hi) || isNaN(lo)) throw new Error(`Invalid BCD digit in: ${decimalStr}`);
    bytes.push((hi << 4) | lo);
  }
  return Buffer.from(bytes);
}

/**
 * Decode BCD bytes to decimal string.
 * [0x12, 0x34, 0x56] → "123456"
 */
export function bcdDecode(buffer) {
  let result = "";
  for (const byte of buffer) {
    result += ((byte >> 4) & 0x0F).toString();
    result += (byte & 0x0F).toString();
  }
  return result;
}

/**
 * Encode amount in minor units (cents) to 6-byte BCD.
 * 1850 → Buffer [00 00 00 00 18 50]
 */
export function encodeAmount(amountMinor) {
  if (!Number.isInteger(amountMinor) || amountMinor < 0) {
    throw new Error(`Invalid amount: ${amountMinor}`);
  }
  const str = amountMinor.toString().padStart(12, "0");
  if (str.length > 12) throw new Error(`Amount too large: ${amountMinor}`);
  return bcdEncode(str);
}

/**
 * Decode 6-byte BCD amount to integer cents.
 */
export function decodeAmount(buffer) {
  if (buffer.length !== 6) throw new Error(`Amount must be 6 bytes, got ${buffer.length}`);
  return parseInt(bcdDecode(buffer), 10);
}

/**
 * Encode ZVT password (6 decimal digits) to 3-byte BCD.
 * "000000" → Buffer [00 00 00]
 */
export function encodePassword(password) {
  if (!/^\d{6}$/.test(password)) throw new Error(`ZVT password must be exactly 6 digits`);
  return bcdEncode(password);
}

/**
 * Encode EUR currency code (978) to 2-byte BCD.
 */
export function encodeCurrency(code = 978) {
  const str = code.toString().padStart(4, "0");
  return bcdEncode(str);
}

// --- APDU Framing ---

/**
 * Build a ZVT APDU frame.
 * @param {number} cmdClass - Command class byte (e.g., 0x06)
 * @param {number} cmdInstr - Instruction byte (e.g., 0x01 for Authorisation)
 * @param {Buffer} payload - Payload data
 * @returns {Buffer} Complete APDU frame
 */
export function buildFrame(cmdClass, cmdInstr, payload = Buffer.alloc(0)) {
  const len = payload.length;
  if (len <= 254) {
    // Short length encoding
    const frame = Buffer.alloc(3 + len);
    frame[0] = cmdClass;
    frame[1] = cmdInstr;
    frame[2] = len;
    payload.copy(frame, 3);
    return frame;
  } else {
    // Extended length encoding (0xFF + 2 byte little-endian length per ZVT)
    const frame = Buffer.alloc(5 + len);
    frame[0] = cmdClass;
    frame[1] = cmdInstr;
    frame[2] = 0xFF;
    frame.writeUInt16LE(len, 3);
    payload.copy(frame, 5);
    return frame;
  }
}

/**
 * Parse a ZVT APDU frame from buffer.
 * Returns { cmdClass, cmdInstr, payload, totalLength } or null if incomplete.
 */
export function parseFrame(buffer) {
  if (buffer.length < 3) return null;
  const cmdClass = buffer[0];
  const cmdInstr = buffer[1];
  const lenByte = buffer[2];

  let payloadLength;
  let headerLength;

  if (lenByte === 0xFF) {
    // Extended length (2 byte little-endian per ZVT spec)
    if (buffer.length < 5) return null;
    payloadLength = buffer.readUInt16LE(3);
    headerLength = 5;
  } else {
    payloadLength = lenByte;
    headerLength = 3;
  }

  const totalLength = headerLength + payloadLength;
  if (buffer.length < totalLength) return null;

  const payload = buffer.slice(headerLength, totalLength);
  return { cmdClass, cmdInstr, payload, totalLength };
}

/**
 * ACK frame (positive acknowledgment)
 */
export const ACK = Buffer.from([0x80, 0x00, 0x00]);

/**
 * Check if a frame is an ACK
 */
export function isAck(frame) {
  return frame.cmdClass === 0x80 && frame.cmdInstr === 0x00 && frame.payload.length === 0;
}

// --- TLV Parser ---

/**
 * Parse TLV data from a buffer.
 * Handles:
 * - Single-byte tags
 * - Two-byte tags (1Fxx)
 * - Nested containers (e.g., E4)
 * - Length encoding: 0x00-0x7F direct, 0x81+len, 0x82+hi+lo
 * 
 * Returns array of { tag, value, children? }
 */
export function parseTlv(buffer) {
  const results = [];
  let offset = 0;

  while (offset < buffer.length) {
    // Parse tag
    if (offset >= buffer.length) break;
    let tag;
    const firstByte = buffer[offset];
    
    if ((firstByte & 0x1F) === 0x1F) {
      // Two-byte tag
      if (offset + 1 >= buffer.length) break;
      tag = (firstByte << 8) | buffer[offset + 1];
      offset += 2;
    } else {
      tag = firstByte;
      offset += 1;
    }

    // Parse length
    if (offset >= buffer.length) break;
    const lenFirstByte = buffer[offset];
    let length;

    if (lenFirstByte <= 0x7F) {
      length = lenFirstByte;
      offset += 1;
    } else if (lenFirstByte === 0x81) {
      if (offset + 1 >= buffer.length) break;
      length = buffer[offset + 1];
      offset += 2;
    } else if (lenFirstByte === 0x82) {
      if (offset + 2 >= buffer.length) break;
      length = (buffer[offset + 1] << 8) | buffer[offset + 2];
      offset += 3;
    } else {
      // Invalid length encoding — skip this tag
      break;
    }

    if (offset + length > buffer.length) break;
    const value = buffer.slice(offset, offset + length);
    offset += length;

    // Known container tags — parse recursively
    const containerTags = [0xE4, 0xE0, 0xE1, 0xE2, 0xE3, 0xEF, 0xF0];
    if (containerTags.includes(tag) && length > 0) {
      const children = parseTlv(value);
      results.push({ tag, value, children });
    } else {
      results.push({ tag, value });
    }
  }

  return results;
}

/**
 * Locate the start offset of TLV data within a completion/status payload.
 * Handles the ZVT TLV container form where tag 0x06 is followed by a BER length
 * (0x00-0x7F direct, 0x81 + 1 byte, 0x82 + 2 bytes), and also a bare TLV start
 * (two-byte 1Fxx tag or constructed E0-EF container).
 * Returns the offset of the first TLV tag byte, or -1 if none found.
 */
export function findTlvContainerStart(payload) {
  for (let i = 0; i < payload.length; i++) {
    const b = payload[i];
    // ZVT TLV container: tag 0x06 + BER length, content starts after the length
    if (b === 0x06 && i + 1 < payload.length) {
      const lb = payload[i + 1];
      let contentStart = -1;
      if (lb <= 0x7F) contentStart = i + 2;
      else if (lb === 0x81 && i + 2 < payload.length) contentStart = i + 3;
      else if (lb === 0x82 && i + 3 < payload.length) contentStart = i + 4;
      if (contentStart >= 0 && contentStart < payload.length) {
        const nb = payload[contentStart];
        // Content should begin with a TLV tag (1Fxx two-byte or E0-EF constructed)
        if (nb === 0x1F || (nb >= 0xE0 && nb <= 0xEF)) return contentStart;
      }
    }
    // Bare TLV start
    if (b === 0x1F && i + 1 < payload.length) return i;
    if (b >= 0xE0 && b <= 0xEF) return i;
  }
  return -1;
}

/**
 * Find a TLV tag in parsed results (recursive search).
 * @param {Array} tlvList - Parsed TLV array
 * @param {number} targetTag - Tag to find
 * @returns {Buffer|null} Value of found tag, or null
 */
export function findTlvTag(tlvList, targetTag) {
  for (const item of tlvList) {
    if (item.tag === targetTag) return item.value;
    if (item.children) {
      const found = findTlvTag(item.children, targetTag);
      if (found) return found;
    }
  }
  return null;
}

// --- ZVT Command Builders ---

/**
 * Build Status-Enquiry command (05 01).
 * Uses service-byte 0x06 to request TLV extended status.
 */
export function buildStatusEnquiry(password) {
  const pwdBuf = encodePassword(password);
  // password (3 bytes) + bitmap 0x03 + service-byte 0x06
  const payload = Buffer.concat([pwdBuf, Buffer.from([0x03, 0x06])]);
  return buildFrame(0x05, 0x01, payload);
}

/**
 * Build Registration command (06 00).
 *
 * Layout (per ZVT 13.07, matching the proven Portalum.Zvt implementation):
 *   password(3) + config-byte + [currency(2 BCD)] + [0x03 + service-byte]
 *                + [TLV container permitting 06 D3 Print-Text-Block]
 *
 * Notes learned from the real Nexi/CCV A960 (SECpos EVO):
 *  - The currency here is 2 BARE BCD bytes (e.g. 09 78), NOT prefixed with the
 *    BMP-49 tag. Sending "... 49 09 78" is rejected with 84 9A.
 *  - To make the terminal SEND receipts to the ECR (instead of printing itself
 *    or dropping them), config-byte bit 7 (0x40) must be set AND the ECR must
 *    declare that it permits the 06 D3 Print-Text-Block command via a TLV
 *    container (tag 26 = list of permitted ZVT commands).
 *
 * @param {string} password - 6-digit ZVT password
 * @param {number} configByte - Configuration byte (0x5F = PT sends receipts + intermediate status)
 * @param {number} currencyCode - Currency code (default 978 = EUR)
 * @param {object} opts
 *   @param {boolean} opts.includeCurrency - append 2-byte BCD currency (default true)
 *   @param {number|null} opts.serviceByte - service byte, or null to omit (default 0x01)
 *   @param {boolean} opts.tlvPermitPrint - append TLV permitting 06 D3 (default true)
 */
export function buildRegistration(password, configByte = 0x9A, currencyCode = 978, opts = {}) {
  const {
    includeCurrency = true,
    serviceByte = 0x01,
    tlvPermitPrint = true
  } = opts;

  const parts = [encodePassword(password), Buffer.from([configByte])];

  if (includeCurrency) {
    parts.push(encodeCurrency(currencyCode)); // 2 bare BCD bytes, e.g. 09 78
  }
  if (serviceByte !== null && serviceByte !== undefined) {
    parts.push(Buffer.from([0x03, serviceByte])); // 0x03 = service-byte indicator
  }
  if (tlvPermitPrint) {
    // TLV container: tag 26 (permitted ZVT commands) -> entry 0A (len 2) = 06 D3
    // 06 06 26 04 0A 02 06 D3
    parts.push(Buffer.from([0x06, 0x06, 0x26, 0x04, 0x0A, 0x02, 0x06, 0xD3]));
  }

  return buildFrame(0x06, 0x00, Buffer.concat(parts));
}

/**
 * Build Authorisation command (06 01) — immediate capture.
 *
 * Optional per-transaction receipt override via TLV 1F04 (receipt parameter):
 *   bit 0x01 = use ECR as printer (send 06 D1/06 D3) instead of internal printer
 *   bit 0x20 = positive merchant receipt
 *   bit 0x80 = positive customer receipt
 * e.g. 0xA1 = customer + merchant + ECR-as-printer. Emitted as: 06 04 1F 04 01 <val>
 * This can force ECR receipt output even when the registration MSB is not set,
 * so it is useful as a diagnostic.
 *
 * @param {number} amountMinor - Amount in cents
 * @param {number} currencyCode - Currency code (default 978 = EUR)
 * @param {object} opts
 *   @param {number|null} opts.receiptParam1F04 - value for TLV 1F04, or null to omit (default null)
 */
export function buildAuthorisation(amountMinor, currencyCode = 978, opts = {}) {
  const { receiptParam1F04 = null } = opts;
  const amtBuf = encodeAmount(amountMinor);
  const currBuf = encodeCurrency(currencyCode);
  // BMP 04 (amount) + BMP 49 (currency)
  const parts = [Buffer.from([0x04]), amtBuf, Buffer.from([0x49]), currBuf];
  if (receiptParam1F04 !== null && receiptParam1F04 !== undefined) {
    // TLV container: 06 <len> 1F 04 01 <val>
    parts.push(Buffer.from([0x06, 0x04, 0x1F, 0x04, 0x01, receiptParam1F04 & 0xFF]));
  }
  return buildFrame(0x06, 0x01, Buffer.concat(parts));
}

/**
 * Build Abort command (06 B0).
 */
export function buildAbort() {
  return buildFrame(0x06, 0xB0, Buffer.alloc(0));
}

/**
 * Build Repeat-Receipt command (06 20).
 *
 * Retrieves stored receipts. With ECR-Receipt active the terminal returns them
 * as 06 D1 Print-Line (or 06 D3) blocks. Verified on the A960: requesting by
 * type + a single TA number returns exactly that one receipt.
 *
 * TLVs used (ZVT 13.07):
 *   1F01 = receipt type  (0x02 = merchant, 0x03 = customer, 0x05 = journal)
 *   1F02 = from TA number (1 byte here; 0 = "all")
 *   1F03 = to TA number   (omit for open range)
 *
 * @param {string} password - 6-digit ZVT password
 * @param {object} opts
 *   @param {number} opts.type - receipt type (0x02 merchant / 0x03 customer)
 *   @param {number|null} opts.fromTA - from TA number, or null to omit
 *   @param {number|null} opts.toTA - to TA number, or null to omit
 */
export function buildRepeatReceipt(password, opts = {}) {
  const { type = 0x02, fromTA = null, toTA = null } = opts;
  const pwd = encodePassword(password);
  const tlv = [0x1F, 0x01, 0x01, type & 0xFF];
  if (fromTA !== null && fromTA !== undefined) tlv.push(0x1F, 0x02, 0x01, fromTA & 0xFF);
  if (toTA !== null && toTA !== undefined) tlv.push(0x1F, 0x03, 0x01, toTA & 0xFF);
  const container = Buffer.concat([Buffer.from([0x06, tlv.length]), Buffer.from(tlv)]);
  const payload = Buffer.concat([pwd, container]);
  return buildFrame(0x06, 0x20, payload);
}

/**
 * Parse a single 06 D1 Print-Line payload into its text line.
 * The A960 payload is: <1-byte attribute> <ASCII text>. Returns the text and
 * whether this line marks the end of a receipt section (attribute bit 0x80).
 */
export function parsePrintLine(payload) {
  if (!payload || payload.length === 0) return { text: "", sectionEnd: false };
  const attr = payload[0];
  // Text is CP437-encoded; decode so umlauts survive (was latin1 + ASCII strip,
  // which deleted every German character).
  const text = decodeReceiptText(payload.slice(1)).replace(/\s+$/,"");
  return { text, sectionEnd: (attr & 0x80) !== 0 };
}

// --- ZVT Response Parsing ---

/**
 * Parse a Completion/Status-Information response.
 * Extracts result code (BMP 27) and TLV data.
 */
export function parseCompletion(payload) {
  const result = {
    resultCode: null,
    terminalIdentifier: null,
    serialNumber: null,
    deviceName: null,
    softwareVersion: null,
    deviceState: null,
    tlvRaw: []
  };

  if (payload.length === 0) return result;

  // --- Locate the TLV container and extract identity/device fields ---
  // Status-Enquiry / Registration completions embed a ZVT TLV container that
  // begins with tag 0x06 followed by a (possibly multi-byte) length, e.g.:
  //   00 06 82 01 2d 1F44 04 69 11 81 51 ... E4 23 1F40 ...
  // We scan for that container (or a bare 1F../E0-EF start) and parse from there.
  const tlvStart = findTlvContainerStart(payload);
  if (tlvStart >= 0) {
    try {
      const tlvData = parseTlv(payload.slice(tlvStart));
      if (tlvData.length > 0) {
        result.tlvRaw = tlvData;
        const tid = findTlvTag(tlvData, 0x1F44);
        if (tid) result.terminalIdentifier = bcdDecode(tid);
        const serial = findTlvTag(tlvData, 0x1F42);
        if (serial) result.serialNumber = bcdDecode(serial);
        const devName = findTlvTag(tlvData, 0x1F40);
        if (devName) result.deviceName = devName.toString("ascii").trim();
        const swVer = findTlvTag(tlvData, 0x1F41);
        if (swVer) result.softwareVersion = swVer.toString("ascii").trim();
        const devState = findTlvTag(tlvData, 0x1F43);
        if (devState && devState.length >= 1) result.deviceState = devState[0];
      }
    } catch (_) {}
  }

  // Determine the result code up-front. This terminal frames the
  // Status-Information (04 0F) two ways depending on config/receipt mode:
  //   - long form:  payload starts with BMP 27 result tag: "27 00 29 ..."
  //   - short form: payload starts with a BARE result byte: "00 29 ..."
  // In both cases byte 0 (27-tag value) or byte 0 (bare) is the result.
  let offset = 0;
  if (payload[0] === 0x27 && payload.length > 1) {
    result.resultCode = payload[1];
    offset = 2; // walk BMPs after the 27-tagged result
  } else {
    // Bare leading result byte (not a BMP tag); walk BMPs after it.
    result.resultCode = payload[0];
    offset = 1;
  }

  // Parse BMPs — for TID only. The result code is ALREADY set from the leading
  // byte above and must NOT be overridden by a mid-stream byte that happens to
  // equal 0x27/0x19 (the BMP walk is drift-prone on vendor data). We only look
  // for BMP 29 (Terminal-ID) and stop at TLV; everything else is skipped.
  while (offset < payload.length) {
    const bmp = payload[offset];

    if (bmp === 0x29 && offset + 1 < payload.length) {
      // BMP 29: Terminal ID (4 bytes BCD)
      if (offset + 4 < payload.length) {
        const tidBuf = payload.slice(offset + 1, offset + 5);
        result.terminalIdentifier = bcdDecode(tidBuf);
        offset += 5;
      } else {
        break;
      }
    } else if (bmp === 0x49 && offset + 2 < payload.length) {
      // BMP 49: Currency (2 bytes) — skip
      offset += 3;
    } else if (bmp === 0x04 && offset + 6 < payload.length) {
      // BMP 04: Amount (6 bytes) — skip
      offset += 7;
    } else if (bmp === 0x22 && offset + 2 < payload.length) {
      // BMP 22: various 2 byte — skip
      offset += 3;
    } else if (bmp === 0x1F || bmp === 0xE4 || bmp === 0xE0) {
      // TLV data starts here. Identity/device fields were already extracted
      // above via findTlvContainerStart(); nothing more to do in the BMP walk.
      break;
    } else {
      // Unknown BMP — try to skip (assume 1 byte value for unknown single-byte BMPs)
      offset += 2;
      if (offset > payload.length) break;
    }
  }

  // If no result code found via BMP 27, use first byte as fallback
  if (result.resultCode === null && payload.length > 0) {
    result.resultCode = payload[0];
  }

  return result;
}

// --- Print commands parsing ---

/**
 * Parse Print Text-Block (06 D3) or Print Line (06 D1).
 * 
 * 06 D3 structure:
 *   06 <TLV-container-length> <TLVs>
 *     1F07 = receipt type (01=merchant, 02=customer, 03=admin)
 *     1F37 = receipt information (01=positive auth)
 *     25   = print texts container
 *       09 = print attribute (00=normal, 81=last section)
 *       07 = text line
 *
 * Returns { receiptType, positive, complete, lines }
 */
export function parsePrintCommand(cmdInstr, payload) {
  const result = {
    receiptType: null, // 0x01=merchant, 0x02=customer, 0x03=admin
    positive: false,
    complete: false,
    lines: []
  };

  if (cmdInstr === 0xD3) {
    // Print Text-Block — the payload starts with 06 <length> indicating a TLV container
    let data = payload;
    
    // Check if payload starts with container marker 0x06 (TLV container indicator)
    if (data.length > 2 && data[0] === 0x06) {
      // Skip the container tag and its length. ZVT TLV uses extended length:
      //   0x00..0x7F      -> single-byte length
      //   0x81 <b>        -> length in next 1 byte
      //   0x82 <hi> <lo>  -> length in next 2 bytes (big-endian)
      let p = 1;
      let containerLen = data[p]; p += 1;
      if (containerLen === 0x81) {
        containerLen = data[p]; p += 1;
      } else if (containerLen === 0x82) {
        containerLen = (data[p] << 8) | data[p + 1]; p += 2;
      }
      data = data.slice(p, p + containerLen);
    }

    // Parse the TLV fields within
    let offset = 0;
    while (offset < data.length) {
      if (offset >= data.length) break;

      // Two-byte tags (1Fxx)
      if (data[offset] === 0x1F && offset + 1 < data.length) {
        const tag = (data[offset] << 8) | data[offset + 1];
        offset += 2;
        if (offset >= data.length) break;
        const len = data[offset];
        offset += 1;
        if (offset + len > data.length) break;
        const val = data.slice(offset, offset + len);
        offset += len;

        if (tag === 0x1F07 && val.length >= 1) {
          result.receiptType = val[0];
        } else if (tag === 0x1F37 && val.length >= 1) {
          result.positive = (val[0] === 0x01);
        }
        continue;
      }

      // Tag 25 — print texts container
      if (data[offset] === 0x25) {
        offset += 1;
        if (offset >= data.length) break;
        let containerLength = data[offset];
        offset += 1;
        // Extended length: 0x81 <b> = 1-byte, 0x82 <hi> <lo> = 2-byte length.
        if (containerLength === 0x81) {
          if (offset >= data.length) break;
          containerLength = data[offset];
          offset += 1;
        } else if (containerLength === 0x82) {
          if (offset + 1 >= data.length) break;
          containerLength = (data[offset] << 8) | data[offset + 1];
          offset += 2;
        }
        const end = Math.min(offset + containerLength, data.length);

        // Parse inner tags: 09 (attribute) and 07 (text line)
        while (offset < end) {
          const innerTag = data[offset];
          offset += 1;
          if (offset >= end) break;
          const innerLen = data[offset];
          offset += 1;

          if (innerTag === 0x09) {
            // Print attribute
            const attrVal = data.slice(offset, offset + innerLen);
            offset += innerLen;
            if (attrVal.length >= 1 && (attrVal[0] & 0x80)) {
              result.complete = true;
            }
          } else if (innerTag === 0x07) {
            // Text line (CP437-encoded)
            if (innerLen === 0) {
              result.lines.push("");
            } else {
              result.lines.push(decodeReceiptText(data.slice(offset, offset + innerLen)));
            }
            offset += innerLen;
          } else {
            // Unknown inner tag — skip
            offset += innerLen;
          }
        }
        continue;
      }

      // Single-byte tag with length — skip unknown
      const tag = data[offset];
      offset += 1;
      if (offset >= data.length) break;
      const len = data[offset];
      offset += 1;
      offset += len;
    }
  } else if (cmdInstr === 0xD1) {
    // Print Line — simpler format: [attribute byte] + CP437 text.
    const body = payload.length > 0 ? payload.slice(1) : payload;
    result.lines = [decodeReceiptText(body).replace(/\s+$/,"")];
  }

  return result;
}

// --- Status-Information field extraction (for receipt reconstruction) ---

/**
 * Parse the payment fields from a Status-Information (04 0F) payload.
 *
 * The A960 does not send printable 06 D1/06 D3 receipt blocks; instead the
 * transaction data needed to reconstruct the receipt is carried as BMPs in the
 * 04 0F Status-Information. This walks the known BMPs and returns a structured
 * object. Unknown BMPs are skipped as safely as possible.
 *
 * Fields returned (any may be null if not present):
 *   resultCode        (BMP 27, 1 byte)
 *   terminalId        (BMP 29, 4 byte BCD)
 *   amountMinor       (BMP 04, 6 byte BCD)
 *   traceNumber       (BMP 0B, 3 byte BCD)  -> "TA-Nr."
 *   time              (BMP 0C, 3 byte BCD HHMMSS)
 *   date              (BMP 0D, 2 byte BCD MMDD)
 *   expiry            (BMP 0E, 2 byte BCD)
 *   receiptNumber     (BMP 87, 2 byte BCD)  -> "Beleg-Nr."
 *   cardName          (BMP 8B, LLVAR ASCII) -> e.g. MASTERCARD
 *   additionalText    (BMP 8A, LLLVAR ASCII)-> AS-Proc-Code / Capt.-Ref / AID line
 *   vuNumber          (BMP 2A, 15 byte ASCII) -> "VU-Nummer"
 *   aid               (from TLV 60 -> tag 43) -> EMV AID
 *   currencyCode      (BMP 49, 2 byte BCD)
 */
export function parseStatusInformation(payload) {
  const r = {
    resultCode: null, terminalId: null, amountMinor: null,
    traceNumber: null, time: null, date: null, expiry: null,
    receiptNumber: null, cardName: null, additionalText: null,
    vuNumber: null, aid: null, currencyCode: null, cardPanMasked: null,
    authNumber: null
  };
  if (!payload || payload.length === 0) return r;
  const len = payload.length;

  // --- Phase 1: BMP walk of the fixed-length leading fields ---
  // The A960 04 0F payload is a ZVT BMP sequence. Verified layout from real
  // captures (offsets into payload):
  //   27 <rc>            result code (00 = approved)
  //   29 <tid×4 BCD>     terminal id
  //   04 <amt×6 BCD>     amount (minor units)
  //   0B <trace×3 BCD>   trace number (TA-Nr.)
  //   0C <time×3 BCD>    time HHMMSS
  //   0D <date×2 BCD>    date MMDD
  //   0E <exp×2 BCD>     card expiry
  //   17 <×2>, 19 <×1>   (skipped)
  //   87 <recno×2 BCD>   receipt number (Beleg-Nr.)  — appears later in stream
  // We walk known fixed BMPs and stop cleanly at the first non-fixed/vendor BMP;
  // the remaining vendor fields are recovered by pattern in Phase 3.
  let o = 0;
  // Leading result code: either "27 <rc>" (long form) or a BARE <rc> byte
  // (short form, e.g. "00 29 ..."). Handle both, then walk the fixed BMPs.
  if (payload[0] === 0x27 && len > 1) {
    r.resultCode = payload[1];
    o = 2;
  } else {
    r.resultCode = payload[0];
    o = 1;
  }
  const fixed = {
    0x27: 1, 0x29: 4, 0x04: 6, 0x0B: 3, 0x0C: 3, 0x0D: 2, 0x0E: 2,
    0x17: 2, 0x19: 1, 0x87: 2, 0x49: 2
  };
  while (o < len) {
    const bmp = payload[o];
    const n = fixed[bmp];
    if (n === undefined || o + n >= len) break; // hit vendor/variable field
    const val = payload.slice(o + 1, o + 1 + n);
    switch (bmp) {
      case 0x27: r.resultCode = val[0]; break;
      case 0x29: r.terminalId = bcdDecode(val); break;
      case 0x04: r.amountMinor = parseInt(bcdDecode(val), 10); break;
      case 0x0B: r.traceNumber = bcdDecode(val); break;
      case 0x0C: r.time = bcdDecode(val); break;
      case 0x0D: r.date = bcdDecode(val); break;
      case 0x0E: r.expiry = bcdDecode(val); break;
      case 0x87: r.receiptNumber = bcdDecode(val); break;
      case 0x49: r.currencyCode = parseInt(bcdDecode(val), 10); break;
      // 0x17, 0x19: skipped
    }
    o += 1 + n;
  }
  // BMP 87 (Beleg-Nr.) and 49 (currency) may appear after variable fields we
  // stopped at; recover them by scanning for their tag at a stable position.
  if (r.receiptNumber === null) {
    const i = payload.indexOf(0x87);
    if (i >= 0 && i + 2 < len) r.receiptNumber = bcdDecode(payload.slice(i + 1, i + 3));
  }

  // --- Phase 2: EMV AID from the trailing TLV container (tag 60 -> 43) ---
  const tlvStart = findTlvContainerStart(payload);
  if (tlvStart >= 0) {
    try {
      const tlv = parseTlv(payload.slice(tlvStart));
      const aid = findTlvTag(tlv, 0x43);
      if (aid) r.aid = aid.toString("hex").toUpperCase();
    } catch (_) {}
  }

  // --- Phase 3: pattern-based extraction of vendor fields (resync-proof) ---
  const full = payload.toString("latin1");

  // Card scheme name (BMP 8B region)
  const mCard = full.match(/\b(DEBIT MASTERCARD|AMERICAN EXPRESS|MASTERCARD|MAESTRO|V ?PAY|VISA|GIROCARD|JCB|DINERS|DISCOVER|UNIONPAY)\b/i);
  if (mCard) r.cardName = mCard[1].toUpperCase();

  // Additional text line (BMP 8A): "AS-Proc-Code = .. /Capt.-Ref.= .. /AID..: .."
  const mAdd = full.match(/AS-Proc-Code[\s\S]*?AID\d*\s*:\s*\d+/i);
  if (mAdd) r.additionalText = mAdd[0].replace(/[^\x20-\x7e]/g, " ").replace(/\s+/g, " ").trim();

  // Masked PAN. If the terminal sent it as text (####5373) use that; otherwise
  // recover the last-4 digits that follow the 84 <blocked-PAN> BMP and render a
  // masked form like the printed receipt ("############5373").
  const mPanText = full.match(/[#*]{4,}\d{2,4}/);
  if (mPanText) {
    r.cardPanMasked = mPanText[0];
  } else {
    // 84 ee ee ee <last4 as 2 BCD bytes>, e.g. 84 ee ee ee 53 73 -> "5373"
    const i84 = payload.indexOf(0x84);
    if (i84 >= 0 && i84 + 6 <= len) {
      const tail = payload.slice(i84 + 4, i84 + 6); // 2 bytes after 3 filler bytes
      const last4 = bcdDecode(tail);
      if (/^\d{4}$/.test(last4)) r.cardPanMasked = "############" + last4;
    }
  }

  // VU-Nummer: 9-15 digit contract number appearing just before the card name
  const mVu = full.match(/(\d{9,15})(?=\D*(?:MASTERCARD|MAESTRO|VISA|V ?PAY|GIROCARD|AMERICAN|JCB|DINERS|DISCOVER|UNIONPAY))/i);
  if (mVu) r.vuNumber = mVu[1];

  // Authorisation number: the value in the AID line (AID..: NNNNNN),
  // which equals "Autorisierungsnummer" on the printed receipt.
  const mAuth = full.match(/AID\d*\s*:\s*(\d{4,8})/i);
  if (mAuth) r.authNumber = mAuth[1];

  return r;
}

/**
 * Format a card-payment receipt (matching the A960's own printed layout) from
 * parsed Status-Information fields + a merchant header supplied by the caller.
 *
 * @param {object} si - result of parseStatusInformation()
 * @param {object} opts
 *   @param {string[]} opts.headerLines - merchant header lines (from cashier companyinfo)
 *   @param {"merchant"|"customer"} opts.copyType
 *   @param {number} opts.width - printer character width (default 32)
 * @returns {string[]} receipt text lines
 */
export function formatReceipt(si, opts = {}) {
  const headerLines = opts.headerLines || [];
  const copyType = opts.copyType === "customer" ? "customer" : "merchant";
  const width = opts.width || 32;

  const center = (s) => {
    s = String(s);
    if (s.length >= width) return s;
    const pad = Math.floor((width - s.length) / 2);
    return " ".repeat(pad) + s;
  };
  const lr = (l, r) => {
    l = String(l); r = String(r);
    const space = width - l.length - r.length;
    return space > 0 ? l + " ".repeat(space) + r : l + " " + r;
  };
  const spacedTitle = (t) => t.split("").join("-"); // matches "K-U-N-D-E-N-B-E-L-E-G" style

  const lines = [];
  lines.push(center(spacedTitle(copyType === "customer" ? "KUNDENBELEG" : "HAENDLERBELEG")));
  for (const h of headerLines) lines.push(center(h));
  lines.push("");

  if (si.cardName) lines.push(center(`Bezahlung ${si.cardName}`));
  lines.push("");

  // Amount
  const eur = si.amountMinor != null ? (si.amountMinor / 100).toFixed(2).replace(".", ",") + " EUR" : "";
  lines.push(lr("Betrag", eur));
  lines.push("");

  // Date/time + TID
  const dt = formatDateTime(si.date, si.time);
  lines.push(`${dt}  T-ID ${si.terminalId || ""}`.trim());
  lines.push(lr(`TA-Nr. ${si.traceNumber || ""}`, `Beleg-Nr. ${si.receiptNumber || ""}`));
  if (si.cardPanMasked) lines.push(lr("Kartennr.", si.cardPanMasked));
  if (si.vuNumber) lines.push(lr("VU-Nummer", si.vuNumber));
  if (si.authNumber) lines.push(lr("Autorisierungsnummer", si.authNumber));
  if (si.resultCode != null) lines.push(lr("Autorisierungsantwortcode", String(si.resultCode).padStart(2, "0")));
  if (si.aid) { lines.push("EMV-Daten:"); lines.push(si.aid); }
  lines.push("");
  if (si.additionalText) {
    for (const seg of si.additionalText.split("/")) lines.push(seg.trim());
  }
  lines.push("");
  const approved = si.resultCode === 0x00;
  lines.push(center(approved ? "**  Autorisierung erfolgt  **" : "**  Autorisierung abgelehnt  **"));

  return lines;
}

function formatDateTime(dateMMDD, timeHHMMSS) {
  let d = "", t = "";
  if (dateMMDD && dateMMDD.length >= 4) {
    const mm = dateMMDD.slice(0, 2), dd = dateMMDD.slice(2, 4);
    const yyyy = new Date().getFullYear();
    d = `${dd}.${mm}.${yyyy}`;
  }
  if (timeHHMMSS && timeHHMMSS.length >= 6) {
    t = `${timeHHMMSS.slice(0, 2)}:${timeHHMMSS.slice(2, 4)}:${timeHHMMSS.slice(4, 6)}`;
  }
  return `${d} ${t}`.trim();
}
