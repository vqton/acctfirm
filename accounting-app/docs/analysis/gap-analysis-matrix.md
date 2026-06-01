# Gap Analysis Matrix: Performance & Structural Evaluation

**Date:** 2026-05-19
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
| G07 | **Structural** | AP / AR | **No bad debt provision engine.** TK 2293 provision estimation and write-off not built (UC-007, UC-008 in AR spec; UC-008 in AP spec). Risk: misstated AR balance on BC 01. | Implement provision estimation based on aging buckets + configurable loss rates. Write-off flow: Dr 2293 (provision) + Dr 642 (excess) → Cr 131. Off-balance-sheet tracking for written-off amounts. | **HIGH** |
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
| G22 | **Documentation** | Master Roadmap | **Master roadmap not reconciled with actual progress.** Shows Phases 1-8 with many "NOT STARTED" that are actually complete. Risk: stakeholder confusion about project status. | Rewrite MASTER_IMPLEMENTATION_ROADMAP.md v2.0 with current status. Mark completed modules. Update dependency graph to reflect actual build order. | **MEDIUM** |
| G23 | **Documentation** | Inventory Spec vs Code | **Inventory Journal spec documents 34 UCs across 8 domains.** Code implements all inventory operations but not mapped to UC numbers. Risk: unclear which UCs are covered. | Create traceability matrix mapping implemented methods → UC-0XX numbers. Audit for gaps between spec and code. | **LOW** |
| G24 | **Documentation** | Treasury Spec | **Treasury spec defines 37 UCs across 1,900 lines.** Basic CRUD implemented but missing 15+ UC scenarios (FC handling, multi-currency, cash-in-transit lifecycle, approval workflow). Risk: spec scope ≠ implementation scope. | Conduct delta analysis: for each of 37 Treasury UCs, mark status (implemented/partial/missing). Prioritize gap closure per compliance risk. | **MEDIUM** |
| G25 | **Documentation** | Transaction States | **No formal state machine definition.** Current code recognizes "draft/pending/posted" but specs document "Pending (unposted)" as UC-001 state. Risk: inconsistent usage across modules. | Define formal transaction state machine: Draft → Pending → Posted. Document states in AGENTS.md code patterns. Enforce transitions in JournalService. | **LOW** |
| G26 | **Documentation** | Correction Methods | **No documented error correction policy.** The system currently has no process for correcting posted entries. Risk: users resort to DB edits → audit trail broken. | Document Article 27 correction workflow in a new spec section. Implement correction methods. Train users on correct procedure. | **MEDIUM** |
| G27 | **Documentation** | AGENTS.md | **Migration count (42 → 81) and test count out of sync.** AGENTS.md not auto-updated on each commit. Risk: stale reference data for developers. | Add AGENTS.md auto-update check to commit hook or set reminder to update on each migration/addition. | **LOW** |

---

## Summary

| Category | Count | RESOLVED | HIGH | MEDIUM | LOW |
|---|---|---|---|---|---|
| **Structural** | 15 | 7 | 1 (G07) | 5 | 2 |
| **Performance** | 6 | 1 | 0 | 5 | 0 |
| **Documentation** | 6 | 0 | 0 | 4 | 2 |
| **Total** | **27** | **8** | **1** | **14** | **4** |

### Resolved Gaps (moved to implementation tracking)

8 of 27 gaps resolved since 2026-05-19: G01, G02, G05, G09, G10, G12, G13, G14, G16.

### Remaining HIGH Priority

G07 Bad debt provision — BC 01 asset valuation accuracy. No provision engine yet.
