# BC02 — Implementation Status & Plan

## Current State

| File | Status |
|------|--------|
| `docs/analysis/bc02-tt99-full-analysis.md` | ✅ Complete (12 sections) |
| `XbrlGenerator.php` BC02_MAP | ✅ Fixed (P0 — G03) |
| Migration 116 (seed fixes) | ✅ Done (G01/G04/G99) |
| `FsService.php` comments | ✅ Fixed (G05/G08) |
| Test suite | ✅ 1,923+ tests / 78 files / 0 new failures (1 pre-existing) |
| G02 sub-accounts (6351-6358) | ✅ Created (migration 117) |
| MS 23 formula_type | ✅ Changed to `account_tree` (migration 118) |
| MS 24 formula_detail | ✅ Changed to `6351` (migration 118) |
| 635 is_control flag | ✅ Set to 1 |
| COA seed | ✅ Updated (`data/coa_circular_99.json`) |

## Remaining Gaps

| ID | Priority | Gap | Status |
|----|----------|-----|--------|
| G12 | **P1** | BC02 view: prior period values | ✅ DONE |
| G09 | **P2** | Manual input for MS 21 (Lãi/lỗ BĐS ĐT) | ✅ DONE |
| G10 | **P3** | Manual input for MS 70/71 (EPS) | ✅ DONE |
| G11 | **P2** | Snapshot: manual values preserved | ✅ DONE |
| G07 | **P2** | Dedicated BC02 tests (72 tests) | ✅ DONE |
| G02 | **P1** | MS 24: sub-account for 635 lãi vay | ✅ DONE |

## Implementation Log

| Phase | Date | What | Files |
|-------|------|------|-------|
| 1 — Engine | 2026-06-08 | FsService: $manualValues param in generateBC02/generateStatement | `FsService.php` |
| 2 — API | 2026-06-08 | FsController: manual values CRUD, prior period, save endpoint | `FsController.php`, `api_financial.php` |
| 3 — UI | 2026-06-08 | fs_bc02 view: editable MS 21/70/71, prior period display, save btn | `fs_bc02.php` |
| 4 — Tests | 2026-06-08 | 72 tests: zero, full P&L, manual, save/load, prior, XBRL, snapshot, validation | `Bc02Test.php` |
| 5 — G02 | 2026-06-08 | Sub-accounts 6351-6358 + MS 23→account_tree + MS 24→6351 + tests | See AGENTS.md v4.2 |
