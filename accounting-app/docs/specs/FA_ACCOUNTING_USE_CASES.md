# Use Case Specification: Fixed Asset Accounting (TT 99)

## 1. Sources

- [Tài sản cố định là gì? Phân loại TSCĐ](https://ketoanthienung.net/tai-san-co-dinh-la-gi.htm)
- [TSCĐ hữu hình — tiêu chuẩn, nguyên giá, phân loại](https://ketoanthienung.net/tai-san-co-dinh-huu-hinh.htm)
- [TSCĐ vô hình — tiêu chuẩn, nguyên giá, phân loại](https://ketoanthienung.net/tai-san-co-dinh-vo-hinh.htm)
- [TSCĐ thuê tài chính — nguyên giá, hạch toán chi tiết](https://ketoanthienung.net/tai-san-co-dinh-thue-tai-chinh.htm)
- [Đầu tư nâng cấp, sửa chữa TSCĐ](https://ketoanthienung.net/dau-tu-nang-cap-sua-chua-tai-san-co-dinh.htm)
- [Nguyên tắc kế toán TSCĐ, BĐSĐT và CP XDCB dở dang theo TT 99](https://ketoanthienung.net/nguyen-tac-ke-toan-tai-san-co-dinh-theo-thong-tu-99.htm)
- [Mẫu chứng từ kế toán TSCĐ theo TT 99](https://ketoanthienung.net/mau-chung-tu-ke-toan-tscd-theo-thong-tu-99.htm)
- Existing codebase: COA (`data/coa_circular_99.json`), migrations 015/019/038/043, CRUD controllers, views, sidebar, route config

### Key Definitions (TT 45/2013/TT-BTC + TT 99/2025/TT-BTC)

| Term | Definition |
|---|---|
| TSCĐ hữu hình | Tangible means of labor, physical form, participates in multiple business cycles, retains original physical form |
| TSCĐ vô hình | Intangible assets, no physical form, represents invested value meeting intangible FA criteria |
| Nguyên giá (NG) | All costs incurred to acquire the FA up to the point it is ready for use |
| Hao mòn | Gradual decrease in use value and asset value due to participation in operations, natural wear, technical progress |
| Khấu hao | Systematic allocation of NG into production/business costs over the depreciation period |
| Giá trị còn lại (NBV) | NG minus accumulated depreciation/amortization at reporting date |
| Sửa chữa | Maintenance, repair, replacement to restore to original standard operating condition |
| Nâng cấp | Improvement, expansion, upgrade to increase capacity, quality, features, or extend useful life |
| Giá trị hợp lý | Amount at which an asset could be exchanged between knowledgeable, willing parties in an arm's length transaction |

### FA Recognition Criteria (TT 45 Art. 3)

All three must be met simultaneously:
1. Probable future economic benefits from using the asset
2. Useful life > 1 year
3. Cost reliably determined and ≥ 30,000,000 VND

---

## 2. Domain Breakdown

### Domain: FA Classification & Master Data

#### FA-UC-01: Classify FA by Type (7 Categories per TT 45/147)

- **Goal:** Categorize fixed assets into regulatory classification for management and reporting
- **Actors:** Accountant
- **Preconditions:** FA record exists
- **Trigger:** FA creation or reclassification
- **Main Flow — Tangible FA (TK 211):**
  1. Loại 1 — Nhà cửa, vật kiến trúc: buildings, structures (office, warehouse, fence, water tower, roads, bridges)
  2. Loại 2 — Máy móc, thiết bị: machinery, equipment (specialized machinery, production line, cranes, drilling rigs)
  3. Loại 3 — Phương tiện vận tải, thiết bị truyền dẫn: transport vehicles (rail, road, water, air, pipeline), transmission equipment (IT systems, electrical systems, water pipes, conveyor belts, gas pipelines)
  4. Loại 4 — Thiết bị, dụng cụ quản lý: management equipment (computers, electronic devices, measuring instruments, quality control tools)
  5. Loại 5 — Vườn cây lâu năm, súc vật làm việc và/hoặc cho sản phẩm: perennial plantations (coffee, tea, rubber, fruit trees), working/producing animals (elephants, horses, buffalo, cattle)
  6. Loại 6 — Kết cấu hạ tầng: infrastructure assets (irrigation works, industrial zone infrastructure, railway/urban rail infrastructure)
  7. Loại 7 — Các loại TSCĐ khác: other FA not listed above
- **Alternate Flow — Intangible FA (TK 213):** Land use rights, IP rights (patents, copyrights, trademarks, industrial designs), software, internally generated intangibles
- **Output:** FA with classification type assigned

#### FA-UC-02: Manage Fixed Asset Master Records

- **Goal:** Create, update, list, delete fixed asset master records
- **Actors:** Accountant
- **Preconditions:** COA loaded
- **Trigger:** Asset acquisition or master data correction
- **Main Flow:**
  1. User enters: code, name, purchase_date, original_cost, fa_type (211/212/213)
  2. User selects: depreciation_method (straight_line / declining_balance / sum_of_years / production), fa_category (Loại 1-7 for tangible, or specific intangible subtype)
  3. User enters: useful_life (months), salvage_value, department_id, employee_id, location, notes
  4. System calculates: monthly_depreciation
  5. System sets: accumulated_depreciation = 0, net_book_value = original_cost, status = "in_use"
- **Alternative Flow - Asset revaluation:** Update NG from revaluation event; recalculate monthly_depreciation prospectively
- **Alternative Flow - Asset upgrade:** Increase NG by capitalized improvement cost; recalculate monthly_depreciation prospectively
- **Output:** Fixed asset record persisted
- **Dependencies:** Depreciation policy master (FA-UC-03)
- **Status:** ✅ CRUD exists. Depreciation calculation NOT implemented.

#### FA-UC-03: Manage Depreciation Policy Master Records

- **Goal:** Define depreciation policy templates for reuse
- **Actors:** Accountant
- **Main Flow:**
  1. User enters: code, name, method (straight_line / declining_balance / sum_of_years / production)
  2. User enters: default_life (months), default_salvage_rate (% of NG)
  3. System saves policy for reference when creating new FA records
- **Output:** Depreciation policy record
- **Status:** ✅ CRUD exists. Policy not yet linked to depreciation calculation.

---

### Domain: FA Acquisition / Recognition (Ghi tăng TSCĐ)

#### FA-UC-04: Purchase FA for Cash — Tangible (TK 211)

- **Goal:** Record FA purchased and paid immediately
- **Actors:** Accountant
- **Preconditions:** Supplier invoice + goods received
- **Trigger:** Payment completed + Biên bản giao nhận TSCĐ (01-TSCĐ)
- **Main Flow:**
  1. NG = purchase price + transport + installation + testing + registration fee - trade discounts
  2. Dr 211 (NG), Dr 1332 (VAT, if deductible), Cr 111/112
- **Alternate Flow — Import:** Dr 211 (incl. import duty, non-refundable taxes), Dr 1332 (if VAT deductible on import), Cr 112, Cr 3333 (import duty)
- **Alternate Flow — Used FA purchased:** Same treatment; NG = actual price paid + direct costs to ready for use
- **Alternate Flow — Purchase bundled with free items (Principle #4):**
  - Allocate purchase price between main FA and bundled items at fair value
  - Dr 211 (allocated to FA), Dr 152/153/242 (bundled items), Dr 1332, Cr 112
  - Exception: specialized spare parts only usable with this FA → no separate recognition, tracked off-balance-sheet
- **Alternate Flow — Land + building purchase:** Separate land use rights (TK 213) from building (TK 211)
  - Land use rights → TSCĐ vô hình if criteria met
  - Building → TSCĐ hữu hình at building cost
  - If existing building demolished: land use rights recognized separately (TK 213); new construction cost → NG of new building
- **Output:** Journal entry + FA record

#### FA-UC-05: Purchase FA on Credit (TK 211, Cr 331)

- **Goal:** Record FA purchased with deferred payment
- **Actors:** Accountant
- **Main Flow:**
  1. Dr 211 (NG), Dr 1332, Cr 331
  2. When paid: Dr 331, Cr 111/112
- **Output:** Journal entry

#### FA-UC-06: Purchase FA via Installment / Deferred Payment (TT 45 Art. 4)

- **Goal:** Record FA purchased with installment payment
- **Actors:** Accountant
- **Main Flow:**
  1. NG = cash-equivalent price at purchase date + direct costs
  2. Dr 211 (cash price), Dr 1332, Cr 331 (total payable)
  3. Difference between total payable and cash price = interest expense
  4. Periodically: Dr 635 (interest expense), Cr 331
- **Alternate Flow — Implicit interest (no rate stated, tenor > 12 months):**
  - Compute implicit rate; amortize interest using effective interest method
- **Output:** Journal entries per period + FA record at cash-equivalent NG

#### FA-UC-07: Acquire FA via Exchange (TT 45 Art. 4.2)

- **Goal:** Record FA acquired through exchange of non-similar asset
- **Actors:** Accountant
- **Main Flow — Non-similar exchange:**
  1. NG = fair value of asset received (or fair value of asset given up ± cash adjustment) + direct costs + non-refundable taxes
  2. Derecognize asset given up; recognize gain/loss on exchange
- **Main Flow — Similar exchange:**
  1. NG = carrying value of asset given up (no gain/loss recognized)
- **Output:** Journal entry

#### FA-UC-08: Self-Constructed / Self-Built FA (Tự xây dựng, tự sản xuất)

- **Goal:** Capitalize internally constructed asset from CIP (TK 241)
- **Actors:** Accountant
- **Preconditions:** Construction completed, CIP costs accumulated in TK 241
- **Trigger:** Completion certificate + Biên bản giao nhận TSCĐ (01-TSCĐ)
- **Main Flow:**
  1. Accumulate costs in TK 241 during construction:
     - Dr 241 (all direct costs: materials, labor, overhead, borrowing costs)
     - Cr 152/334/331/111/112 etc.
  2. On completion, NG = final settlement value (or provisional value if settlement pending, adjusted later)
  3. Dr 211, Cr 241
- **Alternate Flow — Self-produced FA:** NG = actual production cost + installation/testing costs - internal profit - recoverable by-products - abnormal waste
- **Output:** Journal entry + FA record

#### FA-UC-09: Acquire FA via Capital Contribution / Receive Back Capital (TT 45 Art. 4.7)

- **Goal:** Record FA received as capital contribution
- **Actors:** Accountant
- **Preconditions:** Appraisal report or agreed valuation by members/shareholders
- **Main Flow:**
  1. Dr 211 (at appraised/agreed value), Cr 411 (owner's equity)
  2. If appraisal cost incurred: Dr 211, Cr 111/112
- **Exception:** If appraisal value overstated intentionally → legal liability per Principle #6
- **Output:** Journal entry + FA record

#### FA-UC-10: Acquire FA via Allocation / Transfer (Điều chuyển) (TT 45 Art. 4.6)

- **Goal:** Record FA received from parent/sister entity
- **Actors:** Accountant
- **Main Flow:**
  1. NG = carrying value at transferring entity + direct costs incurred by receiving entity (appraisal, transport, installation)
  2. Dr 211, Dr 214 (accum. deprec. received), Cr 411 (equity) or Cr 336 (payable to related party)
- **Output:** Journal entry + FA record

#### FA-UC-11: Receive FA as Gift / Donation / Grant (TT 45 Art. 4.5)

- **Goal:** Record donated FA
- **Actors:** Accountant
- **Main Flow:**
  1. NG = appraised value by receiving committee or professional valuer
  2. Dr 211 (appraised value), Cr 711 (other income)
  3. If donation-related costs: Dr 211, Cr 111/112
- **Alternate Flow — Surplus discovered during physical count:** Same treatment as gift
- **Output:** Journal entry + FA record

#### FA-UC-12: Acquire FA via Finance Lease (TK 212) (TT 99 TK 212)

- **Goal:** Record FA acquired through finance lease
- **Actors:** Accountant
- **Preconditions:** Finance lease contract meeting ≥1 of 3 conditions (cancellation penalty, residual value risk/reward transfer, below-market renewal option)
- **Main Flow — Initial direct costs before receipt:**
  1. Dr 242 (deferred costs), Cr 111/112
- **Main Flow — Prepaid rent / security deposit:**
  1. Dr 3412 (prepaid principal), Dr 244 (deposit), Cr 111/112
- **Main Flow — Initial recognition (lower of fair value or PV of min lease payments):**
  1. NG = lower of (fair value, PV of min lease payments) + direct costs (excluding VAT)
  2. Dr 212 (NG, excluding VAT), Cr 3412
  3. Dr 212 (transfer deferred direct costs), Cr 242
- **Main Flow — Periodic payment:**
  1. Dr 635 (interest portion), Dr 3412 (principal portion), Cr 111/112
- **Main Flow — VAT treatment:**
  1. If VAT deductible: Dr 1332, Cr 111/112/338
  2. If VAT non-deductible and paid upfront: add to NG (Dr 212)
  3. If VAT non-deductible and paid periodically: Dr 627/641/642 (same line as depreciation)
- **Main Flow — Commitment fee:**
  1. Dr 635, Cr 111/112
- **Main Flow — Return asset at lease end:**
  1. Dr 2142 (accum. deprec.), Cr 212 (NG)
- **Main Flow — Purchase option exercised:**
  1. Dr 211, Cr 212 (NG)
  2. Dr 2142, Cr 2141 (transfer accum. deprec.)
  3. Additional payment: Dr 211, Cr 111/112
- **Alternate Flow — Sale-and-leaseback (finance lease):**
  1. Sale: recognize gain/loss per TK 711/811 rules
  2. Leaseback: Dr 212, Cr 3412
  3. Depreciation: Dr 627/641/642, Cr 2142
  4. Deferred gain: Dr 3387, Cr 627/641/642 (amortized over lease term)
  5. Deferred loss: Dr 242, Cr 627/641/642 (amortized over lease term)
- **Output:** Journal entry + FA record (type = 212, TK 212)
- **Note:** Finance lease depreciation policy = own-asset policy; if purchase not certain, depreciate over lease term if shorter

#### FA-UC-13: Transfer from CIP to FA (XDCB hoàn thành bàn giao)

- **Goal:** Capitalize completed construction project
- **Actors:** Accountant
- **Preconditions:** CIP fully accumulated in TK 2411/2414
- **Trigger:** Biên bản giao nhận TSCĐ (01-TSCĐ)
- **Main Flow:**
  1. Dr 211, Cr 2411/2414 (transfer finalized construction cost)
  2. If provisional settlement: use provisional NG, adjust when final settlement available
- **Output:** Journal entry + FA record

#### FA-UC-14: Recognize Internally Generated Intangible Asset (TT 45 Art. 4.4)

- **Goal:** Capitalize development phase costs meeting 7 conditions
- **Actors:** Accountant
- **Preconditions:** Technical feasibility, intention to complete, ability to use/sell, future economic benefits, sufficient resources, costs reliably measurable, meets FA criteria (>1 year, ≥30M)
- **Main Flow:**
  1. Research phase costs → expense (Dr 642, Cr 111/112)
  2. Development phase costs meeting all 7 conditions → capitalize
  3. Dr 213, Cr 154/241/111/112
- **Note:** Internally generated brands, customer lists, research costs → always expense
- **Output:** Journal entry + FA record (type = 213 intangible)

#### FA-UC-15: Recognize Land Use Rights as Intangible (TK 213) (TT 45 Art. 4.5)

- **Goal:** Record land use rights meeting intangible FA criteria
- **Actors:** Accountant
- **Main Flow — Recognized as TSCĐ vô hình:**
  1. Land allocated by State with payment, or transferred use rights with certificate (perpetual or term)
  2. Land leased pre-2003 with prepaid rent ≥5 years remaining + certificate
  3. NG = total payment for use rights + compensation/clearance/leveling + registration tax
- **Main Flow — NOT recognized as TSCĐ vô hình:**
  1. State allocation without payment → off-balance sheet tracking
  2. Post-2003 lease with single prepayment but no certificate → amortize over lease term (Dr 242)
  3. Annual lease payment → expense directly (Dr 642, Cr 111/112)
- **Alternate Flow — Mixed-use building (part office, part rental, part for sale) (TT 28/2017):**
  1. Allocate by area percentage or cost proportion
  2. Portion for own use + rental (non-finance lease) → FA (Dr 211/213, Cr 241)
  3. Portion for sale → inventory (Dr 156), NOT FA, no depreciation
  4. Cannot separate → NOT FA (entire building excluded from FA treatment)
  5. Common areas (lobby, parking, elevators): allocate proportionally
- **Output:** Journal entry + FA record

---

### Domain: FA Depreciation (Trích khấu hao TSCĐ)

#### FA-UC-16: Calculate Monthly Depreciation — Straight-Line

- **Goal:** Compute monthly depreciation using straight-line method
- **Actors:** System (scheduled), Accountant (manual trigger)
- **Preconditions:** FA exists with NG, useful_life, salvage_value, method = "straight_line"
- **Trigger:** Monthly depreciation run
- **Main Flow:**
  1. Monthly depreciation = (NG - salvage_value) / useful_life_months
  2. Month of acquisition: pro-rate from in-service date
  3. Month of disposal: full month (Vietnamese practice: no pro-ration in disposal month)
  4. Month of full depreciation: stop when accum. deprec. = NG - salvage_value
- **Output:** Calculated amount per FA

#### FA-UC-17: Calculate Monthly Depreciation — Declining Balance

- **Goal:** Compute depreciation using declining balance method
- **Actors:** System (scheduled), Accountant (manual trigger)
- **Main Flow:**
  1. Annual rate = (1/useful_life_years) × coefficient
  2. Coefficient: 2 if life ≤ 4 years, 2.5 if ≤ 6 years, 3 if > 6 years
  3. Monthly depreciation = NBV × annual_rate / 12
  4. Switch to straight-line when SL ≥ DB amount (final years)
- **Output:** Calculated amount per FA

#### FA-UC-18: Calculate Monthly Depreciation — Sum of Years Digits

- **Goal:** Compute depreciation using sum-of-years-digits method
- **Main Flow:**
  1. Sum of years = n(n+1)/2 where n = useful_life_years
  2. Year Y depreciation = (NG - salvage_value) × remaining_life_at_start / sum_of_years
  3. Monthly = year_amount / 12
- **Output:** Calculated amount per FA

#### FA-UC-19: Calculate Monthly Depreciation — Production / Output Method

- **Goal:** Compute depreciation based on actual production volume
- **Main Flow:**
  1. Per-unit rate = (NG - salvage_value) / total_estimated_units
  2. Monthly depreciation = actual_units × unit_rate
- **Output:** Calculated amount per FA

#### FA-UC-20: Post Monthly Depreciation Journal Entry

- **Goal:** Record monthly depreciation expense and accumulated depreciation
- **Actors:** System (scheduled)
- **Preconditions:** Depreciation amounts calculated for all active FAs
- **Trigger:** Monthly closing
- **Main Flow:**
  1. Dr 627/623/641/642 (depending on cost center / usage department)
  2. Cr 2141/2142/2143 (accumulated depreciation matching FA type)
  3. Update FA.accumulated_depreciation += monthly_amount
  4. Update FA.net_book_value = FA.original_cost - FA.accumulated_depreciation
- **Alternate Flow — Fund-sourced FA (Principle #2):**
  - If FA formed from welfare fund (353) or science & tech fund (356):
  - Dr 627/641/642, Cr 214 (normal depreciation)
  - AND Dr 3533/3562 (reduce fund), Cr 411 (increase equity)
- **Output:** Journal entry + FA record update
- **Dependencies:** FA-UC-16/17/18/19, cost center allocation

#### FA-UC-21: Allocate Depreciation by Cost Center (Mẫu 06-TSCĐ)

- **Goal:** Allocate depreciation across cost centers/departments
- **Actors:** Accountant
- **Trigger:** Monthly depreciation run
- **Main Flow:**
  1. Each FA is assigned to a department (department_id)
  2. System maps department to cost center (627/641/642/623)
  3. Bảng tính và phân bổ khấu hao TSCĐ (06-TSCĐ) structure:
     - Column 1: Total enterprise
     - Columns: TK 627 (by production dept), TK 623, TK 641, TK 642, TK 241, TK 242, TK 335...
     - Row I: Prior month depreciation
     - Row II: Increase this month (new FAs or re-activated)
     - Row III: Decrease this month (disposed or fully depreciated)
     - Row IV: Current month (I + II - III)
- **Output:** Depreciation allocation schedule + journal entries

#### FA-UC-22: Adjust Depreciation Mid-Life

- **Goal:** Adjust depreciation when useful_life, salvage_value, or NG changes
- **Actors:** Accountant
- **Trigger:** Revaluation, upgrade, or policy change
- **Main Flow:**
  1. Remaining depreciable amount = NBV - salvage_value (new)
  2. Remaining months = useful_life (new) - months_already_depreciated
  3. New monthly depreciation = remaining_depreciable_amount / remaining_months (prospective)
- **Output:** Updated monthly depreciation going forward

---

### Domain: FA Revaluation (Đánh giá lại TSCĐ)

#### FA-UC-23: Revaluation Increase (Mẫu 04-TSCĐ)

- **Goal:** Record increase in FA fair value
- **Actors:** Appraisal committee, Accountant
- **Preconditions:** Board decision, qualified appraiser report
- **Trigger:** Periodic or event-driven revaluation
- **Main Flow:**
  1. Revalue NG and accumulated_depreciation proportionally
  2. Dr 211 (increase in NG), Cr 2141 (increase in accum. deprec.)
  3. Net increase → Dr 211, Cr 412 (revaluation surplus)
- **Output:** Journal entry + Biên bản đánh giá lại TSCĐ (04-TSCĐ) + FA update

#### FA-UC-24: Revaluation Decrease

- **Goal:** Record decrease in FA fair value
- **Actors:** Appraisal committee, Accountant
- **Main Flow:**
  1. Dr 412 (if revaluation surplus exists for this asset)
  2. Dr 632/811 (if decrease exceeds prior surplus)
  3. Cr 211 (reduce NG proportionally), Cr 2141 (reduce accum. deprec.)
- **Output:** Journal entry + 04-TSCĐ + FA update

#### FA-UC-25: Derecognize Revaluation Surplus on Disposal

- **Goal:** Transfer remaining 412 balance to retained earnings when asset disposed
- **Main Flow:**
  1. Dr 412 (revaluation surplus for disposed asset)
  2. Cr 421 (retained earnings — not recycled through P&L)
- **Output:** Journal entry

---

### Domain: FA Impairment (Tổn thất tài sản)

#### FA-UC-26: Recognize FA Impairment

- **Goal:** Record impairment when recoverable amount < carrying value
- **Actors:** Accountant
- **Trigger:** Impairment indicators (physical damage, obsolescence, market decline)
- **Main Flow:**
  1. Recoverable amount = max(fair value - costs to sell, value in use)
  2. If recoverable amount < NBV: impairment loss = NBV - recoverable amount
  3. Dr 632 (impairment loss), Cr 2293 (impairment provision for TSCĐ)
- **Alternate Flow — Revalued asset:** Dr 412 first (reduce surplus), then Dr 632
- **Alternate Flow — Reversal:** Dr 2293, Cr 632 (up to original impairment)
- **Output:** Journal entry + FA record

---

### Domain: FA Repair & Upgrade (Sửa chữa, nâng cấp TSCĐ)

#### FA-UC-27: Regular Maintenance / Minor Repair

- **Goal:** Expense routine maintenance costs (TT 45 Art. 7)
- **Actors:** Accountant
- **Main Flow:**
  1. Dr 627/641/642 (maintenance expense)
  2. Dr 1331 (VAT, if deductible)
  3. Cr 111/112/331
- **Output:** Journal entry

#### FA-UC-28: Major Repair — Capitalize or Defer (TT 45 Art. 7.2)

- **Goal:** Handle major repair costs that restore but do not enhance
- **Actors:** Accountant
- **Preconditions:** Biên bản bàn giao TSCĐ sửa chữa hoàn thành (03-TSCĐ)
- **Main Flow — Direct expense:**
  1. Dr 627/641/642, Cr 152/334/111/112
- **Alternate Flow — Defer and amortize (if large amount):**
  1. Dr 242, Cr 152/334/111/112
  2. Amortize monthly: Dr 627/641/642, Cr 242
  3. Max amortization period: 3 years (TT 45 Art. 7.2)
- **Alternate Flow — Provision for cyclical repairs:**
  1. Accrue in advance: Dr 627/641/642, Cr 335 (accrued expense)
  2. When actual repair done: Dr 335, Cr 152/334/111/112
  3. Adjust at year-end if actual ≠ provision
- **Output:** Journal entry + 03-TSCĐ

#### FA-UC-29: Upgrade / Improvement — Capitalize (TT 45 Art. 7.1)

- **Goal:** Capitalize improvement costs that increase future economic benefits
- **Actors:** Accountant
- **Preconditions:** Biên bản bàn giao nâng cấp hoàn thành (03-TSCĐ)
- **Main Flow — Tangible FA:**
  1. Accumulate costs: Dr 2414, Cr 152/334/111/112
  2. On completion: Dr 211 (increase NG by upgrade cost), Cr 2414
  3. Recalculate monthly depreciation prospectively (FA-UC-22)
- **Main Flow — Intangible FA (TT 45 Art. 7.3):**
  1. Capitalize only if: cost reliably measured AND increases future economic benefits beyond original
  2. Otherwise: expense immediately
- **Output:** Journal entry + 03-TSCĐ + FA update

---

### Domain: FA Disposal (Giảm TSCĐ — Thanh lý, Nhượng bán)

#### FA-UC-30: Dispose / Liquidate Fully Depreciated FA (Mẫu 02-TSCĐ)

- **Goal:** Remove fully depreciated asset from books
- **Actors:** Accountant
- **Preconditions:** NBV ≈ 0, liquidation decision approved
- **Main Flow:**
  1. Dr 2141 (full accumulated depreciation), Cr 211 (full NG)
  2. If proceeds: Dr 111/112, Cr 711
  3. If costs: Dr 811, Cr 111/112
- **Output:** Journal entry + 02-TSCĐ + FA status = "liquidated"

#### FA-UC-31: Dispose / Liquidate Partially Depreciated FA

- **Goal:** Remove asset with remaining NBV, recognize gain/loss
- **Actors:** Accountant
- **Preconditions:** Board decision, 02-TSCĐ
- **Main Flow:**
  1. Dr 2141 (accumulated depreciation), Dr 811 (loss) or Cr 711 (gain)
  2. Cr 211 (full NG)
  3. Dr 111/112 (proceeds net of VAT), Cr 711, Cr 3331 (VAT output)
  4. Dr 811 (liquidation costs), Cr 111/112
- **Output:** Journal entry + 02-TSCĐ + FA status = "liquidated"

#### FA-UC-32: Sell FA (Nhượng bán TSCĐ)

- **Goal:** Record sale of FA
- **Actors:** Accountant
- **Main Flow:** Same as FA-UC-31
- **Note:** Proceeds subject to 10% output VAT on selling price
- **Output:** Journal entry + FA removed

---

### Domain: FA Transfer (Điều chuyển TSCĐ)

#### FA-UC-33: Inter-Department Transfer

- **Goal:** Transfer FA between departments within same enterprise
- **Actors:** Accountant
- **Main Flow:**
  1. Update department_id on FA record
  2. No P&L impact
  3. Future depreciation reallocated to new department's cost center
- **Output:** FA record update

#### FA-UC-34: Transfer FA to Subsidiary (as Capital Contribution)

- **Goal:** Record FA transferred as investment in subsidiary
- **Actors:** Accountant
- **Main Flow:**
  1. Dr 221 (at appraised value), Dr 2141 (accum. deprec.)
  2. Cr 211 (original NG)
  3. Dr 811 (if appraised < NBV) or Cr 711 (if appraised > NBV)
- **Output:** Journal entry

---

### Domain: FA Inventory / Physical Count (Kiểm kê TSCĐ)

#### FA-UC-35: Physical Count — Surplus (Mẫu 05-TSCĐ)

- **Goal:** Record FA found in physical count but not in books
- **Actors:** Accountant, Inventory committee
- **Trigger:** Periodic physical count
- **Main Flow:**
  1. If cause identified: Dr 211 (at estimated value), Cr 711
  2. If cause unknown: Dr 211 (at estimated value), Cr 3381 (pending)
  3. When resolved: Dr 3381, Cr 711/411/...
- **Output:** Journal entry + 05-TSCĐ

#### FA-UC-36: Physical Count — Shortage

- **Goal:** Record FA missing in physical count
- **Actors:** Accountant, Inventory committee
- **Main Flow:**
  1. Dr 2141 (accum. deprec.), Dr 1381 (NBV if cause unknown)
  2. Cr 211 (original NG)
  3. If responsible party: Dr 1388/334, Cr 1381
  4. If no responsible party: Dr 632, Cr 1381
- **Output:** Journal entry + 05-TSCĐ

---

### Domain: FA Reporting (Báo cáo TSCĐ)

#### FA-UC-37: Depreciation Schedule Report (Mẫu 06-TSCĐ)

- **Goal:** Produce monthly depreciation schedule per Mẫu 06-TSCĐ format
- **Actors:** Accountant
- **Trigger:** Monthly, quarterly, annually
- **Main Flow:**
  1. List all active FAs
  2. For each FA: NG, accum. deprec. opening, current month, closing, NBV
  3. Group by FA category (211/212/213)
  4. Show allocation across cost centers (627/623/641/642/241/242/335)
- **Output:** Report / data for view

#### FA-UC-38: FA Increase / Decrease Movement Report

- **Goal:** Report FA movements during period
- **Actors:** Accountant
- **Trigger:** Monthly, quarterly, annually
- **Main Flow:**
  1. Opening balance (NG, accum. deprec., NBV)
  2. + Increases (by source: purchase, self-construction, capital contribution, gift, finance lease)
  3. - Decreases (by type: disposal, liquidation, transfer)
  4. = Closing balance
- **Output:** Report data

#### FA-UC-39: BC 01 220-series Integration

- **Goal:** Provide FA data for BC 01 line items (MS 220 series)
- **Actors:** System
- **Trigger:** BC 01 generation
- **Main Flow:**
  1. MS 220 = total FA; MS 221 = MS 222 + MS 223; MS 224 = MS 225 + MS 226; MS 227 = MS 228 + MS 229
- **Note:** BC01 line items (migration 038) are populated from account balances. Already correct.
- **Output:** BC 01 FA section data

#### FA-UC-40: FA Disclosure Notes (Thuyết minh BCTC)

- **Goal:** Provide FA disclosure data for BCTC notes
- **Actors:** Accountant
- **Main Flow:**
  1. Opening / closing balances (NG, accum. deprec., NBV) by category
  2. Increases by source, decreases by type
  3. Depreciation methods + useful life ranges by category
  4. Impairment losses recognized/reversed
  5. FA used as collateral
  6. CIP balance
  7. Finance lease commitments
  8. Revaluation surplus details
- **Output:** Disclosure data

#### FA-UC-41: BC 03 Integration (FA-related lines)

- **Goal:** Provide FA cash flow data for BC 03 (MS 02, 21, 22)
- **Actors:** System
- **Trigger:** BC 03 generation
- **Note:** Existing BC 03 line items in migration 043. Validation needed.
- **Output:** BC 03 FA section data

---

## 3. Voucher Templates (Phụ lục I — TT 99)

| Mẫu số | Tên chứng từ | Domain | UC Reference |
|---|---|---|---|
| 01-TSCĐ | Biên bản giao nhận TSCĐ | Acquisition / CIP transfer / Donation / Capital contribution | FA-UC-04 → FA-UC-15 |
| 02-TSCĐ | Biên bản thanh lý TSCĐ | Disposal / Liquidation | FA-UC-30, FA-UC-31 |
| 03-TSCĐ | BB bàn giao TSCĐ sửa chữa, nâng cấp, cải tạo hoàn thành | Repair / Upgrade | FA-UC-28, FA-UC-29 |
| 04-TSCĐ | Biên bản đánh giá lại TSCĐ | Revaluation | FA-UC-23, FA-UC-24 |
| 05-TSCĐ | Biên bản tổng hợp kiểm kê TSCĐ | Physical count | FA-UC-35, FA-UC-36 |
| 06-TSCĐ | Bảng tính và phân bổ khấu hao TSCĐ | Depreciation allocation | FA-UC-16-21, FA-UC-37 |

---

## 4. Cross-Use Case Observations

### Overlapping UCs

| UC | Overlaps With | Note |
|---|---|---|
| FA-UC-04 (Purchase cash) | FA-UC-05 (Credit) | Same recognition, different AP treatment |
| FA-UC-30 (Fully deprec.) | FA-UC-31 (Partial) | Same process, different P&L impact |
| FA-UC-09 (Capital in) | FA-UC-34 (Capital out) | Same valuation principle, mirrored Dr/Cr |
| FA-UC-23 (Reval increase) | FA-UC-24 (Reval decrease) | Shared 412 surplus tracking |
| FA-UC-16 → FA-UC-20 | Calculation → Posting | Calculation feeds into posting entry |
| FA-UC-28 (Repair) | FA-UC-29 (Upgrade) | Threshold determines capitalize vs expense |
| FA-UC-12 (Finance lease) | FA-UC-04 (Purchase) | Both require NG determination + depreciate; different accounts (212 vs 211) |
| FA-UC-14 (Internal intangible) | FA-UC-08 (Self-construct) | Both capitalize internally created value; different asset types |

### Shared Dependencies

| Dependency | Used By |
|---|---|
| FixedAsset record (FA-UC-02) | All UCs |
| Depreciation calculation (FA-UC-16/17/18/19) | FA-UC-20 (posting), FA-UC-21 (allocation), FA-UC-37 (report) |
| Journal Service | All UCs producing journal entries |
| Period Service | FA-UC-20 (monthly posting), FA-UC-21 (allocation) |
| Cash/Bank module | FA-UC-04/05/06 (payment), FA-UC-30/31/32 (proceeds) |

### Missing Flows

- **FA temporary idle (tạm ngừng sử dụng):** Vietnamese practice: still depreciate unless regulations exempt
- **FA reclassification between types:** e.g., finance lease → own asset on purchase option exercise (covered in FA-UC-12)
- **FA insurance proceeds:** asset destroyed by fire → insurance claim receivable

---

## 5. Gaps Identified

### Existing (Already in Codebase)

1. **FA + Depreciation Policy Master Data CRUD** — ✅ models, repos, controllers, views, routes, DI
2. **COA accounts** — ✅ 211/212/213/2141/2142/2143/2147/2293/2411/2413/2414, 1332, 3533, 3562
3. **BC 01 FA line items** — ✅ MS 220-229 in migration 038
4. **BC 03 FA line items** — ✅ MS 02, 21, 22 in migration 043
5. **Sidebar** — ✅ 6 links; 4 operation links all point to `#`

### Missing — Must Build

| # | Gap | Affected UCs | Priority |
|---|---|---|---|
| G1 | **No FixedAssetService** — business logic layer | All | Critical |
| G2 | **No depreciation calculation engine** (all 4 methods) | FA-UC-16/17/18/19 | Critical |
| G3 | **No monthly depreciation posting** via JournalService | FA-UC-20/21 | Critical |
| G4 | **No FA acquisition journal entries** — no JournalService integration | FA-UC-04/05/06 | High |
| G5 | **No CIP-to-FA transfer** workflow | FA-UC-13 | High |
| G6 | **No finance lease accounting** (TK 212) | FA-UC-12 | High |
| G7 | **No disposal/liquidation workflow** — no 02-TSCĐ | FA-UC-30/31 | High |
| G8 | **No repair/upgrade capitalize vs expense logic** | FA-UC-27/28/29 | Medium |
| G9 | **No revaluation workflow** (412 tracking) | FA-UC-23/24 | Medium |
| G10 | **No impairment** (2293 provision) | FA-UC-26 | Medium |
| G11 | **No NG determination by acquisition type** (7 cash/credit/installment/exchange/self-construct/gift/capital contribution) | FA-UC-04→11 | High |
| G12 | **No land use rights / mixed-use building treatment** | FA-UC-15 | Medium |
| G13 | **No internally generated intangible capitalization** | FA-UC-14 | Low |
| G14 | **No inter-department transfer** | FA-UC-33 | Low |
| G15 | **No physical count workflow** | FA-UC-35/36 | Low |
| G16 | **No BC 06 depreciation schedule report** | FA-UC-37 | Medium |
| G17 | **No FA movement report** | FA-UC-38 | Low |
| G18 | **No disclosure data export** | FA-UC-40 | Low |
| G19 | **No FA tests** | All | Critical |

### Missing Validation Rules

- No validation that FA acquisition Dr = Cr
- No validation that monthly depreciation ≤ NBV
- No check that useful_life > 0
- No blocking of depreciation posting after period close
- No constraint against deleting FA with non-zero NBV
- No check that disposal Qty ≤ purchased Qty (for production-method FAs)
- No validation of ≥30M NG threshold

---

## 6. Suggested Implementation Order

### Phase 1: Core Engine (Critical)
1. **FixedAssetService** — depreciation calculation (all 4 methods) + monthly posting
2. **FA-UC-16/17/18/19 + FA-UC-20/21** — depreciation engine + posting + allocation
3. **FA tests** (straight-line, declining balance switch, monthly posting)

### Phase 2: Acquisition & Lifecycle (High)
4. **FA-UC-04/05/06/11** — basic acquisition (cash, credit, installment, gift)
5. **FA-UC-13** — CIP to FA transfer
6. **FA-UC-12** — finance lease accounting (TK 212)
7. **FA-UC-30/31/32** — disposal/liquidation workflow
8. **REST endpoints** for acquisition, disposal, depreciation trigger
9. **Sidebar links** → working views (replace `href="#"`)

### Phase 3: Advanced Operations (Medium)
10. **FA-UC-07/08/09/10** — exchange, self-construct, capital contribution, allocation
11. **FA-UC-15** — land use rights / mixed-use building
14. **FA-UC-23/24** — revaluation with 412 tracking
15. **FA-UC-26** — impairment with 2293 provision
16. **FA-UC-27/28/29** — repair/upgrade capitalize vs expense
17. **FA-UC-37** — depreciation schedule report (Mẫu 06-TSCĐ)
18. **FA-UC-14** — internally generated intangible

### Phase 4: Completeness (Low)
19. **FA-UC-33** — inter-department transfer
20. **FA-UC-35/36** — physical count workflow
21. **FA-UC-38/40** — movement report + disclosure export
22. **BC 03 FA validation**

---

## 7. Resolved Points (Research from ketoanthienung.net + webketoan.com + TT 45/2013/TT-BTC)

1. **Fund-sourced FA depreciation:**
   - **At acquisition** (when FA formed from fund): `Nợ 3533/3562/Có 411` (transfer fund to capital, one-time)
   - **Monthly depreciation**: standard entry `Nợ 627/641/642/Có 214` — no simultaneous fund reduction
   - Rationale: fund transferred to capital at acquisition time, not at depreciation time. Depreciation is purely cost allocation. (*Source: TT 200 Điều 70 TK 414, ketoanthienung.vn*)

2. **Component depreciation:**
   - Per TT 45 Điều 4: optional, not mandatory. Only needed when significant parts have materially different useful lives
   - For a typical enterprise: **single-asset tracking is sufficient** as initial implementation
   - Component tracking can be added later if needed (Phase 4)

3. **Depreciation method change:**
   - **Yes**, enterprise can change mid-life
   - **Constraints:** max **1 change per asset** lifetime; must notify tax authority in writing; must justify change in usage pattern
   - **Prospective** only (not retrospective — no catch-up adjustment)
   - Disclosure required in financial statements footnotes
   - Must be consistent within a fiscal year; reviewed at year-end
   - (*Source: TT 45 Điều 13, portal.mof.gov.vn, luatvietnam.vn*)

4. **Capitalize vs expense repair threshold:**
   - **No fixed monetary threshold** — TT 45 Điều 7 uses **nature-of-work test**:
     - **Capitalize (increase NG):** extends useful life, increases capacity, improves quality, reduces operating costs
     - **Expense (repair):** restores to original state → record directly or amortize max **3 years**
   - Decision: implement enterprise-configurable flag per repair job ("capitalize" or "expense")

5. **Finance lease implicit rate:**
   - Per TT 99 guidance on TK 212: enterprise may use **3 options** in order of preference:
     1. **Implicit rate** (tỷ lệ lãi suất ngầm định) — preferred if available
     2. **Rate stated in contract** (tỷ lệ lãi suất ghi trong hợp đồng)
     3. **Incremental borrowing rate (IBR)** (tỷ lệ lãi suất biên đi vay) — fallback
   - If no rate stated and tenor >12 months, **must impute** using IBR
   - (*Source: ketoanthienung.net "Cách hạch toán TSCĐ thuê tài chính TK 212 theo TT 99"*)

6. **Intangible FA categories in data model:**
   - Add migration to `fixed_assets` table with:
     - `fa_category` ENUM('tangible','intangible','finance_lease')
     - `fa_type` VARCHAR(50) — for tangible: Nhà cửa, Máy móc, PT vận tải, Thiết bị quản lý, etc.
     - For intangible subtypes: Quyền SD đất, Bằng sáng chế, Bản quyền, Phần mềm, Nhãn hiệu, Giấy phép
   - Depreciation life for intangible: min 2 years, max 20 years (TT 45 Phụ lục 1)

7. **Production method tracking (Khấu hao theo sản lượng):**
   - Formula from TT 45 Phụ lục 2:
     - `Per-unit rate = NG / Total_estimated_units`
     - `Monthly depreciation = Actual_units × Per-unit rate`
   - Add fields to `fixed_assets`:
     - `total_estimated_units DECIMAL(18,2)` — sản lượng theo công suất thiết kế
   - Add monthly record table `fixed_asset_production`:
     - `fixed_asset_id, period (YYYY-MM), actual_units DECIMAL(18,2)`
   - Three conditions for use: directly related to production, design capacity known, actual usage ≥100% design capacity
   - (*Source: ketoanthienung.net "Phương pháp khấu hao theo sản lượng"*)
