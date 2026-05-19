# Gap Analysis Matrix: Performance & Structural Evaluation

**Date:** 2026-05-19
**Classification:** Structural / Performance / Documentation

---

## Gap Matrix

| ID | Category | Module | Identified Gap & Operational Risk | Recommended Remediation | Priority |
|---|---|---|---|---|---|
| G01 | **Structural** | Cash & Bank (All) | **No approval workflow for payments.** Every POST posts immediately. Risk: no authorization gate for cash disbursement → fraud exposure. Non-compliant with Article 19.2 (dual signatures required for disbursement). | Add `payment_status` to transactions table (draft→approved→posted). Create approval workflow with authorization matrix per amount threshold. Cash payment endpoint checks approval status before posting. | **HIGH** |
| G02 | **Structural** | FS — BC 03 | ~~Cash Flow Statement not implemented.~~ ✅ **Resolved.** `FsService::generateBC03()` implements indirect method. Operating section from BC 02 profit + working capital deltas. Investing/financing from balance sheet account deltas. Validation: MS70 = MS50+MS60+MS61 + cross-check with BC 01 MS110. | Migration 043 + Bc03Test (20 tests, 0 failed) + controller/view/route. | **RESOLVED** |
| G03 | **Structural** | FS — BC 09 | **Notes to FS not implemented.** BC 09 is integral to FS per VAS 21. Missing → FS package incomplete. 29 accounting policy sub-sections must be disclosed. | Implement BC 09 generator with 9 sections. Create templates for accounting policy disclosures. Cross-reference every BC 01/02/03 mã số to a BC 09 note. | **HIGH** |
| G04 | **Structural** | GL — So Cái | **Subsidiary ledgers (sổ chi tiết) missing.** Per-account, per-customer, per-supplier detail views not built. Risk: cannot produce detailed breakdowns for audit or BC 09. | Build subsidiary ledger controller + view. Query LedgerEntry filtered by account_code + customer/supplier reference. Show running balance, contra account. Add print format with page numbers + signature fields (Article 26.7). | **HIGH** |
| G05 | **Structural** | Correction Engine | **No Article 27 correction methods.** System has no way to correct posted entries except direct DB edit. Risk: deletion or modification of posted entries violates Law on Accounting Article 27. | Implement 3 correction methods: (1) supplementary entry (additional amount), (2) negative entry (red ink reversal), (3) adjusting entry. All methods create new TXN referencing original. Flag `is_correction=true` + `corrected_transaction_id`. | **HIGH** |
| G06 | **Structural** | Period Engine | **No pre-close checklist enforcement.** Period close does not verify preconditions (inventory done, FC revalued, sub-ledgers reconciled, trial balance balanced). Risk: close with incomplete data → FS errors. | Implement pre-close checklist query: check physical inventory completed, FC revaluation posted, sub-ledger=GL balance match, trial balance Dr=Cr. Block close if any check fails. | **HIGH** |
| G07 | **Structural** | AP / AR | **No bad debt provision engine.** TK 2293 provision estimation and write-off not built (UC-007, UC-008 in AR spec; UC-008 in AP spec). Risk: misstated AR balance on BC 01. | Implement provision estimation based on aging buckets + configurable loss rates. Write-off flow: Dr 2293 (provision) + Dr 642 (excess) → Cr 131. Off-balance-sheet tracking for written-off amounts. | **HIGH** |
| G08 | **Structural** | AP / AR | **No 3-way PO match.** Purchase order → goods receipt → invoice matching not built. Risk: pay for goods not received, duplicate payment. | Implement PO table + match workflow. Before AP posting: verify PO exists, quantity received ≥ quantity invoiced, unit price within tolerance. Flag mismatches. | **MEDIUM** |
| G09 | **Structural** | Fixed Assets | **No FA lifecycle.** Asset registration, depreciation, increase/decrease/transfer/liquidation not built (K48-K53). Risk: FA balances on BC 01 are manual entry only. | Build FixedAssetService: register asset, calculate monthly depreciation (straight-line, declining balance, production-based), post Dr 627/641/642 → Cr 214. Handle increase/decrease/transfer/liquidation. | **HIGH** |
| G10 | **Structural** | CCDC (TK 242) | **No multi-period allocation.** CCDC costs are immediate expense only. TK 242 amortization schedule not built. Risk: misstatement in period expense. | Implement CcdcAllocationService: record CCDC at acquisition (Dr 242), calculate monthly amortization, post Dr 627/641/642 → Cr 242 proportionally over allocation period. | **MEDIUM** |
| G11 | **Structural** | Inventory | **No negative stock warning/prevention.** issueGoods checks `stockQty < qty` and throws, but there is no soft warning or configurable threshold before hitting zero. Risk: operational disruption when stock dips below safety level. | Add `min_stock` check in issueGoods: if post-issue qty < min_stock, emit warning (toast). Optionally block issue if `min_stock` threshold enforcement is enabled. | **MEDIUM** |
| G12 | **Structural** | Tax | **No VAT declaration data prep.** No mechanism to aggregate VAT input (TK 133) and output (TK 3331) by period for declaration forms. Risk: cannot produce VAT return → tax filing non-compliance. | Implement `TaxService::getVatSummary(periodId)`: query 133 (input) and 3331 (output) by period, calculate net payable/refundable. Generate GV-01/GTGT data structure. | **MEDIUM** |
| G13 | **Structural** | Tax | **No CIT calculation.** No model to compute provisional (quarterly) or final (annual) CIT. Risk: cannot produce CIT return or determine CIT expense for BC 02. | Implement CIT calculation: net profit (BC 02 50) × applicable rate, adjust for non-deductible expenses, tax incentives. Post Dr 8211 → Cr 3334. | **MEDIUM** |
| G14 | **Structural** | Multi-branch | **No consolidation logic.** Branch entity exists. Inter-branch transactions not eliminated. Risk: double-counted revenue/expenses in consolidated reports. | Implement consolidation service: identify inter-branch AR/AP, revenue/expense by matching counterparty references. Eliminate from consolidated FS. | **MEDIUM** |
| G15 | **Structural** | Document Retention | **No retention tracking.** Documents not classified by retention tier (5/10 years/permanent). No archiving workflow. Risk: non-compliance with Article 41 (document preservation). | Implement retention tier assignment per document type. Archive workflow: package documents, track storage location, alert before retention expiry. | **LOW** |
| --- | --- | --- | --- | --- | --- |
| G16 | **Performance** | Bank Reconciliation | **No bank statement import.** Every reconciliation is manual entry. CSV/MT940 import not built. Risk: accountant spends hours per month manually keying bank data. | Implement BankStatementImporter service: parse CSV/MT940/QIF/OFX. Auto-match by amount + reference + date. Display unmatched items for manual pairing. | **HIGH** |
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
| G27 | **Documentation** | AGENTS.md | **Migration count (41 vs 42) and test count out of sync.** AGENTS.md not auto-updated on each commit. Risk: stale reference data for developers. | Add AGENTS.md auto-update check to commit hook or set reminder to update on each migration/addition. | **LOW** |

---

## Summary

| Category | Count | HIGH | MEDIUM | LOW |
|---|---|---|---|---|
| **Structural** | 15 | 6 | 7 | 2 |
| **Performance** | 6 | 1 | 5 | 0 |
| **Documentation** | 6 | 0 | 4 | 2 |
| **Total** | **27** | **7** | **16** | **4** |

### Critical Path (HIGH priority — address first)

```
G01 Payment approval workflow  ───→  G09 FA lifecycle  ───→  G05 Correction methods
G02 BC 03 (Cash Flow FS)       ───→  G03 BC 09 (Notes)
                                         ↓
G06 Pre-close checklist        ───→  G07 Bad debt provision
G16 Bank statement import
```

### Next Actions (HIGH priority)

1. **G02 + G03: BC 03 and BC 09.** Legal compliance gap. Must ship before first annual FS deadline. Estimated: 2 sessions (BC 03), 2-3 sessions (BC 09).
2. **G09: Fixed Assets lifecycle.** Core module for BC 01 balance sheet accuracy. Necessary for depreciation expense on BC 02. Estimated: 2 sessions.
3. **G05: Correction Engine.** Legal requirement per Article 27. Prevents audit trail corruption. Estimated: 1 session.
4. **G01: Payment approval workflow.** Operational fraud control. Estimated: 1-2 sessions (adds state machine + authorization matrix).
5. **G16: Bank statement import.** Direct productivity gain for daily reconciliation. Estimated: 1 session.
6. **G06: Pre-close checklist enforcement.** Period integrity control. Estimated: 0.5 session.
7. **G07: Bad debt provision.** BC 01 asset valuation accuracy. Estimated: 1 session.
