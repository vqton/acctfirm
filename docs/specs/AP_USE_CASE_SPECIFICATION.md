# Use Case Specification

## Accounts Payable Management — TK 331 Under Circular 99/2025/TT-BTC

**Version:** 1.0
**Last Updated:** 2026-05-15
**Regulatory Basis:** Circular 99/2025/TT-BTC, Law on Accounting 2015

---

## 1. Source

- **URL:** https://ketoanthienung.net/hach-toan-tk-331-phai-tra-cho-nguoi-ban-theo-thong-tu-99.htm
- **Domain Context:** Accounts Payable (Phải trả người bán) accounting under Circular 99/2025/TT-BTC, covering the full lifecycle of supplier debt: purchase on credit, payment, prepayment, settlement discounts, purchase returns, import purchases, construction contractor payables, consignment agency payables, and unidentifiable creditor write-off.
- **Regulatory Context:** Circular 99/2025/TT-BTC — TK 331 (Phải trả cho người bán), VAS 02 (Inventory), VAS 14 (Revenue), Law on Accounting 2015 Article 24 (accounting books). TK 331 can have both credit balance (payables) and debit balance (prepayments). At period-end, debit balances are reclassified to assets on BC 01.
- **Analysis Summary:** The source defines 11 journal entry scenarios covering the complete AP lifecycle: initial recognition of supplier debt (domestic purchase, import, construction, services), settlement (payment, prepayment, discount, return), adjustments (price correction, FC revaluation), and extinguishment (unidentifiable creditor write-off). Each scenario involves Dr/Cr to TK 331 with corresponding entries to inventory (152/156), VAT (133), cash (111/112), or expense accounts. The account is tracked per-supplier (chi tiết từng đối tượng) and supports dual balance presentation on BC 01 (prepayments as assets, payables as liabilities).

---

## 2. Domain Breakdown

### Domain 1: Supplier Invoice Processing

#### UC-001: Record Supplier Invoice (Domestic Purchase on Credit)

**Description:** Record a supplier invoice for goods, raw materials, fixed assets, or services purchased on credit. The payable is recognized at the total invoice amount including VAT. Input VAT is recorded separately if deductible.

**Goal:** Accurately recognize supplier debt and corresponding asset/expense at the time of invoice receipt.

**Primary Actors:** Accountant, AP Clerk

**Supporting Actors:** Purchasing Department, Supplier

**Preconditions:**
- Supplier exists in master data
- Goods received (if inventory purchase) or service rendered
- Invoice received from supplier

**Trigger:**
- Supplier invoice received for goods, materials, FA, or services

**Main Flow:**
1. Accountant receives supplier invoice and supporting documents (PO, goods receipt note)
2. Accountant verifies: quantity, price, VAT rate, deductibility status
3. System determines VAT treatment:
   - If VAT deductible: record asset/expense at net price, input VAT in TK 133 separately
   - If VAT non-deductible: record asset/expense at gross amount (including VAT)
4. System records journal entry:
   - Dr 152/153/156/157/211/213/241/627/641/642 (net amount)
   - Dr 133 (input VAT, if deductible)
   - Cr 331 (total payable)
5. System updates supplier balance (credit side increases)

**Alternate Flow:**
- **Import purchase:** Record import duties, excise tax, environmental tax as part of inventory cost (Dr 152/156 — Cr 331/333). VAT on imports recorded separately (Dr 133 — Cr 3331). Additional VAT treatment per import status.
- **Construction contractor:** Based on handover protocol and construction invoice. Dr 241 (CIP) — Cr 331.
- **Services (utilities, consulting, audit):** Dr 241/242/627/641/642 — Cr 331.
- **Goods received before invoice:** Record as goods in transit (TK 151) at period-end if invoice not yet received.

**Postconditions:**
- Supplier payable recorded
- Inventory/asset/expense updated
- Input VAT recorded (if deductible)

**Exception Flow:**
- If received quantity ≠ invoiced quantity: flag for investigation before posting
- If VAT rate on invoice doesn't match tax code: reject
- If supplier is not in master data: require supplier creation first

**Business Rules:**
- BR01: TK 331 is credited at total payable amount including VAT (Article 2.1)
- BR02: Deductible VAT recorded separately in TK 133; non-deductible VAT included in cost (Article 2.1a)
- BR03: Import duties, excise tax, environmental tax included in inventory cost (Article 2.1b)
- BR04: Each supplier tracked separately (hạch toán chi tiết từng đối tượng)
- BR05: Settlement discounts (chiết khấu thanh toán) recorded as finance income (TK 515), not cost reduction (Article 2.6)
- BR06: Trade discounts and post-purchase rebates reduce AP (Article 2.6, 2.7)

**Input Data:**
- Supplier ID, invoice number, invoice date
- Item codes, quantities, unit prices
- VAT rate, VAT amount, deductibility flag
- Add-on costs (freight, insurance, duty for imports)

**Output Data:**
- AP balance increased (per supplier)
- Inventory/asset/expense recorded
- Input VAT recorded (if deductible)

**Dependencies:**
- Supplier master data (existing)
- Item/inventory master data
- COA configured with TK 331, TK 133

**Frequency:** Daily (high volume)

**Priority:** Critical

---

#### UC-002: Record Supplier Invoice (Foreign Currency Purchase)

**Description:** Record a supplier invoice denominated in foreign currency. The payable is recorded at the spot exchange rate on the transaction date. Prepayments are locked at the prepayment date rate.

**Goal:** Ensure accurate VND measurement of FC-denominated payables.

**Primary Actors:** Accountant

**Supporting Actors:** Bank, Customs Broker

**Preconditions:**
- Invoice received denominated in foreign currency
- Exchange rate observable at transaction date
- Supplier master data exists

**Trigger:**
- FC supplier invoice received

**Main Flow:**
1. Accountant records invoice at spot exchange rate on invoice date (Article 2.1b)
2. If prepayment was made: prepaid portion uses prepayment date rate; remaining uses transaction date rate
3. Inventory cost recorded at the spot rate; import duties at tax authority rate
4. At period-end: FC payable revalued at closing rate (Article 2.1 — Bên Nợ/Bên Có revaluation entries)

**Business Rules:**
- BR07: FC invoice recorded at spot rate on transaction date
- BR08: Prepayment rate locked at prepayment date
- BR09: Period-end revaluation: rate increase → Cr 331 increase; rate decrease → Dr 331 decrease

**Priority:** High

---

### Domain 2: Supplier Payment Processing

#### UC-003: Pay Supplier

**Description:** Process payment to settle supplier debt via cash, bank transfer, or borrowing. Reduce the corresponding supplier's AP balance.

**Goal:** Accurately record cash outflow and reduce supplier obligation.

**Primary Actors:** Accountant, Cashier

**Preconditions:**
- AP balance exists for the supplier (credit balance)
- Sufficient cash/bank balance
- Payment authorized per delegation matrix

**Trigger:**
- Payment due date
- Early payment to capture settlement discount

**Main Flow:**
1. Accountant selects supplier and outstanding invoice(s) to pay
2. System matches payment to specific invoice(s)
3. If early payment discount applies: record discount as finance income (TK 515)
4. System records journal entry:
   - Dr 331 (total payment amount)
   - Cr 111/112/341 (cash/bank/borrowing)
5. System reduces supplier balance (debit side decreases credit balance)
6. Cash book updated

**Alternate Flow:**
- **Partial payment:** Record partial settlement against specific invoice; remaining balance tracked
- **Prepayment (advance payment):** Dr 331 — Cr 111/112. Supplier balance shows debit (prepayment asset).
- **Payment via borrowing:** Dr 331 — Cr 341 (loan proceeds paid directly to supplier).

**Postconditions:**
- Supplier AP balance reduced
- Cash/bank balance decreased
- Settlement discount recorded (if applicable)

**Business Rules:**
- BR10: Payment reduces TK 331 on the debit side (Article 2.3)
- BR11: Prepayment creates debit balance on TK 331; shown as asset on BC 01 (Article 2.3)
- BR12: Settlement discount recorded as TK 515 income, not AP reduction of original amount (Article 2.6)
- BR13: Payment must reference specific invoice for aging accuracy

**Priority:** Critical

---

#### UC-004: Record Supplier Prepayment

**Description:** Record an advance payment to a supplier before goods/services are delivered. The prepayment is tracked within TK 331 as a debit balance (not a separate account).

**Goal:** Track advance payments recoverable from suppliers.

**Primary Actors:** Accountant

**Preconditions:**
- Purchase agreement with prepayment terms

**Trigger:**
- Prepayment made per contract terms

**Main Flow:**
1. Accountant records prepayment:
   - Dr 331 (advance to supplier)
   - Cr 111/112 (cash/bank)
2. System shows supplier with debit balance (prepayment)
3. When goods received: Dr inventory — Cr 331 (clears advance + records remaining payable)

**Alternative Flows:**
None documented

**Postconditions:**
- Prepayment recorded as Dr balance on TK 331
- Cash/bank decreased
- When goods received: prepayment cleared, remaining payable recorded

**Business Rules:**
- BR14: Prepayment recorded as Dr 331, not a separate prepaid account (Article 2.3)
- BR15: Prepayment shown as asset on BC 01 (Phải trả NB — số dư Nợ chi tiết)
- BR16: When supplier fails to deliver, refund reverses: Dr 111/112 — Cr 331 (Article 2.4)

**Priority:** High

---

### Domain 3: AP Adjustments

#### UC-005: Process Purchase Return

**Description:** Return defective or non-conforming goods to the supplier. Reverse the original purchase: reduce AP, reverse inventory and input VAT.

**Goal:** Correctly reverse the original purchase transaction for returned goods.

**Primary Actors:** Accountant

**Preconditions:**
- Original purchase recorded
- Supplier agrees to return
- Debit note issued

**Trigger:**
- Goods rejected at inspection
- Quality non-conformance discovered
- Excess quantity returned

**Main Flow:**
1. Accountant initiates return referencing original invoice
2. System records:
   - Dr 331 (reduce payable by gross amount)
   - Cr 133 (reverse input VAT, if previously claimed)
   - Cr 152/153/156 (reduce inventory at cost)
3. Supplier balance reduced
4. Inventory decreased

**Alternate Flow:**
- **Goods already consumed in production:** Allocate return credit proportionally: Dr 331 — Cr 154/621/627/641/642 (cost accounts) and Cr 133 (VAT reversal).

**Postconditions:**
- Supplier AP balance reduced
- Inventory decreased
- Input VAT reversed (if previously claimed)

**Business Rules:**
- BR17: Purchase return reverses original entry: Dr 331 — Cr 133 — Cr inventory (Article 2.7)
- BR18: Return must reference original invoice for audit trail
- BR19: VAT adjustment required when input VAT was previously claimed

**Priority:** High

---

#### UC-006: Record Supplier Discount or Rebate

**Description:** Record settlement discount (early payment discount), trade discount, or post-purchase rebate received from supplier. Settlement discount is finance income; trade discount reduces AP.

**Goal:** Properly classify discounts per accounting nature.

**Primary Actors:** Accountant

**Main Flow:**
1. Accountant identifies discount type:
   - **Settlement discount** (early payment): Dr 331 — Cr 515 (finance income) — Article 2.6
   - **Trade discount/rebate** (post-purchase): Dr 331 — Cr 152/153/156 (reduce inventory cost proportionally) — Article 2.7
2. Supplier balance reduced accordingly

**Alternative Flows:**
None documented

**Postconditions:**
- Supplier balance reduced
- Finance income recorded (settlement discount) or inventory cost reduced (trade discount)

**Business Rules:**
- BR20: Settlement discount = finance income (TK 515), never cost reduction (Article 2.6)
- BR21: Trade discount/rebate = reduce inventory cost or expense (Article 2.7)
- BR22: Discount must be documented per supplier agreement

**Priority:** Medium

---

#### UC-007: Revalue Foreign Currency AP at Period-End

**Description:** At period-end, revalue all FC-denominated AP balances at the closing exchange rate. Record unrealized FX gain/loss through TK 413.

**Goal:** Ensure AP balances reflect current exchange rates per VAS 10.

**Primary Actors:** Accountant

**Main Flow:**
1. Identify all AP balances in FC (per supplier detail)
2. Apply period-end closing rate
3. For each supplier:
   - If rate increased: Dr 635 (or Cr 413) — Cr 331 (loss)
   - If rate decreased: Dr 331 — Cr 515 (or Dr 413) (gain)
4. Net difference through P&L or equity per VAS 10

**Alternative Flows:**
None documented

**Postconditions:**
- AP balances updated to closing rate
- FX gain/loss recorded

**Business Rules:**
- BR23: FC AP revalued at period-end closing rate (Article 2.1)
- BR24: Rate increase → increase AP (Cr 331); rate decrease → decrease AP (Dr 331)

**Priority:** High

---

#### UC-008: Write Off Unidentifiable Creditor Balance

**Description:** When a supplier credit balance cannot be settled because the creditor is unidentifiable or confirmed as no longer owed, write off to other income.

**Goal:** Clear stale AP balances from the ledger.

**Primary Actors:** Chief Accountant

**Preconditions:**
- AP balance older than statutory retention period
- Reasonable effort made to locate creditor

**Trigger:**
- Periodic AP aging cleanup
- Statute of limitations expired

**Main Flow:**
1. Chief Accountant reviews aged AP balances
2. For balances where creditor cannot be identified or is confirmed as no longer owed:
   - Dr 331 — Cr 711 (other income) — Article 2.8
3. Supplier balance zeroed

**Alternative Flows:**
None documented

**Postconditions:**
- AP balance cleared
- Other income recorded

**Business Rules:**
- BR25: Write-off requires Chief Accountant approval (Article 2.8)
- BR26: Write-off recorded as other income (TK 711), not reduction of COGS

**Priority:** Low

---

### Domain 4: AP Reporting

#### UC-009: Generate AP Aging Report

**Description:** Produce supplier aging report showing outstanding balances by aging bucket (current, 30-day, 60-day, 90-day, 120+ day overdue). Key input for cash flow forecasting and internal control.

**Goal:** Monitor supplier payment status and identify overdue items.

**Primary Actors:** Accountant, Financial Manager

**Main Flow:**
1. System queries TK 331 balance per supplier with credit balance (outstanding payables)
2. For each supplier, system ages the balance by invoice due date
3. Aging buckets: 0-30 days, 31-60 days, 61-90 days, 91-120 days, 120+ days
4. System also identifies debit balance suppliers (prepayments) separately
5. Report generated for review and payment planning

**Alternative Flows:**
None documented

**Postconditions:**
- Aging report generated
- Prepayments identified separately

**Business Rules:**
- BR27: AP aging reports by invoice due date, not by transaction date
- BR28: Prepayments (Dr balance) shown separately from payables (Cr balance)
- BR29: BC 01 presentation: Dr balance → Tài sản (Mã số 132), Cr balance → Nợ phải trả (Mã số 311/331)

**Priority:** High

---

#### UC-010: Generate Supplier Statement

**Description:** Produce a statement of transactions and balance for a specific supplier over a period. Used for reconciliation with supplier's own records.

**Goal:** Facilitate supplier reconciliation and dispute resolution.

**Primary Actors:** Accountant, AP Clerk

**Main Flow:**
1. Select supplier and date range
2. System lists all transactions: invoices, payments, returns, discounts, adjustments
3. Opening balance, period activity, closing balance
4. Report used to confirm balance with supplier

**Alternative Flows:**
None documented

**Postconditions:**
- Supplier statement generated

**Priority:** Medium

---

## 3. Cross-Use Case Analysis

### End-to-End AP Lifecycle

```
UC-001: Record Supplier Invoice (Dr Inventory — Cr 331)
    │
    ├── UC-002: FC variant (different rate handling)
    │
    ├── UC-005: Purchase Return (Dr 331 — Cr Inventory + VAT reversal)
    │
    ├── UC-006: Discount/Rebate (Dr 331 — Cr 515)
    │
    ├── UC-003: Pay Supplier (Dr 331 — Cr 111/112)
    │
    └── UC-007: Periodic FC Revaluation
    │
    └── UC-008: Write-off (Dr 331 — Cr 711)
    
UC-004: Prepayment (Dr 331 — Cr 111) — separate flow before UC-001
    
UC-009: AP Aging Report — reads all UC-001 to UC-008 transactions
UC-010: Supplier Statement — reads per-supplier transaction history
```

### Overlapping Use Cases
- UC-003 (Payment) and UC-006 (Discount): Settlement discount is deducted from payment and recorded simultaneously
- UC-005 (Return) and UC-001 (Invoice): Return reverses the original invoice
- UC-007 (FC Revaluation) applies to all FC-denominated UC-001 transactions
- UC-009 (Aging) consumes data from all transaction UCs

### Shared Dependencies
- **Supplier detail tracking (chi tiết):** Every transaction references a specific supplier. Required for proper BC 01 presentation (Dr balances → assets, Cr balances → liabilities).
- **Invoice-level payment reference:** UC-003 (Payment) should reference UC-001 (Invoice) for accurate aging.

### Workflow Gaps
- No explicit UC for **purchase order processing** (PO → goods receipt → invoice matching — 3-way match)
- No explicit UC for **debit note management** (supplier-issued credit note processing)
- No explicit UC for **intercompany AP** (between related entities)
- No explicit UC for **AP hold/release** (disputed invoices)

---

## 4. Missing Functionalities

### Missing Use Cases
| Use Case | Description | Priority |
|---|---|---|
| 3-Way PO Match | Match purchase order → goods receipt → invoice before AP posting | High |
| Debit Note Processing | Record supplier-issued credit notes automatically | Medium |
| Payment Batch Processing | Process multiple supplier payments from a single bank transfer file | Medium |
| Electronic AP Integration | Auto-import supplier e-invoices via GDT API | Medium |

### Missing Validation Rules
- Duplicate invoice number from same supplier → flag
- Invoice total ≠ sum of line items → reject
- Invoice amount exceeds PO amount beyond tolerance → flag for approval
- Payment exceeds AP balance for the supplier → warn
- Supplier with debit balance > X days → flag prepayment aging

### Missing Approval Flows
- Payment above threshold requires dual authorization (approver + chief accountant)
- Supplier master creation/update requires purchasing manager approval
- Write-off of AP (UC-008) requires Chief Accountant + CFO approval
- One-time supplier (no master record) requires override

### Missing Audit Trails
- Invoice changes after initial posting: before/after values
- Payment-to-invoice matching changes
- Supplier master data changes (bank account, address, tax code)

---

## 5. Recommended System Modules

| Module | Responsibility |
|---|---|
| **Invoice Processing Engine** | Record supplier invoices, 3-way PO match, import handling, VAT split |
| **Payment Engine** | Single/partial/batch payment, settlement discount, prepayment |
| **AP Adjustment Engine** | Returns, discounts, rebates, FC revaluation, write-off |
| **AP Aging & Reporting** | Aging report, supplier statement, cash flow forecasting input |
| **Supplier Master Data** | Supplier creation, banking details, tax codes, payment terms |

---

## 6. Suggested Improvements

### Business Improvements
1. **3-way automated matching:** Match PO → goods receipt → invoice to reduce manual verification.
2. **Payment scheduling:** Auto-calculate due dates from invoice date + payment terms; flag items due for payment.

### Process Improvements
1. **Early payment discount optimization:** Flag invoices where early payment discount exceeds cost of capital.
2. **Supplier portal:** Allow suppliers to view their AP balance and payment status online.

### Technical Improvements
1. **Invoice-level tracking:** Every AP transaction references a specific invoice for accurate aging.
2. **Sub-ledger integration:** TK 331 balance = sum of all supplier balances. General ledger = sub-ledger reconciliation enforced.

### Compliance Improvements
1. **De minimis reporting:** Prepayments (Dr balance on TK 331) automatically reclassified to "Trả trước cho người bán" on BC 01 (Mã số 132/212).
2. **Retention tracking:** AP write-off (UC-008) requires documentation of creditor location efforts.
3. **Tax authority rate feed:** For FC AP revaluation (UC-007), use the official commercial bank rate as prescribed.

---

*Document generated via BA analysis of Circular 99/2025/TT-BKC Article 2 — TK 331, covering 11 journal entry scenarios from a single source. 10 use cases extracted. All accounting postings follow the Dr/Cr patterns prescribed in the source material.*
