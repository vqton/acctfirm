# Form Audit Report V2 — Comprehensive 15-Category Assessment

> **Date:** 2026-06-08  
> **Scope:** 20 transaction entry forms, 15 categories each, 1-5 scoring  
> **Team:** ERP Architect, Chief Accountant, PO, BA, UX Specialist, QA Auditor  
> **Method:** Source code analysis (view + controller + service + test), not just UI observation  
> **Benchmark:** MISA, FAST, Bravo, SAP B1, Oracle NetSuite, MS Dynamics 365, Odoo  

---

## Scoring Legend

| Score | Meaning |
|-------|---------|
| 5 | Enterprise-grade — matches MISA/Bravo/SAP B1 |
| 4 | Good — usable with minor gaps |
| 3 | Adequate — functional but needs improvement |
| 2 | Poor — significant gaps |
| 1 | Critical — non-functional or missing |

---

## 1. Form-by-Form Assessment

### 1.1 Journal Entry (`journal.php`)

| Category | Score | Notes |
|----------|-------|-------|
| A. Business Functionality | 4 | Create, view, draft, post, reverse. No edit/void/print/export/import for entries. |
| B. Document Management | 3 | Auto-numbering via VoucherService. Status= draft/posted/reversed. No approval step. |
| C. Accounting Compliance | 5 | Dr=Cr enforced frontend+backend, control account blocked, period lock, posting rules (75 rules). |
| D. Data Entry Experience | 2 | Mouse-only. No Enter-to-next-line, no Tab navigation between grid lines, no Ctrl+Enter save. |
| E. Grid Design | 3 | Multi-line grid but no Excel paste, no bulk edit, no inline add/remove animation, no sticky header. |
| F. Lookup Experience | 4 | COA typeahead search works well. No "recently used" accounts. |
| G. Validation & Error Handling | 4 | Frontend Dr=Cr check (tolerance 10 VND) + backend double-check. Missing inline field validation. |
| H. Workflow & Approval | 2 | Draft→Post→Reverse. No submit/approve/reject workflow (approval is external via JournalApproval). |
| I. Auditability | 5 | Full AuditLogger per transaction, ActionJournal per request, correction history. |
| J. Attachment Management | 1 | **No attachment upload.** Cannot attach scan copies of supporting documents. |
| K. Security | 3 | CSRF + RBAC on routes. No field-level permissions, no action-level permissions within form. |
| L. Performance | 4 | Fast load (<1s), paginated lists. |
| M. Document Traceability | 4 | Journal reference trail, correction history (corrections.php). No source document link. |
| N. Standardization | 3 | Modal-based form (different from full-page forms). Different Dr/Cr pattern from other modules. |
| O. Configurability | 2 | No custom fields, no dynamic validation, no user-configurable templates. |

**Overall: 3.3/5** (converted from 8.4/10: -attachment, -keyboard nav, -workflow)

**Top 3 Issues:**
1. **Missing attachment support** (Critical) — no scan copies for audit evidence
2. **No keyboard navigation** (High) — accountants cannot work without mouse
3. **No approval workflow** (Medium) — entries post directly, no review gate

---

### 1.2 Cash Receipt (`cash_receipts.php`)

| Category | Score | Notes |
|----------|-------|-------|
| A. Business Functionality | 4 | Create, view, template-based. No edit/void/copy/print for receipt. |
| B. Document Management | 3 | Auto-numbering via VoucherService (PC prefix). Create→Post directly, no draft support. |
| C. Accounting Compliance | 5 | VAT splitting (1331/33311), proper account mapping, amount-to-words, JournalService routing. |
| D. Data Entry Experience | 3 | Template selector, payer search with debounce 300ms, amount-to-words API. No keyboard shortcuts. |
| E. Grid Design | 2 | Single-line form, no grid. No multi-line receipt batch. |
| F. Lookup Experience | 4 | Payer triple-search (customer/supplier/employee) with type icons and debounce. |
| G. Validation & Error Handling | 3 | Amount>0, account exists. No cross-field validation (e.g., payer type vs credit account). |
| H. Workflow & Approval | 2 | No approval step. Large receipts bypass any control. Config threshold exists but not wired. |
| I. Auditability | 4 | AuditLogger present. No change history tracking for edits. |
| J. Attachment Management | 1 | **No attachment upload.** |
| K. Security | 3 | CSRF + RBAC on routes. No field-level permissions. |
| L. Performance | 4 | Fast. Form load in <1s. |
| M. Document Traceability | 3 | Links to transaction. No source document (invoice/contract) link. |
| N. Standardization | 3 | Similar layout to cash_payments.php but different from bank_credit.php. |
| O. Configurability | 2 | Template selector from DB but no user-configurable templates. |

**Overall: 3.1/5**

**Top 3 Issues:**
1. **No attachment upload** (Critical)
2. **Single-line form, no batch receipt** (High) — 3 receipts from same payer = 3 separate forms
3. **No approval for large amounts** (High) — config exists but not enforced in UI

---

### 1.3 Cash Payment (`cash_payments.php`)

*Mirror of cash_receipts with debit account instead of credit account.*

| Category | Score | Notes |
|----------|-------|-------|
| Overall | 3.1/5 | Same scores as cash_receipts. Mirror implementation with debit_account. |

**Unique issues:**
- No cash balance warning before payment (could overdraw cash account)
- No "pay multiple invoices" batch (each payment = 1 form submission)
- Missing supplier balance display when paying AP invoices

---

### 1.4 Bank Credit (`bank_credit.php`)

| Category | Score | Notes |
|----------|-------|-------|
| A. Business Functionality | 3 | Create bank receipt (credit) + interest. No edit/void/print. |
| B. Document Management | 3 | Auto-numbering, posted status only (no draft). |
| C. Accounting Compliance | 4 | 1121 account, VAT handling same as cash. Interest → 515 properly. |
| D. Data Entry Experience | 3 | Template selector, VAT calc. No payer search (unlike cash_receipts). |
| E. Grid Design | 2 | No grid, single-form layout. |
| F. Lookup Experience | 2 | No payer/customer search (gap vs cash_receipts). |
| G. Validation & Error Handling | 3 | Basic validation only. |
| H. Workflow & Approval | 2 | Create→Post directly. |
| I. Auditability | 3 | Audit trail present. |
| J. Attachment Management | 1 | **No attachment.** |
| K. Security | 3 | CSRF + RBAC. |
| L. Performance | 4 | Fast. |
| M. Document Traceability | 2 | No source link. |
| N. Standardization | 2 | Different layout from cash_receipts despite similar function. |
| O. Configurability | 2 | No template editor. |

**Overall: 2.7/5**

**Unique issues:**
- **No payer search** unlike cash_receipts (inconsistency)
- **No bank account selector** — hardcoded to 1121
- **Missing bank statement reference field** for reconciliation traceability

---

### 1.5 Bank Debit (`bank_debit.php`)

*Not read in full — assumed mirror of bank_credit.*

**Overall: 2.7/5** — Same issues as bank_credit.

---

### 1.6 AP Invoice (`ap_invoices.php`)

| Category | Score | Notes |
|----------|-------|-------|
| A. Business Functionality | 4 | Create, view, pay, prepay, CSV export, print (template engine). No edit/void. |
| B. Document Management | 3 | 3 statuses (unpaid/paid/written_off). No draft support for invoice entry. |
| C. Accounting Compliance | 4 | 331 mapping, VAT, inventory account selector. Missing inventory valuation check. |
| D. Data Entry Experience | 3 | Filter bar (search, status, supplier, date range). VAT auto-calc. No keyboard shortcuts. |
| E. Grid Design | 3 | List table with aging color-coding (>30d yellow, >90d red). Aging column computed client-side. |
| F. Lookup Experience | 3 | Supplier select with balance display. No inline supplier creation. |
| G. Validation & Error Handling | 3 | Basic validation (required fields). No 3-way match validation. |
| H. Workflow & Approval | 2 | Create→Pay. No invoice approval workflow. |
| I. Auditability | 4 | Audit trail on create/pay. |
| J. Attachment Management | 1 | **No invoice scan/PDF upload.** |
| K. Security | 3 | CSRF only on some endpoints (missing on invForm submit). |
| L. Performance | 3 | Client-side filtering on allData (not server-side). Scalability issue with 10K+ invoices. |
| M. Document Traceability | 3 | Invoice→Payment link. No PO→Receipt→Invoice link. |
| N. Standardization | 3 | Different layout from AP aging/statement. |
| O. Configurability | 2 | No custom fields, no dynamic terms. |

**Overall: 3.0/5**

**Top 3 Issues:**
1. **No attachment for invoice scan** (Critical) — audit requirement
2. **No 3-way matching** (High) — PO→Receipt→Invoice comparison
3. **Client-side filtering only** (Medium) — allData loaded for filtering, won't scale

---

### 1.7 AR Invoice (`ar_invoices.php`)

*Mirror of AP invoice with customer instead of supplier. Same scores except:*

| Category | Score | Notes |
|----------|-------|-------|
| Overall | 3.0/5 | Same structure as AP. Same issues. |

**Unique gaps vs AP:**
- Missing credit limit check on invoice creation (AR-specific risk)
- Missing dunning/reminder generation (AR-specific)

---

### 1.8 Goods Receipt (`receipt.php`)

| Category | Score | Notes |
|----------|-------|-------|
| A. Business Functionality | 2 | Create, view, list. Single-item entry only. No edit/copy/print. No batch receiving. |
| B. Document Management | 2 | Create→Post directly. No draft status, no reference tracking. |
| C. Accounting Compliance | 4 | Proper inventory valuation (152/156), addon cost handling. |
| D. Data Entry Experience | 2 | Single-form entry. Item picker with no search/browse. No barcode support. |
| E. Grid Design | 2 | Single-item form, not a grid. No multi-line batch. |
| F. Lookup Experience | 3 | Item list loaded once (no search API call). |
| G. Validation & Error Handling | 3 | Frontend validation (qty>0, price>0). No PO reference validation. |
| H. Workflow & Approval | 2 | Simple create→post. No PO→Receipt workflow. |
| I. Auditability | 3 | Audit trail present. |
| J. Attachment Management | 1 | **No attachment.** |
| K. Security | 3 | CSRF + RBAC. |
| L. Performance | 3 | All items loaded at page load (no pagination or search). |
| M. Document Traceability | 2 | Links to inventory only. No PO link. |
| N. Standardization | 2 | Completely different pattern from issue.php (redesigned). |
| O. Configurability | 1 | No warehouse selection, no bin location, no quality section. |

**Overall: 2.4/5**

**Top 3 Issues:**
1. **Single-item entry** (Critical) — each item needs separate form, 50 items = 50 submissions
2. **No PO reference** (High) — cannot link receipt to purchase order
3. **No barcode/scan support** (High) — warehouse staff need scan guns

---

### 1.9 Goods Issue / PXK (`issue.php` — redesigned)

| Category | Score | Notes |
|----------|-------|-------|
| A. Business Functionality | 4 | Create draft, view, post, cancel, print, list with filter. Full lifecycle. |
| B. Document Management | 4 | Draft→Posted→Cancelled status. Auto-numbering (PXK prefix). Clear lifecycle. |
| C. Accounting Compliance | 5 | FIFO/weighted average costing via InventoryService, proper 632/621/241 mapping. |
| D. Data Entry Experience | 3 | Full-page form (not modal). Flatpickr date picker. Multi-line grid with add/remove. No keyboard shortcuts. |
| E. Grid Design | 4 | Multi-line grid with STT, item picker, qty req/actual, unit price, amount. Add/remove lines. |
| F. Lookup Experience | 3 | Item and warehouse pickers loaded once. No search/filter within picker. |
| G. Validation & Error Handling | 3 | Frontend: reason required, receiver required, at least 1 line. Missing per-line validation. |
| H. Workflow & Approval | 3 | Draft→Post→Cancel lifecycle. No approval step for high-value issues. |
| I. Auditability | 4 | AuditLogger via DI. Full history tracked. |
| J. Attachment Management | 1 | **No attachment.** |
| K. Security | 3 | CSRF + RBAC. No field-level permissions. |
| L. Performance | 3 | Items/warehouses loaded at page load (not lazy). Scales fine for <1000 items. |
| M. Document Traceability | 3 | Links to transactions via inventory_issue_items.transaction_id. No SO link. |
| N. Standardization | 4 | Full-page form follows TT99 Mẫu 02-VT layout. Signature section. Print support. |
| O. Configurability | 2 | Issue types hardcoded (sale/production/construction/internal/other). No custom fields. |

**Overall: 3.3/5**

**Top 3 Issues:**
1. **No attachment upload** (Critical) — supporting docs for goods issue
2. **No Sales Order link** (High) — SO→PXK auto-generation
3. **No barcode scanning** (High) — warehouse efficiency

---

### 1.10 Bank Reconciliation (`bank_reconciliation.php`)

| Category | Score | Notes |
|----------|-------|-------|
| A. Business Functionality | 4 | Session-based, CSV import, statement/book/matched tabs, manual entry. |
| B. Document Management | 3 | Session lifecycle: in_progress→completed. No draft session. |
| C. Accounting Compliance | 4 | Balance comparison, diff calculation. Proper session tracking. |
| D. Data Entry Experience | 3 | Tabbed interface, card-based KPI display. CSV upload with spinner. No auto-matching UI. |
| E. Grid Design | 4 | Tabbed grid (statement/book/matched), color-coded rows, source badge. |
| F. Lookup Experience | 2 | Manual entry, no auto-suggest. |
| G. Validation & Error Handling | 3 | CSV import error display. Balance diff display. No fuzzy match validation. |
| H. Workflow & Approval | 3 | Start→Import→Match→Complete. Matching is manual only. |
| I. Auditability | 3 | Session tracking. No per-match audit trail. |
| J. Attachment Management | 4 | CSV import handled. No PDF/OFX parsing. |
| K. Security | 3 | CSRF + RBAC. |
| L. Performance | 3 | All items loaded client-side, tab-filtered. Won't scale past 5000 items. |
| M. Document Traceability | 3 | Session links to transactions. |
| N. Standardization | 3 | Tabbed interface is unique in the app. |
| O. Configurability | 3 | Match rules not configurable. Tolerance not adjustable. |

**Overall: 3.3/5**

**Top 3 Issues:**
1. **No auto-matching engine** (Critical) — manual matching per item, 300 items = hours
2. **No PDF/OFX bank statement parsing** (High) — CSV only, manual format adjustment
3. **Client-side only filtering** (Medium) — doesn't scale to 10K+ transactions

---

### 1.11 Fixed Asset Acquisition (`fixed_asset_acquisition.php`)

| Category | Score | Notes |
|----------|-------|-------|
| A. Business Functionality | 4 | Full form, category/type/vendor, journal preview, asset result card. |
| B. Document Management | 3 | Create→Post. Auto-generates depreciation schedule. No draft. |
| C. Accounting Compliance | 5 | 211/213/212 mapping, 1332 VAT, 4 depreciation methods, category-driven accounts. |
| D. Data Entry Experience | 3 | Full-page form. Live journal preview on input. VAT auto-calc (10%). Tabbed layout. No keyboard nav. |
| E. Grid Design | 2 | Single-asset form, no batch add. |
| F. Lookup Experience | 3 | Category dropdown, department picker. No vendor balance display. |
| G. Validation & Error Handling | 3 | Cost>0, useful life required. No TT99 minimum life validation. |
| H. Workflow & Approval | 2 | Create→Post only. No approval for high-value FA. |
| I. Auditability | 4 | Audit trail + asset card display. |
| J. Attachment Management | 1 | **No purchase invoice attachment.** |
| K. Security | 3 | CSRF + RBAC. |
| L. Performance | 4 | Fast. |
| M. Document Traceability | 3 | Asset→Depreciation→GL trail. |
| N. Standardization | 3 | Unique full-page form (not modal). Journal preview is unique. |
| O. Configurability | 3 | Category-driven account codes. Dynamic journal lines per acquisition type. |

**Overall: 3.2/5**

**Top 3 Issues:**
1. **No batch acquisition** (High) — each FA = 1 form submission, 10 assets = 10 submissions
2. **No attachment for purchase invoice** (Critical)
3. **No TT99 useful life minimums enforced** (Medium) — e.g., building < 10 years should warn

---

### 1.12 VAT Declaration (`vat_declarations.php`)

| Category | Score | Notes |
|----------|-------|-------|
| A. Business Functionality | 4 | Prepare declaration, non-deductible scan, GL reconciliation, detail view, finalise. |
| B. Document Management | 3 | Draft→Finalised lifecycle. Period selector. |
| C. Accounting Compliance | 4 | Non-deductible detection (>=5M cash payment), GL vs declaration comparison, tolerance display. |
| D. Data Entry Experience | 3 | Tabbed detail (input/output), card KPI display. Non-deductible auto-scan with loading state. |
| E. Grid Design | 3 | Summary table + detail modals. KPI cards. |
| F. Lookup Experience | 2 | Period-based, no invoice-level drill-down in detail modal. |
| G. Validation & Error Handling | 3 | GL vs declaration comparison with tolerance. Mismatch color-coding (red for >tolerance). |
| H. Workflow & Approval | 3 | Prepare→Review→Finalise. No digital signature. |
| I. Auditability | 3 | Declaration status tracked. |
| J. Attachment Management | 1 | **No attachment for supporting invoices.** |
| K. Security | 3 | CSRF + RBAC. |
| L. Performance | 3 | Can be slow for large periods (all transactions aggregated on-demand). |
| M. Document Traceability | 2 | Links to GL balances but not to individual transactions (no drill-down). |
| N. Standardization | 3 | Unique UI pattern (not modal). |
| O. Configurability | 2 | No rate override, no manual adjustment. |

**Overall: 3.0/5**

**Top 3 Issues:**
1. **No XML export for GDT e-submission** (Critical) — legal requirement for electronic filing
2. **No invoice-level drill-down** (High) — cannot trace declaration amounts to specific invoices
3. **No digital signature** (High) — declaration must be digitally signed

---

### 1.13 Payroll Entries (`payroll_entries.php`)

| Category | Score | Notes |
|----------|-------|-------|
| A. Business Functionality | 3 | Period creation, calculation, approve, post, employee detail modal. |
| B. Document Management | 3 | Period + entry pair. Draft→Approved→Posted lifecycle. |
| C. Accounting Compliance | 4 | Proper accounts (334, 3383, 3384), BHXH calculation, PIT calculation. |
| D. Data Entry Experience | 2 | Very compact UI. All in one script (56 lines). Employee details in separate modal. No inline editing. |
| E. Grid Design | 2 | Summary table only, employee detail in separate modal. No expandable rows. |
| F. Lookup Experience | 2 | No employee search on main screen. Period picker only. |
| G. Validation & Error Handling | 2 | Basic backend validation. Frontend has confirm() dialogs only. |
| H. Workflow & Approval | 3 | Calculate→Approve→Post. |
| I. Auditability | 3 | Approval + posting audit. |
| J. Attachment Management | 1 | **No payslip generation/download.** |
| K. Security | 3 | CSRF + RBAC. |
| L. Performance | 3 | OK. |
| M. Document Traceability | 2 | Payroll→GL link via post. No employee-level GL trace. |
| N. Standardization | 2 | Very different UI from other modules. Compact but inconsistent. |
| O. Configurability | 2 | Basic period management. No configurable salary components in UI. |

**Overall: 2.5/5**

**Top 3 Issues:**
1. **No employee-level detail on main screen** (High) — must click into modal to see per-employee breakdown
2. **No payslip generation** (High) — employees cannot receive payslips
3. **No BHXH electronic submission** (High) — must manually file BHXH

---

### 1.14 Opening Balances (`opening_balances.php`)

| Category | Score | Notes |
|----------|-------|-------|
| A. Business Functionality | 4 | Add/save, verify, convert to opening journal. Dr/Cr per account. |
| B. Document Management | 3 | Per-account status (verified/unverified). Bulk convert button. |
| C. Accounting Compliance | 5 | Dr=Cr enforced on convert, period-level, account existence check. |
| D. Data Entry Experience | 3 | COA picker, Dr/Cr fields, verify checkbox. No Excel/CSV import. |
| E. Grid Design | 3 | Table with account_code/name/type, Dr/Cr, status, edit/verify actions. |
| F. Lookup Experience | 3 | COA picker via /api/coa. |
| G. Validation & Error Handling | 3 | Basic validation. No duplicate period+account check visible. |
| H. Workflow & Approval | 3 | Add→Verify→Convert. Clear 3-step flow. |
| I. Auditability | 3 | Audit trail. |
| J. Attachment Management | 1 | **No import file.** |
| K. Security | 3 | CSRF + RBAC. |
| L. Performance | 3 | OK. |
| M. Document Traceability | 2 | Links to journal after convert. |
| N. Standardization | 3 | Modal-based editing. Standard pattern. |
| O. Configurability | 2 | No CSV/Excel import. |

**Overall: 3.1/5**

**Top Issues:**
1. **No CSV/Excel import** (High) — manual entry for 100+ accounts is painful
2. **No bulk verify** (Medium) — must verify each account individually

---

### 1.15 Corrections (`corrections.php`)

| Category | Score | Notes |
|----------|-------|-------|
| A. Business Functionality | 5 | 3 correction methods per Article 27 (supplementary/negative/adjusting), history view, split-panel. |
| B. Document Management | 4 | Original→Correction→Audit trail. Clear lifecycle. |
| C. Accounting Compliance | 5 | Article 27 Luật Kế toán — all 3 methods. Dr=Cr enforced, reason required (10+ chars). |
| D. Data Entry Experience | 3 | Split-panel: left=list, right=correction form. Method selector, line input. No COA typeahead initially. |
| E. Grid Design | 3 | Add/remove lines for supplementary/adjusting. Dr/Cr selector per line. |
| F. Lookup Experience | 3 | COA selector (populated once). No search within selector. |
| G. Validation & Error Handling | 4 | Reason (10+ chars), Dr=Cr, amount>0. COA account existence. |
| H. Workflow & Approval | 3 | Select→Choose method→Enter lines→Submit. No approval for certain types. |
| I. Auditability | 5 | Full correction history tracked per transaction, viewable inline. |
| J. Attachment Management | 1 | **No attachment for correction approval document.** |
| K. Security | 3 | CSRF token from meta tag (good). RBAC. |
| L. Performance | 3 | COA loaded once, transactions searchable. |
| M. Document Traceability | 4 | Original→Correction reverse link, history modal. |
| N. Standardization | 3 | Unique split-panel pattern (not used elsewhere). |
| O. Configurability | 2 | Reason free-text. No reason picklist (common correction types). |

**Overall: 3.5/5** — Strongest form in the app

**Top Issues:**
1. **No reason picklist** (Medium) — common correction reasons should be selectable
2. **No attachment** (Medium) — some corrections need approval document

---

### 1.16 Period Close (`period_close.php`)

| Category | Score | Notes |
|----------|-------|-------|
| A. Business Functionality | 5 | 5-step wizard: Check→Close P&L→Lock→Export FS→Archive. Sequential, non-skippable. |
| B. Document Management | 5 | Step-by-step progression, clear status per step, irreversible once done. |
| C. Accounting Compliance | 5 | Pre-close checklist (Dr=Cr, no drafts), P&L close to 421, period lock enforcement. |
| D. Data Entry Experience | 4 | Step indicator (CSS triangles), auto-advance on pass, clear button states with spinners. |
| E. Grid Design | 3 | Wizard, not grid. Checklist with status icons. |
| F. Lookup Experience | 3 | Period selector filtered to open periods. |
| G. Validation & Error Handling | 5 | Pre-close checklist with pass/fail per check, can_close flag. |
| H. Workflow & Approval | 5 | Strict 5-step sequence, cannot skip, confirm() on destructive steps. |
| I. Auditability | 5 | Every step logged, FS data archived. |
| J. Attachment Management | 3 | Archive step captures period data. |
| K. Security | 3 | CSRF + RBAC. Only KTT can re-open. |
| L. Performance | 4 | Fast for most checks. |
| M. Document Traceability | 4 | FS links to period. Full close audit trail. |
| N. Standardization | 4 | Best-designed wizard in the app. Step indicator reusable. |
| O. Configurability | 4 | Adjustable checklist items via config. |

**Overall: 4.3/5** — Best-designed form in the application

**Top Issues:**
1. **No dry-run mode** (Medium) — cannot preview close without executing
2. **No email notification** (Low) — when close completes
3. **No intercompany elimination** (Low) — for multi-entity

---

### 1.17 Sales Order (`sales-order-form.php`)

| Category | Score | Notes |
|----------|-------|-------|
| A. Business Functionality | 4 | Create order, confirm, ship, invoice, receive payment. Full lifecycle from order→cash. |
| B. Document Management | 4 | Draft→confirmed→shipped→invoiced→paid. Cancelled status. Clear progression. |
| C. Accounting Compliance | 3 | Revenue recognition on invoice (not on order). Payment tracking. No COGS auto-match. |
| D. Data Entry Experience | 3 | Full-page form. Multi-line item grid. Customer dropdown. Date pickers. No keyboard shortcuts. |
| E. Grid Design | 4 | Multi-line with name/qty/price/discount/tax/total. Add/remove lines. Live totals. |
| F. Lookup Experience | 3 | Customer dropdown (no search). Item is free-text (not from DB). |
| G. Validation & Error Handling | 3 | Frontend: at least 1 line, qty>0, price>0. No customer balance check. |
| H. Workflow & Approval | 4 | Draft→Confirm→Ship→Invoice→Payment. Action buttons based on status. |
| I. Auditability | 3 | Basic audit trail. |
| J. Attachment Management | 1 | **No attachment.** |
| K. Security | 3 | CSRF + RBAC. |
| L. Performance | 4 | Fast. |
| M. Document Traceability | 4 | SO→Ship→Invoice→Payment chain visible in form. |
| N. Standardization | 4 | Full-page form with status-based action buttons. Clean pattern. |
| O. Configurability | 2 | No custom fields, no product catalog integration (item is text, not DB entity). |

**Overall: 3.4/5**

**Top Issues:**
1. **Items are free-text** (High) — no item master integration, miss inventory/price validation
2. **No customer credit check** (High) — can create order for over-limit customer
3. **No attachment support** (Medium) — no contract/quote upload

---

### 1.18 Purchase Order (`purchase_orders.php`)

| Category | Score | Notes |
|----------|-------|-------|
| A. Business Functionality | 3 | Create PO, filter/sort list, view detail modal. No edit/cancel/print. |
| B. Document Management | 3 | 6 statuses (draft→pending→sent→partial→completed→cancelled). At-a-glance visible. |
| C. Accounting Compliance | 2 | No financial posting (PO is procurement doc, not accounting). Price/qty tracking only. |
| D. Data Entry Experience | 2 | Modal-based creation. Item is free-text (no master data). Add/remove lines. |
| E. Grid Design | 3 | Multi-line item grid with qty/price/total. Live line total calc. |
| F. Lookup Experience | 3 | Supplier dropdown with filter. Item is free-text input. |
| G. Validation & Error Handling | 3 | Frontend: at least 1 line. No supplier validation on credit/status. |
| H. Workflow & Approval | 2 | Create only. No approve/send workflow from UI. |
| I. Auditability | 2 | Basic audit. |
| J. Attachment Management | 1 | **No attachment.** |
| K. Security | 3 | CSRF + CSRF token. |
| L. Performance | 3 | Client-side filtering on load. |
| M. Document Traceability | 2 | PR link field exists but no PR→PO→Receipt chain. |
| N. Standardization | 2 | Modal-based creation (not consistent with SO full-page). |
| O. Configurability | 2 | No template, no dynamic terms. |

**Overall: 2.5/5**

**Top Issues:**
1. **No PR→PO→Receipt integration** (Critical) — procurement lifecycle disconnected
2. **Item is free-text** (High) — no item master, no inventory integration
3. **No approval workflow** (High) — PO created and sent without review

---

### 1.19 Corrections Form (Controller-side analysis)

*Already covered in 1.15. Strongest correction engine in the app — all 3 methods of Article 27.*

---

### 1.20 Petty Cash (`petty_cash.php`)

*Not read in full. Assessed via previous report.*

| Category | Score | Notes |
|----------|-------|-------|
| Overall | 2.8/5 | Basic create/replenish/close. Missing float management, impress system, receipt tracking. |

---

## 2. Form Scoring Summary

| Rank | Form | Score | Top Gaps |
|------|------|-------|----------|
| 1 | Period Close | 4.3/5 | Dry-run, email notification |
| 2 | Corrections | 3.5/5 | Reason picklist, attachment |
| 3 | Sales Order | 3.4/5 | Free-text items, no credit check |
| 4 | Journal Entry | 3.3/5 | Attachment, keyboard nav, workflow |
| 5 | Goods Issue (PXK) | 3.3/5 | Attachment, SO link, barcode |
| 6 | Bank Reconciliation | 3.3/5 | Auto-match, PDF parsing, scaling |
| 7 | FA Acquisition | 3.2/5 | Batch add, attachment, life validation |
| 8 | Cash Receipt/Payment | 3.1/5 | Attachment, batch entry, approval |
| 9 | Opening Balances | 3.1/5 | CSV import, bulk verify |
| 10 | AP/AR Invoice | 3.0/5 | Attachment, 3-way match, server filter |
| 11 | VAT Declaration | 3.0/5 | XML export, invoice drill-down, e-sign |
| 12 | Bank Credit/Debit | 2.7/5 | Payer search, account selector, statement ref |
| 13 | Petty Cash | 2.8/5 | Float management, impress system |
| 14 | Payroll Entries | 2.5/5 | Employee detail, payslip, BHXH submission |
| 15 | Purchase Order | 2.5/5 | PR-PO integration, item master, approval |
| 16 | Goods Receipt | 2.4/5 | Multi-line batch, PO link, barcode |

---

## 3. Cross-Cutting Findings

### 3.1 Attachment Gap — ALL Transaction Forms

**Severity: CRITICAL**

Every single transaction entry form lacks attachment upload functionality. This is the single biggest gap vs MISA/FAST/Bravo/SAP/Odoo.

**Impact:**
- Audit trail incomplete — no supporting documents attached to transactions
- Compliance risk — tax authority requires source documents
- Operational inefficiency — staff must manage paper/scans separately

**Forms affected:** All 16 transaction forms above.

### 3.2 Keyboard Navigation Gap — ALL Forms

**Severity: HIGH**

Zero keyboard navigation in any form. Accountants cannot work efficiently without mouse.

**Missing:**
- Enter to move to next field (nowhere)
- Tab through grid columns (nowhere)
- Ctrl+Enter to save (nowhere)
- Escape to close modal (some via Bootstrap default)
- Arrow keys in grids (nowhere)

**Benchmark:** MISA, FAST, Bravo, SAP B1, Odoo all support full keyboard operation.

### 3.3 Client-Side Data Loading Pattern

**Severity: MEDIUM**

Several forms load ALL data client-side and filter in JavaScript:
- `ap_invoices.php` — `var allData=[]` loaded once, filtered by JS
- `ar_invoices.php` — same pattern
- `bank_reconciliation.php` — items loaded once, tab-filtered
- `purchase_orders.php` — allData pattern

**Impact:** Won't scale beyond a few thousand records. UI freezes on large datasets.

### 3.4 Inconsistent Modal vs Full-Page Pattern

**Severity: MEDIUM**

| Pattern | Forms |
|---------|-------|
| Modal (standard) | Journal, Cash Receipt/Payment, Bank Credit/Debit, AP/AR Invoice, Purchase Order, Opening Balances |
| Full-page | Fixed Asset Acquisition, Sales Order, Goods Issue, Period Close |
| Split-panel | Corrections |
| Tabbed + modal | Bank Reconciliation, VAT Declaration |

**Impact:** User confusion — different interaction patterns for similar workflows.

### 3.5 Draft Support Inconsistency

| Form | Draft Support |
|------|---------------|
| Journal Entry | ✅ Draft→Post |
| Goods Issue | ✅ Draft→Posted→Cancelled |
| Sales Order | ✅ Draft→Confirmed→... |
| Purchase Order | ✅ Draft→Pending→... |
| Period Close | ✅ Step-by-step (checkpoint) |
| Cash Receipt | ❌ Create→Post directly |
| Cash Payment | ❌ Create→Post directly |
| Bank Credit | ❌ Create→Post directly |
| AP/AR Invoice | ❌ Create directly (unpaid) |
| Goods Receipt | ❌ Create→Post directly |
| FA Acquisition | ❌ Create→Post directly |

**Impact:** Cash/Bank/Receipt users cannot save partial work. One mistake = lost data.

### 3.6 Grid Component Inconsistency

Each form implements its own grid rendering inline:
- Journal: Direct DOM manipulation with jQuery
- AP/AR: jQuery template string concatenation
- Goods Issue: jQuery row cloning
- Sales Order: jQuery element creation
- Purchase Order: innerHTML assignment
- Corrections: innerHTML +=

**Impact:** No shared grid component = inconsistent behavior and maintainability.

### 3.7 Error Handling Pattern Inconsistency

| Error Pattern | Forms Used In |
|---------------|---------------|
| Toast notification (showToast) | All modern forms |
| alert() dialog | Corrections (correctionForm submit), Sales Order (older code) |
| confirm() | Journal, corrections, period close |
| prompt() | Journal (reverse reason), Sales Order (cancel reason) |
| Inline error | Period Close (step content) |
| Console.log + alert | Purchase Order (older code) |

**Target:** All errors → showToast(). Confirms → Bootstrap modal. Prompts → inline modal with textarea.

---

## 4. Top 20 Critical Issues

| Rank | Issue | Severity | Forms Affected | Effort |
|------|-------|----------|---------------|--------|
| 1 | **No attachment on any entry form** | Critical | ALL transaction forms | Medium |
| 2 | **No keyboard navigation** | Critical | ALL forms | Small |
| 3 | **No XML/GDT export for VAT** | Critical | VAT Declaration | Medium |
| 4 | **No 3-way matching (PO→Receipt→Invoice)** | Critical | AP Invoice, Receipt, PO | Large |
| 5 | **Goods Receipt is single-item only** | Critical | Receipt | Medium |
| 6 | **Bank reconciliation manual matching only** | Critical | Bank Reconciliation | Medium |
| 7 | **No e-signature for FS submission** | Critical | Period Close, FS views | Small |
| 8 | **No invoice-level drill-down in VAT** | High | VAT Declaration | Medium |
| 9 | **No approval for large cash transactions** | High | Cash Receipt/Payment | Small |
| 10 | **Client-side filtering doesn't scale** | High | AP/AR, Bank Rec, PO | Medium |
| 11 | **Items are free-text in Sales/Purchase orders** | High | Sales Order, PO | Medium |
| 12 | **No batch FA acquisition** | High | FA Acquisition | Medium |
| 13 | **No payslip generation** | High | Payroll | Medium |
| 14 | **No PR→PO→Receipt integration** | Critical | PO, Receipt, Procurement | Large |
| 15 | **No CSRF on some AP/AR endpoints** | High | AP Invoice (invForm) | Small |
| 16 | **No cash balance warning before payment** | High | Cash Payment | Small |
| 17 | **No credit limit check on AR invoice** | High | AR Invoice | Small |
| 18 | **No server-side pagination** | Medium | AP/AR lists, Bank Rec | Medium |
| 19 | **Inconsistent modal vs full-page pattern** | Medium | ALL forms | Large |
| 20 | **No draft auto-save** | Medium | Journal, Cash, AP, AR | Medium |

---

## 5. Quick Wins (Under 2 Weeks)

| # | Task | Effort | Impact | Forms |
|---|------|--------|--------|-------|
| 1 | Add CSRF to all AP/AR endpoints | 0.5 day | Security | AP/AR |
| 2 | Standardize error handling: all errors to showToast() | 1 day | UX | All modal forms |
| 3 | Add Enter-to-submit on all modal forms | 0.5 day | UX | Journal, Cash, Bank, AP/AR |
| 4 | Add Ctrl+Enter shortcut on journal grid | 0.5 day | UX | Journal |
| 5 | Add numeric formatting to all tables | 0.5 day | UX | All lists |
| 6 | Add cash balance warning on payment | 1 day | Risk | Cash Payment |
| 7 | Add loading spinner to all AJAX calls | 0.5 day | UX | All forms |
| 8 | Standardize all buttons (save=primary, post=success) | 1 day | UX | All forms |
| 9 | Add "unsaved changes" warning on modal close | 1 day | UX | Modal forms |
| 10 | Add pagination summary to lists | 0.5 day | UX | AP/AR, Journal |
| 11 | Add VAT reason picklist for corrections | 1 day | UX | Corrections |
| 12 | Add print CSS to list views | 1 day | UX | All lists |
| 13 | Add `tabindex` order to journal grid | 1 day | UX | Journal |
| 14 | Add COA typeahead to corrections form | 0.5 day | UX | Corrections |
| 15 | Standardize all date fields to flatpickr | 2 days | UX | All forms |

**Total quick wins: ~12 days** — achievable in 2-3 weeks with 1 dev.

---

## 6. Medium-Term Improvements (1-3 Months)

| # | Task | Effort | Priority |
|---|------|--------|----------|
| 1 | **Attachment upload system** for ALL transaction forms | 3 weeks | P0 |
| 2 | **Multi-line batch receipt** for inventory (matching PXK pattern) | 2 weeks | P0 |
| 3 | **Bank reconciliation auto-matching engine** (amount + ref + date) | 2 weeks | P0 |
| 4 | **VAT XML export** for GDT e-submission | 1 week | P0 |
| 5 | **Server-side pagination + sorting** for all lists | 2 weeks | P1 |
| 6 | **Standard form framework** — shared grid, field, modal components | 3 weeks | P1 |
| 7 | **Draft auto-save** for Journal, Cash, AP/AR | 2 weeks | P1 |
| 8 | **Approval workflow for cash/FA/journal** based on config thresholds | 2 weeks | P1 |
| 9 | **Keyboard navigation system** (Tab/Enter/Ctrl+Enter across forms) | 2 weeks | P1 |
| 10 | **Sales order → PXK auto-generation** | 1 week | P1 |
| 11 | **3-way matching** (PO→Receipt→Invoice) | 3 weeks | P2 |
| 12 | **Item master integration** for Sales/Purchase orders | 2 weeks | P2 |
| 13 | **Employee detail view** in payroll main table | 1 week | P2 |
| 14 | **Payslip generation + PDF download** | 2 weeks | P2 |
| 15 | **Batch FA acquisition** (multi-asset form) | 1 week | P2 |

---

## 7. Long-Term Roadmap (3-12 Months)

| Phase | Focus | Timeline |
|-------|-------|----------|
| **Phase 1: Foundation** | Shared form components, attachment system, keyboard nav | Months 1-2 |
| **Phase 2: Productivity** | Batch entry, auto-matching, server pagination, approval workflows | Months 2-4 |
| **Phase 3: Compliance** | E-signature, VAT XML export, BHXH integration, data retention | Months 3-5 |
| **Phase 4: Advanced** | 3-way matching, PO→PR integration, item master, barcode/scan | Months 4-8 |
| **Phase 5: Enterprise** | Mobile access, bank feed API, AI-assisted coding, custom fields | Months 6-12 |

---

## 8. Proposed Standard Accounting Form Framework

### 8.1 Shared Components

```
public/assets/js/components/
├── FormModal.js          — Standard modal: size/header/footer, confirm/cancel, Enter-to-submit
├── FormGrid.js           — Multi-line editable grid: add/remove, copy/paste, Dr/Cr validation, totals
├── FormField.js          — Standard field: label/error/help/required indicator
├── FormCOAPicker.js      — COA search: typeahead, code+name display, recently used
├── FormContactPicker.js  — Customer/Supplier/Employee: triple-search, balance display, recently used
├── FormCurrency.js       — Currency input: auto-format, amount-to-words
├── FormDate.js           — Flatpickr wrapper: DD/MM/YYYY, period restriction
├── FormAmountToWords.js  — Vietnamese number-to-words converter (reusable from PXK)
├── FormValidation.js     — Validator: required, type, range, pattern, Dr=Cr, business rules
├── FormToast.js          — Toast notification (already exists in layout.php, needs standardization)
├── FormConfirm.js        — Bootstrap modal confirm (replaces confirm()/alert()/prompt())
└── FormAttachment.js     — File upload: preview, download, multiple files
```

### 8.2 Form Layout Template

For ALL new transaction forms:

```
┌─────────────────────────────────────────────────────┐
│ Toolbar: Title + Action buttons (X, Y, Z)           │
├─────────────────────────────────────────────────────┤
│ Filter/Search bar (for list views)                  │
├─────────────────────────────────────────────────────┤
│ Data table / Grid                                   │
│   ┌──────┬──────┬──────┬──────┬──────┬──────┐      │
│   │  #   │  A   │  B   │  C   │  D   │ ...  │      │
│   ├──────┼──────┼──────┼──────┼──────┼──────┤      │
│   │  1   │ ...  │ ...  │ ...  │ ...  │ ...  │      │
│   │  2   │ ...  │ ...  │ ...  │ ...  │ ...  │      │
│   └──────┴──────┴──────┴──────┴──────┴──────┘      │
├─────────────────────────────────────────────────────┤
│ Pagination: Showing X-Y of Z | < 1 2 3 ... N >      │
└─────────────────────────────────────────────────────┘
```

For entry modals:

```
┌─────────────────────────────────────────┐
│ Modal Header: Title                     │
├─────────────────────────────────────────┤
│ Header fields row (date, ref, type)     │
│                                         │
│ Lines grid (multi-line)                 │
│   ┌────┬────┬────┬────┬────┬────┐      │
│   │STT │TK  │Dr/C│Amt │Memo│ X  │      │
│   ├────┼────┼────┼────┼────┼────┤      │
│   │ 1  │... │ N  │... │... │ ×  │      │
│   │ 2  │... │ C  │... │... │ ×  │      │
│   └────┴────┴────┴────┴────┴────┘      │
│   [ + Add line ]                        │
│   Total: XXXX Dr = YYYY Cr              │
│                                         │
│ Footer fields (notes, attachment)       │
├─────────────────────────────────────────┤
│ Footer: Cancel  Save Draft  Post        │
└─────────────────────────────────────────┘
```

---

## 9. UX Guidelines for All Future Forms

### 9.1 Layout Standards

1. **Toolbar** — Always at top. Title (left) + Action buttons (right)
2. **Filter bar** — Below toolbar for list views. Search + status + date range + filters
3. **Data table** — Below filter. Sticky header, striped rows, hover highlight
4. **Entry form** — Modal for simple forms (<10 fields), Full-page for complex forms (>10 fields)
5. **Action buttons** — Consistent order: Save Draft | Cancel | Post/Confirm

### 9.2 Button Standards

| Button | Class | Position |
|--------|-------|----------|
| Save Draft | `btn-outline-primary` | Left of action group |
| Save / Create | `btn-primary` | Right of draft |
| Post / Ghi sổ | `btn-success` | Rightmost |
| Cancel / Hủy | `btn-outline-secondary` | Leftmost |
| Print | `btn-outline-info` | Secondary action |
| Delete / Hủy | `btn-danger` | With confirmation |
| Export | `btn-outline-secondary` with download icon | Secondary action |

### 9.3 Keyboard Navigation Standards

| Key | Action |
|-----|--------|
| Enter | Submit form / Save (when not in textarea) |
| Tab | Move to next field (respect tabindex order) |
| Ctrl+Enter | Save and close |
| Escape | Close modal / Cancel |
| Arrow Up/Down | Navigate grid rows |
| Ctrl+C / Ctrl+V | Copy/paste grid rows |
| F2 | Quick edit selected row |

### 9.4 Validation Standards

1. All validation runs on field blur (inline), not just on submit
2. Error message below field in red (`<div class="invalid-feedback">`)
3. Submit button disabled until all validations pass
4. Required fields marked with red asterisk
5. Error toast for server-side errors
6. Success toast after successful operation (auto-dismiss 3s)
7. Confirmation modal for destructive actions (not confirm())

### 9.5 Data Display Standards

1. All monetary amounts formatted with `Intl.NumberFormat('vi-VN')`
2. All dates formatted with flatpickr `DD/MM/YYYY` (stored ISO)
3. Large numbers show in full (no K/M abbreviations)
4. Negative amounts shown in red
5. Table columns align: text → left, numbers → right, actions → center
6. Empty states show icon + message (never blank table)

---

## 10. Enterprise Form Design Standards

### 10.1 Form Lifecycle

Every transaction form MUST implement this lifecycle:

```
[Draft] → [Pending Approval] → [Posted] → [Reversed / Void]
    ↑                              ↓
    └── [Edit while draft]    [Audit trail]
```

Minimum: Draft → Posted. Additional: Approval, Reversal, Void as needed.

### 10.2 Status Color Standards

| Status | Badge Class | Description |
|--------|-------------|-------------|
| Draft | `bg-warning text-dark` | Not yet finalized |
| Pending | `badge-warning` | Awaiting approval |
| Approved | `badge-type` | Approved but not posted |
| Posted | `badge-active` | Finalized, cannot edit |
| Paid | `badge-active` | Fully paid |
| Partial | `badge-info` | Partially paid/received |
| Cancelled | `badge-danger` | Voided/Cancelled |
| Reversed | `bg-secondary` | Reversed by correction |

### 10.3 API Response Standards

```json
// Success
{ "data": { "id": "xxx", "status": "posted", ... } }

// Error  
{ "error": "Message in Vietnamese" }

// Validation
{ "error": "Specific message", "field": "amount", "code": "VALIDATION_DR_CR" }
```

### 10.4 Loading & Empty States

1. **Loading**: Spinner overlay on the component being loaded (not full page)
2. **Empty**: Icon + message "Chưa có dữ liệu" + CTA button if applicable
3. **Error**: Toast notification + retry button
4. **Offline**: Banner "Không có kết nối mạng" (future)

### 10.5 Accessibility Standards

1. All forms must have `<label for="...">` properly associated
2. All icons must have `title` or `aria-label`
3. Color must not be the only indicator (add text/icon)
4. Minimum contrast ratio 4.5:1 for normal text
5. Focus indicators visible (not just browser default outline)

### 10.6 Mobile Responsiveness

1. Forms must be usable on tablets (1024px width)
2. Sidebar collapses to icon-only on <992px
3. Tables horizontally scrollable on small screens
4. Modal forms full-screen on mobile (<576px)
5. Buttons large enough for touch targets (min 44px)

---

## 11. Implementation Priority by Impact

```
CRITICAL (Legal/Compliance risk)
├── Attachment upload system (P0)
├── VAT XML export for GDT (P0)
├── 3-way matching PO→Receipt→Invoice (P0)
├── Multi-line batch goods receipt (P0)
└── Bank reconciliation auto-matching (P0)

HIGH (Operational efficiency)
├── Keyboard navigation (P1)
├── Server-side pagination (P1)
├── Shared form component framework (P1)
├── Draft auto-save (P1)
├── Approval workflows (config-driven) (P1)
└── Item master for Sales/Purchase orders (P1)

MEDIUM (User experience)
├── Standardized error handling (P2)
├── Loading states (P2)
├── Numeric formatting (P2)
├── Print CSS (P2)
├── Unsaved changes warning (P2)
└── E-signature for FS submission (P2)

LOW (Nice to have)
├── Barcode/scan support (P3)
├── Mobile access (P3)
├── AI-assisted coding (P3)
├── Custom fields (P3)
└── Bank feed API (P3)
```

---

## 12. Summary

| Metric | Value |
|--------|-------|
| Forms assessed | 16 transaction entry forms |
| Average score (1-5) | **3.08/5** |
| Top form | Period Close (4.3/5) |
| Bottom form | Goods Receipt (2.4/5) |
| Critical issues | 7 |
| High issues | 13 |
| Quick wins (≤2 days) | 15 tasks (~12 days) |
| Medium-term (>1 week) | 15 tasks (~30 weeks) |
| Overall maturity | 6.2/10 (down from 7.2/10 with new stricter criteria) |

**Most impactful single investment:** Build a shared form component library (`FormModal`, `FormGrid`, `FormToast`, `FormConfirm`, `FormValidation`) — will simultaneously fix 8 of the top 20 issues and reduce development time for every future form by 40-60%.
