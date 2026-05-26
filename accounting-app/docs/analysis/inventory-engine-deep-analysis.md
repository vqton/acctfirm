# Inventory Engine — Deep Business Analysis

> Author: Chief Accountant (20.000+ hours, SME Vietnam)
> Regulation base: Circular 99/2025/TT-BTC, VAS 02, Circular 200/2014/TT-BTC
> Status: Final
> Date: 2026-05

---

## 1. Inventory Engine Brain Logic

### 1.1 Why Inventory Engine Exist

Inventory is largest asset on balance sheet for most SME (30-60% total asset). 
Wrong inventory = wrong FS = wrong tax = wrong business decision.

Inventory Engine exist to:
- Track every gram moving in/out warehouse
- Calculate correct COGS (giá vốn hàng bán)
- Prevent stock fraud (biggest SME risk after cash)
- Support warehouse staff, not replace their brain
- Produce audit trail tax authority accept
- Catch mismatch early before period close

### 1.2 How Stock Move

Only 4 stock movements in accounting:
1. **IN** — purchase receipt, production finished, customer return, count surplus
2. **OUT** — sales issue, production consumption, supplier return, count shortage
3. **TRANSFER** — warehouse A → warehouse B (same legal entity)
4. **ADJUST** — correction, write-off, damaged, expired, missing

Each movement MUST have:
- Legal document (invoice, delivery order, receipt note)
- Physical mover (storekeeper + accountant sign)
- Timestamp (real date, not book date)
- Authorization (who approved)

### 1.3 How Stock Validate

Stock validation chain (Vietnam regulation):
```
Physical count = Storekeeper card = Accounting book = Tax declaration
```

Break any link = problem. Engine must validate at every transaction:
- **Quantity check**: cannot issue more than available (except supervisor override)
- **Cost check**: unit cost must have inbound source
- **Document check**: receipt must reference purchase order or invoice
- **Date check**: cannot post to closed period
- **Duplicate check**: same invoice cannot be posted twice

### 1.4 How Inventory Calculate

Three layers of calculation:

**Layer 1 — Perpetual (real-time)**
- Every IN updates stock card immediately
- Every OUT updates stock card immediately
- Running balance visible at any time
- Used by: trading SME, retail, distribution

**Layer 2 — Periodic (month-end)**
- Stock movements tracked during month
- Cost calculated at month-end
- Used by: manufacturing SME (need cost allocation first)

**Layer 3 — Hybrid (Vietnam reality)**
- Quantity tracked perpetually (storekeeper)
- Value calculated periodic (kế toán)
- Most common in Vietnamese SME
- Circular 99 allows this (Khoản 13, Điều 23)

### 1.5 How Costing Work

VAS 02 + TT 99 require 4 costing methods:

**FIFO (Nhập trước — Xuất trước)**
- Oldest stock goes out first
- COGS reflects older prices
- Ending inventory reflects newer prices
- Good for: goods with expiry, rising market
- Tax impact: lower COGS in inflation → higher profit → higher tax

**Moving Average (Bình quân gia quyền liên hoàn)**
- Unit cost recalculated after each receipt
- COGS always at average
- Good for: stable price goods, any SME
- Most practical for 80% Vietnamese SME

**Periodic Average (Bình quân cuối kỳ)**
- Unit cost calculated once at month-end
- Simpler but less accurate
- Cannot know COGS during month
- Risk: negative stock value if prices fluctuate

**Specific Identification (Thực tế đích danh)**
- Each unit tracked individually
- Most accurate, most expensive
- Good for: cars, machinery, real estate, jewelry

### 1.6 How Reservation Work

Reservation logic for SME:

- **Soft reservation**: sales order creates hold on stock qty (not yet real movement)
- **Hard reservation**: picking slip locks specific batch/lot
- Engine MUST prevent double-allocation of same unit
- Vietnam SME often skip reservation → leads to negative stock
- Minimum: check available qty BEFORE confirming shipment

### 1.7 How Allocation Work

Allocation = matching OUT movement to specific cost layer(s)

- FIFO: allocate oldest cost layer first
- AVCO: use current average, no layer tracking needed
- Specific: allocate exact cost of exact unit
- Engine must handle partial allocation (issue 100 units from 3 different layers)

### 1.8 How Reconciliation Happen

Month-end reconciliation chain:

```
Step 1: Storekeeper card → Physical count
Step 2: Storekeeper card → Accounting stock card  
Step 3: Accounting stock card → GL balance (TK 152/153/155/156)
Step 4: GL balance → Trial balance
Step 5: Trial balance → FS
```

Each step must reconcile to 0 difference.
If not → Engine flags and block period close.

### 1.9 How Adjustment Happen

Adjustment only allowed after:
1. Approved adjustment request (signed paper)
2. Physical count completed
3. Manager authorization

Adjustment types:

| Type | Debit | Credit | Effect |
|---|---|---|---|
| Surplus | TK 156/152 | TK 711 | Increase income |
| Shortage (normal) | TK 632 | TK 156/152 | Increase COGS |
| Shortage (theft) | TK 138 | TK 156/152 | Receivable from employee |
| Shortage (insurance) | TK 1388 | TK 156/152 | Insurance claim |
| Damage | TK 632 | TK 156/152 | Expense |

### 1.10 How Audit Tracking Work

Mandatory audit trail for every stock movement:

- Who created? (user ID, timestamp, IP)
- Who approved? (separate from creator)
- What changed? (before/after values)
- Why? (reason code: PURCHASE/SALE/TRANSFER/ADJUST/RETURN/WRITEOFF)
- Which document? (invoice number, PO number, delivery note)
- Cost layer affected? (layer ID, qty consumed, unit cost)
- GL posting? (journal entry ID, debit/credit accounts)

### 1.11 How Compliance Checking Work

Regulation compliance checks at 3 levels:

**Transaction level** (immediate):
- Duplicate invoice check
- Valid account code
- Period open check
- Stock availability check

**Daily level** (end of day):
- Negative stock check
- Cost mismatch check
- Zero qty non-zero value check

**Period level** (before close):
- Inventory balance = GL balance
- Impairment provision adequacy
- Valuation method consistency
- Physical count completion
- Supporting document completeness

---

## 2. Real SME Inventory Scenarios

### 2.1 Purchase Receipt

**Reality**: Goods arrive with delivery order. Invoice may come same day or later. 
Goods must be physically checked before booking.

**Pain**: "Hàng về trước, hóa đơn về sau" = goods arrive before invoice. 
Storekeeper receives goods but accountant cannot book without invoice.
Result: negative stock or unrecorded receipt.

**Engine must handle**: 
- Receipt on delivery order (no invoice yet) — temporary receipt
- Receipt on invoice — definitive receipt
- Receipt with partial delivery — split receipt across documents
- Receipt with foreign currency — exchange rate at date of receipt (TT99)

### 2.2 Sales Issue

**Reality**: Warehouse issues goods based on delivery order.
Accountant books COGS after sales invoice issued.
Timing mismatch = stock already gone but COGS not yet booked.

**Engine must handle**:
- Issue with invoice — immediate COGS booking
- Issue without invoice (goods on consignment, trial) — temporary issue
- Issue with discount/promotion — adjust COGS or separate promo account
- Issue with batch/lot selection — specific or FIFO

### 2.3 Transfer Warehouse

**Reality**: Moving goods from warehouse A to warehouse B.
Same legal entity, no revenue recognition.
But physical goods leave A before arriving at B.

**Engine must handle**:
- Transfer in transit (goods left A, not yet at B) — track on TK 156 (sub-account)
- Transfer with different cost — same cost moves, no revaluation
- Transfer between branches (different tax codes) — must have inter-branch invoice
- Transfer for processing — raw material → processing warehouse → finished goods

### 2.4 Transfer Branch

**Reality**: Different legal entities or different tax offices.
Must have sales invoice between branches (even if same owner).

**Engine must handle**:
- Inter-branch invoice with mark-up or at cost
- VAT handling (must issue invoice if branches have different tax codes)
- Transfer price documentation required by tax authority

### 2.5 Supplier Return

**Reality**: Goods defective + wrong spec + excess order.
Return to supplier OR ask for credit note + keep goods.

**Engine must handle**:
- Return with replacement — issue goods, wait for new receipt
- Return with credit note — issue goods, reduce AP
- Return with discount — reduce AP, no physical movement
- Return after use (consumables) — cannot return, write-off instead

### 2.6 Customer Return

**Reality**: Customer returns goods. Reasons: wrong, defective, excess, damage in transit.

**Engine must handle**:
- Return to stock — goods can be resold. Reverse COGS, increase inventory
- Return to scrap — goods cannot be resold. Separate scrap account
- Return with replacement — receive return + issue new goods
- Return with credit note — reduce AR, no physical return (but need justification)
- Return after period close — prior period adjustment required

### 2.7 Stock Adjustment

**Reality**: Physical count finds difference. 
Must investigate before adjusting. 
Adjustment without approval = fraud risk.

**Engine must handle**:
- Surplus: need to check (forgotten receipt? wrong counting? supplier extra?)
- Shortage: need to check (theft? wrong issue? measurement error?)
- Both: approved by manager + chief accountant

### 2.8 Stock Counting

**Reality**: Vietnam regulation requires at least annual physical count (Luật Kế toán 2015).

**Engine must handle**:
- Full count (year-end, all items)
- Cycle count (rotating, partial each week)
- Count with two independent counters
- Count with discrepancy threshold (small diff auto-approve, large diff investigate)
- Count freeze (no movement during count)

### 2.9 Cycle Counting

**Reality**: High-value items count more often. Low-value items less often. 
ABC classification based on value.

**Engine must handle**:
- A items (top 20% value): count monthly or weekly
- B items (middle 30%): count quarterly
- C items (bottom 50%): count year-end

### 2.10 Damaged Goods

**Reality**: Goods damaged during storage, handling, or transit.

**Engine must handle**:
- Damage report with photo evidence
- Determine responsible party (warehouse, carrier, supplier)
- Insurance claim if covered
- Tax implication: damaged goods write-off may not be tax deductible
- Circular 99: Dr 632 / Cr 156 for normal damage, Dr 138 / Cr 156 for recoverable

### 2.11 Expired Goods

**Reality**: Especially critical for pharma, food, chemical, cosmetic SME.

**Engine must handle**:
- Batch-level expiry tracking mandatory
- Alert before expiry (30/60/90 days)
- Block issue after expiry
- Write-off process: Dr 632 / Cr 156
- Tax: expired goods write-off deductible if properly documented (Thông tư 96/2015)

### 2.12 Missing Goods

**Reality**: Goods physically missing, not damaged, not sold.

**Engine must handle**:
- Immediate investigation (theft suspicion)
- Separate from shortage (missing = potential theft, shortage = natural loss)
- Police report for theft (required for tax deduction)
- Dr 1381 (chờ xử lý) — pending resolution
- After investigation: Dr 334 (employee pay) / Dr 632 (expense) / Dr 711 (insurance)

### 2.13 Excess Goods

**Reality**: Physical count finds more goods than system shows.

**Engine must handle**:
- Check if purchase not yet booked (hàng về trước hóa đơn về sau)
- Check if goods from different supplier (wrong delivery)
- Waiting resolution: TK 3381 (pending)
- After resolution: Dr 156 / Cr 711 (income) or Dr 156 / Cr 331 (pay supplier)

### 2.14 Production Consumption

**Reality**: Raw material → Work in progress → Finished goods.
Cost allocation is complex for SME.

**Engine must handle**:
- Issue raw material to production: TK 621 → TK 152
- Labor + overhead allocation (if integrated)
- Finished goods receipt: TK 155 → TK 154
- Production order tracking (each order has unique cost)
- Scrap/byproduct handling (reduce cost of main product)

### 2.15 Finished Goods Receipt

**Reality**: Production completed. Goods moved to warehouse.

**Engine must handle**:
- Receipt from production: Dr 155 / Cr 154
- Cost = actual production cost (raw material + labor + overhead)
- If actual cost not yet available: provisional cost + adjustment later
- Multiple outputs from same production (co-products)

### 2.16 Promotion Goods

**Reality**: Buy X get Y free, sample, gift to customer.

**Engine must handle**:
- Promotion goods must have separate account (TK 641 — chi phí bán hàng)
- VAT: must issue invoice for promotion goods (even if free) — Thông tư 219/2013
- Tax risk: promotion goods without invoice = deemed revenue
- COGS booked as sales expense, not COGS

### 2.17 Combo/Bundle Products

**Reality**: Products sold as package (phone + case + screen protector).

**Engine must handle**:
- Bundle definition: which items + quantities in one bundle
- Cost allocation: total cost allocated based on relative value
- Revenue allocation: for accounting, allocate based on standalone price
- Bundle issue: reduce all component stock simultaneously

### 2.18 Negative Inventory

**Reality**: Biggest problem in Vietnamese SME. 
Cause: goods issued before receipt booked, or forgotten receipt.

**Engine must handle**:
- HARD BLOCK: cannot issue if stock insufficient (default)
- SUPERVISOR OVERRIDE: allow with reason code + approval
- AUTO-FLAG: list all negative stock items daily
- PERIOD LOCK: negative stock must be resolved before period close
- Tax risk: negative stock = tax audit red flag

### 2.19 Re-open Inventory Period

**Reality**: After period close, need to correct prior period inventory.

**Engine must handle**:
- Reopen period = inventory can be adjusted in prior period
- Revaluation of prior-period COGS
- Tax implication: adjusted COGS changes prior tax
- Audit trail: every adjusted entry clearly marked as "prior period adjustment"
- Restriction: cannot reopen if financial statements already submitted

---

## 3. Use Cases

### UC-01: Goods Receipt from Supplier

**Goal**: Receive goods from supplier, update stock + accounting

**Actors**: Storekeeper, Accountant, Warehouse Manager

**Preconditions**: 
- Purchase order exists (or at least purchase agreement)
- Goods physically arrived at warehouse
- Delivery order / packing list from supplier

**Trigger**: Supplier delivers goods

**Happy Path**:
1. Storekeeper checks quantity + quality against delivery order
2. If OK: signs delivery order, stocks goods
3. Storekeeper creates receipt note (phiếu nhập kho)
4. Accountant receives supplier invoice (may be same day or later)
5. If invoice available: books Nợ 152/156/155 / Nợ 133 / Có 331
6. Cost layers created with unit cost = (invoice price + add-on costs) / qty
7. Stock qty updated
8. If invoice later: temporary receipt with estimated cost, adjust when invoice arrives

**Alternative Path**:
- Goods damaged on arrival: reject, note on delivery order, supplier sends replacement
- Partial delivery: receipt only what physically received
- Excess delivery: receipt what ordered, supplier arranges return of excess
- No purchase order: manager approval required before receipt

**Exception Path**:
- Goods arrive, storekeeper absent: supervisor designates temporary receiver
- Invoice never arrives: goods still in system, AP aging alert
- Receipt duplicated: system must detect duplicate delivery order number
- Goods received wrong supplier: return immediately, don't book receipt

**Validation Rules**:
- Receipt qty must be positive
- Unit cost must be positive (> 0)
- Item must exist in master data
- Supplier must exist
- Warehouse must exist
- Cannot receipt into closed period

**Inventory Rules**:
- Cost = purchase price + customs duties + freight + insurance + handling
- VAT: if deductible → exclude from cost; if not deductible → include in cost
- Add-on costs allocated proportionally to qty or value

**Accounting Impact**:
- Nợ TK 152/153/155/156 (inventory)
- Nợ TK 133 (VAT deductible)
- Có TK 331 / 111 / 112

**Operational Risk**:
- Damage in transit not recorded → inventory overstated
- Invoice later not received → AP understated, cost understated
- Wrong cost booked → COGS wrong, profit wrong

**Compliance Risk**:
- Receipt without invoice → taxable if not justified (hàng về trước hóa đơn về sau)
- Customs duties not included in cost → understated inventory, tax risk
- Wrong VAT treatment → tax penalty

**Final Result**: Stock increased, cost layer created, AP recorded

---

### UC-02: Goods Issue for Sale

**Goal**: Issue goods from warehouse for sale to customer

**Actors**: Sales staff, Storekeeper, Accountant

**Preconditions**: 
- Sales order received from customer
- Goods available in warehouse (or will be)
- Customer credit approved (if credit sale)

**Trigger**: Sales confirmation / delivery order

**Happy Path**:
1. Sales department issues delivery order (lệnh xuất kho)
2. Storekeeper picks goods, checks quantity, updates stock card
3. Storekeeper creates issue note (phiếu xuất kho)
4. Accountant books: Nợ 632 / Có 156 (COGS)
5. Sales invoice issued: Nợ 131 / Có 511, Có 3331
6. Stock qty decreased
7. Cost layers consumed (FIFO/AVCO per method)
8. Profit margin = revenue - COGS

**Alternative Path**:
- Goods picked but customer cancels → return goods to stock, no accounting impact
- Goods picked for delivery later → move to delivery staging area (separate location, still in stock)
- Partial delivery → issue only available qty, backorder remaining

**Exception Path**:
- Goods not available → produce or purchase, inform customer of delay
- Customer rejects delivery → receive return (see UC-06)
- Goods damaged during picking → damaged goods process (see UC-10)

**Validation Rules**:
- Issue qty must be positive
- Available stock must be sufficient
- Item must exist
- Warehouse must exist
- Cannot issue into closed period

**Inventory Rules**:
- COGS = sum of consumed cost layers
- Cost layer consumption method per item (FIFO/AVCO)
- Stock reduced by physical qty issued

**Accounting Impact**:
- Nợ 632 / Có 152/153/155/156 (COGS)
- Nợ 131/111/112 / Có 511, Có 3331 (revenue)

**Operational Risk**:
- Wrong goods picked → customer complaint, return cost
- Wrong COGS → profit margin wrong
- Goods issued but not invoiced → stock gone, revenue not recorded

**Compliance Risk**:
- Sales without invoice → tax evasion penalty
- Wrong COGS → wrong P&L → wrong tax
- Dumping goods below cost → transfer pricing risk

**Final Result**: Stock decreased, COGS booked, revenue recognized

---

### UC-03: Transfer Between Warehouses

**Goal**: Move goods from warehouse A to warehouse B within same company

**Actors**: Storekeeper A, Storekeeper B, Warehouse Manager, Accountant

**Preconditions**:
- Two warehouses exist in system
- Goods available at source warehouse
- Manager authorized the transfer (reason: redistribution, consolidation)

**Trigger**: Internal transfer request

**Happy Path**:
1. Storekeeper A issues goods with transfer note (phiếu xuất điều chuyển)
2. Goods physically transported to warehouse B (may take hours/days)
3. Storekeeper B receives goods, signs transfer note
4. Accountant books: Nợ 156 (kho B) / Có 156 (kho A) — same account, different sub-account
5. Cost layers move from source to destination
6. No P&L impact (same company)

**Alternative Path**:
- Goods in transit: transfer started but not yet received at B
- System tracks "goods in transit" separately
- Both warehouses book at different times
- Inventory still on balance sheet, just reclassified

**Exception Path**:
- Goods lost in transit → investigation, insurance claim
- Goods damaged in transit → damaged goods process
- Warehouse B refuses receipt (wrong goods) → return to warehouse A
- Transfer without documents → fraud risk

**Validation Rules**:
- Source stock must be sufficient
- Both warehouses must exist
- Cannot transfer to same warehouse
- Item must be active in both warehouses

**Inventory Rules**:
- Cost moves with goods (same unit cost, same layer)
- No revaluation on transfer (unless between branches with different tax codes)
- Transfer qty in original unit

**Accounting Impact**:
- Nợ 156/152 (kho nhận) / Có 156/152 (kho xuất)
- Zero impact on P&L
- Zero impact on total inventory value

**Operational Risk**:
- Goods lost during transfer
- Receipt at B not recorded → A shows less, B shows same → total wrong
- Transfer without real movement (fake transfer to hide shortages)

**Compliance Risk**:
- Transfer between branches with different tax IDs → must issue invoice
- Missing inter-branch invoice → tax penalty

**Final Result**: Stock moves from A to B, total inventory unchanged, cost layers preserved

---

### UC-04: Supplier Return

**Goal**: Return defective or excess goods to supplier

**Actors**: Storekeeper, Purchasing staff, Accountant, Supplier

**Preconditions**:
- Goods previously received from supplier
- Reason for return (defective, wrong, excess)
- Supplier agreed to accept return

**Trigger**: Supplier authorized return / credit note received

**Happy Path**:
1. Storekeeper issues goods with return note (phiếu xuất trả lại)
2. Supplier receives goods
3. Supplier issues credit note (or new replacement)
4. Accountant books: 
   - If replacement: wait for new receipt
   - If credit note: Nợ 331 / Có 156, Có 133 (reverse VAT)
5. Cost layers consumed (same as issue)
6. AP reduced by credit amount

**Alternative Path**:
- Supplier does not accept return → negotiate discount → Dr 331 / Cr 156 (discount kept)
- Return with restocking fee → net amount credited, fee = expense
- Goods already sold → cannot return, different process

**Exception Path**:
- Supplier does not issue credit note → AP overstated, dispute process
- Goods returned, supplier refuses receipt → logistics problem, goods stuck
- Double return → system must prevent duplicate

**Validation Rules**:
- Return quantity cannot exceed received quantity
- Item must have been received from this supplier
- Supplier must accept return (authorization document)
- Cannot return more than remaining balance

**Inventory Rules**:
- Return consumes specific cost layer (FIFO: oldest layer first)
- Cost returns to layer from same receipt
- If AVCO: reverse at current average cost

**Accounting Impact**:
- Nợ 331 / Có 156/152 (reverse receipt)
- Nợ 331 / Có 1331 (reverse VAT if previously claimed)
- If replacement: no accounting, just stock movement

**Operational Risk**:
- Goods returned but credit not received → AP overstated
- Wrong goods returned → customer/commercial issue
- Return after payment → complex refund process

**Compliance Risk**:
- VAT adjustment required if VAT previously deducted
- Credit note must match original invoice details
- Missing credit note → cannot deduct from AP

**Final Result**: Stock decreased, AP decreased, VAT adjusted

---

### UC-05: Customer Return

**Goal**: Customer returns goods previously sold

**Actors**: Customer, Sales staff, Storekeeper, Accountant

**Preconditions**:
- Goods previously sold and issued
- Return reason (defective, wrong, no longer needed)
- Customer has valid invoice/purchase proof

**Trigger**: Customer brings goods back

**Happy Path**:
1. Sales staff inspects returned goods for quality
2. If OK: storekeeper receives goods with return receipt (phiếu nhập hàng bán trả lại)
3. Accountant books:
   - Reverse COGS: Nợ 156 / Có 632
   - Reverse revenue: Nợ 511, Nợ 3331 / Có 131
4. Cost returned to inventory at same cost as issue
5. AR reduced by return amount

**Alternative Path**:
- Goods defective beyond reuse → cannot return to stock, scrap process
- Customer exchange → receive return + issue new goods in one transaction
- Return with restocking fee → net amount refunded, fee = income

**Exception Path**:
- Goods damaged by customer → negotiation, partial refund
- No proof of purchase → goodwill process, exception approval
- Return after period close → prior period adjustment

**Validation Rules**:
- Return qty cannot exceed sold qty
- Goods must have been sold to this customer
- Goods must be physically returnable (check expiry, damage)
- Time limit (30/60/90 days per company policy)

**Inventory Rules**:
- Cost returned = cost at time of sale (if same batch available)
- If AVCO: use current average cost (may differ from COGS at sale)
- Goods returned to same condition category (new = resalable, B-grade = discounted, scrap = write-off)

**Accounting Impact**:
- Nợ 156 / Có 632 (reverse COGS)
- Nợ 511, 3331 / Có 131 (reverse revenue)

**Operational Risk**:
- Customer returns stolen goods → legal issue
- Returned goods damaged again in storage
- Fake returns (customer returns different goods) → investigation

**Compliance Risk**:
- Must have proper return documentation
- Credit note must reference original invoice
- VAT adjustment on both sides

**Final Result**: Stock increased, revenue reversed, AR decreased

---

### UC-06: Stock Count Adjustment

**Goal**: Correct inventory balance after physical count

**Actors**: Count team (2 persons), Storekeeper, Accountant, Manager

**Preconditions**:
- Physical count completed
- Count results recorded
- System balance known

**Trigger**: Count results differ from system

**Happy Path**:
1. Count team records actual qty for each item
2. System calculates difference = actual - system
3. If threshold exceeded: manager investigates
4. If no fraud: manager approves adjustment
5. Accountant books adjustment:
   - Surplus: Nợ 156 / Có 711 (or Có 3381 pending)
   - Shortage: Nợ 632 (or Nợ 1381 pending) / Có 156
6. Stock balance updated

**Alternative Path**:
- Threshold below X%: auto-adjust, no investigation (e.g., < 1% of item value)
- Threshold above X%: full investigation, require written explanation

**Exception Path**:
- Count disputed by storekeeper → recount
- Systematic error found (wrong measure unit, wrong item code) → correct master data
- Theft suspected → police involvement, separate process

**Validation Rules**:
- Count session must be approved before posting
- Adjustment must reference count session
- Cannot adjust without two independent signatures
- Adjustment value limits per manager level

**Inventory Rules**:
- Surplus cost = current average cost (or purchase price)
- Shortage cost = current average cost (consumes cost layer)
- Write-off not tax deductible if due to management negligence

**Accounting Impact**:
- Surplus: Nợ 156 / Có 711 (or 3381)
- Shortage: Nợ 632 (or 1381) / Có 156

**Operational Risk**:
- Adjustment used to hide theft
- Wrong item adjusted (counting wrong item code)
- Adjustment outside physical count period

**Compliance Risk**:
- Large shortage without investigation → tax authority questions
- Surplus without documenting as income → tax evasion
- Count not documented → audit issue

**Final Result**: Stock adjusted to physical reality, P&L impacted

---

### UC-07: Periodic Inventory Closing

**Goal**: Close inventory period, calculate final COGS, prepare for financial close

**Actors**: Accountant, Chief Accountant, Warehouse Manager

**Preconditions**:
- All stock movements in period posted
- Physical count completed and adjusted
- All invoices received and booked
- Exchange rates finalized (for FC purchases)

**Trigger**: Month-end / quarter-end / year-end

**Happy Path**:
1. System blocks new inventory transactions for period
2. Validate: stock qty = system qty for all items
3. Validate: inventory GL balance = sum of item values
4. Validate: no negative stock 
5. Validate: no stock with qty=0 but value≠0
6. Calculate final COGS
7. Generate inventory reports:
   - Stock card (Sổ chi tiết vật tư hàng hóa)
   - Inventory summary (Bảng tổng hợp nhập xuất tồn)
   - Aging analysis (hàng tồn lâu, chậm luân chuyển)
8. Calculate impairment provision (dự phòng giảm giá)
   - Compare cost vs net realizable value
   - If cost > NRV: provision needed (Dr 632 / Cr 2294)
9. Close period

**Alternative Path**:
- Items with wrong cost → correct before close
- Items in transit → confirm receipt or confirm not received yet
- FC items → FX revaluation first

**Exception Path**:
- GL balance != inventory value → investigate each item
- Physical count not done → force count or carry forward
- Impairment needed but no data → make best estimate

**Validation Rules**:
- All transactions in period must have valid documents
- COGS calculation must match costing method
- Opening balance = prior period closing balance
- Inventory value must be non-negative

**Inventory Rules**:
- Costing method applied consistently (VAS 02)
- Impairment at lower of cost or NRV
- Period close must follow sequential order (month 1 → 2 → 3)
- Cannot skip period close

**Accounting Impact**:
- Final COGS determined
- Impairment booked (if needed)
- Inventory value on balance sheet = final
- P&L reflects total COGS + impairment

**Operational Risk**:
- Delay in close → delayed FS → delayed tax filing → penalty
- Wrong COGS → FS restated → audit issue
- Impairment missed → overstated inventory, overstated profit

**Compliance Risk**:
- Wrong valuation method → FS not compliant with TT 99
- Impairment not booked → FS overstated, tax overpaid
- Period not closed → prior period errors carried forward

**Final Result**: Period closed, inventory valued correctly, COGS final, FS ready

---

### UC-08: Batch/Lot Tracking

**Goal**: Track goods by batch/lot number for traceability

**Actors**: Storekeeper, Quality control, Accountant

**Preconditions**:
- Item configured for batch tracking
- Physical batch/label on goods

**Trigger**: Receipt with batch, expiry date

**Happy Path**:
1. Receipt: enters batch code, expiry date
2. Cost layer created with batch information
3. Issue: user selects specific batch to issue
4. OR issue by FIFO: system picks oldest batch first
5. Batch report available at any time
6. Expired goods alert uses batch data

**Alternative Path**:
- FIFO issue auto-selects batch (oldest expiry first)
- Manual batch override allowed with reason

**Exception Path**:
- Wrong batch entered → traceability broken
- Mixed batches in same location → physical tracking hard

**Validation Rules**:
- Batch code must be unique per item (or globally)
- Expiry date cannot be in the past at receipt
- Cannot issue expired batch (unless special approval)

**Inventory Rules**:
- Cost per batch may differ
- Batch consumed FIFO by expiry or by receipt date
- Qty per batch tracked in cost layer

**Accounting Impact**: Same as regular receipt/issue. Batch is informational for cost layer.

**Operational Risk**:
- No batch tracking for regulated goods (pharma, food) = legal violation
- Wrong batch recalled → customer/patient safety issue
- Batch mixing in warehouse → traceability fails

**Compliance Risk**:
- Pharma batch tracking required by Drug Law
- Food batch tracking required by Food Safety Law
- Circular 99 allows but does not mandate batch tracking

**Final Result**: Goods tracked by batch, traceability maintained

---

### UC-09: Impairment Provision

**Goal**: Book provision for inventory where NRV < cost

**Actors**: Accountant, Chief Accountant, Warehouse Manager

**Preconditions**:
- Period-end approaching
- Inventory items identified with potential impairment
- NRV estimate available

**Trigger**: Periodic review (month/quarter/year)

**Happy Path**:
1. Identify items with market value below cost
2. Estimate NRV (selling price - selling costs - completion costs)
3. Calculate provision = cost - NRV (if cost > NRV)
4. Manager approves provision
5. Book: Nợ 632 / Có 2294
6. Update inventory value in FS

**Alternative Path**:
- If NRV recovers later → reverse provision (Nợ 2294 / Có 632)
- Max reversal: cannot reverse more than original provision

**Exception Path**:
- No reliable NRV → use cost (no provision) → conservative but may overstate
- Items fully obsolete → full provision (write off completely)

**Validation Rules**:
- Provision per item (not total), as required by VAS 02
- Provision not tax deductible until goods actually disposed (Luật Thuế TNDN)
- Must be reversed when NRV recovers

**Inventory Rules**:
- NRV = estimated selling price - cost to complete - selling cost
- NRV assessment at each period-end
- Consistent methodology across periods

**Accounting Impact**:
- Nợ 632 / Có 2294 (provision booked)
- Reduces inventory value on BS
- Reduces profit on P&L

**Operational Risk**:
- Provision not booked → inventory overstated → profit overstated
- Provision overbooked → hidden reserves → profit understated

**Compliance Risk**:
- VAS 02 requires impairment, not optional
- Tax: provision not deductible until disposal → book-tax difference
- Insufficient provision → audit qualification

**Final Result**: Inventory valued at lower of cost or NRV

---

### UC-10: Damaged/Expired Goods Disposal

**Goal**: Remove damaged/expired goods from inventory, book loss

**Actors**: Storekeeper, Quality control, Manager, Accountant

**Preconditions**:
- Goods identified as damaged or expired
- Damage report / quality inspection completed
- Manager approval for write-off

**Trigger**: Expiry date passed OR damage discovered

**Happy Path**:
1. Quality control inspects, writes report
2. Storekeeper segregates damaged goods (separate location)
3. Manager approves write-off
4. Accountant books:
   - Nợ 632 (normal loss within tolerance)
   - Nợ 1381 (pending investigation, if abnormal)
   - Có 156 (remove from inventory)
5. Physical goods disposed or destroyed
6. Disposal report documented

**Alternative Path**:
- Insurance claim: Nợ 1388 (insurance receivable)
- Employee responsible: Nợ 334 (deduct from salary)
- Scrap value recovered: Nợ 111/112 (cash from scrap sale)

**Exception Path**:
- Goods hazardous (chemical, battery): special disposal required, cost
- Goods subject to regulatory destruction (expired pharma): witnessed destruction required

**Validation Rules**:
- Approval required from authorized level (value-based)
- Supporting documents must be filed
- Cannot dispose without quality report
- Hazardous disposal must follow environmental law

**Inventory Rules**:
- Cost = current average cost of damaged qty
- If specific batch: use batch cost
- Qty removed from system

**Accounting Impact**:
- Nợ 632 / 1381 / 1388 / 334 / Có 156
- Depends on responsible party

**Operational Risk**:
- Disposal without approval → fraud, inventory manipulation
- Goods not actually disposed → stolen, resold on black market
- Wrong goods disposed → inventory shortage

**Compliance Risk**:
- Damaged goods write-off may require tax authority approval
- Missing documentation → tax not deductible
- Environmental violation for improper disposal

**Final Result**: Damaged goods removed from inventory, loss recognized

---

## 4. Inventory Rule Logic

### 4.1 Stock Availability Check

```
Available = Physical stock - Reserved - Allocated - Quarantine
Reserved = sales order not yet picked
Allocated = picking in progress
Quarantine = blocked (quality hold, damaged, expired)
Net available = what can still be sold

For issue confirmation: must have net available > 0
Exception: supervisor override with reason code
```

### 4.2 Duplicate Transaction Detection

Check by:
- Supplier invoice number + supplier ID + date
- Internal reference number (phiếu nhập/xuất)
- Goods delivery note number
- Combination of (date, item, qty, counterparty)

If duplicate probability > threshold → flag, require manual review.

### 4.3 Negative Inventory Prevention

Default: **HARD BLOCK** — cannot issue if stock insufficient.

Override allowed only with:
- Reason code (goods receipt pending, invoice later, system error)
- Manager approval (digital signature or separate authorization)
- Auto-flags for chief accountant review

After period close: any negative stock must be resolved.

### 4.4 Costing Mismatch Detection

Check after each costing run:
- Total inventory value = sum of all item values
- Item value = total cost layer value for that item
- If mismatch: identify which cost layer(s) have issue
- Zero qty but non-zero value: auto-adjust to zero (Dr/Cr 632)

### 4.5 Expired Goods Control

- Receipt: validate expiry date not in past
- Daily batch scan: find goods expiring within 30/60/90 days
- Issue: prevent issue of expired goods
- Write-off: auto-flag expired goods for disposal
- Report: aging by expiry for inventory optimization

### 4.6 Inventory Mismatch Detection

Reconciliation checks:
- System qty vs physical qty (from count)
- System value vs GL balance
- Movement log vs document log
- Cost layer total vs item value

Run automatically at period-end. Also available on-demand.

### 4.7 Unauthorized Adjustment Prevention

- Physical count adjustment requires count session reference
- No direct stock adjustment (must go through count or receipt/issue)
- Value adjustment requires chief accountant approval
- Supervisor override creates audit alert

### 4.8 Stock Traceability

Bi-directional trace:
- Forward: from receipt → cost layer → issue → customer
- Backward: from issue → cost layer → receipt → supplier
- Full chain visible for each transaction

Required for: recall, audit, quality investigation, fraud detection.

### 4.9 Warehouse Reconciliation

```
Warehouse card (qty) vs System (qty)
  ↓ if mismatch
Compare movement log
  ↓ identify discrepancies
Correct or investigate
  ↓
Adjust inventory if needed
```

Run weekly or monthly. Hard requirement before period close.

### 4.10 Fraud Risk Detection

Suspicious patterns:
- Frequent negative stock on same item
- Many adjustments without count sessions
- Large write-offs without damage report
- Transfer to inactive warehouses
- Receipts from new suppliers followed by write-offs
- Stock levels consistent despite low purchases
- Sales below cost on regular basis

Auto-alert chief accountant when pattern detected.

---

## 5. SME Warehouse Workflow Logic

### 5.1 End-to-End Inventory Lifecycle

```
Supplier → Purchase Order → Goods Receipt → Stock →
  → Sales Order → Picking → Packing → Shipment → Customer
  → Production → WIP → Finished Goods → Stock (repeating)
  → Transfer → Another warehouse
  → Return → Reverse flow
  → Count → Adjust → Correct stock
  → Impairment → Write off → Remove from stock
  → Period Close → Freeze → Value → Report
```

### 5.2 Warehouse Workflow

**Daily**:
1. Receive incoming goods (check qty, quality)
2. Issue outgoing goods (pick, pack, ship)
3. Update stock card (manual or system)
4. Resolve immediately: discrepancies found during movement

**Weekly**:
1. Cycle count (high-value items)
2. Identify slow-moving/expiring goods
3. Reconcile storekeeper card vs system

**Monthly**:
1. Complete all receipts/issues
2. Costing run
3. GL reconciliation
4. Impairment review
5. Report generation

**Year-end**:
1. Full physical count
2. Final costing
3. Impairment booking
4. Inventory closing
5. Audit preparation

### 5.3 Purchasing vs Warehouse Relationship

```
Purchasing → PO → Supplier → Goods + Invoice
  ↓ (Purchase department)
Warehouse → Receives goods → Checks qty/quality
  ↓ (Storekeeper)
  If OK: issues receipt (phiếu nhập)
  If NOT: notes damage, rejects
  ↓
Purchasing → Gets receipt copy → Matches with PO + Invoice
  ↓
Accountant → Books inventory
```

**Key rule**: Storekeeper receives physical goods only. 
Storekeeper does not issue PO. Storekeeper does not approve payment.
Separation of duties = fraud prevention.

### 5.4 Sales vs Warehouse Relationship

```
Sales → Sales Order → Picking List
  ↓
Warehouse → Picks goods → Issues delivery note
  ↓
  If stock OK: handover to logistics
  If stock insufficient: inform sales, backorder
  ↓
Sales → Issues invoice → Customer pays → Goods delivered
```

**Key rule**: Sales cannot issue goods without warehouse release.
Warehouse cannot sell goods (no pricing authority).
Separation of duties.

### 5.5 Accounting vs Warehouse Relationship

```
Warehouse movement (qty) → Document → Accountant books (value)
  ↓
Accountant verifies: documents match warehouse card
  ↓
Accountant updates: stock value, COGS, GL
  ↓
Reconciliation: GL balance = Sum of item values
```

**Key rule**: Warehouse handles quantity. Accountant handles value.
They must reconcile periodically. Discrepancy = alert.

### 5.6 Approval Flow

| Document | Approver 1 | Approver 2 | Approver 3 |
|---|---|---|---|
| Purchase Receipt | Storekeeper | Warehouse Mgr | — |
| Sales Issue | Storekeeper | Sales Mgr | — |
| Transfer | Storekeeper A | Warehouse Mgr | Storekeeper B |
| Count Adjustment | Count Team | Mgr | Chief Accountant |
| Write-off | Storekeeper | Mgr | Chief Accountant |
| Impairment | Accountant | Chief Accountant | Director |
| Period Close | Accountant | Chief Accountant | Director |

### 5.7 Reconciliation Flow

1. Storekeeper card → physical count (daily/weekly)
2. Storekeeper card → system qty (weekly)
3. System qty → system value (month-end)
4. System value → GL balance (month-end)
5. GL balance → FS (quarter-end/year-end)

Each step produces variance report. Variance must be explained.

### 5.8 Adjustment Flow

1. Count/physical event identifies discrepancy
2. Document discrepancy (biên bản kiểm kê)
3. Investigate root cause
4. Approve adjustment (see approval matrix)
5. Book adjustment (Dr/Cr inventory, Dr/Cr counterpart)
6. Update stock
7. File documents for audit

### 5.9 Month-End Inventory Closing

1. Ensure all movements posted (receipts, issues, transfers)
2. Run costing for all items (FIFO/AVCO)
3. Validate: no negative stock, no qty=0/value≠0
4. Reconcile inventory value with GL
5. Review impairment need
6. Book impairment if needed
7. Generate reports:
   - Sổ chi tiết vật tư hàng hóa (stock card)
   - Bảng tổng hợp nhập xuất tồn (inflow-outflow summary)
   - Phiếu nhập kho, phiếu xuất kho (documents)
   - Báo cáo tồn kho chậm luân chuyển (slow-moving report)
8. Lock inventory module for prior period

### 5.10 Year-End Inventory Closing

Same as month-end plus:
- Full physical count (mandatory by Luật Kế toán 2015)
- Auditor observation of count (if audited)
- Final impairment assessment
- Inventory certificate signed by director
- Archive all inventory documents for 10 years (Luật Kế toán)

### 5.11 Audit Preparation

Documents to prepare:
- Physical count sheets (signed by count team, storekeeper)
- Adjustment approvals
- Impairment documentation (NRV calculation)
- Costing methodology document
- Reconciliation reports (stock card ↔ GL)
- Movement register (nhập xuất tồn summary)

### 5.12 Exception Handling

| Exception | Action | System Behavior |
|---|---|---|
| Goods without invoice | Temporary receipt | Pending document alert |
| Invoice without goods | Hold in AP, not in inventory | No stock impact |
| Negative stock on issue | Block (or override) | Alert chief accountant |
| Count discrepancy | Investigate, approve, adjust | Separate pending account |
| Period close failure | Identify root cause | Block all new transactions |
| Duplicate receipt | Flag, require confirmation | Prevent posting |

---

## 6. SME Pain Analysis

### 6.1 Excel Chaos

**Symptom**: Storekeeper uses Excel. Accountant uses different software. 
They compare manually at month-end. Always wrong.

**Root cause**: No shared system. Two versions of truth.

**Solution**: Single system. Storekeeper enters movement. 
Accountant reads from same data. One source of truth.

### 6.2 Stock Mismatch

**Symptom**: Storekeeper says 100 units. System says 95 units. 
Who is right? Nobody knows. Count again.

**Root cause**: Movement not recorded in time. Receipt at warehouse 
not yet entered in system. Issue not yet entered.

**Solution**: Real-time entry at point of movement. 
Barcode scanner, mobile app. No batch entry.

### 6.3 Negative Inventory

**Symptom**: "Bán trước, nhập sau" — sell before receipt. 
Or receipt forgotten. Or receipt entered with wrong item code.

**Root cause**: Business pressure to deliver fast. 
System allows override of stock check. Sloppy data entry.

**Solution**: Block by default. Override with reason + manager approval. 
Auto-sweep and correct at period end.

### 6.4 Late Posting

**Symptom**: Receipt on May 5, entered in system on May 25.
Stock already sold May 10 based on physical stock.
Result: negative stock from May 10, incorrect COGS.

**Root cause**: Paper-first, system-second workflow.
Delays in data entry.

**Solution**: System-first workflow. Movement cannot happen 
without system transaction. Barcode scanning at receipt/dispatch.

### 6.5 Wrong Costing

**Symptom**: COGS does not reflect actual purchase price.
Inventory value different from what was paid.
Profit margin calculation wrong.

**Root cause**: Wrong costing method selected. Add-on costs not included.
Exchange rate used incorrectly. Invoice not yet booked.

**Solution**: Calculate costing automatically based on item method.
Enforce inclusion of all costs. FC handling per TT 99 rules.

### 6.6 Cross-Branch Mismatch

**Symptom**: Branch A sold goods from Branch B's inventory.
Or inventory counted twice (same goods, different branches claim them).
Or transfer goods lost between branches.

**Root cause**: No central inventory control. 
Branches operate independently. No inter-branch reconciliation.

**Solution**: Central inventory master with branch sub-accounts. 
Mandatory inter-branch transfer documents. Periodic cross-branch reconciliation.

### 6.7 Duplicate Movement

**Symptom**: Same receipt entered twice. Or same issue entered twice.
Or return entered but original issue not reversed.
Inventory value inflated or deflated.

**Root cause**: Manual data entry without duplicate check.
Multiple people entering same document.

**Solution**: Document-level uniqueness enforcement. 
Reference number must be unique per document type.

### 6.8 Missing Warehouse Paper

**Symptom**: Receipt without phiếu nhập. Issue without phiếu xuất.
Transfer without biên bản điều chuyển.
Audit finds hole in document trail.

**Root cause**: Paper process not followed. Documents lost after entry.
System does not enforce document requirement.

**Solution**: System enforces: no movement without document ID.
Document image attachment. Auto-numbering for missing documents.

### 6.9 Inventory Shrinkage

**Symptom**: Year-end count shows less than system. 
Difference is material. Nobody knows where goods went.

**Root cause**: Theft. Unrecorded damage. Wrong counting. 
Mixing items with similar appearance.

**Solution**: Cycle counts. Separation of duties. 
Security cameras. Surprise audits. Strict adjustment approval.

### 6.10 Weak Audit Trail

**Symptom**: Can't trace who created receipt. 
Can't trace which batch was sold to which customer. 
Can't trace why adjustment was made.

**Root cause**: No logging. User identities not tracked.
Changes not tracked.

**Solution**: Every transaction has: who, when, what, why, 
before/after values. Tamper-proof log.

### 6.11 Finance vs Warehouse Mismatch

**Symptom**: Total inventory on balance sheet is VND 5 billion.
But warehouse manager says goods are worth VND 4.5 billion.
Difference = impairment, wrong costing, unbooked movements.

**Root cause**: Inventory valued incorrectly. 
Or movements not reflected in GL. 
Or cost not updated.

**Solution**: Periodic GL ↔ inventory reconciliation. 
Automated valuation. Costing run before close.

---

## 7. Final Deliverables

### 7.1 Key Design Principles for Inventory Engine

1. **Quantity first, value second** — stock control starts with quantity. 
   Value follows from costing method.

2. **Every movement has a document** — no document = no movement. 
   Self-generated document as fallback.

3. **Block negative stock by default** — override is exception, not norm.

4. **One source of truth** — storekeeper and accountant work on same data.

5. **Costing at item level** — each item has its own costing method.
   Method consistent within period.

6. **Period gate prevents chaos** — cannot post to closed period.
   Cannot adjust after close without clear audit trail.

7. **Separation of duties** — creator ≠ approver ≠ bookkeeper.
   Especially for adjustments and write-offs.

8. **Audit trail is automatic** — never optional. 
   Who, what, when, why, before/after.

9. **Reconciliation before close** — cannot close if GL ≠ inventory.
   Cannot close if count not done.

10. **System serves people** — not replace storekeeper, support them.
    Interface simple, fast, works on mobile.

### 7.2 Vietnam-Specific Requirements

1. **Circular 99/2025/TT-BTC** — effective Jan 2026, replaces TT 200/2014.
   Must comply.
   Key: valuation methods, impairment mandatory, FC handling, 
   periodic vs perpetual option.

2. **VAS 02 (Hàng tồn kho)** — valuation at cost, lower of cost or NRV.
   Consistent method, FIFO/AVCO/specific/manual.

3. **Luật Kế toán 2015** — annual count mandatory. 
   Document retention 10 years. 
   CEO signs off on inventory.

4. **Luật Thuế TNDN** — inventory write-off deductible only if properly documented.
   Provision not deductible until disposal.

5. **Luật Thuế GTGT** — VAT on inventory: input deductible if proper invoice.
   Hàng về trước hóa đơn về sau: temporary receipt, adjust later.

### 7.3 Existing Codebase Assessment

| Feature | Status | Gap |
|---|---|---|
| Goods receipt | ✅ Implemented | Missing period gate, add-on cost allocation weak |
| Goods issue | ✅ Implemented | Missing period gate, no FOC/promotion separate |
| Transfer | ✅ Implemented | Works, missing multi-branch invoice requirement |
| In transit | ✅ Implemented | Basic, needs formal inter-warehouse transit tracking |
| Consignment | ✅ Implemented | Basic |
| Customer return | ✅ Implemented | Works |
| Supplier return | ❌ Missing | Not implemented |
| Physical count | ⚠️ Partial | Count session exists, adjustment books but no frozen period |
| Impairment | ⚠️ Partial | Basic provision booking, no NRV calculation |
| Costing method | ⚠️ Partial | FIFO-only via cost layer consumption |
| Moving average | ⚠️ Partial | calculateAndUpdateUnitCost exists but cost layer is FIFO |
| Standard cost | ❌ Missing | Not implemented |
| Batch/expiry | ⚠️ Partial | Fields exist in cost layer, issueFromBatch exists |
| Damaged goods | ❌ Missing | Not implemented |
| Negative stock prevention | ❌ Missing | issueGoods checks, but can bypass via direct SQL |
| Period gate | ❌ Missing | No isPeriodOpen check in any InventoryService method |
| Fraud detection | ❌ Missing | Not implemented |
| Stock reservation | ❌ Missing | Not implemented |
| Re-order alerts | ❌ Missing | Not implemented |
| Combo/bundle | ❌ Missing | Not implemented |
| Production integration | ❌ Missing | No production order, no WIP, no finished goods from production |

---

## 8. Recommendation for Implementation Order

### Phase 1 (Critical — fix current)

1. **Period gate**: add `isPeriodOpen` to every InventoryService method
2. **Negative stock block**: hard block, supervisor override with reason
3. **Cost layer method selection**: allow FIFO or AVCO per item
4. **Zero qty non-zero value**: periodic check + auto-adjust

### Phase 2 (Core inventory flows)

5. **Supplier return**: reverse receipt with cost layer consumption
6. **Damaged/expired goods workflow**: quality report → approval → write-off
7. **Physical count freeze**: no movement during counting
8. **Batch expiry enforcement**: block issue of expired goods, auto-alerts

### Phase 3 (Reconciliation & Close)

9. **GL reconciliation**: system value vs GL balance check
10. **Periodic closing workflow**: sequential close (month → quarter → year)
11. **Impairment engine**: NRV calculation based on aging + market price
12. **Costing run**: scheduled, closed period check, results validation

### Phase 4 (Advanced)

13. **Stock reservation**: link to sales order
14. **Re-order alerts**: min/max stock thresholds
15. **Combo/bundle**: define bundle, allocate cost, issue together
16. **Fraud detection**: pattern analysis, auto-alerts
17. **Production integration**: raw material issue → WIP → finished goods
