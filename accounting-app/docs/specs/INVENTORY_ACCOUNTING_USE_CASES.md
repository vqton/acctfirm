# Use Case Specification: Inventory Accounting (TT 99)

## 1. Sources

- [Nguyên tắc kế toán hàng tồn kho theo TT 99](https://ketoanthienung.net/nguyen-tac-ke-toan-hang-ton-kho-theo-thong-tu-99.htm)
- [Hạch toán TK 151 - Hàng mua đang đi đường](https://ketoanthienung.net/cach-hach-toan-tk-151-hang-mua-dang-di-duong-theo-tt99.htm)
- [Hạch toán TK 152 - Nguyên liệu, vật liệu](https://ketoanthienung.net/hach-toan-tk-152-nguyen-lieu-vat-lieu-theo-thong-tu-99.htm)
- [Hạch toán TK 153 - Công cụ, dụng cụ](https://ketoanthienung.net/hach-toan-tk-153-cong-cu-dung-cu-theo-thong-tu-99.htm)
- [Hạch toán TK 154 - Chi phí SXKD dở dang](https://ketoanthienung.net/hach-toan-tk-154-theo-thong-tu-99-chi-phi-sxkd-do-dang.htm)
- [Hạch toán TK 155 - Sản phẩm](https://ketoanthienung.net/cach-hach-toan-tai-khoan-155-san-pham-theo-thong-tu-99.htm)
- [Hạch toán TK 156 - Hàng hóa](https://ketoanthienung.net/cach-hach-toan-tk-156-hang-hoa-theo-thong-tu-99.htm)
- [Hạch toán TK 157 - Hàng gửi đi bán](https://ketoanthienung.net/cach-hach-toan-tk-157-hang-gui-di-ban-theo-thong-tu-99.htm)
- [Hạch toán TK 158 - Nguyên liệu, vật tư kho bảo thuế](https://ketoanthienung.net/cach-hach-toan-tai-khoan-158-theo-thong-tu-99.htm)

---

## 2. Domain Breakdown

### Domain: Inventory Master Data & Configuration

#### INV-UC-01: Define Inventory Valuation Method

- **Goal:** Allow enterprise to select and apply a consistent inventory costing method
- **Actors:** Accountant, System Admin
- **Preconditions:** COA loaded, enterprise configured
- **Trigger:** Enterprise setup or policy change
- **Main Flow:**
  1. User selects costing method from: Specific ID, Weighted Average, FIFO, Retail Method, Standard Cost
  2. System records the selection per item category or global
  3. System applies the method consistently across periods
- **Alternate Flow:** Standard Cost — user defines standard cost + variance coefficient formula
- **Exception Flow:** Changing method mid-period requires disclosure and retrospective adjustment
- **Output:** Costing method policy saved
- **Dependencies:** Item master data

#### INV-UC-02: Configure Cost Flow Method (Perpetual vs Periodic)

- **Goal:** Determine how inventory quantities and values are tracked
- **Actors:** Accountant
- **Preconditions:** Enterprise setup
- **Trigger:** Accounting policy election
- **Main Flow:**
  1. If Perpetual: every receipt and issue updates inventory in real-time
  2. If Periodic: physical count at period-end determines closing inventory; cost of goods sold = opening + purchases - closing
- **Output:** Cost flow method configuration
- **Dependencies:** INV-UC-01

---

### Domain: Inventory Receipt (Nhập kho)

#### INV-UC-03: Record Goods in Transit (TK 151)

- **Goal:** Recognize inventory in transit at period-end when goods are owned but not yet received
- **Actors:** Accountant
- **Preconditions:** Purchase invoice received, goods not yet arrived
- **Trigger:** Period-end closing
- **Main Flow:**
  1. At period-end, for unpaid/unreceived goods with invoice on hand:
     - Dr 151 (Goods in transit)
     - Dr 133 (Input VAT, if deductible)
     - Cr 111/112/331 (Payment/AP)
  2. In next period, when goods arrive:
     - Dr 152/153/156 (Warehouse)
     - Cr 151 (Goods in transit)
- **Alternate Flow:** Drop-ship directly to customer — Dr 157/632, Cr 151
- **Exception Flow:** Shortage/loss discovered on arrival — Dr 138 (Receivable), Cr 151
- **Output:** Journal entries for transit goods
- **Dependencies:** Purchase module, AP module

#### INV-UC-04: Receive Raw Materials (TK 152)

- **Goal:** Record purchase and receipt of raw materials into warehouse
- **Actors:** Warehouse clerk, Accountant
- **Preconditions:** Purchase order exists, goods received
- **Trigger:** Goods received note + supplier invoice
- **Main Flow:**
  1. On receipt: Dr 152 (Raw materials), Dr 133 (VAT), Cr 111/112/331
  2. Include transport, insurance, handling costs in Dr 152
- **Includes:** Domestic purchase (Dr 152, Dr 133, Cr 331)
- **Alternate Flow - Import:**
  - Dr 152 (cost + non-refundable taxes), Dr 133 (if VAT deductible)
  - Cr 331 (supplier), Cr 3332 (special consumption tax), Cr 3333 (import duty), Cr 33381 (env tax)
- **Alternate Flow - Return to supplier:** Dr 331, Cr 152, Cr 133
- **Alternate Flow - Trade discount received post-purchase:**
  - Dr 111/112/331
  - Cr 152 (if still in stock)
  - Cr 154/621/623/627 (if consumed in production)
  - Cr 632 (if product sold)
  - Cr 133 (VAT adjustment)
- **Exception Flow - Foreign currency prepayment:** Record at spot rate at prepayment date for prepaid portion
- **Output:** Inventory receipt journal entry
- **Dependencies:** AP module, Item master, Warehouse master

#### INV-UC-05: Receive Tools & Instruments (TK 153)

- **Goal:** Record purchase of tools, instruments, small tools into warehouse
- **Actors:** Warehouse clerk, Accountant
- **Preconditions:** Purchase order exists
- **Trigger:** Goods receipt
- **Main Flow:** Same as INV-UC-04 but posting to TK 153
- **Output:** Journal entry Dr 153, Dr 133, Cr 111/112/331
- **Dependencies:** Item master

#### INV-UC-06: Receive Finished Goods from Production (TK 155)

- **Goal:** Record finished goods completed and transferred to warehouse
- **Actors:** Production manager, Accountant
- **Preconditions:** WIP balanced, production order completed
- **Trigger:** Production completion report
- **Main Flow:**
  1. Calculate actual production cost from WIP (TK 154)
  2. Dr 155 (Finished goods), Cr 154 (WIP)
- **Output:** Product cost + journal entry
- **Dependencies:** INV-UC-10 (WIP accumulation)

#### INV-UC-07: Receive Merchandise for Resale (TK 156)

- **Goal:** Record merchandise purchased for resale into warehouse
- **Actors:** Warehouse clerk, Accountant
- **Preconditions:** Purchase order exists
- **Trigger:** Goods receipt
- **Main Flow:**
  1. Dr 156 (Merchandise), Dr 133, Cr 111/112/331
  2. Include purchase costs (transport, insurance, handling)
- **Alternate Flow - Installment purchase:**
  - Dr 156 (cash price), Dr 133, Cr 331
  - Periodically: Dr 635 (interest expense), Cr 331
- **Alternate Flow - Real estate (BĐS) conversion from investment property:**
  - Dr 156, Dr 2147 (accum. depreciation), Cr 217 (investment property)
  - Add renovation costs via TK 154, then transfer to TK 156
- **Output:** Journal entry
- **Dependencies:** AP module, Item master

---

### Domain: Inventory Issue / Consumption (Xuất kho)

#### INV-UC-08: Issue Raw Materials to Production (TK 152 → TK 621)

- **Goal:** Record materials issued for manufacturing
- **Actors:** Warehouse clerk
- **Preconditions:** Production order, material requisition
- **Trigger:** Material issue slip
- **Main Flow:**
  1. Dr 621/623/627/641/642 (depending on cost center)
  2. Cr 152 (Raw materials)
  3. Cost calculated per selected valuation method (INV-UC-01)
- **Output:** Journal entry, inventory reduction
- **Dependencies:** INV-UC-01, Production module

#### INV-UC-09: Issue Materials for Construction / Asset Repair (TK 152 → TK 241)

- **Goal:** Issue materials for capital construction or fixed asset repair
- **Actors:** Warehouse clerk
- **Preconditions:** Construction/repair order
- **Main Flow:** Dr 241 (CIP), Cr 152
- **Output:** Journal entry

#### INV-UC-10: Issue Tools for Single-Period Use (TK 153 → Expense)

- **Goal:** Expense low-value tools immediately
- **Actors:** Warehouse clerk
- **Preconditions:** Tool issue request
- **Trigger:** Issue slip
- **Main Flow:**
  1. Dr 623/627/641/642 (direct expense)
  2. Cr 153 (Tools)
- **Output:** Journal entry

#### INV-UC-11: Issue Tools for Multi-Period Use (TK 153 → TK 242)

- **Goal:** Capitalize high-value tools and amortize over useful life
- **Actors:** Accountant
- **Preconditions:** Tool value exceeds single-period threshold
- **Trigger:** Issue slip
- **Main Flow:**
  1. On issue: Dr 242 (Prepaid expenses), Cr 153
  2. Periodically: Dr 623/627/641/642, Cr 242
- **Alternate Flow - Rental tools:**
  - Issue: Dr 242, Cr 153
  - Rental revenue: Dr 111/112/131, Cr 511, Cr 3331
  - Tool returned: Dr 153, Cr 242 (remaining value)
- **Output:** Amortization schedule, journal entries

#### INV-UC-12: Issue Raw Materials for Outsourcing (TK 152 → TK 154)

- **Goal:** Send materials to third-party for processing
- **Actors:** Warehouse clerk
- **Main Flow:**
  1. Issue: Dr 154, Cr 152
  2. Processing cost incurred: Dr 154, Dr 133, Cr 111/112
  3. Receive back processed materials: Dr 152, Cr 154
- **Output:** Journal entries

#### INV-UC-13: Issue Finished Goods / Merchandise for Consignment (TK 155/156 → TK 157)

- **Goal:** Transfer goods to customer or agent but retain ownership until sale confirmed
- **Actors:** Warehouse clerk
- **Trigger:** Delivery order
- **Main Flow:** Dr 157, Cr 155/156
- **Output:** Journal entry, inventory reclassification

#### INV-UC-14: Record Self-Processed Materials (TK 152 self-processing)

- **Goal:** Record raw materials that undergo internal processing (e.g., cutting, mixing)
- **Main Flow:**
  1. Issue to process: Dr 154, Cr 152
  2. Return processed: Dr 152, Cr 154
- **Output:** Journal entries

---

### Domain: Inventory Disposal / Sale (Bán / Thanh lý)

#### INV-UC-15: Sell Finished Goods / Merchandise (TK 155/156 → TK 632)

- **Goal:** Record cost of goods sold upon sale
- **Actors:** Accountant
- **Preconditions:** Sales invoice issued
- **Trigger:** Revenue recognition
- **Main Flow:**
  1. Dr 632 (COGS), Cr 155/156
  2. Dr 111/112/131, Cr 511 (Revenue), Cr 3331 (VAT)
- **Alternate Flow - Internal consumption:**
  - Dr 641/642/241/211, Cr 155/156, Cr 3331
- **Alternate Flow - Pay salary with goods:**
  - Dr 334 (Salary payable), Cr 511, Cr 3331, Cr 3335 (PIT)
  - Dr 632, Cr 155/156
- **Output:** COGS + Revenue journal entries
- **Dependencies:** Sales module

#### INV-UC-16: Sell / Scrap Obsolete Inventory (TK 152/153/155/156 → TK 632)

- **Goal:** Record disposal of obsolete, damaged, or unwanted inventory
- **Actors:** Accountant
- **Main Flow:**
  1. Dr 632 (COGS), Cr Inventory account
  2. Dr 111/112/131, Cr 511, Cr 3331 (revenue from scrap sale)
- **Output:** Journal entries

#### INV-UC-17: Contribute Inventory as Capital (TK 152/155/156 → TK 221/222/228)

- **Goal:** Record inventory contributed to subsidiaries, joint ventures, or other investments
- **Actors:** Accountant
- **Main Flow:**
  1. Dr 221/222/228 (at revalued amount)
  2. Dr 811 (if revalued < book value)
  3. Cr Inventory (at book value)
  4. Cr 711 (if revalued > book value)
- **Output:** Journal entries

#### INV-UC-18: Use Inventory for Promotion / Giveaway

- **Goal:** Record promotional goods issuance per TT 99 rules
- **Actors:** Accountant
- **Preconditions:** Marketing campaign
- **Trigger:** Goods issue for promotion
- **Main Flow - Free giveaway (no purchase condition):**
  1. Dr 641 (Selling expense), Cr 155/156
- **Main Flow - Conditional promotion (buy X get Y):**
  1. Dr 632 (COGS), Cr 155/156
  2. Allocate revenue: Dr 111/112/131, Cr 511, Cr 3331
- **Alternate Flow - Employee gifts from welfare fund:**
  1. Dr 632, Cr 155/156
  2. Dr 353 (Welfare fund), Cr 511, Cr 3331
- **Output:** Journal entries

---

### Domain: Production Cost Accumulation (WIP — TK 154)

#### INV-UC-19: Accumulate Direct Material Costs (TK 621 → TK 154)

- **Goal:** Transfer direct material costs to WIP at period-end
- **Actors:** Accountant
- **Trigger:** Period-end closing
- **Main Flow:**
  1. Dr 154, Dr 632 (abnormal waste), Cr 621
- **Output:** Journal entry

#### INV-UC-20: Accumulate Direct Labor Costs (TK 622 → TK 154)

- **Goal:** Transfer direct labor costs to WIP
- **Trigger:** Period-end closing
- **Main Flow:**
  1. Dr 154, Dr 632 (abnormal labor), Cr 622
- **Output:** Journal entry

#### INV-UC-21: Accumulate Production Overhead (TK 627 → TK 154)

- **Goal:** Allocate overhead to WIP at normal capacity
- **Trigger:** Period-end closing
- **Main Flow:**
  1. If actual >= normal capacity: Dr 154, Cr 627 (full overhead)
  2. If actual < normal capacity: Dr 154 (at normal rate), Dr 632 (unabsorbed), Cr 627
- **Output:** Overhead allocation journal entry

#### INV-UC-22: Calculate Product Cost & Transfer to Finished Goods (TK 154 → TK 155)

- **Goal:** Compute actual production cost and record finished goods
- **Actors:** Accountant
- **Trigger:** Production completion
- **Main Flow:**
  1. WIP opening + DM + DL + OH - WIP closing = cost of goods manufactured
  2. Dr 155 (Finished goods), Cr 154
- **Alternate Flow - Direct sale (no warehousing):** Dr 632, Cr 154
- **Alternate Flow - Internal use:** Dr 241/641/642, Cr 154
- **Output:** Product cost report, journal entry
- **Dependencies:** INV-UC-19, UC-20, UC-21

#### INV-UC-23: Record Spoiled / Defective Goods

- **Goal:** Write off or recover costs of defective production
- **Actors:** Accountant
- **Main Flow:**
  1. If recoverable: continue processing (remain in WIP)
  2. If unrecoverable, responsible party pays:
     - Dr 1388/334, Cr 154
  3. Scrap value recovered: Dr 152, Cr 154
- **Output:** Journal entries

---

### Domain: Consignment & Goods Sent for Sale (TK 157)

#### INV-UC-24: Send Goods on Consignment (TK 155/156 → TK 157)

- **Goal:** Record goods sent to customer or agent but not yet sold
- **Actors:** Warehouse clerk
- **Preconditions:** Sales order or consignment agreement
- **Trigger:** Physical delivery
- **Main Flow:**
  1. Dr 157, Cr 155/156
  2. When customer accepts: Dr 632, Cr 157 (COGS)
  3. Simultaneously record revenue: Dr 111/112/131, Cr 511, Cr 333
- **Alternate Flow - Service completed but not yet billed:**
  1. Dr 157, Cr 154
  2. When billed/accepted: Dr 632, Cr 157
- **Output:** Journal entries

---

### Domain: Physical Count & Adjustment (Kiểm kê)

#### INV-UC-25: Record Physical Count Surplus

- **Goal:** Record inventory surplus found during physical count
- **Actors:** Accountant, Warehouse clerk
- **Trigger:** Periodic physical count
- **Main Flow:**
  1. If cause identified: adjust books directly
  2. If cause unknown: Dr Inventory, Cr 3381 (Pending adjustment)
  3. When resolved: Dr 3381, Cr appropriate account
- **Output:** Adjustment journal entries

#### INV-UC-26: Record Physical Count Shortage

- **Goal:** Record inventory shortage found during physical count
- **Actors:** Accountant, Warehouse clerk
- **Trigger:** Periodic physical count
- **Main Flow:**
  1. If within normal spoilage limits: Dr 632, Cr Inventory
  2. If responsible party identified: Dr 1388/334, Cr Inventory
  3. If cause unknown: Dr 1381, Cr Inventory
  4. When resolved: Dr appropriate account, Cr 1381
- **Output:** Adjustment journal entries

#### INV-UC-27: Create Inventory Impairment Provision (TK 229)

- **Goal:** Record provision when net realizable value < cost
- **Actors:** Accountant
- **Trigger:** Period-end assessment
- **Main Flow:**
  1. Calculate NRV per item
  2. If NRV < cost: Dr 632, Cr 229 (Provision for inventory impairment)
  3. Reverse when NRV recovers (max to original cost)
- **Output:** Provision journal entry
- **Dependencies:** Periodic closing

---

### Domain: Bonded Warehouse (Kho bảo thuế — TK 158)

#### INV-UC-28: Import Materials to Bonded Warehouse (TK 158)

- **Goal:** Record duty-free import for export production
- **Actors:** Accountant, Customs officer
- **Preconditions:** Enterprise qualified for bonded warehouse
- **Trigger:** Customs clearance
- **Main Flow:**
  1. Dr 158, Cr 331 (supplier)
  2. No import duty or VAT recorded (deferred)
- **Output:** Journal entry

#### INV-UC-29: Issue Bonded Materials to Production (TK 158 → TK 621)

- **Goal:** Record consumption of bonded materials for export manufacturing
- **Main Flow:** Dr 621, Cr 158
- **Output:** Journal entry

#### INV-UC-30: Re-export or Destroy Bonded Materials

- **Goal:** Record disposition of damaged/unusable bonded materials
- **Main Flow - Re-export:** Dr 331, Cr 158
- **Main Flow - Destroy:** Dr 632, Cr 158
- **Output:** Journal entries

#### INV-UC-31: Sell Bonded Materials Domestically

- **Goal:** Record domestic sale of bonded materials (duty becomes due)
- **Actors:** Accountant
- **Note:** Requires customs procedures + tax payment per law
- **Output:** Journal entries + tax filing

---

### Domain: Inventory Valuation & Adjustment

#### INV-UC-32: Allocate Purchase Costs (TK 156/152)

- **Goal:** Allocate transport, handling, insurance costs to inventory
- **Actors:** Accountant
- **Main Flow:**
  1. Dr 156/152 (cost allocation), Cr 111/112
  2. For small costs: Dr 632 directly
- **Output:** Journal entry

#### INV-UC-33: Apply Provisional Price Coefficient (TK 152)

- **Goal:** Adjust from provisional to actual cost using coefficient
- **Actors:** Accountant
- **Main Flow:**
  1. Coefficient = (actual opening + actual purchases) / (provisional opening + provisional purchases)
  2. Actual issue cost = provisional issue cost × coefficient
- **Output:** Adjusted COGS

#### INV-UC-34: Handle Returned Goods from Customer

- **Goal:** Record goods returned by customer
- **Actors:** Accountant
- **Main Flow:**
  1. Dr 521 (Revenue deduction), Dr 3331, Cr 111/112/131
  2. Dr 155/156, Cr 632 (COGS reversal)
- **Output:** Journal entries

---

## 3. Cross-Use Case Observations

### Overlapping Use Cases

| UC | Overlaps With | Note |
|---|---|---|
| INV-UC-04 (Receive Raw Mats) | INV-UC-05 (Receive Tools) | Almost identical accounting, different account |
| INV-UC-15 (Sell Goods) | INV-UC-24 (Consignment) | TK 157 acts as intermediate step before sale recognition |
| INV-UC-25/26 (Count Adj) | All receipt/issue UCs | Physical count validation applies across all inventory types |
| INV-UC-11 (Multi-period tools) | INV-UC-10 (Single-period) | Same source, different treatment by value threshold |
| INV-UC-18 (Promotion) | INV-UC-15 (Sale) | Conditional promotion is economically a discount, treated as sale |

### Missing Flows

- **Inter-warehouse transfer** — moving inventory between warehouses/ locations has no explicit use case but is common
- **Inventory revaluation** — write-up/down outside of impairment (e.g., price change) not addressed
- **Batch/lot tracking** — serial number, expiry date tracking implied by "per-item detail" but not explicit
- **By-product / co-product costing** — production with multiple outputs not covered
- **Consignment-in** (goods held on behalf of others) — mentioned as "not recorded in inventory" but no operational flow for tracking

### Unclear Points

- Threshold for "low-value" tools vs capitalized — not defined, left to enterprise policy
- Normal capacity calculation method for overhead absorption — not specified
- Frequency of physical count — "periodically or at period-end" — no minimum requirement stated
- Foreign currency prepayment rate determination — "spot rate at prepayment date" stated but no follow-up on final settlement

---

## 4. Gaps Identified

### Missing Use Cases
1. **Inventory Transfer** — between warehouses, locations, cost centers
2. **Inventory Reclassification** — from raw to finished goods inter-company, from goods to samples
3. **Batch / Lot / Serial Tracking** — required by TT 99 §12 for per-item detail tracking
4. **Consignment-in Tracking** — goods held for others (off-balance sheet tracking)
5. **Intra-company Inventory Transfer** — between subsidiaries/ branches
6. **Bill of Materials (BOM) Explosion** — needed for production costing calculation
7. **Inventory Aging Report** — for impairment assessment and obsolescence
8. **Exchange Rate Revaluation of FX Inventory** — when inventory was purchased in foreign currency and settled later

### Missing Validation Rules
- No validation that Dr = Cr in inventory journal entries
- No validation that inventory issue cannot exceed available quantity
- No blocking of period-end transactions after period close
- No check that valuation method is consistent across periods

### Missing Error Handling
- Insufficient quantity for issue — backorder vs block
- Duplicate goods receipt — prevent or alert
- Negative inventory balance — prevent or flag
- Cost calculation with zero or negative coefficient — edge case

---

## 5. Suggested Improvements

### Priority 1: Critical for Correctness

1. **Add Inventory Transfer (INV-UC-NEW-01)**
   - Dr 152/153/155/156 (destination warehouse), Cr same account (source warehouse)
   - Support with route: `POST /api/inventory/transfer`

2. **Add Consignment-in Tracking (INV-UC-NEW-02)**
   - Off-balance sheet: Dr 002 (Goods held for others), Cr memo
   - Reverse when returned or sold

3. **Add Physical Count Validation Rule**
   - Lock inventory movements during physical count
   - Require manager approval for adjustment above threshold

### Priority 2: Operational Completeness

4. **Extend INV-UC-01 to support per-warehouse valuation method**
   - Different warehouses may use different costing methods (FIFO for high-value, weighted avg for bulk)

5. **Add Inventory Aging report use case**
   - Classify items by days in stock: 0-30, 31-90, 91-180, 181-365, 365+
   - Suggest impairment rate per bracket

6. **Add Batch/Lot Tracking**
   - Extend item receipt to capture batch number, expiry date
   - FIFO must respect batch-based cost layers, not just time-weighted

### Priority 3: System Integration

7. **Production Costing Integration**
   - INV-UC-22 needs integration with production module (work orders, BOMs)
   - Actual cost = BOM standard cost + variance allocation

8. **Import Customs Integration**
   - INV-UC-04 (import) needs tax calculation based on customs declaration
   - Bonded warehouse (INV-UC-28) requires customs documentation tracking

### Priority 4: Audit & Compliance

9. **Add Inventory Transaction Audit Trail**
   - Every receipt/issue/adjustment logs: who, when, why, old value, new value
   - Required by TT 99 §12 (physical vs book reconciliation)

10. **Standardize the 4 Costing Method Implementations**
    - Specific ID: simple, per-item cost tracking
    - Weighted Average: (opening + purchases) / (opening qty + purchase qty)
    - FIFO: separate cost layers per purchase lot, consume oldest first
    - Standard Cost: maintain variance accounts for material, labor, overhead
