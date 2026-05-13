# Implementation Progress

## Architecture

```
public/index.php → autoloader → config/services.php (DI container) → config/routes.php
→ Router::dispatch() → Controller → Repository (PDO) → MySQL

JournalService::postEntry() → Transaction + LedgerEntry records → Account balance update
InventoryService::receiveGoods() / issueGoods() → JournalService + stock_qty + cost layers
```

---

## Master Data (16/16 tables — 100%)

| Table | Status | CRUD | View |
|---|---|---|---|
| items | ✅ | `ItemController` | `/danh-muc/vat-tu` |
| customers | ✅ | `CustomerController` | `/danh-muc/khach-hang` |
| suppliers | ✅ | `SupplierController` | `/danh-muc/nha-cung-cap` |
| employees | ✅ | `EmployeeController` | `/danh-muc/nhan-vien` |
| departments | ✅ | `DepartmentController` | `/danh-muc/phong-ban` |
| warehouses | ✅ | `WarehouseController` | `/danh-muc/kho` |
| uoms | ✅ | `UomController` | `/danh-muc/don-vi-tinh` |
| ccdc | ✅ | `CcdcController` | `/danh-muc/cong-cu-dung-cu` |
| bank_accounts | ✅ | `BankAccountController` | `/danh-muc/tai-khoan-ngan-hang` |
| fixed_assets | ✅ | `FixedAssetController` | `/danh-muc/tai-san-co-dinh` |
| tax_rates | ✅ | `TaxRateController` | `/danh-muc/bieu-thue` |
| exchange_rates | ✅ | `ExchangeRateController` | `/danh-muc/ty-gia` |
| valuation_methods | ✅ | `ValuationMethodController` | `/danh-muc/phuong-phap-tinh-gia` |
| contracts | ✅ | `ContractController` | `/danh-muc/hop-dong` |
| projects | ✅ | `ProjectController` | `/danh-muc/du-an` |
| depreciation_policies | ✅ | `DepreciationPolicyController` | `/danh-muc/chinh-sach-khau-hao` |
| accounts (COA) | ✅ (72 seeded) | `AccountController` | `/danh-muc/he-thong-tai-khoan` |

---

## COA — Circular 99 Standard (Appendix II — Full Compliance)

**Regulatory basis:** Article 11.1 of Circular 99/2025/TT-BTC requires enterprises to *"shall apply the chart of accounts provided in Appendix II"*. Article 11.2 permits modifications only if the enterprise issues an internal Accounting Policy Regulation. Default = full Appendix II.

**Current state:** Seed updated to match Appendix II exactly — all Level 1 and Level 2 accounts, correct names per TT99, correct normal balances, all sub-accounts. Abolished accounts (TK 611, 631) removed. Accounts renamed: 112, 155, 156, 158, 242, 419, etc. New accounts added: 171, 215 (with sub-accounts), 332, 344, 347, 353, 356, 357, 412, 413, 414, etc. Total: ~150 accounts covering all 9 classes.

**Key changes from previous seed:**
- TK 112 renamed from "Tiền gửi ngân hàng" → "Tiền gửi không kỳ hạn"
- TK 155 renamed from "Thành phẩm" → "Sản phẩm"
- TK 242 renamed from "Chi phí trả trước" → "Chi phí chờ phân bổ"
- TK 337 repurposed from "Trái phiếu phát hành" → "Thanh toán theo tiến độ hợp đồng XD"
- TK 344 repurposed from "Nợ thuê tài chính" → "Nhận ký quỹ, ký cược"
- TK 347 repurposed from "Doanh thu chưa thực hiện" → "Thuế TNDN hoãn lại phải trả"
- TK 356 repurposed from "Quỹ khen thưởng, phúc lợi" → "Quỹ phát triển KHCN"
- TK 631 removed (abolished in TT99 — was "Giá thành sản xuất")
- New accounts: 171, 1361-1368, 1381-1388, 2141-2147, 2151-2153, 2281-2288, 2291-2295, 2411-2414, 332, 3331-3339 (detailed tax sub-accounts), 3361-3368, 3381-3388, 3411-3412, 3431-3432, 3521-3525, 3531-3534, 3561-3562, 357, 4111-4118, 412, 413, 414, 4211-4212, 8211-8212

---

## Journal Engine (UC-01/02/03/07 — 25 tests)

| Service | File | Tests |
|---|---|---|
| `ValuationService` | `src/Accounting/Domain/Service/ValuationService.php` | `tests/ValuationServiceTest.php` — 4/4 |
| `JournalService` | `src/Accounting/Domain/Service/JournalService.php` | `tests/JournalServiceTest.php` — 12/12 |
| `InventoryService` | `src/Accounting/Domain/Service/InventoryService.php` | combined in receipt + issue tests |

### Key fixes applied
- `Account::debit()` — removed "Insufficient balance" check (wrong for double-entry)
- `PDOAccountRepository` — `hydrate()` now loads balance via `setBalance()`
- `PDOAccountRepository` — `ON DUPLICATE KEY UPDATE` includes `balance=VALUES(balance)`
- `JournalService::postEntry()` — Dr=Cr validation BEFORE balance changes (4-phase ordering)
- `Transaction` model — added `setStatus()`, `setCreatedBy()` setters
- `PDOTransactionRepository` — uses setters instead of private property access

### COA account posting rules (in JournalService)
- Dr asset/expense = increase (calls `credit()` on Account model)
- Cr liability/equity/revenue = increase (calls `credit()` on Account model)
- Reverse for opposite: calls `debit()` (decrease balance)

---

## Inventory Module — All 10 phases (98 tests)

### P1: Valuation Engine — 4/4 tests
- `ValuationService::calculateWeightedAverage()` — basic, single batch, zero qty, empty
- Items table: `valuation_method_id` column
- Valuation methods seeded: specific_id, weighted_avg, fifo, retail, standard_cost

### P2: Goods Receipt — 9/9 tests
- `InventoryService::receiveGoods()` — Dr Inventory (152/155/156) — Cr AP (331)
- Landed cost: base price + add-on costs (freight, duty, insurance)
- Stock quantity incremented, cost layers saved

### P3: Goods Issue / COGS — 6/6 tests
- `InventoryService::issueGoods()` — Dr COGS (632) or WIP (154) — Cr Inventory
- Insufficient stock rejected, FIFO cost layer consumption
- Issue types: `sale` → Dr 632, `production` → Dr 154

### Inventory Account Mapping (used by P2–P10)
| item_type | Inventory (Cr) | Sale (Dr) | Production (Dr) |
|---|---|---|---|
| material/tool/other | 152 | 632 | 154 |
| product | 155 | 632 | 154 |
| merchandise | 156 | 632 | 154 |

---

### P4: Warehouse Transfer — 11/11 tests
- `InventoryService::transferGoods()` — moves goods between warehouses
- Dr Inventory — Cr Inventory (same account, net zero on GL)
- Cost layers consumed from source, created for destination (warehouse_id tracking)
- Migration 023: `warehouse_id` on `inventory_cost_layers`
- API: `/api/transfers`, Frontend: `/kho/dieu-chuyen`

### P5: In Transit (TK 151) — 10/10 tests
- `InventoryService::recordInTransit()` — Dr 151 — Cr 331 (no stock change)
- `InventoryService::receiveFromTransit()` — Dr Inventory — Cr 151 (stock + cost layers)
- Partial receive support, transit record tracking
- Migration 024: `inventory_in_transit` table
- API: `/api/inventory-transit`, Frontend: `/kho/hang-dang-di-duong`

### P6: Consignment (TK 157) — 10/10 tests
- `InventoryService::consignGoods()` — Dr 157 — Cr Inventory, stock decreases
- `InventoryService::sellConsigned()` — Dr 632 — Cr 157
- `InventoryService::returnConsigned()` — Dr Inventory — Cr 157 (return to stock)
- Migration 025: `inventory_consignment` table
- API: `/api/consignments`, Frontend: `/kho/hang-gui-ban`

### P7: Physical Count — 10/10 tests
- `InventoryService::adjustPhysicalCount()` — surplus Dr Inventory / Cr 711, shortage Dr 632 / Cr Inventory
- `InventoryService::createCountSession()` — batch count session with lines
- Cost layers created/consumed for adjustments
- Migration 026: `inventory_count_sessions` + `inventory_count_lines`
- API: `/api/physical-count/*`, Frontend: `/kho/kiem-ke`

### P8: Impairment (TK 229) — 6/6 tests
- `InventoryService::recordImpairment()` — Dr 632 — Cr 229 (contra-asset, credit balance)
- `InventoryService::reverseImpairment()` — Dr 229 — Cr 632
- Remaining amount tracking, reversal validation
- Migration 027: `inventory_impairment` table
- API: `/api/impairments`, Frontend: `/kho/du-phong-giam-gia`

### P9: Promotional — 4/4 tests
- `InventoryService::issuePromotional()` — Dr 641 (selling expense) — Cr Inventory
- Stock decreases, cost layers consumed
- API: `POST /api/promotional/issue`

### P10: Periodic System — 7/7 tests
- `InventoryService::closePeriodicInventory()` — COGS = total available - closing value
- Periodic close clears cost layers, sets new closing stock
- Full multi-period COGS accumulation
- Migration 028: `periodic_inventory` table
- API: `/api/periodic`, Frontend: `/kho/kiem-ke-dinh-ky`

---

## All Tests (98 total — ALL PASS)

| Test file | Tests | Status |
|---|---|---|
| `tests/ValuationServiceTest.php` | 4 | ✅ PASS |
| `tests/JournalServiceTest.php` | 12 | ✅ PASS |
| `tests/TrialBalanceTest.php` | 9 | ✅ PASS |
| `tests/InventoryReceiptTest.php` | 9 | ✅ PASS |
| `tests/InventoryIssueTest.php` | 6 | ✅ PASS |
| `tests/InventoryTransferTest.php` | 11 | ✅ PASS |
| `tests/InventoryTransitTest.php` | 10 | ✅ PASS |
| `tests/InventoryConsignmentTest.php` | 10 | ✅ PASS |
| `tests/InventoryPhysicalCountTest.php` | 10 | ✅ PASS |
| `tests/InventoryImpairmentTest.php` | 6 | ✅ PASS |
| `tests/InventoryPromotionalTest.php` | 4 | ✅ PASS |
| `tests/InventoryPeriodicTest.php` | 7 | ✅ PASS |
| **Total** | **98** | **✅ ALL PASS** |

---

## API Endpoints

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/coa` | List COA accounts |
| POST | `/api/coa/seed` | Seed 72 standard accounts |
| POST | `/api/journal` | Post journal entry |
| GET | `/api/trial-balance` | Get trial balance |
| GET/POST/PUT/DELETE | `/api/items`, `customers`, etc. | 16 master data CRUDs |
| POST | `/api/transfers` | Warehouse transfer |
| GET | `/api/inventory-transit` | List goods in transit |
| POST | `/api/inventory-transit` | Record goods in transit |
| POST | `/api/inventory-transit/receive` | Receive from transit |
| GET | `/api/consignments` | List consignments |
| POST | `/api/consignments` | Send goods on consignment |
| POST | `/api/consignments/sell` | Sell consigned goods |
| POST | `/api/consignments/return` | Return from consignment |
| GET | `/api/physical-count/sessions` | List count sessions |
| POST | `/api/physical-count/sessions` | Create count session |
| POST | `/api/physical-count/adjust` | Adjust physical count |
| GET | `/api/impairments` | List impairment provisions |
| POST | `/api/impairments` | Record impairment |
| POST | `/api/impairments/reverse` | Reverse impairment |
| POST | `/api/promotional/issue` | Issue promotional goods |
| POST | `/api/periodic/close` | Close periodic inventory |

---

## How to Start Server

```bash
php -S 0.0.0.0:8800 -t /path/to/accounting-app/public /path/to/accounting-app/public/index.php
```

## How to Run Migrations

```bash
php /path/to/accounting-app/database/migrate.php
```

## How to Run All Tests

```bash
for f in tests/*.php; do php "$f"; done
```

## Critical Files

| File | Purpose |
|---|---|
| `public/index.php` | Entry point + autoloader |
| `config/services.php` | DI container |
| `config/routes.php` | All routes (50+ endpoints) |
| `config/database.php` | DB credentials (dev/123456) |
| `src/.../Service/JournalService.php` | Double-entry engine |
| `src/.../Service/InventoryService.php` | All 10 inventory phases (500+ lines) |
| `src/.../Service/ValuationService.php` | Weighted average calculator |
| `src/.../Model/Account.php` | COA account with balance |
| `src/.../Model/Transaction.php` | Journal entry |
| `src/.../Model/LedgerEntry.php` | Journal line |
| `src/.../Repository/PDOAccountRepository.php` | Account CRUD + balance |
| `database/migrate.php` | Migration runner |
| `database/migrations/*.php` | 28 migration files |
