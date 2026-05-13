# Use Case Specification

## Inventory Accounting System — Circular 99/2025/TT-BTC (Vietnamese Enterprise Accounting Regime)

---

## 1. Source

- **URLs:**
  - https://ketoanthienung.net/nguyen-tac-ke-toan-hang-ton-kho-theo-thong-tu-99.htm
  - https://ketoanthienung.net/cach-hach-toan-tk-151-hang-mua-dang-di-duong-theo-tt99.htm
  - https://ketoanthienung.net/hach-toan-tk-152-nguyen-lieu-vat-lieu-theo-thong-tu-99.htm
  - https://ketoanthienung.net/hach-toan-tk-153-cong-cu-dung-cu-theo-thong-tu-99.htm
  - https://ketoanthienung.net/hach-toan-tk-154-theo-thong-tu-99-chi-phi-sxkd-do-dang.htm
  - https://ketoanthienung.net/cach-hach-toan-tai-khoan-155-san-pham-theo-thong-tu-99.htm
  - https://ketoanthienung.net/cach-hach-toan-tk-156-hang-hoa-theo-thong-tu-99.htm
  - https://ketoanthienung.net/cach-hach-toan-tk-157-hang-gui-di-ban-theo-thong-tu-99.htm
  - https://ketoanthienung.net/cach-hach-toan-tai-khoan-158-theo-thong-tu-99.htm

- **Domain Context:** Inventory Accounting (Hàng tồn kho) for Vietnamese enterprises under Circular 99/2025/TT-BTC, effective 01 January 2026, replacing Circular 200/2014/TT-BTC.

- **Regulatory Context:** Vietnamese Accounting Standards (VAS 02 - Inventory), Circular 99/2025/TT-BTC, Tax laws (CIT, VAT, import/export duties, excise tax, environmental protection tax, natural resource tax).

- **Analysis Summary:** The source material defines the full lifecycle of inventory accounting across 8 account groups (TK 151–158), covering: cost determination principles, valuation methods, recognition rules, receipt/issue/return/adjustment postings, foreign currency handling, promotional goods, impairment provisioning, and physical inventory count procedures. The system must implement double-entry bookkeeping with strict debit/credit balance enforcement.

---

## 2. Domain Breakdown

---

### Domain 1: Inventory Master Data & Configuration

#### UC-001: Configure Inventory Valuation Method

##### Description
Enterprise selects and applies a consistent cost determination method for inventory valuation across accounting periods.

##### Goal
Establish the cost-flow assumption for inventory valuation that is applied consistently unless policy changes.

##### Primary Actors
- Chief Accountant

##### Supporting Actors
- System Administrator

##### Preconditions
- Enterprise accounting regime is configured (Circular 99)
- Chart of accounts is initialized

##### Trigger
- Enterprise registration or accounting policy change

##### Main Flow
1. System presents available valuation methods: specific identification, weighted average, FIFO, retail method, standard cost
2. User selects a method per inventory item category or globally
3. System validates the method is consistently applied across prior and current period
4. System records the policy election with effective date
5. System enforces the selected method for all subsequent inventory transactions

##### Alternate Flow
- **Method change:** User initiates a policy change. System requires documented justification. System applies prospective application from the change date; retrospective restatement is not permitted unless required by VAS. System logs the change with auditor trail.

##### Exception Flow
- If the selected method violates Vietnamese Accounting Standards, system rejects and requests correction.

##### Business Rules
- BR001: Method must be applied consistently across periods unless policy change is formally documented
- BR002: Weighted average may be calculated per period or per receipt batch
- BR003: Standard cost method requires periodic review and adjustment to actual cost
- BR004: Retail method is restricted to retail/supermarket businesses with high-volume, low-margin, fast-moving goods

##### Input Data
- Valuation method code
- Effective date
- Justification (if changing from prior method)
- Applicable inventory categories

##### Output Data
- Valuation method configuration record
- Audit log entry

##### Dependencies
- UC-002 (Configure Inventory Categories)

##### Frequency
- Once at setup; rarely changed

##### Priority
- Critical

##### Compliance Impact
- VAS 02 §4, §9, §11; Circular 99 principles §9

---

#### UC-002: Configure Inventory Classification

##### Description
Enterprise defines inventory categories, sub-categories, and their attributes (account mapping, unit of measure, valuation method override, storage locations).

##### Goal
Enable granular inventory tracking and control by category, type, and location.

##### Primary Actors
- Accountant
- Warehouse Manager

##### Supporting Actors
- System Administrator

##### Preconditions
- Chart of accounts is set up with TK 151–158

##### Trigger
- Enterprise setup or new inventory category requirement

##### Main Flow
1. User defines inventory categories (raw materials, tools, WIP, finished goods, merchandise, goods in transit, bonded warehouse materials)
2. For each category, user assigns default GL account (152, 153, 154, 155, 156, 151, 157, 158)
3. User defines sub-classifications: raw material sub-types (main materials, sub-materials, fuel, spare parts, construction materials)
4. User specifies unit of measure, valuation method override (optional), and storage locations
5. System validates account mappings against chart of accounts

##### Alternate Flow
- None

##### Exception Flow
- If account is locked or period is closed, system rejects modification

##### Business Rules
- BR005: TK 152 raw materials must be classified as: main materials, sub-materials, fuel, spare parts, construction materials
- BR006: TK 153 tools include: formwork, packaging, glass/ceramic tools, office supplies, protective equipment
- BR007: Non-owned inventory (consignment, custody, processing) must NOT be recorded in TK 151–158; tracked separately off-balance-sheet
- BR008: Inventory held >12 months or beyond one operating cycle must be reclassified as long-term assets (not presented as current inventory on balance sheet)

##### Input Data
- Category code and name
- GL account mapping
- Sub-classifications
- Unit of measure
- Storage location(s)

##### Output Data
- Inventory category master record
- Updated chart of accounts mapping

##### Dependencies
- UC-001 (Configure Inventory Valuation Method)

##### Frequency
- Once at setup; occasional additions

##### Priority
- High

##### Compliance Impact
- Circular 99 §1, §3; VAS 02 §6

---

### Domain 2: Goods Receipt (Nhập kho)

#### UC-003: Record Inventory Purchase Receipt

##### Description
Record the receipt of purchased inventory (domestic or imported) into warehouse, including cost accumulation: purchase price, non-refundable taxes, freight, insurance, handling, and other directly attributable costs.

##### Goal
Accurately capture the landed cost of purchased inventory and update stock quantities/values.

##### Primary Actors
- Accountant
- Warehouse Keeper

##### Supporting Actors
- Purchasing Department
- Supplier

##### Preconditions
- Purchase order exists (or direct purchase is authorized)
- Supplier invoice is received
- Inventory category and account are configured

##### Trigger
- Physical goods received at warehouse with accompanying invoice

##### Main Flow
1. Warehouse keeper receives goods and performs quantity/quality inspection
2. Warehouse keeper creates Goods Receipt Note (Phiếu nhập kho)
3. System records receipt with: item code, quantity, unit price (excluding VAT if deductible; including VAT if non-deductible)
4. System calculates landed cost: invoice price + non-refundable taxes (import duty, excise tax, environmental tax, natural resource tax) + freight + insurance + handling + other direct costs
5. System records journal entry: Dr TK 152/153/156 (+ VAT deductible Dr TK 133) — Cr TK 111/112/141/331
6. System updates inventory quantity and value in stock ledger

##### Alternate Flow
- **Goods arrived before invoice:** Goods are recorded as "goods in transit" (TK 151) at period-end if invoice not yet received. Posting is deferred; goods tracked physically.
- **Goods in transit arrive next period:** Dr TK 152/153/156 — Cr TK 151
- **Goods delivered directly to customer (drop-shipment):** Dr TK 157/632 — Cr TK 151 (bypass warehouse receipt)
- **Import purchase (foreign currency):** Cost recorded at spot exchange rate at transaction date; prepayments recorded at prepayment date rate. Exchange differences handled via TK 413.

##### Exception Flow
- If received quantity exceeds ordered quantity beyond tolerance, system flags for approval
- If goods are damaged on arrival, system routes to claims/discrepancy workflow
- If invoice has tax calculation error, accountant must correct before posting

##### Business Rules
- BR009: VAT on imports: if deductible, exclude from cost (Dr TK 133); if non-deductible, include in cost
- BR010: Foreign currency purchase: prepayment portion uses prepayment date rate; remaining uses transaction date rate
- BR011: Non-refundable taxes (import duty, excise, environmental, resource tax) are always included in inventory cost
- BR012: Trade discounts and rebates received AFTER purchase must be allocated: reduce cost of inventory still on hand; reduce expense for inventory already consumed; reduce COGS for inventory already sold
- BR013: Settlement discounts (early payment) are recorded as financial income (TK 515), not inventory cost reduction

##### Input Data
- Supplier, invoice number, date
- Item code, quantity, unit price
- Add-on costs (freight, insurance, handling)
- Tax amounts and deductibility status
- Exchange rate (if foreign currency)

##### Output Data
- Goods Receipt Note
- Journal entry (Dr TK 152/153/156 ± TK 133 — Cr TK 111/112/141/331)
- Updated stock balance

##### Dependencies
- UC-001, UC-002

##### Frequency
- Daily/Event-driven

##### Priority
- Critical

##### Compliance Impact
- VAS 02 §5, §7; Circular 99 §2, §5, §6, §10; Tax law on VAT deductibility

---

#### UC-004: Record Self-Manufactured Inventory Receipt

##### Description
Record finished goods or semi-finished goods produced internally and received into warehouse at actual production cost.

##### Goal
Capture manufacturing cost and transfer completed output from WIP to finished goods inventory.

##### Primary Actors
- Cost Accountant
- Production Manager

##### Supporting Actors
- Warehouse Keeper

##### Preconditions
- WIP costs have been accumulated in TK 154
- Production order is completed and quality-checked

##### Trigger
- Completed production batch is received into warehouse

##### Main Flow
1. Production department confirms batch completion with quality inspection pass
2. Cost accountant calculates actual production cost from TK 154 (direct materials + direct labor + machine costs + production overhead)
3. System creates Goods Receipt Note for finished goods
4. System records journal entry: Dr TK 155 — Cr TK 154
5. System updates finished goods stock balance

##### Alternate Flow
- **Products delivered directly to customer (not warehoused):** Dr TK 632 — Cr TK 154 (for utilities like electricity/water)
- **Products used internally for construction:** Dr TK 241 — Cr TK 154
- **By-products / scrap recovered:** Dr TK 152 (scrap value) — Cr TK 154

##### Exception Flow
- If actual cost exceeds standard/budget by abnormal margin, the excess is charged directly to COGS (TK 632) not to inventory

##### Business Rules
- BR014: Normal vs abnormal cost: fixed overhead is allocated based on normal capacity; unallocated fixed overhead due to below-capacity production is expensed to COGS
- BR015: Variable overhead is allocated in full based on actual production volume
- BR016: Direct material and direct labor above normal levels are expensed to COGS

##### Input Data
- Production order ID
- Finished product codes and quantities
- Cost allocation details

##### Output Data
- Finished goods receipt note
- Journal entry (Dr TK 155 — Cr TK 154)
- Updated WIP balance

##### Dependencies
- UC-009 (Record Production Cost Allocation)

##### Frequency
- Daily/Event-driven

##### Priority
- High

##### Compliance Impact
- VAS 02 §7; Circular 99 — TK 154, TK 155; VAS on production cost

---

#### UC-005: Record Inventory from External Processing

##### Description
Record the receipt of raw materials or semi-finished goods that were sent to third-party processors and returned after processing.

##### Goal
Capture processing cost and track returned processed inventory.

##### Primary Actors
- Accountant

##### Supporting Actors
- External Processor

##### Preconditions
- Raw materials were issued to processor (Dr TK 154 — Cr TK 152)
- Processing invoice received

##### Trigger
- Processed goods returned with processing invoice

##### Main Flow
1. User records processing costs: processing fee + transport + other direct costs
2. System records journal entry: Dr TK 154 (± TK 133) — Cr TK 111/112/331
3. Upon return to warehouse: Dr TK 152/155 — Cr TK 154

##### Alternate Flow
- None

##### Exception Flow
- If processing results in scrap or loss, system records loss handling per UC-011

##### Business Rules
- BR017: Cost of externally processed goods = raw material cost + processing fee + transport + related costs

##### Input Data
- Processing order reference
- Processing fee invoice
- Returned quantities and grades

##### Output Data
- Processed goods receipt
- Journal entries

##### Dependencies
- UC-003, UC-011

##### Frequency
- Event-driven

##### Priority
- Medium

##### Compliance Impact
- Circular 99 — TK 152 §3.7, TK 154

---

### Domain 3: Goods Issue (Xuất kho)

#### UC-006: Issue Inventory to Production

##### Description
Record the issuance of raw materials from warehouse to production departments for manufacturing.

##### Goal
Transfer raw material cost to work-in-process and track material consumption.

##### Primary Actors
- Warehouse Keeper
- Production Manager

##### Supporting Actors
- Cost Accountant

##### Preconditions
- Raw materials exist in stock with sufficient quantity
- Production order is active

##### Trigger
- Production requisition submitted and approved

##### Main Flow
1. Production department submits material requisition
2. Warehouse picks and issues materials
3. System records: Dr TK 621/623/627/641/642 — Cr TK 152
4. System applies selected valuation method (FIFO/weighted avg/specific ID) to calculate issue cost
5. System decrements inventory quantity and updates GL

##### Alternate Flow
- **Materials issued for construction projects:** Dr TK 241 — Cr TK 152
- **Materials issued for capital contribution:** Dr TK 221/222 (at revalued amount); Dr TK 811 (loss); Cr TK 152 (book value); Cr TK 711 (gain)
- **Materials issued to repurchase equity interests in subsidiaries/associates:** Record as revenue (Dr TK 221/222 — Cr TK 511 ± TK 3331); record COGS (Dr TK 632 — Cr TK 152)

##### Exception Flow
- If insufficient stock, system blocks issue and alerts procurement

##### Business Rules
- BR018: Issue cost is calculated using the configured valuation method per item category
- BR019: If using provisional/standard cost, period-end adjustment coefficient must be calculated:
  - Coefficient = (Actual opening + Actual receipts) / (Provisional opening + Provisional receipts)
  - Actual issue cost = Provisional issue cost × Coefficient

##### Input Data
- Requisition reference
- Item codes and quantities
- Cost center / production order

##### Output Data
- Material issue slip
- Journal entry
- Updated stock balance

##### Dependencies
- UC-001, UC-002

##### Frequency
- Daily/Event-driven

##### Priority
- Critical

##### Compliance Impact
- Circular 99 — TK 152 §3.9; VAS 02

---

#### UC-007: Issue Inventory for Sale

##### Description
Record the delivery of finished goods, merchandise, or raw materials to customers, including cost of goods sold recognition.

##### Goal
Recognize revenue and matching cost of goods sold upon sale/delivery.

##### Primary Actors
- Sales Accountant
- Warehouse Keeper

##### Supporting Actors
- Sales Department
- Customer

##### Preconditions
- Sales order is confirmed
- Inventory is available and priced

##### Trigger
- Delivery order issued or goods dispatched

##### Main Flow
1. Sales order triggers picking and dispatch
2. Warehouse records goods issue note
3. System calculates COGS using valuation method
4. Journal: Dr TK 632 — Cr TK 155/156/152
5. Revenue recognized separately: Dr TK 111/112/131 — Cr TK 511 ± TK 3331

##### Alternate Flow
- **Goods sent on consignment (not yet sold):** Dr TK 157 — Cr TK 155/156 (remain as enterprise inventory until sold by consignee)
- **Sale-and-leaseback / promotional giveaways:** See UC-012, UC-014

##### Exception Flow
- If goods returned by customer, see UC-015

##### Business Rules
- BR020: Consignment goods (TK 157) remain enterprise property until sold by agent; risk and rewards not yet transferred
- BR021: Bill-and-hold arrangements require careful evaluation of revenue recognition timing per VAS

##### Input Data
- Sales order / delivery note
- Item codes, quantities
- Customer details

##### Output Data
- Delivery note / goods issue slip
- COGS journal entry (Dr TK 632)
- Updated inventory balance

##### Dependencies
- UC-003, UC-004, UC-001

##### Frequency
- Daily/Event-driven

##### Priority
- Critical

##### Compliance Impact
- VAS 02, VAS 14 (Revenue); Circular 99 — TK 155, TK 156, TK 157, TK 632

---

### Domain 4: In-Transit & Consignment Inventory

#### UC-008: Manage Goods in Transit (TK 151)

##### Description
Track inventory purchased but not yet received at warehouse at period-end. Recognize obligation and asset when risks and rewards have transferred to buyer.

##### Goal
Ensure accurate period-end cut-off for inventory ownership and payables recognition.

##### Primary Actors
- Accountant

##### Supporting Actors
- Purchasing Department
- Supplier

##### Preconditions
- Goods have been shipped by supplier or title has transferred
- Invoice has been received (or can be reliably estimated)
- Goods not yet physically received or inspected

##### Trigger
- Period-end closing procedure detects open purchase orders where goods are in transit

##### Main Flow
1. At period-end, accountant identifies all purchases where goods are in transit (title transferred, goods not yet received)
2. System records: Dr TK 151 — Dr TK 133 (if VAT deductible) — Cr TK 111/112/141/331
3. Opening next period: upon receipt, Dr TK 152/153/156 — Cr TK 151

##### Alternate Flow
- **Goods received within same period:** bypass TK 151; record directly to inventory account
- **Goods delivered directly to customer from transit:** Dr TK 157/632 — Cr TK 151 (no warehouse receipt)

##### Exception Flow
- If goods are lost/damaged in transit, record as Dr TK 138 (1381/1388) — Cr TK 151 and pursue claim

##### Business Rules
- BR022: TK 151 balance represents inventory legally owned by enterprise but not yet physically received at period-end
- BR023: Detailed tracking by item type, shipment, and purchase contract is required
- BR024: If invoice is not yet received at period-end, system must accrue the liability based on contract or estimate

##### Input Data
- Purchase order / contract reference
- Invoice (or estimated value)
- Shipping documents
- Period-end date

##### Output Data
- TK 151 journal entry
- Updated goods-in-transit register
- Period-end cut-off report

##### Dependencies
- UC-003

##### Frequency
- Monthly/Period-end

##### Priority
- High

##### Compliance Impact
- Circular 99 — TK 151; VAS 02 §5; Inventory ownership cut-off principle

---

#### UC-009: Manage Consignment Inventory (TK 157)

##### Description
Track goods sent to agents/consignees for sale but not yet sold. Goods remain enterprise property until sold by consignee.

##### Goal
Maintain accurate inventory ownership segregation and recognize revenue only when consignee sells to end customer.

##### Primary Actors
- Sales Accountant
- Warehouse Keeper

##### Supporting Actors
- Consignee/Agent

##### Preconditions
- Consignment agreement exists
- Inventory is available

##### Trigger
- Goods dispatched to consignee

##### Main Flow
1. System records: Dr TK 157 — Cr TK 155/156 (transfer to consignment)
2. Consignee reports sales periodically
3. Upon sale notification: Dr TK 632 — Cr TK 157 (COGS); Dr TK 111/112/131 — Cr TK 511 ± TK 3331 (revenue)
4. Unsold consignment goods are included in enterprise inventory at period-end (TK 157 balance)

##### Alternate Flow
- **Goods returned from consignee:** Dr TK 155/156 — Cr TK 157
- **Goods lost/damaged at consignee:** record loss and pursue claim: Dr TK 138/632 — Cr TK 157

##### Exception Flow
- None

##### Business Rules
- BR025: Consignment goods are NOT removed from inventory valuation; they stay on balance sheet (TK 157) until sale to third party
- BR026: Detailed tracking by consignee, location, and consignment contract is required

##### Input Data
- Consignment reference
- Item codes, quantities
- Consignee details

##### Output Data
- Consignment dispatch note
- Journal entry (Dr TK 157)
- Consignment stock report

##### Dependencies
- UC-007

##### Frequency
- Event-driven

##### Priority
- High

##### Compliance Impact
- Circular 99 — TK 157; VAS 02; Revenue recognition principle

---

### Domain 5: Production Cost & Work-in-Process (TK 154)

#### UC-010: Accumulate and Allocate Production Costs

##### Description
Collect all production costs (direct materials, direct labor, machine costs, production overhead) into WIP (TK 154) and calculate finished goods cost.

##### Goal
Determine accurate production cost per unit and support period-end inventory valuation.

##### Primary Actors
- Cost Accountant

##### Supporting Actors
- Production Manager
- HR / Payroll

##### Preconditions
- Raw material issues have been recorded (UC-005)
- Labor and overhead costs are recorded in respective accounts (TK 622, 623, 627)

##### Trigger
- Period-end costing cycle

##### Main Flow
1. System accumulates direct material costs: Dr TK 154 — Cr TK 621 (with abnormal portion recorded to TK 632)
2. System accumulates direct labor costs: Dr TK 154 — Cr TK 622 (with abnormal portion to TK 632)
3. System allocates machine costs: Dr TK 154 — Cr TK 623 (with abnormal portion to TK 632)
4. System allocates production overhead:
   - Fixed overhead: allocated at normal capacity; unallocated portion charged to TK 632
   - Variable overhead: allocated in full
   - Journal: Dr TK 154 — Cr TK 627
5. System calculates unit cost: (opening WIP + current costs − scrap/by-product value) / completed units
6. System transfers completed output cost: Dr TK 155 — Cr TK 154
7. System leaves residual as WIP ending balance (TK 154)

##### Alternate Flow
- **Services industry:** cost accumulation is similar; completed service cost transferred to TK 632 directly
- **Construction industry:** costs tracked per contract/project; progress billings recognized; percentage-of-completion method applied
- **Abnormal capacity:** if actual production < normal capacity, fixed overhead per unit is based on normal capacity; excess is expensed

##### Exception Flow
- If cost cannot be reliably measured, system flags for manual review

##### Business Rules
- BR027: TK 154 must NOT include: selling expenses, administrative expenses, finance costs, other expenses, CIT expense, CAPEX, or expenses covered by other sources
- BR028: Fixed overhead allocation rate = total fixed overhead / normal capacity; applied to actual units produced
- BR029: WIP and finished goods are valued at actual production cost

##### Input Data
- Opening WIP balance
- Current period costs (TK 621, 622, 623, 627)
- Production quantities (completed, in progress)
- Normal capacity level

##### Output Data
- Cost allocation journal entries
- Unit cost calculation
- WIP ending balance
- Finished goods receipt value

##### Dependencies
- UC-005, UC-006, UC-007

##### Frequency
- Monthly/Period-end

##### Priority
- Critical

##### Compliance Impact
- VAS 02; Circular 99 — TK 154; VAS on production cost; Industry-specific guidance for manufacturing, construction, services

---

### Domain 6: Inventory Adjustment & Count

#### UC-011: Perform Physical Inventory Count and Adjust

##### Description
Conduct periodic physical count of all inventory items, compare to system records, investigate discrepancies, and adjust stock records accordingly.

##### Goal
Ensure inventory records match physical stock; identify and resolve shrinkage, overages, misclassification.

##### Primary Actors
- Warehouse Keeper
- Accountant
- Inventory Controller

##### Supporting Actors
- Internal Auditor
- Count Team

##### Preconditions
- Count is scheduled (periodic or perpetual)
- Count teams are assigned

##### Trigger
- Period-end closing, or random cycle count trigger

##### Main Flow
1. System generates count sheets or count tags per storage location
2. Count teams physically count and record quantities on count sheets
3. System loads count results and compares to book balances
4. For each discrepancy, system calculates variance (quantity × unit cost)
5. For surpluses: Dr TK 152/153/155/156 — Cr TK 3381 (waiting for resolution) — then upon approval Cr appropriate account
6. For deficits:
   - Within normal tolerance: Dr TK 632 — Cr TK 152/153/155/156
   - Attributable to responsible person: Dr TK 1388/334 — Cr inventory account
   - Unidentified (pending investigation): Dr TK 1381 — Cr inventory account
   - Upon resolution: Dr TK 632/111/1388/334 — Cr TK 1381
7. System updates stock records and generates adjustment journal

##### Alternate Flow
- **Perpetual inventory system:** book balance is continuously updated; count verifies accuracy
- **Periodic inventory system:** ending inventory determined by physical count; issue cost calculated as: Opening + Receipts − Closing

##### Exception Flow
- If significant discrepancy detected, system triggers investigation workflow and suspends related transactions

##### Business Rules
- BR030: Physical count must be performed at least at each period-end
- BR031: In perpetual system, book balance must always equal physical quantity; any variance requires immediate investigation
- BR032: Material variances must be approved by chief accountant or higher authority before adjustment
- BR033: Count results must be documented in official count minutes (Biên bản kiểm kê)
- BR034: For periodic system: Issued quantity/value = Opening + Receipts − Closing (physical)

##### Input Data
- Count sheets with physical quantities
- Book quantities and unit costs

##### Output Data
- Count variance report
- Adjustment journal entries
- Updated stock records
- Discrepancy investigation log

##### Dependencies
- All receipt and issue use cases

##### Frequency
- Monthly/Quarterly/Annually or continuous cycle counting

##### Priority
- Critical

##### Compliance Impact
- Circular 99 §12, §13; VAS 02; Auditing standards on inventory observation

---

### Domain 7: Inventory Valuation & Provisioning

#### UC-012: Calculate and Record Inventory Impairment Provision

##### Description
Assess whether net realizable value (NRV) of inventory is lower than carrying cost, and record or reverse provision for inventory impairment (TK 229 — Dự phòng tổn thất tài sản).

##### Goal
Ensure inventory is not carried above its recoverable amount; reflect true economic value in financial statements.

##### Primary Actors
- Chief Accountant
- Financial Controller

##### Supporting Actors
- Sales Department (for NRV data)

##### Preconditions
- Period-end closing is in progress
- Cost of inventory is known

##### Trigger
- Period-end impairment assessment procedure

##### Main Flow
1. For each inventory item, accountant determines:
   - Carrying cost (book value)
   - Net realizable value (estimated selling price less completion and selling costs)
2. If carrying cost > NRV, provision is needed
3. System records: Dr TK 632 — Cr TK 2294 (for the impairment amount)
4. If NRV recovers in subsequent period, provision is reversed (up to original impairment amount): Dr TK 2294 — Cr TK 632
5. Impairment is assessed per item, not by inventory category

##### Alternate Flow
- None

##### Exception Flow
- If NRV cannot be reliably estimated, system flags for manual assessment by valuation expert

##### Business Rules
- BR035: NRV = estimated selling price − estimated completion costs − estimated selling costs
- BR036: Provision is calculated per individual item
- BR037: Reversal of provision is capped at the original impairment amount (carrying cost cannot exceed original cost after reversal)
- BR038: Raw materials are not written down below cost if finished product is still profitable (unless material is obsolete)

##### Input Data
- Inventory item cost
- Estimated selling price
- Estimated completion and selling costs

##### Output Data
- Impairment provision calculation worksheet
- Journal entry (Dr/Cr TK 632 — Cr/Dr TK 2294)
- Updated inventory NRV schedule

##### Dependencies
- UC-001, UC-002

##### Frequency
- Quarterly/Annually (minimum at year-end)

##### Priority
- High

##### Compliance Impact
- VAS 02 §8, §11; Circular 99 §11 (TK 229); VAS on impairment

---

#### UC-013: Handle Promotional / Marketing Inventory

##### Description
Account for inventory withdrawn for promotional campaigns, free samples, marketing giveaways, and customer loyalty programs.

##### Goal
Properly classify promotional costs as selling expense or revenue reduction based on transaction substance.

##### Primary Actors
- Accountant
- Marketing Manager

##### Supporting Actors
- Warehouse Keeper

##### Preconditions
- Inventory is available for promotional use
- Promotion campaign is approved

##### Trigger
- Inventory withdrawn for promotional purpose

##### Main Flow
1. User specifies whether promotion is conditional (requires purchase) or unconditional (free gift)
2. If **unconditional** (free gift, no purchase required): Dr TK 641 (selling expense) — Cr TK 152/153/155/156
3. If **conditional** (e.g., buy 2 get 1 free):
   - System allocates revenue between sold and promoted items
   - Revenue recognized for total consideration; promoted item's cost recorded as COGS (TK 632)
   - Substance is a discount, not a separate expense

##### Alternate Flow
- **Donation / charitable giving:** Dr TK 811 — Cr inventory (with VAT implications as applicable)

##### Exception Flow
- None

##### Business Rules
- BR039: Unconditional promotional giveaways are recorded as selling expense (TK 641)
- BR040: Conditional promotions (requiring purchase of main product) are treated as a discount; revenue is allocated proportionally; promoted item cost is COGS
- BR041: Free samples and marketing inventory must be tracked separately for tax purposes

##### Input Data
- Promotion campaign reference
- Item codes, quantities
- Promotion type (conditional/unconditional)
- Fair value of promoted goods

##### Output Data
- Promotion inventory issue slip
- Journal entries
- Tax adjustment report

##### Dependencies
- UC-007

##### Frequency
- Event-driven

##### Priority
- Medium

##### Compliance Impact
- Circular 99 §8; Tax law on promotional goods; VAT treatment of free goods

---

### Domain 8: Returns, Adjustments & Disposals

#### UC-014: Record Inventory Return to Supplier

##### Description
Process the return of defective, excess, or non-conforming inventory to the supplier, including reversal of original receipt and any related VAT adjustments.

##### Goal
Reverse the original purchase transaction for returned goods and update supplier payable.

##### Primary Actors
- Accountant
- Purchasing Department

##### Supporting Actors
- Supplier

##### Preconditions
- Original receipt was recorded
- Supplier agrees to return

##### Trigger
- Return of goods to supplier

##### Main Flow
1. User initiates return with reference to original receipt
2. System reverses original receipt if goods still in stock: Dr TK 111/112/331 — Cr TK 152/153/156 — Cr TK 133 (if VAT was deducted)
3. Goods issued from warehouse to supplier
4. System updates inventory balance and supplier balance

##### Alternate Flow
- **Return of goods already consumed in production:** allocate rebate/return credit proportionally to cost accounts (TK 154/621/623/627/641/642) and inventory (if still in stock)

##### Exception Flow
- None

##### Business Rules
- BR042: Trade discounts and rebates received after initial purchase must be allocated based on inventory status at receipt date
- BR043: VAT adjustment is required when input VAT was claimed on returned goods

##### Input Data
- Original receipt reference
- Returned item codes and quantities
- Reason code
- Supplier credit note

##### Output Data
- Return goods issue note
- Journal entries
- Updated supplier balance

##### Dependencies
- UC-003

##### Frequency
- Event-driven

##### Priority
- High

##### Compliance Impact
- VAT law; Circular 99 — TK 152 §3.2, TK 153 §c

---

#### UC-015: Record Customer Returns / Sales Returns

##### Description
Process goods returned by customers, including reversal of revenue and COGS recognition, and inspection for restocking or scrapping.

##### Goal
Accurately reflect returned goods in inventory and correct previously recognized revenue and COGS.

##### Primary Actors
- Sales Accountant
- Warehouse Keeper

##### Supporting Actors
- Customer
- Quality Inspector

##### Preconditions
- Original sale was recorded
- Customer initiates return per sales contract or policy

##### Trigger
- Customer returns goods

##### Main Flow
1. Warehouse receives returned goods and performs quality inspection
2. If goods are salable, system records: Dr TK 155/156 (at original cost) — Cr TK 632 (reversal of COGS)
3. System records revenue reversal: Dr TK 511 ± TK 3331 — Cr TK 111/112/131
4. Inventory balance increases

##### Alternate Flow
- **Goods damaged beyond repair:** Dr TK 811 (loss) — Cr TK 632; no inventory receipt
- **Goods returned but consumed by customer:** negotiate settlement (credit note only, no physical return)

##### Exception Flow
- None

##### Business Rules
- BR044: Returned goods are valued at original cost if salable; at scrap value if damaged
- BR045: Revenue reversal must be accompanied by proper credit note and VAT adjustment

##### Input Data
- Original sales invoice reference
- Returned item codes, quantities, condition
- Credit note

##### Output Data
- Customer credit note
- Restocking receipt
- Journal entries (revenue reversal, COGS reversal)

##### Dependencies
- UC-007

##### Frequency
- Event-driven

##### Priority
- High

##### Compliance Impact
- VAS 14; Circular 99; VAT credit note regulations

---

#### UC-016: Liquidate / Dispose of Inventory

##### Description
Process the sale or disposal of obsolete, slow-moving, damaged, or excess inventory as scrap or secondary sale.

##### Goal
Recognize gain or loss on inventory disposal and remove from stock records.

##### Primary Actors
- Accountant
- Warehouse Keeper

##### Supporting Actors
- Purchaser / Scrap dealer

##### Preconditions
- Inventory is identified for disposal
- Approval obtained from authorized manager

##### Trigger
- Management decision to dispose of obsolete/scrap inventory

##### Main Flow
1. Revenue recognition: Dr TK 111/112/131 — Cr TK 511 — Cr TK 3331
2. COGS recognition: Dr TK 632 — Cr TK 152/153/155/156
3. Inventory removed from stock records

##### Alternate Flow
- **Fixed assets (tools/equipment below capitalization threshold already treated as inventory):** same as above
- **Capital contribution:** treated as disposal with valuation; Dr TK 221/222 (revalued) — Cr TK 152 (book) — Cr/Dr TK 711/811 (gain/loss)

##### Exception Flow
- If disposal violates regulatory or tax restrictions, system blocks transaction

##### Business Rules
- BR046: Scrap/obsolete inventory sales are taxable revenue subject to VAT
- BR047: COGS is the carrying value at disposal date

##### Input Data
- Items to dispose
- Disposal price
- Approval reference

##### Output Data
- Disposal invoice
- Journal entries
- Stock removal

##### Dependencies
- None (direct)

##### Frequency
- Event-driven

##### Priority
- Low

##### Compliance Impact
- VAT; CIT; Circular 99 — TK 152 §3.13, TK 153 §h, TK 154

---

### Domain 9: Foreign Currency Inventory Transactions

#### UC-017: Handle Foreign Currency Inventory Transactions

##### Description
Process inventory purchases denominated in foreign currency, including prepayments, spot transactions, and exchange difference recognition.

##### Goal
Ensure accurate cost measurement for foreign-currency inventory in compliance with VAS 10 (Foreign Exchange) and Circular 99.

##### Primary Actors
- Accountant
- Treasury Manager

##### Supporting Actors
- Supplier (overseas)
- Bank

##### Preconditions
- Foreign currency bank account or payable exists
- Exchange rate is observable at transaction date

##### Trigger
- Purchase order or invoice in foreign currency

##### Main Flow
1. At purchase/invoice recognition: system records inventory cost at spot exchange rate (transaction date rate)
2. If prepayment was made: prepaid portion uses prepayment date rate; remaining uses transaction date rate
3. At settlement: system records exchange difference via TK 413 (exchange rate difference)
4. At period-end: revalue foreign currency payables at closing rate; record unrealized exchange gain/loss via TK 413/515/635

##### Alternate Flow
- None

##### Exception Flow
- None

##### Business Rules
- BR048: Inventory cost is recorded at spot rate on transaction date (invoice date)
- BR049: Prepayment amount is fixed at prepayment date rate; does not change on delivery
- BR050: Tax values (import duty, VAT on imports) follow tax authority exchange rates
- BR051: Exchange differences on inventory purchases are recognized in TK 413 and may be capitalized per VAS 10

##### Input Data
- Foreign currency invoice
- Exchange rate at transaction date
- Prepayment exchange rate (if applicable)

##### Output Data
- Inventory cost in VND
- Journal entries (purchase, settlement, revaluation)
- Exchange difference report

##### Dependencies
- UC-003

##### Frequency
- Event-driven + Period-end revaluation

##### Priority
- High

##### Compliance Impact
- VAS 10; Circular 99 §10, TK 413; Tax authority exchange rate regulations

---

### Domain 10: Period-End Closing & Reporting

#### UC-018: Perform Period-End Inventory Cut-Off

##### Description
Execute period-end inventory closing procedures: reconcile stock ledger to GL, verify cut-off, accrue for in-transit goods, calculate impairment, and prepare inventory schedules.

##### Goal
Ensure inventory balances are complete, accurate, and properly presented in financial statements.

##### Primary Actors
- Chief Accountant
- Inventory Accountant

##### Supporting Actors
- Auditor (internal/external)

##### Preconditions
- All inventory transactions for the period are posted
- Physical count is completed (or cycle count data available)

##### Trigger
- Month/quarter/year-end closing schedule

##### Main Flow
1. System reconciles inventory sub-ledger (quantity × unit cost) to GL balance for each account (TK 151–158)
2. System identifies and accrues for:
   - Goods in transit (TK 151)
   - Uninvoiced receipts (accrual)
   - Consignment goods with consignee sales reports
3. System calculates and records inventory impairment provision (UC-012)
4. System runs valuation method calculation (weighted average, FIFO) to compute final period costs
5. System generates inventory schedules:
   - Inventory listing by category, location, quantity, cost, NRV
   - Movement schedule (opening + receipts − issues = closing)
   - Impairment provision schedule
   - Aged inventory report (slow-moving / obsolete identification)

##### Alternate Flow
- None

##### Exception Flow
- If reconciliation fails (sub-ledger ≠ GL), system blocks closing and requires resolution

##### Business Rules
- BR052: Inventory sub-ledger must reconcile to GL before period can be closed
- BR053: Cut-off procedures must ensure all inventory receipts/issues are recorded in correct period
- BR054: Long-term inventory (>12 months or >1 operating cycle) must be reclassified from current assets to long-term assets

##### Input Data
- Period parameters (opening/closing dates)
- All transaction data for period

##### Output Data
- Inventory reconciliation report
- Cut-off certificates
- Inventory movement schedule
- Aging report
- Impairment schedule

##### Dependencies
- All preceding use cases

##### Frequency
- Monthly (at minimum)

##### Priority
- Critical

##### Compliance Impact
- VAS 02; VAS 21 (Financial Statement Presentation); Circular 99 §1, §2; Auditing standards

---

## 3. Cross-Use Case Analysis

### Overlapping Use Cases
- UC-003 (Purchase Receipt) and UC-008 (Goods in Transit) share purchase transaction data; transit handling occurs when goods cross period boundaries
- UC-010 (Cost Allocation) and UC-007 (Sale Issue) both depend on valuation method — WIP cost flows to finished goods then to COGS
- UC-011 (Physical Count) and UC-018 (Period-End Closing) — count results must be processed before closing
- UC-012 (Impairment) is invoked during UC-018 (Period-End Closing)

### Shared Dependencies
- **Valuation method** (UC-001): shared by all issue transactions (UC-005, UC-006, UC-007), cost allocation (UC-010), impairment (UC-012)
- **Inventory categories** (UC-002): used by all transaction use cases for account mapping
- **Landed cost logic**: shared by UC-003 (purchase), UC-005 (external processing), UC-017 (foreign currency)

### Workflow Gaps
- No explicit use case for **inter-warehouse transfer** of inventory (logical transfer between storage locations)
- No explicit use case for **inventory reclassification** (e.g., reclassifying raw materials as spare parts, or reclassifying to long-term assets)
- No explicit use case for **consignment sales report processing** from consignees/agents
- No explicit use case for **standard cost variance analysis and disposition**

### Missing Transitions
- **TK 154 → TK 155** (WIP to finished goods): covered but no explicit handling of by-products/waste allocation
- **TK 151 → TK 152/153/156** (transit to warehouse): well covered
- **TK 157 → TK 632** (consignment to COGS): assumes periodic reporting from consignee — no automated trigger
- **Long-term reclassification:** no defined workflow to identify and reclassify slow-moving inventory held >12 months

### Inconsistent Terminology
- Source material uses "Nguyên liệu, vật liệu" for raw materials but also uses "vật tư" for supplies;
- "Công cụ, dụng cụ" includes many sub-types (tools, fixtures, packaging, temporary structures) with different accounting treatments — system must handle sub-class differentiation
- "Sản phẩm" includes both finished goods (thành phẩm) and semi-finished (bán thành phẩm) — separate treatment needed

### Potential System Risks
- **Valuation method consistency:** User may attempt to change method mid-period; system must enforce prospective-only changes
- **Period cut-off errors:** Goods received near period-end may be incorrectly classified as in-transit or received
- **Foreign currency revaluation:** Exchange rate fluctuations at period-end may create material unrealized gains/losses that are incorrectly allocated to inventory cost rather than P&L
- **Promotional goods misclassification:** Incorrect classification between conditional/unconditional promotions may materially misstate revenue
- **Impairment reversal cap:** System must prevent reversal above original impairment amount

---

## 4. Missing Functionalities

### Missing Use Cases
| Use Case | Description | Priority |
|---|---|---|
| Inter-Warehouse Transfer | Move inventory between storage locations without ownership change | Medium |
| Inventory Reclassification | Reclassify between categories (e.g., raw material → spare part, short-term → long-term) | Medium |
| Consignment Sales Report Processing | Receive and process consignee sales reports to trigger COGS and revenue recognition | High |
| Standard Cost Variance Analysis | Calculate and dispose purchase price variance and manufacturing variance | High |
| Batch/Lot Tracking | Track inventory by lot/serial number for expiry, quality, and traceability | Medium |
| Inventory Budget/Forecast | Set inventory targets and monitor actual vs budget | Low |
| Cycle Counting | Continuous cycle counting program for high-value items without full physical count | Medium |

### Missing Validation Rules
- **Date validation:** Receipt date cannot precede purchase order date; issue date cannot precede receipt date
- **Negative stock prevention:** System should block issues exceeding available quantity (configurable override with approval)
- **Tax code validation:** Supplier VAT invoice must map to valid tax codes before input VAT is recorded
- **Consignment age:** System should alert if consignment goods remain unsold beyond agreed period
- **Impairment schedule:** System should enforce that impairment is calculated per individual item, not by category pool

### Missing Approval Flows
- **Impairment provision** above configurable threshold requires chief accountant approval
- **Physical count adjustment** over configurable tolerance requires CFO authorization
- **Write-off of obsolete inventory** requires management approval committee
- **Valuation method change** must be approved by Board of Management and disclosed in financial statement notes

### Missing Audit Trails
- **Inventory movement log:** every receipt, issue, adjustment must record user, timestamp, reason, prior balance, new balance
- **Valuation method change log:** before/after values and impact disclosure
- **Impairment assumption log:** NRV basis, management assumptions, supporting documents reference
- **Physical count history:** count results, investigator, resolution date, adjustment reference

### Missing Error Handling
- **In-transit misclassification:** system should check that goods not received within reasonable period are flagged
- **Cost calculation failure:** if weighted average calculation fails due to data inconsistency, system should notify accountant and prevent closing
- **Exchange rate missing:** if foreign currency rate is missing at transaction date, system should warn and use approximate rate with flag
- **Concurrent period processing:** system should prevent posting to closed period and warn when posting to prior open period

### Missing Compliance Controls
- **Tax authority rate database:** system should maintain official tax authority exchange rates for customs valuation
- **VAT deductibility matrix:** system should enforce VAT deductibility rules per inventory category and transaction type
- **Long-term asset flag:** system should automatically flag inventory held >12 months for reclassification
- **Circular 99 transition:** for enterprises migrating from Circular 200, system should support account mapping and opening balance conversion

---

## 5. Recommended System Modules

| Module | Responsibility |
|---|---|
| **Inventory Master Data** | Categories, items, UOM, storage locations, valuation method config |
| **Purchasing & Receiving** | Purchase orders, goods receipt, landed cost, in-transit tracking |
| **Warehouse Management** | Stock balances, location transfer, picking, cycle counting |
| **Production Costing** | Cost allocation, WIP tracking, unit cost calculation, variance analysis |
| **Sales & Fulfillment** | Sales orders, delivery, COGS, consignment tracking, customer returns |
| **Inventory Valuation** | Valuation engine (FIFO/weighted avg/specific), impairment calculation, standard cost |
| **Physical Count** | Count sheet generation, discrepancy analysis, adjustment processing |
| **Period-End Closing** | Cut-off, reconciliation, reporting, reclassification |
| **Foreign Currency** | Multi-currency support, exchange rate management, revaluation |
| **Tax & Compliance** | VAT deductibility, import duty, tax rate matrix, regulatory reporting |
| **Audit & Controls** | Approval workflows, audit trail, security, compliance monitoring |
| **Reporting** | Inventory listing, movement schedule, aging, impairment report, GL reconciliation |

---

## 6. Suggested Improvements

### Business Improvements
1. **Periodic automatic impairment trigger:** configure NRV thresholds per category; system auto-calculates provision at period-end
2. **Slow-moving / obsolete dashboard:** age inventory by turnover ratio; flag items exceeding configured shelf life
3. **Automated cut-off reconciliation:** system should match purchase orders, goods receipts, and invoices for period-end accruals
4. **Consignment automation:** receive electronic sales reports from consignees; auto-trigger revenue and COGS recognition

### Process Improvements
1. **Multi-level approval matrix:** configure approval thresholds by transaction value and type
2. **Cycle counting program:** prioritize high-value / fast-moving items for more frequent counts
3. **Batch-level traceability:** extend system to track raw material lots through production to finished goods (recall readiness)

### Technical Improvements
1. **Real-time valuation:** maintain weighted average cost after each receipt/issue (perpetual) instead of period-end recalculation
2. **API integration layer:** expose inventory endpoints for ERP, e-commerce, warehouse management system (WMS) integration
3. **Audit-friendly data model:** use slowly-changing dimensions for inventory master; never overwrite historical cost layers
4. **Configurable costing engine:** support different valuation methods per item category with enforcement rules

### Compliance Improvements
1. **Tax authority exchange rate service:** automatic feed of official customs exchange rates for import valuation
2. **Regulatory report generator:** pre-configured inventory schedules for CIT filing, VAT reconciliation, financial statements
3. **VAS 02 disclosure support:** automated preparation of notes to financial statements on inventory accounting policies, impairment, cost of goods sold
4. **Circular 99 ↔ Circular 200 bridge:** migration tools for enterprises transitioning to the new regime, including account mapping, opening balance conversion, prior-period restatement

---

*Document generated via BA/PM/SA analysis of Circular 99/2025/TT-BTC inventory accounting content. Inferred actors, workflows, and missing functionalities are explicitly marked where assumptions were made.*
