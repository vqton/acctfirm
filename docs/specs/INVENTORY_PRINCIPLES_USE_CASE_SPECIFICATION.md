# Use Case Specification

## Inventory Accounting — Circular 99/2025/TT-BTC (Principles)

**Version:** 1.0
**Last Updated:** 2026-05-15
**Regulatory Basis:** Circular 99/2025/TT-BTC, Law on Accounting 2015

---

## 1. Source

- **URL:** https://ketoanthienung.net/nguyen-tac-ke-toan-hang-ton-kho-theo-thong-tu-99.htm
- **Domain Context:** Inventory Accounting (Hàng tồn kho) under Vietnamese SME Enterprise Accounting Regime — Circular 99/2025/TT-BTC, effective 01 January 2026, replacing Circular 200/2014/TT-BTC.
- **Regulatory Context:** VAS 02 (Inventory), VAS 21 (Financial Statement Presentation), Tax laws on VAT deductibility, import duty, excise tax, environmental protection tax, natural resource tax.
- **Analysis Summary:** The source defines 13 foundational principles governing inventory accounting: classification, recognition boundaries, cost composition, valuation methods, foreign currency handling, impairment, physical count, perpetual vs periodic systems, and promotional goods treatment. These principles apply uniformly across all inventory accounts (TK 151–158).

---

## 2. Domain Breakdown

---

### Domain 1: Inventory Classification & Recognition

#### UC-001: Classify Inventory by Category and Holding Period

##### Description
Enterprise classifies inventory items into prescribed categories (goods in transit, raw materials, tools, WIP, finished goods, merchandise, consignment goods, bonded warehouse materials) and determines balance sheet presentation based on expected holding period.

##### Goal
Ensure inventory is correctly categorized and presented as current or long-term asset in accordance with VAS 21.

##### Primary Actors
- Accountant
- Chief Accountant

##### Supporting Actors
- Warehouse Manager

##### Preconditions
- Chart of accounts is configured with TK 151–158
- Inventory items are registered in the system

##### Trigger
- Initial inventory setup
- Periodic review at each reporting period-end

##### Main Flow
1. Accountant receives inventory item from purchasing or production
2. System classifies item into category based on item master configuration:
   - TK 151: Goods in transit (ownership transferred, not yet received)
   - TK 152: Raw materials, main and sub-materials, fuel, spare parts, construction materials
   - TK 153: Tools, fixtures, packaging, temporary structures, office supplies, PPE
   - TK 154: Work in process
   - TK 155: Finished goods and semi-finished goods
   - TK 156: Merchandise for trading
   - TK 157: Goods sent on consignment
   - TK 158: Materials at bonded warehouse
3. System evaluates expected holding period:
   - If holding period ≤ 12 months or ≤ one operating cycle → classify as current asset
   - If holding period > 12 months or > one operating cycle → reclassify as long-term asset

##### Alternate Flow
- **Spare parts with >12 month reserve life:** System flags for long-term asset reclassification
- **WIP with >1 production cycle:** System flags for long-term asset reclassification

##### Exception Flow
- If item category is ambiguous, system routes to chief accountant for manual classification

##### Business Rules
- BR01: Inventory with holding period >12 months or >1 operating cycle must NOT be presented as current asset on balance sheet
- BR02: Non-owned inventory (consignment, custody, processing, warehousing) must NOT be recorded in TK 151–158
- BR03: Non-owned inventory must be tracked off-balance-sheet and disclosed in financial statement notes
- BR04: Raw materials sub-classification: main materials, sub-materials, fuel, spare parts, construction materials

##### Postconditions
- Inventory classified by category
- Current/long-term asset flag assigned
- Non-owned inventory tracked off-balance-sheet

##### Input Data
- Item code, category code, quantity, unit cost
- Purchase/production date
- Expected holding period

##### Output Data
- Inventory classification record
- Current/long-term asset flag
- Off-balance-sheet tracking record (for non-owned inventory)

##### Dependencies
- UC-004 (Configure Valuation Method)

##### Frequency
- Event-driven (at receipt) + periodic review at period-end

##### Priority
- Critical

##### Compliance Impact
- VAS 02 §6, VAS 21; Circular 99 §1, §2, §3

---

#### UC-002: Identify and Record Non-Owned Inventory Off-Balance-Sheet

##### Description
Record inventory held by the enterprise but not owned (custody, consignment received, processing received, import/export commission) in off-balance-sheet records with full disclosure.

##### Goal
Prevent misstatement of inventory assets on balance sheet; provide auditor and regulator visibility into non-owned stock.

##### Primary Actors
- Accountant
- Warehouse Keeper

##### Supporting Actors
- Third party (consignor, principal, customer)

##### Preconditions
- Physical goods are present in enterprise premises or under enterprise control
- Legal ownership resides with external party

##### Trigger
- Receipt of goods under custody/consignment/processing agreement

##### Main Flow
1. Warehouse keeper receives goods and identifies non-ownership basis from accompanying documents
2. System records in off-balance-sheet memo account (not TK 151–158)
3. System tracks quantity, value, owner, agreement reference, location
4. At period-end, system includes disclosure note in financial statements

##### Alternate Flow
- None

##### Exception Flow
- If ownership is disputed or unclear, system blocks inventory recognition and routes to chief accountant

##### Business Rules
- BR05: Custody, consignment, processing, and import/export commission goods are NOT enterprise inventory
- BR06: Off-balance-sheet records must track: product, quantity, owner, agreement date, location
- BR07: Disclosure in financial statement notes is mandatory

##### Postconditions
- Non-owned inventory recorded off-balance-sheet
- Disclosure note prepared

##### Input Data
- Non-ownership agreement reference
- Item codes and quantities
- Owner details
- Warehouse location

##### Output Data
- Off-balance-sheet inventory record
- Period-end disclosure note

##### Dependencies
- UC-001

##### Frequency
- Event-driven

##### Priority
- High

##### Compliance Impact
- Circular 99 §3; VAS 02 §6; VAS 21 §Disclosure

---

### Domain 2: Inventory Cost Determination

#### UC-003: Determine Inventory Cost (Landed Cost)

##### Description
Calculate and record the full landed cost of purchased, self-manufactured, or externally processed inventory, including all directly attributable costs and non-refundable taxes.

##### Goal
Ensure inventory is initially recognized at cost per VAS 02 §5–7, including all costs to bring the asset to its present location and condition.

##### Primary Actors
- Accountant

##### Supporting Actors
- Purchasing Department
- Customs Broker (for imports)

##### Preconditions
- Purchase invoice or production order exists
- Cost elements are documented

##### Trigger
- Inventory receipt (purchase, self-manufacture, external processing)

##### Main Flow
1. Accountant identifies cost components based on source:
   - **Purchased:** invoice price + non-refundable taxes (import duty, excise, environmental tax, natural resource tax) + freight + insurance + handling + other directly attributable costs
   - **Self-manufactured:** raw material cost + direct labor + production overhead (fixed + variable)
   - **Externally processed:** raw material cost + processing fee + transport + other directly attributable costs
   - **Capital contribution:** value agreed by contributing parties
2. System determines VAT treatment:
   - If VAT is deductible → exclude from cost (record in TK 133)
   - If VAT is non-deductible → include in cost
3. System accumulates all cost elements into landed cost
4. System records journal entry at landed cost

##### Alternate Flow
- **Bonus/free items received with purchase:** System allocates fair value to bonus item. If enterprise does not intend to sell or use separately, bonus item may be recorded at nil value.
- **Foreign currency purchase:** Cost recorded at spot rate at transaction date; prepayment portion at prepayment date rate

##### Exception Flow
- If cost cannot be reliably measured, system blocks receipt and routes to chief accountant

##### Business Rules
- BR08: Landed cost = invoice price + non-refundable taxes + freight + insurance + handling + direct purchasing costs
- BR09: VAT on imports: if deductible → exclude (Dr TK 133); if non-deductible → include in cost
- BR10: Import duty, excise tax, environmental tax, natural resource tax are always included in inventory cost (non-refundable)
- BR11: Settlement discounts (early payment) are financial income (TK 515), NOT inventory cost reduction
- BR12: Trade discounts and rebates received after purchase must be allocated: reduce cost of inventory still on hand; reduce cost of inventory already consumed; reduce COGS of inventory already sold
- BR13: Bonus items received with purchase must be recorded at fair value unless enterprise does not intend to sell/use separately

##### Postconditions
- Landed cost calculated
- Journal entry posted at landed cost

##### Input Data
- Purchase invoice or production cost report
- Tax amounts (VAT, import duty, excise, environmental, resource)
- Add-on cost invoices (freight, insurance, handling)
- Exchange rate (for foreign currency)

##### Output Data
- Landed cost calculation
- Journal entry (Dr TK 152/153/155/156 ± TK 133 — Cr TK 111/112/141/331)

##### Dependencies
- UC-004 (Valuation Method)

##### Frequency
- Event-driven (each receipt)

##### Priority
- Critical

##### Compliance Impact
- VAS 02 §5, §7; Circular 99 §5, §6, §7, §10; Tax law on VAT, import duty, excise, environmental tax

---

### Domain 3: Inventory Valuation Methods

#### UC-004: Configure and Apply Valuation Method

##### Description
Enterprise selects and applies a consistent cost-flow assumption for inventory valuation from five permitted methods: Specific Identification, Weighted Average (periodic or perpetual), FIFO, Retail Method, Standard Cost.

##### Goal
Establish consistent cost-flow assumption for inventory valuation and COGS determination.

##### Primary Actors
- Chief Accountant

##### Supporting Actors
- System Administrator

##### Preconditions
- Enterprise accounting regime is configured (Circular 99)
- Inventory categories are defined

##### Trigger
- Enterprise setup or accounting policy change

##### Main Flow
1. Chief Accountant selects valuation method for each inventory category or globally
2. System validates:
   - Method is applied consistently across periods
   - Method is one of five permitted methods
3. System records policy election with effective date
4. System enforces selected method for all transactions

##### Alternate Flow
- **Method change:** System requires documented justification. System applies prospectively from change date. System logs change with auditor trail.

##### Exception Flow
- If selected method violates VAS 02, system rejects

##### Business Rules
- BR14: Five permitted methods: (a) Specific Identification, (b) Weighted Average, (c) FIFO, (d) Retail, (e) Standard Cost
- BR15: Method must be applied consistently across periods unless policy change is formally documented
- BR16: Weighted Average may be calculated per period or per receipt batch
- BR17: FIFO assumes earliest purchased/produced items are sold first
- BR18: Retail method is for high-volume, fast-moving retail businesses; cost = selling price − reasonable profit margin
- BR19: Standard Cost must be periodically reviewed and adjusted to approximate actual cost
- BR20: Specific Identification is for low-volume, identifiable, stable items

##### Postconditions
- Valuation method configured per inventory category
- Policy election recorded with effective date

##### Input Data
- Valuation method code per item category
- Effective date
- Justification (if changing method)

##### Output Data
- Valuation policy configuration
- Audit log entry

##### Dependencies
- Inventory items must have cost layers or tracking data

##### Frequency
- Once at setup; rarely changed

##### Priority
- Critical

##### Compliance Impact
- VAS 02 §4, §9, §11; Circular 99 §9

---

### Domain 4: Inventory Accounting Systems

#### UC-005: Operate Perpetual Inventory System

##### Description
Maintain continuous, real-time tracking of inventory quantities and values through systematic recording of every receipt and issue transaction.

##### Goal
Ensure inventory book balance is always available at any point during the period.

##### Primary Actors
- Accountant
- Warehouse Keeper

##### Supporting Actors
- System (automatic)

##### Preconditions
- All inventory transactions are recorded through the system
- Physical count is performed at period-end for verification

##### Trigger
- Each inventory receipt, issue, transfer, or adjustment

##### Main Flow
1. System records each receipt transaction: Dr Inventory — Cr AP/Cash
2. System records each issue transaction: Dr Cost/Expense — Cr Inventory
3. System calculates issue cost using configured valuation method in real-time
4. System maintains continuous book balance (quantity + value)
5. At period-end, physical count is performed
6. System compares physical count to book balance
7. If discrepancy exists → UC-010 (Investigate Inventory Discrepancy)

##### Alternate Flow
- None

##### Exception Flow
- None

##### Business Rules
- BR21: Book balance must equal physical quantity at all times; any variance requires investigation
- BR22: Perpetual system is required for manufacturing enterprises, construction enterprises, and high-value goods trading
- BR23: Issue cost is calculated per transaction (not at period-end)

##### Postconditions
- Continuous inventory book balance maintained
- Issue cost calculated per transaction

##### Input Data
- All receipt, issue, transfer, adjustment transactions

##### Output Data
- Real-time inventory book balance
- Transaction journal entries
- Period-end reconciliation report

##### Dependencies
- UC-004 (Valuation Method)
- UC-010 (Physical Count)

##### Frequency
- Continuous/Real-time

##### Priority
- High

##### Compliance Impact
- Circular 99 §13a

---

#### UC-006: Operate Periodic Inventory System

##### Description
Determine inventory quantities and values at period-end through physical count, and calculate issues as residual: Opening + Receipts − Closing = Issues.

##### Goal
Provide simplified inventory accounting for low-value, high-variety items where perpetual tracking is impractical.

##### Primary Actors
- Accountant

##### Supporting Actors
- Warehouse Keeper
- Count Team

##### Preconditions
- Physical count is conducted at period-end
- Opening balance and receipts are accurately recorded

##### Trigger
- Period-end closing procedure

##### Main Flow
1. System accumulates all receipts for the period
2. Physical count is conducted at period-end (UC-010)
3. System calculates issues: Issues = Opening + Receipts − Closing
4. System records COGS/expense based on calculated issues
5. System updates GL with closing balance

##### Alternate Flow
- None

##### Exception Flow
- If physical count is unreliable, system flags for reconciliation

##### Business Rules
- BR24: Issued quantity/value = Opening + Receipts − Closing (physical count)
- BR25: Periodic system is for high-volume, low-value items with many variations (retail)
- BR26: Accuracy depends on physical count and warehouse management quality

##### Postconditions
- Issues calculated as residual
- Period-end inventory balance determined

##### Input Data
- Opening inventory balance
- All receipt documents for the period
- Physical count results

##### Output Data
- Calculated issues for the period
- Period-end inventory balance
- COGS journal entry

##### Dependencies
- UC-010 (Physical Count)

##### Frequency
- Period-end (monthly/quarterly/yearly)

##### Priority
- Medium

##### Compliance Impact
- Circular 99 §13b

---

### Domain 5: Physical Inventory & Adjustment

#### UC-007: Perform Physical Inventory Count

##### Description
Conduct systematic physical count of all inventory items, compare results to book records, and resolve discrepancies.

##### Goal
Verify physical stock exists and matches accounting records.

##### Primary Actors
- Warehouse Keeper
- Count Team
- Accountant

##### Supporting Actors
- Internal Auditor

##### Preconditions
- Count is scheduled
- Count teams and assignments are prepared

##### Trigger
- Period-end closing, or cycle count trigger

##### Main Flow
1. System generates count sheets per warehouse and location
2. Count teams physically count and record quantities
3. System loads count results and compares to book balance
4. For each discrepancy, system calculates variance

##### Alternate Flow
- None

##### Exception Flow
- If systems cannot be stopped during count, system supports cycle counting for high-value items

##### Business Rules
- BR27: Physical count at minimum at each period-end
- BR28: Count results documented in official count minutes

##### Postconditions
- Physical count results compared to book
- Variance report generated

##### Input Data
- Count sheets with physical quantities
- Book quantities from system

##### Output Data
- Count variance report
- Adjustment journal entries

##### Dependencies
- UC-010 (Investigate Discrepancy)

##### Frequency
- Monthly/Quarterly/Yearly or continuous

##### Priority
- Critical

##### Compliance Impact
- Circular 99 §12, §13

---

#### UC-008: Investigate and Adjust Inventory Discrepancy

##### Description
Analyze count variances, determine root cause, and record adjustment entries for surpluses and deficits.

##### Goal
Restore accuracy between physical stock and book records.

##### Primary Actors
- Accountant
- Inventory Controller

##### Supporting Actors
- Internal Auditor
- Department Manager

##### Preconditions
- Physical count (UC-007) completed
- Variance report generated

##### Trigger
- Discrepancy identified in count comparison

##### Main Flow
1. Accountant reviews variance report
2. For surpluses: Dr Inventory — Cr TK 3381 (waiting for resolution) — then upon approval Cr appropriate account
3. For deficits:
   - Within tolerance: Dr TK 632 — Cr Inventory
   - Attributable to responsible person: Dr TK 1388/334 — Cr Inventory
   - Unidentified: Dr TK 1381 — Cr Inventory
4. Upon resolution: Dr TK 632/111/1388/334 — Cr TK 1381

##### Alternate Flow
- None

##### Exception Flow
- If variance exceeds configurable threshold, system requires chief accountant approval before posting

##### Business Rules
- BR29: Surplus: Dr Inventory — Cr TK 3381 (pending)
- BR30: Deficit within tolerance: Dr TK 632
- BR31: Deficit attributable to person: Dr TK 1388/334
- BR32: Unidentified deficit: Dr TK 1381 (pending investigation)
- BR33: Upon resolution, close TK 1381/3381 to appropriate final account

##### Postconditions
- Inventory adjusted to physical count
- Discrepancy suspense cleared

##### Input Data
- Variance report
- Investigation findings
- Approval decision

##### Output Data
- Adjustment journal entries
- Updated stock records

##### Dependencies
- UC-007

##### Frequency
- Event-driven (after each count)

##### Priority
- Critical

##### Compliance Impact
- VAS 02; Circular 99 §12

---

### Domain 6: Inventory Impairment

#### UC-009: Calculate and Record Inventory Impairment Provision

##### Description
Assess net realizable value (NRV) of each inventory item at period-end and record provision if NRV < carrying cost.

##### Goal
Ensure inventory is not carried above recoverable amount.

##### Primary Actors
- Chief Accountant

##### Supporting Actors
- Sales Department (for NRV estimation)

##### Preconditions
- Inventory cost is known
- NRV can be reliably estimated

##### Trigger
- Period-end closing

##### Main Flow
1. For each inventory item, accountant determines:
   - Carrying cost (book value)
   - NRV = estimated selling price − estimated completion costs − estimated selling costs
2. If carrying cost > NRV → provision needed
3. System records: Dr TK 632 — Cr TK 2294
4. If NRV recovers in subsequent period → reversal: Dr TK 2294 — Cr TK 632 (capped at original impairment)

##### Alternate Flow
- None

##### Exception Flow
- If NRV cannot be reliably estimated, system flags for manual assessment

##### Business Rules
- BR34: NRV = estimated selling price − estimated completion costs − estimated selling costs
- BR35: Provision calculated per individual item, not by category
- BR36: Reversal capped at original impairment amount
- BR37: Raw materials not written down if finished product is profitable (unless obsolete)

##### Postconditions
- Impairment provision calculated per item
- Journal entry posted (Dr/Cr TK 632 — Cr/Dr TK 2294)

##### Input Data
- Item carrying cost
- Estimated selling price
- Estimated completion costs
- Estimated selling costs

##### Output Data
- Impairment provision calculation
- Journal entry (Dr/Cr TK 632 — Cr/Dr TK 2294)

##### Dependencies
- UC-004 (Valuation Method)

##### Frequency
- Quarterly/Annually (minimum at year-end)

##### Priority
- High

##### Compliance Impact
- VAS 02 §8; Circular 99 §11; TK 229

---

### Domain 7: Promotional & Marketing Inventory

#### UC-010: Account for Promotional Inventory

##### Description
Record inventory withdrawn for promotional campaigns, distinguishing between unconditional giveaways (selling expense) and conditional promotions (revenue allocation).

##### Goal
Properly classify promotional costs in accordance with transaction substance.

##### Primary Actors
- Accountant
- Marketing Manager

##### Supporting Actors
- Warehouse Keeper

##### Preconditions
- Inventory is available
- Promotion campaign is approved

##### Trigger
- Inventory withdrawn for promotional purpose

##### Main Flow
1. Accountant determines promotion type:
   - **Unconditional** (free gift, no purchase required): Dr TK 641 — Cr Inventory
   - **Conditional** (requires purchase of main product): allocate consideration between sold and promoted items; promoted item cost → Dr TK 632
2. System records appropriate journal entry

##### Alternate Flow
- None

##### Exception Flow
- None

##### Business Rules
- BR38: Unconditional promotional giveaways → selling expense (TK 641)
- BR39: Conditional promotions → revenue is allocated; promoted item cost is COGS (TK 632)
- BR40: Substance over form: a "buy 2 get 1 free" is a discount, not a donation

##### Postconditions
- Promotional inventory issued
- Selling expense or COGS recorded

##### Input Data
- Promotion campaign reference
- Item codes and quantities
- Promotion type (conditional/unconditional)
- Fair value of promoted item

##### Output Data
- Promotion inventory issue
- Journal entry
- Tax adjustment report

##### Dependencies
- UC-006 (Goods Issue)

##### Frequency
- Event-driven

##### Priority
- Medium

##### Compliance Impact
- Circular 99 §8; VAT law on promotional goods

---

### Domain 8: Foreign Currency Inventory

#### UC-011: Account for Foreign Currency Inventory Purchases

##### Description
Record inventory purchased in foreign currency at appropriate exchange rates, including prepayment and period-end revaluation.

##### Goal
Ensure accurate VND cost measurement for foreign-currency inventory.

##### Primary Actors
- Accountant

##### Supporting Actors
- Treasury Manager

##### Preconditions
- Purchase invoice is in foreign currency
- Exchange rate is observable at transaction date

##### Trigger
- Foreign currency purchase transaction

##### Main Flow
1. At purchase: system records inventory at spot rate (transaction date rate)
2. If prepayment made: prepaid portion at prepayment date rate; remaining at transaction date rate
3. Tax amounts calculated at tax authority exchange rate
4. At settlement: exchange difference → TK 413
5. At period-end: revalue foreign currency payable at closing rate

##### Alternate Flow
- None

##### Exception Flow
- None

##### Business Rules
- BR41: Inventory cost recorded at spot rate on transaction date
- BR42: Prepayment fixed at prepayment date rate (does not change on delivery)
- BR43: Tax values follow tax authority exchange rate
- BR44: Exchange differences on inventory purchases → TK 413

##### Postconditions
- FC inventory cost recorded in VND
- Exchange differences tracked

##### Input Data
- Foreign currency invoice
- Exchange rate at transaction date
- Prepayment exchange rate (if applicable)
- Period-end closing rate

##### Output Data
- Inventory cost in VND
- Journal entries (purchase, settlement, revaluation)
- Exchange difference report

##### Dependencies
- UC-003 (Landed Cost)

##### Frequency
- Event-driven + period-end revaluation

##### Priority
- High

##### Compliance Impact
- Circular 99 §10; VAS 10; TK 413

---

### Domain 9: Dual Tracking (Quantity & Value)

#### UC-012: Maintain Dual Inventory Records (Quantity + Value)

##### Description
Maintain simultaneous tracking of inventory by physical quantity and monetary value, at item, category, and location level.

##### Goal
Ensure perpetual reconciliation between physical stock and financial records.

##### Primary Actors
- System (automatic)
- Accountant

##### Supporting Actors
- Warehouse Keeper

##### Preconditions
- Inventory master data is complete with unit of measure and cost

##### Trigger
- Every inventory transaction (receipt, issue, transfer, adjustment)

##### Main Flow
1. Each transaction updates both quantity and value records simultaneously
2. System maintains:
   - Quantity ledger (by item, warehouse, location)
   - Value ledger (by item, category, GL account)
3. System validates quantity and value always move in same direction
4. At any point, unit cost can be derived: value ÷ quantity

##### Alternate Flow
- None

##### Exception Flow
- If quantity and value diverge (e.g., cost = 0 but quantity > 0), system alerts

##### Business Rules
- BR45: Every transaction must update quantity AND value simultaneously
- BR46: Quantity and value must always reconcile: value = quantity × unit cost
- BR47: Tracking granularity: by item, by specification, by warehouse/location

##### Postconditions
- Dual records maintained (quantity + value)
- Unit cost derivable at any point

##### Input Data
- Transaction quantity and value

##### Output Data
- Updated quantity and value records
- Real-time unit cost

##### Dependencies
- All inventory transaction use cases

##### Frequency
- Continuous

##### Priority
- Critical

##### Compliance Impact
- Circular 99 §12

---

## 3. Cross-Use Case Analysis

### Overlapping Use Cases
- UC-003 (Landed Cost) and UC-011 (Foreign Currency) share FX rate logic
- UC-004 (Valuation Method) is consumed by UC-005 and UC-006 (both inventory systems)
- UC-007 (Physical Count) and UC-008 (Discrepancy) must execute as a sequential pair
- UC-009 (Impairment) depends on UC-004 (valuation) for carrying cost
- UC-010 (Promotional) overlaps with UC-003 (cost) for fair value allocation

### Shared Dependencies
- **Valuation method** (UC-004): shared by all issue calculations (UC-005, UC-006)
- **Cost determination** (UC-003): shared by all receipt types (purchase, manufacture, processing)
- **Classification** (UC-001): prerequisite for all downstream inventory transactions

### Workflow Gaps
- No explicit use case for **inter-warehouse transfer** (ownership unchanged, location changes)
- No explicit use case for **inventory reclassification between GL accounts** (e.g., reclassify raw material to spare part, or current to long-term)
- No explicit use case for **free-of-charge inventory receipt** (donation, discovery)

### Inconsistent Terminology
- The source uses "vật tư" (supplies) and "nguyên liệu, vật liệu" (raw materials) interchangeably in some contexts
- "Sản phẩm" includes both finished goods and semi-finished goods — system must distinguish for costing

### Potential System Risks
- **VAT deductibility matrix:** Incorrect classification of deductible vs non-deductible VAT can misstate inventory cost
- **Standard cost variance:** Without periodic review, standard cost can drift far from actual cost
- **Foreign currency prepayment:** Rate locked at prepayment date can create mismatch if delivery is significantly delayed
- **Impairment reversal cap:** System must prevent reversal above original impairment amount

---

## 4. Missing Functionalities

### Missing Use Cases
| Use Case | Description | Priority |
|---|---|---|
| Inter-Warehouse Transfer | Change location without ownership change | Medium |
| Inventory Reclassification | Change GL account mapping (e.g., raw material → spare part) | Medium |
| Standard Cost Variance Disposition | Calculate and dispose purchase/manufacturing variance | High |
| Free-of-Charge Inventory Receipt | Record donated or discovered inventory at fair value | Low |

### Missing Validation Rules
- Receipt date cannot precede PO date
- Issue quantity cannot exceed available quantity (configurable override)
- For FIFO/Weighted Average: cost layers must be tracked; system must prevent negative layers
- Physical count quantities must be non-negative

### Missing Approval Flows
- Write-off of obsolete inventory above configurable threshold requires CFO authorization
- Valuation method change must be approved by Board of Management
- Physical count adjustment over tolerance requires chief accountant approval

### Missing Audit Trails
- Every inventory transaction must record: user, timestamp, before/after quantity and value
- Cost layer changes (FIFO layer consumption, weighted average recalculation) must be logged

### Missing Compliance Controls
- Long-term asset flag: system must auto-flag inventory held >12 months for reclassification
- VAT deductibility matrix per item category and transaction type

---

## 5. Recommended System Modules

| Module | Responsibility |
|---|---|
| **Inventory Master Data** | Categories, items, UOM, valuation method config |
| **Purchasing & Receiving** | PO, goods receipt, landed cost, in-transit tracking (TK 151) |
| **Warehouse Management** | Stock balance, location transfer, picking, cycle counting |
| **Valuation Engine** | FIFO, Weighted Average, Specific ID, Standard Cost, Retail |
| **Physical Count** | Count sheet generation, discrepancy analysis, adjustment |
| **Impairment** | NRV assessment, provision calculation, reversal tracking |
| **Promotional Inventory** | Conditional/unconditional promotion handling |
| **Foreign Currency** | Multi-currency rate management, prepayment tracking |
| **Period-End Closing** | Cut-off, reconciliation, reporting, long-term reclassification |
| **Audit & Compliance** | Audit trail, approval workflows, VAT deductibility matrix |
| **Reporting** | Inventory listing, movement schedule, aging, NRV schedule |

---

## 6. Suggested Improvements

### Business Improvements
1. **Real-time valuation:** Maintain weighted average after each transaction (perpetual) instead of period-end batch calculation
2. **Slow-moving / obsolete dashboard:** Age inventory by turnover ratio; auto-flag items exceeding shelf life
3. **Automated NRV feed:** Configure NRV thresholds per category; system auto-calculates provision at period-end

### Process Improvements
1. **Multi-level approval matrix:** Configure approval thresholds by transaction value and type
2. **Cycle counting program:** Prioritize high-value items for more frequent counts

### Technical Improvements
1. **Cost layer transparency:** Show FIFO layer consumption details on issue transactions
2. **Standard cost variance dashboard:** Real-time view of purchase price variance and manufacturing variance

### Compliance Improvements
1. **Tax authority rate feed:** Automatic import of official customs exchange rates for import valuation
2. **VAS 02 disclosure support:** Automated preparation of notes to financial statements on inventory policies

---

*Document generated via BA/SA analysis of Circular 99/2025/TT-BTC inventory principles (single source). All use cases derived from the 13 principles in the source material. Actors, workflows, and missing functionalities inferred where content was implicit.*
