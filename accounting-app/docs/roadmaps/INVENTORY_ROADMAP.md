# Inventory Module — Implementation Roadmap

## Legend

| Symbol | Meaning |
|---|---|
| ✅ | Done (implemented + tests) |
| ◐ | Partial (service exists, no UI / missing routes) |
| ⚠️ | Deprioritized (low business value per effort) |
| ❌ | Not started |

---

## Phase 0 — Current State Audit (Done)

### Scorecard

| UC | Name | Status | Lines of Code | Tests |
|---|---|---|---|---|
| INV-UC-01 | Valuation Method Config | ◐ | 66 (repo) + 30 (controller) | 0 |
| INV-UC-02 | Cost Flow (Perpetual/Periodic) | ◐ | 50 (service) + controller | 2 |
| INV-UC-03 | Goods in Transit (TK 151) | ✅ | 69 (controller) + 72 (service) | 4 |
| INV-UC-04 | Receive Raw Materials (TK 152) | ◐ | 26 (service) — **no controller** | 3 |
| INV-UC-05 | Receive Tools (TK 153) | ❌ | Mapped to 152, no 153 account | 0 |
| INV-UC-06 | Receive Finished Goods (TK 154→155) | ❌ | — | 0 |
| INV-UC-07 | Receive Merchandise (TK 156) | ◐ | Same as UC-04 (receiveGoods) | 0 |
| INV-UC-08 | Issue to Production (TK→154) | ◐ | 30 (service) — **no controller** | 3 |
| INV-UC-09 | Issue for Construction (TK→241) | ❌ | — | 0 |
| INV-UC-10 | Issue Tools Single-Period (TK→Expense) | ❌ | — | 0 |
| INV-UC-11 | Issue Tools Multi-Period (TK→242) | ❌ | — | 0 |
| INV-UC-12 | Outsource Processing | ❌ | — | 0 |
| INV-UC-13 | Consignment Out (TK 156→157) | ✅ | 87 (controller) + 104 (service) | 4 |
| INV-UC-14 | Self-Process Materials | ❌ | — | 0 |
| INV-UC-15 | Sell Goods (TK→632) | ◐ | Same as UC-08 (issueGoods sale type) | 0 |
| INV-UC-16 | Scrap Obsolete Inventory | ❌ | — | 0 |
| INV-UC-17 | Contribute Inventory as Capital | ❌ | — | 0 |
| INV-UC-18 | Promotion/ Giveaway (TK→641) | ✅ | 36 (controller) + 24 (service) | 2 |
| INV-UC-19 | Accumulate Direct Material (621→154) | ❌ | — | 0 |
| INV-UC-20 | Accumulate Direct Labor (622→154) | ❌ | — | 0 |
| INV-UC-21 | Accumulate Overhead (627→154) | ❌ | — | 0 |
| INV-UC-22 | Product Cost → Finished Goods | ❌ | — | 0 |
| INV-UC-23 | Spoiled/ Defective Goods | ❌ | — | 0 |
| INV-UC-24 | Consignment Sales (157→632) | ✅ | Same as UC-13 | 0 |
| INV-UC-25 | Physical Count Surplus | ✅ | 75 (controller) + 42 (service) | 4 |
| INV-UC-26 | Physical Count Shortage | ✅ | Same as UC-25 | 0 |
| INV-UC-27 | Impairment Provision (2294) | ✅ | 66 (controller) + 46 (service) | 3 |
| INV-UC-28 | Bonded Warehouse Import (TK 158) | ❌ | — | 0 |
| INV-UC-29 | Issue Bonded to Production | ❌ | — | 0 |
| INV-UC-30 | Re-export/ Destroy Bonded | ❌ | — | 0 |
| INV-UC-31 | Sell Bonded Domestically | ❌ | — | 0 |
| INV-UC-32 | Allocate Purchase Costs | ◐ | Supported in receiveGoods(), no UI | 1 |
| INV-UC-33 | Provisional Price Coefficient | ❌ | — | 0 |
| INV-UC-34 | Customer Returns | ❌ | — | 0 |
| **Extra** | Inter-Warehouse Transfer | ✅ | 83 (controller) + 78 (service) | 4 |

**Totals:** 11 complete, 4 partial, 19 not started

### Code Quality Findings

| Severity | Issue | Location | Detail |
|---|---|---|---|
| **High** | No receive/issue views | Sidebar lines 152-153 | `#` placeholders |
| **High** | No receive/issue API routes | routes.php | Missing |
| **High** | No transaction wrapping | InventoryService all methods | Risk of partial updates |
| **Medium** | issueGoods uses purchasePrice | InventoryService.php:80 | Should consume cost layers for actual unit cost, not item->purchasePrice |
| **Medium** | No CSRF checks | All operational controllers | Missing Auth::checkCsrf() |
| **Medium** | No permission checks | All operational controllers | Missing Auth::requirePermission() |
| **Medium** | Tools mapped to TK 152 | inventoryAccountMap:20 | Violates VAS/TT 99 — should be TK 153 |
| **Low** | No FK constraints | cost_layers, transit, consignment tables | Referential integrity via app only |
| **Low** | Static weight limit | DB schema | Purchase price in items table doesn't scale |

---

## Phase 1 — Critical Fixes (Week 1)

> **Goal:** Unblock core daily operations. Receive + Issue are the highest-frequency inventory transactions.

### 1.1 Add Transaction Wrapping to InventoryService

**Problem:** Every method directly calls `journal->postEntry()` then updates stock + cost layers separately. If the DB crashes between step 1 and step 2, the ledger has entries but stock is wrong (or vice versa).

**Fix:** Wrap every public method in `beginTransaction/commit/rollback`.

**Files:** `src/Accounting/Domain/Service/InventoryService.php`

**Effort:** ~30 min. One-time change to all 16 public methods.

**Tests:** All 77 existing tests must still pass.

### 1.2 Fix issueGoods() Cost Calculation

**Problem:** `issueGoods()` uses `item->getPurchasePrice()` (a cached weighted average on the master record) instead of consuming FIFO cost layers. This means:
- COGS reflects the running weighted average, not actual FIFO cost
- `consumeCostLayers()` still runs after but only for quantity tracking — the amounts don't match

**Fix:** Consume cost layers first to compute actual cost, then use that for the journal entry.

**Files:** `InventoryService.php:68-98`

**Effort:** ~1 hr. Refactor `consumeCostLayers` to return cost breakdowns.

**Tests:** Update `InventoryIssueTest.php` assertions. Add test with multiple cost layers at different prices.

### 1.3 Add Receive + Issue API Routes

**Problem:** `receiveGoods()` and `issueGoods()` have no HTTP endpoints. Sidebar points to `#`.

**Deliverables:**

| File | Action |
|---|---|
| `config/routes.php` | Add POST `/api/inventory/receive` → new `InventoryReceiptController::receive()` |
| `config/routes.php` | Add GET `/api/inventory/receive/items` → `ItemController::list()` |
| `config/routes.php` | Add POST `/api/inventory/issue` → new `InventoryIssueController::issue()` |
| `config/routes.php` | Add GET `/kho/nhap-kho` → view |
| `config/routes.php` | Add GET `/kho/xuat-kho` → view |
| `src/Accounting/Interfaces/HTTP/Inventory/ReceiptController.php` | New controller: `receive()`, `items()`, `list()` |
| `src/Accounting/Interfaces/HTTP/Inventory/IssueController.php` | New controller: `issue()`, `items()`, `list()` |
| `public/views/receipt.php` | New view: "Nhập kho" form |
| `public/views/issue.php` | New view: "Xuất kho" form |
| `config/services.php` | Wire both controllers |
| `public/views/layout.php` | Update sidebar links (lines 152-153) |

**API Contracts:**

```
POST /api/inventory/receive
Body: { item_id, qty, unit_price, addon_costs?: [{name, amount}], reference?, created_by? }
Response: { transaction_id, total_cost }

POST /api/inventory/issue
Body: { item_id, qty, issue_type: "production"|"sale", reference?, created_by? }
Response: { transaction_id, total_cost }
```

**Effort:** ~4 hrs (2 controllers + 2 views + wiring)

**Tests:** `InventoryReceiptTest.php` + `InventoryIssueTest.php` already exist (3 test groups each). Update to cover edge cases.

### 1.4 Add CSRF + Permission Checks

**Problem:** No operational inventory controller calls `Auth::checkCsrf()` or `Auth::requirePermission()`.

**Fix:** Add to all inventory controllers' write methods (POST/PUT/DELETE).

**Files:** All 8 controllers + CrudControllerTrait already has CSRF — ensure operational controllers call it.

**Effort:** ~1 hr

---

## Phase 2 — Immediate Value (Week 1-2)

> **Goal:** Deliver the most impactful remaining use cases.

### 2.1 Customer Returns (INV-UC-34)

**What:** Goods returned by customer → reverse COGS, restore inventory.

**Accounting:** Dr inventory → Cr 632 (COGS reversal) + Dr 521 (revenue deduction) → Cr 131.

**Deliverables:**

| File | Action |
|---|---|
| `InventoryService.php` | Add `returnFromCustomer(itemId, qty, reference, createdBy)` |
| `ReturnController.php` | New controller: `returnGoods()`, `list()` |
| `routes.php` | POST `/api/inventory/returns` + GET `/kho/hang-tra-lai` |
| `public/views/returns.php` | New view |
| `tests/InventoryReturnTest.php` | ~3 test groups |

**Effort:** ~3 hrs

### 2.2 Separate TK 153 Account (INV-UC-05)

**What:** Tools (CCDC) currently post to TK 152. Per TT 99, they must post to TK 153.

**Fix:**
1. Update `inventoryAccountMap` to map `'tool' => '153'`
2. Verify TK 153 exists in `chart_of_accounts` (migration to add if missing)
3. Update item views to show CCDC type properly

**Effort:** ~30 min

### 2.3 Add TK 241 Issue Type (INV-UC-09)

**What:** Support issuing materials to construction/asset repair.

**Fix:** Add `'construction'` issueType to `issueGoods()`. Map to account 241.

**Effort:** ~30 min (small change in InventoryService + route + view update)

---

## Phase 3 — WIP + Production Costing (Week 2-3)

> **Goal:** Enable manufacturing enterprises to track work-in-progress and finished goods.

### 3.1 WIP Cost Accumulation (INV-UC-19, 20, 21)

**What:** Transfer DM (621), DL (622), OH (627) → WIP (154) at period-end.

**Deliverables:**

| File | Action |
|---|---|
| `InventoryService.php` | Add `accumulateMaterialToWip()`, `accumulateLaborToWip()`, `accumulateOverheadToWip()` |
| `WipController.php` | New controller |
| `routes.php` | POST + GET endpoints |
| `public/views/wip.php` | New view |
| `tests/WipTest.php` | ~6 test groups |

**Effort:** ~4 hrs

### 3.2 Product Cost Calculation + Transfer to Finished Goods (INV-UC-06, 22)

**What:** Calculate actual WIP → FG. Compute opening + input - closing = cost of goods manufactured.

**Deliverables:**

| File | Action |
|---|---|
| `InventoryService.php` | Add `calculateProductionCost()` + `transferToFinishedGoods()` |
| routes + controller + view | Standard pattern |
| `tests/ProductionCostTest.php` | ~4 test groups |

**Effort:** ~4 hrs

### 3.3 Spoiled/Defective Goods (INV-UC-23)

**What:** Record normal vs abnormal spoilage. Normal → COGS, abnormal → receivables.

**Effort:** ~2 hrs

---

## Phase 4 — Valuation Completeness (Week 3)

> **Goal:** Support all 5 costing methods per TT 99.

### 4.1 Implement Actual FIFO Costing

**What:** The cost layer system already supports FIFO (ORDER BY created_at ASC). The issue is that `issueGoods()` doesn't use them. After Phase 1.2, fix is done.

### 4.2 Implement Specific ID Costing

**What:** Per-item cost tracking for high-value goods.

**Effort:** ~2 hrs (new flag on item: `costing_method`, specific path in `issueGoods()`)

### 4.3 Implement Standard Cost + Variance

**What:** Maintain standard cost on items. Track variance accounts (TK 1521 variance). Coefficient formula.

**Effort:** ~6 hrs (new tables, new methods, new controllers)

### 4.4 Implement Provisional Price Coefficient (INV-UC-33)

**What:** For raw materials where provisional prices are used at receipt and actual costs adjusted later.

**Effort:** ~3 hrs

---

## Phase 5 — Advanced Transactions (Week 4)

> **Goal:** Complete remaining non-bonded scenarios.

### 5.1 Tools Capitalization (INV-UC-10, 11)

**What:** Single-period tools → direct expense. Multi-period tools → TK 242 (prepaid) with amortization schedule.

**Effort:** ~3 hrs (amortization engine may already exist in TSCĐ module)

### 5.2 Outsource Processing (INV-UC-12)

**What:** Send materials to processor → receive back processed goods + pay processing fee.

**Effort:** ~2 hrs

### 5.3 Self-Processing (INV-UC-14)

**What:** Internal transformation of materials (e.g., cutting timber). TK 152 → TK 154 → TK 152 (new form).

**Effort:** ~1 hr (reuses WIP flow)

### 5.4 Scrap Inventory (INV-UC-16)

**What:** Write-off obsoletes. Dr 632 → Cr inventory. Revenue from scrap sale → Dr 111 → Cr 711.

**Effort:** ~1 hr

### 5.5 Capital Contribution (INV-UC-17)

**What:** Contribute inventory to subsidiaries. Dr 221/222 → Cr inventory + Dr/Cr difference to 811/711.

**Effort:** ~2 hrs

### 5.6 Customer Returns (INV-UC-34)

**Already in Phase 2** — moved up due to business value.

---

## Phase 6 — Bonded Warehouse (Week 4-5)

> **Goal:** TK 158 — for duty-free import/export manufacturing enterprises.

### 6.1 TK 158 Full Module (INV-UC-28 through 31)

**Deliverables:**

| Component | Effort |
|---|---|
| Migration: `inventory_bonded` table | ~1 hr |
| Model + Repository + PDO | ~1 hr |
| Service methods (import, issue, re-export, destroy, domestic sale) | ~4 hrs |
| Controller + routes + view | ~2 hrs |
| Tests | ~3 hrs |

**Note:** Domestic sale of bonded materials requires customs integration — coordinate with tax module.

---

## Phase 7 — Reports & Compliance (Week 5)

> **Goal:** Audit-ready reporting.

### 7.1 Inventory Aging Report

**SQL:** `SELECT item_id, SUM(CASE WHEN days_in_stock <= 30 THEN qty END) as bracket_0_30, ...`

**Effort:** ~2 hrs

### 7.2 Inventory Transaction Audit Trail

**Fix:** Add `AuditLogger` calls to all InventoryService methods.

**Effort:** ~1 hr

### 7.3 Period-End Locking

**Problem:** No validation that inventory transactions are blocked after period close.

**Fix:** Inject `PeriodService` into InventoryService, check `canPost()` before every operation.

**Effort:** ~2 hrs

### 7.4 Physical Count Lock

**Problem:** Transactions can happen during physical count, causing drift.

**Fix:** When a count session is `'in_progress'`, block inventory movements for those items.

**Effort:** ~2 hrs

---

## Phase 8 — Master Data & Config (Week 6)

> **Goal:** Complete the master data story.

### 8.1 Batch / Lot / Serial Tracking

**Migration:** Add `batch_number`, `expiry_date` to items table + cost layers.

**Effort:** ~4 hrs (impacts all receipt/issue/transfer flows)

### 8.2 Consignment-in Tracking

**What:** Off-balance sheet tracking (TK 002) for goods held on behalf of suppliers.

**Effort:** ~2 hrs

### 8.3 Exchange Rate Revaluation of FX Inventory

**Fix:** When inventory purchased in foreign currency is settled at a different rate, the difference goes to Dr/Cr 413 (Exchange rate differences) or 635/515.

**Effort:** ~3 hrs

---

## Schedule Summary

```
Week 1   Phase 1: Critical Fixes (transactions, routing, cost fix, CSRF)
Week 1-2 Phase 2: Returns, TK 153, TK 241 issue  
Week 2-3 Phase 3: WIP + Production Costing
Week 3   Phase 4: Valuation Methods (FIFO, Specific ID, Standard)
Week 4   Phase 5: Advanced Transactions (tools, outsourcing, scrap, capital)
Week 4-5 Phase 6: Bonded Warehouse
Week 5   Phase 7: Reports & Compliance
Week 6   Phase 8: Master Data & Config
```

**MVP (minimum viable for daily operations) = Phase 1 only** — unblocks receive/issue. ~6 hrs work.

**Production-ready = Phase 1 + 2 + 7** — enterprises can use for daily operations with proper audit trail. ~12 hrs work.

**Full compliance = All phases** — covers all Circular 99 inventory accounts (151-158) with all 5 valuation methods. ~40 hrs work.

---

## Dependencies & Risks

| Dependency | Risk | Mitigation |
|---|---|---|
| `JournalService::postEntry()` supports Dr/Cr lines | None ❌ | Already works |
| TK 153 exists in COA | Low | Add migration if missing |
| TK 241 exists in COA | None ❌ | Already in TT 99 COA |
| TK 242 exists in COA | None ❌ | Already in TT 99 COA |
| PeriodService.isClosed() | Low | Already implemented |
| Bonded warehouse customs integration | Medium | Coordinate with fiscal/tax module |
| Standard cost variance accounts | Medium | Need new COA accounts (1521, etc.) |
