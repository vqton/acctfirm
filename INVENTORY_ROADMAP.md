# Inventory Module — Implementation Roadmap

Based on the 12 use cases defined in `INVENTORY_USE_CASE_SPECIFICATION.md`.

---

## Current Status

| Layer | Count | Status |
|---|---|---|
| Master Tables (Items, UOM, Warehouses, CCDC, etc.) | 16 | ✅ COMPLETE |
| COA (Circular 99 standard) | 72 accounts | ✅ COMPLETE |
| Valuation Method master | 5 methods | ✅ COMPLETE |
| **Inventory Transaction Modules** | **0** | ❌ NOT STARTED |

---

## Implementation Plan

### Phase 1: Valuation Engine (UC-04)

**Goal:** Set up the cost calculation method per item so all future transactions compute correct issue cost.

| Step | Description | Files | Verification |
|---|---|---|---|
| 1a | Add `valuation_method_id` column to `items` table | Migration 021 | Items link to valuation methods |
| 1b | Update Item model with `getValuationMethod()`, `setValuationMethod()` | Item.php | REST API returns method |
| 1c | **Views:** Items create/edit form shows valuation method dropdown | items.php | User can assign method |
| 1d | **Valuation engine service:** Weighted Average calculator (periodic + perpetual) | `src/Accounting/Domain/Service/ValuationService.php` | Unit test: cost per unit correct after 3 receipts |

**Estimated: 1 session**

---

### Phase 2: Goods Receipt (UC-03, UC-05, UC-12)

**Goal:** Record inventory purchases with landed cost calculation, updating quantity + value simultaneously.

| Step | Description | Verification |
|---|---|---|
| 2a | **InventoryReceipt transaction:** POST `/api/inventory/receipt` — Dr Inventory (TK 152/155/156) — Cr AP/Cash | Journal entry balances |
| 2b | **Landed cost allocation:** Freight, insurance, import duty, non-refundable taxes added to unit cost | Unit cost = (total cost) / quantity |
| 2c | **Bonus/free items:** Fair value allocation for items received with purchase | Bonus item has non-zero cost |
| 2d | **Foreign currency purchase:** Spot rate at transaction date, prepayment rate locking | FX difference → TK 413 |
| 2e | **Real-time quantity + value update:** UC-12 — every receipt updates both | Balance query returns correct qty + value |

**Estimated: 2 sessions**

---

### Phase 3: Goods Issue / COGS (UC-05, UC-12)

**Goal:** Record inventory issues (to production, to sale) using configured valuation method.

| Step | Description | Verification |
|---|---|---|
| 3a | **InventoryIssue transaction:** POST `/api/inventory/issue` — Dr Cost/Expense — Cr Inventory | COGS matches valuation method |
| 3b | **FIFO cost calculation:** Track cost layers; issue consumes oldest layer first | FIFO cost < latest purchase cost |
| 3c | **Weighted Average recalculation:** After each receipt, recalc average; issue at current average | Avg cost between min and max of layer costs |
| 3d | **Issue to production:** Dr TK 621/623/627 — Cr TK 152 | WIP increases |
| 3e | **Issue for sale:** Dr TK 632 — Cr TK 155/156 | COGS recorded |

**Estimated: 2 sessions**

---

### Phase 4: Inter-Warehouse Transfer

**Goal:** Move inventory between warehouses without ownership change or P&L impact.

| Step | Description | Verification |
|---|---|---|
| 4a | **Transfer transaction:** POST `/api/inventory/transfer` — Dr Warehouse B — Cr Warehouse A | Same quantity, same cost, different warehouse |

**Estimated: 0.5 session**

---

### Phase 5: Goods in Transit (UC-01, TK 151)

**Goal:** Track inventory purchased but not yet received at period-end.

| Step | Description | Verification |
|---|---|---|
| 5a | **In-transit receipt:** PO shipped, not yet received → Dr TK 151 — Cr AP | TK 151 balance > 0 |
| 5b | **In-transit arrival:** Goods received → Dr TK 152/155/156 — Cr TK 151 | TK 151 returns to 0 |
| 5c | **Period-end cut-off:** Identify open PO where title transferred but goods not received | Cut-off report matches in-transit balance |

**Estimated: 1 session**

---

### Phase 6: Consignment Goods (UC-02, TK 157)

**Goal:** Track goods sent to agents for sale (still enterprise property until sold).

| Step | Description | Verification |
|---|---|---|
| 6a | **Consignment dispatch:** Dr TK 157 — Cr TK 155/156 | TK 157 balance increases |
| 6b | **Consignment sale report:** Agent reports sale → Dr TK 632 — Cr TK 157; Dr AR — Cr Revenue | Revenue + COGS recorded |
| 6c | **Consignment return:** Unsold goods returned → Dr TK 155/156 — Cr TK 157 | Inventory restored |

**Estimated: 1 session**

---

### Phase 7: Physical Count & Adjustment (UC-07, UC-08)

**Goal:** Perform physical count, compare to book, adjust discrepancies.

| Step | Description | Verification |
|---|---|---|
| 7a | **Count sheet generation:** POST `/api/inventory/count-sheet` — creates count records per item/warehouse | Count sheet with book quantities |
| 7b | **Count entry:** PUT `/api/inventory/count-sheet/{id}` — record physical quantities | Variance calculated |
| 7c | **Adjustment posting:** Surplus → Dr Inventory — Cr TK 3381; Deficit → Dr TK 1381 — Cr Inventory | Book balance matches physical |
| 7d | **Resolution:** Approved surplus → Cr appropriate account; Deficit → Dr TK 632/1388/334 | Final resolution |

**Estimated: 2 sessions**

---

### Phase 8: Inventory Impairment (UC-09)

**Goal:** Calculate and record provision when NRV < carrying cost.

| Step | Description | Verification |
|---|---|---|
| 8a | **NRV input:** For each item, enter estimated selling price, completion cost, selling cost | NRV calculated |
| 8b | **Provision calculation:** If carrying cost > NRV → provision = carrying cost − NRV | Provision = difference |
| 8c | **Journal entry:** Dr TK 632 — Cr TK 2294 | Provision recorded |
| 8d | **Reversal:** If NRV recovers → Dr TK 2294 — Cr TK 632 (capped at original) | Cannot exceed original impairment |

**Estimated: 1 session**

---

### Phase 9: Promotional Inventory (UC-10)

**Goal:** Handle promotional giveaways and conditional promotions.

| Step | Description | Verification |
|---|---|---|
| 9a | **Unconditional promotion:** Free gift, no purchase → Dr TK 641 — Cr Inventory | Selling expense recorded |
| 9b | **Conditional promotion:** Buy 2 get 1 → allocate consideration; promoted item cost → Dr TK 632 | COGS includes promoted item |

**Estimated: 0.5 session**

---

### Phase 10: Periodic Inventory System (UC-06)

**Goal:** Simplified accounting for low-value, high-variety items.

| Step | Description | Verification |
|---|---|---|
| 10a | **Period-end calculation:** Issues = Opening + Receipts − Closing (physical) | Issues match formula |
| 10b | **Journal entry:** Dr TK 632 — Cr Inventory (closing entry for periodic system) | COGS equals calculated issues |

**Estimated: 0.5 session**

---

## Timeline Summary

| Phase | Sessions | Cumulative | Deliverable |
|---|---|---|---|
| P1: Valuation Engine | 1 | 1 | Items have valuation methods |
| P2: Goods Receipt | 2 | 3 | Purchase entries post to GL |
| P3: Goods Issue / COGS | 2 | 5 | COGS calculated per method |
| P4: Inter-Warehouse Transfer | 0.5 | 5.5 | Stock moves between locations |
| P5: Goods in Transit | 1 | 6.5 | Period-end cut-off works |
| P6: Consignment | 1 | 7.5 | Agent sales tracked |
| P7: Physical Count | 2 | 9.5 | Count → adjust → resolve |
| P8: Impairment | 1 | 10.5 | NRV provision recorded |
| P9: Promotional | 0.5 | 11 | Promo costs correctly classified |
| P10: Periodic System | 0.5 | 11.5 | Periodic method supported |
| **Total** | **11.5 sessions** | | **Full inventory module** |

---

## Recommended Start

**Phase 1 (Valuation Engine)** is the prerequisite for all other phases — without valuation methods configured per item, issue costs cannot be calculated. Estimated 1 session.

After Phase 1, the natural dependency order is:

```
P1: Valuation Engine
  ↓
P2: Goods Receipt (creates cost layers)
  ↓
P3: Goods Issue (consumes cost layers)
  ↓
P4: Transfer ← P5: In Transit ← P6: Consignment
  ↓
P7: Physical Count (compares book to physical)
  ↓
P8: Impairment (period-end valuation)
  ↓
P9: Promotional (ad-hoc)
P10: Periodic (alternative method)
```
