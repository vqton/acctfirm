# Implementation Roadmap — Financial Statements Module

**Base Spec:** `docs/FS_USE_CASE_SPECIFICATION.md`
**Regulatory Basis:** Circular 99/2025/TT-BTC Article 17, Appendix IV
**Statements:** BC 01 (Balance Sheet), BC 02 (Income Statement), BC 03 (Cash Flow), BC 09 (Notes)

---

## Current State Assessment

| Capability | Status | Gap |
|---|---|---|
| COA (~150 accounts) | ✅ Seeded | — |
| Journal posting | ✅ JournalService | — |
| Trial balance | ✅ TrialBalanceTest (9) | — |
| Period close + closing entries | ✅ PeriodService | — |
| Account balances | ✅ Available per account | — |
| **Account-to-FS-line mapping** | ❌ **Not created** | Map each GL account → mã số |
| **FS formula engine** | ❌ **Not created** | 80+ line items with formulas |
| **BC 01 generation** | ❌ **Not created** | — |
| **BC 02 generation** | ❌ **Not created** | — |
| **BC 03 generation** | ❌ **Not created** | — |
| **BC 09 generation** | ❌ **Not created** | — |
| **FS cross-validation** | ❌ **Not created** | 3 key validation rules |
| **FS sign-off workflow** | ❌ **Not created** | Signatures, deadline tracking |
| **FS view/report** | ❌ **Not created** | — |

---

## Implementation Plan

### Phase 1: FS Foundation (3 days)

**Goal:** Build the core engine: fs_line_items table (account→mã số mapping), BC 01 formula engine, and BC 01 generation.

| Task | UC | Files | Effort |
|---|---|---|---|
| **Migration 038** — `fs_line_items` table | All | `database/migrations/038_create_fs_line_items_table.php` | 0.5d |
| **Seed** — Load all BC 01/02/03 mã số with GL account mappings | All | Seed script in migration | 0.5d |
| **Service** — `FsService::generateBC01()` | UC-001 | `src/.../Service/FsService.php` | 1d |
| **Controller** — `FsController` | All | `src/.../HTTP/FsController.php` | 0.5d |
| **View** — BC 01 display | UC-001 | `public/views/fs_bc01.php` | 0.5d |
| **Tests** — `FsTest` | All | `tests/FsTest.php` | 1d |

#### Schema — `fs_line_items`

```sql
CREATE TABLE fs_line_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    statement VARCHAR(10) NOT NULL,          -- 'BC01', 'BC02', 'BC03'
    ma_so VARCHAR(10) NOT NULL,              -- e.g. '111', '130', '200'
    parent_ma_so VARCHAR(10) DEFAULT NULL,   -- parent line for roll-up
    name_vi VARCHAR(255) NOT NULL,           -- Vietnamese name
    formula_type VARCHAR(20) DEFAULT 'account', -- 'account', 'sum', 'subtract', 'calculated', 'manual'
    formula_detail TEXT DEFAULT NULL,         -- TK list or mã số formula
    sign_convention VARCHAR(10) DEFAULT 'positive', -- 'positive', 'negative'
    display_order INT DEFAULT 0,
    is_control TINYINT(1) DEFAULT 0,         -- 1 for subtotal lines (100, 200, 300, 400)
    is_total TINYINT(1) DEFAULT 0,           -- 1 for grand total (280, 440)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_stmt_ma (statement, ma_so)
);
```

#### Seed data examples

```
BC01, 111, NULL, 'Tiền', 'account', '111,112,113', 'positive'
BC01, 112, NULL, 'Các khoản tương đương tiền', 'account', '1281,1288', 'positive'
BC01, 110, NULL, 'Tiền và tương đương tiền', 'sum', '111,112', 'positive', control=1
...
BC01, 280, NULL, 'Tổng cộng tài sản', 'calculated', '100+200', 'positive', total=1
BC01, 440, NULL, 'Tổng cộng nguồn vốn', 'calculated', '300+400', 'positive', total=1
BC02, 01, NULL, 'Doanh thu bán hàng', 'account', '511', 'positive'
BC02, 10, NULL, 'Doanh thu thuần', 'calculated', '01-02', 'positive', control=1
BC02, 20, NULL, 'Lợi nhuận gộp', 'calculated', '10-11', 'positive', control=1
BC02, 50, NULL, 'Tổng LNKT trước thuế', 'calculated', '30+40', 'positive', control=1
BC02, 60, NULL, 'Lợi nhuận sau thuế', 'calculated', '50-(51+52)', 'positive', control=1, total=1
```

#### Formula engine logic

```php
switch ($item->formula_type):
    case 'account':
        // SUM balances of listed GL accounts
        $value = sumBalances([TK 111, TK 112, TK 113]);
    case 'sum':
        // SUM values of child mã số
        $value = $values['111'] + $values['112'];
    case 'calculated':
        // Evaluate expression: '50-(51+52)'
        $value = evaluate($item->formula_detail, $values);
    case 'manual':
        // Manual override entry
        $value = manualEntry;
```

#### Three signature types
| Mã số range | Classification |
|---|---|
| 100–280 | Assets (Tài sản) |
| 300–400 | Liabilities (Nợ phải trả) |
| 400–440 | Equity (Vốn chủ sở hữu) |
| 01–60 | Income Statement items |
| 01–70 (BC 03) | Cash Flow items |

---

### Phase 2: BC 02 Income Statement (2 days)

**Goal:** Generate BC 02 with full formula chain from revenue to net profit. Add EPS calculation for joint stock companies.

| Task | Files | Effort |
|---|---|---|
| `FsService::generateBC02()` | `FsService.php` | 0.5d |
| BC 02 view | `fs_bc02.php` | 0.5d |
| EPS calculation (basic + diluted) | `FsService.php` | 0.5d |
| Tests | `FsTest.php` | 0.5d |

#### BC 02 formula chain

```
01 (Revenue) → account: TK 511
02 (Revenue deductions) → account: TK 521
10 (Net revenue) = 01 - 02
11 (COGS) → account: TK 632
20 (Gross profit) = 10 - 11
21 (Investment property P&L) → account: TK 511/632 detail
22 (Finance income) → account: TK 515
23 (Finance costs) → account: TK 635
24 (of which: borrowing costs) → account: TK 635 detail
25 (Selling expenses) → account: TK 641
26 (Admin expenses) → account: TK 642
30 (Operating profit) = 20 + 21 + 22 - 23 - 25 - 26
31 (Other income) → account: TK 711
32 (Other expenses) → account: TK 811
40 (Other profit) = 31 - 32
50 (Pre-tax profit) = 30 + 40
51 (Current CIT) → account: TK 8211
52 (Deferred CIT) → account: TK 8212
60 (Net profit) = 50 - (51 + 52)
70 (Basic EPS) = (60 - preferred dividends) / weighted avg shares
71 (Diluted EPS) = (60 - preferred dividends + dilution adjustments) / diluted shares
```

---

### Phase 3: BC 03 Cash Flow Statement (2 days)

**Goal:** Generate BC 03 using both direct and indirect methods. Cross-validate closing cash (70) with BC 01 cash (111).

| Task | Files | Effort |
|---|---|---|
| `FsService::generateBC03()` with direct method | `FsService.php` | 0.5d |
| `FsService::generateBC03()` with indirect method | `FsService.php` | 0.5d |
| Cash flow classification logic (operating/investing/financing) | `FsService.php` | 0.5d |
| BC 03 view | `fs_bc03.php` | 0.5d |

#### Cross-validation: BC 03 70 = BC 01 111

```php
$closingCashBC03 = $fsService->getValue('BC03', '70');
$closingCashBC01 = $fsService->getValue('BC01', '111');
if (abs($closingCashBC03 - $closingCashBC01) > 1) {
    throw new \RuntimeException('Cash mismatch: BC03 70 != BC01 111');
}
```

---

### Phase 4: BC 09 Notes to FS (3 days)

**Goal:** Generate BC 09 with 29 accounting policy categories + supplementary schedules for all BC 01/02/03 line items.

| Task | Files | Effort |
|---|---|---|
| `FsService::generateBC09()` | `FsService.php` | 1d |
| Movement schedule engine (FA, equity, provisions) | `FsService.php` | 0.5d |
| Accounting policy template system | `FsService.php` | 1d |
| BC 09 view | `fs_bc09.php` | 0.5d |

---

### Phase 5: FS Validation + Sign-off (2 days)

**Goal:** Cross-statement validation, signature workflow, submission tracking.

| Task | Files | Effort |
|---|---|---|
| `FsService::validateCrossStatement()` | `FsService.php` | 0.5d |
| FS sign-off tracking table + workflow | Migration 039, `FsService.php` | 0.5d |
| FS submission deadline dashboard | View | 0.5d |
| FS export (Excel/PDF) | `FsController.php` | 0.5d |

---

## Effort Summary

| Phase | Module | Days | Cumulative |
|---|---|---|---|
| P1 | FS Foundation (mapping + BC 01) | 3 | 3 |
| P2 | BC 02 Income Statement | 2 | 5 |
| P3 | BC 03 Cash Flow Statement | 2 | 7 |
| P4 | BC 09 Notes to FS | 3 | 10 |
| P5 | Validation + Sign-off | 2 | 12 |
| **Total** | | **12 days** | |

---

## Dependency Graph

```
Phase 1: Foundation
  Migration: fs_line_items table
  Seed: all BC 01/02/03 mã số + GL mappings
  FsService::generateBC01()
      │
      ├── Phase 2: BC 02 (needs same FsService pattern)
      │       │
      │       ├── Phase 3: BC 03 (needs BC 01 for cash validation)
      │       │       │
      │       │       ├── Phase 4: BC 09 (needs BC 01 + BC 02 + BC 03)
      │       │       │
      │       │       └── Phase 5: Validation (needs all 4 statements)
      │       │
      │       └── Phase 5: uses BC 02 in validation
      │
      └── Phase 5: uses BC 01 in validation
```

---

## Go-Live Minimum

**BC 01 + BC 02** (Phases 1-2, ~5 days) is the minimum viable financial reporting package. With these two statements:
- Balance Sheet proves Assets = Liabilities + Equity
- Income Statement proves profit/loss
- Trial balance feeds both

BC 03 and BC 09 add depth but don't block submission for basic compliance.

**Recommendation:** Implement P1 first (3 days), then P2 (2 days), then assess whether BC 03/09 are needed immediately or can wait.
