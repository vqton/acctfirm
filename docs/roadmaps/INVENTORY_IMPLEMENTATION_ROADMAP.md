# Inventory Module — Implementation Roadmap

**Version:** 2.0
**Last Updated:** 2026-05-19
**Base Spec:** `docs/specs/INVENTORY_PRINCIPLES_USE_CASE_SPECIFICATION.md`

---

## Current State Assessment

| Layer | Count | Status |
|---|---|---|
| Master Tables (Items, UOM, Warehouses, CCDC, etc.) | 16 | ✅ COMPLETE |
| COA (Circular 99 standard) | 72 accounts | ✅ COMPLETE |
| Valuation Method master | 5 methods | ✅ COMPLETE |
| **Inventory Transaction Modules** | **12 files, 80 tests** | ✅ COMPLETE |

---

## Implementation Plan (Actual)

Implementation followed a different phasing than originally drafted. Below is the actual plan.

### Phase 1: Core Engine + Transaction Integrity

**Goal:** Reliable posting engine with transaction safety, cost layer management, and basic receive/issue/transfer.

| Task | Description | Status |
|---|---|---|
| 1.1 | Transaction wrapping: all 16 InventoryService methods in `beginTransaction/commit/rollback`. JournalService `postEntry` checks `$pdo->inTransaction()` to avoid nested transaction errors. | ✅ |
| 1.2 | FIFO cost layers: `consumeCostLayers()` returns `['total_cost', 'remaining']`. All issue methods use actual layer cost. | ✅ |
| 1.3 | Receipt + Issue controllers, views, routes. 8 new API endpoints. Sidebar links. | ✅ |
| 1.4 | CSRF + permission guard on all 11 inventory POST endpoints. | ✅ |

**Tests:** InventoryReceiptTest (9), InventoryIssueTest (6), InventoryTransferTest (11)

---

### Phase 2: Account Mapping + Special Issue Types

**Goal:** Correct TK mapping per Circular 99 for all inventory item types and issue destinations.

| Task | Description | Status |
|---|---|---|
| 2.1 | **Customer Returns (UC-015):** `returnFromCustomer()` restores inventory at weighted average cost, reverses COGS (Dr 155/156 — Cr 632). Full vertical slice: controller, view (`/kho/hang-ban-tra-lai`), routes, sidebar. | ✅ |
| 2.2 | **TK 153 (CCDC):** Tool items map to TK 153 instead of TK 152. | ✅ |
| 2.3 | **TK 241 (Construction CIP):** `issueGoods()` with `issueType='construction'` posts Dr 241 / Cr 152. Uses `match()` to handle 3 types + reject invalid. | ✅ |

**Tests:** CustomerReturnTest (4), Tk153Test (7), Tk241Test (6)

---

### Phase 3: Consignment, Transit, Periodic Count

**Goal:** Track goods in transit, on consignment, and periodic inventory adjustments.

| Task | Description | Status |
|---|---|---|
| 3.1 | **Consignment (TK 157):** `consignGoods()`, `sellConsigned()`, `returnConsigned()` — full lifecycle. | ✅ |
| 3.2 | **In-transit (TK 151):** `receiveInTransit()`, `arriveFromTransit()`. | ✅ |
| 3.3 | **Physical count:** `startPhysicalCount()`, `recordCount()`, `adjustPhysicalCount()`. | ✅ |
| 3.4 | **Periodic inventory:** `closePeriodicInventory()` for non-perpetual methods. | ✅ |

**Tests:** InventoryConsignmentTest (10), InventoryTransitTest (10), InventoryPhysicalCountTest (10), InventoryPeriodicTest (7)

---

### Phase 4: Adjustments, Impairment, Promotions

**Goal:** Handle special inventory events — impairment provisions, promotional giveaways, and ad-hoc adjustments.

| Task | Description | Status |
|---|---|---|
| 4.1 | **Impairment (TK 2294):** `recordImpairment()`, `reverseImpairment()`. | ✅ |
| 4.2 | **Promotional (TK 641):** `issuePromotional()` for unconditional giveaways. | ✅ |

**Tests:** InventoryImpairmentTest (6), InventoryPromotionalTest (4)

---

## Coverage by Circular 99

| TK | Description | Type | Status |
|---|---|---|---|
| 151 | Hàng mua đang đi đường | Asset (in-transit) | ✅ Consignment ≡ Transit |
| 152 | Nguyên liệu, vật liệu | Raw materials | ✅ |
| 153 | Công cụ, dụng cụ | Tools/supplies | ✅ |
| 154 | CPSXKD dở dang | WIP | ✅ (production issue) |
| 155 | Thành phẩm | Finished goods | ✅ (via inventoryAccountMap) |
| 156 | Hàng hóa | Merchandise | ✅ (via inventoryAccountMap) |
| 157 | Hàng gửi đi bán | Consignment | ✅ |
| 241 | XDCB dở dang | Construction CIP | ✅ |
| 632 | Giá vốn hàng bán | COGS | ✅ |
| 641 | Chi phí bán hàng | Selling expense | ✅ (promotional) |
| 2294 | Dự phòng giảm giá HTK | Impairment provision | ✅ |
| 1381 | Tài sản thiếu chờ xử lý | Asset deficit | ✅ (physical count) |
| 3381 | Tài sản thừa chờ xử lý | Asset surplus | ✅ (physical count) |

## Forward Plan

| Next | Priority | Notes |
|---|---|---|
| Batch/Serial tracking | Medium | Phase 3 of original draft — not yet implemented |
| Landed cost allocation (multiple add-on costs per receipt) | Low | Current `receiveGoods` accepts `$addonCosts` array |
| Foreign currency purchase | Low | Requires FX sub-module |
| Weighted Average (periodic) recalculation on every receipt | Low | Currently FIFO only; user can call `calculateAndUpdateUnitCost` manually |

## InventoryService Methods (16 total)

| Method | Type | Lines |
|---|---|---|
| `receiveGoods` | Receipt | ~40 |
| `issueGoods` | Issue | ~35 |
| `transferBetweenWarehouses` | Transfer | ~30 |
| `transferToTransit` | Transit-out | ~20 |
| `receiveInTransit` | Transit-in | ~20 |
| `arriveFromTransit` | Transit-arrive | ~30 |
| `consignGoods` | Consign-out | ~20 |
| `sellConsigned` | Consign-sale | ~30 |
| `returnConsigned` | Consign-return | ~25 |
| `returnFromCustomer` | Customer return | ~25 |
| `startPhysicalCount` | Count | ~15 |
| `recordPhysicalCount` | Count | ~20 |
| `adjustPhysicalCount` | Count-adjust | ~30 |
| `closePeriodicInventory` | Periodic | ~20 |
| `recordImpairment` | Impairment | ~25 |
| `issuePromotional` | Promotional | ~25 |
| `reverseImpairment` | Impairment | ~20 |

## Test Summary

| File | Tests | Status |
|---|---|---|
| InventoryReceiptTest.php | 9 | ✅ |
| InventoryIssueTest.php | 6 | ✅ |
| InventoryTransferTest.php | 11 | ✅ |
| InventoryConsignmentTest.php | 10 | ✅ |
| InventoryTransitTest.php | 10 | ✅ |
| InventoryPhysicalCountTest.php | 10 | ✅ |
| InventoryPeriodicTest.php | 7 | ✅ |
| InventoryImpairmentTest.php | 6 | ✅ |
| InventoryPromotionalTest.php | 4 | ✅ |
| CustomerReturnTest.php | 4 | ✅ |
| Tk153Test.php | 7 | ✅ |
| Tk241Test.php | 6 | ✅ |
| **Total** | **80** | **✅ 0 failures** |
