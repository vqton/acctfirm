# Implementation Roadmap — Law on Accounting 2015 Provisions

**Scope:** Features mandated by Law 88/2015/QH13 that Circular 99/2025/TT-BTC does NOT cover.
**Base Spec:** `docs/LAW_ON_ACCOUNTING_2015_USE_CASE_SPECIFICATION.md`
**Regulatory Reference:** Chapter I (General), Chapter II §4–6 (Inventory, Retention, Transitional), Chapter III (Organization), Chapter V (Inspection)

---

## Current State Assessment

| Domain | UC | Status in Codebase | Gap |
|---|---|---|---|
| **Period Management** | UC-001–004 | ✅ PeriodService, period guard, closing entries, view | Done (migration 037, 18 tests) |
| **Personnel & Organization** | UC-005–006 | ⚠️ Users/RBAC exist (migration 035) | Missing: prohibited person validation, role conflict checks, chief accountant enforcement |
| **Internal Control** | UC-007–008 | ⚠️ RBAC permissions exist (migration 035) | Missing: cash disbursement dual-signature enforcement, segregation enforcement |
| **Physical Inventory** | UC-009 | ⚠️ Count sessions + adjustment exist (inventory P7) | Missing: connection to period close enforcement |
| **Document Retention** | UC-010–011 | ❌ No archiving, no retention tracking | Full build needed |
| **Transitional Events** | UC-012–017 | ❌ No restructuring workflows | Full build needed (low freq — manual feasible) |
| **Regulatory Inspection** | UC-018 | ❌ No inspection support | Manual feasible |
| **FS Disclosure** | UC-019 | ❌ No disclosure workflow | Manual feasible |

---

## Phased Implementation Plan

### Phase 1: Period Engine (Week 1 — CRITICAL PATH)

**Business value:** Without period management, system can't close a month. No go-live possible.

| Task | UC | Files | Days |
|---|---|---|---|
| **Migration** — `accounting_periods` table | UC-001 | `database/migrations/037_create_accounting_periods_table.php` | 0.5 |
| **Service** — `PeriodService` | UC-001–004 | `src/.../Service/PeriodService.php` | 1 |
| **API** — CRUD + open/close/re-open | UC-001–004 | `PeriodController.php` | 0.5 |
| **Period guard** in `index.php` — reject posting to closed period | UC-003 | `public/index.php` guard middleware | 0.5 |
| **Pre-close validation** — checklist enforcement | UC-003 | `PeriodService::canClose()` | 0.5 |
| **Closing entries engine** — Dr revenue → Cr P&L, Dr P&L → Cr expense, P&L → retained earnings | UC-003 | `PeriodService::executeClosingEntries()` | 1 |
| **View** — Period management dashboard | UC-001–004 | `public/views/periods.php` | 0.5 |
| **Tests** | All | `tests/PeriodTest.php` | 1 |
| **Total** | | **~5.5 days** | |

#### Schema — `accounting_periods`

```sql
CREATE TABLE accounting_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_type ENUM('month','quarter','year') NOT NULL,
    period_code VARCHAR(10) NOT NULL,          -- e.g. '2026-05', '2026-Q2', '2026'
    name VARCHAR(100) NOT NULL,                 -- e.g. 'Tháng 5/2026'
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'open',          -- open | reconciling | closed
    is_first TINYINT(1) DEFAULT 0,
    is_last TINYINT(1) DEFAULT 0,
    opened_by VARCHAR(50) DEFAULT NULL,
    opened_at TIMESTAMP NULL DEFAULT NULL,
    closed_by VARCHAR(50) DEFAULT NULL,
    closed_at TIMESTAMP NULL DEFAULT NULL,
    re_open_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_period_code (period_type, period_code)
);
```

#### Close validation checklist (pre-close verification)

```
[ ] All sub-ledgers reconciled to GL
[ ] Physical inventory completed (UC-009)
[ ] FC items revalued (UC-008 Circular 99)
[ ] Trial balance: Dr = Cr
[ ] No unposted transactions in the period
[ ] Bank reconciliation completed
[ ] All adjustments recorded
```

#### Closing entries logic

```
1. Dr Revenue/Income accounts (5xxx, 7xxx) → Cr P&L (911)
2. Dr P&L (911) → Cr Expense accounts (6xxx, 8xxx)
3. Net profit: Dr 911 → Cr 421 (retained earnings)
   Net loss:  Dr 421 → Cr 911 (reverse)
```

---

### Phase 2: Physical Inventory → Period Link (Week 2 — 2 days)

**Business value:** Legal mandate — inventory must complete before close (Article 40.3).

| Task | Files | Days |
|---|---|---|
| Add `period_id` FK to count sessions table | Migration 038 | 0.5 |
| Modify `PeriodService::canClose()` to check inventory completion | `PeriodService.php` | 0.5 |
| Modify physical count view to show period linkage | `physical_count.php` | 0.5 |
| Tests | `tests/PeriodTest.php` | 0.5 |

---

### Phase 3: Personnel Validation (Week 2 — 2 days)

**Business value:** Legal compliance — preventing prohibited persons and role conflicts (Articles 52–54).

| Task | Files | Days |
|---|---|---|
| Add validation to UserController: prohibited family relationships check | `UserController.php` | 0.5 |
| Add validation: same person as accountant AND cashier blocked | `UserController.php`, role assignment | 0.5 |
| Add chief accountant qualification fields and validation | Migration 039, `UserController.php` | 0.5 |
| Tests | | 0.5 |

---

### Phase 4: Internal Control Enforcement (Week 2 — 1 day)

**Business value:** Article 39 mandate — segregation of duties, cash disbursement dual-signature.

| Task | Files | Days |
|---|---|---|
| Enforce cash disbursement dual signature in CashController | `CashController.php` | 0.5 |
| Persistent session check blocker dashboard | `layout.php` | 0.5 |

---

### Phase 5: Document Retention (Week 3 — 2 days)

**Business value:** Legal compliance — retention tiers (5/10 years/permanent) and 12-month archival rule.

| Task | Files | Days |
|---|---|---|
| Migration — `document_archive` table | Migration 040 | 0.5 |
| Service — `RetentionService` | NEW | 0.5 |
| API — archive/report/retention-check | Controller | 0.5 |
| View — archive dashboard | View | 0.5 |

---

### Phase 6: Transitional Events — Manual Procedures Only (Week 3 — 0.5 day)

**Business value:** Low frequency events (merger, dissolution, etc.). Procedure documentation sufficient.

| Task | Days |
|---|---|
| Document step-by-step procedure for each of 6 event types | 0.5 |
| No code changes — procedures reference existing close + inventory features | |

---

## Effort Summary

| Phase | Days | Cumulative |
|---|---|---|
| **P1: Period Engine** | 5.5 | 5.5 |
| **P2: Inventory → Period link** | 2 | 7.5 |
| **P3: Personnel validation** | 2 | 9.5 |
| **P4: Internal control enforce** | 1 | 10.5 |
| **P5: Document retention** | 2 | 12.5 |
| **P6: Transitional procedures** | 0.5 | 13 |
| **Total** | **13 days** | **~2.5 weeks** |

---

## Dependency Graph

```
Phase 1: Period Engine ──── no deps, must go first
    │
    ├── Phase 2: Inventory → Period link (depends on P1 canClose())
    │
    ├── Phase 3: Personnel validation (depends on RBAC from previous impl)
    │   │
    │   └── Phase 4: Internal control (depends on P3 role assignments)
    │
    ├── Phase 5: Document retention (depends on P1 period close event)
    │
    └── Phase 6: Transitional procedures (depends on P1 + P2)
```

---

## Go-Live Minimum

Period Engine (P1) is the critical path. Without it:
- No month-end close
- No FS preparation
- No period integrity
- System cannot produce auditable financial statements

**All other phases enhance compliance but don't block go-live.**

Recommend implementing P1 first (~5.5 days), then remaining phases in order of compliance risk.
