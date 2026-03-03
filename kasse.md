# POS Layout & Interaction Specification
Version: 1.0  
Purpose: Deterministic layout + interaction definition for Codex implementation  

---

# 1. Layout Definition

## Grid Structure

Root grid: **5 columns × 5 rows**

### Columns
- Column 1–4 = 80% total width
- Column 5     = 20% total width

### Rows
- Row 1       = 20% total height
- Row 2–5     = 80% total height

---

## Area Mapping

| Area | Grid Position |
|------|--------------|
| OL   | Row 1, Col 1–4 |
| OR   | Row 1, Col 5 |
| URL  | Row 2–5, Col 1–2 |
| URR  | Row 2–5, Col 3–4 |
| UR   | Row 2–5, Col 5 |

---

# 2. ASCII Layout Overview
| OL (80% x 20%)                    | OR (20% x 20%) |
| URL (40% x 80%) | URR (40% x 80%) | UR (20% x 80%) |
|                 |                 |                |

---

# 3. Functional Specification by Area

---

## OL (Header Left – 80% width × 20% height)

### Purpose
Session control and contextual information.

### Structure
Horizontal layout:
- Left aligned group:
  - Text: `Kasse`
  - Button: `Start`
  - Button: `Bestellung`
- Right aligned:
  - Button: `Menu`
- Second row (below header line):
  - Left aligned large text: `Tischname`

### Actions

#### Start
- Reset current session
- Clear Bon (URR)
- Reset state to initial
- go to start screen

#### Bestellung
- Switch to order screen for currently active table

#### Menu
- Open system/main menu

#### Tischname
- Displays active table
- click → change table

---

## OR (Header Right – 20% width × 20% height)

### Purpose
show Tischname (or to go) as button
- Displays active table
- click → change table

---

## URL (Order List – 40% width × 80% height)

### Header
- Left: Tischname
- Right: Button `+alles`

### Content
Scrollable vertical list  
Each entry is a full-width Button

### Actions

#### Click Product Button
- Add product to Bon (URR)
- If already present → increase quantity

#### +alles
- Add all visible products to Bon
#### Tischname
- select new unpayed table
### Behavior
- Independent scrolling
- No layout shift of other areas

---

## URR (Bon – 40% width × 80% height)

### Header
- Left: `Bon`
- Right: Button `-alles`

### Content
Scrollable vertical list  
Each entry is a full-width Button

### Actions

#### Click Bon Position
- Decrease quantity OR
- Remove position if quantity = 1

#### -alles
- Clear entire Bon

### Behavior
- Independent scrolling

---

## UR (Payment Area – 20% width × 80% height)

### 1. Toggle Section

#### Bewirtungsbeleg (Toggle)
- Default: OFF
- ON → enables business invoice mode
- State affects receipt data

---

### 2. Payment Methods Section

For each payment method:

Structure:
- Button: `Zahlungsart`
- Below it (slightly indented): Button `Bondruck`
- Divider line between payment method blocks

---

### Actions

#### Zahlungsart
- Execute payment with selected method
- Close Bon
- Reset to initial state

#### Bondruck
- Execute payment with payment method in Block (above)
- Immediately print receipt
- Reset to initial state

### Validation Rules
- If Bon empty → disable all payment buttons
- Payment resets URL/URR state
- After Finished payment if all is payed (URL List is empty) returns to start screen
---

# 4. Global Interaction Logic

## Flow

1. Products selected in URL
2. Items appear in URR (Bon)
3. Bon modified in URR
4. Payment executed in UR
5. Session reset

---

# 6. Implementation Constraints

- No absolute positioning
- URL, URR, UR must scroll independently
- No layout shifting during scroll

---

# End of Specification
