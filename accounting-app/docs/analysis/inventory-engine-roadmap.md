# Implementation Roadmap: Inventory Engine & Period-Closing Engine

## Overview

This document decomposes the inventory engine work into small, verifiable tasks organized in phases. Each task follows the dependency graph: period gate → negative stock → cost layer → core flows → reconciliation → close → advanced.

Current codebase has 11 controllers, 16 InventoryService methods, 764 lines, 10 test files, 6 inventory-related migrations. FIFO-only costing via cost layer consumption (`created_at ASC`). PeriodService exists with `isPeriodOpen()` but **no inventory method calls it**. Item model has `valuationMethodId` field — never used.

---

## Phase 0: Foundation — Period Gate (Critical Fix)

### Task 0.1: Add period guard to InventoryService

**Description:** Before every inventory mutation (`receiveGoods`, `issueGoods`, `transferGoods`, etc.), check `PeriodService::isPeriodOpen()` and reject with `\InvalidArgumentException` if closed. This prevents data corruption when someone posts to a locked period.

**AC:**
- [ ] `InventoryService` calls `PeriodService::isPeriodOpen($date)` at start of every mutation method
- [ ] Mutation methods accept optional `$date` param (defaults to today) — all existing callers unchanged
- [ ] Closed period → `InvalidArgumentException` with message "Cannot modify inventory in a closed period"
- [ ] Existing tests pass with open period (seed an open period)

**Verification:** `php tests/InventoryReceiptTest.php && php tests/InventoryIssueTest.php`

**Dependencies:** None

**Files touched:**
- `src/Accounting/Domain/Service/InventoryService.php`
- `tests/InventoryReceiptTest.php` (seed period)
- Optionally `config/services.php` (inject PeriodService or pass static PDO)

**Scope:** Small (1-2 files)

---

### Task 0.2: Migrate a test "period" accounting_periods setup

**Description:** All inventory tests currently run without periods. Add a helper to `tests/bootstrap.php` or each test file: insert an open `accounting_periods` row covering today so `isPeriodOpen` returns true.

**AC:**
- [ ] Every inventory test file has open period seeding in its setup
- [ ] Teardown (explicit) deletes test period row
- [ ] No test fails due to period gate

**Verification:** `for f in tests/Inventory*.php; do php "$f"; done`

**Dependencies:** Task 0.1

**Files touched:**
- `tests/bootstrap.php`
- `tests/InventoryReceiptTest.php`
- `tests/InventoryIssueTest.php`
- `tests/InventoryPeriodicTest.php`
- `tests/InventoryPhysicalCountTest.php`
- `tests/InventoryTransferTest.php`
- `tests/InventoryConsignmentTest.php`
- `tests/InventoryImpairmentTest.php`
- `tests/InventoryTransitTest.php`
- `tests/InventoryPromotionalTest.php`
- `tests/InventoryServiceEnhancementsTest.php`

**Scope:** Small (1-2 files, but touches all tests)

---

## Phase 1: Negative Stock & Costing Method Selection

### Task 1.1: Allow negative stock for goods receipt scenarios

**Description:** In Vietnamese accounting, a warehouse can have negative stock (goods received but invoice not yet posted, or inter-warehouse transfer timing). Add a configurable `allow_negative_stock` flag on item. If true, `issueGoods` and `transferGoods` do not check stock qty. If false (default), existing behavior.

**AC:**
- [ ] Item model has `allowNegativeStock` field (bool, default false)
- [ ] Migration adds `allow_negative_stock TINYINT(1) DEFAULT 0` to `items` table
- [ ] `issueGoods` checks `$item->getAllowNegativeStock()` before stock check
- [ ] `transferGoods` checks before source stock check
- [ ] `consignGoods` checks before stock check
- [ ] `issuePromotional` checks before stock check
- [ ] `issueFromBatch` checks before stock check
- [ ] Existing tests pass (all items default false, no change)

**Verification:** `php tests/InventoryIssueTest.php`

**Dependencies:** Task 0.2

**Files touched:**
- `src/Accounting/Domain/Model/Item.php`
- `src/Accounting/Domain/Service/InventoryService.php`
- `src/Accounting/Infrastructure/Persistence/PDOItemRepository.php`
- `database/migrations/XXX_add_allow_negative_stock_to_items.php`
- `tests/InventoryIssueTest.php`
- `tests/InventoryTransferTest.php`

**Scope:** Small-Medium (4 files)

---

### Task 1.2: Costing method selection (FIFO / Weighted Average / Specific ID)

**Description:** Item model has `valuationMethodId` but it's never read. Implement costing method dispatch in `consumeCostLayers`: FIFO (current), Weighted Average (aggregate all layers, use avg unit cost), Specific ID (requires batch_code). Store method ID on item. Weighted average = sum(qty * unit_cost) / sum(qty) across all layers for the item.

**AC:**
- [ ] `consumeCostLayers` reads `$item->getValuationMethodId()` and dispatches:
  - `fifo` (default): existing behavior (oldest layers first)
  - `weighted_average`: aggregate all layers, use avg unit cost × qty
  - `specific_id`: requires `$batchCode` param, error without it
- [ ] Callers that pass `$batchCode` use specific ID automatically
- [ ] `issueGoods` and other callers pass no `$batchCode` → use item's method
- [ ] Error on unknown method
- [ ] Existing tests pass (VT001 has no valuation_method_id → uses FIFO)

**Verification:** `for f in tests/Inventory*.php; do php "$f"; done`

**Dependencies:** Task 0.2

**Files touched:**
- `src/Accounting/Domain/Service/InventoryService.php`
- `tests/InventoryServiceEnhancementsTest.php`

**Scope:** Medium (3-4 files)

---

### Task 1.3: Weighted average unit cost after each receipt

**Description:** `calculateAndUpdateUnitCost` already computes WA. But the costing method dispatch in 1.2 needs to distinguish between periodic-AVG (end-of-period) and perpetual-AVG (after each receipt). Implement a flag on valuation method: `perpetual` vs `periodic`. When `perpetual`, recalculate unit cost after every receipt and update all remaining cost layers. When `periodic`, only compute at period close.

**AC:**
- [ ] Valuation methods table has `calculation_type` (`perpetual`/`periodic`)
- [ ] `perpetual`: after every receipt, calculate avg cost and update all remaining layers for that item to the new avg
- [ ] `periodic`: existing FIFO behavior, only `calculateAndUpdateUnitCost` stores the avg on item
- [ ] Migration seeds default methods (FIFO=perpetual, Weighted Avg=perpetual, Periodic Avg=periodic)

**Verification:** Test with 2 receipts at different prices, issue, check COGS uses WA

**Dependencies:** Task 1.1, 1.2

**Files touched:**
- `src/Accounting/Domain/Service/InventoryService.php`
- `database/migrations/XXX_seed_valuation_methods.php`
- `tests/InventoryServiceEnhancementsTest.php`

**Scope:** Medium (3 files)

---

## Phase 2: Core Business Flows

### Task 2.1: Supplier return (hàng mua trả lại)

**Description:** When goods are returned to supplier, need to: reduce stock, reverse cost layers, Dr AP / Cr Inventory. This is NOT the same as issue — it's a negative receipt. The AP module already has `returnGoods` route (post `/api/ap/invoices/:id/return`) but it doesn't call into InventoryService currently.

**AC:**
- [ ] `InventoryService::returnToSupplier(itemId, qty, reference, createdBy, originalReceiptId)`:
  - Dr AP(331) — Cr Inventory(152/155/156) at original cost
  - Reverse cost layers (FIFO: consume most recent layers first for returns)
  - Decrease stock qty
  - Record in transactions table
- [ ] AP controller can call this when processing supplier returns
- [ ] Returns cannot exceed qty-on-hand for that receipt (specific receipt tracking)
- [ ] Validation: return qty > 0, item exists, stock sufficient

**Verification:** `php tests/InventorySupplierReturnTest.php`

**Dependencies:** Task 0.2

**Files touched:**
- `src/Accounting/Domain/Service/InventoryService.php`
- `src/Accounting/Interfaces/HTTP/Inventory/ReturnToSupplierController.php`
- `config/routes.php`
- `config/services.php`
- `tests/InventorySupplierReturnTest.php`

**Scope:** Medium (5 files)

---

### Task 2.2: Damaged / obsolete goods write-off

**Description:** Goods damaged, expired, or obsolete need write-off with specific Dr expense / Cr Inventory, separate from normal issue. Dr 632 (COGS) or 811 (other expense) depending on cause. Record in `inventory_write_off` table with reason, approval, and inspection notes.

**AC:**
- [ ] `InventoryService::writeOffGoods(itemId, qty, reason, expenseAccount, reference, createdBy, notes)`:
  - Dr `expenseAccount` — Cr Inventory at avg cost
  - Consume cost layers
  - Decrease stock
  - Record in `inventory_write_off` table
- [ ] Reason must be one of: `damaged`, `expired`, `obsolete`, `lost`, `other`
- [ ] Write-off > threshold (e.g., 1M VND) requires approval flag
- [ ] Migration creates `inventory_write_offs` table

**Verification:** `php tests/InventoryWriteOffTest.php`

**Dependencies:** Task 0.2

**Files touched:**
- `src/Accounting/Domain/Service/InventoryService.php`
- `src/Accounting/Interfaces/HTTP/Inventory/WriteOffController.php`
- `database/migrations/XXX_create_inventory_write_offs_table.php`
- `config/routes.php`
- `config/services.php`
- `tests/InventoryWriteOffTest.php`
- `public/views/write_off.php`

**Scope:** Medium (7 files)

---

### Task 2.3: Promotional / sample goods with tax adjustment

**Description:** Current `issuePromotional` Dr 641 (selling expense) / Cr Inventory. But VAS 02 requires VAT adjustment for promotional goods (output VAT on FCT). Add optional VAT computation: if item has VAT rate > 0, issue generates additional Dr 641/ Cr 3331 for the VAT on deemed sale value.

**AC:**
- [ ] `issuePromotional` accepts optional `$deemedSaleValue` param
- [ ] If provided and item has VAT rate, compute output VAT: Dr 641 (VAT amount) — Cr 3331
- [ ] Journal entry records VAT portion
- [ ] Existing behavior unchanged if no VAT params

**Verification:** `php tests/InventoryPromotionalTest.php`

**Dependencies:** Task 0.2

**Files touched:**
- `src/Accounting/Domain/Service/InventoryService.php`
- `tests/InventoryPromotionalTest.php`

**Scope:** Small (2 files)

---

## Phase 3: Inventory Reconciliation & Reporting

### Task 3.1: Inventory aging report

**Description:** Report showing stock by age buckets (0-30, 31-60, 61-90, 91-180, 180+ days) based on cost layer creation date. Each cost layer has `created_at` — aged based on how long that layer has been in stock. For slow-moving stock, flag for impairment review.

**AC:**
- [ ] `InventoryService::getAgingReport(itemId?, warehouseId?)` returns:
  - Item code, name, unit
  - Quantity per age bucket
  - Value per age bucket (unit_cost + addon_per_unit × qty)
  - Total quantity and value
  - Flagged items (qty in 180+ bucket > 0)
- [ ] Report endpoints at `/api/inventory/aging`

**Verification:** `php tests/InventoryAgingTest.php`

**Dependencies:** Task 0.2

**Files touched:**
- `src/Accounting/Domain/Service/InventoryService.php`
- `src/Accounting/Interfaces/HTTP/Inventory/AgingController.php`
- `config/routes.php`
- `config/services.php`
- `tests/InventoryAgingTest.php`

**Scope:** Medium (5 files)

---

### Task 3.2: Inventory turnover ratio

**Description:** COGS / Average Inventory for a period. Used by management to assess inventory efficiency. COGS from transactions in period. Average inventory = (opening + closing) / 2 from periodic_inventory table, or from cost layers snapshot.

**AC:**
- [ ] `InventoryService::getTurnoverRatio(periodStart, periodEnd, itemId?)` returns:
  - Total COGS in period
  - Opening inventory value
  - Closing inventory value
  - Average inventory
  - Turnover ratio (COGS / Avg Inventory)
  - Days inventory outstanding (365 / ratio)
- [ ] Endpoint at `/api/inventory/turnover`

**Verification:** `php tests/InventoryTurnoverTest.php`

**Dependencies:** Task 0.2

**Files touched:**
- `src/Accounting/Domain/Service/InventoryService.php`
- `src/Accounting/Interfaces/HTTP/Inventory/TurnoverController.php`
- `config/routes.php`
- `config/services.php`
- `tests/InventoryTurnoverTest.php`

**Scope:** Medium (5 files)

---

### Task 3.3: Inventory valuation report by costing method

**Description:** Current valuation is visible only via cost layers table (raw SQL). Build a proper report: for each item, show opening qty/value, receipts (qty + cost), issues (qty + cost), closing qty/value, calculated per costing method with full audit trail.

**AC:**
- [ ] `InventoryService::getValuationReport(itemId?, warehouseId?, periodStart?, periodEnd?)`:
  - Opening: qty + value at period start (from cost layers snapshots or first layer)
  - Receipts: total qty + total value during period
  - Issues: total qty + total cost (FIFO/WA/Specific)
  - Closing: qty + value remaining
  - Unit cost at period end
- [ ] Endpoint at `/api/inventory/valuation`

**Verification:** `php tests/InventoryValuationTest.php`

**Dependencies:** Task 0.2, 1.2

**Files touched:**
- `src/Accounting/Domain/Service/InventoryService.php`
- `src/Accounting/Interfaces/HTTP/Inventory/ValuationController.php`
- `config/routes.php`
- `config/services.php`
- `tests/InventoryValuationTest.php`

**Scope:** Medium (5 files)

---

## Phase 4: Period-Closing Engine

### Task 4.1: Period close checklist

**Description:** Current `canClose` is a stub. Implement real pre-close validation:
- No unposted inventory transactions (all inventory txns should be 'posted')
- No draft count sessions
- Inventory sub-ledger balance = GL balance (152/153/155/156)
- No negative stock at period end (unless allowed)
- Consignment balance = 157 balance
- In-transit balance = 151 balance
- All impairment provisions booked

**AC:**
- [ ] `PeriodService::canClose(id)` returns detailed result per check:
  - `check`: description
  - `passed`: bool
  - `note`: detail or empty
- [ ] All checks must pass for `canClose` = true
- [ ] Each check has a SQL query verifying the condition
- [ ] Endpoint `/api/periods/:id/can-close` returns full check list

**Verification:** `php tests/PeriodCloseChecklistTest.php`

**Dependencies:** Task 0.1

**Files touched:**
- `src/Accounting/Domain/Service/PeriodService.php`
- `tests/PeriodCloseChecklistTest.php`

**Scope:** Medium (2 files)

---

### Task 4.2: Inventory close procedure during period close

**Description:** When closing a period, inventory must be finalized:
1. All issue costs calculated and posted (COGS up to date)
2. Periodic inventory valuation computed (if using periodic PPV)
3. Inventory sub-ledger reconciled with GL
4. Cost layer snapshot for the period archived
5. No further inventory mutations allowed in closed period (already gated by Task 0.1)

**AC:**
- [ ] `PeriodService::executeClosingEntries` calls new `InventoryService::closeInventoryForPeriod(periodId, closedBy)`:
  - Compute periodic inventory for items with PPV = periodic
  - Archive cost layer snapshot to `period_inventory_snapshots` table
  - Verify sub-ledger = GL for all inventory accounts
  - Record result in audit log
- [ ] Close fails if inventory sub-ledger ≠ GL

**Verification:** `php tests/PeriodCloseInventoryTest.php`

**Dependencies:** Task 0.1, 1.2, 4.1

**Files touched:**
- `src/Accounting/Domain/Service/PeriodService.php`
- `src/Accounting/Domain/Service/InventoryService.php`
- `database/migrations/XXX_create_period_inventory_snapshots_table.php`
- `tests/PeriodCloseInventoryTest.php`

**Scope:** Medium (4 files)

---

### Task 4.3: Period re-open with inventory rollback

**Description:** If a closed period needs re-opening (audit adjustment), inventory must be restored to pre-close state. Current `reOpenPeriod` changes status but doesn't touch inventory. Implement inventory rollback from the snapshot created during close.

**AC:**
- [ ] `InventoryService::rollbackInventoryForPeriod(periodId, rolledBackBy)`:
  - Restore cost layers from snapshot
  - Revert stock quantities to pre-close values
  - Post reversal journal entry for COGS adjustment
  - Log to audit
- [ ] `PeriodService::reOpenPeriod` calls `rollbackInventoryForPeriod` before changing status
- [ ] Rollback fails if current period has inventory mutations (warn user)

**Verification:** `php tests/PeriodReopenInventoryTest.php`

**Dependencies:** Task 4.2

**Files touched:**
- `src/Accounting/Domain/Service/InventoryService.php`
- `src/Accounting/Domain/Service/PeriodService.php`
- `tests/PeriodReopenInventoryTest.php`

**Scope:** Medium (3 files)

---

## Phase 5: Advanced Features

### Task 5.1: NRV impairment engine

**Description:** Current `recordImpairment` is manual. Build NRV engine that:
1. Compares item's unit cost with net realizable value (market price - selling costs)
2. If NRV < cost, calculates provision = qty × (cost - NRV)
3. Generates proposed impairment entries
4. Allows user to approve/reject proposed entries
5. Auto-reverses when NRV recovers (max reversal = original provision)

**AC:**
- [ ] `InventoryService::calculateImpairmentProposal(itemId?, date?)` returns proposed impairments
- [ ] NRV configurable per item: `nrv` field or fallback to sale_price - selling_cost_pct
- [ ] `InventoryService::applyImpairmentProposal(proposalId, approvedBy)` creates entries
- [ ] `InventoryService::autoReverseImpairment(itemId, date)` checks if NRV recovered
- [ ] Migration adds `nrv`, `selling_cost_pct` to items table

**Verification:** `php tests/InventoryNrvImpairmentTest.php`

**Dependencies:** Task 0.2

**Files touched:**
- `src/Accounting/Domain/Service/InventoryService.php`
- `src/Accounting/Domain/Model/Item.php`
- `database/migrations/XXX_add_nrv_fields_to_items.php`
- `database/migrations/XXX_create_impairment_proposals_table.php`
- `config/routes.php`
- `tests/InventoryNrvImpairmentTest.php`

**Scope:** Large (6 files)

---

### Task 5.2: Production / WIP integration

**Description:** Connect inventory with production orders. When issuing to production (Dr 154), track which production order consumed what. When finished goods are received from production, record with BOM reference. This enables WIP valuation and production cost analysis.

**AC:**
- [ ] `InventoryService::issueToProduction(itemId, qty, productionOrderId, reference, createdBy)`:
  - Dr 154 — Cr Inventory at cost
  - Link to production order in `production_order_materials` table
- [ ] Migration creates `production_orders` and `production_order_materials` tables
- [ ] Receipt from production: `receiveFromProduction(itemId, qty, unitCost, productionOrderId, createdBy)`
  - Dr Inventory — Cr 154 (WIP cleared)
  - Record in production order as completed

**Verification:** `php tests/InventoryProductionTest.php`

**Dependencies:** Task 0.2

**Files touched:**
- `src/Accounting/Domain/Service/InventoryService.php`
- `database/migrations/XXX_create_production_tables.php`
- `config/services.php`
- `config/routes.php`
- `tests/InventoryProductionTest.php`

**Scope:** Medium (5 files)

---

### Task 5.3: Batch/expiry management enhancements

**Description:** Batch fields exist in cost layers but no dedicated management UI. Build:
1. Batch master list (`batches` table)
2. Batch assignment during goods receipt
3. Batch tracking in stock card
4. Expiry alerts (dashboard widget)
5. FIFO by expiry date (FEFO) as additional costing variant

**AC:**
- [ ] `batches` table with batch_code, item_id, manufacturing_date, expiry_date, initial_qty, remaining_qty
- [ ] Receipt with batch creates/updates batch record
- [ ] FEFO costing method: consume nearest-expiry layers first
- [ ] Expiry alerts: items expiring within 30/60/90 days
- [ ] Batch report: stock by batch with aging

**Verification:** `php tests/InventoryBatchManagementTest.php`

**Dependencies:** Task 0.2, 1.2

**Files touched:**
- `src/Accounting/Domain/Service/InventoryService.php`
- `database/migrations/XXX_create_batches_table.php`
- `config/routes.php`
- `public/views/batch_tracking.php`
- `tests/InventoryBatchManagementTest.php`

**Scope:** Large (8+ files)

---

## Dependency Graph

```
0.1 Period Gate
  ├── 0.2 Test Period Setup
  │     ├── 1.1 Negative Stock
  │     │     └── 1.2 Costing Method
  │     │           └── 1.3 Weighted Avg Logic
  │     ├── 2.1 Supplier Return
  │     ├── 2.2 Damaged Write-off
  │     ├── 2.3 Promotional VAT
  │     ├── 3.1 Aging Report
  │     ├── 3.2 Turnover Ratio
  │     ├── 3.3 Valuation Report
  │     ├── 5.1 NRV Engine
  │     ├── 5.2 Production/WIP
  │     └── 5.3 Batch Mgmt
  └── 4.1 Close Checklist
        └── 4.2 Inventory Close
              └── 4.3 Period Re-open Rollback
```

## Recommendation: Implementation Order

| Order | Task | Why First |
|-------|------|-----------|
| 1 | 0.1 Period Gate | Data integrity — prevents corruption immediately |
| 2 | 0.2 Test Period Setup | Unblocks all testing |
| 3 | 1.1 Negative Stock | Fixes real SME operational flow |
| 4 | 1.2 Costing Method | Foundation for correct COGS |
| 5 | 1.3 Weighted Avg Logic | Completes costing |
| 6 | 2.1 Supplier Return | Missing core flow |
| 7 | 2.2 Damaged Write-off | Missing core flow |
| 8 | 2.3 Promotional VAT | Tax compliance |
| 9 | 4.1 Close Checklist | Period close readiness |
| 10 | 4.2 Inventory Close | Period close execution |
| 11 | 4.3 Period Re-open Rollback | Safety net |
| 12 | 3.1 Aging Report | Management reporting |
| 13 | 3.2 Turnover Ratio | Management reporting |
| 14 | 3.3 Valuation Report | Financial reporting |
| 15 | 5.1 NRV Engine | Advanced — after impairment cleanup |
| 16 | 5.2 Production/WIP | Integration — after core flows |
| 17 | 5.3 Batch Mgmt | Enhancement — lowest risk |

## Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Costing method change breaks existing COGS | High | All existing items default to FIFO; new method only applies when explicitly set |
| Period gate breaks existing API callers | Medium | Add `$date` param with default = today; existing JS callers unchanged |
| Tests fail without period seeding | High | Task 0.2 makes period seeding explicit in all tests |
| `closePeriodicInventory` destructive (deletes layers) | High | Task 4.2 archives snapshot before close; Task 4.3 implements rollback |
| Weighted average recalibration on existing layers | Medium | Only triggered for items with `perpetual` costing method; default method = FIFO (no change) |
| Re-opening a period with inventory adjustments | High | Rollback from snapshot (Task 4.3) — implement before exposing re-open to users |

## Open Questions

1. Should supplier return consume the specific cost layer from the original receipt (matching), or use FIFO in reverse? Vietnamese practice: return at original receipt cost if identifiable, otherwise at current avg cost.
2. Damaged goods write-off: is threshold approval handled at controller level or service level?
3. Production integration: should production orders be created via UI in this app, or imported from external ERP? Current decision: UI-based for SME scope.
4. NRV engine: how to determine selling_cost_pct? Fixed % per item type or item-level override?
5. Batch management: is serial number tracking needed for this SME app, or batch-only sufficient?

---

**Total estimated tasks:** 17
**Estimated total files touched:** ~65 files (including migrations, tests, views)
**Phases:** 6 (Foundation → Costing → Flows → Reconciliation → Close → Advanced)
