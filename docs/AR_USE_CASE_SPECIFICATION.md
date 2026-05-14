# Use Case Specification

## Accounts Receivable Management — TK 131 Under Circular 99/2025/TT-BTC

---

## 1. Source

- **URL:** https://ketoanthienung.net/hach-toan-tk-131-phai-thu-cua-khach-hang-theo-thong-tu-99.htm
- **Domain Context:** Accounts Receivable (Phải thu khách hàng) accounting under Circular 99/2025/TT-BTC, covering the full lifecycle of customer debt: sales on credit, customer payment, prepayment, sales returns, trade discounts, settlement discounts, construction progress billing, barter transactions, bad debt write-off, and export entrusted fees.
- **Regulatory Context:** Circular 99/2025/TT-BTC — TK 131 (Phải thu của khách hàng), VAS 14 (Revenue), VAS 21 (Presentation of FS). TK 131 is an asset account (debit-normal) but can have credit balances (customer prepayments). At period-end, credit balances are reclassified to liabilities on BC 01. Aging classification (current vs. long-term) is based on the 12-month/operating cycle rule. Bad debt provision follows TK 2293.
- **Analysis Summary:** The source defines 9 journal entry scenarios covering the complete AR lifecycle: initial recognition (sales on credit with indirect tax separation), collection (cash/ bank receipt), adjustments (sales returns, trade discounts, settlement discounts), construction contracts (progress billing, performance bonuses, compensation), barter (goods exchange), and extinguishment (bad debt write-off via TK 2293). Each scenario involves Dr/Cr to TK 131 with corresponding entries to revenue (511), cash (111/112), taxes (333), or expense accounts. The account is tracked per-customer and supports dual balance presentation on BC 01 (prepayments as liabilities, AR as assets).

---

## 2. Domain Breakdown

### Domain 1: Sales Invoice Processing

#### UC-001: Record Sales Invoice on Credit

**Description:** Record a sales invoice for goods, products, or services sold to a customer on credit. Recognize revenue and indirect taxes (VAT, excise, export duty) separately. The receivable is recorded at the total invoice amount.

**Goal:** Accurately recognize customer receivable and corresponding revenue at the time of sale.

**Primary Actors:** Accountant, AR Clerk

**Supporting Actors:** Sales Department, Customer

**Preconditions:**
- Customer exists in master data
- Goods delivered or service rendered
- Sales invoice prepared

**Trigger:**
- Sales invoice issued to customer on credit

**Main Flow:**
1. Accountant prepares sales invoice referencing delivery note or sales order
2. System determines revenue recognition:
   - Revenue recorded at net price (excluding indirect taxes)
   - Indirect taxes (VAT, excise, export duty, environmental tax) recorded separately in TK 333
3. System records journal entry:
   - Dr 131 (total invoice amount)
   - Cr 511 (net revenue — excluding indirect taxes)
   - Cr 333 (indirect taxes payable)
4. System updates customer AR balance (debit side increases)

**Alternate Flow:**
- **Indirect taxes not separated at invoice time:** Record revenue at gross amount. Periodically determine tax liability and adjust: Dr 511 — Cr 333 (Article 3.1b).
- **Export entrusted (giao ủy thác xuất khẩu):** Principal records AR from entrusted agent as normal sales transaction.
- **Construction contract:** Progress billing per contract terms via TK 337 (Article 3.6).

**Exception Flow:**
- If customer credit limit exceeded: flag for approval
- If customer is on hold (due to overdue balance): block new sales

**Business Rules:**
- BR01: TK 131 is debited at total invoice amount (Article 3.1a)
- BR02: Revenue recognized at net price; indirect taxes recorded separately in TK 333 (Article 3.1a)
- BR03: If taxes not separated at invoice, adjust periodically via Dr 511 — Cr 333 (Article 3.1b)
- BR04: Each customer tracked separately (hạch toán chi tiết từng đối tượng)
- BR05: AR classified as current (<12 months) or long-term (>12 months) for BC 01 presentation
- BR06: Settlement discount is finance cost (TK 635), not revenue reduction (Article 3.4)

**Input Data:**
- Customer ID, invoice number, invoice date
- Item codes, quantities, unit prices
- Tax rates and amounts (VAT, excise, export duty)
- Payment terms, due date

**Output Data:**
- AR balance increased (per customer)
- Revenue recognized (net)
- Indirect tax liability recorded

**Dependencies:**
- Customer master data
- COA configured with TK 131, TK 511, TK 333

**Frequency:** Daily (high volume)

**Priority:** Critical

---

### Domain 2: Customer Payment Processing

#### UC-002: Record Customer Payment

**Description:** Record payment received from a customer (cash, bank transfer, or other means). Reduce the corresponding customer's AR balance. If the payment includes interest on overdue amount, record interest income separately.

**Goal:** Accurately record cash inflow and reduce customer obligation.

**Primary Actors:** Accountant, Cashier

**Preconditions:**
- AR balance exists for the customer (debit balance)
- Payment received and verified

**Trigger:**
- Customer payment received
- Customer prepayment received

**Main Flow:**
1. Accountant selects customer and outstanding invoice(s) to match payment
2. If payment includes interest on overdue amount:
   - Record interest portion as finance income (TK 515)
3. System records journal entry:
   - Dr 111/112 (total amount received)
   - Cr 131 (principal portion)
   - Cr 515 (interest income, if any)
4. System reduces customer AR balance (credit side decreases debit balance)

**Alternate Flow:**
- **Prepayment (customer advance):** Record as Dr 111/112 — Cr 131. TK 131 shows credit balance (customer prepayment = liability).
- **Partial payment:** Record partial collection; balance remains outstanding.

**Business Rules:**
- BR07: Payment reduces TK 131 on the credit side (Article 3.5)
- BR08: Prepayment creates credit balance on TK 131; shown as liability on BC 01
- BR09: Interest on overdue amount recorded as TK 515 (finance income) (Article 3.5)
- BR10: Payment must reference specific invoice for accurate aging

**Priority:** Critical

---

### Domain 3: AR Adjustments

#### UC-003: Process Sales Return

**Description:** Customer returns goods due to defects, non-conformance, or change of order. Reverse the original sale: reduce AR, record revenue deduction, reverse output tax.

**Goal:** Correctly reverse the original sales transaction for returned goods.

**Primary Actors:** Accountant

**Preconditions:**
- Original sale recorded
- Goods received back (inspected)
- Credit note issued

**Trigger:**
- Customer returns goods

**Main Flow:**
1. Accountant initiates return referencing original invoice
2. Goods received and inspected
3. System records:
   - Dr 521 (revenue deduction — sales return)
   - Dr 333 (reverse output VAT/indirect taxes)
   - Cr 131 (reduce AR by total amount)
4. Customer AR balance reduced

**Business Rules:**
- BR11: Sales return: Dr 521 + Dr 333 — Cr 131 (Article 3.2)
- BR12: Return must reference original invoice for audit trail
- BR13: Returned goods inventoried at cost (separate inventory receipt entry)

**Priority:** High

---

#### UC-004: Process Trade Discount or Sales Allowance

**Description:** Grant a trade discount or sales allowance to a customer after the original sale. Record as a revenue deduction (TK 521) and reduce AR.

**Goal:** Properly account for post-sale price adjustments.

**Primary Actors:** Accountant

**Main Flow:**
1. Accountant determines discount/allowance type:
   - **Trade discount on invoice:** Revenue recorded at net amount (no separate entry)
   - **Post-sale discount/allowance:** Dr 521 + Dr 333 — Cr 131 (Article 3.3)
2. Customer AR balance reduced

**Business Rules:**
- BR14: Trade discount shown on invoice = revenue at net amount (Article 3.3a)
- BR15: Post-sale discount = Dr 521 (revenue deduction) + Dr 333 — Cr 131 (Article 3.3b)

**Priority:** Medium

---

#### UC-005: Process Settlement Discount

**Description:** Grant a settlement discount to a customer for early payment. The discount is recorded as a finance cost (TK 635), not a revenue reduction.

**Goal:** Record early payment incentive as financing cost.

**Primary Actors:** Accountant

**Main Flow:**
1. Customer pays before due date, qualifying for settlement discount
2. System records:
   - Dr 111/112 (amount received after discount)
   - Dr 635 (discount amount — finance cost)
   - Cr 131 (total invoice amount)
3. AR balance cleared

**Business Rules:**
- BR16: Settlement discount = Dr 635 (finance cost), NOT revenue reduction (Article 3.4)
- BR17: Discount amount must not exceed invoice balance

**Priority:** Medium

---

#### UC-006: Record Barter Transaction

**Description:** Customer settles receivable by delivering goods instead of cash (goods-for-goods exchange). Record receipt of inventory at fair value.

**Goal:** Account for non-cash settlement of AR.

**Primary Actors:** Accountant

**Main Flow:**
1. Customer delivers goods in settlement of AR
2. Goods valued at fair value per invoice
3. System records:
   - Dr 152/153/156 (inventory at fair value)
   - Dr 133 (input VAT, if applicable)
   - Cr 131 (reduce AR by total amount)
4. Inventory updated

**Business Rules:**
- BR18: Barter: Dr inventory at fair value — Cr 131 (Article 3.7)
- BR19: Fair value determined per VAT invoice from customer

**Priority:** Low

---

### Domain 4: Bad Debt and Provision

#### UC-007: Write Off Uncollectible Receivable

**Description:** When a receivable is confirmed as uncollectible, write off against the bad debt provision (TK 2293). If provision is insufficient, charge the excess to administrative expense (TK 642).

**Goal:** Remove uncollectible amounts from AR balance.

**Primary Actors:** Chief Accountant

**Preconditions:**
- Collection efforts exhausted
- Bad debt provision previously estimated (TK 2293)
- Write-off approved by authorized management

**Trigger:**
- Receivable confirmed uncollectible
- Court decision, customer bankruptcy, or statute of limitations

**Main Flow:**
1. Chief Accountant reviews aged AR and identifies uncollectible items
2. System records:
   - Dr 2293 (bad debt provision, up to provision balance)
   - Dr 642 (excess beyond provision)
   - Cr 131 (reduce AR)
3. System opens off-balance-sheet tracking for written-off amounts (Article 3.8)
4. Written-off amounts tracked for potential future recovery

**Business Rules:**
- BR20: Write-off: Dr 2293 (provision) + Dr 642 (excess) — Cr 131 (Article 3.8)
- BR21: Written-off amounts tracked off-balance-sheet for recovery within legal期限
- BR22: Requires management approval documented in writing
- BR23: If later recovered: Dr 111/112 — Cr 711 (other income)

**Priority:** High

---

#### UC-008: Estimate Bad Debt Provision

**Description:** At period-end, estimate the required bad debt provision (TK 2293) based on AR aging analysis. Adjust the provision balance to the estimated amount.

**Goal:** Reflect expected credit losses in the financial statements per VAS.

**Primary Actors:** Chief Accountant

**Main Flow:**
1. System generates AR aging report by customer and aging bucket
2. Chief Accountant applies estimated loss rates per bucket
3. System calculates required provision balance
4. Compare to existing TK 2293 balance
5. Record adjustment:
   - If additional provision needed: Dr 642 — Cr 2293
   - If provision can be reduced: Dr 2293 — Cr 642 (reversal)

**Business Rules:**
- BR24: Bad debt provision assessed per individual customer (AR aging)
- BR25: Provision adjustment through P&L (TK 642)

**Priority:** High

---

### Domain 5: Construction Contract AR

#### UC-009: Record Construction Progress Billing

**Description:** For construction contracts, recognize AR based on progress billings. Two methods: planned progress billing (via TK 337) or actual completed work billing.

**Goal:** Recognize AR for construction work completed but not yet paid.

**Primary Actors:** Accountant

**Preconditions:**
- Construction contract active
- Work completed per contract terms

**Trigger:**
- Progress billing milestone reached
- Completion certificate issued

**Main Flow:**
1. **Method A — Planned progress billing:**
   - Record revenue based on completed work: Dr 337 — Cr 511
   - Issue invoice per plan: Dr 131 — Cr 337 — Cr 3331
2. **Method B — Actual completed work billing:**
   - Record revenue and AR based on certified completed work: Dr 131 — Cr 511 — Cr 3331
3. Customer invoiced per contract terms

**Alternate Flow:**
- **Performance bonus:** Additional billing when contract targets exceeded: Dr 131 — Cr 511 — Cr 3331
- **Compensation from customer:** For delays, errors caused by customer: Dr 131 — Cr 511 — Cr 3331

**Business Rules:**
- BR26: Planned billing: Dr 337 (revenue) → Dr 131 (invoice) — Cr 337 (Article 3.6a)
- BR27: Actual billing: Dr 131 (certified completed work) — Cr 511 (Article 3.6b)
- BR28: Performance bonus recognized as revenue when collectible (Article 3.6c)

**Priority:** Medium

---

### Domain 6: AR Reporting

#### UC-010: Generate AR Aging Report

**Description:** Produce customer aging report showing outstanding balances by aging bucket (current, 1-30, 31-60, 61-90, 90+ days overdue). Basis for bad debt estimation and cash flow forecasting.

**Goal:** Monitor collection status and identify overdue accounts.

**Primary Actors:** Accountant, Credit Manager

**Main Flow:**
1. System queries TK 131 balance per customer with debit balance (outstanding AR)
2. System ages the balance by invoice due date
3. Aging buckets: current, 1-30 days, 31-60 days, 61-90 days, 90+ days
4. Credit balance customers (prepayments) listed separately
5. Report generated for collection follow-up and provision estimation

**Business Rules:**
- BR29: AR aging by invoice due date, not transaction date
- BR30: Customer prepayments (Cr balance) shown separately from AR (Dr balance)
- BR31: BC 01 presentation: Dr balance → Tài sản (Mã số 131), Cr balance → Nguồn vốn (Mã số 312)

**Priority:** High

---

## 3. Cross-Use Case Analysis

### End-to-End AR Lifecycle

```
UC-001: Record Sales Invoice (Dr 131 — Cr 511 + Cr 333)
    │
    ├── UC-003: Sales Return (Dr 521 + Dr 333 — Cr 131)
    ├── UC-004: Trade Discount (Dr 521 + Dr 333 — Cr 131)
    ├── UC-005: Settlement Discount (Dr 635 — Cr 131)
    ├── UC-006: Barter (Dr inventory — Cr 131)
    ├── UC-002: Customer Payment (Dr 111/112 — Cr 131)
    │
    └── UC-007: Bad Debt Write-off (Dr 2293/642 — Cr 131)
    
UC-009: Construction Progress Billing — separate lifecycle via TK 337

UC-008: Bad Debt Provision — period-end adjustment
UC-010: AR Aging Report — reads all UC-001 to UC-008 transactions
```

### Overlapping Use Cases
- UC-003 (Sales Return) and UC-001 (Invoice): Return reverses the original invoice
- UC-004/005 (Discounts): Both reduce AR but through different P&L accounts
- UC-008 (Provision) consumes aging data from UC-010
- UC-009 (Construction) involves both TK 337 and TK 131

### Shared Dependencies
- **Per-customer tracking:** Every transaction references a specific customer
- **Invoice-level reference:** Payments and returns reference original invoices
- **Aging classification:** Current vs. long-term based on due date vs. 12-month threshold

---

## 4. Missing Functionalities

### Missing Use Cases
| Use Case | Description | Priority |
|---|---|---|
| Customer Credit Management | Credit limit checking, credit hold/release | High |
| Dunning/Collection Workflow | Automated collection emails, reminder letters | Medium |
| AR Reclassification (Current ↔ Long-term) | Auto-reclassify AR based on aging >12 months | Medium |
| Customer Statement Generation | Per-customer transaction history for reconciliation | Medium |

### Missing Validation Rules
- Duplicate invoice number from same customer → flag
- Payment exceeds AR balance → warn
- Credit limit exceeded → block or flag
- Customer with credit balance (prepayment) > X months → review

### Missing Approval Flows
- Write-off (UC-007) requires Chief Accountant + CFO authorization
- Settlement discount exceeding standard terms requires sales manager approval
- Credit limit increase requires credit committee approval

---

## 5. Recommended System Modules

| Module | Responsibility |
|---|---|
| **Sales Invoice Engine** | Record invoices, revenue recognition, tax split, credit check |
| **Collection Engine** | Payment recording, cash application, dunning |
| **AR Adjustment Engine** | Returns, discounts, allowances, barter, write-off |
| **Bad Debt Engine** | Provision estimation, write-off, off-balance-sheet tracking |
| **Construction Contract Engine** | Progress billing, TK 337 management, performance bonuses |
| **AR Reporting** | Aging, customer statement, cash flow forecasting |

---

## 6. Suggested Improvements

### Business Improvements
1. **Automated dunning:** Send payment reminders at 7, 14, 30 days past due.
2. **Credit scoring:** Integrate customer payment history into credit limit decisions.

### Process Improvements
1. **Cash application automation:** Auto-match incoming payments to open invoices by amount + reference.
2. **Early payment discount optimization:** Offer dynamic discounts based on payment timing.

### Technical Improvements
1. **Sub-ledger integration:** TK 131 balance = sum of all customer balances. General ledger = sub-ledger reconciliation enforced.
2. **Aging-driven provision:** Auto-calculate bad debt provision based on configurable aging thresholds.

### Compliance Improvements
1. **Dual balance presentation:** Debit balances → assets (Mã số 131), credit balances → liabilities (Mã số 312) on BC 01.
2. **Off-balance-sheet tracking:** Written-off AR tracked for minimum 5 years per tax regulations.
3. **FC revaluation:** AR denominated in FC revalued at period-end closing rate (Dr/Cr TK 131 — Cr/Dr TK 413).

---

*Document generated via BA analysis of Circular 99/2025/TT-BTC Article 2 — TK 131, covering 9 journal entry scenarios from a single source. 10 use cases extracted across 6 domains. All accounting postings follow the Dr/Cr patterns prescribed in the source material.*
