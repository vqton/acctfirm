# Spec: BC03 — Cash Flow Statement Enhancements

## Objective

Enhance BC03 (Báo cáo Lưu chuyển Tiền tệ, Mẫu B03-DN) to production-ready covering 3 gaps:

1. **Direct method** (phương pháp trực tiếp) — classify cash receipts/payments from 111/112 journals into B03-DN direct line items (cash from customers, paid to suppliers, etc.)
2. **CSV export** — download BC03 as CSV file, following existing `ReportExportService::exportCsv()` pattern
3. **Prior-period comparative data** — fix `getPriorPeriodValues()` to display "Năm trước" column reliably for non-year periods

**User:** Kế toán trưởng, kiểm toán viên, cơ quan thuế
**Success criteria:** BC03 renders correctly with both direct & indirect method; CSV exports open in Excel; prior-year column shows correct values

## Tech Stack

- PHP 8.4, no framework, no Composer (per §4.2)
- MySQL 8+ / MariaDB 10.6+
- Bootstrap 5 + jQuery 3.x for views
- `ReportExportService` for CSV (existing)
- PDO prepared statements

## Commands

```
# Test BC03 only
php tests/Bc03Test.php

# Run all FS tests
php tests/FsTest.php

# Run all tests to check no regression
for f in tests/*.php; do php "$f"; done

# PHP syntax check
php -l src/Accounting/Domain/Service/FsService.php
```

## Project Structure (affected files)

```
src/Accounting/Domain/Service/
  FsService.php              → add generateBC03Direct() + helpers, fix getPriorPeriodValues()

src/Accounting/Interfaces/HTTP/Financial/
  FsController.php           → add bc03ExportCsv() + bc03Direct()

src/Accounting/Infrastructure/
  JsonResponse.php           → unchanged (already has ok/error)

config/
  routes.php                 → add 3 new routes: bc03-direct API, bc03-csv export, bc03-direct view

public/views/
  fs_bc03.php                → update to support direct method toggle + export button

tests/
  Bc03Test.php               → add direct method tests, CSV export test, prior-period test
  FsTest.php                 → unchanged (BC01/BC02/BC03 zero-balance + cross-check)
```

## Code Style

- Follow existing `FsService` conventions: private methods prefixed with `_` or descriptive names, camelCase, PDO `?` placeholders
- Direct method formula types go into `formula_type` enum in `fs_line_items` (new types: `direct_receipt`, `direct_payment`)
- No new DB tables — line items seeded via new migration (080)
- CSV export follows `ReportExportController::exportCsvLedger()` pattern exactly

**Example snippet (matching existing style):**
```php
public function generateBC03Direct(?string $periodCode = null): array
{
    $items = $this->getLineItems('BC03_DIRECT');
    // Classify cash movements from 111/112 ledger entries
    $stmt = $this->pdo->prepare(
        "SELECT ... FROM ledger_entries le
         JOIN transactions t ON t.id = le.transaction_id
         JOIN accounts a ON a.id = le.account_id
         WHERE a.code IN ('111','112')
           AND t.status = 'posted'
           AND t.transaction_date BETWEEN ? AND ?"
    );
    // Map to direct method line items
    ...
}
```

## Testing Strategy

| Test file | What | Coverage |
|---|---|---|
| `Bc03Test.php` | BC03 indirect: zero balances, revenue, FA purchase, loan, cross-check | 8 existing tests — fix period issue |
| `Bc03Test.php` | BC03 direct: classify cash receipts → customers, payments → suppliers, interest, tax | 6 new tests |
| `Bc03Test.php` | CSV export: produces valid CSV, correct headers | 2 new tests |
| `Bc03Test.php` | Prior-period: snapshot read, non-year period handling | 2 new tests |

Test framework: inline `assertEq`/`assertTrue`/`assertFloatEq`/`assertThrows` (per §11.1).

## Boundaries

**Always do:**
- Run `php tests/Bc03Test.php` before marking done
- Verify CSV opens in Excel (BOM prefix, UTF-8, comma-separated)
- Cross-check: closing cash MS 70 (direct) = closing cash MS 70 (indirect) = BC01 MS 110
- Use `ON DUPLICATE KEY` or `IF NOT EXISTS` for migration

**Ask first:**
- Adding new formula types to `fs_line_items` schema
- Modifying `generateBC03()` signature (backward compat)

**Never do:**
- Remove existing indirect method
- Change `fs_line_items` table schema without migration
- Hardcode account codes in controller

---

## Detailed Spec

### 1. Direct Method (`generateBC03Direct`)

**B03-DN Direct Method line items** (new seed migration `080_seed_bc03_direct_items.php`):

| MS | Name (VI) | Formula | Source |
|---|---|---|---|
| 01 | Tiền thu từ khách hàng | direct_receipt | 131 credit (or 511 debit → 111/112) |
| 02 | Tiền chi trả cho nhà cung cấp | direct_payment | 331 debit (or 111/112 → 331, 152, 156) |
| 03 | Tiền chi trả cho người lao động | direct_payment | 334, 338 debit |
| 04 | Tiền chi trả lãi vay | direct_payment | 635 |
| 05 | Tiền chi nộp thuế TNDN | direct_payment | 3334 |
| 06 | Tiền thu khác từ HĐKD | direct_receipt | misc (no matching above) |
| 07 | Tiền chi khác cho HĐKD | direct_payment | misc |
| 10 | **Lưu chuyển thuần từ HĐKD** | sum | 01+02+03+04+05+06+07 |
| 21 | Tiền chi mua TSCĐ | direct_payment | 211, 213, 241 |
| 22 | Tiền thu từ thanh lý TSCĐ | direct_receipt | 711 |
| 23 | Tiền chi cho vay | direct_payment | 128 |
| 24 | Tiền thu hồi cho vay | direct_receipt | 128 |
| 25 | Tiền chi đầu tư | direct_payment | 221, 222 |
| 26 | Tiền thu đầu tư | direct_receipt | 221, 222 |
| 27 | Tiền thu lãi cho vay, cổ tức | direct_receipt | 515, 635 |
| 30 | **Lưu chuyển thuần từ HĐĐT** | sum | 21+22+23+24+25+26+27 |
| 31 | Tiền thu từ phát hành CP | direct_receipt | 411 |
| 32 | Tiền trả vốn góp | direct_payment | 419 |
| 33 | Tiền thu từ đi vay | direct_receipt | 341 |
| 34 | Tiền trả nợ vay | direct_payment | 341 |
| 35 | Tiền trả nợ thuê TC | direct_payment | 3412 |
| 36 | Cổ tức đã trả | direct_payment | 421 |
| 40 | **Lưu chuyển thuần từ HĐTC** | sum | 31+32+33+34+35+36 |
| 50 | **Lưu chuyển thuần trong kỳ** | sum | 10+30+40 |
| 60 | **Tiền đầu kỳ** | prior | BC01 MS 110 (prior period) |
| 70 | **Tiền cuối kỳ** | sum | 50+60 |

**Classification logic** (implement as private methods in FsService):

- `_classifyCashReceipts(from, to)` → query ledger_entries where debit in (111,112) and credit account matches classification rules
- `_classifyCashPayments(from, to)` → query ledger_entries where credit in (111,112) and debit account matches classification rules
- Opponent account determines the line item (e.g., if cash received ↔ AR → "01. Thu từ khách hàng")

### 2. CSV Export

**New endpoint:** `GET /api/fs/bc03/export?period=2025&method=direct|indirect`

**Controller method:** `FsController::bc03ExportCsv()`

Follow `ReportExportController::exportCsvLedger()` pattern:
```php
public function bc03ExportCsv(): void
{
    Auth::requirePermission('report', 'export');
    $period = $_GET['period'] ?? date('Y');
    $method = $_GET['method'] ?? 'indirect';
    $data = $method === 'direct'
        ? $this->fs->generateBC03Direct($period)
        : $this->fs->generateBC03($period);
    $headers = ['Mã số', 'Chỉ tiêu', 'Năm nay', 'Năm trước'];
    $rows = [];
    foreach ($data as $r) {
        $rows[] = [$r['ma_so'], $r['name_vi'], $r['value'], $r['prior'] ?? 0];
    }
    $result = $this->export->exportCsv($headers, $rows, "BC03_{$method}_{$period}.csv");
    header("Content-Type: {$result['mime']}");
    header("Content-Disposition: attachment; filename=\"{$result['filename']}\"");
    echo $result['content'];
}
```

**View button:** Add export button to `fs_bc03.php`:
```html
<button class="btn btn-outline-success btn-sm" onclick="exportCsv()">
    <i class="bi bi-download"></i> Xuất CSV
</button>
```

**Route:**
```php
$router->get('/api/fs/bc03/export', function() use ($c) { $c['FsController']->bc03ExportCsv(); });
```

### 3. Prior-Period Enhancement

**Fix `getPriorPeriodValues()`** to handle non-year period codes:
- Month: `2026-05` → prior = `2026-04`
- Quarter: `2026-Q2` → prior = `2026-Q1`  
- Year: `2026` → prior = `2025`
- If no prior snapshot → return zeros, log warning via AuditLogger

**View update:** `fs_bc03.php` already has `prior` column; ensure `res.prior` data renders correctly when values differ from 0.

### 4. Test Period Fix

- Open June 2026 period (or use 2026-05 for test transactions) in `Bc03Test.php`
- Add prior-period snapshot seed for year-2025 so `getPriorPeriodValues()` returns real data

---

## Acceptance Criteria

1. `php tests/Bc03Test.php` — 18+ tests, 0 failed
2. Direct method generates same closing cash (MS 70) as indirect method for same period
3. Direct method MS 70 = BC01 MS 110 (cross-statement reconciliation)
4. CSV export produces valid file with UTF-8 BOM that opens in Excel
5. Prior-year column shows non-zero values when prior snapshot exists
6. No regression in existing BC01/BC02/BC03 indirect tests (`FsTest.php`)

## Open Questions

1. Direct method line items classification: should opponent account alone determine line item, or should we also consider transaction description patterns?
2. CSV filename convention: `BC03_2025_direct.csv` or `BC03_Direct_2025.csv`?
3. Should direct method replace or supplement the existing indirect method view?

Answered:
- Q1: Opponent account classification is sufficient per Circular 99 guidance. If ambiguous, transaction description serves as fallback. ✅
- Q2: `BC03_{method}_{period}.csv` with method=direct|indirect. ✅
- Q3: Supplement — show both methods with toggle, matching view. ✅
