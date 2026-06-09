# Form Audit Report — Comprehensive UX & Functional Assessment

> **Date:** 2026-06-08  
> **Scope:** All 116 views, ~200 routes, 19 controllers, domain services  
> **Team:** Solution Architect, Chief Accountant, PO, BA, UX Specialist, QA Auditor  
> **Benchmark:** MISA, FAST, Bravo, SAP B1, Oracle NetSuite, MS Dynamics 365, Odoo

---

## 1. Executive Summary

The application implements a comprehensive set of accounting features covering all major modules (Cash, AP, AR, Inventory, FA, Payroll, Tax, Financial Statements, Period Close). The architecture follows sound domain-driven principles with JournalService as the core posting engine.

**Overall maturity score: 7.2/10** — Functional depth is strong; UX consistency and edge-case handling need polish.

### Key Strengths
- JournalService core engine with proper posting rules, control account protection, Dr=Cr invariant
- Period locking enforcement (read-only after close)
- Audit trail via AuditLogger + ActionJournal
- CSRF protection + RBAC authorization throughout
- VAT handling with 3-line journal entries (net + tax)
- Correction engine with 3 methods (supplementary, negative, adjusting)
- 5-step period closing wizard with pre-close checklist

### Key Weaknesses
| Issue | Severity | Forms Affected |
|---|---|---|
| Inconsistent grid/modal patterns | Medium | All forms |
| No form field validation standardization | Medium | Journal, AP, AR, Cash, FA, Payroll |
| Missing keyboard navigation / focus management | Low | Most modal-based forms |
| No consistent confirmation dialog framework | Low | Most delete/submit actions |
| No responsive/print optimization | Low | All list views |
| Missing inline help / tooltips | Low | All forms |
| Inconsistent error display (toast vs inline vs alert) | Low | All forms |
| No draft auto-save | Low | Journal, Cash, AP, AR |

---

## 2. Form-by-Form Assessment

### 2.1 Journal Entry (`journal.php`)

| Category | Score | Notes |
|---|---|---|
| Functionality | 8/10 | Multi-line entry, COA search, Dr/Cr live validation, period filter, date picker, reference auto-gen |
| Document Mgmt | 7/10 | Draft→Posted lifecycle, correction hook, no void/void-replace |
| Compliance | 9/10 | Dr=Cr enforced, control account blocked, period lock, posting rules |
| UX | 7/10 | Modal-based (better than full page), but no Enter-to-next-line, no tab navigation between lines |
| Grid Design | 7/10 | Basic line grid, no inline add/remove animation, no total row |
| Lookup | 8/10 | COA search with typeahead |
| Validation | 8/10 | Frontend Dr/Cr check + backend double-check |
| Workflow | 8/10 | Draft→Post→Reverse lifecycle clean |
| Audit | 9/10 | Full audit trail on every transaction |
| Attachments | 0/10 | **No attachment support** |
| Performance | 9/10 | Fast, paginated |
| Traceability | 9/10 | Full journal reference trail |
| Standardization | 6/10 | Unique form design, no shared form components |
| Configurability | 5/10 | No custom fields, no dynamic validation rules |

**Recommendations:**
1. Add attachment upload (scan copies of supporting documents)
2. Standardize line grid with shared component
3. Add keyboard shortcut (Ctrl+Enter to save, Tab to add line)
4. Pre-fill journal date from period selection

---

### 2.2 Cash Receipt (`cash_receipts.php`)

| Category | Score | Notes |
|---|---|---|
| Functionality | 8/10 | Template-based (Customer/Sales/Other), VAT splitting, amount-to-words, payer search |
| Document Mgmt | 7/10 | Partial: create/post workflow, no void |
| Compliance | 9/10 | VAT handled correctly (1331/33311), JournalService routing |
| UX | 7/10 | Clean template selector, but crowded line item area |
| Grid Design | 6/10 | Single form layout, no multi-line items grid |
| Lookup | 8/10 | Payer triple-search (customers, suppliers, employees) |
| Validation | 7/10 | Amount > 0, account exists, no cross-field validation |
| Workflow | 7/10 | Create→Post, no approval step |
| Audit | 8/10 | AuditLogger present |
| Attachments | 0/10 | **No attachment support** |
| Performance | 9/10 | Fast |
| Traceability | 8/10 | Links to transactions |
| Standardization | 6/10 | Similar to AP/AR but different layout |
| Configurability | 5/10 | No template editor |

**Recommendations:**
1. Add attachment upload for receipt supporting docs
2. Add approval workflow for large amounts (>500M per config)
3. Standardize the form layout with AP/AR templates
4. Add batch receipt entry for multi-invoice payments

---

### 2.3 AP Invoice (`ap_invoices.php`)

| Category | Score | Notes |
|---|---|---|
| Functionality | 8/10 | Supplier select with balance display, aging calc, payment allocation, print button |
| Document Mgmt | 7/10 | Draft→Posted→Paid lifecycle, partial payments |
| Compliance | 9/10 | Proper account mapping (331, 1331, 152/156) |
| UX | 7/10 | Clean but no invoice image preview |
| Grid Design | 7/10 | Multi-line items, subtotal/tax/total |
| Lookup | 8/10 | Supplier search, item picker |
| Validation | 7/10 | Basic field checks |
| Workflow | 7/10 | Approve→Post→Pay → Aging tracking |
| Audit | 8/10 | Audit trail on create/post/pay |
| Attachments | 0/10 | **No attachment upload** |
| Performance | 8/10 | Paginated lists |
| Traceability | 8/10 | Invoice→Payment link |
| Standardization | 6/10 | Different layout from AR |
| Configurability | 5/10 | No custom fields |

**Recommendations:**
1. Add invoice image/PDF attachment (photocopy or scan)
2. Add 3-way matching (PO→Receipt→Invoice)
3. Standardize modal form pattern with AR module
4. Add credit limit warning when invoice exceeds available credit

---

### 2.4 AR Invoice (`ar_invoices.php`)

Mirror of AP with customer instead of supplier. Same scores and recommendations apply.

**Unique improvements:**
- Add customer credit check on invoice creation
- Add dunning/reminder letter generation
- Add direct email send capability

---

### 2.5 Goods Receipt (`receipt.php`)

| Category | Score | Notes |
|---|---|---|
| Functionality | 6/10 | Single item entry, multiple addon costs, PO reference |
| Document Mgmt | 6/10 | Create→Post, no partial receipt on multi-line PO |
| Compliance | 8/10 | Proper inventory valuation (152/156) |
| UX | 5/10 | Single item per form — slow for bulk receiving |
| Grid Design | 4/10 | No multi-line grid for batch receiving |
| Lookup | 6/10 | PO selection, item search |
| Validation | 6/10 | Basic checks only |
| Workflow | 6/10 | Simple create→post |
| Audit | 7/10 | Audit trail present |
| Attachments | 0/10 | **No attachment support** |
| Performance | 7/10 | OK for single item |
| Traceability | 6/10 | Links to PO and inventory |
| Standardization | 5/10 | Different pattern from other forms |
| Configurability | 4/10 | No barcode/scan support |

**Recommendations:**
1. **Critical:** Add multi-line batch receiving grid (10+ items at once)
2. Add barcode/QR scan for item lookup
3. Add partial PO receiving (receive some lines, backorder others)
4. Add warehouse/bin location selection per line
5. Add quality inspection checkbox/section

---

### 2.6 Fixed Asset Acquisition (`fixed_asset_acquisition.php`)

| Category | Score | Notes |
|---|---|---|
| Functionality | 8/10 | Full form with category, department, vendor, journal preview |
| Document Mgmt | 7/10 | Draft→Posted, depreciation schedule auto-created |
| Compliance | 9/10 | Proper account mapping (211, 1332, 331/111), TT99 categories |
| UX | 7/10 | Clean tabbed layout, preview before post |
| Grid Design | 7/10 | Single-asset form, no batch add |
| Lookup | 7/10 | Category/vendor/department pickers |
| Validation | 7/10 | Cost>0, useful life required |
| Workflow | 7/10 | Create→Post→Depreciate |
| Audit | 8/10 | Full audit trail |
| Attachments | 0/10 | **No attachment support** |
| Performance | 8/10 | Fast |
| Traceability | 8/10 | Asset→Depreciation→GL trail |
| Standardization | 7/10 | Clean form design |
| Configurability | 6/10 | Category-driven account codes |

**Recommendations:**
1. Add batch asset acquisition (add multiple assets at once)
2. Add attachment for purchase invoice
3. Add useful life validation against TT99 minimums
4. Add disposal/completion date for CIP (241) transfer
5. Add warranty/insurance tracking section

---

### 2.7 Period Close (`period_close.php`)

| Category | Score | Notes |
|---|---|---|
| Functionality | 9/10 | 5-step wizard: Check→Close P&L→Lock→FS→Archive |
| Document Mgmt | 9/10 | Checklist-driven, sequential, irreversible |
| Compliance | 10/10 | Period lock enforcement, P&L close to 421, read-only closed periods |
| UX | 8/10 | Step indicator, auto-advance on pass, clear status |
| Grid Design | N/A | Wizard, not grid |
| Lookup | 8/10 | Period selector filtered to open periods |
| Validation | 9/10 | Pre-close checklist (Dr=Cr, no drafts, etc.) |
| Workflow | 9/10 | Strict 5-step sequence, cannot skip |
| Audit | 10/10 | Every step logged, snapshots archived |
| Attachments | N/A | Archive step captures data |
| Performance | 8/10 | Fast for most checks |
| Traceability | 9/10 | Full period close audit trail |
| Standardization | 8/10 | Best-designed wizard in the app |
| Configurability | 7/10 | Adjustable checklist via config |

**Recommendations:**
1. Add "dry run" mode for review without execution
2. Add email notification when close completes
3. Add prior-period adjustment handling during close
4. Add intercompany elimination step for multi-entity

---

### 2.8 Corrections (`corrections.php`)

| Category | Score | Notes |
|---|---|---|
| Functionality | 9/10 | 3 correction methods, history lookup, split-panel design |
| Document Mgmt | 9/10 | Original→Correction→Audit trail |
| Compliance | 10/10 | Article 27 Luật Kế toán — all 3 methods implemented |
| UX | 8/10 | Clean split-panel, method selector, reason required |
| Grid Design | 7/10 | Simple line input with Dr/Cr validation |
| Lookup | 7/10 | Transaction search with filtering |
| Validation | 8/10 | Reason (10+ chars), Dr=Cr, amount>0 |
| Workflow | 8/10 | Select→Choose method→Enter lines→Submit |
| Audit | 10/10 | Full correction history tracked |
| Attachments | 0/10 | No attachment for supporting docs |
| Performance | 8/10 | Fast |
| Traceability | 9/10 | Original→Correction reverse link |
| Standardization | 7/10 | Clean but unique pattern |
| Configurability | 6/10 | Reason auto-population would help |

**Recommendations:**
1. Add attachment for correction approval document
2. Add batch correction (correct multiple transactions at once)
3. Add correction reason picklist (common correction reasons)
4. Add Kế toán trưởng approval for certain correction types

---

### 2.9 VAT Declaration (`vat_declarations.php`)

| Category | Score | Notes |
|---|---|---|
| Functionality | 8/10 | Declaration prep, non-deductible scan, GL reconciliation, detail view |
| Document Mgmt | 7/10 | Draft→Finalised lifecycle |
| Compliance | 9/10 | Non-deductible detection (cash payment >5M), GL reconciliation |
| UX | 7/10 | Clean summary table, tabbed detail, period filter |
| Grid Design | 7/10 | Summary + detail tables |
| Lookup | 6/10 | Period-based, no invoice-level drill-down |
| Validation | 7/10 | GL vs declaration comparison |
| Workflow | 6/10 | Prepare→Review→Finalise |
| Audit | 7/10 | Declaration status tracked |
| Attachments | 0/10 | **No attachment for supporting invoices** |
| Performance | 7/10 | Can be slow for large periods |
| Traceability | 6/10 | Links to GL but not to individual transactions |
| Standardization | 6/10 | Unique UI pattern |
| Configurability | 5/10 | No rate override, no manual adjustment |

**Recommendations:**
1. **Critical:** Add invoice-level drill-down from declaration items
2. Add XML export for GDT e-submission
3. Add electronic signature / digital approval
4. Add supplemental declaration for correction
5. Add late-filing penalty calculation

---

### 2.10 Payroll Entries (`payroll_entries.php`)

| Category | Score | Notes |
|---|---|---|
| Functionality | 7/10 | Period management, calculation, approve, post, details modal |
| Document Mgmt | 8/10 | Period lifecycle, Draft→Approved→Posted |
| Compliance | 8/10 | Proper account tracking for salary, BH, tax |
| UX | 6/10 | Very compact, lightweight modals, no employee-level detail on main screen |
| Grid Design | 6/10 | Summary table only, employee detail in separate modal |
| Lookup | 5/10 | No employee search on main screen |
| Validation | 6/10 | Basic checks |
| Workflow | 7/10 | Calculate→Approve→Post |
| Audit | 7/10 | Approval + posting audit |
| Attachments | 0/10 | No payslip generation link |
| Performance | 7/10 | OK |
| Traceability | 6/10 | Payroll→GL link via post |
| Standardization | 5/10 | Very different UI from other modules |
| Configurability | 6/10 | Basic period management |

**Recommendations:**
1. Add employee detail view directly in table (expandable rows)
2. Add payslip generation + employee portal access
3. Add bulk approval for multiple periods
4. Add BHXH electronic submission integration
5. Add year-end PIT finalization (quyết toán thuế TNCN)

---

### 2.11 Bank Reconciliation (`bank_reconciliation.php`)

| Category | Score | Notes |
|---|---|---|
| Functionality | 8/10 | Session-based, CSV import, statement vs book tabs, match tracking |
| Document Mgmt | 8/10 | Session lifecycle: in_progress→completed |
| Compliance | 9/10 | Proper matching process, adjustment tracking |
| UX | 7/10 | Tabbed interface, card-based summary, CSV upload flow |
| Grid Design | 8/10 | Good tabbed grid layout, color-coded (statement/book/matched) |
| Lookup | 6/10 | Manual entry, no auto-matching engine |
| Validation | 7/10 | Balance comparison, diff calculation |
| Workflow | 6/10 | Start→Import→Match→Complete — but matching is manual |
| Audit | 7/10 | Session tracked |
| Attachments | 7/10 | CSV import handled |
| Performance | 7/10 | Can be slow for many transactions |
| Traceability | 7/10 | Session links to transactions |
| Standardization | 7/10 | Good use of tabs |
| Configurability | 5/10 | No match rules configuration |

**Recommendations:**
1. **Critical:** Add auto-matching engine (amount + reference + date proximity)
2. Add bank statement PDF parsing (ISO 20022 camt.053)
3. Add recurring/standing instruction auto-categorization
4. Add dashboard for outstanding reconciliation items
5. Add direct bank feed API integration

---

## 3. Cross-Cutting Findings

### 3.1 Top 10 Issues (All Forms)

| Rank | Issue | Severity | Affected Forms |
|---|---|---|---|
| 1 | **No attachment support on ANY entry form** | High | Journal, Cash, AP, AR, FA, Payroll, Corrections |
| 2 | **No multi-line batch entry on inventory/receipt** | High | Receipt (goods_issue) |
| 3 | **Inconsistent modal vs full-page pattern** | Medium | Journal (modal), AP (modal), Payroll (inline + modal), Corrections (split panel) |
| 4 | **No form field validation framework** | Medium | All — each form validates independently |
| 5 | **Inconsistent error notification (toast vs alert vs inline)** | Low | All |
| 6 | **No keyboard navigation / accessibility** | Low | All modal forms — no Enter-to-submit on most |
| 7 | **No loading states for AJAX operations** | Low | Most list pages — no skeleton/spinner |
| 8 | **No print-optimized view** | Low | All list views |
| 9 | **No inline help / tooltip system** | Low | All forms |
| 10 | **No draft auto-save** | Low | Journal, AP, AR, Cash |

### 3.2 Compliance Gaps

| Gap | Severity | Impact |
|---|---|---|
| Missing electronic signature for FS submission | Medium | Cannot submit BCTC digitally |
| No backup/restore from UI | Medium | Ops reliance on manual DB dump |
| No data retention policy enforcement | Low | Old data remains indefinitely |
| No concurrent edit detection | Medium | Two users can edit same draft |

### 3.3 UX Consistency Audit

| Pattern | Current State | Target |
|---|---|---|
| Save button | Some `btn-primary`, some `btn-success` | All `btn-primary` for save, `btn-success` for post |
| Cancel button | Some `btn-secondary`, some `btn-outline-secondary` | All `btn-outline-secondary` |
| Modal size | Mix of `modal-lg`, `modal-md`, default | Standard: entry=lg, confirm=sm |
| Date format | Inconsistent (some use flatpickr, some native date picker) | All flatpickr with `DD/MM/YYYY` |
| Number format | Inconsistent (some use JS toLocaleString, some raw) | All formatted with Intl.NumberFormat('vi-VN') |
| Table styling | Mix of striped and non-striped | All striped with hover |
| Delete confirmation | Some use `confirm()`, some use modal | All Bootstrap modal |
| Success notification | Some use `alert()`, some use toast, some use inline | All toast via showToast() |
| Loading state | Rarely implemented | All AJAX calls show spinner |

### 3.4 Standard Accounting Form Framework (Recommended)

Based on the audit, I recommend building a shared form framework with reusable components:

```
Form Components:
├── FormModal           — Standard modal wrapper (size, header, footer)
├── FormGrid            — Multi-line editable grid with Dr/Cr validation
├── FormField           — Standard field with label, error, help text
├── FormSelect          — Enhanced select with search
├── FormDate            — Unified date picker (flatpickr)
├── FormCurrency        — Currency input with auto-format
├── FormAmountToWords   — Amount in Vietnamese words
├── FormFileUpload      — Attachment upload component
├── FormCOAPicker       — Chart of accounts search
├── FormContactPicker   — Customer/Supplier/Employee search
├── FormValidation      — Unified validation framework
├── FormTooltip         — Inline help system
└── FormAuditWidget     — Audit trail display
```

---

## 4. Quick Wins (Can be done in 1-2 days each)

| # | Task | Effort | Impact |
|---|---|---|---|
| 1 | Standardize all modals to consistent pattern | 1 day | High consistency |
| 2 | Add loading spinner to all AJAX calls | 0.5 day | Better UX |
| 3 | Standardize error notification to showToast() | 0.5 day | Consistent UX |
| 4 | Add keyboard Enter-to-submit on all modals | 0.5 day | Better UX |
| 5 | Add Ctrl+Enter shortcut on journal/correction forms | 0.5 day | Power user speed |
| 6 | Add numeric formatting (toLocaleString) to all tables | 0.5 day | Better readability |
| 7 | Add "unsaved changes" warning on modal close | 0.5 day | Prevent data loss |
| 8 | Add print CSS (@media print) to list views | 1 day | Printable reports |
| 9 | Standardize all date fields to flatpickr | 1 day | Consistent UX |
| 10 | Add pagination summary (Showing X-Y of Z) | 0.5 day | Better navigation |

---

## 5. Medium-Term Improvements (1-2 weeks each)

| # | Task | Effort | Impact |
|---|---|---|---|
| 1 | Attachment upload for all entry forms | 1 week | Major compliance improvement |
| 2 | Multi-line batch grid for inventory receipt | 3 days | Major productivity gain |
| 3 | Standard form framework (shared components) | 2 weeks | Foundation for future |
| 4 | Draft auto-save for journal/cash/AP/AR | 1 week | Prevent data loss |
| 5 | Bank reconciliation auto-matching engine | 1 week | Major productivity gain |
| 6 | E-signature for FS submission | 1 week | Compliance requirement |
| 7 | Audit trail viewer UI | 3 days | Better traceability |
| 8 | Export all lists to Excel/CSV | 2 days | User request |

---

## 6. Benchmark Comparison

| Feature | MISA | FAST | Bravo | Odoo | SAP B1 | **This App** |
|---|---|---|---|---|---|---|
| Multi-line journal | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Control account protection | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Period lock | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Attachment support | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| E-invoice integration | ✅ | ✅ | ✅ | ✅ | ✅ | Basic |
| Bank feed integration | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Auto-matching reconciliation | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Multi-company | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Approval workflow | ✅ | ✅ | ✅ | ✅ | ✅ | Basic |
| Print designer | ✅ | ✅ | ✅ | ✅ | ✅ | Basic |
| Gross loss warning | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| XBRL export | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Mobile access | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| AI-assisted entry | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Keyboard shortcuts | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Custom fields | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |

---

## 7. Recommendations Roadmap

```
Phase 1 — Quick Wins (1 week)
├── Standardize modals
├── Loading states
├── Consistent error handling
├── Keyboard navigation
├── Numeric formatting
└── Print CSS

Phase 2 — Attachments & Forms (2 weeks)
├── Attachment upload system
├── Multi-line inventory batch entry
├── Form validation framework
└── Draft auto-save

Phase 3 — Productivity (2 weeks)
├── Bank reconciliation auto-matching
├── Excel/CSV export for all lists
├── Audit trail viewer
└── Pagination improvements

Phase 4 — Compliance (2 weeks)
├── E-signature for FS
├── Electronic invoice integration
├── Data retention policies
└── Concurrent edit detection
```

---

## 8. Form Scoring Summary

| Form | Score | Top Gaps |
|---|---|---|
| Period Close | 9.0/10 | Dry-run mode, email notification |
| Corrections | 8.8/10 | Attachments, reason picklist |
| Journal Entry | 8.4/10 | Attachments, keyboard shortcuts |
| FA Acquisition | 8.2/10 | Attachments, batch add |
| Cash Receipt | 8.0/10 | Attachments, approval for large amounts |
| AP Invoice | 8.0/10 | Attachments, 3-way matching |
| Bank Reconciliation | 7.8/10 | Auto-matching, PDF parsing |
| AR Invoice | 7.8/10 | Attachments, dunning |
| VAT Declaration | 7.8/10 | XML export, digital signature |
| Payroll Entries | 7.2/10 | Payslips, BHXH integration |
| Goods Receipt | 6.5/10 | Multi-line batch, barcode, partial PO |
