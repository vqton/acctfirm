# Accounting Engine Brain Logic

**Author:** Chief Accountant — 20,000+ hours. Vietnam SME. Audit survivor.
**Sources:** Kế Toán Thiên Ưng, Kế Toán Lê Ánh, Webketoan, VAS, Circular 99, TT08, TT80, NĐ 320/2025, Luật 56/2025/QH15, Luật 109/2025/QH15, NĐ 181/2025/NĐ-CP
**Date:** 2026-05-28

---

## Table of Contents

1. [Accounting Engine Brain Logic](#1-accounting-engine-brain-logic)
2. [Real SME Closing Scenarios](#2-real-sme-closing-scenarios)
3. [Use Cases](#3-use-cases)
4. [Process & Workflow Logic](#4-process--workflow-logic)
5. [Data Flow Logic](#5-data-flow-logic)
6. [Validation & Control Rules](#6-validation--control-rules)
7. [User Journey](#7-user-journey)
8. [SME Pain Analysis](#8-sme-pain-analysis)
9. [Final Deliverables](#9-final-deliverables)

---

# 1. Accounting Engine Brain Logic

## 1.1 Why Closing Engine Exist

Every SME in Vietnam runs a monthly accounting cycle. The closing engine is not optional — it is the legal backbone.

**The reality:**
- Month-end: kế toán tổng hợp closes period, runs trial balance, checks Dr=Cr
- Quarter-end: VAT declaration deadline (20th of next month)
- Year-end: BCTC (BC01/02/03/09), CIT finalization (03/TNDN), TNCN finalization

Without a closing engine:
- Kế toán công nợ reconciles AP aging manually in Excel
- Kế toán kho counts inventory manually
- Kế toán thuế retypes numbers from ledger into HTKK
- Kế toán tổng hợp prays trial balance balances

**The closing engine exists to:**
1. Lock closed periods — no more edits (Article 26 Law on Accounting)
2. Automate closing entries — Dr 511/515/711 → Cr 911, Dr 911 → Cr 632/635/641/642
3. Pre-close verification — inventory done? FC revalued? sub-ledger = GL? Dr = Cr?
4. Audit trail — who closed, when, what changed
5. Re-open only with dual authorization, full trail

## 1.2 How BC09 Report Built

BC09 (B09-DN — Thuyết minh Báo cáo tài chính) is the most detailed FS. Every mã số on BC01/02/03 must trace back to a BC09 note.

**BC09 structure — 9 sections mandated by VAS 21:**

| Section | Content | Source |
|---|---|---|
| I | Đặc điểm hoạt động | Manual entry (company info) |
| II | Chế độ kế toán áp dụng | Accounting policy disclosures |
| III | Tình hình tài sản, nợ phải trả, VCSH | BC01 breakdown by category |
| IV | Giải thích BC02 items | Revenue, COGS, expenses detail |
| V | Thuế và các khoản nộp NN | VAT, CIT, PIT, license tax |
| VI | Hàng tồn kho | Inventory valuation, impairment |
| VII | Chi phí SXKD theo yếu tố | Production costs by element |
| VIII | Tình hình tăng giảm TSCĐ | Fixed assets movement schedule |
| IX | Các chỉ tiêu ngoài BCĐKT | Off-balance sheet items |

**How it builds:**
- Auto-populate from BC01/02/03 mã số cross-references
- Pull fs_snapshots data for comparative period
- Calculate variances (current year vs prior year)
- Generate accounting policy text from template (29 sub-sections)
- Manual override fields for narrative disclosures

## 1.3 How VAT Declaration Prepared

VAT declaration (01/GTGT — khấu trừ or 04/GTGT — trực tiếp) is the #1 reason SMEs buy accounting software.

**Logic:**

```
Period start → query TK 133 (input VAT) grouped by tax rate
            → query TK 3331 (output VAT) grouped by tax rate
            → calculate:
                [40] = [33] - [25] (net payable)
                [41] = [25] - [33] (excess credit)
            → check HDDT (e-invoice) data from GDT
            → flag mismatches: HDDT vs GL
            → generate 01/GTGT + PL 01-1/GTGT (input), 01-2/GTGT (output)
            → export XML → HTKK upload
```

**VAT rates 2026 (NĐ 181/2025/NĐ-CP):**
- 0%: Export, intl transport
- 5%: Essential goods (food, water, medicine, education)
- 8%: Reduced rate (temporary — check current decree)
- 10%: Standard rate

**Declaration period (NĐ 373/2025/NĐ-CP):**
- Monthly: Revenue > 50 tỷ/year
- Quarterly: Revenue ≤ 50 tỷ/year

## 1.4 How CIT/TNDN Finalized

CIT finalization (03/TNDN) is the annual tax return. Quarterly payments are provisional.

**Flow:**

```
Step 1: Calculate provisional CIT per quarter
    Provisional CIT = (Revenue - Deductible expenses) × 20%
    Due: 30th of month after quarter end

Step 2: Year-end finalization
    Net profit (BC02 Mã số 60) → tax adjustments:
    + Non-deductible expenses (fines, excess depreciation, no invoices)
    - Tax-exempt income (dividends, etc.)
    - Loss carry-forward (max 5 years)
    = Taxable income
    × 20% (standard rate, NĐ 320/2025)
    - Tax incentives (if any)
    = CIT payable
    - Quarterly provisional payments
    = Additional payable / overpayment

Step 3: Post adjustment
    If CIT payable > provisional: Dr 8211 Cr 3334
    If CIT payable < provisional: Dr 3334 Cr 8211
    Then: Dr 911 Cr 8211 (or Dr 8212 Cr 911 for deferred tax)
```

## 1.5 How Subsidiary Ledger Controlled

Subsidiary ledger (sổ chi tiết) tracks each customer (TK 131) and supplier (TK 331) individually. The sum of all sub-ledger balances MUST equal the GL control account balance.

**Reconciliation logic:**

```
Every AP/AR transaction posts to BOTH:
  1. GL: Dr/Cr 131 (total)
  2. Sub-ledger: ap_invoices/ar_invoices (per customer/supplier)

At month-end:
  SELECT SUM(balance) FROM ap_invoices WHERE period = ? → AP sub-total
  SELECT SUM(balance) FROM ar_invoices WHERE period = ? → AR sub-total
  
  ASSERT: AP sub-total = GL balance of TK 331
  ASSERT: AR sub-total = GL balance of TK 131
  
  If mismatch → flag transactions that caused gap → manual correction
```

**This is non-negotiable for audit.** Every auditor checks sub-ledger vs GL.

## 1.6 How Opening Balance Created

Opening balances are the biggest risk in any accounting system. Every new SME system needs to load prior-period balances.

**Logic:**

```
Phase 1: Entity setup
  For each GL account: input ending balance as of last period
  
Phase 2: Sub-ledger setup
  For AR: list each customer with outstanding invoices
  For AP: list each supplier with unpaid invoices
  For Inventory: list each item with quantity and unit cost
  For Fixed Assets: list each asset with cost, accumulated depreciation, useful life

Phase 3: Validation
  Total sub-ledger AR = GL 131 balance
  Total sub-ledger AP = GL 331 balance
  Total inventory value = GL 152/153/155/156 balance
  Total FA net book value = GL 211 - GL 214 balance
  
Phase 4: Period lock
  Opening balance period is CLOSED after validation
  Changes only via correction entries
```

## 1.7 How Correction Engine Works

Under Article 27 Law on Accounting, posted entries cannot be modified or deleted. Three allowed methods:

**Method 1 — Bút toán bổ sung (Supplementary entry)**

When: Recorded correct amount but too low (ghi thiếu)
```
Correct: Dr 156 10M / Cr 331 10M
Recorded: Dr 156 5M / Cr 331 5M
Correction: Dr 156 5M / Cr 331 5M
```

**Method 2 — Bút toán đỏ (Negative/Red ink entry)**

When: Recorded wrong account or wrong amount
```
Wrong: Dr 632 10M / Cr 111 10M
Correction: Dr 632 -10M / Cr 111 -10M (red ink)
Then correct: Dr 152 10M / Cr 111 10M
```

**Method 3 — Bút toán điều chỉnh (Adjusting entry)**

When: Amount correct, account classification wrong
```
Wrong: Dr 641 (selling) 10M / Cr 111 10M
Correct should be: Dr 642 (admin) 10M / Cr 111 10M
Adjustment: Dr 642 10M / Cr 641 10M (reclassify)
```

**System requirements:**
- Every correction must reference original transaction ID
- Correction flag on transaction (`is_correction = true`)
- Audit log: old value, new value, reason, corrected_by
- Report: show original + correction together

## 1.8 How CCDC/TK242 Allocated

CCDC (Công cụ dụng cụ) — tools with value < 30M or useful life < 1 year. Cannot expense immediately per VAS — must allocate over expected usage period.

**Allocation rules (Thiên Ưng source):**

| Value | Treatment | Period |
|---|---|---|
| < 5M | Immediate expense (Dr 627/641/642) | Single period |
| 5M - 30M | TK 242 → allocate over ≤ 24 months | Monthly |
| > 30M | Fixed asset (TK 211) — depreciate | Per FA policy |

**Calculation:**

```
Monthly allocation = (CCDC cost) / (allocation months)

Record at purchase:
  Dr 242 (CCDC) 24M
  Cr 111/331     24M

Monthly allocation:
  Dr 627/641/642 1M (24M / 24 months)
  Cr 242         1M

Remaining balance (Dr 242) = cost - accumulated allocation
```

**System must:**
- Auto-generate monthly allocation entries
- Track remaining allocation period
- Allow custom start date
- Auto-stop when fully allocated
- Support partial disposal

## 1.9 How Bank Statement Imported & Reconciled

Bank reconciliation is every accountant's pain. SME kế toan spends 4-8 hours per month manually matching.

**Import flow:**

```
CSV/MT940 from bank → parse rows:
  - Transaction date
  - Value date
  - Reference/description
  - Debit amount (outflow)
  - Credit amount (inflow)
  - Balance after transaction
  - Bank code, account number

Auto-match algorithm:
  Match by: amount + date (±3 days) + reference partial match
  Scenarios:
    1. Exact match (amount + date + ref) → auto-match
    2. Amount match + date within ±3 days → suggest match
    3. Amount match only → suggest with warning
    4. No match → unmatched item (needs manual review)

Reconciliation process:
  1. Import statement
  2. Review auto-matched items (95%+ match rate with bank ref)
  3. Manually match remaining items
  4. Create adjusting entries for:
     - Bank charges (Dr 642 Cr 112)
     - Bank interest (Dr 112 Cr 515)
     - Exchange rate differences (Dr/ Cr 635/515)
  5. Balance check: GL 112 = Bank statement balance ± unmatched items
  6. Complete → lock session
```

## 1.10 How Pre-Close Checklist Protects Data

Before any period close, software CHECKs — you do not close blind:

```
CHECK 1: Inventory
  - Physical count completed this period?
  - All receipts posted? All issues posted?
  - Cost layers balanced (total qty in = total qty out + ending qty)?

CHECK 2: FC Revaluation
  - All foreign currency balances revalued at period-end rate?
  - FX gain/loss posted (Dr/ Cr 635/515)?

CHECK 3: Sub-ledger = GL
  - AP aging total = TK 331 balance?
  - AR aging total = TK 131 balance?
  - Bank GL balance matches all bank account sub-ledgers?

CHECK 4: Trial Balance
  - Sum of all Dr = Sum of all Cr?
  - BC01: Total Assets (280) = Total Liabilities + Equity (440)?
  - All control accounts have detailed sub-accounts posted?

CHECK 5: Accruals
  - All prepaid expenses (TK 242) allocated to this period?
  - All accrued expenses (TK 335) recorded for this period?
  - All fixed asset depreciation charged this month?
  - All payroll posted this period?

RESULT:
  If ALL passed → close allowed
  If WARNINGS → close allowed with acknowledgment
  If FAILURES → close BLOCKED
```

## 1.11 How Audit Traceability Works

Every transaction in the system must be traceable from FS → GL → Journal → Source document.

```
BC01 balance → sum of GL transactions → 
  individual journal entry → 
    supporting documents (invoice, receipt, contract)

Audit trail records:
  - Original entry: who, when, amount, account, period
  - Correction entries: who, when, original reference, reason
  - Period events: open, close, re-open (by whom, when)
  - Permission changes: role changes, user creation/deactivation
  - System events: backup, migration, config change

ActionJournal (JSON Lines): every HTTP request + response
  - User ID, IP, timestamp, action
  - Before/after values
  - Status code, duration
  - Immutable (write-once, append-only)
```

## 1.12 How Compliance Ensured

**Circular 99/2025/TT-BTC compliance:**
- Chart of accounts seeded per official system
- Control accounts (TK 111, 112, 131, 331, etc.) block direct posting
- Posting rules validate Dr/Cr pairs per module
- Voucher numbering auto-increment per year
- FS templates (BC01/02/03) follow Circular 99 mã số
- Print formats with page numbers, signature fields

**Tax compliance:**
- VAT input/output segregation by tax rate
- VAT declaration data aggregation by period
- CIT calculation: taxable income = accounting profit ± adjustments
- Tax rate mapping per NĐ 320/2025 (20% standard, incentives)
- Tax code (MST) validation on customers/suppliers
- HDDT (e-invoice) tracking for input tax credit

**Law on Accounting 2015:**
- No edits after posting — only correction entries
- Period close = data lock
- Soft delete only (status flags, never DELETE FROM)
- Audit trail on every data change
- 10-year retention for accounting documents

---

# 2. Real SME Closing Scenarios

## 2.1 Month-End Closing

**Context:** Kế toán tổng hợp at a trading SME (50 employees, revenue 50 tỷ/year).

**Timeline:**
```
Day 1-5: Collect all invoices, receipts, bank statements
Day 5-10: Post all transactions, reconcile bank, match AP/AR
Day 10-12: Run pre-close checklist
Day 12-15: Run closing entries, generate FS
Day 15-20: VAT declaration (quarterly), submit
Day 15-20 (CIT): Provisional CIT payment (quarterly)
```

**Pain points:**
- Late invoices from suppliers (arrive after close)
- Bank statement not yet available (3-5 day lag)
- Inter-company transactions not matched
- Accruals not recorded (forgot TK 242 allocation)
- Manual Excel trial balance: formula errors

## 2.2 Year-End Closing

**Context:** Annual FS, CIT finalization, audit preparation.

**Timeline:**
```
Dec 31: Cut-off date
Jan 1-15: Physical inventory count
Jan 1-20: Year-end adjusting entries
  - FC revaluation
  - Inventory impairment (TK 2294)
  - Bad debt provision (TK 2293)
  - CCDC full allocation check
Jan 15-31: BC01/02/03/09 generation
Feb 1-28: CIT finalization (03/TNDN)
Feb 1-28: TNCN finalization (05/TNCN)
Feb 1-28: VAT finalization (not required for monthly declarers)
March 1-31: Audit preparation (if audited)
March 31: Deadline: BC01/02/03/09 + 03/TNDN submission
```

**High-stress items:**
- Inventory count discrepancy → adjusting entries
- Bad debt provision calculation (5 buckets per TT 48)
- CIT adjustments (non-deductible expenses, fines, gifts)
- BC09 manual narratives written last-minute at 11pm
- Audit finding: missing invoices → correction entries

## 2.3 VAT Declaration Submission

**Context:** May 2026, declaring Q1/2026 VAT.

**System generates:**
```
TK 133 (input VAT):
  - Goods/services: 120M (from purchase invoices)
  - Fixed assets: 0M
  - Total: 120M

TK 3331 (output VAT):
  - 10% rate: 200M (from sales invoices)
  - 5% rate: 0M
  - 0% rate: 0M
  - Total: 200M

01/GTGT results:
  [40] VAT payable: 200M - 120M = 80M
  Due: 20/04/2026
```

**Validation:**
- HDDT data (downloaded from GDT) matches GL input: 120M
- Sales invoices (HDDT) match GL output: 200M
- If mismatch → flag: "3 invoices in GL not in HDDT, 5 invoices in HDDT not in GL"

## 2.4 CIT Finalization

**Context:** Year 2025 finalization, due March 2026.

```
Accounting profit (BC02 mã số 60): 1,200M
CIT adjustments:
  + Fines and penalties: 15M (non-deductible)
  + Excess entertainment: 10M (over 15% limit)
  + Invoices without proper info: 5M
  - Tax-exempt dividends: 20M
  - Loss carry-forward from 2023: 50M
Taxable income: 1,200M + 15M + 10M + 5M - 20M - 50M = 1,160M
CIT @ 20%: 232M
Less: Quarterly provisional payments: 210M
Additional payable: 22M + late payment interest
```

## 2.5 Bank Reconciliation Mismatch

**Context:** GL 112 shows 500M. Bank statement shows 485M. Difference = 15M.

**Investigation:**
- Uncleared cheques: 10M (recorded in GL, not yet presented to bank)
- Bank charges: 2M (in bank statement, not yet recorded in GL)
- Bank interest: 3M (in bank statement, not yet recorded in GL)
- Unknown: 0M

**Adjusting entries needed:**
```
Dr 642 2M (bank charges)
Cr 112 2M

Dr 112 3M (interest)
Cr 515 3M

After entries: GL 112 = 501M
Reconciled: 501M - 10M (outstanding cheques) = 491M... wait
```

ACTUAL: Bank statement 485M + outstanding deposits 6M = 491M.
Still off. Found: one deposit recorded in GL but not yet credited by bank (6M).

**True reconciliation:**
```
GL balance after adjustment: 501M
Less: Outstanding cheques: 10M
Add: Deposit in transit: 6M
= 497M... NOT 485M + 0 = 485M

Still 12M gap → investigation continues... 
Found: a payment of 12M recorded in GL as Dr 331 Cr 112, but bank statement shows it was returned NSF.
Correction: Dr 112 12M, Cr 331 12M (reverse NSF)

Final: 501M - 10M + 6M = 497M ≠ 485M + 0 = 485M... STILL 12M.
Wait. After NSF reversal: GL 112 = 513M. 
513M - 10M (outstanding cheques) + 6M (deposit in transit) = 509M.
Bank: 485M + 12M (NSF reversal not yet re-presented) = 497M.
STILL 12M. This is the NSF cheque that was returned and not yet re-deposited.
```

THIS is why kế toán spends hours on reconciliation. Every mismatch tells a story.

## 2.6 Missing Bank Transactions

**Context:** Accountant forgot to record 2 bank transfers:
1. Payment to supplier B: 50M on 15/05
2. Bank charge: 0.5M on 31/05

**Impact on trial balance:**
- TK 112 balance: overstated by 50.5M
- TK 331 balance: overstated by 50M (AP not reduced)
- TK 642: understated by 0.5M (expense not recorded)
- Trial balance still balances (both sides mis-recorded)
- BC01: Cash overstated by 50.5M, AP overstated by 50M, Equity overstated by 0.5M
- FS is WRONG but trial balance is balanced

**Detection:**
- Bank reconciliation catches this immediately
- Missing transactions in GL vs bank statement
- Post adjusting entries to fix

## 2.7 Wrong Subsidiary Ledger

**Context:** Payment to supplier A (50M) was incorrectly recorded against supplier B.

**Impact:**
- AP aging: Supplier A shows 50M overdue (wrong — actually paid)
- Supplier B shows 50M paid (wrong — actually owes)
- Supplier A calls: "Why haven't you paid?"
- Supplier B confused: "You paid us extra?"

**GL impact:**
- TK 331 total balance: CORRECT (50M debited in total)
- But individual supplier balances: WRONG
- Sub-ledger ≠ detailed reality

**Correction:**
```
Reclassify: Dr 331 (Supplier A) 50M (increase payable — reverse wrong payment)
           Cr 331 (Supplier B) 50M (decrease payable — remove wrong credit)
Then record correct: Dr 331 (Supplier B) 50M
                     Cr 112 50M
```

## 2.8 Wrong Opening Balance

**Context:** Company migrated from Excel to new software. Opening balance of TK 131 (AR) was input as 1,000M. Actual outstanding was 1,200M.

**Impact:**
- BC01: AR understated by 200M
- BC01: Equity overstated by 200M (balancing item)
- BC01 doesn't balance? Actually it does — both sides moved
- FS is WRONG but balanced

**Detection:**
- Trial balance last month (Excel) vs this month (new system): AR dropped 200M
- Expected: no major AR collection happened
- Sub-ledger total (sum of all customer balances) = 1,200M ≠ GL 131 = 1,000M
- Mismatch → investigation → found input error

**Correction:**
```
Dr 131 200M (increase AR to correct amount)
Cr 421 200M (retained earnings adjustment — prior period error correction)
```

## 2.9 CCDC Allocation Missing

**Context:** Bought CCDC for 24M in January. Accountant expensed entire amount immediately (Dr 641 24M, Cr 111 24M). Should allocate 24 months.

**Impact:**
- January expense: overstated by 22M (23M of 24M should be TK 242)
- February-December each: understated by 1M
- BC02 for January: profit too low
- BC02 for rest of year: profit too high each month
- Annual BC02: CORRECT total, wrong monthly pattern
- CIT: correct for full year, wrong quarterly provisional payments

**Correction:**
```
Reverse January: Dr 641 -24M, Cr 111 -24M
Record correct: Dr 242 24M, Cr 111 24M
Post January allocation: Dr 641 1M, Cr 242 1M
Auto-schedule: Dr 641 1M, Cr 242 1M for months 2-24
```

## 2.10 TK242 Expense Not Allocated

**Context:** CCDC recorded in TK 242 (24M) but allocation entries were never created. 6 months later, TK 242 balance is still 24M.

**Impact:**
- Monthly expenses understated by 1M (months 1-6)
- TK 242 balance overstated by 6M (should be 18M)
- BC01: Prepaid expenses overstated by 6M
- BC02: Operating expenses understated by 6M cumulative

**Fix:**
- One-time catch-up: Dr 641 6M, Cr 242 6M
- Then set up auto-allocation for remaining 18 months

## 2.11 Late Invoice Posting

**Context:** Supplier sent invoice dated March 25. Accountant was on leave. Invoice only posted April 5 (after period close).

**Impact:**
- March expenses understated
- March AP understated
- March inventory cost understated (if goods received but invoice not posted)
- April expenses overstated (reversal/reclassification needed)

**Handling:**
- If goods received in March: record accrual in March (Dr 152/156 Cr 331)
- When invoice arrives April: reverse accrual, record invoice
- If no accrual was made: correction entry in April (prior period adjustment)

## 2.12 Wrong Accounting Entry Correction

**Context:** Recorded Dr 632 100M Cr 152 100M for goods issue. Should be Dr 632 80M (correct COGS based on actual cost).

**Impact:**
- COGS overstated by 20M
- Inventory understated by 20M
- Profit understated by 20M

**Correction (Method 1 — Supplementary):**
```
Dr 152 20M (reverse excess inventory)
Cr 632 20M (reduce COGS)
```
(Basically red ink on original entry, then correct)

OR method 2 — Negative entry:
```
Dr 632 -20M (red ink to reverse excess)
Cr 152 -20M
```

OR method 3 — Just adjust:
```
Dr 152 20M (restore inventory)
Cr 632 20M (adjust COGS)
```

## 2.13 Re-Open Closed Period

**Context:** After year-end close, auditor finds: missing depreciation for October.

**Problem:**
- Period is CLOSED (read-only)
- Cannot directly edit October entries
- Must use correction entries

**Process:**
1. Kế toán trưởng authorization (dual control)
2. Re-open period with audit trail (timestamp, reason, authorized by)
3. Post correction entry dated within October
4. Re-close period
5. Audit log: re-open event, correction entry, re-close event

**Risk:** If re-open is abused, audit trail breaks. Every re-open is logged and flagged for auditors.

## 2.14 Audit Adjustment Entries

**Context:** External auditor discovers:
- Revenue recognition: 500M recorded in Dec but goods shipped Jan 5 → should be Jan revenue
- Depreciation: asset useful life overstated by 3 years → excess depreciation 50M/year

**Audit adjustments:**
```
Adjustment 1:
  Dr 511 (Revenue) 500M (decrease revenue — wrong period)
  Cr 131 (AR) 500M
  
  Then in Jan: Dr 131 500M, Cr 511 500M

Adjustment 2:
  Dr 421 (Retained earnings) 150M (cumulative excess depreciation 3 years)
  Cr 214 (Accum deprec) 150M
  
  Future: correct depreciation schedule
```

**System must:**
- Allow auditor to propose adjustments
- Track adjustments separately (flag: `is_audit_adjustment = true`)
- Apply adjustments only after management approval
- Show FS before and after audit adjustments

---

# 3. Use Cases

## UC-001: BC09 Thuyết Minh BCTC

**Business Goal:** Generate complete BC09 with 9 sections, cross-referenced to BC01/02/03.

**Actors:** Kế toán tổng hợp, Kế toán trưởng, Kiểm toán

**Preconditions:** BC01/02/03 generated, period closed, accounting policies configured

**Trigger Event:** Year-end FS generation request

**Happy Path:**
1. User selects year-end period
2. System auto-populates sections I-III, V-VIII from fs_snapshots + fs_line_items
3. System calculates variances (current vs prior year)
4. User fills Section IV (explanation) and Section IX (off-BS)
5. System validates: every mã số on BC01/02/03 has a corresponding note in BC09
6. User reviews, edits narrative disclosures
7. Kế toán trưởng approves
8. Generate PDF/Excel with signature blocks

**Alternative Paths:**
- Mid-year BC09 (for audit purposes): use current period balances
- Comparative period not available: show single column only

**Exception Paths:**
- BC01/02/03 not yet generated → block with message
- Prior year data not available → allow but flag
- Validation failure: mã số X missing from BC09 → highlight missing

**Validation Rules:**
- Every mã số 00-280 (BC01) and 01-60 (BC02) and 01-70 (BC03) → has BC09 note
- Sum of detailed notes = BC total for each line item
- Narrative fields: minimum 50 characters for Section IV

**Accounting Rules:**
- BC09 is integral part of FS — cannot submit FS without it
- 29 accounting policy disclosures per VAS 21
- Notes must explain significant variances (>20% change year-over-year)

**Tax Rules:**
- BC09 Section V (tax) must match CIT finalization
- Tax breakdown: current tax + deferred tax

**Financial Impact:** Incomplete BC09 → auditor qualification → bank covenant breach

**Audit Risk:** HIGH — missing notes = non-compliance with VAS 21

**Final Result:** PDF/Excel BC09 with all 9 sections, cross-referenced, approved, ready for submission

## UC-002: PDF/Excel Export BCTC

**Business Goal:** Export any report (BC01/02/03/09, GL, subsidiary ledger, aging) to PDF or Excel.

**Actors:** All users

**Preconditions:** Report generated, data available

**Trigger Event:** User clicks "Export" or "Print"

**Happy Path:**
1. User views report on screen
2. Clicks "Xuất PDF" or "Xuất Excel"
3. System generates formatted document:
   - PDF: A4 landscape/portrait, company logo, header/footer, page numbers, date printed
   - Excel: formatted columns, frozen header row, auto-width, print area set
4. User downloads file

**Alternative Paths:**
- Batch export: select multiple reports → single PDF
- Email report directly from system
- Schedule automatic PDF generation (e.g., monthly FS to management)

**Exception Paths:**
- Large report (>10,000 rows): warn user, export in batches
- No data for selected period: export empty template
- Font not available: fallback to standard font

**Validation Rules:**
- Column widths preserved
- Vietnamese characters rendered correctly
- Signature lines present for legal documents
- Total rows bold, formulas if Excel

**Accounting Rules:**
- BC01/02/03 must have: company name, address, MST, period, prepared by, reviewed by, approved by
- Page numbering: "Page X of Y"
- Signatures: Kế toán trưởng, Tổng giám đốc, Người lập biểu

**Tax Rules:** N/A

**Financial Impact:** Inability to export = cannot submit FS to tax authority

**Audit Risk:** MEDIUM — printed reports must match system data

**Final Result:** Downloadable PDF/Excel

## UC-003: Subsidiary Ledger (Sổ Chi Tiết)

**Business Goal:** View detail of any account by customer/supplier with running balance.

**Actors:** Kế toán công nợ, Kế toán tổng hợp, Kiểm toán

**Preconditions:** Transactions posted, account selected

**Trigger Event:** User opens subsidiary ledger for TK 131 or TK 331

**Happy Path:**
1. User selects account (131 or 331)
2. Selects customer/supplier (or "All")
3. Selects date range
4. System displays:
   - Date, reference, description, customer/supplier
   - Opening balance, debit, credit, running balance
   - Contra account
5. User can filter by: date range, amount range, document type
6. User can export to PDF/Excel
7. Total column matches GL control account balance

**Alternative Paths:**
- View by contract/project (grouping)
- View with aging split (current, 30, 60, 90+)
- Print format with signature blocks (for legal binding)

**Exception Paths:**
- No transactions: display opening balance only
- Customer/supplier not found: show error
- Period closed: display as read-only

**Validation Rules:**
- Sum of all subsidiary details = GL balance
- Opening balance from previous period closing balance
- Running balance calculated after each transaction

**Accounting Rules:**
- Every AR/AP transaction must have customer/supplier (no orphan entries)
- Control account (TK 131/331) can only post through sub-ledger
- Sub-ledger = GL enforced at month-end close

**Tax Rules:** N/A

**Financial Impact:** Incorrect AR/AP → wrong BC01 → wrong CIT

**Audit Risk:** HIGH — every auditor requests subsidiary ledgers

**Final Result:** Filtered, exportable subsidiary ledger

## UC-004: Correction Engine — Supplementary Entry

**Business Goal:** Correct a posted entry that recorded too low an amount.

**Actors:** Kế toán tổng hợp, Kế toán trưởng (approve)

**Preconditions:** Original transaction posted, period may be closed

**Trigger Event:** User identifies under-recorded entry

**Happy Path:**
1. User opens original transaction
2. Selects "Điều chỉnh bổ sung"
3. System shows original amounts
4. User enters correct amount (system calculates difference)
5. User enters reason for correction
6. Kế toán trưởng approves (if amount > threshold)
7. System creates new transaction:
   - Reference to original
   - Amount = difference
   - `is_correction = true`
   - `correction_type = supplementary`
   - Date = current date (or original period if re-opened)
8. Both original and correction shown in GL

**Alternative Paths:**
- Bulk correction: select multiple transactions
- Auto-correction for systematic errors (e.g., wrong exchange rate)

**Exception Paths:**
- Period closed and cannot re-open → post to current period
- Amount difference = 0 → no correction needed
- Correct amount < original → use negative entry instead

**Validation Rules:**
- Correction must balance (Dr = Cr)
- Reference original transaction ID
- Reason field required (min 20 characters)
- If period closed: requires Kế toán trưởng approval

**Accounting Rules:**
- Article 27 method 1: supplementary entry
- Original transaction preserved, never modified
- Audit log: "Corrected transaction X: added Y VND"

**Tax Rules:** If correction affects VAT: adjust in next declaration period

**Financial Impact:** Changes P&L for the correction period

**Audit Risk:** MEDIUM — corrections must be clearly identifiable

**Final Result:** New correction transaction linked to original

## UC-005: Opening Balance Setup

**Business Goal:** Load prior period balances when migrating to the system.

**Actors:** Kế toán trưởng, Kế toán tổng hợp

**Preconditions:** COA configured, no transactions exist in system

**Trigger Event:** Initial system setup or year migration

**Happy Path:**
1. User accesses "Số dư đầu kỳ"
2. Enters GL balance for each account:
   - Asset accounts: Dr balance
   - Liability/Equity accounts: Cr balance
   - Revenue/Expense: zero (reset each period)
3. System validates: total Dr = total Cr
4. User enters sub-ledger details:
   - AR: customer invoices outstanding
   - AP: supplier invoices unpaid
   - Inventory: items × quantity × unit cost
   - FA: asset details with accumulated depreciation
5. System validates: sub-ledger totals = GL balance
6. User confirms and locks opening period
7. Opening balances used for comparative FS

**Alternative Paths:**
- Import from Excel template
- Import from previous system (API/custom format)
- Sequential migration: first GL, then sub-ledger

**Exception Paths:**
- Opening Dr ≠ Cr → block with message
- Sub-ledger ≠ GL → warn, allow override with reason
- Missing account → freeze until resolved
- Duplicate entries → flag for review

**Validation Rules:**
- Total Dr = Total Cr (absolute, no tolerance)
- Sub-ledger sum = GL control account balance
- No transactions exist before opening balance period
- All accounts on COA have opening balance (even if zero)

**Accounting Rules:**
- Opening balance period is special (type = `opening`)
- Once locked: only correction entries can modify
- Opening balances = ending balances of prior period
- Revenue/Expense accounts begin at zero

**Tax Rules:** Prior period tax balances (TK 3331, 3334) must match last return

**Financial Impact:** Wrong opening balances → wrong FS for entire year

**Audit Risk:** CRITICAL — most common migration error

**Final Result:** Locked opening balance period

## UC-006: Pre-Close Checklist Execution

**Business Goal:** Ensure all conditions met before period close.

**Actors:** Kế toán tổng hợp, Kế toán trưởng

**Preconditions:** User initiates period close

**Trigger Event:** User clicks "Đóng kỳ"

**Happy Path:**
1. User initiates close for selected period
2. System runs 5 checks:
   - Inventory count completed?
   - FC revaluation posted?
   - Sub-ledger = GL?
   - Trial balance balanced (Dr=Cr)?
   - All recurring entries posted?
3. All checks pass (green)
4. System runs closing entries:
   - Dr 511/515/711 → Cr 911
   - Dr 911 → Cr 632/635/641/642
   - Dr 911 → Cr 421 (or Dr 421 → Cr 911)
5. System locks period (read-only)
6. User confirms success

**Alternative Paths:**
- Warning level: allow close with acknowledgment
- Manual override: Kế toán trưởng bypass with reason
- Partial close: close some modules, leave others open

**Exception Paths:**
- Check FAIL: block close, show details
- Closing entry error: rollback, log audit
- Concurrent close attempt: lock prevents second attempt
- Prior period not closed: must close sequentially

**Validation Rules:**
- All 5 checks must pass (or manually overridden)
- Closing entries must balance
- No pending unapproved transactions
- Inventory module must be marked "counted"

**Accounting Rules:**
- Closing entries auto-generated
- Kết chuyển doanh thu: Dr 5111/5112/5113 → Cr 911
- Kết chuyển chi phí: Dr 911 → Cr 632/635/641/642
- Kết chuyển lãi lỗ: Dr 911 (lãi) → Cr 421
- Period lock: no inserts/updates/deletes

**Tax Rules:** Quarter-end close = VAT declaration prep

**Financial Impact:** Close without checks → FS errors → tax penalties

**Audit Risk:** HIGH — improper close is red flag for auditors

**Final Result:** Closed period with closing entries

## UC-007: Bank Statement Import & Reconciliation

**Business Goal:** Import bank statement, auto-match, manual review, complete reconciliation.

**Actors:** Kế toán tiền gửi ngân hàng, Kế toán tổng hợp

**Preconditions:** Bank account configured, GL transactions exist

**Trigger Event:** Bank statement (CSV/MT940) received

**Happy Path:**
1. User downloads CSV/MT940 from internet banking
2. Uploads to system
3. System parses: all rows with date, amount, reference, balance
4. Auto-matching runs: match by amount + date ± 3 days + reference
5. Results: 90% auto-matched, 10% unmatched
6. User reviews each unmatched item:
   - Checks bank transactions not in GL
   - Checks GL transactions not in bank
7. User manually matches remaining
8. User records adjusting entries:
   - Bank charges (Dr 642 Cr 112)
   - Interest (Dr 112 Cr 515)
   - NSF return (Dr 331 Cr 112)
9. System checks: GL 112 = bank balance ± unmatched items
10. User confirms → session complete

**Alternative Paths:**
- No GL transactions for period: warn but allow
- Multiple bank accounts: reconcile each separately
- Foreign currency accounts: exchange rate handling

**Exception Paths:**
- Import file format not supported → manual entry only
- Duplicate import (same statement loaded twice) → reject
- GL balance differs significantly from bank → investigate
- Session incomplete (user closes browser) → resume from last save

**Validation Rules:**
- Opening balance previous session = closing balance this session
- Every bank transaction → matched to 0 or 1 GL transactions
- No duplicate matches
- Adjusting entries must balance

**Accounting Rules:**
- Bank reconciliation is independent per period
- Adjusting entries posted to current period
- Outstanding items carry forward to next session
- Complete reconciliation required before period close

**Tax Rules:** Bank charges deductible for CIT (with proper invoice)

**Financial Impact:** Unreconciled bank = cash misstatement on BC01

**Audit Risk:** HIGH — auditor always checks bank rec

**Final Result:** Completed bank reconciliation session

## UC-008: CCDC/TK242 Allocation Schedule

**Business Goal:** Allocate CCDC cost over multiple periods.

**Actors:** Kế toán kho, Kế toán tổng hợp

**Preconditions:** CCDC registered in TK 242

**Trigger Event:** CCDC purchase recorded or monthly allocation due

**Happy Path (Monthly Allocation Auto-Run):**
1. System checks: any TK 242 entries with remaining allocation > 0
2. For each: calculate monthly allocation = cost / total months
3. Generate allocation entries:
   - Dr 627/641/642 X VND
   - Cr 242 X VND
4. Update remaining allocation
5. If fully allocated: mark as done
6. Log allocation history

**Happy Path (New CCDC Registration):**
1. User records CCDC purchase
2. System prompts: allocate? If yes:
   - Select allocation period: 6/12/24 months
   - Select expense account: 627/641/642
   - Select start month
3. System schedules allocation entries
4. Monthly auto-run picks it up

**Alternative Paths:**
- Partial disposal: reverse remaining allocation
- Change allocation method: requires Kế toán trưởng approval
- Immediate expense (value < 5M): bypass TK 242 entirely

**Exception Paths:**
- Cost = 0: no allocation needed
- Allocation period ended: auto-stop, flag for review
- TK 242 no balance: nothing to allocate

**Validation Rules:**
- Monthly amount = Cost / Months (rounded to nearest đồng, last month absorbs remainder)
- Expense account must be P&L account (class 6/8)
- Allocation start date cannot be in closed period
- Cannot allocate more than remaining balance

**Accounting Rules:**
- TK 242 is an asset (prepaid expense)
- Monthly allocation reduces TK 242, increases expense
- Period of allocation: max 24 months per Circular 99
- If value ≥ 30M: classify as FA, not CCDC

**Tax Rules:** CCDC allocation deductible for CIT if matching Circular 99 criteria

**Financial Impact:** Incorrect allocation → wrong expense pattern → wrong CIT per quarter

**Audit Risk:** LOW-MEDIUM — rarely audited in detail unless material

**Final Result:** Scheduled allocation entries, auto-executed monthly

---

# 4. Process & Workflow Logic

## 4.1 End-to-End Closing Workflow

```
┌─────────────────────────────────────────────────────────────────┐
│                      DAILY ACCOUNTING                           │
│  Input invoices → Post transactions → Match bank → OK?         │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                    MONTH-END (T-10 DAYS)                        │
│  Collect all: invoices, receipts, bank statements               │
│  Post all pending entries                                       │
│  Run bank reconciliation                                        │
│  Record accruals and prepayments                                │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                    PRE-CLOSE CHECKS                             │
│  [1] Inventory counted?                                         │
│  [2] FC revalued?                                               │
│  [3] Sub-ledger = GL?                                           │
│  [4] Trial balance balanced?                                    │
│  [5] Recurring entries posted?                                  │
│                                                                │
│  Result: ALL PASS → continue                                    │
│  Result: FAIL → investigate and fix                             │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                    CLOSING ENTRIES                              │
│  Auto: Dr 511/515/711 → Cr 911 (close revenue)                 │
│  Auto: Dr 911 → Cr 632/635/641/642 (close expenses)            │
│  Auto: Dr 911 (profit) → Cr 421 / Dr 421 → Cr 911 (loss)      │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                    PERIOD LOCK                                  │
│  Period status = CLOSED                                         │
│  No inserts/updates/deletes allowed                             │
│  FS snapshots saved (bc01/02/03)                                │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                    REPORTING                                    │
│  Generate trial balance                                         │
│  Generate BC01/02                                               │
│  Generate BC03 (if quarter/year)                                │
│  Generate BC09 (if year-end)                                    │
│  Export PDF/Excel                                               │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                    TAX (QUARTERLY/YEARLY)                      │
│  Aggregate VAT → 01/GTGT → Submit HTKK                         │
│  Provisional CIT (quarterly) → submit                          │
│  CIT finalization (yearly) → 03/TNDN                            │
│  TNCN finalization (yearly) → 05/TNCN                           │
└─────────────────────────────────────────────────────────────────┘
```

## 4.2 Pre-Close Checklist Flow

```
                    INITIATE CLOSE
                          │
                          ▼
            ┌─────────────────────────┐
            │ RUN PRE-CLOSE CHECKS    │
            └─────────────────────────┘
                          │
                          ▼
         ┌────────────────────────────────────┐
         │ CHECK 1: Physical inventory done?  │← inventory_count table: period = current, status = completed
         │ CHECK 2: FC revaluation posted?    │← fx_revaluation table: period = current
         │ CHECK 3: Sub-ledger = GL?          │← compare ap_invoices/ar_invoices sum vs GL 131/331
         │ CHECK 4: Trial balance Dr = Cr?    │← ledger_entries sum by period
         │ CHECK 5: Recurring entries posted? │← CCDC allocation, FA depreciation, prepaid amortization
         └────────────────────────────────────┘
                          │
                          ▼
               ┌──────────────────────┐
               │ ALL CHECKS PASS?     │
               └──────────────────────┘
              /                      \
            YES                      NO
             │                        │
             ▼                        ▼
    ┌──────────────────┐   ┌─────────────────────┐
    │ GENERATE CLOSING  │   │ SHOW FAILED CHECKS  │
    │ ENTRIES           │   │ WITH DETAILS        │
    └──────────────────┘   └─────────────────────┘
             │                        │
             ▼                        ▼
    ┌──────────────────┐   ┌─────────────────────┐
    │ LOCK PERIOD       │   │ INVESTIGATE & FIX   │
    │ STATUS = CLOSED   │   │ OR OVERRIDE (KTG)   │
    └──────────────────┘   └─────────────────────┘
             │                        │
             ▼                        │
    ┌──────────────────┐              │
    │ SAVE FS SNAPSHOT │              │
    └──────────────────┘              │
             │                        │
             ▼                        ▼
          DONE                    RE-RUN CHECKS
```

## 4.3 Data Locking After Close

```
┌───────────────────────────────────────────────┐
│              PERIOD STATE MACHINE              │
│                                                │
│  OPEN → (edit/delete/post allowed)             │
│    ↓                                           │
│  CLOSED → (read-only)                          │
│    ↓                                           │
│  RE-OPENING → (Kế toán trưởng approves)        │
│    ↓                                           │
│  OPEN (temporary) → (correction entries only)  │
│    ↓                                           │
│  CLOSED (re-locked)                            │
│                                                │
│  Each transition: audit log entry              │
│  - who, when, reason, IP                       │
│  - re-open counter incremented                 │
│  - flagged for auditor review                   │
└───────────────────────────────────────────────┘
```

## 4.4 Correction Approval Flow

```
                    ERROR DETECTED
                          │
                          ▼
            ┌─────────────────────────┐
            │ CREATE CORRECTION       │
            │ ENTRY (DRAFT)           │
            │ Refer to original TXN   │
            └─────────────────────────┘
                          │
                          ▼
         ┌────────────────────────────────────┐
         │ AMOUNT < THRESHOLD (10M)?          │
         └────────────────────────────────────┘
        /                                      \
      YES                                      NO
       │                                        │
       ▼                                        ▼
┌─────────────────┐                  ┌─────────────────────┐
│ AUTO-APPROVE    │                  │ SEND TO KẾ TOÁN     │
│ POST DIRECTLY   │                  │ TRƯỞNG FOR REVIEW   │
└─────────────────┘                  └─────────────────────┘
       │                                        │
       ▼                                        ▼
┌─────────────────┐                  ┌─────────────────────┐
│ POST + AUDIT    │                  │ APPROVED?           │
│ LOG             │                  └─────────────────────┘
└─────────────────┘                 /                      \
                                    YES                    NO
                                     │                      │
                                     ▼                      ▼
                          ┌─────────────────┐    ┌─────────────────┐
                          │ POST + AUDIT LOG │    │ REJECT + REASON │
                          └─────────────────┘    └─────────────────┘
```

## 4.5 Subsidiary Ledger Reconciliation Flow

```
                    MONTH-END CLOSE INITIATED
                          │
                          ▼
         ┌────────────────────────────────────┐
         │ COMPUTE SUB-LEDGER SUMS            │
         │                                    │
         │ AR: SELECT SUM(balance)            │
         │     FROM ar_invoices               │
         │     WHERE period = current         │
         │                                    │
         │ AP: SELECT SUM(balance)            │
         │     FROM ap_invoices               │
         │     WHERE period = current         │
         │                                    │
         │ GL: SELECT SUM(Dr) - SUM(Cr)       │
         │     FROM ledger_entries            │
         │     WHERE account IN (131, 331)    │
         │     AND period = current           │
         └────────────────────────────────────┘
                          │
                          ▼
               ┌──────────────────────┐
               │ AR_SUB = GL_131?    │
               │ AP_SUB = GL_331?    │
               └──────────────────────┘
              /                      \
            YES                     NO
             │                       │
             ▼                       ▼
    ┌──────────────────┐   ┌──────────────────────────┐
    │ PASS             │   │ FLAG MISMATCH             │
    │ CONTINUE CLOSE   │   │ LIST TRANSACTIONS IN GAP  │
    └──────────────────┘   │ SEND TO KẾ TOÁN CÔNG NỢ  │
                           └──────────────────────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │ INVESTIGATE & FIX    │
                            │ - Missing sub-ledger │
                            │ - Wrong account      │
                            │ - Journal posted     │
                            │   without sub-ledger │
                            └──────────────────────┘
                                       │
                                       ▼
                            ┌──────────────────────┐
                            │ Reconcile again      │
                            │ If still mismatch:   │
                            │ correction entry     │
                            └──────────────────────┘
```

## 4.6 VAT Declaration Workflow

```
┌───────────────────────────────────────────────────────────────┐
│                   PERIOD-END VAT PREP                         │
│                                                               │
│  1. System queries:                                           │
│     - TK 133 (input VAT) grouped by 5%/8%/10%/0%             │
│     - TK 3331 (output VAT) grouped by 5%/8%/10%/0%           │
│                                                               │
│  2. System compares with HDDT (e-invoice) data:               │
│     - e-invoice data downloaded from GDT API                  │
│     - Match: invoice GL vs e-invoice                          │
│     - Flag: invoices in GL not in HDDT                        │
│     - Flag: invoices in HDDT not in GL                        │
│                                                               │
│  3. User reviews flags, resolves discrepancies                │
│                                                               │
│  4. Generate 01/GTGT (or 04/GTGT) data                       │
│                                                               │
│  5. Export XML → HTKK submission                              │
│                                                               │
│  6. Record payment or credit carry-forward                    │
└───────────────────────────────────────────────────────────────┘
```

## 4.7 CIT Finalization Workflow

```
┌───────────────────────────────────────────────────────────────┐
│                   YEAR-END CIT FINALIZATION                   │
│                                                               │
│  1. Accounting profit (BC02 mã số 60) calculated               │
│                                                               │
│  2. System applies CIT adjustments:                           │
│     + Non-deductible expenses (by expense category)           │
│     + Excess over limits (entertainment, depreciation)        │
│     - Tax-exempt income                                       │
│     - Loss carry-forward (max 5 years)                        │
│                                                               │
│  3. Taxable income = adjusted profit                          │
│                                                               │
│  4. CIT payable = taxable income × 20%                        │
│                                                               │
│  5. Less provisional quarterly payments                       │
│                                                               │
│  6. Additional payable or overpayment                         │
│                                                               │
│  7. Generate 03/TNDN → XML → HTKK                             │
│                                                               │
│  8. Post adjusting entry: Dr 8211/Cr 3334 or Dr 3334/Cr 8211  │
│                                                               │
│  9. Finalize: Dr 911/Cr 8211, Dr 8212/Cr 911 (deferred tax) │
└───────────────────────────────────────────────────────────────┘
```

## 4.8 Opening Balance Carry-Forward Workflow

```
┌───────────────────────────────────────────────────────────────┐
│                   YEAR TRANSITION - CARRY FORWARD             │
│                                                               │
│  1. System identifies period ending Dec 31 (year-end)        │
│                                                               │
│  2. For each GL account:                                      │
│     - Ending balance Dec 31 = opening balance Jan 1          │
│     - Revenue/Expense accounts: reset to zero                │
│                                                               │
│  3. For sub-ledgers:                                          │
│     - AR: carry forward all outstanding invoices              │
│     - AP: carry forward all unpaid invoices                   │
│     - Inventory: carry forward ending qty × cost             │
│     - FA: carry forward cost, accumulated depreciation       │
│                                                               │
│  4. Validation:                                               │
│     - Sub-ledger total = GL control account                   │
│     - Total assets = total liabilities + equity              │
│     - No orphan transactions                                  │
│                                                               │
│  5. Lock transition period                                    │
│                                                               │
│  6. New year: period 1 is OPEN for transactions               │
└───────────────────────────────────────────────────────────────┘
```

## 4.9 Bank Reconciliation Workflow

```
┌───────────────────────────────────────────────────────────────┐
│                BANK RECONCILIATION FLOW                        │
│                                                               │
│  START → Import bank statement (CSV/MT940)                    │
│    → Auto-match (amount + date + ref)                         │
│    → Review unmatched items                                   │
│    → Manual match remaining                                    │
│    → Record adjusting entries (bank charges, interest, NSF)   │
│    → Check: GL balance ± unmatched = bank balance             │
│    → If OK: complete session                                  │
│    → If NOT: investigate, find missing transactions           │
│    → Post corrections                                         │
│    → Re-run match                                             │
│    → OK → complete                                            │
└───────────────────────────────────────────────────────────────┘
```

## 4.10 CCDC/TK242 Allocation Workflow

```
┌───────────────────────────────────────────────────────────────┐
│                CCDC ALLOCATION FLOW                            │
│                                                               │
│  Monthly auto-run (scheduled task):                           │
│    → Query TK 242 entries with remaining > 0                  │
│    → For each:                                                │
│      → If allocation_start <= current_month:                  │
│        → Calculate monthly amount = cost / total_months       │
│        → Generate entry: Dr 627/641/642 Cr 242                │
│        → Update remaining allocation                          │
│        → If fully allocated (remaining = 0): mark done        │
│    → Log allocation history                                   │
│                                                               │
│  On-demand (new CCDC purchase):                               │
│    → Record purchase: Dr 242 Cr 111/331                      │
│    → Set: cost, total_months, expense_account, start_month   │
│    → System adds to auto-run queue                            │
└───────────────────────────────────────────────────────────────┘
```

## 4.11 Audit Preparation Workflow

```
┌───────────────────────────────────────────────────────────────┐
│                AUDIT PREPARATION FLOW                          │
│                                                               │
│  1. Export all GL transactions for the year                   │
│  2. Export subsidiary ledgers (AR, AP by customer/supplier)   │
│  3. Export trial balance by month                             │
│  4. Export all FS (BC01/02/03/09)                             │
│  5. Export bank reconciliation for all months                 │
│  6. Export FA movement schedule (cost, deprec, NBV)           │
│  7. Export inventory listing (item × qty × value)            │
│  8. Export aging reports (AR, AP)                             │
│  9. Export tax returns (01/GTGT, 03/TNDN, 05/TNCN)            │
│ 10. Export audit trail (corrections, period events, RBAC)    │
│ 11. Package all into audit folder                             │
│ 12. Generate audit confirmation letters for banks/AR/AP      │
└───────────────────────────────────────────────────────────────┘
```

## 4.12 Exception Handling Workflow

```
┌───────────────────────────────────────────────────────────────┐
│                EXCEPTION HANDLING                              │
│                                                               │
│  EXCEPTION TYPE              → HANDLING                        │
│  ─────────────────────────────────────────────────────         │
│  Dr ≠ Cr in entry            → Block, show difference          │
│  Post to control account     → Block, suggest sub-account     │
│  Post to closed period       → Block, require re-open         │
│  Duplicate invoice number    → Warn, allow with confirmation  │
│  Duplicate voucher number    → Block (FOR UPDATE exclusive)   │
│  Missing customer/supplier   → Block, require selection       │
│  Negative inventory          → Warn, allow with reason        │
│  Amount > credit limit       → Warn, require approval         │
│  Period close with issues    → Block, show unresolved items   │
│  Re-open already open period → Block, close first             │
│  Delete posted transaction   → Block (can only correct)       │
│  Login from new device       → Verify 2FA if enabled          │
└───────────────────────────────────────────────────────────────┘
```

---

# 5. Data Flow Logic

## 5.1 Source of Accounting Data

```
TRANSACTION SOURCES → SYSTEM:

  Source Document                    System Entry
  ─────────────────────────────────────────────────
  Sales invoice (HDDT)          → Dr 111/112/131
                                  Cr 511 + Cr 3331

  Purchase invoice             → Dr 152/156 + Dr 1331
                                  Cr 111/112/331

  Cash receipt (Phiếu thu)    → Dr 1111
                                  Cr 131/511/338

  Cash payment (Phiếu chi)    → Dr 152/331/642
                                  Cr 1111

  Bank statement (CSV/MT940)  → Auto-import
                                  → reconciliation session

  Payroll sheet                → Dr 622/627/641/642
                                  Cr 334 + Cr 338

  Depreciation schedule        → Dr 627/641/642
                                  Cr 214

  CCDC allocation schedule     → Dr 627/641/642
                                  Cr 242

  Physical count adjustment    → Dr 152/156 (surplus)
                                  Cr 711 (or reverse for deficit)
```

## 5.2 Movement of Journal Entries Into Ledgers

```
                 JOURNAL ENTRY
                      │
                      ▼
          ┌─────────────────────┐
          │ JournalService::    │
          │ postEntry()         │
          │                     │
          │ Validation:         │
          │ - Dr = Cr?          │
          │ - Account exists?   │
          │ - Period open?      │
          │ - Control account?  │
          │ - Posting rule?     │
          │ - Voucher unique?   │
          └─────────────────────┘
                      │ (validated)
                      ▼
         ┌──────────────────────────┐
         │ INSERT transactions      │
         │ INSERT ledger_entries    │
         │ UPDATE account balance   │
         └──────────────────────────┘
                      │
                      ▼
         ┌──────────────────────────┐
         │ UPDATE sub-ledger        │
         │ (if AR/AP/inventory):    │
         │ - AR: ar_invoices        │
         │ - AP: ap_invoices        │
         │ - Inventory: stock_qty   │
         │   cost_layers            │
         └──────────────────────────┘
                      │
                      ▼
         ┌──────────────────────────┐
         │ AUDIT LOG:               │
         │ - AuditLogger::log()     │
         │ - ActionJournal::record()│
         └──────────────────────────┘
```

## 5.3 Subsidiary Ledger to General Ledger Matching

```
SUB-LEDGER                         GENERAL LEDGER
───────────                        ──────────────

  AR Invoices (per customer)        TK 131 (total)
  ─────────────────────             ─────────────
  Customer A: 100M                  │
  Customer B: 200M                  │
  Customer C: 50M                   │
  Customer D: 150M                  │
  ─────────────────────             │
  TOTAL: 500M ─────────────────► Dr balance: 500M
                                    │
  AP Invoices (per supplier)        TK 331 (total)
  ─────────────────────             ─────────────
  Supplier X: 300M                  │
  Supplier Y: 150M                  │
  Supplier Z: 100M                  │
  ─────────────────────             │
  TOTAL: 550M ─────────────────► Cr balance: 550M
                                    │
  MATCH CHECK:                      │
  IF sub-total ≠ GL balance →        │
    FLAG: missing entries            │
    Find transactions in GL         │
    without sub-ledger record       │
```

## 5.4 Bank Statement to Cash Ledger Matching

```
BANK STATEMENT                 GENERAL LEDGER TK 112
──────────────                 ──────────────────────

  Date  │ Ref  │ Amount         Date  │ Ref  │ Amount
  ──────┼──────┼───────         ──────┼──────┼───────
  01/05 │ INV1 │ 50,000         01/05 │ INV1 │ 50,000  ← MATCH
  03/05 │ INV2 │ 30,000         03/05 │ INV2 │ 30,000  ← MATCH
  05/05 │ CHRG │ -2,000         05/05 │  —   │   —     ← UNMATCHED BANK
  07/05 │  —   │  —              07/05 │ PAY3 │ 10,000  ← UNMATCHED GL
  10/05 │ PAY4 │ 20,000         10/05 │ PAY4 │ 20,000  ← MATCH
  31/05 │ INT  │ 5,000            —   │  —   │   —     ← UNMATCHED BANK

  MATCHING RESULTS:
  ✓ 3 exact matches (50k + 30k + 20k)
  ✗ Bank charges 2k → need entry: Dr 642 Cr 112
  ✗ GL payment 10k (outstanding cheque) → carry forward
  ✗ Bank interest 5k → need entry: Dr 112 Cr 515

  After adjusting entries:
  GL balance + unmatched GL items = Bank balance + unmatched bank items
```

## 5.5 Expense Allocation Flow (TK242 → Expense)

```
                    TIMELINE
                       │
    PURCHASE MONTH     ▼
    ┌────────────────────────┐
    │ Dr 242 (CCDC) 24M     │  ← Asset recorded
    │ Cr 111/331     24M    │
    └────────────────────────┘
                       │
                       ▼
    MONTH 1            │
    ┌────────────────────────┐
    │ Dr 641 1M             │  ← Monthly allocation
    │ Cr 242  1M            │
    │ Remaining: 23M        │
    └────────────────────────┘
                       │
                       ▼
    MONTH 2            │
    ┌────────────────────────┐
    │ Dr 641 1M             │
    │ Cr 242  1M            │
    │ Remaining: 22M        │
    └────────────────────────┘
                       │
                      ...
                       │
                       ▼
    MONTH 24           │
    ┌────────────────────────┐
    │ Dr 641 1M             │
    │ Cr 242  1M            │
    │ Remaining: 0M         │  ← Fully allocated
    │ Status: DONE          │
    └────────────────────────┘

    TOTAL EXPENSE: 24M over 24 months
    BC02 IMPACT: 641 increased by 1M/month
```

## 5.6 VAT Input/Output Flow Into Declaration

```
TK 133 (INPUT VAT)                      TK 3331 (OUTPUT VAT)
──────────────────                      ────────────────────

  Purchase invoices in period             Sales invoices in period
  ┌────────────────────────┐              ┌─────────────────────┐
  │ Recorded in GL as:    │              │ Recorded in GL as: │
  │ Dr 152/156 (net)     │              │ Dr 111/112/131     │
  │ Dr 1331 (VAT)        │              │ Cr 511 (net)       │
  │ Cr 111/112/331       │              │ Cr 3331 (VAT)      │
  └────────────────────────┘              └─────────────────────┘
            │                                       │
            ▼                                       ▼
  ┌────────────────┐                     ┌────────────────────┐
  │ VAT by rate:   │                     │ VAT by rate:       │
  │ 5%: 50M       │                     │ 0%: 0M             │
  │ 8%: 200M      │                     │ 8%: 300M           │
  │ 10%: 300M     │                     │ 10%: 500M          │
  │ Total: 550M   │                     │ Total: 800M        │
  └────────────────┘                     └────────────────────┘
            │                                       │
            └──────────────┬────────────────────────┘
                           ▼
                ┌──────────────────────┐
                │ 01/GTGT DECLARATION  │
                │                      │
                │ [23] Input 5%: 50M  │
                │ [24] Input 8%: 200M │
                │ [25] Input 10%: 300M│
                │ [25] Total input:   │
                │   550M              │
                │                      │
                │ [29] Output 8%: 300M│
                │ [32] Output 10%: 500M│
                │ [33] Total output:  │
                │   800M              │
                │                      │
                │ [40] Payable: 250M  │
                │   (800M - 550M)     │
                └──────────────────────┘
                           │
                           ▼
                ┌──────────────────────┐
                │ SUBMIT VIA HTKK     │
                │ Deadline: 20th of   │
                │ next month/quarter  │
                └──────────────────────┘
```

## 5.7 Opening Balance Carry-Forward Logic

```
  YEAR N                         YEAR N+1
  ──────                         ────────

  PERIOD 12 CLOSED                PERIOD 0 (OPENING)
  ┌────────────────────┐          ┌────────────────────┐
  │ Account  │ Balance│          │ Account  │ Balance│
  │ ────────┼────────│          │ ────────┼────────│
  │ 1111    │ 100M   │───copy──►│ 1111    │ 100M   │
  │ 1121    │ 500M   │───copy──►│ 1121    │ 500M   │
  │ 131     │ 300M   │───copy──►│ 131     │ 300M   │
  │ 152     │ 200M   │───copy──►│ 152     │ 200M   │
  │ 211     │ 1000M  │───copy──►│ 211     │ 1000M  │
  │ 214     │ -300M  │───copy──►│ 214     │ -300M  │
  │ 331     │ 400M   │───copy──►│ 331     │ 400M   │
  │ 411     │ 1000M  │───copy──►│ 411     │ 1000M  │
  │ 421     │ 400M   │───copy──►│ 421     │ 400M   │
  │ ────────┼────────│          │ ────────┼────────│
  │ 511     │ 0      │  reset   │ 511     │ 0      │
  │ 632     │ 0      │  reset   │ 632     │ 0      │
  │ 641     │ 0      │  reset   │ 641     │ 0      │
  │ 642     │ 0      │  reset   │ 642     │ 0      │
  └────────────────────┘          └────────────────────┘

  SUB-LEDGER CARRY FORWARD:
  AR: outstanding invoices → AR opening
  AP: unpaid invoices → AP opening
  Inventory: qty × cost → inventory opening
```

## 5.8 Adjustment Entry Flow Into Reports

```
  ORIGINAL ENTRY          ADJUSTMENT              REPORT IMPACT
  (Period N)              (Period N or N+1)        (BC01/02)
  ─────────────           ──────────────           ────────────

  Dr 632 100M              Dr 152 20M (reverse)    BC02: COGS -20M
  Cr 152 100M              Cr 632 20M              BC01: Inventory +20M
                                                    Profit before tax +20M

  Dr 641 24M               Dr 242 23M (reclass)    BC02: Expense -23M
  Cr 111 24M               Cr 641 23M              BC01: Prepaid +23M
                            Schedule allocation:   Future: expense 1M/mo
                            Dr 641 1M Cr 242 1M

  Dr 642 50M (to B)        Dr 331-A 50M (reclass)  BC01: AP A +50M, AP B -50M
  Cr 112 50M               Cr 331-B 50M             Total AP unchanged
                            Dr 331-B 50M
                            Cr 112 50M

  REPORTING RULES:
  - Each adjustment must show original + correction
  - GL: both entries appear, flagged `is_correction`
  - BC: adjusted totals incorporate all entries
  - Period: adjustment entries shown in correction period
  - Comparative: restated if prior period adjustment
```

---

# 6. Validation & Control Rules

## 6.1 Missing Transactions Detection

```
RULE: Every transaction in source documents must be in GL.

DETECTION:
  Bank reconciliation:
    Bank transactions     → check in GL 112
    Unmatched bank items  → missing GL entries

  E-invoice vs GL:
    HDDT data from GDT   → compare with GL 133/3331
    Invoices in HDDT but not in GL → missing entries

  Fixed asset schedule:
    Expected monthly depreciation = total FA × rate
    Compare with actual GL 214 monthly credit
    If gap: missing depreciation entries

  CCDC allocation:
    Expected monthly allocation = TK 242 balance / months
    Compare with actual GL 242 monthly credit
    If gap: missing allocation entries

  AP/AR:
    Supplier/customer invoices (source) ≠ GL 331/131
    → missing entries in sub-ledger or GL

TOLERANCE: Zero tolerance for unreconciled items > 0 VND
```

## 6.2 Duplicate Entries Detection

```
RULE: No two transactions with same reference + date + amount.

DETECTION:
  By voucher number:
    SELECT reference, COUNT(*)
    FROM transactions
    WHERE period = ?
    GROUP BY reference
    HAVING COUNT(*) > 1

  By amount + date + account:
    SELECT t.date, le.account_code, le.amount, COUNT(*)
    FROM transactions t
    JOIN ledger_entries le ON t.id = le.transaction_id
    WHERE t.period = ?
    GROUP BY t.date, le.account_code, le.amount
    HAVING COUNT(*) > 1

  By e-invoice number:
    e-invoice number should be unique per entry
    Check: same invoice number posted twice

ACTION:
  First occurrence: keep
  Second occurrence: flag, require confirmation
  Third occurrence: block, require investigation

TOLERANCE: Zero tolerance for exact duplicates
```

## 6.3 Imbalance Detection

```
RULE: Every journal entry must have total Dr = total Cr.

DETECTION:
  Per transaction (REQUIRED at postEntry):
    SUM(Dr) == SUM(Cr)
    Tolerance: ±10 VND (rounding)

  Trial balance (period-end):
    SELECT account_id,
           SUM(CASE WHEN is_debit = 1 THEN amount ELSE 0 END) -
           SUM(CASE WHEN is_debit = 0 THEN amount ELSE 0 END)
           AS balance
    FROM ledger_entries le
    JOIN transactions t ON le.transaction_id = t.id
    WHERE t.period = ?
    GROUP BY account_id
    HAVING ABS(balance) > 0

  Cross-check:
    SELECT SUM(CASE WHEN is_debit = 1 THEN amount ELSE 0 END) AS total_dr,
           SUM(CASE WHEN is_debit = 0 THEN amount ELSE 0 END) AS total_cr
    FROM ledger_entries le
    JOIN transactions t ON le.transaction_id = t.id
    WHERE t.period = ?
    → ASSERT total_dr = total_cr

ACTION:
  If Dr ≠ Cr at post time: block entry, show difference
  If trial balance total Dr ≠ Cr: flag, run investigation
  Find last balanced state, trace differences

TOLERANCE: ±10 VND per entry (mathematical rounding)
```

## 6.4 VAT Mismatch Detection

```
RULE: VAT amounts in GL must match e-invoice data from GDT.

DETECTION:
  Input VAT:
    GL TK 1331 total     VS   HDDT input VAT data
    GL TK 1332 total     VS   HDDT FA input VAT
    Per tax rate: 5%, 8%, 10% should match

  Output VAT:
    GL TK 3331 total     VS   HDDT output VAT data
    Per tax rate: 0%, 5%, 8%, 10% should match
    Per invoice: each sale invoice → HDDT record

  Threshold:
    Individual: > 500K difference → flag
    Total: > 1% difference → block
    HDDT count vs GL count: missing invoices → list

ACTIONS:
  Input VAT mismatch:
    Check: invoice recorded in GL but not in HDDT?
      → Post invoice to HDDT, adjust declaration
    Check: invoice in HDDT but not in GL?
      → Missing GL entry, post correction

  Output VAT mismatch:
    Check: sale in GL but HDDT not issued?
      → Possible: cash sales without invoice (flag for penalty)
    Check: HDDT issued but not in GL?
      → Missing GL entry, post correction

TOLERANCE: 0.5% of total VAT, max 10M
```

## 6.5 Subsidiary Ledger Mismatch Detection

```
RULE: Sub-ledger balances must equal GL control account balances.

DETECTION:
  AR (TK 131):
    SELECT COALESCE(SUM(balance), 0) FROM ar_invoices
      WHERE status IN ('outstanding', 'overdue')
    VS
    SELECT balance FROM accounts WHERE code = '131'

    If diff ≠ 0 → flag with:
    - Total difference
    - List of transactions in GL not in sub-ledger
    - List of invoices in sub-ledger not in GL

  AP (TK 331):
    Same logic as AR but for ap_invoices

  Bank (TK 112):
    SELECT COALESCE(SUM(balance), 0) FROM bank_accounts
    VS
    SELECT balance FROM accounts WHERE code LIKE '112%'

  Inventory (TK 152/153/155/156):
    SELECT SUM(stock_qty * unit_cost) FROM items
    WHERE account_code IN ('152','153','155','156')
    VS
    SELECT balance FROM accounts WHERE code IN ('152','153','155','156')

ACTIONS:
  Difference > 0:
    - Identify transactions that posted to GL without sub-ledger
    - Create missing sub-ledger entries
    - If impossible to match: manual adjustment entry

TOLERANCE: 0 VND — must match exactly at period-end
```

## 6.6 Bank Mismatch Detection

```
RULE: GL 112 balance must reconcile to bank statement balance.

DETECTION:
  Per bank account:
    GL balance (adjusted for unrecorded items)
    ± Outstanding cheques (in GL, not in bank)
    ± Deposits in transit (in GL, not in bank)
    ± Bank charges/interest (in bank, not in GL)
    ± Other items
    = Adjusted GL balance

    VS
    Bank statement ending balance

    If ≠ → investigate

  Timing differences:
    Cheques: 1-3 days to clear
    Deposits: 1-2 days to credit
    Bank charges: monthly, often last day
    Interest: monthly, often last day

ACTIONS:
  Find unmatched items:
    - Categorized as: timing / missing entry / error
    - Missing entry: post adjusting entry
    - Error: correction entry
    - Timing: carry forward to next period

TOLERANCE: 0 VND at completion
```

## 6.7 Invalid Opening Balance Detection

```
RULE: Opening balances must be mathematically correct and internally consistent.

DETECTION:
  Structural:
    Dr accounts total = Cr accounts total (trial balance)
    Otherwise: opening balance entry is invalid

  Sub-ledger matching:
    AR sub-ledger total = GL 131 opening balance
    AP sub-ledger total = GL 331 opening balance
    Inventory value = GL 152/153/155/156 opening balance

  Cross-period:
    Ending balance period N = opening balance period N+1
    For all accounts (except revenue/expense which reset)

  Zero-check:
    Revenue/expense accounts should have 0 opening balance
    Asset/liability/equity should have 0 or non-zero (flagged if 0)

  Prior year comparison:
    If previous year data exists:
      Opening balance period 1 = closing balance period 12 prior year

ACTIONS:
  Mismatch found:
    - Block period opening
    - Show exact difference per account
    - Force correction before proceeding

TOLERANCE: 0 VND
```

## 6.8 Wrong CCDC/TK242 Allocation Detection

```
RULE: CCDC allocation must match the defined schedule.

DETECTION:
  Per CCDC item:
    Expected monthly = Cost / Allocation months
    Actual GL entry for period:
      SELECT amount FROM ledger_entries
      WHERE account_code = '242' AND is_debit = 0
      AND transaction_id IN (correction transactions for this CCDC)

  Total allocated to date:
    Cost - (remaining balance in TK 242 for this item)
    Should equal Monthly amount × Months elapsed

  Allocation period check:
    Months elapsed from start_date to current_month
    Should not exceed total allocation months
    If elapsed > total: CCDC should be fully allocated

  Orphan check:
    TK 242 credit entries without matching CCDC record
    → flag as unallocated

ACTIONS:
  Missing allocation: auto-generate catch-up entry
  Over-allocation: reverse excess
  Period mismatch: adjust start date or total months

TOLERANCE: 0 VND
```

## 6.9 Closed-Period Edits Prevention

```
RULE: No inserts, updates, or deletes on transactions in closed periods.

DETECTION:
  Every POST/PUT/DELETE on transaction table:
    CHECK: PeriodService::isPeriodOpen(transaction_date)
    If NOT open:
      - Block operation
      - Return error: "Kỳ kế toán đã đóng"
      - Log: "Attempted edit on closed period" → audit

  Direct DB attempts:
    LoggingPDO captures all SQL
    Pattern: WHERE id = ?, UPDATE transactions SET ...
    Check if transaction's period is closed
    If closed → log security alert, rollback

  Only exception:
    Period re-open (requires Kế toán trưởng + dual auth)
    Full audit trail of re-open event

TOLERANCE: Zero tolerance — absolute block
```

## 6.10 Audit Trail Preservation

```
RULE: No audit trail records can be modified or deleted.

DETECTION:
  Audit table design:
    - INSERT only (no UPDATE/DELETE grants to app user)
    - All columns: timestamp, action, resource, resource_id
      old_value, new_value, actor_id, ip_address

  ActionJournal design:
    - JSON Lines file (append-only)
    - Daily files: logs/actions/YYYY-MM-DD.jsonl
    - No edit capability (file system: chmod 644, owned by app)

  Integrity checks:
    Daily cron: check missing sequences in audit log
    Weekly: checksum audit log files (SHA256)
    Monthly: verify audit log count = transaction count

  Recovery:
    If audit log missing → incident report
    Restore from backup
    Flag period for deep audit

TOLERANCE: Zero tolerance — any gap in audit trail triggers audit flag
```

---

# 7. User Journey

## 7.1 Accountant Daily Workflow

```
TIME    ACTIVITY
────    ────────
08:00   Check email: new invoices from suppliers, payment confirmations
08:15   Log in to system → check pending items (unmatched bank, unposted documents)
08:30   Process invoices:
        - Verify invoice matches PO/goods receipt
        - Encode invoice in system (Dr 152/156 + Dr 1331 / Cr 331)
        - Attach scanned invoice to transaction
09:30   Process cash payments:
        - Review payment requests from departments
        - Verify supporting documents
        - Create Phiếu chi (Dr 331/642/641 / Cr 1111)
10:30   Bank reconciliation (if statement received):
        - Download CSV from internet banking
        - Import to system
        - Review auto-matched items
        - Handle unmatched items
11:30   Answer phone: supplier asking about unpaid invoice
        → Check AP aging → "Will pay next week"
12:00   Lunch
13:30   AR collection follow-up:
        - Run AR aging report
        - Identify overdue customers
        - Send payment reminders (phone/email)
14:30   Prepare VAT data for quarter:
        - Run VAT summary
        - Check for mismatches vs HDDT
        - Resolve discrepancies
15:30   Month-end tasks (if close to deadline):
        - Run CCDC allocation
        - Check FA depreciation posted
        - Verify all bank statements reconciled
16:30   Backup data, log out
```

## 7.2 Chief Accountant Review Flow

```
FREQUENCY   ACTIVITY
─────────   ────────
DAILY       10:00 Quick check:
            - Cash balance (111 + 112) — any unusual movements?
            - AR overdue > 90 days — any risk accounts?
            - Bank reconciliation status — any old sessions?

WEEKLY      15:00 Friday review:
            - Trial balance for current period
            - AP aging: any supplier approaching payment terms?
            - AR aging: any customer exceeding credit limit?
            - Inventory turnover: slow-moving items?
            - New fixed assets: correctly classified?

MONTHLY     After close:
            - Review BC01/02 variances vs budget
            - Compare actual vs prior month
            - Sign off on FS
            - Review audit trail: any period re-opens? corrections?
            - Review VAT declaration before submission

QUARTERLY   After period-end:
            - Review VAT return before submission
            - Review provisional CIT calculation
            - Check: quarterly CIT ≥ 80% of annual estimate?
              (Penalty if quarterly payments < 80% of final CIT)
            - Sign off on quarterly reports

YEARLY      Before FS submission:
            - Review full year FS (BC01/02/03/09)
            - Review CIT adjustments (non-deductible expenses)
            - Review TNCN finalization
            - Approve all adjusting entries
            - Sign BC01/02/03/09
            - Coordinate with external auditor if applicable

AUDIT       During audit:
            - Provide GL, subsidiary ledgers, aging reports
            - Explain significant adjustments
            - Review auditor findings
            - Approve audit adjustment entries
            - Sign management representation letter
```

## 7.3 Finance Controller Approval Flow

```
APPROVAL TYPE          THRESHOLD          APPROVER
─────────────          ──────────         ────────
Payment to supplier    < 10M              Kế toán thanh toán
                       10M - 100M         Kế toán trưởng
                       > 100M            Giám đốc tài chính

Cash advance           < 5M               Trưởng bộ phận
                       5M - 50M           Kế toán trưởng
                       > 50M              Giám đốc

Credit note            < 20M              Kế toán trưởng
                       > 20M              Giám đốc

Purchase order         < 30M              Trưởng bộ phận
                       30M - 200M         Kế toán trưởng
                       > 200M             Giám đốc

Contract               < 100M             Kế toán trưởng
                       100M - 500M        Giám đốc
                       > 500M             Hội đồng quản trị

Period re-open         Any                Kế toán trưởng + Giám đốc (dual)

Correction entry       < 10M              Tự động
                       10M - 50M          Kế toán trưởng
                       > 50M              Giám đốc + Kế toán trưởng
```

## 7.4 Bank Reconciliation Journey

```
KẾ TOÁN TIỀN GỬI NGÂN HÀNG

MONTHLY ROUTINE:
  Day 1-3: Download bank statement for previous month
  Day 2-4: Import to system, run auto-match
  Day 2-5: Review unmatched items
  Day 3-6: Contact departments for missing payment info
  Day 4-7: Post adjusting entries (bank charges, interest)
  Day 5-8: Balance check, complete reconciliation
  Day 5-10: File reconciliation report

PAIN POINTS:
  - Bank statement arrives late (3-5 days after month-end)
  - CSV format changes without notice (bank updates system)
  - Missing reference info on bank transactions
  - Multiple currencies: FX rates at transaction date vs period-end
  - Inter-bank transfers: both sides need matching

SYSTEM FEATURES THAT HELP:
  - Remember previous matches (learn matching patterns)
  - Auto-suggest match candidates for remaining items
  - Batch post adjusting entries
  - Carry forward unmatched items automatically
  - Reconciliation dashboard: how many periods completed
```

## 7.5 VAT Declaration Journey

```
KẾ TOÁN THUẾ

QUARTERLY ROUTINE:
  Month-end + 15 days:
    Day 1: Run VAT summary from system
    Day 1-2: Compare system data with HDDT (e-invoice)
    Day 2-3: Identify and resolve discrepancies
    Day 3-4: Generate 01/GTGT data
    Day 4-5: Export XML, upload to HTKK
    Day 5: Submit to GDT
    Day 5-7: Pay tax (if payable)
    Day 7: File payment receipt

PAIN POINTS:
  - Missing input invoices (supplier sent late)
  - Wrong tax rate on invoice
  - Non-deductible input VAT (personal expenses, no proper invoice)
  - Export invoices at 0% need supporting documents
  - VAT refund requests (complex process)

SYSTEM FEATURES THAT HELP:
  - Auto-categorize VAT by rate
  - HDDT data import from GDT API
  - Match GL vs HDDT automatically
  - Flag non-deductible VAT
  - Generate XML for HTKK import
  - Track submission status + payment due date
```

## 7.6 Year-End Closing Journey

```
KẾ TOÁN TỔNG HỢP — NOVEMBER TO MARCH

NOVEMBER:
  - Review fixed asset list → identify fully depreciated assets
  - Review CCDC → ensure all allocations up to date
  - Start collecting supplier statements for AP confirmation

DECEMBER:
  - Final month entries (all December transactions)
  - Physical inventory count (Dec 31 or nearest working day)
  - Record inventory adjustments
  - FC revaluation at Dec 31 rate
  - Bad debt provision calculation (5 aging buckets)
  - Inventory impairment assessment (NRV < cost)
  - Accruals: ensure all expenses recorded
  - Prepayments: ensure all allocations up to date

JANUARY:
  - Jan 1-5: Complete December closing entries
  - Jan 5-10: Run pre-close checklist
  - Jan 10-15: Generate BC01/02
  - Jan 15-20: Generate BC03 (cash flow)
  - Jan 20-31: Generate BC09 (notes to FS)
  - Jan 20-31: Draft CIT finalization

FEBRUARY:
  - Feb 1-15: Complete BC09 (narrative sections)
  - Feb 1-20: Finalize CIT computation
  - Feb 1-28: Prepare TNCN finalization
  - Feb 28: Submit BC01/02/03/09 to tax authority
  - Feb 28: Submit CIT finalization (03/TNDN)
  - Feb 28: Submit TNCN finalization (05/TNCN)

MARCH (if audited):
  - Prepare audit files
  - Respond to auditor queries
  - Book audit adjustment entries
  - Finalize audited FS

STRESS POINTS:
  - Physical count discrepancies → adjusting entries in high volume
  - Missing invoices from December suppliers (arrive in January)
  - CIT adjustments: non-deductible items calculation
  - BC09 narratives: always done at the last minute
  - Audit: always finds something → correction entries
  - Deadline pressure: March 31 is hard deadline
```

## 7.7 Audit Preparation Journey

```
KẾ TOÁN TRƯỞNG — AUDIT SEASON

PREPARATION (T-30 DAYS):
  - Confirm audit scope and timeline with auditor
  - Assign team members for each audit area
  - Prepare information request list from auditor
  - Clean up: pending items, uncleared reconciliation

DATA PREPARATION (T-14 DAYS):
  Export from system:
    - Trial balance by month (12 months)
    - GL detail (all transactions by account)
    - Subsidiary ledgers (AR/AP by customer/supplier)
    - Bank reconciliation (all 12 months)
    - Fixed asset movement schedule
    - Inventory listing with valuation
    - AP aging, AR aging (as of Dec 31)
    - Revenue breakdown by product/customer
    - Expense breakdown by nature
    - Related party transactions
    - Tax returns (01/GTGT, 03/TNDN, 05/TNCN)
    - FS (BC01/02/03/09)
    - Audit trail (corrections, period events)

AUDIT WEEK:
  - Provide auditor workspace
  - Answer queries (30-50 per audit)
  - Provide supporting documents (invoices, contracts)
  - Review audit adjustment proposals
  - Book approved adjustments
  - Respond to management letter

POST-AUDIT:
  - Book final audit adjustments
  - Generate audited FS
  - File audited FS with tax authority (if required)
  - Implement audit recommendations
```

## 7.8 Correction Request Journey

```
KẾ TOÁN — ERROR DETECTED

STEP 1: DETECTION
  - Accountant finds error during reconciliation
  - Or: bank rec shows mismatch
  - Or: supplier/customer reports wrong balance
  - Or: audit finds misclassification

STEP 2: DECISION
  - Is it a genuine error? → proceed
  - Is it a timing difference? → wait for next period
  - Is it a system error? → report to IT

STEP 3: CORRECTION TYPE
  - Amount too low → supplementary entry (Method 1)
  - Wrong account → adjusting/red ink (Method 2 or 3)
  - Wrong period → correction in current period with explanation

STEP 4: APPROVAL
  - Below threshold → auto-approve
  - Above threshold → Kế toán trưởng review + approve
  - Significant → Giám đốc approval

STEP 5: EXECUTION
  - Create correction entry in system
  - Reference original transaction
  - Attach supporting document
  - Post

STEP 6: VERIFICATION
  - Check: GL balance now correct?
  - Check: sub-ledger matches GL?
  - Check: audit trail complete?

STEP 7: COMMUNICATION
  - If correction affects customer/supplier balance → notify
  - If correction affects tax → adjust next declaration
```

## 7.9 Management Reporting Journey (BC09 Export PDF/Excel)

```
KẾ TOÁN TRƯỞNG — MONTHLY REPORT TO CEO

STEP 1: GENERATE FS
  - Run BC01/02/03 for the period
  - Review: any unusual variances?
  - If variance > 20% → investigate, add explanation

STEP 2: GENERATE BC09
  - Auto-populate sections from FS data
  - Fill narrative sections (Section IV: explanations)
  - Write management commentary (Section I: business overview)

STEP 3: PDF EXPORT
  - Select: Include all sections?
  - Select: Comparative period (prior month/prior year)
  - Generate PDF with company branding
  - Check: PDF renders correctly (Vietnamese fonts)

STEP 4: REVIEW
  - Kế toán tổng hợp reviews
  - Kế toán trưởng approves
  - Sign: Người lập, Kế toán trưởng

STEP 5: DISTRIBUTE
  - Email to CEO/Giám đốc
  - Save to shared drive
  - File in document management system (10-year retention)
```

---

# 8. SME Pain Analysis

## 8.1 Excel-Based Closing Chaos

| Pain | Impact | Frequency |
|---|---|---|
| Trial balance formula errors | Incorrect FS, hours to trace | Monthly |
| Version control: "BC02_FINAL_v3_REAL.xlsx" | Confusion, wrong data used | Monthly |
| Manual consolidation | Missed inter-company elimination | Quarterly |
| Rounding errors | BC01 doesn't balance to the đồng | Monthly |
| No audit trail | Auditor rejects Excel | Yearly |
| Broken links between sheets | Wrong totals | Monthly |
| Accidental overwrite | Data loss | Quarterly |
| File corruption | Complete re-entry | Yearly |

**Symptoms:**
- Kế toán tổng hợp works 2 extra days every month-end
- Kế toán trưởng spends 1 day verifying Excel formulas
- Every audit starts with "Please provide system reports, not Excel"

## 8.2 Late Bank Statement Import

| Pain | Impact |
|---|---|
| Bank statement arrives day 5 of next month | Cannot close on time |
| Different bank → different format → manual re-entry | 4-8 hours/month |
| Missing transactions not caught until next month | Late correction |
| Bank charges unrecorded | Expense understated |
| Outstanding items pile up | Reconciliation becomes harder |

**SME reality:**
- 80% of SMEs have accounts at 2+ banks
- Multi-currency accounts: matching by FX rate is painful
- Bank portals change login requirements quarterly
- CSV formats change without notice

## 8.3 VAT Mismatch vs Ledger

| Pain | Root Cause |
|---|---|
| Input VAT in GL ≠ input VAT in HDDT | Invoice posted to GL but not declared |
| Output VAT in GL ≠ output VAT in HDDT | HDDT issued but GL entry missing |
| Wrong tax rate in GL (10% vs 8%) | Incorrect categorization |
| Non-deductible VAT expensed | Accountant missed flag |
| HDDT data not downloaded | No API connection to GDT |

**Consequence:**
- VAT return wrong → risk of tax audit
- Late correction → penalty + late payment interest
- Input VAT not claimed → cash flow loss

## 8.4 Wrong CCDC Allocation

| Pain | Impact |
|---|---|
| CCDC expensed immediately (no TK 242) | Expense overstated 1 month, understated rest of year |
| Wrong allocation period | Expense pattern wrong |
| No allocation at all | TK 242 balance never decreased |
| Wrong expense account (641 vs 642) | BC02 line items wrong |
| Inconsistent method | Some items expensed, some allocated |

**Consequence:**
- BC02 wrong by month, correct by year
- Quarterly CIT wrong (provisional payments)
- Management confused about expense pattern

## 8.5 Manual Correction Risk

| Pain | Impact |
|---|---|
| DELETE FROM transactions (direct SQL) | Audit trail broken, no recovery |
| UPDATE amount (direct SQL) | No before/after, no reason |
| Delete and re-enter | No trace of correction, original lost |
| Correction without reference to original | Cannot trace history |
| Wrong correction method (supplementary vs red ink) | Balances wrong |

**SME habit:** Kế toán opens DB, types DELETE, re-enters. 15 minutes. No one knows. Until audit.

## 8.6 Opening Balance Errors

| Pain | Impact |
|---|---|
| GL balance input wrong | All FS wrong for the year |
| Sub-ledger ≠ GL at migration | Never matches, always needs reconciliation |
| AR aging from old system not migrated | Cannot collect old debts |
| Inventory qty × cost not loaded | Inventory valuation wrong |
| FA accumulated depreciation missing | NBV wrong → depreciation wrong |

**Consequence:**
- Wrong opening = wrong closing = wrong year
- Only detected at year-end closing or audit
- Too late to fix easily → prior period adjustment

## 8.7 Subsidiary Ledger Mismatch

| Pain | Impact |
|---|---|
| Customer balance in AR aging ≠ GL 131 | Cannot send accurate statement |
| Supplier balance in AP aging ≠ GL 331 | Pay wrong amount |
| Inter-company account mismatch | Consolidation impossible |
| Total sub-ledger ≠ GL at month-end | Close delayed |

**Root causes:**
- Transaction posted to GL without sub-ledger entry
- Sub-ledger entry without GL posting
- Wrong customer/supplier selected
- Journal entry directly to TK 131/331 (bypass sub-ledger)

## 8.8 Missing Audit Trail

| Pain | Impact |
|---|---|
| Who changed this? | Unknown |
| What was the original value? | Unknown |
| When was it changed? | Unknown |
| Why was it changed? | Unknown |

**Consequence:**
- Auditor cannot trace transactions → qualification
- Tax inspector cannot verify → penalty
- Management cannot trust numbers
- Fraud cannot be investigated

**Root cause:** No system-level audit logging. Only manual tracking.

## 8.9 Re-Open Period Risk

| Pain | Impact |
|---|---|
| Re-open without authorization | Uncontrolled changes to FS |
| Re-open multiple times | Loss of period integrity |
| Re-open after submission | Filed FS ≠ system FS |
| No record of re-open | Auditor cannot verify |

**Consequence:**
- Если period re-opened after FS submission → restatement
- If detected by auditor → red flag, deeper audit
- If detected by tax → penalty for unreliable books

## 8.10 Audit Stress

| Pain | Impact |
|---|---|
| 3 months of preparation | Kế toán burned out |
| 30-50 auditor queries | Interrupts daily work |
| Missing documents found at last minute | Stress, overtime |
| Adjustment entries in March | Changes to closed year |
| Management letter findings | Reputation risk |

**SME reality:**
- No dedicated audit team
- Kế toán tổng hợp does audit prep alongside daily work
- Auditor requests always come at busy season
- First audit is the hardest (everything wrong)

---

# 9. Final Deliverables

## 9.1 Deep Accounting Analysis Summary

```
SYSTEM MATURITY ASSESSMENT:
─────────────────────────
✅ Core engine (JournalService, Dr=Cr enforcement)
✅ Cash & Bank (receipt, payment, bank, reconciliation, petty cash)
✅ Inventory (receipt, issue, transfer, consignment, physical count, impairment, periodic)
✅ AP/AR (invoice, payment, return, discount, aging, write-off, provision)
✅ BC01/02/03 (Balance Sheet, Income Statement, Cash Flow)
✅ Period Engine (open, close, closing entries)
✅ GL (Sổ Cái with running balance)
✅ Payroll (full engine: insurance, PIT, posting)
✅ Fixed Assets (depreciation, lifecycle)
✅ Tax Rate management
✅ RBAC + Auth + Audit Log
✅ Master Data (16 tables)
✅ Trial Balance, Journal Book, Posting Rules

❌ BC09 Thuyết minh BCTC — NEEDED (completes FS package)
❌ PDF/Excel Export — NEEDED (cannot submit tax, cannot sign)
❌ Subsidiary Ledger UI — NEEDED (audit always asks)
❌ Correction Engine (Article 27) — NEEDED (legal requirement)
❌ VAT Declaration Prep — NEEDED (#1 reason SMEs buy software)
❌ CIT Finalization — NEEDED (annual requirement)
❌ Opening Balances Module — NEEDED (cannot go live without)
❌ Pre-Close Checklist Enforcement — NEEDED (close blind = FS errors)
❌ CCDC/TK242 Auto-Allocation — NEEDED (monthly recurring task)
❌ Bank Statement Import (CSV/MT940) — NEEDED (saves hours/month)

ROADMAP (Chief Accountant priority):
  SHIP NOW:   BC09 + PDF Export + Subsidiary Ledger
  SHIP NEXT:  Correction Engine + VAT Prep + Opening Balances
  SHIP SOON:  CIT Finalization + Pre-Close Checklist + CCDC Allocation
  SHIP LATER: Bank Statement Import
```

## 9.2 Closing Workflows Summary

```
DAILY:
  Invoice capture → AP/AR posting → Cash recording → Bank matching

MONTH-END:
  Pre-close checks → Closing entries → Period lock → FS generation

QUARTER-END:
  Month-end + VAT declaration + Provisional CIT payment

YEAR-END:
  Quarter-end + Physical count + FC revaluation + Provisioning
  + BC09 + CIT finalization + TNCN finalization + Audit prep

KEY CONTROLS:
  Period lock = read-only after close
  Sub-ledger = GL every month
  Trial balance Dr = Cr every post
  Bank reconciliation every month
  VAT declaration every quarter
  CIT finalization every year
```

## 9.3 End-to-End Process Flows

*(Detailed in Sections 4 and 5 above)*

## 9.4 Validation Matrix

| Check | When | Action |
|---|---|---|
| Dr = Cr | Every entry | Block if not |
| Period open | Every entry | Block if closed |
| Control account | Every entry | Block unless allowed |
| Posting rule | Every entry | Block/warn as configured |
| Unique voucher | Every entry | Block duplicate |
| Sub-ledger = GL | Month-end | Flag mismatch |
| Bank recon complete | Close | Block if not |
| Inventory count | Year-end | Block if not |
| FC revaluation | Period-end | Warn if not |
| Trial balance | Month-end | Block if Dr ≠ Cr |
| BC01: Assets = Liab+Equity | FS generation | Warn if not |
| Pre-close checklist | Close | Block if any check fails |

## 9.5 Control Matrix

| Control | Function | Frequency |
|---|---|---|
| Period lock | Prevent edits to closed periods | Continuous |
| Audit trail | Track all changes | Continuous |
| Voucher numbering | Prevent duplicate documents | Every transaction |
| CSRF token | Prevent cross-site attacks | Every API call |
| RBAC permission | Control access by role | Every API call |
| Session timeout | Auto-logout idle users | 8 hours |
| Input validation | Prevent invalid data | Every entry |
| Soft delete | Prevent data loss | Every delete |
| LoggingPDO | Capture all SQL queries | Every query |
| ActionJournal | Capture all user actions | Every request |

## 9.6 Real SME Accounting Examples

*(Embedded in Section 2 scenarios and Section 3 use cases)*

## 9.7 BC09 Report Logic Explanation

*(Detailed in Section 1.2 above — 9 sections, auto-populate from BC01/02/03, cross-referenced mã số)*

## 9.8 VAT/CIT Reconciliation Logic

*(Detailed in Section 1.3 (VAT) and Section 1.4 (CIT) above)*

## 9.9 Audit Risk Analysis

| Risk Area | Risk Level | Mitigation |
|---|---|---|
| Opening balance errors | CRITICAL | Sub-ledger check, period lock after setup |
| Subsidiary ledger mismatch | HIGH | Automated month-end reconciliation |
| Missing bank transactions | HIGH | Monthly bank reconciliation with GL |
| Period re-open without trail | HIGH | Re-open requires dual auth + full audit log |
| Direct DB edits | HIGH | App DB user has no UPDATE/DELETE on audit tables |
| VAT discrepancy vs HDDT | HIGH | Auto-compare GL vs e-invoice data |
| Correction without audit trail | HIGH | Correction engine only (no direct edits) |
| Inventory valuation error | MEDIUM | FIFO cost layer tracking |
| CCDC allocation missing | MEDIUM | Auto-scheduled allocation |
| FC revaluation missed | MEDIUM | Period-end check |
| Depreciation not posted | MEDIUM | Monthly auto-run |
| Accruals not recorded | MEDIUM | Pre-close checklist |

---

*End of Accounting Engine Brain Logic Document*
*Chief Accountant — 20,000+ hours*
*Vietnam SME Accounting — Real, Not Theory*
