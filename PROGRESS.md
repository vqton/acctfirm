# Implementation Progress

## Architecture

```
public/index.php → autoloader → config/services.php (DI container) → config/routes.php
→ Router::dispatch() → Controller → Repository (PDO) → MySQL

JournalService::postEntry() → Transaction + LedgerEntry records → Account balance update
InventoryService::receiveGoods() / issueGoods() → JournalService + stock_qty + cost layers
CashService → JournalService → Account balance (all cash/bank operations)
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
| accounts (COA) | ✅ (~150 seeded) | `AccountController` | `/danh-muc/he-thong-tai-khoan` |

---

## Cash & Bank Module — 9 UCs (6.5 weeks)

| Phase | Module | UC | Tests | Status |
|---|---|---|---|---|
| P1.1 | Cash Receipt (Phiếu thu) | UC-01 | CashTest (7) | ✅ |
| P1.2 | Cash Payment (Phiếu chi) | UC-02 | CashTest (4) | ✅ |
| P1.3 | Integration | — | CashTest + CashBankTest | ✅ |
| P2.1 | Bank Transactions | UC-03 | CashBankTest (8) | ✅ |
| P2.2 | Cash in Transit (TK 113) | UC-04 | CashTransitTest (4) | ✅ |
| P3.1 | Cash Book (Sổ quỹ) | UC-05 | CashBookTest (5) | ✅ |
| P3.2 | Petty Cash (Tạm ứng) | UC-07 | PettyCashTest (7) | ✅ |
| P4.1 | **Bank Reconciliation** | **UC-06** | **BankReconciliationTest (24)** | ✅ |
| P4.2 | **FX Cash (VAS 10)** | **UC-08** | **CashFXTest (17)** | ✅ |
| P5.1 | **Cash Reports** | **UC-09** | **CashReportTest (14)** | ✅ |
| P5.2 | Dashboard Widgets | — | — | ❌ |

**Service:** `CashService` (435 lines) — single service for all cash/bank transactions.
**Controller:** `CashController` (444 lines) — 10+ API endpoints.
**Architecture:** `CashService → JournalService::postEntry()` — reuses existing double-entry engine.

---

## Audit Log

| Component | File | Status |
|---|---|---|
| Migration | `033_create_audit_log_table.php` | ✅ |
| Logger | `AuditLogger.php` — static, auto-context (IP, request_id) | ✅ |
| Instrumentation | `JournalService::postEntry()` — every financial transaction | ✅ |
| Instrumentation | `AccountController` — COA create/update/delete/seed | ✅ |
| Instrumentation | `BankReconciliationService::complete()` | ✅ |
| Viewer | `AuditLogController` + view at `/he-thong/nhat-ky-hoat-dong` | ✅ |

---

## RBAC (Users, Roles, Permissions)

| Component | File | Status |
|---|---|---|
| Migration | `035_create_rbac_tables.php` — users, roles, role_permissions, user_roles | ✅ |
| Seed data | 9 roles, 81 permissions, admin user | ✅ |
| Auth | `AuthController` — login/logout/me, PHP sessions | ✅ |
| Auth guard | `index.php` — blocks unauthenticated access | ✅ |
| Permission check | `Helpers::requirePermission(module, action)` | ✅ |
| User CRUD | `UserController` + view at `/he-thong/nguoi-dung` | ✅ |
| Role CRUD | `RoleController` + view at `/he-thong/vai-tro` | ✅ |
| Permission matrix UI | Checkbox grid: 9 modules × 6 actions | ✅ |
| Wired controllers | `CashController` — receipts/payments guarded | Partial |

### Default roles
| Role | Modules | Write |
|---|---|---|
| Quản trị dữ liệu | 9/9 | Yes |
| Kế toán trưởng | 9/9 | Yes |
| Kế toán vốn bằng tiền | cash, bank, master_data, reconciliation, report | Yes |
| Kế toán mua hàng | inventory, master_data, report | Yes |
| Kế toán bán hàng | inventory, master_data, report | Yes |
| Kế toán kho | inventory, master_data, report | Yes |
| Kế toán thuế | master_data, report | Yes |
| Lãnh đạo | 7 modules | No |
| Kiểm toán | 8 modules (no system) | No |

---

## Helpers & Utilities

| Helper | Location | Methods |
|---|---|---|
| **Helpers** | `src/Accounting/Infrastructure/Helpers.php` | toVnWords, fmt, e, jsonOk, jsonError, isValidAccountCode, nextVoucherNo, paginate, isAuthenticated, hasPermission, requirePermission, currentUser, isAdmin |
| **DB** | `src/Accounting/Infrastructure/Database/DB.php` | select, fetch, fetchColumn, execute, insertGetId, transaction, sqlIn, sqlInCondition, tableExists |
| **AuditLogger** | `src/Accounting/Infrastructure/Database/AuditLogger.php` | log(action, resource, id, old, new, actor) |

---

## Period Engine (Law on Accounting 2015 — Article 12, 26.6)

| Service | UC | Tests |
|---|---|---|
| `PeriodService` | UC-001–004 | PeriodTest (18) |

**Features:**
- Period CRUD (create monthly/quarterly/annual periods)
- Period open/close lifecycle with status tracking
- Closing entries: Dr Revenue → Cr P&L, Dr P&L → Cr Expense, P&L → Retained Earnings
- Period guard in JournalService (rejects posting to closed periods)
- Re-open with audit trail and re-open counter
- `isPeriodOpen()` static check consumed by JournalService
- View at `/he-thong/quan-ly-ky`

**Migration:** `037_create_accounting_periods_table.php`

---

## Journal Engine

| Service | File | Tests |
|---|---|---|
| `JournalService` | `src/Accounting/Domain/Service/JournalService.php` | 12 |
| `ValuationService` | `src/Accounting/Domain/Service/ValuationService.php` | 4 |
| `TrialBalance` | `tests/TrialBalanceTest.php` | 9 |
| `PostingValidation` | `tests/PostingValidationTest.php` | 5 |
| **Total** | | **30** |

---

## Inventory Module — All 10 phases

| Phase | Service methods | Tests |
|---|---|---|
| P1: Valuation Engine | `calculateWeightedAverage()` | 4 |
| P2: Goods Receipt | `receiveGoods()` | 9 |
| P3: Goods Issue / COGS | `issueGoods()` | 6 |
| P4: Warehouse Transfer | `transferGoods()` | 11 |
| P5: In Transit (TK 151) | `recordInTransit()`, `receiveFromTransit()` | 10 |
| P6: Consignment (TK 157) | `consignGoods()`, `sellConsigned()`, `returnConsigned()` | 10 |
| P7: Physical Count | `adjustPhysicalCount()`, `createCountSession()` | 10 |
| P8: Impairment (TK 229) | `recordImpairment()`, `reverseImpairment()` | 6 |
| P9: Promotional | `issuePromotional()` | 4 |
| P10: Periodic System | `closePeriodicInventory()` | 7 |
| **Total** | | **77** |

**Service:** `InventoryService` (500+ lines)
**Inventory Account Mapping:** material/tool/other → 152, product → 155, merchandise → 156

---

## All Tests (290 total — ALL PASS)

| Test file | Tests | Module |
|---|---|---|
| `tests/ValuationServiceTest.php` | 4 | Inventory P1 |
| `tests/JournalServiceTest.php` | 12 | Journal Engine |
| `tests/TrialBalanceTest.php` | 9 | Journal Engine |
| `tests/PostingValidationTest.php` | 5 | Journal Engine |
| `tests/InventoryReceiptTest.php` | 9 | Inventory P2 |
| `tests/InventoryIssueTest.php` | 6 | Inventory P3 |
| `tests/InventoryTransferTest.php` | 11 | Inventory P4 |
| `tests/InventoryTransitTest.php` | 10 | Inventory P5 |
| `tests/InventoryConsignmentTest.php` | 10 | Inventory P6 |
| `tests/InventoryPhysicalCountTest.php` | 10 | Inventory P7 |
| `tests/InventoryImpairmentTest.php` | 6 | Inventory P8 |
| `tests/InventoryPromotionalTest.php` | 4 | Inventory P9 |
| `tests/InventoryPeriodicTest.php` | 7 | Inventory P10 |
| `tests/CashTest.php` | 11 | Cash & Bank |
| `tests/CashBankTest.php` | 14 | Cash & Bank |
| `tests/CashBookTest.php` | 9 | Cash & Bank |
| `tests/CashTransitTest.php` | 10 | Cash & Bank |
| `tests/PettyCashTest.php` | 15 | Cash & Bank |
| `tests/BankReconciliationTest.php` | 24 | Cash & Bank |
| `tests/CashFXTest.php` | 17 | Cash & Bank |
| `tests/CashReportTest.php` | 14 | Cash & Bank |
| `tests/COATest.php` | 47 | COA |
| `tests/HelpersTest.php` | 42 | Helpers |
| `tests/DBTest.php` | 18 | DB Helper |
| `tests/PeriodTest.php` | 18 | Period Engine |
| **Total** | **339** | **25 test files** |

---

## API Endpoints

### Cash & Bank (20+ endpoints)
| Method | Path | Purpose |
|---|---|---|
| GET/POST | `/api/cash/receipts` | List/create cash receipts |
| GET/POST | `/api/cash/payments` | List/create cash payments |
| GET | `/api/cash/accounts` | Account picker |
| GET | `/api/bank-transactions` | List bank transactions |
| POST | `/api/bank/deposit` | Cash → bank deposit |
| POST | `/api/bank/withdrawal` | Bank → cash withdrawal |
| POST | `/api/bank/receipt` | Customer pays to bank |
| POST | `/api/bank/payment` | Supplier paid from bank |
| POST | `/api/bank/interest` | Bank interest income |
| POST | `/api/bank/charge` | Bank service fee |
| GET/POST | `/api/cash/transit` | List/record cash in transit |
| POST | `/api/cash/transit/confirm` | Confirm bank credited |
| POST | `/api/cash/transit/reverse` | Reverse transit (cheque dishonour) |
| GET | `/api/cash-book` | Cash book (computed) |
| GET/POST | `/api/petty-cash/funds` | List/create petty cash funds |
| POST | `/api/petty-cash/disburse` | Disburse from petty cash |
| POST | `/api/petty-cash/replenish` | Replenish petty cash |
| POST | `/api/petty-cash/close` | Close petty cash fund |
| GET | `/api/petty-cash/{id}/transactions` | Petty cash transaction history |

### Bank Reconciliation (11 endpoints)
| Method | Path | Purpose |
|---|---|---|
| GET | `/api/bank-reconciliation/sessions` | List sessions |
| POST | `/api/bank-reconciliation/start` | Start new session |
| GET | `/api/bank-reconciliation/{id}/session` | Session detail |
| GET | `/api/bank-reconciliation/{id}/items` | All items (book + statement) |
| GET | `/api/bank-reconciliation/{id}/unmatched` | Unmatched items |
| POST | `/api/bank-reconciliation/{id}/statement-entry` | Add statement transaction |
| POST | `/api/bank-reconciliation/{id}/auto-match` | Auto-match by amount/ref/date |
| POST | `/api/bank-reconciliation/{id}/manual-match` | Manual match pair |
| POST | `/api/bank-reconciliation/{id}/adjust` | Adjusting entry (bank charges) |
| POST | `/api/bank-reconciliation/{id}/complete` | Complete reconciliation |
| GET | `/api/bank-reconciliation/bank-accounts` | Bank accounts (TK 112) |

### COA
| Method | Path | Purpose |
|---|---|---|
| GET | `/api/coa` | List all accounts |
| POST | `/api/coa/seed` | Seed Circular 99 standard COA |

### Journal
| Method | Path | Purpose |
|---|---|---|
| POST | `/api/journal` | Post journal entry |
| GET | `/api/trial-balance` | Get trial balance |

### Inventory
| Method | Path | Purpose |
|---|---|---|
| GET/POST/PUT/DELETE | `/api/items`, `/api/customers`, etc. | 16 master data CRUDs |
| POST | `/api/transfers` | Warehouse transfer |
| GET/POST | `/api/inventory-transit` | Goods in transit |
| POST | `/api/inventory-transit/receive` | Receive from transit |
| GET/POST | `/api/consignments` | Consignment management |
| POST | `/api/consignments/sell` | Sell consigned |
| POST | `/api/consignments/return` | Return consigned |
| GET/POST | `/api/physical-count/sessions` | Physical count |
| POST | `/api/physical-count/adjust` | Adjust count |
| GET/POST | `/api/impairments` | Impairment provision |
| POST | `/api/impairments/reverse` | Reverse impairment |
| POST | `/api/promotional/issue` | Issue promotional goods |
| POST | `/api/periodic/close` | Close periodic inventory |

### Audit Log
| Method | Path | Purpose |
|---|---|---|
| GET | `/api/audit-log` | Paginated, filterable audit log |
| GET | `/api/audit-log/{id}` | Single entry detail |

### Auth
| Method | Path | Purpose |
|---|---|---|
| POST | `/api/auth/login` | Login with username/password |
| POST | `/api/auth/logout` | Destroy session |
| GET | `/api/auth/me` | Current user info + permissions |

### User & Role Management
| Method | Path | Purpose |
|---|---|---|
| GET | `/api/users` | User list with roles |
| POST | `/api/users` | Create user |
| PUT | `/api/users/{id}` | Update user |
| DELETE | `/api/users/{id}` | Deactivate user |
| GET | `/api/roles` | List roles |
| POST | `/api/roles` | Create role |
| PUT | `/api/roles/{id}` | Update role |
| DELETE | `/api/roles/{id}` | Delete role (non-system) |
| GET | `/api/roles/{id}/permissions` | Get permission matrix |
| PUT | `/api/roles/{id}/permissions` | Update permission matrix |

### Frontend Views
| Path | Page |
|---|---|
| `/` | Dashboard |
| `/dang-nhap` | Login |
| `/danh-muc/*` | 16 master data CRUD pages |
| `/thu/quy-tien-mat` | Cash receipt (Phiếu thu) |
| `/chi/quy-tien-mat` | Cash payment (Phiếu chi) |
| `/thu/giao-bao-co` | Bank credit (Giấy báo Có) |
| `/chi/giao-bao-no` | Bank debit (Giấy báo Nợ) |
| `/thu/tien-dang-chuyen` | Cash in transit |
| `/thu/so-quy-tien-mat` | Cash book (Sổ quỹ) |
| `/thu/tam-ung` | Petty cash (Tạm ứng) |
| `/thu/doi-chieu-ngan-hang` | Bank reconciliation |
| `/kho/*` | Inventory views (receipt, issue, transfer, count, etc.) |
| `/he-thong/nhat-ky-hoat-dong` | Audit log |
| `/he-thong/quan-ly-ky` | Period management |
| `/he-thong/nguoi-dung` | User management |
| `/he-thong/vai-tro` | Role & permission management |

---

## Database Migrations (35 total)

| # | File | Purpose |
|---|---|---|
| 001-019 | — | Master data tables + COA seed |
| 020-021 | — | Account alterations, valuation method |
| 022-028 | — | Inventory module (cost layers, transit, consignment, count, impairment, periodic) |
| 029 | `add_is_control_to_accounts` | Control account flag |
| 030 | `create_cash_transit_table` | Cash in transit tracking |
| 031 | `create_petty_cash_tables` | Petty cash funds + transactions |
| 032 | `create_bank_reconciliation_tables` | Reconciliation sessions + items |
| 033 | `create_audit_log_table` | Audit trail |
| 034 | `create_voucher_sequences_table` | Document number sequences |
| 035 | `create_rbac_tables` | Users, roles, permissions |
| 036 | `create_fc_transactions_table` | FC transaction tracking |
| 037 | `create_accounting_periods_table` | Period management |

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
| `public/index.php` | Entry point + autoloader + session auth guard |
| `config/services.php` | DI container |
| `config/routes.php` | All routes (100+ endpoints) |
| `config/database.php` | DB credentials (dev/123456) |
| `src/.../Service/JournalService.php` | Double-entry engine |
| `src/.../Service/CashService.php` | Cash & bank operations (435 lines) |
| `src/.../Service/InventoryService.php` | All 10 inventory phases (500+ lines) |
| `src/.../Service/BankReconciliationService.php` | Reconciliation matching engine |
| `src/.../Service/PeriodService.php` | Period open/close/closing entries |
| `src/.../Infrastructure/Helpers.php` | Utility functions (auth, format, etc.) |
| `src/.../Infrastructure/Database/DB.php` | DB transaction/query helpers |
| `src/.../Infrastructure/Database/AuditLogger.php` | Audit trail logger |
| `database/migrate.php` | Migration runner |
| `database/migrations/*.php` | 37 migration files |
