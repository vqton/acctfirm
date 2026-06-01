# Fixed Asset + CCDC Brain Logic — Chief Accountant Analysis

> **Author:** Chief Accountant — 20,000+ hours  
> **Sources:** Kế Toán Thiên Ưng, Kế Toán Lê Ánh, Webketoan, Circular 99/2025/TT-BTC,  
>   Thông tư 45/2013/TT-BTC, Thông tư 133/2016/TT-BTC, Thông tư 96/2015/TT-BTC,  
>   Nghị định 320/2025/NĐ-CP, VAS, existing FA_ACCOUNTING_USE_CASES.md  
> **Date:** 2026-05-29  
> **Domain:** Fixed Assets (TK 211, 213, 214) + CCDC (TK 153, 242)

---

## Table of Contents

1. [Fixed Asset + CCDC Brain Logic](#1-fixed-asset--ccdc-brain-logic)
2. [Real SME Scenarios](#2-real-sme-scenarios)
3. [Use Cases](#3-use-cases)
4. [Lifecycle Rules](#4-lifecycle-rules-core-accounting-engine-logic)
5. [Process & Workflow Logic](#5-process--workflow-logic)
6. [Data Flow](#6-data-flow-business-view-only)
7. [Validation & Control Rules](#7-validation--control-rules)
8. [User Journey](#8-user-journey-sme-reality)
9. [SME Pain Analysis](#9-sme-pain-analysis)
10. [Final Deliverables](#10-final-deliverables)
11. [Reporting & Tax Compliance](#11-reporting--tax-compliance)
12. [Integration Contracts](#12-integration-contracts--module-boundaries)
13. [Implementation Roadmap](#13-implementation-roadmap--acceptance-criteria)

---

# 1. Fixed Asset + CCDC Brain Logic

## 1.1 Why Fixed Asset System Exist

Every SME in Vietnam owns fixed assets. The tax inspector's first question: "show me your fixed asset register and depreciation schedule." Without a proper system:

- **BC 01 (Balance Sheet) misstated.** TK 211 (gross value) and TK 214 (accumulated depreciation) are wrong → Total Assets (MS 280) wrong → Balance sheet doesn't balance.
- **BC 02 (Income Statement) misstated.** Depreciation expense (MS 24-26) is wrong → Profit before tax (MS 50) wrong → CIT wrong → tax penalty.
- **Audit trail broken.** No one can trace: what did we buy? When? How much depreciated? What's the net book value?
- **Tax inspection failure.** Circular 99 requires Mẫu 06-TSCĐ (Bảng tính và phân bổ khấu hao TSCĐ). Without it: administrative fine + forced re-determination of CIT.

The FA system exists to solve ONE problem: **track every VND of asset cost from capitalization through monthly expense recognition to disposal**, ensuring BC 01 and BC 02 are correct every month.

## 1.2 Why CCDC (TK 242) Exist

Under Circular 99, an item that costs < 30,000,000 VND or has useful life < 1 year is NOT a fixed asset — it's CCDC (Công cụ dụng cụ).

But SMEs buy many items between 1,000,000 and 29,000,000 VND:
- Laptops for staff
- Printers, scanners
- Office furniture
- Tools, molds, jigs
- Uniforms, protective equipment
- Bao bì luân chuyển (returnable packaging)

If all these went straight to expense in one month, the P&L would be volatile. TK 242 exists to **smooth the expense** over the period the item is actually used (max 3 years).

## 1.3 Difference Between Asset vs CCDC

| Criteria | Fixed Asset (TK 211/213) | CCDC (TK 153 → 242) |
|---|---|---|
| Cost threshold | ≥ 30,000,000 VND | < 30,000,000 VND |
| Useful life | > 1 year | Any (≤ 3 years for tax) |
| Account | TK 211 (gross), TK 214 (accum. dep.) | TK 153 (stock), TK 242 (prepaid) |
| Expense recognition | Monthly depreciation (systematic) | Monthly amortization (systematic) |
| Residual value | Yes (salvage value) | No (fully amortized) |
| Tax treatment | Depreciation is deductible expense | Amortization is deductible expense |
| Regulation | TT 45/2013/TT-BTC | TT 96/2015/TT-BTC (max 3 years) |

The critical operational question for every purchase: **"Is this ≥ 30M and will we use it > 1 year?"** If yes → fixed asset. If no → CCDC or direct expense.

## 1.4 Lifecycle of Fixed Asset

```
Acquisition → Capitalization → Put Into Use → Monthly Depreciation 
  → (Repair/Upgrade/Impairment) → Disposal/Liquidation → Derecognition
```

### Stage 1: Acquisition
- Purchase from supplier: Dr 211 (NG), Dr 1332 (VAT), Cr 111/112/331
- Self-constructed: Dr 241 (WIP), then transfer to 211 when completed
- Import: Dr 211 (incl. import duty), Dr 1332, Cr 112/3333
- Capital lease: Dr 211, Cr 341 (finance lease liability)

NG (Nguyên giá) = purchase price + transport + installation + testing + registration fees - trade discounts. All costs to get the asset ready for use.

### Stage 2: Capitalization Decision
The accountant must determine: does this meet FA criteria?
- Future economic benefit? ✅
- Useful life > 1 year? ✅
- Cost ≥ 30,000,000 VND and reliably determined? ✅

If all three → capitalize as FA. If any fails → CCDC or expense.

### Stage 3: Put Into Use
Formalized by Biên bản giao nhận TSCĐ (Mẫu 01-TSCĐ). This document:
- Confirms the asset is ready for use
- Records NG, useful life, depreciation method
- Assigns FA code/category
- Is the trigger for depreciation start
- Must be signed by: người giao, người nhận, kế toán trưởng, giám đốc

### Stage 4: Monthly Depreciation
Every month (except the month of acquisition after the 15th):
- System calculates monthly depreciation
- Posts: Dr 627/641/642/241, Cr 214 (accumulated depreciation)
- Updates NBV (Net Book Value) = NG - accumulated depreciation

### Stage 5: Repair/Upgrade
- **Repair (sửa chữa):** Maintains original operating condition. Expense immediately: Dr 627/641/642, Cr 111/112/331. Does NOT change NG.
- **Upgrade (nâng cấp):** Increases capacity, extends life, improves quality. Capitalized: Dr 211 (increase NG), Cr 111/112/331. Depreciation recalculated prospectively.

### Stage 6: Disposal/Liquidation
Formalized by Biên bản thanh lý TSCĐ (Mẫu 02-TSCĐ):
- Remove from register: Dr 214 (full accumulated depreciation), Dr 811 (loss)/Cr 711 (gain) for residual NBV, Cr 211 (original NG)
- Record proceeds: Dr 111/112, Cr 711
- Record costs: Dr 811, Cr 111/112
- VAT: Output VAT on proceeds if selling

## 1.5 Lifecycle of CCDC

```
Purchase → Stock (TK 153) → Issue → Prepaid (TK 242) → Monthly Amortization → Fully Expensed
```

### Stage 1: Purchase
- Dr 153 (CCDC stock), Dr 1331 (VAT), Cr 111/112/331

### Stage 2: Issue for Use
- Small value (< ~2,000,000): Dr directly to expense (627/641/642), Cr 153
- Large value: Dr 242 (prepaid), Cr 153

### Stage 3: Monthly Amortization
- Dr 627/641/642, Cr 242
- Amount = Total value / number of months (max 36 months)
- First month: pro-rated if mid-month issue

## 1.6 Why Monthly Depreciation/Amortization Is Required

The matching principle: **cost must follow revenue.** A machine produces goods for 10 years. Its cost is recognized over those 10 years, not all in year 1.

**Without monthly depreciation:**
- Year 1: P&L shows huge loss (full asset cost expensed)
- Years 2-10: P&L shows inflated profit (no cost)
- BC 01: Asset value goes to zero in month 1

**Vietnamese law (TT 45/2013, Art. 9):**
> "Doanh nghiệp phải trích khấu hao TSCĐ vào chi phí sản xuất, kinh doanh hàng tháng."

Monthly is not optional. It's the law.

## 1.7 How Cost Moves Into Expense

```
Fixed Asset:
  211 (NG) ──monthly──→ 214 (accum. dep.) ──expense──→ 627/641/642

CCDC:
  153 (stock) ──issue──→ 242 (prepaid) ──monthly──→ 627/641/642
```

The cost sits on the balance sheet (asset) and moves to the P&L (expense) gradually over the asset's useful life.

## 1.8 How BC 01 and BC 02 Are Affected Monthly

### BC 01 (Balance Sheet) — Monthly Impact

| Line Item | Mã số | Effect |
|---|---|---|
| TSCĐ hữu hình (NG) | 221 | Original cost — increases on acquisition, decreases on disposal |
| TSCĐ hữu hình (HM lũy kế) | 222 | Accumulated depreciation — increases monthly |
| TSCĐ hữu hình (GT còn lại) | 220 = 221-222 | NBV — decreases monthly by depreciation |
| Chi phí trả trước | 242 | CCDC balance — decreases monthly by amortization |
| **Total Assets** | **280** | Changes by net asset movements |

### BC 02 (Income Statement) — Monthly Impact

| Line Item | Mã số | Effect |
|---|---|---|
| Chi phí bán hàng | 25 | Depreciation of sales/selling assets + CCDC amortization |
| Chi phí QLDN | 26 | Depreciation of office assets + CCDC amortization |
| Chi phí SXC | 24 | Depreciation of production assets + CCDC amortization |
| **Lợi nhuận trước thuế** | **50** | Reduced by total depreciation + amortization |

**Real example:**
- Company buys machine for 1.2 billion VND, useful life 10 years
- Monthly depreciation: 10,000,000 VND
- Every month: BC 02 expense increases by 10M, BC 01 NBV decreases by 10M
- Over 10 years: 1.2 billion moves from BC 01 to BC 02 → matches revenue from production

## 1.9 Why Excel-Based Depreciation Is Risky

I've audited 50+ SMEs using Excel. The failure modes are consistent:

1. **Formula error in one cell.** Every subsequent month is wrong. Error compounds.
2. **Missing asset.** Accountant forgets to add new asset to Excel. NBV on BC 01 is wrong.
3. **Disposal not removed.** Asset sold 2 years ago still depreciating in Excel.
4. **Useful life changed.** Accountant changes life in Excel but doesn't update past months consistently.
5. **No audit trail.** Who changed what, when? No record. Auditor cannot verify.
6. **BC 01 vs ledger mismatch.** Excel says NBV = 500M, GL says 480M. Which is right? Nobody knows.
7. **Month-end stress.** Depreciation run takes 2-3 hours manually. Prone to error.
8. **No consolidation.** Multi-department allocation of depreciation requires complex Excel formulas that break.

**In 10 years, I have never seen an Excel-based FA register that was fully correct.** Never.

## 1.10 Audit Expectations in Vietnam

When the tax inspector or independent auditor reviews FA, they check:

1. **Full register:** Every asset listed with code, name, purchase date, NG, useful life, depreciation method, accumulated depreciation, NBV.
2. **Monthly depreciation schedule:** Mẫu 06-TSCĐ (Bảng tính và phân bổ khấu hao) printed and signed each month.
3. **Physical verification:** Assets exist. Count matches register.
4. **Disposal documentation:** Biên bản thanh lý (Mẫu 02-TSCĐ) for every disposed asset.
5. **Capitalization policy:** Written policy defining what's FA vs CCDC vs expense.
6. **BC 01 = FA register:** Total NBV on BC 01 must equal FA register total NBV. If not → audit adjustment.
7. **Depreciation method consistency:** Cannot change method frequently without justification.
8. **Useful life within legal framework:** Must be within TT 45 Appendix I ranges.

**Most common audit finding in Vietnamese SMEs:** BC 01 TSCĐ balance ≠ FA register. Every time.

---

# 2. Real SME Scenarios

## Fixed Asset Scenarios

### Scenario FA-01: Purchase Asset (Xe ô tô 1.6 tỷ)

A manufacturing SME buys a delivery truck for 1.6 billion VND + VAT 10%.

**Real issue:** Cars over 1.6 billion have a tax limitation — only 1.6 billion is deductible for depreciation. The excess (> 1.6B) is NOT deductible for CIT (Nghị định 320/2025). The system must track two NG values: accounting NG (full cost) and tax NG (limited to 1.6B).

**Journal:**
- Dr 211: 1,600,000,000 (NG)
- Dr 1332: 160,000,000 (VAT)
- Cr 112: 1,760,000,000

**Depreciation:** 10 years, straight-line. Monthly = 13,333,333 VND.
- Dr 641/642: 13,333,333
- Cr 214: 13,333,333

**Tax adjustment:** Of 13,333,333 monthly depreciation, only 13,333,333 is deductible (because NG ≤ 1.6B). If NG > 1.6B, the excess would be a permanent difference on CIT finalization.

### Scenario FA-02: Capitalization Decision

Company buys 50 laptops at 15,000,000 each = 750,000,000 total.

**Decision:**
- Per unit: 15M < 30M → CCDC, not FA
- But: if purchased as ONE batch for a specific project with > 1 year use, some accountants argue for FA treatment
- SAFEST: Treat as CCDC. Amortize over 24 months. Monthly expense = 31,250,000.
- If treated as FA: would need to register 50 individual FA records → administrative nightmare

**Rule of thumb for Chief Accountant:** If an item costs < 30M, treat as CCDC. Only capitalize as FA when clearly meeting the 30M threshold per unit.

### Scenario FA-03: Put Into Use

Asset purchased last month, stored in warehouse, not yet used.

**Key rule (TT 45 Art. 9):** Depreciation starts when the asset is READY FOR USE, not when purchased. If stored but ready → still depreciate. If not yet installed → record as construction in progress (TK 241).

**Trigger:** Biên bản giao nhận TSCĐ (Mẫu 01-TSCĐ) signed → system starts depreciation next month.

**If put into use on day 1-15 of month:** Full month depreciation.
**If put into use on day 16+:** Depreciation from next month.

### Scenario FA-04: Monthly Depreciation

System runs depreciation on the last day of every month.

**For each asset:**
- Monthly depreciation = (NG - salvage value) / useful life in months
- Straight-line method (most common in Vietnamese SMEs)

**Journal:**
- Dr 627 (production department): 50,000,000
- Dr 641 (sales department): 10,000,000
- Dr 642 (management): 15,000,000
- Dr 623 (construction): 5,000,000
- Cr 214: 80,000,000

**BC 01 impact:** TK 214 increases by 80M → NBV decreases by 80M
**BC 02 impact:** MS 24/25/26 increase by 80M → Profit before tax decreases by 80M

### Scenario FA-05: Asset Transfer Between Departments

Asset moves from production (TK 627) to management (TK 642).

**Impact:** Only the expense account changes. NG and accumulated depreciation unchanged.

**System action:** Update department_id on FA record. From next month: depreciation posts to TK 642 instead of TK 627.

**Documentation:** Internal transfer memo. No change in asset value.

### Scenario FA-06: Asset Repair vs Upgrade

**Case A — Repair:** Máy hỏng, sửa hết 15,000,000.

- This is maintenance. Expense immediately.
- Dr 627: 15,000,000
- Cr 111/112: 15,000,000
- NG unchanged. Depreciation unchanged.

**Case B — Upgrade:** Nâng cấp dây chuyền sản xuất, tăng công suất 30%. Chi phí 200,000,000.

- This is an upgrade (nâng cấp). Capitalized.
- Dr 211: 200,000,000 (increase NG)
- Cr 111/112: 200,000,000
- Recalculate depreciation prospectively over remaining useful life.

**The critical question:** Does this expenditure extend the asset's life or increase its capacity? Yes → capitalize. No → expense.

### Scenario FA-07: Asset Impairment

Machine NBV = 500M. Market value dropped to 200M due to technology change.

**VAS requirement:** Asset must be tested for impairment annually (or whenever indicators exist).

**Journal:**
- Dr 642 (or other appropriate expense): 300,000,000
- Cr 229 (provision for impairment): 300,000,000

**Note:** Tax authority does NOT recognize impairment losses as deductible. This is a permanent difference on CIT finalization. Must add back: 300M.

### Scenario FA-08: Asset Disposal / Liquidation

Sell old machinery. NG = 800M, accumulated depreciation = 650M, NBV = 150M. Sold for 120M + VAT.

**Step 1: Record disposal:**
- Dr 214: 650,000,000 (remove accumulated depreciation)
- Dr 811: 150,000,000 (loss on disposal = NBV)
- Cr 211: 800,000,000 (remove original cost)

**Step 2: Record proceeds:**
- Dr 112: 132,000,000 (120M + 12M VAT)
- Cr 711: 120,000,000 (gain on disposal)
- Cr 3331: 12,000,000 (output VAT — must issue invoice)

**Net impact:** Loss of 30M (811: 150M - 711: 120M). This loss is deductible for CIT.

**Documentation:** Biên bản thanh lý TSCĐ (Mẫu 02-TSCĐ) + Hợp đồng mua bán + Hóa đơn GTGT.

### Scenario FA-09: Fully Depreciated Asset Still in Use

Machine fully depreciated (NG = 100M, accumulated depreciation = 100M, NBV = 0) but still running.

**Rules:**
- No more depreciation. TK 214 = TK 211.
- Asset remains on register at NBV = 0.
- Still tracked for physical inventory purposes.
- If disposed: Dr 214 (full), Cr 211 (full). No P&L impact.
- If revalued: Can increase NG and restart depreciation under VAS.

**Risk:** Without proper tracking, these "zero value" assets are often lost or stolen. Physical count required annually.

---

## CCDC (TK 242) Scenarios

### Scenario CCDC-01: Purchase Tool/Equipment

Buy 10 printers at 5,000,000 each = 50,000,000 + VAT 8%.

- Per unit: 5M < 30M → CCDC
- Dr 153: 50,000,000
- Dr 1331: 4,000,000
- Cr 331: 54,000,000

### Scenario CCDC-02: Allocation Setup

Issue 10 printers to office use.

**Decision:** Value per unit = 5M. Do we expense immediately or amortize?
- If company policy: items > 2M must be amortized → Amortize over 12 months
- Dr 242: 50,000,000
- Cr 153: 50,000,000

**Amortization schedule:**
- Period: 12 months
- Monthly: 50,000,000 / 12 = 4,166,667
- First month (if issued on 10th): prorated: (4,166,667 / 30) × 21 days = 2,916,667

### Scenario CCDC-03: Monthly Amortization Posting

Every month-end:
- Dr 642: 4,166,667
- Cr 242: 4,166,667

After 12 months: TK 242 balance = 0. CCDC fully expensed.

### Scenario CCDC-04: Change Allocation Period

After 6 months, company decides to change from 12 to 18 months.

**Treatment:** This is a change in accounting estimate. Adjust prospectively.
- Remaining balance: 50,000,000 - (4,166,667 × 6) = 25,000,000
- Remaining months: 18 - 6 = 12
- New monthly amortization: 25,000,000 / 12 = 2,083,333

### Scenario CCDC-05: Early Termination of Allocation

Printer breaks (after 8 months). Cannot be repaired. Early write-off.
- Accumulated amortization: 4,166,667 × 8 = 33,333,336
- Remaining TK 242 balance: 50,000,000 - 33,333,336 = 16,666,664
- Dr 642 (or 811 for abnormal loss): 16,666,664
- Cr 242: 16,666,664

**Tax note:** If early termination is due to normal wear → expense is deductible. If due to mismanagement/theft → may not be deductible.

### Scenario CCDC-06: Lost/Damaged CCDC

Printers stolen after 5 months. Police report filed.

- Accumulated amortization: 4,166,667 × 5 = 20,833,335
- Remaining: 29,166,665
- Dr 642 (extraordinary loss): 29,166,665
- Cr 242: 29,166,665

**Tax treatment:** With police report → deductible. Without → may be rejected.

### Scenario CCDC-07: Reclassification CCDC → Fixed Asset

Company buys a high-end server (28,000,000) classified as CCDC. Later upgrades it with 10,000,000 worth of components. Total = 38,000,000.

**Decision:** Now it meets FA threshold (≥ 30M). Reclassify.
- Dr 211: 38,000,000 (new NG)
- Cr 242: remaining CCDC balance (before reclassification)
- Cr 111/112: 10,000,000 (upgrade cost)
- System recalculates depreciation over remaining useful life as FA

---

## Month-End Scenarios

### ME-01: Depreciation Run

System calculates depreciation for all active assets:
- Query all assets where status = 'in_use' and NG > accumulated depreciation
- For each: monthly_depreciation = (NG - salvage_value) / useful_life_months
- Group by department → expense account mapping
- Generate batch journal entry

**Validation:** total Dr entries = total Cr entries. Number of lines = number of depreciation accounts.

### ME-02: Amortization Run

System calculates amortization for all active CCDC:
- Query all CCDC where status = 'in_use' and TK 242 balance > 0
- For each: monthly_amortization = remaining_242_balance / remaining_months
- Generate batch journal entry

### ME-03: Adjustment Posting

Accountant finds error: asset A was using wrong useful life (5 years instead of 8 years).

**Correction method (TT 99 Art. 9 — tự điều chỉnh):**
- Calculate correct depreciation retrospectively
- If under-depreciated: Dr accumulated depreciation catch-up, Cr retained earnings (or current P&L)
- Adjust future depreciation prospectively

### ME-04: Closing Reconciliation

**Pre-close checklist for FA/CCDC:**
1. Total TK 214 balance = sum of all individual asset accumulated depreciation
2. Total TK 211 balance = sum of all asset NG
3. Total TK 242 balance = sum of all active CCDC remaining balance
4. NBV total (211 - 214) = balance sheet value (MS 220)
5. No asset has negative NBV
6. No asset with status 'disposed' still depreciating
7. All CCDC items have amortization end date set

---

# 3. Use Cases

## FA-UC-01: Purchase Asset for Cash

**Name:** Ghi tăng TSCĐ mua bằng tiền  
**Goal:** Record FA purchased via cash/bank transfer  
**Actors:** Kế toán TSCĐ, Kế toán trưởng  
**Preconditions:** Supplier invoice, Biên bản giao nhận TSCĐ  
**Trigger:** Payment completed + asset received  
**Happy Path:**
1. System captures: supplier, invoice no, purchase date, NG components
2. NG = purchase price + transport + installation + testing + registration fees
3. VAT recorded separately (TK 1332) if invoice valid
4. Asset created with status 'pending'
5. After Biên bản giao nhận signed: status changes to 'in_use', depreciation starts next month
**Alternative Path — Import:** Add import duty (TK 3333), non-refundable taxes to NG
**Exception Path — Credit purchase:** Cr 331 instead of 111/112
**Accounting Rules Applied:**
- Debit 211 (NG), Debit 1332 (VAT), Credit 111/112/331
- NG = all costs to bring asset to ready-for-use condition
**Depreciation Rules:** Not yet applicable (not in use)
**Journal Entry Impact:**
```
Dr 2111 (buildings): 5,000,000,000  (NG)
Dr 1332 (VAT input): 500,000,000
Cr 112: 5,500,000,000
```
**FS Impact:** BC 01 MS 221 increases by NG. Cash decreases.
**Audit Risk:** Missing costs in NG → understated depreciation → overstated profit
**Final Result:** Asset registered at correct NG, ready for depreciation

## FA-UC-02: Monthly Depreciation Run

**Name:** Trích khấu hao TSCĐ hàng tháng  
**Goal:** Calculate and post monthly depreciation for all active assets  
**Actors:** System (automatic), Kế toán tổng hợp (review)  
**Preconditions:** Assets are 'in_use' status, useful life and method set  
**Trigger:** Month-end closing  
**Happy Path:**
1. System queries all assets where status = 'in_use' and NBV > 0
2. For each: monthly_dep = (NG - salvage) / (useful_life_months)
3. Group by expense account (based on department using asset)
4. Generate batch journal: Dr 627/641/642/241/623 → Cr 214
5. Post to GL
6. Generate Mẫu 06-TSCĐ (Bảng tính và phân bổ khấu hao)
**Alternative Path — Partial month:** Assets put into use after 15th: start depreciation next month
**Exception Path — Fully depreciated:** Skip assets where NBV = 0
**Accounting Rules Applied:**
- Straight-line method (default)
- Depreciation per TT 45 Art. 9: "trích khấu hao hàng tháng"
**Depreciation Rules:**
- Monthly = (NG - salvage) / (life_years × 12)
- No depreciation in month of acquisition if received after 15th
**Journal Entry Impact:**
```
Dr 627: 80,000,000 (production — máy móc)
Dr 641: 15,000,000 (sales — xe tải)
Dr 642: 25,000,000 (management — văn phòng)
Cr 2141: 120,000,000 (tổng khấu hao)
```
**FS Impact:** BC 02 MS 24/25/26 increase. BC 01 MS 222 increases, MS 220 decreases.
**Audit Risk:** Missing depreciation for new assets, double depreciation for disposed assets
**Final Result:** 120M moved from BC 01 to BC 02 as expense

## FA-UC-03: Asset Disposal (Liquidation)

**Name:** Thanh lý TSCĐ  
**Goal:** Remove disposed asset from register, record gain/loss  
**Actors:** Kế toán TSCĐ, Kế toán trưởng, Giám đốc  
**Preconditions:** Asset exists, Biên bản thanh lý signed  
**Trigger:** Physical disposal or sale  
**Happy Path:**
1. Verify asset exists and NBV is correct
2. Record proceeds: Dr 111/112 → Cr 711
3. Output VAT: Dr 112 → Cr 3331
4. Remove asset: Dr 214 (full accum. dep.), Dr 811 (residual loss), Cr 211 (original NG)
5. Update FA status to 'disposed'
**Alternative Path — NBV = 0:** Dr 214 (full), Cr 211 (full). No P&L impact.
**Alternative Path — Gain:** Remove Dr 214, Dr 811 (if loss), Cr 211, Cr 711 (if proceeds > NBV)
**Exception Path — Scrap:** No proceeds, only removal cost. Dr 811 (full NBV + removal cost), Cr 211.
**Accounting Rules Applied:**
- Full derecognition of asset
- Gain/loss = proceeds - NBV - disposal costs
**Depreciation Rules:** Stop depreciation at disposal date. No depreciation in month of disposal.
**Journal Entry Impact:**
```
Step 1 — Removal:
Dr 2141: 650,000,000 (accumulated depreciation)
Dr 811: 150,000,000 (NBV = 800M - 650M)
Cr 2111: 800,000,000

Step 2 — Proceeds:
Dr 112: 132,000,000
Cr 711: 120,000,000 (selling price)
Cr 3331: 12,000,000 (VAT 10%)
```
**FS Impact:** BC 01 — asset removed. BC 02 — loss 30M (150M - 120M) reduces profit.
**Audit Risk:** Asset still on register after disposal → overstate BC 01.
**Final Result:** Asset removed. P&L impact recorded. Register clean.

## FA-UC-04: Asset Upgrade (Nâng cấp)

**Name:** Nâng cấp TSCĐ  
**Goal:** Capitalize improvement cost, extend life/increase capacity  
**Actors:** Kế toán TSCĐ  
**Preconditions:** Asset exists, upgrade complete  
**Trigger:** Biên bản bàn giao TSCĐ sửa chữa/nâng cấp (Mẫu 03-TSCĐ)  
**Happy Path:**
1. Verify cost is capitalizable (increases future economic benefit)
2. Add cost to NG: Dr 211 → Cr 111/112/331
3. Recalculate remaining depreciation: (new NG - salvage - accumulated depreciation) / remaining months
4. Update useful life if extended
**Alternative Path — Cost < threshold:** Expense immediately even if asset is FA (minor improvement)
**Exception Path — Wrong classification:** Upgrade treated as repair → reclassify
**Accounting Rules Applied:**
- Only costs that increase capacity/quality/extend life are capitalized
- Routine repair: expense immediately
**Depreciation Rules:**
- Recalculate prospectively from upgrade month
- Do NOT adjust past depreciation
**Journal Entry Impact:**
```
Dr 2111: 200,000,000 (increase NG)
Cr 112: 200,000,000

Then:
New NG = 1,000M + 200M = 1,200M
Old accum. dep. = 300M (after 3 years of 10)
Remaining months = 84 (7 years)
New monthly dep. = (1,200M - 300M - 0) / 84 = 10,714,286
```
**FS Impact:** BC 01 — NG increases. BC 02 — future depreciation higher.
**Audit Risk:** Expensing capitalizable costs → understate asset, overstate expense.
**Final Result:** Asset value updated, depreciation recalculated.

## FA-UC-05: Asset Impairment (Đánh giá lại)

**Name:** Đánh giá lại TSCĐ / Trích lập dự phòng giảm giá  
**Goal:** Record impairment when recoverable amount < NBV  
**Actors:** Kế toán TSCĐ, Thẩm định giá (external)  
**Preconditions:** Impairment indicator exists (market decline, damage, obsolescence)  
**Trigger:** Biên bản đánh giá lại TSCĐ (Mẫu 04-TSCĐ)  
**Happy Path:**
1. Determine recoverable amount (higher of fair value - selling costs and value in use)
2. Calculate impairment loss = NBV - recoverable amount
3. Dr 642 (or 632): impairment loss
4. Cr 229: provision for impairment
5. Update NBV disclosure
**Alternative Path — Reversal:** If recoverable amount recovers later → reverse impairment (max to original NBV)
**Exception Path — Full impairment:** NBV > recoverable amount → write down to zero
**Accounting Rules Applied:**
- VAS requires impairment testing annually
- Impairment loss is NOT tax deductible (permanent difference)
**Depreciation Rules:** After impairment, recalculate depreciation on new NBV over remaining useful life.
**Journal Entry Impact:**
```
Dr 642: 300,000,000
Cr 2294: 300,000,000

Then new monthly dep = (500M - 300M - 100M salvage) / 60 months = 1,666,667
```
**FS Impact:** BC 01 — TK 229 increases (contra asset). BC 02 — expense increases.
**Audit Risk:** Impairment not recognized → BC 01 overstated. Auditor will adjust.
**Final Result:** Asset value adjusted. CIT add-back tracked.

---

## CCDC-UC-01: Purchase and Allocate CCDC

**Name:** Mua và phân bổ CCDC  
**Goal:** Record CCDC purchase and setup amortization schedule  
**Actors:** Kế toán kho, Kế toán tổng hợp  
**Preconditions:** Item exists, < 30M or < 1 year life  
**Trigger:** Purchase invoice received  
**Happy Path:**
1. Record purchase: Dr 153, Dr 1331, Cr 111/112/331
2. On issue: Dr 242 (if multi-period), Cr 153
3. Set amortization period (1-36 months)
4. Calculate monthly amortization
5. Generate amortization schedule
**Alternative Path — Direct expense:** Small value → Dr 627/641/642 directly
**Exception Path — Return to supplier:** Reverse purchase: Dr 331, Cr 153, Cr 1331
**Accounting Rules Applied:**
- Max 3 years amortization per TT 96/2015
- Must be reasonable for the item's actual useful life
**Amortization Rules:**
- First month: prorated by days if mid-month
- Monthly = total / months
- After first month: re-calculate for remaining balance/period
**Journal Entry Impact:**
```
Purchase:
Dr 153: 50,000,000
Dr 1331: 4,000,000
Cr 331: 54,000,000

Issue:
Dr 242: 50,000,000
Cr 153: 50,000,000

Monthly amortization (12 months):
Dr 642: 4,166,667
Cr 242: 4,166,667
```
**FS Impact:** BC 01 — TK 242 decreases monthly. BC 02 — expense recognized.
**Audit Risk:** Forgotten amortization → TK 242 balance stale → BC 01 overstated.
**Final Result:** CCDC properly tracked and expensed over useful period.

## CCDC-UC-02: Early Write-Off CCDC

**Name:** Xử lý CCDC hỏng/mất  
**Goal:** Remove CCDC from register when lost/damaged/destroyed  
**Actors:** Kế toán tổng hợp  
**Preconditions:** CCDC active on TK 242, loss confirmed  
**Trigger:** Biên bản xử lý tài sản  
**Happy Path:**
1. Calculate remaining TK 242 balance
2. Record write-off: Dr 642/811, Cr 242
3. Update CCDC status to 'written_off'
**Alternative Path — Insurance claim:** Dr 138 (receivable), Cr 711
**Exception Path — Third-party liability:** Dr 138 (recoverable from liable party)
**Accounting Rules Applied:**
- Normal loss: expense (TK 642)
- Abnormal loss: other expense (TK 811)
**Journal Entry Impact:**
```
Dr 642: 16,666,664 (remaining balance)
Cr 242: 16,666,664

CCDC status → 'written_off'
```
**FS Impact:** BC 02 — expense recognized. BC 01 — TK 242 decreases.
**Audit Risk:** Missing documentation (police report for theft) → tax disallowance.
**Final Result:** CCDC removed, expense recorded.

---

# 4. Lifecycle Rules (Core Accounting Engine Logic)

## Fixed Asset Lifecycle Rules

### Rule FA-R01: When Asset Becomes Fixed Asset
- Cost ≥ 30,000,000 VND
- Useful life > 1 year
- Probable future economic benefit
- Reliable cost measurement

All three criteria must be satisfied simultaneously (TT 45 Art. 3).

### Rule FA-R02: How Cost Is Capitalized
- NG = Purchase price (net of trade discounts) + transport + installation + testing + professional fees + registration fees + import duties
- VAT is NOT part of NG if deductible (TK 1332)
- VAT IS part of NG if non-deductible (e.g., non-taxable activity)
- Borrowing costs for qualifying assets: capitalized per VAS 16

### Rule FA-R03: How Depreciation Method Chosen
Three methods per TT 45:
1. **Straight-line (đường thẳng):** Default. Equal amount every month. Most common.
2. **Declining balance (số dư giảm dần):** Accelerated. Higher early years. Must have profit to use.
3. **Production-based (sản lượng):** Based on actual output. For machinery with variable usage.

**Choice is documented** and registered with tax authority. Cannot change during asset's life (except once, with justification).

### Rule FA-R04: How Monthly Depreciation Calculated
```
Monthly depreciation = (NG - salvage value) / useful life in months

Useful life: within TT 45 Appendix I ranges
  - Buildings: 25-50 years
  - Machinery: 7-15 years
  - Vehicles: 6-10 years
  - Computers: 3-8 years
  - Office equipment: 5-10 years

Salvage value: typically 0 for tax purposes
```

### Rule FA-R05: How Accumulated Depreciation Tracked
- TK 2141 (tangible FA), TK 2142 (finance lease), TK 2143 (intangible FA)
- Increases monthly by depreciation amount
- Balance = cumulative depreciation from acquisition to date
- Cannot exceed NG (asset cannot be depreciated below zero)
- On disposal: full TK 214 balance is reversed against TK 211

### Rule FA-R06: How Disposal Is Handled
- Full derecognition: remove TK 211 (NG) and TK 214 (accum. dep.)
- Record proceeds at fair value
- Gain/loss = proceeds - NBV - direct disposal costs
- Report on BC 02 as other income/expense (MS 31/32)
- VAT: output VAT on proceeds (if selling)

### Rule FA-R07: How Revaluation/Adjustment Handled
- VAS requires revaluation only at specific events (privatization, M&A, contribution)
- Revaluation increase: Dr 211, Cr 412 (equity reserve)
- Revaluation decrease: Dr 412 (if reserve exists), otherwise Dr 632 (expense)
- After revaluation: depreciation recalculated on new NG
- **Revaluation is rare in Vietnamese SMEs.** Most use historical cost.

---

## CCDC Lifecycle Rules

### Rule CCDC-R01: When Item Is CCDC (TK 242)
- Cost < 30,000,000 VND
- OR useful life < 1 year
- OR is a consumable (uniforms, packaging, tools)
- Items > 1,000,000 VND with use > 1 period → TK 242 amortization
- Items < 1,000,000 VND → direct expense

### Rule CCDC-R02: How Allocation Period Defined
- Company determines based on item's expected useful life
- Maximum: 36 months (3 years) per TT 96/2015
- Common periods: 6 months, 12 months, 24 months, 36 months
- Policy should be documented in company's accounting regulation

### Rule CCDC-R03: How Monthly Expense Recognized
```
Monthly amortization = Total CCDC value / months

First month (if issued mid-month):
  Prorated = (monthly_amount / days_in_month) × days_from_issue_to_month_end

After first month: recalculate
  Remaining = (total - first_month) / (remaining_months)
```

### Rule CCDC-R04: How Partial Allocation Works
If CCDC is used jointly by multiple departments:
- Allocate proportionally (e.g., 60% production, 40% management)
- Dr 627: 60%, Dr 642: 40%, Cr 242: 100%
- Allocation ratio should be consistent monthly

### Rule CCDC-R05: How Early Stop Allocation Works
- When CCDC is lost, damaged, or fully consumed before schedule
- Remaining TK 242 balance → write off to appropriate expense account
- Normal wear: Dr original expense account (627/641/642)
- Abnormal/theft: Dr 811

### Rule CCDC-R06: How Reclassification Works
CCDC → FA reclassification:
- Only when cumulative costs push total per-unit value ≥ 30M
- Or when original classification was incorrect
- Transfer from TK 242 to TK 211
- Begin FA depreciation rules apply prospectively

---

# 5. Process & Workflow Logic

## 5.1 Asset Acquisition Workflow

```
Purchase Requisition → PO → Goods Receipt → Invoice → 
  → Capitalization Decision (FA vs CCDC vs Expense) →
    → If FA: Biên bản giao nhận TSCĐ (Mẫu 01-TSCĐ) →
    → Register asset in FA module →
    → Set useful life, depreciation method →
    → Ready for depreciation
```

**Approval gates:** Purchase > 50M requires Kế toán trưởng approval. > 500M requires Giám đốc.

## 5.2 Monthly Depreciation Run Workflow

```
[System] Last day of month:
1. Query all assets with status = 'in_use' and NBV > 0
2. For each: calculate monthly depreciation
3. Group by expense account (department mapping)
4. Generate batch journal entry
5. Post to GL
6. Generate Mẫu 06-TSCĐ (Bảng tính & phân bổ khấu hao)

[Human] Kế toán trưởng:
7. Review batch for reasonableness
8. Compare to prior month (variance analysis)
9. Approve posting
10. File Mẫu 06-TSCĐ for the month
```

## 5.3 Monthly CCDC Allocation Workflow

```
[System] Last day of month:
1. Query all CCDC with status = 'in_use' and TK 242 balance > 0
2. For each: calculate monthly amortization
3. Group by expense account
4. Generate batch journal entry
5. Post to GL

[Human] Kế toán trưởng:
6. Verify amortization schedule matches policy
7. Check for CCDC that should have ended but still active
```

## 5.4 Disposal Workflow

```
1. Request for disposal (reason: sold/scrapped/donated)
2. Giám đốc approval
3. Biên bản thanh lý TSCĐ (Mẫu 02-TSCĐ) signed
4. If selling: issue hóa đơn GTGT
5. Record disposal in system
6. System stops depreciation
7. Remove from FA register
8. Archive disposal documents
```

## 5.5 Month-End Closing Workflow (FA/CCDC Part)

```
Step 1: Run depreciation → verify posting
Step 2: Run CCDC amortization → verify posting
Step 3: Reconcile FA register to GL
  - Sum of TK 211 balances = FA register total NG
  - Sum of TK 214 balances = FA register total accumulated depreciation
  - Sum of TK 242 balances = CCDC remaining balances
Step 4: Check for exceptions
  - Assets with NBV = 0? Confirm status
  - Assets acquired but not in use? Confirm appropriate
  - Assets past useful life? Flag for review
Step 5: Generate reports
  - Mẫu 06-TSCĐ (depreciation schedule)
  - FA movement schedule (tăng/giảm trong kỳ)
  - BC 01 cross-reference
Step 6: Close period for FA module
```

## 5.6 Audit Preparation Workflow

```
1. Print full FA register (code, name, NG, acc. dep., NBV)
2. Print Mẫu 06-TSCĐ for each month in audit period
3. Print FA movement report (additions, disposals, transfers)
4. Print disposal files (Biên bản thanh lý, Hợp đồng, Hóa đơn)
5. Verify BC 01 MS 220/221/222 = FA register totals
6. Physical verification: select 10% of assets, confirm existence
7. Confirm depreciation method consistency
8. Confirm useful lives within TT 45 framework
9. Document impairment assessment (even if no impairment)
10. Prepare accounting policy disclosure for BC 09
```

---

# 6. Data Flow (Business View Only)

## Purchase Invoice → Asset Register

```
Supplier Invoice (hóa đơn GTGT)
  → Extract: item, NG components, VAT, supplier
  → Capitalization decision (FA/CCDC/Expense)
  → If FA: Create FA record
  → FA record: code, name, purchase_date, NG, supplier, invoice_no
  → Asset register updated
```

## Asset Register → Depreciation Schedule

```
Asset register (all 'in_use' assets)
  → For each: NG, useful_life_months, method, start_date
  → Calculate monthly_depreciation
  → Generate Mẫu 06-TSCĐ table
  → Columns: asset code, name, NG, prior month dep, current dep, accum dep, NBV
  → Rows: each asset, grouped by department/expense account
  → Total row: sum of all depreciation
```

## Depreciation Schedule → Journal Entries

```
Mẫu 06-TSCĐ
  → Group by expense account (department codes)
  → Generate batch journal:
      Dr 627: production depreciation total
      Dr 641: sales depreciation total
      Dr 642: management depreciation total
      Cr 214: grand total
  → Post to GL
  → Each line references: Mẫu 06-TSCĐ month/year
```

## CCDC Register → Allocation Schedule

```
CCDC TK 242 register
  → For each item: total value, start_date, months, monthly_amount
  → Remaining balance = total - (monthly × months_elapsed)
  → Generate allocation schedule
  → Column: item, total, monthly, elapsed_months, remaining, end_date
```

## Allocation Schedule → Expense Recognition

```
Allocation schedule
  → Group by expense account
  → Monthly entry:
      Dr 627/641/642: total CCDC amortization
      Cr 242: same total
  → Post to GL
```

## Journal Entries → General Ledger

```
Each month's batch:
  1 depreciation entry (Dr multiple expense, Cr 214)
  1 CCDC amortization entry (Dr multiple expense, Cr 242)
  1 acquisition entry (if any: Dr 211, Cr 111/112/331)
  1 disposal entry (if any: Dr 214 + Dr 811, Cr 211)

All posted to GL.
GL shows running balance for each account.
```

## Ledger → BC 01 / BC 02 Reports

```
BC 01 — Balance Sheet:
  MS 221 (NG): TK 211 ending balance
  MS 222 (Accum. dep.): TK 214 ending balance
  MS 220 (NBV): MS 221 - MS 222
  MS 242 (Prepaid): TK 242 ending balance

BC 02 — Income Statement:
  MS 24 (SXC): sum of Dr 627 depreciation + CCDC amortization
  MS 25 (BH): sum of Dr 641 depreciation + CCDC amortization  
  MS 26 (QL): sum of Dr 642 depreciation + CCDC amortization

Cross-check: monthly depreciation total = change in TK 214 + disposed accumulated depreciation
```

---

# 7. Validation & Control Rules

## V-01: Missing Depreciation Detection
**Rule:** Every month-end, system checks: are there assets with status 'in_use' that have NO depreciation entry this month?
**Action:** Alert: "Các TSCĐ sau chưa được trích khấu hao tháng này: [list]"

## V-02: Double Depreciation Prevention
**Rule:** If a depreciation entry already exists for this month for an asset, block second entry.
**Action:** "TSCĐ [code] đã được trích khấu hao tháng này. Không thể trích lần thứ hai."

## V-03: Wrong Useful Life Detection
**Rule:** Useful life must be within TT 45 Appendix I ranges for the asset category.
**Action:** Warning if outside range: "Thời gian sử dụng [X năm] nằm ngoài khung [min-max] năm cho nhóm TSCĐ này."

## V-04: Wrong Allocation Period Detection
**Rule:** CCDC allocation period must be 1-36 months.
**Action:** Block if > 36: "Thời gian phân bổ CCDC tối đa 36 tháng theo TT 96/2015."

## V-05: Asset Not In Use But Depreciating
**Rule:** If status ≠ 'in_use' (e.g., 'repair', 'idle'), system must verify depreciation should continue.
**Action:** Flag: "TSCĐ [code] có trạng thái [status] nhưng vẫn đang trích khấu hao. Xác nhận tiếp tục?"

**Note under TT 45:** Assets under repair can continue depreciation. But idle assets: if temporarily out of service > 1 year → stop depreciation.

## V-06: CCDC Not Allocated Flagged
**Rule:** If CCDC was issued to TK 242 > 1 month ago but has 0 amortization entries.
**Action:** Alert: "CCDC [code] đã xuất vào TK 242 từ [date] nhưng chưa có bút toán phân bổ."

## V-07: Register vs Ledger Mismatch
**Rule:** After each month-close, compare:
- Sum FA register NG = TK 211 GL balance
- Sum FA register accum dep = TK 214 GL balance
- Sum CCDC remaining = TK 242 GL balance
**Action:** If mismatch > 1,000 VND: "Chênh lệch giữa sổ chi tiết TSCĐ và số dư tài khoản: [amount]. Cần kiểm tra."

## V-08: Audit Trail Preservation
**Rule:** Every change to FA/CCDC record must log:
- Before value, after value
- User who made change
- Timestamp
- Reason for change
**Action:** Audit log queryable by asset code, date range, user.

## V-09: Negative NBV Prevention
**Rule:** If monthly depreciation would cause NBV < 0, cap at NBV.
**Action:** "TSCĐ [code] sẽ hết khấu hao sau tháng này. NBV sau khấu hao = 0."

## V-10: Disposal Completeness Check
**Rule:** After disposal, verify both NG and accumulated depreciation were fully removed.
**Action:** "TSCĐ [code] đã thanh lý nhưng TK 214 còn số dư [amount]. Cần kiểm tra."

---

# 8. User Journey (SME Reality)

## Daily Asset Entry Journey

**7:30 AM — Kế toán kho receives supplier delivery:**
- Checks goods received note against PO
- Enters purchase invoice into system
- Makes capitalization decision: is this ≥ 30M and > 1 year life?
- If FA → opens FA form, enters NG components, uploads invoice PDF
- If CCDC → enters as inventory item

**9:00 AM — Kế toán tổng hợp reviews:**
- Confirms FA capitalization is correct
- Sets useful life (checks TT 45 framework)
- Sets depreciation method (usually straight-line)
- Assigns department (for expense allocation)
- Prints Biên bản giao nhận TSCĐ for signatures
- Asset goes 'active' — ready for depreciation next month

## Monthly Depreciation Run Journey

**Day 28 of month — Kế toán tổng hợp runs monthly close:**
- Opens FA module → clicks "Tính khấu hao tháng này"
- System calculates all assets in 2-3 seconds
- Shows comparison: last month vs this month
- Accountant reviews: any new assets? Any disposals?
- Checks: any red flags? (negative NBV, status inconsistency)
- Clicks "Ghi sổ" → system posts batch journal
- Generates Mẫu 06-TSCĐ (PDF for filing)

**Then CCDC amortization:**
- Clicks "Tính phân bổ CCDC tháng này"
- Same flow: calculate → review → post → print schedule

## Monthly Closing Reconciliation Journey

**Day 30 — System reconciliation:**
- System auto-checks: FA register total = GL balance
- If match → green ✅
- If mismatch → red ❌ with difference amount

**Accountant investigates mismatch:**
- Runs detailed comparison report
- Finds: one asset was disposed in GL but not in FA register
- Fixes: updates FA register status
- Re-runs reconciliation → green ✅

## CFO/Chief Accountant Review Journey

**Quarter-end — CFO reviews FA status:**
- Opens dashboard: total NBV, monthly depreciation, additions this quarter, disposals
- Checks BC 01 FA numbers against prior quarter
- Verifies depreciation expense trend vs prior year
- Approves quarterly CIT provisional: depreciation is deductible

## Audit Preparation Journey

**Audit announcement received — 2 weeks to prepare:**
- Kế toán tổng hợp runs all FA reports:
  - FA register (full list)
  - Monthly Mẫu 06-TSCĐ (12 months)
  - FA movement summary (additions/disposals)
  - Physical count report
- Matches BC 01 to FA register → any difference? Fix immediately
- Prints all Biên bản thanh lý for disposed assets
- Verifies all useful lives within TT 45 framework
- Confirms depreciation method consistency

**Auditor arrives:**
- Selects 5 assets from register → "Show me these physical assets"
- Takes photo of each
- Checks: does asset tag match register?
- Asks: "Why did you change useful life on asset XYZ from 8 to 10 years?"
- Accountant explains: "Still in use, reassessed remaining life"
- Auditor accepts

## Correction/Adjustment Journey

**Error found:** Máy in A was depreciated using 5-year life instead of 8-year.

**Correction:**
1. Calculate correct depreciation (8 years = 96 months) vs actual (5 years = 60 months)
2. Over-depreciated by: (actual - correct) × months elapsed
3. If over: Dr 214 (reduce accum dep), Cr 642 (reverse excess expense)
4. Adjust future: new monthly = remaining NG / remaining months
5. Log in audit trail: "Điều chỉnh thời gian khấu hao từ 5 năm lên 8 năm do đánh giá lại thời gian sử dụng thực tế."

---

# 9. SME Pain Analysis

## P01: Excel Depreciation Chaos
**Pain:** "My FA register is in Excel. Last month I accidentally dragged a formula wrong. Now every asset's depreciation is off by 20%. I have to redo 50 assets manually."
**Impact:** 4-8 hours/month fixing Excel errors. BC 01/02 wrong until caught.
**Solution:** System calculates automatically. Never touch formulas.

## P02: Wrong Useful Life Setup
**Pain:** "We set 5 years for a building. After 3 years, accountant realizes buildings should be 25-50 years. Now we've over-depreciated by 60%. CIT adjustment needed."
**Impact:** Over-depreciation reduces taxable profit → underpaid CIT → penalty + interest.
**Solution:** System enforces TT 45 useful life ranges per asset category.

## P03: Missing Asset Tracking
**Pain:** "We bought 10 laptops with petty cash. Nobody told accounting. Laptops were used for 6 months before appearing on FA register. 6 months of missing depreciation."
**Impact:** BC 01 understated. BC 02 overstated (missing expense). Both wrong.
**Solution:** Integration with purchasing/PettyCashService → automatic asset creation on eligible purchases.

## P04: Manual Journal Errors
**Pain:** "I posted depreciation manually: Dr 642 50M, Cr 214 50M. But I typed 5M instead of 50M. Caught it 3 months later. Three months of wrong BC 01/02."
**Impact:** Misstated financial statements for 3 months. Audit risk.
**Solution:** System-generated journals with pre-calculated amounts. No manual typing.

## P05: CCDC Forgotten Allocation
**Pain:** "We bought 50 laptops last year and posted to TK 242. Nobody set up amortization schedule. TK 242 still shows 500M balance. BC 01 is overstated by 500M."
**Impact:** 500M sitting on balance sheet as an asset that has already been consumed.
**Solution:** System auto-generates amortization schedule on CCDC issue. Alerts if amortization hasn't been run.

## P06: Month-End Stress
**Pain:** "Every month-end, I spend 3 hours on FA + 2 hours on CCDC. If I'm on leave, nobody knows how to run it. Month-end takes 2 extra days."
**Impact:** Late closing → late management reports → late decisions.
**Solution:** One-click "Run monthly depreciation + amortization" batch process.

## P07: BC 01 vs Ledger Mismatch
**Pain:** "Our BC 01 says TSCĐ NBV = 2.5B. Our FA register says 2.3B. I don't know which is right. I'll just adjust the register to match."
**Impact:** Accountant hides the error. Discrepancy compounds monthly. Next year: 400M gap. Audit finds it → qualification on FS.
**Solution:** Auto-reconciliation at every period close. Mismatch > 0 VND: cannot close period until resolved.

## P08: Audit Adjustment Risk
**Pain:** "Auditor asked for our FA register and depreciation schedule. Excel had a broken formula for 2 assets. Auditor adjusted 15M. They charged 5M for the finding."
**Impact:** Audit fee increases. Audit opinion may be qualified if material. Tax inspection may follow.
**Solution:** Complete, accurate, verifiable register. Audit trail for every change.

## P09: Lack of Traceability
**Pain:** "The director asked: 'When did we buy that machine?' I checked Excel. No purchase date column. 'How much was it?' No invoice reference. 'Has it been fully depreciated?' Nobody knows."
**Impact:** Management loses trust in accounting data. Bad decisions based on bad info.
**Solution:** Every FA record has: purchase date, invoice no, supplier, NG breakdown, useful life, depreciation method, status.

## P10: Multi-Branch Inconsistency
**Pain:** "We have 3 branches. Each branch runs FA in their own Excel. Branch A uses 8 years for computers. Branch B uses 5 years. Branch C forgot to depreciate for 2 months."
**Impact:** Consolidated BC 01 is wrong. Inter-branch comparison meaningless.
**Solution:** Centralized FA register. Consistent policies. Company-wide configuration controls.

---

# 10. Final Deliverables

## Deep Accounting Analysis (Summary)

The FA and CCDC module must handle:

| Area | System Requirement |
|---|---|
| **FA Master** | Code, name, NG, supplier, purchase date, useful life, method, department, status, category (TT 45) |
| **Capitalization** | NG = purchase + transport + installation + testing + registration. VAT to 1332 if deductible |
| **Depreciation** | 3 methods. Straight-line default. Monthly = (NG - salvage) / life_months |
| **Accumulated Depreciation** | TK 214, auto-increment monthly, capped at NG |
| **Useful Life** | TT 45 Appendix I ranges enforced per category |
| **Disposal** | Full derecognition: Dr 214 + Dr/Cr 811/711, Cr 211 |
| **Repair vs Upgrade** | Repair → expense. Upgrade → capitalize, recalculate depreciation |
| **Impairment** | Dr 642, Cr 229. Not tax deductible. Track CIT add-back. |
| **CCDC Master** | Item, value, issue date, amortization period (1-36 months) |
| **CCDC Amortization** | Monthly = total / months. First month prorated if mid-month. |
| **CCDC Write-off** | Early termination: remaining balance to expense or loss |
| **Reconciliation** | Auto-verify: FA register = GL balance. Block close if mismatch. |
| **Audit Trail** | Every change logged: before/after/user/timestamp/reason |

## Lifecycle Diagrams (Text)

```
FA Lifecycle:
  Acquire → Capitalize → Depreciate (monthly) → (Repair/Upgrade) → (Impairment) → Dispose
  Timeline: Years (2-50 years)
  Balance Sheet: 211 ↑ → 214 ↑ monthly → 211 and 214 removed on disposal
  P&L: Depreciation expense monthly for entire useful life

CCDC Lifecycle:
  Purchase → Stock (153) → Issue → Prepaid (242) → Amortize (monthly) → Full expense
  Timeline: Months (1-36 months)
  Balance Sheet: 153 to 242 on issue → 242 ↓ monthly
  P&L: Amortization expense monthly
```

## Use Case Library (Summary)

| UC ID | Name | Type | Priority |
|---|---|---|---|
| FA-01 | Purchase Asset | Create | HIGH |
| FA-02 | Capitalization Decision | Process | HIGH |
| FA-03 | Put Into Use | Update | HIGH |
| FA-04 | Monthly Depreciation | Process | HIGH |
| FA-05 | Transfer Between Departments | Update | MEDIUM |
| FA-06 | Repair vs Upgrade | Create/Update | HIGH |
| FA-07 | Impairment | Create | MEDIUM |
| FA-08 | Disposal/Liquidation | Delete | HIGH |
| FA-09 | Fully Depreciated Tracking | Read/Monitor | LOW |
| CCDC-01 | Purchase & Allocate | Create | HIGH |
| CCDC-02 | Monthly Amortization | Process | HIGH |
| CCDC-03 | Change Allocation Period | Update | MEDIUM |
| CCDC-04 | Early Write-off | Delete | MEDIUM |
| CCDC-05 | Lost/Damaged | Delete | MEDIUM |
| CCDC-06 | Reclassify to FA | Update | LOW |
| ME-01 | Depreciation Run | Batch | HIGH |
| ME-02 | Amortization Run | Batch | HIGH |
| ME-03 | Adjustment Posting | Create | HIGH |
| ME-04 | Closing Reconciliation | Process | HIGH |

## Workflow Design (Summary)

```
Acquisition: PO → Receipt → Invoice → Capital Decision → FA Register → Depreciation
Monthly: Depreciation Run → CCDC Run → Reconciliation → Close
Disposal: Request → Approve → Physical → Document → Derecognize
```

## Validation Matrix

| Check | Rule | Severity |
|---|---|---|
| NG formatting | DECIMAL(15,2) > 0 | BLOCK |
| Useful life | Within TT 45 ranges per category | WARN |
| Depreciation method | Valid (straight/declining/production) | BLOCK |
| Department | Must exist in department master | BLOCK |
| Expense account | Must be 627/641/642/623/241 | BLOCK |
| Monthly depreciation | Cannot exceed (NG - accum_dep) | BLOCK |
| Disposal completeness | Both TK 211 and TK 214 cleared | WARN |
| Register = GL | After close: difference < 1000 VND | BLOCK |
| CCDC period | 1-36 months | BLOCK |
| CCDC remaining | ≥ 0 at all times | BLOCK |

## Journal Entry Patterns (Master Table)

| Transaction | Debit | Credit | Note |
|---|---|---|---|
| Purchase FA for cash | 211 (NG) | 111/112 | Include 1332 for VAT |
| Purchase FA on credit | 211 (NG) | 331 | Pay later |
| Self-constructed FA completed | 211 (NG) | 241 | Transfer from WIP |
| Monthly depreciation | 627/641/642/623/241 | 214 | Every month |
| Upgrade FA | 211 (increase) | 111/112 | Capitalize |
| Repair FA | 627/641/642 | 111/112 | Expense immediate |
| Impairment | 642 | 229 | Not tax deductible |
| Disposal (loss) | 214 + 811 | 211 | NBV loss |
| Disposal (gain) | 214 | 211 + 711 | NBV gain |
| Purchase CCDC to stock | 153 | 111/112 | |
| Issue CCDC (multi-period) | 242 | 153 | |
| Issue CCDC (direct expense) | 627/641/642 | 153 | Small value |
| Monthly CCDC amortization | 627/641/642 | 242 | Every month |
| CCDC early write-off | 642/811 | 242 | Remaining balance |

## BC 01 / BC 02 Impact

| FS Line | Mã số | Source | Monthly Change |
|---|---|---|---|
| NG TSCĐ HH | 221 | TK 211 | + acquisitions, - disposals |
| HM lũy kế TSCĐ HH | 222 | TK 214 | + monthly depreciation, - disposals |
| GT còn lại TSCĐ HH | 220 = 221-222 | Calculated | - monthly depreciation |
| Chi phí trả trước | 242 | TK 242 | - monthly CCDC amortization |
| Chi phí SXC (KH) | 24 | Dr 627 deprec + amort | + monthly |
| Chi phí BH (KH) | 25 | Dr 641 deprec + amort | + monthly |
| Chi phí QLDN (KH) | 26 | Dr 642 deprec + amort | + monthly |
| Lợi nhuận trước thuế | 50 | Net of all above | - (dep + amort) |

## Audit Risk Analysis

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Missing depreciation | HIGH | MEDIUM | Auto-run + alert if skipped |
| Double depreciation | LOW | MEDIUM | One-run-per-month lock |
| Wrong useful life | HIGH | HIGH | TT 45 framework enforcement |
| Disposal not removed | HIGH | HIGH | Auto-reconciliation at close |
| Register ≠ GL | VERY HIGH | VERY HIGH | Block close if mismatch |
| CCDC not amortized | HIGH | MEDIUM | Auto-schedule + alert |
| Missing capitalization | MEDIUM | HIGH | Link to purchasing workflow |
| Impairment not recognized | MEDIUM | HIGH | Annual impairment trigger |
| Manual journal error | VERY HIGH | HIGH | Auto-generated entries |
| Audit trail gap | MEDIUM | VERY HIGH | Every change logged immutably |

## SME Operational Best Practices

1. **Register depreciation method** with tax authority before first use
2. **Useful life:** always pick a value within TT 45 framework (document why)
3. **Capitalization threshold:** 30M strictly enforced. No exceptions.
4. **Monthly run:** same day every month (last working day). Never skip.
5. **Reconciliation:** FA register = GL. If not → don't close. Investigate first.
6. **Disposal:** never delete an asset. Always use disposal workflow with Biên bản.
7. **Physical count:** annually. All assets. Tagged with register code.
8. **CCDC policy:** written. Defines:
   - Which items go to TK 242 (value threshold, e.g., > 2M)
   - Default amortization periods (12/24/36 months)
   - Who can authorize write-off
9. **Audit file:** maintain a folder per year with:
   - FA register (year-end)
   - 12 months of Mẫu 06-TSCĐ
   - All Biên bản giao nhận (new assets)
   - All Biên bản thanh lý (disposals)
   - Physical count report
10. **Chief Accountant review:** quarterly. Spot check 5-10 assets.
    - Does asset exist? Match register.
    - Is depreciation reasonable? Compare to prior year same month.
    - Any disposals not recorded? Check physical.
    - Any fully depreciated assets still in use? Confirm status.

---

# 11. Reporting & Tax Compliance

## 11.1 TT 99 Form Templates — Full Specification

Circular 99/2025/TT-BTC defines 6 mandatory form templates for FA. Every transaction must produce the correct form.

### Mẫu 01-TSCĐ: Biên bản giao nhận TSCĐ

**Khi nào dùng:** Khi TSCĐ được bàn giao từ bên mua/bên xây dựng cho bên sử dụng. Depreciation bắt đầu sau form này.

| Field | Source | Note |
|---|---|---|
| Tên TSCĐ | FA record | Full name + model |
| Số hiệu TSCĐ | System-generated | Format: TS-{YYYY}-{NNNNN} |
| Nước sản xuất | Purchase data | |
| Năm sản xuất | Purchase data | |
| Số hiệu Biên bản | System-generated | Format: BB-01-{YYYY}-{NNNN} |
| Số hóa đơn | Invoice reference | From AP module |
| Nguyên giá | NG calculation | Purchase + transport + installation + testing + registration |
| Hao mòn lũy kế | 0 at acquisition | Dr 0 |
| Giá trị còn lại | NG | Equal to NG at this point |
| Đại diện bên giao | Supplier/constructor | |
| Đại diện bên nhận | User department | |

**Output:** Fixed-asset ID + initial depreciation will start next month.
**Storage:** Asset record + PDF generation for physical signature.
**Audit:** Form must be signed before asset can be 'in_use' status.

### Mẫu 02-TSCĐ: Biên bản thanh lý TSCĐ

**Khi nào dùng:** Khi TSCĐ được thanh lý (hết khấu hao, hư hỏng, bán).

| Field | Source | Note |
|---|---|---|
| Tên TSCĐ | FA record | |
| Số hiệu TSCĐ | FA record | |
| Nguyên giá | FA record | Original cost |
| Giá trị hao mòn lũy kế | FA record | Accumulated depreciation at disposal date |
| Giá trị còn lại | Calculated | NG - accumulated depreciation |
| Lý do thanh lý | User input | Dropdown: hết KH, hư hỏng, bán, lỗi thời |
| Chi phí thanh lý | User input | Actual removal/transport/dismantling costs |
| Giá trị thu hồi | User input | Proceeds from sale/scrap |
| Kết quả | Calculated | Gain/loss = proceeds - NBV - costs |
| Hội đồng thanh lý | Multi-person | CEO + Chief Accountant + Storekeeper |

**Output:** Journal: Dr 214 + Dr 811, Cr 211 (disposal). Dr 111/112 (proceeds).
**Storage:** PDF for tax inspection.
**Approval:** Must have Hội đồng thanh lý signature ≥ 3 persons.

### Mẫu 03-TSCĐ: Biên bản sửa chữa, nâng cấp TSCĐ

**Khi nào dùng:** Khi TSCĐ được sửa chữa lớn hoặc nâng cấp.

| Field | Source | Note |
|---|---|---|
| Tên TSCĐ, số hiệu | FA record | |
| Ngày sửa chữa/nâng cấp | User input | |
| Nội dung | User input | Detailed description of work done |
| Chi phí | User input | Actual cost |
| Nguồn vốn | User input | Quỹ đầu tư phát triển / vốn vay / khác |
| Loại | Dropdown | Sửa chữa (repair) vs Nâng cấp (upgrade) |
| Tăng NG (nếu nâng cấp) | Auto if upgrade | +Dr 211 if upgrade |
| Thời gian sử dụng lại | Auto | Recalculated remaining life |

**Accounting treatment depends on type:**
- Repair: Dr 627/641/642 → expense immediate
- Upgrade: Dr 211 → capitalize, recalculate depreciation

### Mẫu 04-TSCĐ: Biên bản đánh giá lại TSCĐ

**Khi nào dùng:** Khi đánh giá lại TSCĐ (bắt buộc khi cổ phần hóa, M&A, góp vốn).

| Field | Source | Note |
|---|---|---|
| Tên TSCĐ | FA record | |
| NG cũ | FA record | |
| Hao mòn lũy kế cũ | FA record | |
| Giá trị đánh giá lại | Appraisal report | From external valuer |
| NG sau đánh giá | Calculated | = new fair value |
| Hao mòn sau đánh giá | Calculated | Proportional |
| Chênh lệch tăng/giảm | Calculated | Dr 211 (+), Cr 412 (equity) |
| Ngày đánh giá | Appraisal date | |

**Note:** Revaluation is rare for SMEs. Most use historical cost.

### Mẫu 05-TSCĐ: Thẻ TSCĐ

**Khi nào dùng:** Mỗi TSCĐ có một thẻ riêng. Dùng để quản lý chi tiết.

| Field | Source |
|---|---|
| Full register info | FA record |
| Ngày đưa vào sử dụng | FA record 'in_use' date |
| Số hiệu chứng từ ghi tăng | Reference to Mẫu 01 |
| Các lần sửa chữa/lý do | Linked repair/upgrade records |
| Ngày thanh lý | If disposed |

**Operational use:** Print and attach to asset physically (or store digitally).
**Audit:** Auditor selects random assets → checks Thẻ matches physical asset.

### Mẫu 06-TSCĐ: Bảng tính và phân bổ khấu hao TSCĐ

**Khi nào dùng:** Mỗi tháng, sau khi chạy khấu hao.

**Most important form for tax inspection.** The tax inspector asks for this form first.

| Column | Source | Note |
|---|---|---|
| STT | Auto-number | |
| Tên TSCĐ | FA record | |
| Ngày đưa vào sử dụng | FA record | |
| Số hiệu TSCĐ | FA record | |
| NG đầu kỳ | NG + any additions this period | |
| Số khấu hao đã trích lũy kế | Accumulated depreciation before this month | |
| Giá trị còn lại | NG - accum dep before this month | |
| Số khấu hao tháng này | Calculated | Monthly depreciation |
| Khấu hao phân bổ cho các đối tượng | Sum of Dr accounts | Grouped by department/expense account |

**Layout for printing:**
```
BẢNG TÍNH VÀ PHÂN BỔ KHẤU HAO TSCĐ
Tháng 05/2026

| STT | Tên TSCĐ | ... | Số KH tháng này | TK 627 | TK 641 | TK 642 |
|-----|----------|-----|-----------------|--------|--------|--------|
| 1   | Máy A    | ... | 10,000,000     | 10,000,000 | - | - |
| 2   | Xe B     | ... | 5,000,000      | - | 5,000,000 | - |
|     | TỔNG     |     | 15,000,000     | 10,000,000 | 5,000,000 | - |
```

**Validation:** Total Dr = Total Cr. Total this column = sum of expense columns.

## 11.2 BC 01 / BC 02 / BC 09 Impact Mapping

### BC 01 (Cân đối kế toán)

| Mã số | Chỉ tiêu | TK | Source | Monthly Change |
|---|---|---|---|---|
| 220 | TSCĐ hữu hình | 211 - 214 | FA register | - depreciation |
| 221 | Nguyên giá | 211 | Sum of all FA NG | + acquisitions, - disposals |
| 222 | Giá trị hao mòn lũy kế | 214 | Sum of all FA accum dep | + monthly depreciation, - disposal removal |
| 227 | TSCĐ thuê tài chính | 212 - 2142 | Finance lease table | - finance lease depreciation |
| 230 | TSCĐ vô hình | 213 - 2143 | Intangible FA register | - amortization |
| 241 | CPSXKD dở dang | 154 | Reclassification from CIP | When 241 → 211 |
| 242 | Chi phí trả trước | 242 | CCDC register | - monthly amortization |
| 229 | Dự phòng giảm giá TSCĐ | 229 | Impairment table | + impairment, - reversal |

### BC 02 (Kết quả kinh doanh)

| Mã số | Chỉ tiêu | Source | Monthly Change |
|---|---|---|---|
| 24 | Chi phí bán hàng | Dr 641 depreciation + CCDC amort | Depends on sales dept assets |
| 25 | Chi phí QLDN | Dr 642 depreciation + CCDC amort | Depends on mgmt dept assets |
| 26 | Chi phí SXC | Dr 627 depreciation + CCDC amort | Depends on production assets |
| 31 | Lợi nhuận khác | Cr 711 (gain) - Dr 811 (loss) on disposal | When disposal happens |

### BC 09 (Thuyết minh BCTC)

| Section | Content | Source |
|---|---|---|
| IV.01 | TSCĐ tăng/giảm trong năm | FA register movement summary |
| IV.02 | Nguyên giá TSCĐ cuối năm | FA register total NG |
| IV.03 | Giá trị hao mòn lũy kế | FA register total accum dep |
| IV.04 | Chi phí khấu hao trong năm | Dr account totals grouped by expense |
| IV.05 | Tình hình thanh lý, nhượng bán | Disposal records for the year |
| IV.06 | Cam kết thuê tài chính | Finance lease schedule (if any) |

**BC 09 is annual** — not monthly. Data exported from FA module at year-end.

## 11.3 CIT Impact — Depreciation Deductibility

### General Rule (Luật Thuế TNDN Art. 4, Nghị định 320/2025)

Depreciation is deductible for CIT if:
1. Asset is used in production/business activities
2. NG ≤ 1.6B for cars (luxury car limitation per Nghị định 320/2025)
3. Useful life within TT 45 framework
4. Straight-line method (declining balance requires registration)
5. Complete documentation: invoice, Biên bản giao nhận, Mẫu 06-TSCĐ monthly

### Permanent Differences (Không được khấu trừ thuế)

| Item | Accounting | Tax | Impact |
|---|---|---|---|
| Impairment loss | Dr 642 | NOT deductible | Add back on CIT finalization |
| Depreciation on car NG > 1.6B | Full NG | NG capped at 1.6B | Excess depreciation NOT deductible |
| Depreciation on idle assets > 1 year | Dr 642 | NOT deductible if not used for business |
| Accelerated depreciation (declining balance) | Higher early years | Only straight-line amount deductible | Temporary difference — reverses later |

### Temporary Differences (Chênh lệch tạm thời)

| Scenario | Accounting Deferred Tax | Treatment |
|---|---|---|
| Declining balance > straight-line in early years | Dr CIT expense, Cr deferred tax liability | Reverses when declining balance < straight-line |
| Useful life shorter in accounting vs tax | Dr deferred tax asset | Reverses when accounting life ends |

**SME reality:** Most SMEs use straight-line with TT 45 standard lives. Temporary differences are rare. Permanent differences (impairment, luxury car) must be tracked for CIT finalization.

## 11.4 Tax Inspection Preparation

### FA Documents Required for Inspection

| Document | Retention | Source |
|---|---|---|
| FA register (full list) | Permanently | FA module / Mẫu 05-TSCĐ |
| Mẫu 06-TSCĐ (12 months) | 10 years | FA module monthly export |
| Mẫu 01-TSCĐ (each acquisition) | Permanently | Asset creation form |
| Mẫu 02-TSCĐ (each disposal) | Permanently | Disposal form |
| Purchase invoices | 10 years | AP module |
| Biên bản kiểm kê TSCĐ hàng năm | Permanently | Annual physical count |
| Policy document — FA vs CCDC vs expense | Permanently | Company's accounting regulation |

### Common Tax Inspection Findings

| Finding | Severity | Fix |
|---|---|---|
| No Mẫu 06-TSCĐ | Medium | System prints monthly — archive PDFs |
| Useful life outside TT 45 range | High | System enforces ranges per category |
| No Biên bản thanh lý for disposed assets | High | Block disposal without form |
| BC 01 NG ≠ FA register NG | Very High | Auto-reconciliation at period close |
| CCDC allocation > 36 months | Medium | System blocks > 36 |
| Impairment deducted for CIT | High | System tracks CIT add-back separately |
| Car NG > 1.6B fully depreciated | Medium | Two-track NG: accounting NG vs tax NG |
| Missing depreciation on new assets | High | Auto-detection: no depreciation entry → alert |

## 11.5 Audit File Requirements

Annual audit file for FA section must contain:

1. **FA register extract** — year-end balances, all assets
2. **Mẫu 06-TSCĐ** — all 12 months, printed and signed by Chief Accountant
3. **Movement schedule** — opening balance + additions - disposals = closing balance
   ```
   TK 211 Opening: X
   + Additions: Y
   - Disposals: Z
   = TK 211 Closing: X + Y - Z
   
   TK 214 Opening: A
   + Monthly depreciation: B (12 months = sum)
   - Depreciation on disposals: C
   = TK 214 Closing: A + B - C
   ```
4. **Physical count report** — signed, with exceptions noted
5. **Full-depreciated asset list** — still in use? Confirm status
6. **Impairment assessment** — performed? Any indicators?
7. **Useful life justification** — for any life outside standard range
8. **CIT schedule** — depreciation add-backs (impairment, luxury car)

---

# 12. Integration Contracts & Module Boundaries

## 12.1 Integration Map

```
                      ┌─────────────┐
                      │   Period    │
                      │   Close     │
                      └──────┬──────┘
                             │ Check: FA register = GL
                             │ Trigger: depreciation run
                             ▼
┌──────────┐   Purchase    ┌─────────────┐   Journal    ┌──────────┐
│    AP    │ ────────────► │     FA      │ ───────────► │   GL     │
│ Module   │ (credit buy)  │   Module    │ (post entry) │   Module │
└──────────┘               └──────┬──────┘              └──────────┘
                                  │
                    ┌─────────────┼─────────────┐
                    ▼             ▼             ▼
              ┌──────────┐ ┌──────────┐ ┌──────────┐
              │   Cash   │ │Inventory │ │   BC 01  │
              │  Module  │ │(CIP→FA)  │ │   /02/03 │
              └──────────┘ └──────────┘ └──────────┘
                 Payment        CIP          Report
                 for FA      transfer       FA data
```

## 12.2 Integration Contracts

### INT-FA-01: FA → GL (Posting Contract)

**Direction:** FA → GL (via JournalService)
**When:** Monthly depreciation run, disposal, upgrade, impairment, acquisition

| Field | Type | Required | Note |
|---|---|---|---|
| journal_type | string | YES | 'depreciation', 'acquisition', 'disposal', 'upgrade', 'impairment' |
| reference_type | string | YES | 'fixed_asset' |
| reference_id | string | YES | Asset code or transaction ID |
| lines[] | array | YES | Journal lines array |
| lines[].account_code | string | YES | Must be leaf account (not control) |
| lines[].is_debit | bool | YES | |
| lines[].amount | float | YES | > 0 |
| created_by | string | YES | User ID |
| description | string | YES | Vietnamese reason |

**Contract behavior:**
- Source: JournalService from other modules.
- FA module calls JournalService::createDraft() then JournalService::postEntry()
- Each journal must pass posting rules validation
- Must maintain Dr = Cr
- Must not post to control accounts (211, 214 = control? Check account mapping)

**Check:** Account 211 (TSCĐ hữu hình) and 214 (Hao mòn TSCĐ) — are they control accounts?
- 211: YES — has sub-accounts 2111 (buildings), 2112 (machinery), 2113 (vehicles), 2114 (office equipment), 2115 (trees/perennial crops), 2118 (other)
- 214: YES — has sub-accounts 2141 (tangible), 2142 (finance lease), 2143 (intangible)
- **FA module MUST post to sub-accounts** (e.g., 2112 for machinery, 2141 for tangible)

**Journal templates per transaction:**

| Transaction | Debit | Credit | Control OK? |
|---|---|---|---|
| Purchase acquisition | 2112 (machinery sub) | 112 | ❌ 211 → use 2112 |
| Monthly depreciation | 627/641/642 | 2141 | ❌ 214 → use 2141 |
| Disposal — remove NG | 2141 | 2112 | Both sub-accounts |
| Disposal — loss | 811 | — | |
| Disposal — gain | — | 711 | |
| Upgrade capitalize | 2112 | 112 | Sub-account |
| Impairment | 642 | 229 | 229 is not control |

### INT-FA-02: FA ↔ AP (Credit Purchase Contract)

**Direction:** AP → FA
**When:** Asset purchased on credit (Cr 331 instead of Cr 111/112)

**Flow:**
1. AP creates invoice with FA flag
2. System detects this is an asset purchase (≥ 30M, > 1 year)
3. FA module creates asset record with supplier info
4. Journal: Dr 2112, Dr 1332, Cr 331
5. When AP payment is made: Dr 331, Cr 112 (standard AP process)

**Data passed:**
- Supplier ID, invoice no, invoice date, NG amount, VAT amount, due date

### INT-FA-03: FA ↔ Cash (Payment Contract)

**Direction:** Cash → FA
**When:** Asset purchased with cash/bank transfer

**Flow:**
1. FA module records acquisition: Dr 2112, Dr 1332, Cr 111/112
2. No separate payment step needed — payment is implicit in the acquisition journal
3. Exception: deposit (đặt cọc) before delivery
   - Dr 331 (deposit to supplier), Cr 112
   - When asset arrives: Dr 2112, Dr 1332, Cr 331 (net off deposit + balance)

### INT-FA-04: FA ↔ Inventory (CIP Transfer Contract)

**Direction:** Inventory/Construction → FA
**When:** Self-constructed asset completed (241 → 211)

**Flow:**
1. Construction costs accumulated in TK 241 (CIP) over months
2. When construction complete: Biên bản nghiệm thu signed
3. Transfer: Dr 2112, Cr 241
4. Asset created with NG = total TK 241 accumulated cost
5. Depreciation starts next month

**Data passed:**
- TK 241 balance to transfer
- Cost breakdown by component (for NG decomposition)
- Completion certificate reference

### INT-FA-05: FA ↔ Period Close (Depreciation Run Contract)

**Direction:** Period Close → FA
**When:** Month-end closing

**Flow:**
1. Period Close initiates close workflow
2. Step: "Run depreciation" — calls FA module
3. FA module calculates depreciation + CCDC amortization for all assets
4. Posts journal to GL via JournalService
5. Generates Mẫu 06-TSCĐ
6. Verifies: FA register GL balance = total register
7. Returns status: OK or errors
8. Period Close proceeds if OK, blocks if errors

**Contract:**
```
Input:  period_id (YYYY-MM)
Output: {
  success: bool,
  depreciation_total: float,
  amortization_total: float,
  assets_processed: int,
  errors: string[],
  m06_pdf_url: string
}
```

### INT-FA-06: FA → BC 01/02/03 (Reporting Contract)

**Direction:** FA → FsService (Financial Statements)
**When:** BC 01, BC 02, BC 03 generation

**Data passed:**

| FS Report | Line Item | SQL Source |
|---|---|---|
| BC 01 MS 221 | NG TSCĐ hữu hình | SUM(ledger_entries amount) WHERE account_code LIKE '211%' AND is_debit = 1 |
| BC 01 MS 222 | HM lũy kế | SUM(ledger_entries amount) WHERE account_code LIKE '214%' AND is_debit = 0 |
| BC 01 MS 220 | GT còn lại | MS 221 - MS 222 |
| BC 01 MS 242 | CP trả trước (CCDC) | SUM(ledger_entries amount) WHERE account_code = '242' AND is_debit > 0 |
| BC 02 | Depreciation expense | SUM(ledger_entries) WHERE account_code IN (627, 641, 642) AND description LIKE '%khấu hao%' |

**Alternative source:** FA register total NG + total accum dep can be used as cross-check for BC 01 MS 220.

## 12.3 Module Boundary Rules

| Rule | Reason |
|---|---|
| FA module does NOT create users or permissions | Auth handled by central Auth module |
| FA module does NOT manage suppliers | Supplier master in AP module |
| FA module does NOT manage departments | Department master in HR/organization module |
| FA module does NOT manage accounts (chart of accounts) | AccountRepository is shared |
| FA module DOES manage FA register, depreciation schedules, disposal records | Core responsibility |
| FA module DOES generate its own journal entries | Via JournalService, not manual |
| FA module DOES generate TT 99 forms | PDF output on demand |
| FA module DOES NOT post directly to GL bypassing JournalService | FORBIDDEN — must go through JournalService |

---

# 13. Implementation Roadmap & Acceptance Criteria

## 13.1 Phase Gates

```
Phase 1: Core Acquisition + Depreciation (CURRENT — partial)
Phase 2: Full Acquisition Suite + Disposal
Phase 3: CCDC Full Lifecycle
Phase 4: Upgrade, Impairment, Revaluation
Phase 5: Reporting + TT 99 Forms
Phase 6: Approval Workflows + Integration
Phase 7: Multi-Branch + Advanced Features
```

### Phase 1 — Core Acquisition + Depreciation (DONE)

| Deliverable | Status | Acceptance |
|---|---|---|
| FixedAsset model | ✅ DONE | All fields: code, name, NG, useful life, method, accum dep, NBV |
| FixedAssetRepository + PDO | ✅ DONE | CRUD operations work |
| FixedAssetService.calculateMonthlyDepreciation | ✅ DONE | 3 methods: straight, declining, production |
| FixedAssetService.postMonthlyDepreciation | ✅ DONE | Batch journal via JournalService |
| FixedAssetController | ✅ DONE | CRUD REST endpoints |
| FA master data views | ✅ DONE | List, create, edit, view |
| FixedAssetServiceTest (16 tests) | ✅ DONE | Depreciation calculations verified |
| FixedAssetLifecycleTest (25 tests) | ✅ DONE | Acquisition + disposal scenarios verified |

**Total:** 41 tests covering Phase 1. 0 failures.

### Phase 2 — Full Acquisition Suite + Disposal (DONE)

| Deliverable | Status | Acceptance |
|---|---|---|
| recordAcquisition() — 5 types | ✅ DONE | purchase_cash, purchase_bank, purchase_credit, capital_contribution, gift |
| VAT splitting for all types | ✅ DONE | Dr 1332 for deductible VAT |
| recordDisposal() — liquidation + sale | ✅ DONE | Standard 3-step VN accounting |
| LifecycleController | ✅ DONE | acquire + dispose endpoints |
| Views: acquisition + disposal | ✅ DONE | Journal preview, account code fixes, category-dynamic FA acct |
| All posting rules pass | ✅ DONE | Dr = Cr verified for every transaction |
| Views: list / search | ✅ DONE | Client-side search, record count, pagination info |

### Phase 3 — CCDC Full Lifecycle (NOT STARTED)

| Deliverable | Priority | Acceptance |
|---|---|---|
| CCDC model (TK 153 + TK 242) | HIGH | Item, value, issue date, amortization period |
| CCDC issue to TK 242 | HIGH | Dr 242, Cr 153 |
| Monthly amortization batch | HIGH | Dr 627/641/642, Cr 242 |
| Prorated first month | MEDIUM | Days-based partial month |
| Early write-off | MEDIUM | Remaining balance to expense/loss |
| CCDC amortization schedule | MEDIUM | Monthly view, remaining balance |
| Change allocation period | LOW | Prospective recalculation |
| Reclassify CCDC → FA | LOW | Dr 211, Cr 242 + additional cost |

**Estimated effort:** 3-4 vertical slices of similar complexity to FA acquisition.

**Dependency:** CCDC needs a separate service (CcdcService) or extended FixedAssetService.

### Phase 4 — Upgrade, Impairment, Revaluation (NOT STARTED)

| Deliverable | Priority | Acceptance |
|---|---|---|
| Repair vs upgrade decision workflow | MEDIUM | Type selector → different JE |
| Upgrade capitalization | MEDIUM | Dr 211, recalculate depreciation prospectively |
| Impairment recognition | MEDIUM | Dr 642, Cr 229. CIT add-back tracking |
| Impairment reversal | LOW | Dr 229, Cr 642 (max original NBV) |
| Revaluation (full) | LOW | Dr 211, Cr 412 equity. Recalculate depreciation |

**Estimated effort:** 2-3 vertical slices.

### Phase 5 — Reporting + TT 99 Forms (NOT STARTED)

| Deliverable | Priority | Acceptance |
|---|---|---|
| Mẫu 05-TSCĐ — Asset card per asset | HIGH | Printable, includes full register data |
| Mẫu 06-TSCĐ — Monthly depreciation table | HIGH | Printed format, grouped by expense account |
| Mẫu 01-TSCĐ — Acquisition form | MEDIUM | Generated on asset creation |
| Mẫu 02-TSCĐ — Disposal form | MEDIUM | Generated on disposal |
| BC 01 cross-check report | HIGH | FA register NG vs GL balance |
| FA movement summary (year) | MEDIUM | Opening + additions - disposals = closing |
| Full-depreciated asset list | LOW | For physical count planning |

**Format:** HTML for screen, PDF for filing (via browser print-to-PDF).

### Phase 6 — Approval Workflows (NOT STARTED)

| Deliverable | Priority | Acceptance |
|---|---|---|
| Acquisition approval: amount threshold | HIGH | > 500M → Director approval required |
| Disposal Hội đồng workflow | HIGH | CEO + Chief Accountant + Storekeeper must approve |
| Depreciation schedule approval | MEDIUM | Chief Accountant reviews + approves monthly run |
| Upgrade/impairment approval | MEDIUM | Director approval for significant amounts |

**Approval states per transaction:** draft → pending → approved → rejected → posted

### Phase 7 — Multi-Branch + Advanced (NOT STARTED)

| Deliverable | Priority | Acceptance |
|---|---|---|
| Finance lease (TK 212) | LOW | VAS 06 standard, complex |
| Inter-branch asset transfer | LOW | Dr 211 branch B, Cr 211 branch A |
| CIP → FA (241 → 211) | LOW | Accumulated WIP to asset at completion |
| Physical count module | LOW | List assets, mark found/missing, reconcile |
| Tax limitation tracking (luxury car) | LOW | Two-track NG: accounting vs tax |

## 13.2 Priority Matrix

```
HIGH + MUST HAVE (Month 1):
  └─ CCDC lifecycle (Phase 3)
  └─ Mẫu 05 + 06 forms (Phase 5)
  └─ Acquisition + disposal view polish (Phase 2 gap) ✅ DONE
  └─ Approval workflows: acquisition + disposal (Phase 6)

MEDIUM + SHOULD HAVE (Month 2):
  └─ Upgrade capitalization (Phase 4)
  └─ Impairment (Phase 4)
  └─ BC 01 cross-check (Phase 5)
  └─ Depreciation schedule approval (Phase 6)

LOW + NICE TO HAVE (Month 3+):
  └─ Revaluation (Phase 4)
  └─ Finance lease (Phase 7)
  └─ CIP → FA transfer (Phase 7)
  └─ Inter-branch transfer (Phase 7)
  └─ Physical count module (Phase 7)
```

## 13.3 Acceptance Criteria (Definition of Done for Each Phase)

Every phase must meet all criteria below before marking complete:

```
[ ] All new service methods have test coverage:
    - Happy path: exact amounts verified
    - Failure case: each validation error tested
    - Accounting invariant: Dr = Cr for every journal
    - Edge case: empty list, zero amounts, negative amounts blocked
[ ] FA register total NG = GL TK 211 balance
[ ] FA register total accum dep = GL TK 214 balance
[ ] CCDC register total = GL TK 242 balance
[ ] All posting rules pass validation
[ ] No control account posting (211 → 2112, 214 → 2141)
[ ] Audit trail logged for every state change
[ ] Mẫu biểu generated correctly for each transaction type
[ ] Vietnamese messages throughout UI
[ ] All TT 45 useful life ranges enforced
[ ] Period lock: cannot post to closed period
[ ] Performance: depreciation run for 500 assets < 5 seconds
[ ] 0 new test failures in full suite
[ ] No breaking change to existing API
```

## 13.4 Risk Monitoring During Implementation

| Risk | Mitigation | Check Frequency |
|---|---|---|
| Phase 1 tests break | Run full suite before every commit | Every change |
| CCDC design overlaps with FA | Keep separate service or extend FA service? Decide early | Before Phase 3 start |
| Form templates delay Phase 5 | Use HTML print-to-PDF approach, not PDF library | Architecture decision |
| Accountants reject views | Prototype + UAT with real accountant | Before each phase gate |
| Approval workflow scope creep | Keep first version: simple approve/reject. No complex routing | During Phase 6 |
| Tax rule changes | Peg to Circular 99/2025. Track TT BTC updates annually | Annual review |

---

> **Final word from 20k-hour Chief Accountant:**
> Fixed assets and CCDC are the #1 source of audit adjustments in Vietnamese SMEs. Not revenue, not cash — but assets. Because every month, 365 days a year, depreciation must be calculated correctly. One formula error compounds for years. A proper FA engine is not a luxury — it's the difference between a clean audit opinion and a qualified one. Build it right, build it once, and the accountant will sleep well every month-end.
