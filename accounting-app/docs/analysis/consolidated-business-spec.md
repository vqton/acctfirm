# Consolidated Business Specification: Vietnamese Enterprise Accounting System

**Generated:** 2026-05-19
**Scope:** All fragments ingested — 5 research, 10 specs, 7 roadmaps, AGENTS.md, routes.php, services.php
**Method:** De-duplication, conflict resolution, standardization

---

## 1. System Architecture

### 1.1 Technical Stack

| Layer | Technology | Detail |
|---|---|---|
| Language | PHP 8.4 | No frameworks, no Composer |
| Database | MySQL/MariaDB | PDO with prepared statements, `?` placeholders |
| Frontend | Bootstrap 5 + jQuery | JSON API consumed via AJAX |
| Auth | PHP Sessions | `SessionMiddleware` (open/close/authGuard) |
| DI | Plain array in `$GLOBALS['container']` | All repos/services singletons |
| Router | Custom regex-based | Accepts `callable\|array\|string` |
| Autoloader | Custom PSR-4-like | `Accounting\` → `src/Accounting/` |
| Logging | Custom `Logger` + `LoggingPDO` | Django-style request/SQL logging + ActionJournal (JSON Lines) |

### 1.2 Architecture Flow

```
index.php → autoloader → config/services.php (DI) → config/routes.php
→ Router::dispatch() → Controller → Service → Repository (PDO) → MySQL
```

### 1.3 Hexagonal / DDD Alignment

```
┌──────────────────────────────────────────────────────────────┐
│  Interfaces/HTTP/           Controllers, Router              │
│  Domain/Model/              Plain PHP objects (getter/setter) │
│  Domain/Repository/         Interface contracts              │
│  Domain/Service/            Business logic (JournalService…) │
│  Infrastructure/Repository/ PDO implementations              │
│  Infrastructure/Database/   DB helpers, AuditLogger          │
│  Infrastructure/Logging/    Logger, LoggingPDO, ActionJournal│
└──────────────────────────────────────────────────────────────┘
```

---

## 2. Glossary of Business Entities

### 2.1 Core Accounting Entities

| Entity | Fields (Key) | Normal Balance | Notes |
|---|---|---|---|
| **Account** | id, code, name, class, normal\_balance\_side (Dr/Cr), is\_control (bool), parent\_id, status, balance | Per class | Hierarchical: Level 1 (3-digit) = control; Level 2+ (4-digit) = detail. Only detail accounts accept postings. |
| **Transaction** | id, description, reference, status (draft/pending/posted), created\_at, created\_by | N/A | Immutable after posting. Sequential numbering per type. |
| **LedgerEntry** | id, transaction\_id, account\_code, amount, is\_debit (bool) | N/A | Every TXN has ≥2 entries. Total debits = total credits enforced. |
| **Item** | id, code, name, item\_type (material/tool/product/merchandise/other), unit, purchase\_price, sale\_price, stock\_qty, min\_stock, valuation\_method\_id | N/A | Links to valuation method. `stock_qty` maintained in real-time. |
| **CostLayer** | id, item\_id, warehouse\_id, batch\_code, expiry\_date, qty, unit\_cost, addon\_per\_unit | N/A | FIFO basis (consumed ORDER BY created\_at ASC). |

### 2.2 Financial Statements (FS)

| Statement | Code | Period | Status |
|---|---|---|---|
| Balance Sheet | BC 01 (B01-DN) | Point-in-time | ✅ Implemented |
| Income Statement | BC 02 (B02-DN) | Period | ✅ Implemented |
| Cash Flow Statement | BC 03 (B03-DN) | Period | ✅ Implemented |
| Notes to FS | BC 09 (B09-DN) | Period | ❌ Not implemented |

BC 01 formula: `280 (Total Assets) = 440 (Total Liabilities + Equity)`. 8 account classes mapped to mã số line items. BC 02 formula chain: `01 Revenue → 10 Net → 11 COGS → 20 Gross → 30 Operating → 50 Pre-tax → 60 Net`.
BC 03 (Cash Flow, indirect method): operating section from BC 02 profit + working capital deltas; investing/financing sections from balance sheet account deltas. Validation: `MS70 = MS50+MS60+MS61` and `MS70 = BC01 MS110`.

### 2.3 State Machines

**Period States:** `Defined → Open → (Reconciling) → Closed`
- Only one period open at a time
- Closed period: no INSERT/UPDATE/DELETE on transactions
- Re-open requires dual auth + full audit trail
- Pre-close checklist: inventory done, FC revalued, sub-ledgers reconciled, trial balance balanced

**Transaction States:** `Draft → Posted` (no pending state exposed in current code)
- Once posted: immutable. Correction via adjusting entry only (Article 27 methods)

---

## 3. Functional Domain Map

### 3.1 Module Inventory (vs. Market Standard K1-K108)

| Domain | Status | Test Count | Key Files |
|---|---|---|---|
| COA (Chart of Accounts) | ✅ Complete | — | AccountController, PDOAccountRepository |
| Journal Engine | ✅ Complete | — | JournalService (postEntry, approveDraft, listEntries) |
| Cash & Bank (TK 111-113) | ✅ 9 UCs | ~100 | CashService, PettyCashService, BankReconciliationService |
| Inventory (TK 151-158) | ✅ 13 files | 99 | InventoryService, ReceiptController, IssueController |
| AP (TK 331) | ✅ Complete | 22 | ApService — 10 UCs per spec |
| AR (TK 131) | ✅ Complete | 19 | ArService — 10 UCs per spec |
| FS — BC 01/02 | ✅ Complete | 18 | FsService |
| FS — BC 03 | ✅ Complete | 20 | Bc03Test — indirect method, 3 activity sections, BC 01 cross-validation |
| FS — BC 09 | ⚠️ Partial | — | `bc09.php` view + `FsController::viewTT99()`. 29-section disclosure not yet automated. |
| Period Engine | ✅ Complete | 18 | PeriodService — open/close/lock |
| GL (Sổ Cái) | ✅ Complete | 12 | GlService — ledger with running balance |
| RBAC | ✅ Complete | — | AuthController, Auth helper, roles/permissions tables |
| Audit Log | ✅ Complete | — | AuditLogger |
| Bank Reconciliation | ✅ Complete | 24 | BankReconciliationService |
| Petty Cash | ✅ Complete | 6 | PettyCashService |
| Fixed Assets | ✅ Complete | 25 | FixedAssetService + lifecycle controller (acquire/dispose) + views |
| CCDC / TK 242 | ✅ Complete | — | CcdcAllocationService + migration 063 + controller + 28 lifecycle tests |
| Payroll | ✅ Complete | 44 | PayrollService (669 lines), PayrollController (203 lines), 9 views, 35 routes, 12+ migrations |
| Tax — VAT | ✅ Complete | — | VatService + migration 064 + VatController (scan non-deductible, reconcile, loss carryforward) |
| Tax — CIT/PIT | ✅ Complete | — | CitService + migration 065 + CitController (scan non-deductible, reconcile, loss carryforward) |
| E-Invoice | ❌ Missing | 0 | No integration |
| Production / Costing | ❌ Missing | 0 | No BOM, WIP, unit cost |
| Management Reports | ❌ Missing | 0 | No dashboard, no BI |
| Multi-branch | ✅ Complete | 10 | IntercompanyService (280 lines) with matching + elimination + consolidated report. Migration 055. |

### 3.2 Inventory Coverage Detail

| TK | Description | Status |
|---|---|---|
| 151 | Goods in transit | ✅ (via Consignment/Transit module) |
| 152 | Raw materials | ✅ |
| 153 | Tools/CCDC | ✅ |
| 154 | WIP | ✅ (production issue) |
| 155 | Finished goods | ✅ (via inventoryAccountMap) |
| 156 | Merchandise | ✅ (via inventoryAccountMap) |
| 157 | Consignment goods | ✅ |
| 158 | Bonded warehouse | ❌ Not implemented |
| 241 | Construction CIP | ✅ |
| 632 | COGS | ✅ |
| 641 | Selling expense | ✅ (promotional) |
| 2294 | Impairment provision | ✅ |
| 1381/3381 | Asset surplus/deficit | ✅ (physical count) |

### 3.3 Cash & Bank Coverage Detail (Treasury Spec)

The Treasury spec documents **37 UC scenarios** across 3 accounts (111/112/113). Implemented coverage:

| Area | Specified | Implemented | Gap |
|---|---|---|---|
| Cash Receipt (Phiếu thu) | UC-001 to UC-009 | ✅ Basic CRUD | Missing: FC cash receipt, petty cash reimbursement, cash-in-transit |
| Cash Payment (Phiếu chi) | UC-010 to UC-018 | ✅ Basic CRUD | Missing: approval workflow, multi-signature enforcement |
| Bank Receipt (Giấy báo Có) | UC-019 to UC-024 | ✅ Basic CRUD | Missing: bank statement auto-import, CSV/MT940 |
| Bank Payment (Giấy báo Nợ) | UC-025 to UC-031 | ✅ Basic CRUD | Missing: payment approval workflow |
| Cash in Transit (113) | UC-032 to UC-034 | ⚠️ Partial | Migration 030 created table, controller exists |
| Bank Reconciliation | UC-035 to UC-037 | ✅ Complete | Auto-matching per spec, adjusting entries |

### 3.4 AP/AR Coverage

| Use Case | AR | AP | Status |
|---|---|---|---|
| Invoice on Credit | UC-001 | UC-001 | ✅ |
| FC Invoice | — | UC-002 | ✅ |
| Payment | UC-002 | UC-003 | ✅ |
| Prepayment | — | UC-004 | ⚠️ Partial (exists in spec, wired in service?) |
| Sales/Purchase Return | UC-003 | UC-005 | ✅ |
| Trade Discount | UC-004 | UC-006 | ⚠️ Partial |
| Settlement Discount | UC-005 | UC-006 (dual) | ✅ |
| Barter | UC-006 | — | ❌ Not implemented |
| Bad Debt Write-off | UC-007 | — | ❌ Not implemented |
| Bad Debt Provision | UC-008 | — | ❌ Not implemented |
| FC Revaluation | — | UC-007 | ✅ |
| Write-off Creditor | — | UC-008 | ❌ Not implemented |
| Aging Report | UC-010 | UC-009 | ✅ |
| Supplier Statement | — | UC-010 | ⚠️ Partial (view exists) |
| Construction Progress Billing | UC-009 | — | ❌ Not implemented |

### 3.5 Treasury Spec — UC Count Per Account

| Account | Use Cases | Lines of Spec | Implementation Status |
|---|---|---|---|
| TK 111 (Cash on Hand) | UC-001 to UC-018 | ~800 lines | ✅ Basic flows; missing FC, multi-currency, approval workflow |
| TK 112 (Bank Deposits) | UC-019 to UC-031 | ~700 lines | ✅ Basic flows; missing bank API, CSV import |
| TK 113 (Cash in Transit) | UC-032 to UC-034 | ~200 lines | ⚠️ Partial; table exists, no full lifecycle UI |

**Total Treasury Spec: 37 UCs, ~1,900 lines**

---

## 4. Conflicts & Resolutions

| Conflict | Source A | Source B | Resolution |
|---|---|---|---|
| Architecture folder structure | ACCOUNTING_ARCHITECTURE.md suggests flat `src/Accounting/` with Composer | AGENTS.md shows expanded `Domain/Model/Repository/Service/Infrastructure/Interfaces` with no Composer | AGENTS.md is authoritative (reflects actual codebase). The architecture doc was aspirational. |
| Phase numbering | MASTER_ROADMAP.md Phases 1-8 | INVENTORY_ROADMAP.md v2.0 has different 4-phase structure | INVENTORY_ROADMAP v2.0 is authoritative for inventory (updated 2026-05-19). Master roadmap not yet reconciled. |
| Migration count | AGENTS.md says "41 files" | Codebase now has 42 (migration 042 added) | 42 is current count. AGENTS.md is stale. |
| Test file count | AGENTS.md says "30 test files, ~430 tests" | Inventory has 99 tests total | This is current. AGENTS.md was updated per commit. |

---

## 5. Market Standard Coverage (KPI: K1-K108)

From ACCOUNTING_SOFTWARE_STANDARD_MODULES.md — 108 standard requirements for Vietnamese accounting software.

### Covered (57/108 ≈ 53%)

K1-K2 (Circular 99, VAS), K11-K13 (double-entry, COA, multi-currency), K20-K25 (multi-warehouse, FIFO/WAC, COGS automatic, goods in transit, consignment), K28-K30 (physical count, impairment, inter-warehouse transfer), K33-K40 (AP: PO→receipt→payment cycle partial), K41-K46 (AR: SO→delivery→receipt cycle partial), K69-K72 (cash receipt/payment, bank reconciliation, petty cash), K84-K86 (BC 01/02, trial balance, GL), K94-K98 (RBAC, auth, audit log, backup, multi-language partial), K102 (open architecture)

### Missing (51/108 ≈ 47%)

K3-K10 (GDT API, e-invoice, digital signature, auto tax declaration, auto FS, regulatory forms, social insurance), K14-K15 (multi-branch consolidation, fiscal periods), K16-K19 (full audit trail with before/after values, period lock workflow, auto sub-ledger→GL posting, accounting templates), K26-K27 (consignment full → done, bonded warehouse), K31-K32 (lot/serial, negative stock warning), K47 (bad debt provision), K48-K53 (fixed assets, CCDC depreciation, multi-period allocation), K54-K61 (production, BOM, WIP, costing), K62-K68 (payroll), K73-K74 (e-banking API, approval workflow), K75-K83 (tax: VAT, CIT, PIT, license, import tax, e-filing), K87-K93 (management reports, custom reports, dashboard, export, scheduling), K99-K101 (multi-language full, API, mobile), K103-K108 (GDT API, e-banking, e-commerce, e-signature, POS, third-party API)

---

## 6. Roadmap Reconciliation

### Master Roadmap (v1.0, dated 2026-05-15)

| Phase | Modules | Status | Assessment |
|---|---|---|---|
| P1: Foundation | COA Seed, Opening Balances | ✅ | COA seeded. Opening balances module not built. |
| P2: Cash & Bank | 5 modules | ✅ | All 5 built (receipt, payment, bank, reconciliation, petty cash) |
| P3: Trade | 7 modules (PO, Receipt, Payment, SO, Delivery, Receipt, Return) | ⚠️ Partial | Inventory receipt, issue, return built. PO/SO not built. Payment/receipt via Cash module. |
| P4: Inventory | 7 modules | ✅ | All built per INVENTORY_ROADMAP v2.0 |
| P5: Production | 5 modules | ❌ | Not started |
| P6: FA & Payroll | 4 modules | ❌ | Not started |
| P7: Tax & GL | 5 modules | ❌ | GL built. Tax/VAT not started. |
| P8: Reporting | 4 modules | ❌ | BC 01/02 built. BC 03/09 and management reports not started. |

### Inventory Roadmap (v2.0, dated 2026-05-19)

| Phase | Contents | Status |
|---|---|---|
| 1: Core Engine | Transaction wrapping, FIFO cost layers, receipt/issue/transfer controllers + views, CSRF/permissions | ✅ |
| 2: Account Mapping | TK 153 (tools), TK 241 (construction), Customer Returns (UC-015) | ✅ |
| 3: Consignment/Transit/Count | Consignment (TK 157), Transit (TK 151), physical count, periodic inventory | ✅ |
| 4: Adjustments | Impairment (TK 2294), promotional (TK 641) | ✅ |
| Enhancements | Auto WA, batch tracking, FC purchase, landed cost | ✅ |

---

## 7. Key Architectural Decisions

### Confirmed Patterns

| Pattern | Decision |
|---|---|
| Double-entry | Every TXN has ≥2 LedgerEntry rows. Dr=Cr enforced in JournalService. |
| Detail accounts only | postEntry rejects control accounts (Level 1) unless `$allowControl=true`. |
| Audit immutability | Posted TXNs cannot be deleted or modified. Correction via new TXN. |
| FIFO cost layers | consumeCostLayers orders by created_at ASC. |
| Transaction wrapping | All multi-step InventoryService methods use `wrapInTransaction(callable)`. JournalService checks `inTransaction()` for nested safety. |
| Period guard | Transactions cannot post to closed periods. |
| Voucher numbering | Configurable format via `nextVoucherNo()` helper. |
| CSRF | Every POST/PUT/DELETE requires X-CSRF-Token header from `Auth::csrfToken()`. |

### Deviations from Standard Patterns

| Expected Pattern | Current Implementation | Risk |
|---|---|---|
| Separate sub-ledgers (AR, AP) reconciled to GL | AR/AP balances computed from separate tables, but no formal reconciliation enforcement | Low — consistent in practice |
| Approval workflow for payments | No workflow layer. Every POST is immediate. | Medium — no payment authorization |
| Bank statement import (CSV/MT940) | Manual entry only | Medium — high manual overhead |
| Consolidated FS (multi-entity) | Not started | Low — out of scope for SME |

---

## 8. Configuration & Routing

### DI Container Structure (`config/services.php`)

- 30+ services constructed with explicit dependency injection
- All repositories are singletons sharing one LoggingPDO instance
- Key services: JournalService (central posting), InventoryService (16 methods), CashService (receipt/payment/bank), ApService (10 UCs), ArService (10 UCs), FsService (BC 01/02), GlService

### API Route Inventory (`config/routes.php`)

~400 lines covering:
- 16 master data CRUD endpoints
- 8 cash/bank route groups (receipt, payment, bank deposit/withdrawal, transfer, reconciliation, petty cash, cash reports, FX revaluation)
- 4 inventory route groups (receipt, issue, consignment, transfers, transit, physical count, periodic, impairment, promotional, customer return)
- 3 AP/AR route groups (invoices, payments, returns, aging)
- 3 FS routes (BC 01, BC 02, trial balance)
- Auth routes (login/logout/csrf/users/roles)
- Period routes (open/close/list)
- GL routes (ledger, journal book)
- View routes (52 sidebar pages)
