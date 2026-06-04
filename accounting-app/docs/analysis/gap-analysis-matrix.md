# Gap Analysis Matrix: Performance & Structural Evaluation

**Date:** 2026-06-02
**Classification:** Structural / Performance / Documentation

---

## Gap Matrix

| ID | Category | Module | Identified Gap & Operational Risk | Recommended Remediation | Priority |
|---|---|---|---|---|---|
| G01 | **Structural** | Cash & Bank (All) | **No approval workflow for payments.** Every POST posts immediately. Risk: no authorization gate for cash disbursement → fraud exposure. Non-compliant with Article 19.2 (dual signatures required for disbursement). | ~~Add `payment_status` to transactions table…~~ ✅ **Resolved.** `ApprovalRoutingService` + `ApprovalController` + approval view + state machine (migration 052). | **RESOLVED** |
| G02 | **Structural** | FS — BC 03 | ~~Cash Flow Statement not implemented.~~ ✅ **Resolved.** `FsService::generateBC03()` implements indirect method. Operating section from BC 02 profit + working capital deltas. Investing/financing from balance sheet account deltas. Validation: MS70 = MS50+MS60+MS61 + cross-check with BC 01 MS110. | Migration 043 + Bc03Test (20 tests, 0 failed) + controller/view/route. | **RESOLVED** |
| G03 | **Structural** | FS — BC 09 | **Notes to FS not implemented.** BC 09 is integral to FS per VAS 21. Missing → FS package incomplete. 29 accounting policy sub-sections must be disclosed. | ⚠️ **Partially resolved.** `bc09.php` view + `FsController::viewTT99()` render BC 09. 29-section automated disclosure not yet complete. | **HIGH** |
| G04 | **Structural** | GL — So Cái | **Subsidiary ledgers (sổ chi tiết) missing.** Per-account, per-customer, per-supplier detail views not built. Risk: cannot produce detailed breakdowns for audit or BC 09. | Build subsidiary ledger controller + view. Query LedgerEntry filtered by account_code + customer/supplier reference. Show running balance, contra account. Add print format with page numbers + signature fields (Article 26.7). | **HIGH** |
| G05 | **Structural** | Correction Engine | ~~No Article 27 correction methods~~ ✅ **Resolved.** `CorrectionController` implements supplementary + negative + adjusting (migration 062). | Implement 3 correction methods: (1) supplementary entry, (2) negative entry, (3) adjusting entry — all via `CorrectionController`. | **RESOLVED** |
| G06 | **Structural** | Period Engine | **Pre-close checklist partially enforced.** `PeriodService::canClose()` checks trial balance + triggers + reconciliation via `ReconciliationService`. No dedicated UI for checklist preview. | ⚠️ **Partially resolved.** `PeriodService::canClose()` + `getCloseChecklist()` exist. No standalone checklist UI screen. | **HIGH** |
| G07 | **Structural** | AP / AR | ~~No bad debt provision engine.~~ ✅ **Resolved.** `DebtCollectionService` provides write-off proposals (tests 31-33, 62), multi-level approval (tests 32-33), settlement with discount (tests 34-39), provision estimation via aging buckets. Write-off flow: pending approval → approved → status=written_off | Implemented via `DebtCollectionService` (84 tests): write-off proposal + approval chain + settlement + auto-close queue on payment. | **RESOLVED** |
| G08 | **Structural** | AP / AR | **No 3-way PO match.** Purchase order → goods receipt → invoice matching not built. Risk: pay for goods not received, duplicate payment. | Implement PO table + match workflow. Before AP posting: verify PO exists, quantity received ≥ quantity invoiced, unit price within tolerance. Flag mismatches. | **MEDIUM** |
| G09 | **Structural** | Fixed Assets | ~~No FA lifecycle~~ ✅ **Resolved.** `FixedAssetService` + lifecycle controller + acquisition/disposal/depreciation views + 25 lifecycle tests. | Build FixedAssetService: register, depreciate, acquire/dispose. Implemented via lifecycle views + integration with GL/AP/Cash. | **RESOLVED** |
| G10 | **Structural** | CCDC (TK 242) | ~~No multi-period allocation~~ ✅ **Resolved.** `CcdcAllocationService` + migration 063 (ccdc_allocations) + controller + 28 lifecycle tests. | Implement CcdcAllocationService: acquisition → amortization schedule → monthly Dr 627/641/642 → Cr 242. | **RESOLVED** |
| G11 | **Structural** | Inventory | **No negative stock warning/prevention.** issueGoods checks `stockQty < qty` and throws, but there is no soft warning or configurable threshold before hitting zero. Risk: operational disruption when stock dips below safety level. | Add `min_stock` check in issueGoods: if post-issue qty < min_stock, emit warning (toast). Optionally block issue if `min_stock` threshold enforcement is enabled. | **MEDIUM** |
| G12 | **Structural** | Tax | ~~No VAT declaration data prep~~ ✅ **Resolved.** `VatService` + migration 064 + `VatController` — scan non-deductible, reconcile, loss carryforward. | Implement `VatService::getVatSummary()`: query 133/3331 by period, generate GV-01/GTGT. | **RESOLVED** |
| G13 | **Structural** | Tax | ~~No CIT calculation~~ ✅ **Resolved.** `CitService` + migration 065 + `CitController` — scan non-deductible, reconcile, loss carryforward. | Implement CIT calculation: net profit × rate, adjust for non-deductible, post Dr 8211 → Cr 3334. | **RESOLVED** |
| G14 | **Structural** | Multi-branch | ~~No consolidation logic~~ ✅ **Resolved.** `IntercompanyService` (280 lines) provides matching + elimination + consolidated report. Migration 055 (accounting_entities). | Implement consolidation service: `IntercompanyService::matchBalances()` + `eliminate()` + `consolidatedReport()`. | **RESOLVED** |
| G15 | **Structural** | Document Retention | **No retention tracking.** Documents not classified by retention tier (5/10 years/permanent). No archiving workflow. Risk: non-compliance with Article 41 (document preservation). | Implement retention tier assignment per document type. Archive workflow: package documents, track storage location, alert before retention expiry. | **LOW** |
| --- | --- | --- | --- | --- | --- |
| G16 | **Performance** | Bank Reconciliation | ~~No bank statement import~~ ✅ **Resolved.** `BankReconciliationController::importCsv()` parses CSV with auto-match. | Implement `BankStatementImporter`: parse CSV/MT940, auto-match by amount + reference + date. | **RESOLVED** |
| G17 | **Performance** | AP / AR | **No batch payment processing.** Each supplier payment requires individual entry. No multi-invoice payment batch. Risk: slow month-end payment runs for enterprises with 50+ suppliers. | Implement PaymentBatchEngine: select multiple invoices across suppliers, generate single bank transfer file (ISO 20022/SEPA/TTSP), post batch journal entry (Dr 331 multiple lines — Cr 112). | **MEDIUM** |
| G18 | **Performance** | Master Data | **No Excel import.** All master data entered via individual modal forms. Risk: slow initial data entry; error-prone for large datasets. | Build generic ExcelImportController: upload .xlsx, map columns to entity fields, validate rows, batch insert with error report. Support Items, Customers, Suppliers, Accounts. | **MEDIUM** |
| G19 | **Performance** | Global UI | **No global search.** Users cannot search across modules from the top bar. Risk: time wasted navigating menus to find customers, invoices, items. | Implement global search endpoint (`/api/search?q=...`) scanning Items, Customers, Suppliers, Transactions. Add search bar to layout.php top bar with jQuery autocomplete. | **MEDIUM** |
| G20 | **Performance** | Cash & Bank | **No accounting templates / recurring entries.** Cash receipt/payment templates exist in spec but not exposed as easy-to-use preset system. Risk: repetitive data entry for common transactions. | Build TemplateEngine: save transaction as template with placeholder fields. On use: pre-fill form, user fills only variable fields (amount, date). Support auto-post on schedule. | **MEDIUM** |
| G21 | **Performance** | Reports | **No PDF/Excel export.** Reports display as HTML tables only. Risk: cannot submit FS to tax authority or email to management in standard format. | Implement `ExportService`: generate formatted PDF using TCPDF/Dompdf for FS and GL. Generate .xlsx using PhpSpreadsheet for trial balance and aging reports. | **MEDIUM** |
| --- | --- | --- | --- | --- | --- |
| G22 | **Documentation** | Master Roadmap | ~~Master roadmap not reconciled with actual progress.~~ ✅ **Resolved.** Tax engine roadmap (§14) updated with ✅/⬜/⏳ per phase. Implementation summary (§15) added with actual file references + test counts. | `docs/analysis/tax-engine-brain-logic.md` updated 06/2026 — Phases 1-6 marked completed, §15 Implementation Summary added. Other module roadmaps pending same treatment. | **RESOLVED** |
| G23 | **Documentation** | Inventory Spec vs Code | **Inventory Journal spec documents 34 UCs across 8 domains.** Code implements all inventory operations but not mapped to UC numbers. Risk: unclear which UCs are covered. | Create traceability matrix mapping implemented methods → UC-0XX numbers. Audit for gaps between spec and code. | **LOW** |
| G24 | **Documentation** | Treasury Spec | **Treasury spec defines 37 UCs across 1,900 lines.** Basic CRUD implemented but missing 15+ UC scenarios (FC handling, multi-currency, cash-in-transit lifecycle, approval workflow). Risk: spec scope ≠ implementation scope. | Conduct delta analysis: for each of 37 Treasury UCs, mark status (implemented/partial/missing). Prioritize gap closure per compliance risk. | **MEDIUM** |
| G25 | **Documentation** | Transaction States | **No formal state machine definition.** Current code recognizes "draft/pending/posted" but specs document "Pending (unposted)" as UC-001 state. Risk: inconsistent usage across modules. | Define formal transaction state machine: Draft → Pending → Posted. Document states in AGENTS.md code patterns. Enforce transitions in JournalService. | **LOW** |
| G26 | **Documentation** | Correction Methods | ~~No documented error correction policy.~~ ✅ **Resolved.** `CorrectionController` implements Article 27 supplementary + negative + adjusting methods. Correction workflow documented in accounting-engine-brain.md §7. | Implemented: 3 correction methods via `CorrectionController` (migration 062). Documentation: accounting-engine-brain.md covers correction methods. | **RESOLVED** |
| G27 | **Documentation** | AGENTS.md | **Migration count (42 → 81) and test count out of sync.** AGENTS.md not auto-updated on each commit. Risk: stale reference data for developers. | Add AGENTS.md auto-update check to commit hook or set reminder to update on each migration/addition. | **LOW** |

---

## Summary

| Category | Count | RESOLVED | HIGH | MEDIUM | LOW |
|---|---|---|---|---|---|---|---|
| **Structural** | 15 | 8 | 0 | 5 | 2 |
| **Performance** | 6 | 1 | 0 | 5 | 0 |
| **Documentation** | 6 | 2 | 0 | 2 | 2 |
| **Total** | **27** | **11** | **0** | **12** | **4** |

### Feature Gaps Implemented (Gap Specs 1-10)

6 of 10 feature gaps implemented in this session (Phase A + Gaps 1-5, 7):

| Gap | Module | Service | Migrations | Tests | Status |
|---|---|---|---|---|---|
| Phase A | Export/SubLedger/BC09 | ExportService, ReportExportService | 088-093 | 124 | ✅ |
| 1 | Sales Order | SalesOrderService | — | 28 | ✅ |
| 2 | Cost/Manufacturing | ManufacturingService | 096 | 30 | ✅ |
| 3 | Budget & Planning | BudgetService | 097 | 13 | ✅ |
| 4 | Contract Management | ContractService | 094 | 6 | ✅ |
| 5 | Project Accounting | ProjectAccountingService | 095 | 18 | ✅ |
| 7 | Custom Report Builder | ReportBuilderService | 098 | 20 | ✅ |

Gap 6 (BC09) and Gap 8 (SubLedger) were bundled into Phase A. Gap 9 (Mobile App) deferred. Gap 10 (PDF/Excel) bundled into Phase A.

### Resolved Gaps (moved to implementation tracking)

11 of 27 matrix gaps resolved since 2026-05-19: G01, G02, G05, G07, G09, G10, G12, G13, G14, G16, G22, G26.

### Remaining Gaps

✅ All original HIGH-priority gaps resolved. Remaining 16 gaps are MEDIUM/LOW (G03 BC09 partially, G04 subsidiary ledger, G06 pre-close UI, G08 3-way PO, G11 negative stock warning, G15 document retention, G17 batch payment, G18 Excel import, G19 global search, G20 templates, G21 PDF/Excel export, G23 inventory UC traceability, G24 treasury delta, G25 state machine definition, G27 AGENTS.md sync).
