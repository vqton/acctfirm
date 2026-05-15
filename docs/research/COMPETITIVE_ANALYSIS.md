# Competitive Analysis: Current App vs MISA / Fast / Bravo

**Version:** 1.0
**Date:** 2026-05-15
**Author:** BA Lead

---

## 1. Market Landscape

| Product | Position | Segment | Deployment | Users |
|---|---|---|---|---|
| MISA (AMIS + SME) | #1, 28yr | All sizes | Cloud + On-prem | 250K+ enterprises |
| Fast Accounting | #2, 27yr | SME-Mid | Cloud + On-prem | 65K+ businesses |
| BRAVO 10 ERP | #3, ~25yr | Mid-Large | Cloud + On-prem | Large enterprises |
| **Current App** | New entrant | SME | Web-only (PHP) | Early stage |

---

## 2. Feature Coverage vs Standard (K1-K108)

### Legend: ✅=done  ⚠️=partial  ❌=missing  🟡=not started

| Domain | MISA | Fast | BRAVO | Current | Gap |
|---|---|---|---|---|---|
| COA | ✅ | ✅ | ✅ | ✅ | None |
| Journal Engine | ✅ | ✅ | ✅ | ✅ | None |
| Cash & Bank | ✅ | ✅ | ✅ | ✅ | No e-banking API |
| Inventory | ✅ | ✅ | ✅ | ✅ | No lot/serial, no negative stock warn |
| AP (TK 331) | ✅ | ✅ | ✅ | ✅✅ | Matches Bravo sophistication |
| AR (TK 131) | ✅ | ✅ | ✅ | ✅✅ | Matches Bravo sophistication |
| FS BC 01/02 | ✅ | ✅ | ✅ | ✅ | None |
| FS BC 03/09 | ✅ | ✅ | ✅ | ❌ | **CRITICAL GAP** |
| Period Engine | ✅ | ✅ | ✅ | ✅ | None |
| GL (So Cai) | ✅ | ✅ | ✅ | ⚠️ | Missing subsidiary ledgers, print format |
| RBAC | ✅ | ✅ | ✅ | ✅☑️ | Features exist, not fully wired |
| Audit Log | ✅ | ✅ | ✅ | ✅ | None |
| Multi-currency | ✅ | ✅ | ✅ | ✅ | None |
| Multi-branch | ✅ | ✅ | ✅ | ⚠️ | Entity exists, consolidation not built |

### Go-to-Market Gaps (must-have for Minimum Viable Product)

| Category | Missing Feature | Priority |
|---|---|---|
| **Legal** | BC 03 (Cash Flow Statement) | HIGH |
| **Legal** | BC 09 (Notes to FS) | HIGH |
| **Legal** | GL print with page numbers + signature fields (Art 26.7) | HIGH |
| **Legal** | Correction via strikethrough (Art 27) | HIGH |
| **Legal** | Tax declaration data (VAT, CIT, PIT reports) | HIGH |
| **Core** | E-invoice issuance & processing | HIGH |
| **Core** | Bank statement import + auto-matching | HIGH |
| **Core** | Fixed Asset lifecycle (depreciation, increase, decrease, liquidation) | HIGH |
| **Core** | CCDC amortization (TK 242 allocation) | HIGH |
| **Core** | Dashboard with KPI widgets | MEDIUM |
| **Core** | Subsidiary ledgers (per-customer/per-supplier) | MEDIUM |
| **Core** | Management reports (P&L by department, project) | MEDIUM |
| **UX** | Global search bar | MEDIUM |
| **UX** | Excel import for master data | MEDIUM |

---

## 3. User Journey Comparison

### 3.1 Daily Cash Receipt (Phieu thu)

| Step | MISA | Fast | BRAVO | Current |
|---|---|---|---|---|
| Data entry | AI auto from e-invoice + bank feed | Manual + e-invoice import | Manual + workflow approval | Manual jQuery form |
| Account suggestion | AI suggests credit account | Manual pick | Configurable auto-posting | Manual pick (dropdown) |
| Validation | Real-time Dr=Cr, period check | Real-time | Real-time | Validated server-side |
| Approval | Optional workflow | None | Built-in workflow | None |
| Posting | 1-click | 1-click | 1-click | AJAX POST |
| Audit | Full | Full | Full | Full |

**Gap:** Current app is fully manual - no auto-entry, no approval workflow, no AI suggestions.

### 3.2 Bank Reconciliation

| Step | MISA | Fast | BRAVO | Current |
|---|---|---|---|---|
| Statement import | Auto bank feed (API) | CSV/Excel | CSV/Excel + API | Manual entry only |
| Auto-matching | AI match by amount/date/ref | Amount match | Configurable rules | Manual match |
| Adjusting entries | Auto-generated | Manual | Auto-generated | Manual |
| Report | Built-in | Built-in | Built-in | Built-in |

**Gap:** No bank API, no auto-matching, no CSV import. Manual-only reconciliation slows accountants down.

### 3.3 Period Close

| Step | MISA | Fast | BRAVO | Current |
|---|---|---|---|---|
| Checklist | Auto pre-close checklist | Manual | Workflow-driven | Pre-close checklist ✅ |
| Closing entries | 1-click auto | Semi-auto | Auto | Auto closing entries ✅ |
| FX revaluation | Auto | Auto | Auto | Auto ✅ |
| Period lock | Irreversible lock | Lock | Lock + re-open | Lock + re-open ✅ |

**Gap:** Pre-close checklist exists. Good parity here.

### 3.4 Report Generation

| Step | MISA | Fast | BRAVO | Current |
|---|---|---|---|---|
| FS | Auto (all B01-B09) | Auto | Auto | BC 01/02 only |
| Management reports | 200+ built-in | Many built-in | Custom report designer | None |
| Dashboard | KPI widgets + charts | Simple | Customizable BI | None |
| Export | Excel/PDF/CSV | Excel/PDF | All formats | Basic |
| Scheduling | Auto email | None | Auto | None |

**Gap:** Reports are the biggest weakness. No BC 03/09, no management reports, no dashboard, no scheduling.

---

## 4. Data Flow Comparison

### Current App Flow:

```
User Input (jQuery form) 
  → AJAX POST 
    → Controller 
      → Service (CashService, InventoryService, etc.)
        → JournalService::postEntry() 
          → Transaction + LedgerEntry + Account balance
            → AuditLogger
```

### MISA Flow:

```
Auto Entry (AI/e-invoice/bank/OCR)
Manual Entry (form)
  → Approval Workflow (if configured)
    → Auto-posting engine
      → GL + Sub-ledgers
        → Audit trail
          → Live dashboard update
```

### BRAVO Flow:

```
Entry (any channel: Web/Mobile/API/E-invoice)
  → Customizable Workflow (approval chain)
    → Auto-posting (configurable templates)
      → GL + Sub-ledgers + Cost allocation
        → Audit + BI dashboards
```

**Key Architecture Differences:**

| Aspect | Current App | MISA | BRAVO |
|---|---|---|---|
| Entry channels | Web only | Web/Mobile/API/AI/OCR | Web/Mobile/Win/API |
| Auto-entry | None | AI from e-invoice/bank | Configurable templates |
| Approval | None | Optional | Required |
| Posting engine | JournalService | Auto-posting engine | Configurable auto-posting |
| Sub-ledger sync | Manual per service | Auto to GL | Auto to GL |
| Dashboard | None | Live widgets | Customizable BI |
| Data pipeline | Monolithic PHP | Microservices | 3-tier .NET |

---

## 5. UX/UI Comparison

| Aspect | MISA | Fast | BRAVO | Current | Verdict |
|---|---|---|---|---|---|
| Layout | Top bar + Left dark sidebar + Content | Top bar + Left sidebar | Top bar + Left sidebar (tree) | Top bar + Left dark sidebar + Content | ✅ Good match |
| Dashboard | KPI widgets, cash flow, AR/AP aging | Simple KPIs | Customizable | None | ❌ Must add |
| Forms | Modal-based | Modal + tab | Modal customizable | Modal-based | ✅ Good |
| Data entry | AI-assisted, auto-complete | Keyboard shortcuts | Configurable forms | Manual jQuery | ❌ Needs auto-entry pipeline |
| Tables | Striped, right-align numbers, filter | Similar | Similar | Striped, right-align ✅ | ✅ Good |
| Navigation | Collapsible sections with icons | Flat list | Tree view | Collapsible sections ✅ | ✅ Good |
| Mobile | Full app (iOS/Android) | Viewer + approval | Approval + reports | None | ❌ Out of scope now |
| Search | Global search bar | Basic | Advanced | None | ❌ Add global search |
| Notifications | Bell icon, in-app + email | In-app | Smart alerts | Toast only | ⚠️ Enhance |
| Theme | Fixed dark sidebar | Fixed | Customizable | Dark sidebar ✅ | ✅ Good |
| Responsive | Full responsive | Partial | Full | Bootstrap 5 responsive ✅ | ✅ Good |

---

## 6. Workflow Comparison

### Current App: Function-driven (no workflow)

```
User initiates action → System executes → Done
No approval steps, no notification chain, no document routing
```

### MISA: Configurable approval

```
Document created → Optional approval chain → Auto-posting → Notify
Supports: manager approval, multi-level, conditional routing
```

### BRAVO: Built-in workflow engine

```
Document created → Custom workflow (user-defined) → Auto-posting
Supports: parallel approval, condition branches, deadline alerts
```

**Gap:** Current app has zero workflow/approval. Every action is immediate and irreversible post-posting. For real-world use, at minimum payment approval workflow is needed.

---

## 7. Process Standardization

### Current App Strengths (keep):
- Double-entry journal engine with Dr=Cr validation
- Period guard (no posting to closed periods)
- Voucher auto-numbering
- Audit logging on critical operations
- COA control account enforcement
- Detail-account-only posting rule

### Current App Weaknesses (fix):
- No transaction wrapping for multi-step ops
- No workflow/approval for payments
- No bank statement import pipeline
- No e-invoice integration
- No tax declaration data prep
- No FA lifecycle
- No CCDC amortization

---

## 8. Smartest Approach: Recommendation

### Strategy: MISA-inspired modular growth

**Why MISA pattern fits best:**
1. Current app already mirrors MISA's left sidebar + modal form UX pattern
2. MISA targets same SME segment
3. MISA's modular architecture (40+ apps) matches PHP service-oriented structure
4. MISA's AI/auto-entry can be implemented later as a data pipeline layer

### Phase 0 — Foundation Fixes (NOW)

```
Priority 1 — Security:
  [ ] Replace eval() in FsService with safe arithmetic parser
  [ ] Fix SQLi in CashService (prepared statements)
  [ ] Add session_regenerate_id() after login
  [ ] Fix path traversal in index.php
  [ ] Inject PDO via constructor (remove reflection hack)

Priority 2 — Architecture:
  [ ] Wrap multi-step ops in DB transactions (JournalService, InventoryService, CashService)
  [ ] Extract generic CRUD trait (eliminate 12 duplicate controllers)
  [ ] Fix N+1 queries in PDOTransactionRepository (JOIN instead of separate queries)
  [ ] Delete dead AccountingService
```

### Phase 1 — Legal Compliance (Month 1)

```
  [ ] Build BC 03 (Cash Flow Statement)
  [ ] Build BC 09 (Notes to FS)
  [ ] Build subsidiary ledgers (so chi tiet per-customer/per-supplier)
  [ ] Add GL print with page numbering + signature fields
  [ ] Add 10-year retention tracking
```

### Phase 2 — Core Module Expansion (Month 2-3)

```
  [ ] Fixed Asset module (TK 211/213/214):
      - Asset registration (increase)
      - Depreciation calculation + posting
      - Asset decrease / transfer / liquidation
  
  [ ] CCDC module (TK 242/153):
      - Tool registration
      - Multi-period allocation
      - Amortization schedule
  
  [ ] Dashboard (first version):
      - Cash position (111+112 balance)
      - AR/AP aging summary
      - Revenue/expense trend
      - Quick links to common actions
```

### Phase 3 — Data Automation Pipeline (Month 3-4)

```
  [ ] Build data import engine:
      - Bank statement CSV/MT940 import
      - Auto-matching engine (amount + date + ref)
      - Excel import for master data
  
  [ ] E-invoice foundation:
      - Invoice receipt from PDF/XML
      - Data extraction + auto-posting
      - Integration API for e-invoice services
```

### Phase 4 — Tax & Reporting (Month 4-5)

```
  [ ] Tax declaration data prep:
      - VAT input/output summary by period
      - CIT calculation
      - PIT calculation
  
  [ ] Management reports:
      - P&L by department / project
      - Expense analysis
      - Cash flow analysis
  
  [ ] Report export:
      - PDF export with proper formatting
      - Excel export
```

### Phase 5 — Advanced (Month 6+)

```
  [ ] Approval workflow engine for payments
  [ ] Global search bar in layout
  [ ] E-banking API integration
  [ ] Mobile app (approval + reports)
  [ ] Payroll module
  [ ] Production costing
```

---

## 9. Architecture Decision Notes

### Keep (Current architecture is sound):
- PDO with prepared statements (after SQLi fix)
- Service → Repository pattern with interfaces
- JournalService as central posting engine
- Bootstrap 5 + jQuery frontend
- Left sidebar navigation
- Modal forms for create/edit
- JSON API responses
- AuditLogger pattern

### Replace/Refactor:
- `eval()` in FsService → safe arithmetic expression parser
- Reflection-based PDO access → constructor injection
- 12 duplicate CRUD controllers → generic CrudController trait
- Scattered transaction handling → beginTransaction/commit/rollback wrapper
- Manual account dropdowns → AJAX searchable select2

### Add:
- Data import pipeline (CSV/MT940/XML)
- Dashboard service + widgets
- Approval workflow state machine
- Report generator service
- E-invoice adapter layer

---

## 10. UX Roadmap Priority

```
NOW:   Add loading states to all AJAX operations
NOW:   Add confirmation dialogs for destructive actions
M1:    Dashboard with KPIs
M1:    Export to PDF for GL and reports
M2:    Global search bar in header
M2:    Select2 for account/customer/supplier dropdowns
M3:    In-app notifications (toast + bell icon)
M3:    Responsive table improvements for mobile
M4:    Dark/light theme toggle
M5:    Keyboard shortcuts for common actions
```

---

## 11. Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Security vulns (eval, SQLi) exploited | Medium | Critical | Fix Phase 0 immediately |
| Circular 99 non-compliance | Low | High | BC 03/09 must ship M1 |
| Data loss from untransacted ops | Medium | High | Add DB transactions Phase 0 |
| Slow performance from N+1 queries | High | Medium | Fix with JOIN in Phase 0 |
| User adoption low due to no dashboard | Medium | Medium | Dashboard in Phase 2 |
| Missing e-invoice = non-starter | High | Critical | Integration Phase 3 |
