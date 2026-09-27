/**
 * Synthetic Receipt Generator for Test Mode
 * 
 * Generates binary 06 D3 frames identical to what a real A920 terminal would produce.
 * Used when ZVT_TEST_INJECT_RECEIPTS=true and the simulator doesn't provide receipts.
 * 
 * The generated frames are fed through the normal receipt parser — no special path.
 */

/**
 * Generate synthetic merchant and customer receipt frames.
 * @param {object} tx - Transaction data { terminalId, amountMinor, requestId }
 * @returns {{ merchantFrame: Buffer, customerFrame: Buffer }}
 */
export function generateSyntheticReceipts(tx) {
  const terminalId = tx.terminalIdentifier || "29001234";
  const amount = formatAmount(tx.amountMinor);
  const receiptNr = "0001";

  const merchantLines = [
    "CAFE KOMINE",
    "HAENDLERBELEG",
    "",
    `TERMINAL-ID: ${terminalId}`,
    `BELEG-NR: ${receiptNr}`,
    `BETRAG EUR ${amount}`,
    "ZAHLUNG ERFOLGT"
  ];

  const customerLines = [
    "CAFE KOMINE",
    "KUNDENBELEG",
    "",
    `TERMINAL-ID: ${terminalId}`,
    `BELEG-NR: ${receiptNr}`,
    `BETRAG EUR ${amount}`,
    "ZAHLUNG ERFOLGT"
  ];

  return {
    merchantFrame: buildPrintTextBlock(0x01, merchantLines),
    customerFrame: buildPrintTextBlock(0x02, customerLines)
  };
}

/**
 * Build a complete 06 D3 Print Text-Block frame.
 * @param {number} receiptType - 0x01=merchant, 0x02=customer
 * @param {string[]} lines - Text lines
 * @returns {Buffer} Complete APDU frame
 */
function buildPrintTextBlock(receiptType, lines) {
  // Build the inner content of tag 25 (print texts)
  const textParts = [];

  // Opening attribute: 09 01 00 (normal text)
  textParts.push(Buffer.from([0x09, 0x01, 0x00]));

  // Text lines: 07 <len> <ascii>
  for (const line of lines) {
    const textBuf = Buffer.from(line, "ascii");
    textParts.push(Buffer.from([0x07, textBuf.length]));
    if (textBuf.length > 0) textParts.push(textBuf);
  }

  // Closing attribute: 09 01 81 (end of receipt)
  textParts.push(Buffer.from([0x09, 0x01, 0x81]));

  const tag25Content = Buffer.concat(textParts);

  // Build the TLV container content
  const tlvParts = [];

  // 1F07 receipt type
  tlvParts.push(Buffer.from([0x1F, 0x07, 0x01, receiptType]));

  // 1F37 receipt information (01 = positive auth)
  tlvParts.push(Buffer.from([0x1F, 0x37, 0x01, 0x01]));

  // 25 <length> <content>
  tlvParts.push(Buffer.from([0x25, tag25Content.length]));
  tlvParts.push(tag25Content);

  const tlvContent = Buffer.concat(tlvParts);

  // Wrap in container: 06 <length> <tlvContent>
  const containerPayload = Buffer.concat([
    Buffer.from([0x06, tlvContent.length]),
    tlvContent
  ]);

  // Build APDU: 06 D3 <length> <containerPayload>
  const totalLength = containerPayload.length;
  let frame;
  if (totalLength <= 254) {
    frame = Buffer.alloc(3 + totalLength);
    frame[0] = 0x06;
    frame[1] = 0xD3;
    frame[2] = totalLength;
    containerPayload.copy(frame, 3);
  } else {
    frame = Buffer.alloc(5 + totalLength);
    frame[0] = 0x06;
    frame[1] = 0xD3;
    frame[2] = 0xFF;
    frame.writeUInt16BE(totalLength, 3);
    containerPayload.copy(frame, 5);
  }

  return frame;
}

/**
 * Format amount from cents to EUR display string.
 * 2290 → "22,90"
 */
function formatAmount(amountMinor) {
  const euros = Math.floor(amountMinor / 100);
  const cents = amountMinor % 100;
  return `${euros},${cents.toString().padStart(2, "0")}`;
}
