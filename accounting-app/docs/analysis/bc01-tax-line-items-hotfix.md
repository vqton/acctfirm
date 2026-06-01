# BC01 Formula Hotfix: Tax Line Items (MS 162, MS 163, MS 314, MS 333)

## Status
Implemented via migration `085_fix_bc01_tax_line_items.php`

## Bug Description
BC01 (Báo cáo tình hình tài chính) has 4 line items referencing **control accounts** in `formula_detail`:
- MS 162 (Thuế GTGT được khấu trừ): `formula_detail: '133'` → balance = 0
- MS 163 (Thuế và khoản khác phải thu NN): `formula_detail: '1383,333'` → 333 balance = 0
- MS 314 (Thuế và các khoản phải nộp NN): `formula_detail: '333'` → balance = 0
- MS 333 (Thuế và khoản phải nộp NN DH): `formula_detail: '333'` → balance = 0

Since all postings go to sub-accounts (1331, 1332, 33311, 33312, etc.), the control accounts always report balance = 0, causing these BC01 line items to be understated.

### Impact
- **MS 162**: VAT input (1331 + 1332) omitted → Total assets understated
- **MS 163**: Tax receivables (1383 + all 333 sub-accounts) omitted
- **MS 314**: Tax payables (all 333 sub-accounts) omitted → Total liabilities understated
- **MS 333**: Long-term tax payables (333 sub-accounts) omitted

### Root Cause
Database migration `038_create_fs_tables.php` line 69-70, 137, 154 used control account codes instead of sub-account codes when defining `fs_line_items` formula.

## Resolution
Change `formula_detail` to reference sub-accounts directly:

| MS | Before | After |
|----|--------|-------|
| 162 | `133` | `1331,1332` |
| 163 | `1383,333` | `1383,3331,3332,3333,3334,3335,3336,3337,3338,3339` |
| 314 | `333` | `3331,3332,3333,3334,3335,3336,3337,3338,3339` |
| 333 | `333` | `3331,3332,3333,3334,3335,3336,3337,3338,3339` |

### Verification
1. Run `085_fix_bc01_tax_line_items.php` migration
2. IntegrationSmokeTest Cycle A: assets = 130M, L+E = 130M, gap = 0
3. Run `for f in tests/*.php; do php "$f"; done` — 0 failures

## Evidence
- TT 99/2025/TT-BTC Phụ lục II: TK 133 sub-accounts = 1331, 1332; TK 333 sub-accounts = 3331-3339
- All journal postings use sub-accounts (control account protection blocks direct posting to 133/333)
- Integration test confirmed: 1331 balance = 2.5M, 1332 balance = 12M, 133 balance = 0
