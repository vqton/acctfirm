# Use Case Specification

## Financial Statement Preparation — Circular 99/2025/TT-BTC

**Version:** 1.0
**Last Updated:** 2026-05-15
**Regulatory Basis:** Circular 99/2025/TT-BTC, Law on Accounting 2015

---

## 1. Source

- **URLs:**
  - https://ketoanthienung.net/bao-cao-tai-chinh-gom-nhung-gi-mau-bieu-bang-nao.htm
  - https://ketoanthienung.net/cach-lap-bao-cao-tinh-hinh-tai-chinh-theo-thong-tu-99.htm
  - https://ketoanthienung.net/cach-lap-bao-cao-ket-qua-hoat-dong-kinh-doanh-theo-thong-tu-99.htm
  - https://ketoanthienung.net/mau-bao-cao-luu-chuyen-tien-te-theo-thong-tu-99.htm
  - https://ketoanthienung.net/cach-lap-thuyet-minh-bctc-theo-thong-tu-99.htm
- **Domain Context:** Financial statement preparation for Vietnamese enterprises under Circular 99/2025/TT-BTC, effective 1 January 2026. Defines the complete financial reporting package: Balance Sheet (BC 01), Income Statement (BC 02), Cash Flow Statement (BC 03), and Notes to Financial Statements (BC 09).
- **Regulatory Context:** Article 17 of Circular 99/2025/TT-BTC; VAS 21 (Presentation of Financial Statements), VAS 24 (Cash Flow Statements), VAS 27 (Interim Financial Reporting). Three FS variants: annual full, interim full, interim condensed. Two presentation bases: going concern and non-going concern.
- **Analysis Summary:** The financial reporting system comprises 4 primary statements with defined mã số (line item codes), calculation formulas, and cross-statement reconciliation. BC 01 presents assets = liabilities + equity at a point in time. BC 02 presents revenue, expenses, and profit over a period. BC 03 presents cash inflows/outflows by operating, investing, and financing activities (direct or indirect method). BC 09 provides narrative and detailed breakdowns of all line items plus accounting policy disclosures. Each statement has comparative columns (current year / prior year). All statements require three signatures: preparer, chief accountant, legal representative.

---

## 2. Domain Breakdown

### Domain 1: Balance Sheet (BC 01)

#### UC-001: Generate Statement of Financial Position (BC 01)

**Description:** Prepare the Balance Sheet (Báo cáo tình hình tài chính, Mẫu B01-DN) reflecting all assets, liabilities, and equity at period-end. Classify assets and liabilities as current or non-current. The statement must balance: Total Assets = Total Liabilities + Equity.

**Goal:** Produce a true and fair view of the entity's financial position at a point in time.

**Primary Actors:** Accountant, Chief Accountant

**Preconditions:**
- Period closed (ledger finalized)
- All adjusting entries posted
- Trial balance balanced

**Trigger:**
- Period-end reporting
- Statutory deadline

**Main Flow:**
1. System loads all account balances from closed ledger
2. System maps account balances to BC 01 line items per the following structure:

**Assets — Current (Mã số 100):**
```
100 = 110 (Cash) + 120 (Short-term investments) + 130 (Receivables) + 140 (Inventory) + 150 (Biological assets) + 160 (Other current assets)
```
- **110** = 111 (Cash: TK 111+112+113) + 112 (Cash equivalents: TK 1281, 1288 ≤ 3 months)
- **120** = 121 (Trading securities: TK 121) + 122 (Provision: TK 2291 negative) + 123 (HTM investments: TK 128) + 124 (Provision: TK 2292 negative) + 125 (Other: TK 2281) + 126 (Provision: TK 2292 negative)
- **130** = 131 (AR: TK 131 debit) + 132 (Prepayments: TK 331 debit) + 133 (Interco AR: TK 136) + 134 (Construction: TK 337) + 135 (Other AR: TK 1388,334,338,141,244) + 136 (Bad debt provision: TK 2293 negative) + 137 (Asset shortage: TK 1381)
- **140** = 141 (Inventory: TK 151-158) + 142 (Inventory provision: TK 2294 negative)
- **150** = 151 (Short-term biological: TK 2152/2153) + 152 (Provision: TK 2295 negative)
- **160** = 161 (Prepaid expenses: TK 242) + 162 (Input VAT: TK 133) + 163 (Tax receivable: TK 1383/333) + 164 (Gov bond repo: TK 171) + 165 (Other: TK 2288)

**Assets — Non-current (Mã số 200):**
```
200 = 210 (Long-term receivables) + 220 (Fixed assets) + 230 (Biological assets) + 240 (Investment property) + 250 (CIP) + 260 (Long-term investments) + 270 (Other long-term assets)
```
- **210** = 211-216 (Long-term AR, prepayments, interco, other)
- **220** = 221 (Tangible FA: 222-223, TK 211 - TK 2141) + 224 (Finance lease: 225-226, TK 212 - TK 2142) + 227 (Intangible FA: 228-229, TK 213 - TK 2143)
- **230** = 231 (Bearing animals: 232+233, TK 2151/21511/215121/215122) + 236 (One-time animals: TK 2152) + 237 (Seasonal crops: TK 2153) + 238 (Provision: TK 2295 negative)
- **240** = 241 (Cost: TK 217) + 242 (Accum. depreciation: TK 2147 negative)
- **250** = 251 (Long-term WIP: TK 154 - TK 2294) + 252 (Construction CIP: TK 241)
- **260** = 261 (Subsidiaries: TK 221) + 262 (Associates: TK 222) + 263 (Other equity: TK 2281) + 264 (Provision: TK 2292 negative) + 265 (HTM long-term: TK 128) + 266 (Provision: TK 2292 negative)
- **270** = 271 (Long-term prepaid: TK 242) + 272 (Deferred tax asset: TK 243) + 273 (Long-term supplies: TK 153 - TK 2294) + 274 (Other: TK 2288)

**Total Assets (280) = 100 + 200**

**Liabilities (Mã số 300):**
```
300 = 310 (Current liabilities) + 330 (Non-current liabilities)
```
- **310** = 311-325 (AP, customer prepayments, taxes, payables, borrowings, provisions, funds)
- **330** = 331-344 (Long-term AP, bonds, convertible bonds, deferred tax, provisions)

**Equity (Mã số 400):**
```
400 = 411 (Contributed capital) + 412 (Share premium) + 413 (Conversion options) + 414 (Other capital) + 415 (Treasury shares negative) + 416 (Asset revaluation) + 417 (FX differences) + 418 (Investment fund) + 419 (Other funds) + 420 (Retained earnings)
```

**Total Liabilities & Equity (440) = 300 + 400**

3. System validates: **280 = 440** (Assets = Liabilities + Equity)
4. System populates comparative column (prior year end) from prior period FS
5. Statement signed and submitted for signature

**Alternative Flows:**
None documented

**Postconditions:**
- BC 01 generated with balanced assets = liabilities + equity
- Comparative data populated

**Business Rules:**
- BR01: Assets = Liabilities + Equity (accounting equation)
- BR02: Current vs. non-current classification based on 12-month/operating cycle rule
- BR03: Contra accounts (provisions, accumulated depreciation) presented as negative amounts in parentheses
- BR04: Comparative column required (current year end / prior year end)
- BR05: Line items with zero balance may be omitted; mã số may NOT be renumbered
- BR06: Intra-entity balances must be eliminated in consolidated/combined statements
- BR07: Cash in restricted use is NOT presented under Cash (111) but under Other current assets (165) or Other long-term assets (274)

**Input Data:**
- Closed ledger balances for all accounts
- Prior period BC 01 for comparative data
- Account-to-FS-line mapping configuration

**Output Data:**
- BC 01 — Statement of Financial Position (B01-DN or B01-DNKLT)

**Dependencies:**
- Period closed (UC-003 Period Engine)
- Trial balance verified

**Frequency:** Monthly / Quarterly / Annually

**Priority:** Critical

---

### Domain 2: Income Statement (BC 02)

#### UC-002: Generate Income Statement (BC 02)

**Description:** Prepare the Income Statement (Báo cáo kết quả hoạt động kinh doanh, Mẫu B02-DN) reflecting revenue, expenses, and profit/loss for the period. Includes operating, financial, and other activities.

**Goal:** Measure financial performance over a reporting period.

**Primary Actors:** Accountant, Chief Accountant

**Preconditions:**
- Period closed
- All revenue/expense accounts finalized
- Intra-entity transactions identified for elimination

**Trigger:**
- Period-end reporting

**Main Flow:**
1. System loads revenue and expense account balances
2. System maps to BC 02 line items:

```
01 = Revenue from sales and service (TK 511 credit turnover)
02 = Revenue deductions (TK 521 → TK 511 debit)
10 = Net revenue (01 - 02)
11 = Cost of goods sold (TK 632 → TK 911 debit)
20 = Gross profit (10 - 11)
21 = Gain/loss on investment property disposal (TK 511/632 → TK 911)
22 = Finance income (TK 515 → TK 911 debit)
23 = Finance costs (TK 635 → TK 911 credit)
24 = Of which: borrowing costs (TK 635 detail)
25 = Selling expenses (TK 641 → TK 911 credit)
26 = Administrative expenses (TK 642 → TK 911 credit)
30 = Operating profit (20 + 21 + 22 - 23 - 25 - 26)
31 = Other income (TK 711 → TK 911 debit)
32 = Other expenses (TK 811 → TK 911 credit)
40 = Other profit (31 - 32)
50 = Pre-tax profit (30 + 40)
51 = Current CIT expense (TK 8211 → TK 911 credit)
52 = Deferred CIT expense (TK 8212 → TK 911 credit)
60 = Net profit after tax (50 - 51 - 52)
70 = Basic EPS
71 = Diluted EPS
```

3. System validates: Formula chain integrity (30, 40, 50, 60 cross-checked)
4. System populates comparative column (prior period)
5. For consolidated/combined statements: all intra-entity revenue/expense eliminated

**Alternative Flows:**
None documented

**Postconditions:**
- BC 02 generated with formula chain integrity verified
- Comparative data populated

**Business Rules:**
- BR08: Indirect taxes (VAT, excise, export duties) excluded from revenue
- BR09: Revenue deductions (01→02) include trade discounts, sales returns, allowances
- BR10: Finance income (22) includes net FX gain if gain > loss from revaluation
- BR11: Finance costs (23) includes net FX loss if loss > gain from revaluation
- BR12: Borrowing costs (24) disclosed separately within finance costs
- BR13: Intra-entity transactions eliminated in consolidation

**Input Data:**
- Revenue/expense account balances (Class 5-9)
- Prior period BC 02
- EPS calculation inputs (for joint stock companies)

**Output Data:**
- BC 02 — Income Statement (B02-DN)

**Frequency:** Monthly / Quarterly / Annually

**Priority:** Critical

---

### Domain 3: Cash Flow Statement (BC 03)

#### UC-003: Generate Cash Flow Statement (BC 03)

**Description:** Prepare the Cash Flow Statement (Báo cáo lưu chuyển tiền tệ, Mẫu B03-DN) using either the direct or indirect method. Classify cash flows into operating, investing, and financing activities.

**Goal:** Provide information about cash inflows and outflows and the entity's liquidity position.

**Primary Actors:** Accountant, Chief Accountant

**Preconditions:**
- BC 01 and BC 02 finalized
- Cash account movements analyzed

**Trigger:**
- Period-end reporting (annual minimum)
- Management request

**Main Flow:**
1. System presents method choice: Direct (default) or Indirect
2. **Direct Method — Operating Activities:**
```
01 = Cash received from customers
02 = Cash paid to suppliers
03 = Cash paid to employees
04 = Interest paid
05 = Income tax paid
06 = Other operating cash receipts
07 = Other operating cash payments
20 = Net cash from operations
```
3. **Indirect Method — Operating Activities:**
```
01 = Pre-tax profit
02 = Adjustments: depreciation, provisions, FX gains/losses, investment gains, borrowing costs
08 = Operating profit before working capital changes
09-17 = Working capital changes: AR, inventory, AP, prepaid expenses, trading securities
20 = Net cash from operations
```
4. **Investing Activities:**
```
21 = Purchase of fixed assets (CAPEX)
22 = Proceeds from FA disposal
23 = Loans to others
24 = Collection of loans
25 = Equity investments
26 = Divestment proceeds
27 = Interest, dividends received
30 = Net cash from investing
```
5. **Financing Activities:**
```
31 = Proceeds from share issuance
32 = Share buybacks
33 = Proceeds from borrowings
34 = Repayment of borrowings
35 = Repayment of finance lease
36 = Dividends paid
40 = Net cash from financing
50 = Net cash flow (20 + 30 + 40)
60 = Opening cash and equivalents
61 = FX rate effects
70 = Closing cash and equivalents (50 + 60 + 61)
```
6. System validates: **70** (closing cash) must equal **111** (Cash) from BC 01
7. Comparative column populated from prior period BC 03

**Alternative Flows:**
None documented

**Postconditions:**
- BC 03 generated with closing cash = BC 01 cash
- Direct or indirect method applied

**Business Rules:**
- BR14: Closing cash (70) must reconcile to BC 01 Cash line (111)
- BR15: Direct method shows actual cash receipts/payments; indirect starts from profit
- BR16: Interest paid classified as operating, not financing (Vietnamese practice)
- BR17: Dividends paid classified as financing
- BR18: Acquisitions/disposals of subsidiaries shown separately with supplemental disclosures

**Input Data:**
- BC 01 and BC 02
- General ledger detail for cash accounts
- Prior period BC 03

**Output Data:**
- BC 03 — Cash Flow Statement (B03-DN)

**Frequency:** Annually (plus interim for some entities)

**Priority:** High

---

### Domain 4: Notes to Financial Statements (BC 09)

#### UC-004: Generate Notes to Financial Statements (BC 09)

**Description:** Prepare the Notes to FS (Bản thuyết minh Báo cáo tài chính, Mẫu B09-DN) providing narrative descriptions and detailed breakdowns of all line items in BC 01, BC 02, and BC 03, plus accounting policies and other disclosures.

**Goal:** Provide complete, transparent disclosure to enable users to understand the financial statements.

**Primary Actors:** Chief Accountant

**Preconditions:**
- BC 01, BC 02, BC 03 finalized
- Subsidiary ledger details available
- Prior period BC 09 available

**Trigger:**
- Annual financial reporting

**Main Flow:**
1. System generates BC 09 with the following sections:

**Section 1 — Enterprise Information:**
- Ownership form, business sector, industry
- Number of employees
- Subsidiaries, associates, joint ventures list
- Going concern declaration

**Section 2 — Accounting Periods and Currency:**
- Fiscal year start/end dates
- Reporting currency (VND or foreign currency election)

**Section 3 — Accounting Standards and Regime:**
- Applicable regime (Circular 99)
- VAS compliance declaration

**Section 4 — Accounting Policies** (29 sub-sections):
1. FC translation principles
2. Exchange rate types applied
3. Effective interest rate determination
4. Cash and cash equivalents recognition
5. Financial investments (trading, HTM, subsidiaries, associates, other)
6. Receivables (classification, aging, FC revaluation, bad debt provision)
7. Inventory (cost method, valuation method, perpetual vs. periodic, impairment)
8. Fixed assets (cost model, depreciation method, subsequent expenditure)
9. Finance lease assets
10. Investment property (cost model vs. fair value)
11. Biological assets
12. BCC (business cooperation contracts)
13. Prepaid expenses
14. Accounts payable
15. Dividends payable
16. Accrued expenses
17. Unearned revenue
18. Provisions (warranty, restructuring)
19. Deferred tax (temporary differences, tax loss carryforwards)
20. Borrowings and lease liabilities
21. Borrowing costs capitalization
22. Convertible bonds (liability vs. equity components)
23. Equity (capital, share premium, treasury shares)
24. Revenue recognition (goods, services, construction contracts, investment property)
25. Revenue deductions
26. Cost of goods sold
27. Finance costs (incl. FX losses)
28. Selling and administrative expenses
29. Income tax (current CIT + global minimum tax, deferred CIT)

**Section 5 — Non-Going Concern Policies** (if applicable):
- Reclassification of long-term assets/liabilities to short-term
- Measurement basis for each item category

**Section 6 — Supplementary Info for BC 01:**
- Detailed breakdown of each BC 01 line item
- Opening balance (prior year end) vs. closing balance (current year end)
- Movement schedule for fixed assets, investments, provisions, equity

**Section 7 — Supplementary Info for BC 02:**
- Detailed breakdown of revenue, COGS, expenses
- Prior year vs. current year comparison

**Section 8 — Supplementary Info for BC 03:**
- Detailed cash flow items
- Acquisitions/disposals of subsidiaries detail

**Section 9 — Other Disclosures:**
- Contingent liabilities
- Related party transactions
- Subsequent events
- Commitments

2. System validates cross-references: each BC 01/02/03 mã số must have a corresponding BC 09 note
3. Comparative data populated from prior year BC 09

**Alternative Flows:**
None documented

**Postconditions:**
- BC 09 generated with cross-references to BC 01/02/03
- All mã số have corresponding notes

**Business Rules:**
- BR19: BC 09 is an integral part of the FS — not optional (VAS 21)
- BR20: Every mã số in BC 01/02/03 must be cross-referenced to a BC 09 note
- BR21: Accounting policies must be consistently applied; changes must be disclosed with justification and financial effect
- BR22: Prior year comparatives must be restated if policy changed retrospectively
- BR23: Fair value disclosures required where applicable; if not determinable, reason must be stated

**Input Data:**
- BC 01, BC 02, BC 03
- Subsidiary ledgers for all balance sheet and P&L items
- Movement schedules (fixed assets, equity, provisions)
- Prior year BC 09
- Entity registration and operational data

**Output Data:**
- BC 09 — Notes to Financial Statements

**Frequency:** Annually

**Priority:** High

---

### Domain 5: FS Cross-Statement Validation

#### UC-005: Validate Financial Statement Integrity

**Description:** Perform cross-statement validation checks to ensure internal consistency, formula accuracy, and regulatory compliance across all four FS statements.

**Goal:** Deliver error-free, auditable financial statements.

**Primary Actors:** System (automatic), Chief Accountant

**Preconditions:**
- All 4 statements generated
- Current and prior period data available

**Trigger:**
- Before FS submission
- Pre-audit preparation

**Main Flow:**
1. System performs automatic validation checks:
   - BC 01: **280 = 440** (Assets = Liabilities + Equity)
   - BC 03: **70 (Closing cash) = 111 (Cash in BC 01)**
   - BC 02: **60 (Net profit)** flows to BC 01: **420 (Retained earnings)** movement
   - BC 09: Every mã số in BC 01/02/03 has a corresponding BC 09 note number
   - Multi-period: Opening balance (current) = Closing balance (prior)
2. System flags any discrepancies
3. Chief Accountant reviews and resolves flags
4. Clean validation confirms FS ready for signature

**Alternative Flows:**
None documented

**Postconditions:**
- All cross-statement validations passed
- FS integrity confirmed

**Business Rules:**
- BR24: Assets = Liabilities + Equity
- BR25: Closing cash (BC 03) = Cash + equivalents (BC 01 111)
- BR26: Net profit (BC 02 60) = movement in retained earnings (BC 01 420) after dividends
- BR27: Opening balances = prior period closing balances

**Priority:** Critical

---

### Domain 6: FS Sign-off and Submission

#### UC-006: Sign and Submit Financial Statements

**Description:** Apply required signatures (preparer, chief accountant, legal representative) and submit FS to regulatory authorities within statutory deadlines.

**Goal:** Fulfill statutory reporting obligations.

**Primary Actors:** Chief Accountant, Legal Representative

**Preconditions:**
- Validation passed (UC-005)
- FS audited if required (Law on Accounting Article 33)

**Trigger:**
- Statutory deadline: 90 days for annual FS (Article 29.5)

**Main Flow:**
1. FS printed/generated in final format
2. Preparer signs
3. Chief Accountant signs and certifies
4. Legal Representative signs and seals
5. FS submitted to: tax authority, statistics office, business registration office
6. If audited: audit report attached to FS submission (Article 33)
7. Deadline tracked: annual FS within 90 days of year-end

**Alternative Flows:**
None documented

**Postconditions:**
- FS signed by all required parties
- FS submitted to regulatory authorities

**Business Rules:**
- BR28: Three signatures required: preparer, chief accountant, legal representative (Article 29.4)
- BR29: Annual FS due within 90 days of year-end (Article 29.5)
- BR30: Audited FS must include audit report (Article 33)

**Priority:** Critical

---

### Domain 7: Interim Financial Reporting

#### UC-007: Generate Interim Financial Statements

**Description:** Prepare interim FS (dạng đầy đủ - full form, or dạng tóm lược - condensed form) for quarterly or mid-year reporting periods.

**Goal:** Provide periodic financial information within the fiscal year.

**Primary Actors:** Accountant

**Main Flow:**
1. System generates interim FS using same line item structure but:
   - Full form (B01a-DN, B02a-DN, B03a-DN, B09a-DN): same as annual
   - Condensed form (B01b-DN, B02b-DN, B03b-DN): fewer line items
2. Interim comparative: current period vs. same period prior year
3. Notes (B09a-DN): selective — only material changes since prior year-end

**Alternative Flows:**
None documented

**Postconditions:**
- Interim FS generated per VAS 27

**Business Rules:**
- BR31: Interim FS per VAS 27
- BR32: Condensed form permitted for interim periods; annual requires full form

**Frequency:** Quarterly

**Priority:** Medium

---

### Domain 8: Non-Going Concern FS

#### UC-008: Generate Non-Going Concern Financial Statements

**Description:** Prepare FS when the entity does not satisfy the going concern assumption (dissolution, bankruptcy, significant financial distress). Uses separate form templates: B01-DNKLT, B02-DNKLT, B03-DNKLT, B09-DNKLT.

**Goal:** Present financial position on a liquidation basis.

**Primary Actors:** Chief Accountant, Liquidator

**Main Flow:**
1. System detects non-going concern indicators
2. System uses non-going concern form templates
3. All long-term assets/liabilities reclassified as current
4. Assets measured at net realizable value
5. FS signed and submitted per dissolution/bankruptcy regulations

**Alternative Flows:**
None documented

**Postconditions:**
- Non-going concern FS generated
- All items classified as current

**Business Rules:**
- BR33: Non-going concern FS uses separate form templates (Bxx-DNKLT)
- BR34: All items classified as current — no long-term/short-term distinction
- BR35: Measurement basis switches from historical cost to realizable value

**Frequency:** Event-driven

**Priority:** Medium

---

## 3. Cross-Use Case Analysis

### Dependency Map

```
UC-003: Period Close (Period Engine)
    ↓
UC-005: Validate FS (cross-statement checks)
    ↓
UC-001: Generate BC 01 ──────────────────────────────────┐
UC-002: Generate BC 02 ──────────────────────────────────┤
UC-003: Generate BC 03 → depends on BC 01 + BC 02      │
UC-004: Generate BC 09 → depends on BC 01 + BC 02 + BC 03 │
    ↓                                                      │
UC-005: Validate FS Integrity ←─────── all 4 statements ──┘
    ↓
UC-006: Sign and Submit FS
```

### Overlapping Use Cases
- UC-001 (BC 01) and UC-003 (BC 03) share Cash data — BC 03's closing cash must equal BC 01's Cash line
- UC-002 (BC 02) and UC-001 (BC 01) share Net Profit — BC 02's profit flows to BC 01's retained earnings
- UC-004 (BC 09) aggregates data from all three other statements
- UC-005 (Validation) is consumed by UC-006 (Submission) — validation must pass before sign-off

### Shared Dependencies
- **Closed period**: all FS depend on period being closed
- **Account-to-FS-line mapping**: shared by UC-001, UC-002, UC-003
- **Comparative data**: all UCs require prior period FS data for comparative column
- **Signatory authority**: UC-006 consumed by UC-005 (validation must pass before signing)

### Workflow Gaps
- No explicit UC for **FS audit preparation** (workpaper generation for auditors)
- No explicit UC for **FS reissuance** (correction of errors after submission)
- No explicit UC for **consolidated FS** (parent-subsidiary elimination)
- No explicit UC for **management report generation** (non-statutory internal reports)

---

## 4. Missing Functionalities

### Missing Use Cases
| Use Case | Description | Priority |
|---|---|---|
| Prepare Consolidated FS | Eliminate intra-group balances, revenue, expenses for parent-subsidiary groups | High |
| Reissue Financial Statements | Correct errors in previously issued FS with disclosure restatement | Medium |
| FS Audit Workpaper Generation | Generate supporting schedules for external audit | Medium |
| Export FS to Tax Authority Format | XML/JSON export for GDT electronic submission | Medium |

### Missing Validation Rules
- BC 01 opening balance must equal prior period closing balance
- BC 02 net profit must equal BC 01 retained earnings movement (after dividends postings)
- BC 03 closing cash (70) must equal BC 01 cash (111)
- BC 09 cross-reference: every BC 01/02/03 mã số with a note column must have a BC 09 corresponding section
- EPS consistency: basic EPS (70) ≤ diluted EPS (71)
- Current assets must be ≤ total assets
- Negative equity must not present total liabilities > total assets (going concern assumption violated)

### Missing Approval Flows
- FS sign-off: preparer → chief accountant → legal representative (sequential, mandatory)
- FS reissuance: requires auditor approval for audited FS
- FS deadline extension: requires regulatory approval

### Missing Audit Trails
- FS generation: who generated, when, which period, version number
- FS sign-off: signature timestamps per signatory level
- FS submission: submission method, timestamp, confirmation receipt
- FS reissuance: reason code, approval reference, prior version archival

---

## 5. Recommended System Modules

| Module | Responsibility |
|---|---|
| **BC 01 Engine** | Map GL balances to BC 01 line items. Formulas per mã số. Current/non-current classification. Comparative data. |
| **BC 02 Engine** | Map P&L accounts to BC 02. Formula chain: revenue → gross profit → operating profit → pre-tax → net profit. EPS calculation. |
| **BC 03 Engine** | Direct and indirect method. Operating/investing/financing classification. Reconciliation to BC 01 cash. |
| **BC 09 Engine** | Narrative generation from entity data. Policy disclosure templates. Cross-reference management. Movement schedules. |
| **FS Validator** | Cross-statement checks. Formula integrity. Comparative continuity. Sign-off readiness. |
| **FS Sign-off Workflow** | Sequential signature workflow. Deadline tracking. Audit report attachment. |
| **Account-to-FS-Line Config** | Configurable mapping of GL accounts to FS line items. Supports customization per entity. |

---

## 6. Suggested Improvements

### Business Improvements
1. **Real-time FS preview:** Show draft FS at any point during the period (before close) for management review.
2. **FS variance analysis:** Auto-flag material variances (>20%) between current and prior period for investigation.

### Process Improvements
1. **Pre-FS checklist automation:** System checks pre-conditions and blocks FS generation if any is unmet.
2. **Audit trail integration:** Every FS version is snapshotted immutably for audit reference.

### Technical Improvements
1. **Account-to-FS-line mapping UI:** Configurable, versioned mapping that supports custom entities. Each account maps to exactly one FS line. Parent accounts auto-roll up.
2. **Comparative data carry-forward:** Prior period FS data auto-loaded for comparative column.
3. **FS export:** Export to Excel, PDF, XML with Circular 99 prescribed formats.

### Compliance Improvements
1. **Deadline dashboard:** Days remaining until FS submission deadline. Alerts at 30, 14, 7 days.
2. **Signature compliance:** Enforce sequential signature order: preparer → chief accountant → legal representative. Block submission if incomplete.
3. **Non-going concern detection:** System auto-detects indicators (negative equity, debt covenant breach) and prompts for non-going concern treatment.

---

*Document generated via BA analysis of Circular 99/2025/TT-BTC financial statement provisions across 5 source articles. All mã số codes, formulas, and cross-references follow Article 17 and Appendix IV of Circular 99/2025/TT-BTC.*
