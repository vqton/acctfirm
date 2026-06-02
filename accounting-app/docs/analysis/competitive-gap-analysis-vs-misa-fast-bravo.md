# Competitive Gap Analysis: Bookwise vs MISA, FAST, BRAVO

> **Author:** Lead BA (20yr) + Chief Accountant (20yr)
> **Date:** 2026-06-02
> **Scope:** Module-by-module comparison against 3 market leaders
> **Methodology:** Public docs, user reviews, demo experience, regulatory compliance

---

## Executive Summary

| Dimension | MISA | FAST | BRAVO | Bookwise (Current) |
|---|---|---|---|---|
| Market position | #1 SME (70%+ share) | #2 mid-market | #3 large enterprise | Custom build |
| Modules | 11 standard | 8-15 depending on edition | Full ERP (20+) | ~10 core modules |
| Deployment | Cloud + Desktop | Cloud + Desktop | On-premise | Web (PHP built-in) |
| TT 99 compliance | ✅ Full | ✅ Full | ✅ Full | ✅ Core (gaps in forms) |
| E-invoice integration | ✅ Built-in (MISA meInvoice) | ✅ Via 3rd party | ✅ Via 3rd party | ✅ Via VNPT gateway |
| AI features | ✅ AI invoice recognition | ⚠️ Basic smart suggestions | ⚠️ In development | ❌ None |
| Multi-branch | ✅ Yes | ✅ Yes | ✅ Yes (advanced) | ⚠️ IntercompanyService |
| Mobile app | ✅ Yes | ✅ Yes | ❌ Desktop only | ❌ None |
| Price (VND/yr) | 1.7M-23M | 2M-15M | 200M+ (custom) | Free (self-host) |

---

## 1. Module-by-Module Comparison

### 1.1 Core Accounting Modules

| Module | MISA | FAST | BRAVO | Bookwise | Gap |
|---|---|---|---|---|---|
| **General Ledger** | Full | Full | Full | Full (JournalService + GlService) | None |
| **Cash & Bank** | Full | Full | Full | Full (CashService) | None |
| **AP/AR** | Full | Full | Full | Full (ApService, ArService) | None |
| **Inventory** | Full (FIFO/WA/Specific) | Full | Full | Full (FIFO/WA) | None |
| **Fixed Assets** | Full (all methods) | Full | Full | Full (6 methods) | None |
| **VAT/Tax** | Full (all forms) | Full | Full | Full (VatService + all forms) | None |
| **Payroll** | Full (BHXH, PIT, reports) | Full | Full | ✅ Phase 1 (gross-to-net + post) | ⚠️ Missing: PIT dependent portal, BHXH claims |

### 1.2 Specialized Modules

| Module | MISA | FAST | BRAVO | Bookwise | Priority |
|---|---|---|---|---|---|
| **Cost/Manufacturing** | Basic (cost by item) | ✅ Full (cost by project/order) | ✅ Full (multi-stage) | ❌ Not started | **HIGH** |
| **Budget/Planning** | ✅ Yes | ✅ Yes | ✅ Yes (advanced) | ❌ Not started | **HIGH** |
| **Bank Reconciliation** | ✅ Auto (API connection) | ✅ Auto | ✅ Auto | ✅ CSV import | Medium |
| **Cash Flow Forecasting** | ✅ Yes | ✅ Yes | ✅ Yes (AI-assisted) | ❌ Not started | Medium |
| **Multi-currency** | ✅ Full | ✅ Full | ✅ Full | ✅ Full (FxRevaluationService) | — |
| **Intercompany** | ✅ Yes | ✅ Yes | ✅ Full | ✅ IntercompanyService | — |
| **Contract Management** | ⚠️ Basic | ✅ Yes | ✅ Yes | ❌ Not started | **HIGH** |
| **Project Accounting** | ⚠️ Basic | ✅ Yes | ✅ Yes | ❌ Not started | **HIGH** |

### 1.3 Tax & Compliance

| Feature | MISA | FAST | BRAVO | Bookwise | Gap |
|---|---|---|---|---|---|
| 01/GTGT | ✅ Auto | ✅ Auto | ✅ Auto | ✅ VatDeclarationEngine | None |
| 03/TNDN | ✅ Auto | ✅ Auto | ✅ Auto | ✅ CitDeclarationEngine | None |
| 05/KK-TNCN | ✅ Auto | ✅ Auto | ✅ Auto | ✅ PitDeclarationService | None |
| 05/QTT-TNCN | ✅ Auto | ✅ Auto | ✅ Auto | ✅ PitDeclarationService | None |
| 03/KHBS | ✅ Auto | ✅ Auto | ✅ Auto | ✅ VatService::createAdjustment | None |
| HTKK export | ✅ Built-in | ✅ Built-in | ✅ Built-in | ✅ VatService::exportHtkkXml | None |
| E-invoice (TT32) | ✅ MISA meInvoice | ✅ 3rd party | ✅ 3rd party | ✅ VNPT gateway | None |
| FCT (TT 103) | ⚠️ Basic | ✅ Yes | ✅ Yes | ✅ FctService | None |
| Tax calendar | ✅ Yes | ✅ Yes | ✅ Yes | ❌ Not started | **MEDIUM** |
| Tax dashboard | ✅ Yes | ✅ Yes | ✅ Yes | ❌ Not started | **MEDIUM** |

### 1.4 Reporting & Analytics

| Feature | MISA | FAST | BRAVO | Bookwise | Gap |
|---|---|---|---|---|---|
| BC01/BC02/BC03 | ✅ Full | ✅ Full | ✅ Full | ✅ Full | None |
| BC09 (Notes to FS) | ✅ Full | ✅ Full | ✅ Full | ⚠️ Partial (view only) | **HIGH** |
| S01-DN (Ledger) | ✅ Full | ✅ Full | ✅ Full | ✅ Full | None |
| S02-DN (Journal book) | ✅ Full | ✅ Full | ✅ Full | ✅ Full | None |
| S03-DN (Cash book) | ✅ Full | ✅ Full | ✅ Full | ✅ Full | None |
| S04-DN (Bank book) | ✅ Full | ✅ Full | ✅ Full | ✅ Full | None |
| S05-DN (Monthly ledger) | ✅ Full | ✅ Full | ✅ Full | ✅ Full | None |
| Aging reports | ✅ Full | ✅ Full | ✅ Full | ✅ Full | None |
| Cost analysis | ✅ By department | ✅ By project/order | ✅ Multi-dimensional | ❌ Not started | **HIGH** |
| Custom reports | ⚠️ Limited | ✅ Flexible | ✅ Full | ❌ Not started | **HIGH** |
| PDF/Excel export | ✅ All reports | ✅ All reports | ✅ All reports | ❌ HTML only | **MEDIUM** |
| Dashboard/KPIs | ✅ CEO dashboard | ✅ Yes | ✅ Yes | ❌ Not started | **MEDIUM** |

---

## 2. User Journeys & Process Flows

### 2.1 Complete User Journey Map

```
BUY-SIDE CYCLE:
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│ Purchase │    │ Purchase │    │ Goods    │    │ Invoice  │    │ Payment  │
│ Request  │───→│ Order    │───→│ Receipt  │───→│ Matching │───→│          │
└──────────┘    └──────────┘    └──────────┘    └──────────┘    └──────────┘
                     │               │               │
                     │               │               ├── 3-way match (PO/GR/Invoice)
                     │               │               │
                     ▼               ▼               ▼
              [Bookwise: ✅]    [Bookwise: ✅]   [Bookwise: ✅ 3-way match]
                                                        │
                                                        ▼
                                              [MISA/FAST/BRAVO: auto-match + exception handling]
                                              [Bookwise: ✅ match + price tolerance check]

SELL-SIDE CYCLE:
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│ Sales    │    │ Delivery │    │ Invoice  │    │ E-invoice│    │ Collection│
│ Order    │───→│          │───→│          │───→│ Push CQT │───→│           │
└──────────┘    └──────────┘    └──────────┘    └──────────┘    └──────────┘
                     │               │               │
                     ▼               ▼               ▼
              [Bookwise: ❌]    [Bookwise: ✅]   [Bookwise: VNPT gateway]
                                                        │
                                                        ▼
                                              [MISA: meInvoice auto]
                                              [FAST/BRAVO: 3rd party]

CASH CYCLE:
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│ Receipt  │───→│ Bank     │───→│ Cash     │───→│ Petty    │───→│ Bank     │
│ (111)    │    │ (112)    │    │ Transfer │    │ Cash     │    │ Recon    │
└──────────┘    └──────────┘    └──────────┘    └──────────┘    └──────────┘
       │              │
       ▼              ▼
[Bookwise: ✅ full]  [Bookwise: ✅ Cash-in-transit]

MONTH-END CLOSE:
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│ Trial    │───→│ Sub-ledger│───→│ FX       │───→│ Inventory│───→│ Pre-close│
│ Balance  │    │ Recon     │    │ Reval    │    │ Close    │    │ Checklist│
└──────────┘    └──────────┘    └──────────┘    └──────────┘    └──────────┘
                                                                      │
      ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐    │
      │ Period   │←───│ Tax      │←───│ FS       │←───│ Closing  │←───┘
      │ Archive  │    │ Declare  │    │ Generate │    │ Entries  │
      └──────────┘    └──────────┘    └──────────┘    └──────────┘
```

### 2.2 Happy Paths / Alternative Paths / Exception Paths

#### Purchase-to-Pay (P2P)

| Step | Happy Path | Alternative Path | Exception Path |
|---|---|---|---|
| 1. PR | PR created, pending approval | PR auto-approved (< threshold) | PR rejected → return with reason |
| 2. PO | PO issued from approved PR | Blanket PO for multiple deliveries | PO over budget → escalation |
| 3. GR | Full GR against PO | Partial GR (short shipment) | Over-delivery > 10% → block |
| 4. Invoice match | 3-way match (PO=GR=Inv) | 2-way match (no PO) | Price/qty mismatch → flag for review |
| 5. Payment | Full payment on due date | Early payment (discount taken) | Late payment → penalty calc |
| **MISA/FAST/BRAVO** | ✅ Full | ✅ Full | ✅ Full |
| **Bookwise** | ✅ Full | ✅ Full (ProcurementService) | ✅ Full (3-way match with tolerance) |

#### Order-to-Cash (O2C)

| Step | Happy Path | Alternative Path | Exception Path |
|---|---|---|---|
| 1. Sales quote | Quote → approval | Quote expires → regenerate | Quote rejected |
| 2. Sales order | SO created | SO with deposit required | Credit limit exceeded → block |
| 3. Delivery | Full delivery | Partial delivery | Backorder → partial ship |
| 4. Invoice | Invoice after delivery | Proforma invoice (deposit) | Invoice dispute → credit note |
| 5. E-invoice push | Auto-push CQT → get code | Retry (max 3) → success | Gateway down → manual intervention |
| 6. Collection | Full payment on due date | Partial payment → allocation | Overdue → dunning escalation |
| **MISA/FAST/BRAVO** | ✅ Full (MISA: meInvoice auto) | ✅ Full | ✅ Full |
| **Bookwise** | ⚠️ Missing: Sales Order module | ✅ E-invoice with retry | ✅ Dunning (DebtCollectionService) |

#### Period Close

| Step | Happy Path | Alternative Path | Exception Path |
|---|---|---|---|
| 1. Trial balance | Dr = Cr | Out of balance → auto-detect diff | Diff > tolerance → block close |
| 2. Sub-ledger recon | All matched | Minor diff < threshold (auto-allow) | Material diff > threshold → block |
| 3. FX revaluation | Rate unchanged → zero entry | Rate changed → gain/loss entry | Rate not found → use prior rate + warn |
| 4. Inventory close | Perpetual matches periodic | Adjustment needed → physical count | Unreconcilable → investigation req |
| 5. Closing entries | Auto-generate closing | Manual adjustment needed | Loss > retained earnings → block |
| 6. FS generation | BC01/BC02/BC03 balanced | Prior period adjustment | BC09 notes incomplete → warn |
| 7. Tax declaration | Auto-calc, balances with GL | Adjustment needed (03/KHBS) | Mismatch > threshold → block |
| 8. Period lock | Close → no further edits | Re-open (max 3 times, audit trail) | Re-open limit exceeded → CFO override |
| **MISA/FAST/BRAVO** | ✅ Full auto-close | ✅ Full | ✅ Full |
| **Bookwise** | ✅ Full (all 8 steps) | ✅ Full (re-open config-driven) | ✅ Full (max-reopen, audit trail) |

---

## 3. Critical Gaps vs Market Leaders

### 3.1 HIGH Priority — Must Build for Parity

| # | Gap | MISA | FAST | BRAVO | Impact | Effort |
|---|---|---|---|---|---|---|
| 1 | **Sales Order module** | ✅ Full CRM sync | ✅ Full | ✅ Full | Missing upstream for O2C cycle | 2-3 weeks |
| 2 | **Cost/Manufacturing accounting** | ✅ Basic | ✅ Full | ✅ Full (multi-stage) | Cannot support production enterprises | 4-6 weeks |
| 3 | **Budget & planning** | ✅ Yes | ✅ Yes | ✅ Yes | No spending control | 3-4 weeks |
| 4 | **Contract management** | ⚠️ Basic | ✅ Yes | ✅ Yes | No contract-linked billing | 3-4 weeks |
| 5 | **Project accounting** | ⚠️ Basic | ✅ Yes | ✅ Yes | No project P&L tracking | 3-4 weeks |
| 6 | **BC09 (Notes to FS)** | ✅ Full | ✅ Full | ✅ Full | FS package incomplete per VAS 21 | 1 week |
| 7 | **Custom report builder** | ⚠️ Limited | ✅ Flexible | ✅ Full | Cannot create ad-hoc management reports | 4-6 weeks |
| 8 | **Subsidiary ledgers (sổ chi tiết)** | ✅ Full | ✅ Full | ✅ Full | Audit risk (G04 in gap matrix) | 1-2 weeks |
| 9 | **Mobile app** | ✅ Yes | ✅ Yes | ❌ Desktop | No field access for approvals | 8-12 weeks |
| 10 | **PDF/Excel export** | ✅ All reports | ✅ All reports | ✅ All reports | Cannot submit FS or share reports | 1-2 weeks |

### 3.2 MEDIUM Priority — Competitive Differentiator

| # | Gap | MISA | FAST | BRAVO | Impact | Effort |
|---|---|---|---|---|---|---|
| 1 | **AI invoice recognition** | ✅ Yes | ⚠️ Basic | ❌ No | Would leapfrog competitors for SME | 6-8 weeks |
| 2 | **Auto bank feed (API)** | ✅ Yes | ✅ Yes | ✅ Yes | Manual entry → faster close | 4-6 weeks |
| 3 | **Tax calendar + alerts** | ✅ Yes | ✅ Yes | ✅ Yes | Missed deadlines → penalties | 1 week |
| 4 | **Cash flow forecasting** | ✅ Yes | ✅ Yes | ✅ AI-assisted | CFO decision tool | 2-3 weeks |
| 5 | **Tax dashboard** | ✅ Yes | ✅ Yes | ✅ Yes | Real-time tax position | 1 week |
| 6 | **Global search** | ✅ Yes | ✅ Yes | ✅ Yes | Navigation efficiency | 1 week |
| 7 | **Batch payment processing** | ✅ Yes | ✅ Yes | ✅ Yes | Efficiency for >50 suppliers | 2-3 weeks |
| 8 | **PIT dependent registration portal** | ✅ Yes | ✅ Yes | ✅ Yes | HR self-service | 2 weeks |
| 9 | **Excel import (master data)** | ✅ Yes | ✅ Yes | ✅ Yes | Onboarding speed | 1-2 weeks |

### 3.3 LOW Priority — Future Differentiator

| # | Gap | MISA | FAST | BRAVO | Impact |
|---|---|---|---|---|---|
| 1 | **Multi-entity consolidation** | ✅ Yes | ✅ Yes | ✅ Full for group | Group FS |
| 2 | **Document retention tracking** | ⚠️ Partial | ⚠️ Partial | ✅ Full | Compliance |
| 3 | **BHXH claims processing** | ✅ Yes | ✅ Yes | ✅ Yes | Payroll complete |
| 4 | **Ad-hoc query builder** | ❌ No | ⚠️ Partial | ✅ Full | Analytics |
| 5 | **API gateway for 3rd party** | ⚠️ Limited | ⚠️ Limited | ✅ Custom | Integration |
| 6 | **IFRS conversion** | ❌ No | ❌ No | ✅ Yes | FDI enterprises |

---

## 4. Data Flows — What Mature Systems Do That We Don't

### 4.1 Integrated Data Flow (MISA/FAST/BRAVO)

```
[Bank API] ──→ Auto bank feed ──→ Auto-match transactions ──→ Auto-suggest entry
                   │
[E-invoice API] ──→ Auto-import purchase invoices ──→ Match with GR/PO ──→ Suggest AP entry
                   │
[Tax API (TCT)] ──→ Auto-download issued invoices ──→ Reconcile with GL ──→ Flag mismatch
                   │
[HRM/Payroll] ────→ Employee master ──→ Auto-calc payroll ──→ Post GL + PIT declaration
                   │
[CRM] ────────────→ Sales orders ──→ Auto-generate delivery + invoice ──→ E-invoice push
```

### 4.2 Bookwise Current Data Flow

```
[Manual entry] ──→ Controller → Service → JournalService → PDO → MySQL
                       │
[CSV import] ────→ Bank Reconciliation (partial)
                       │
[VNPT API] ─────→ E-invoice push (outbound only, no auto-import)
                       │
[Payroll] ──────→ PayrollService → calc → post → PIT declaration
```

### 4.3 Missing Data Integration Points

| Integration | Maturity | Bookwise | Gap |
|---|---|---|---|
| Bank feed (auto sync) | ✅ All 3 have it | ❌ CSV import only | Auto-match + reduce manual entry 80% |
| Purchase invoice auto-import | ✅ All 3 have it | ❌ Not started | E-invoice XML from supplier → auto AP entry |
| Sales order → invoice auto | ✅ All 3 have it | ❌ Not started | Missing SO module entirely |
| CRM sync | ✅ MISA has it | ❌ No CRM | Customer master + sales pipeline |
| HRM integration | ✅ All 3 have it | ⚠️ Basic payroll | Timekeeping → payroll auto |

---

## 5. User Journeys — Detailed Walkthrough

### 5.1 Chief Accountant Monthly Close Journey

```
Day 1-3: Data entry period
  ├── Verify all bank transactions posted
  ├── Verify all AP invoices entered
  ├── Verify all AR invoices generated
  └── Run e-invoice reconciliation

Day 4-5: Mid-month checks
  ├── Review trial balance (Dr = Cr)
  ├── Run sub-ledger reconciliation
  ├── Flag any unreconciled items
  └── Initiate corrections

Day 6-7: Closing preparation
  ├── FX revaluation (if FC exists)
  ├── Inventory close (perpetual → periodic)
  ├── Depreciation run
  ├── Accruals and prepayments
  └── Pre-close checklist

Day 8-9: Closing execution
  ├── Closing entries (revenue/expense/P&L/retained)
  ├── Generate FS (BC01/BC02/BC03)
  ├── Tax declaration (01/GTGT, 03/TNDN)
  └── Review FS package

Day 10: Finalize
  ├── Period lock
  ├── Archive snapshots
  └── Sign-off by CFO

[MISA/FAST/BRAVO: 3-5 day close with automation]
[Bookwise: 5-7 day close with current automation]
```

### 5.2 Tax Manager Quarterly Journey

```
Quarter-end:
  ├── Run CIT non-deductible scan
  ├── Calculate provisional CIT (≥80% rule)
  ├── Review loss carryforward position
  ├── Prepare 03/TNDN draft
  ├── Chief Accountant review → approve
  ├── Submit to tax authority
  └── Track payment status

[MISA/FAST/BRAVO: Auto-calc, auto-fill forms]
[Bookwise: ✅ Auto-calc + XML export. Missing: tax calendar + deadline alerts]
```

### 5.3 SME Owner/CEO Journey

```
Monthly:
  ├── Open dashboard → see cash position
  ├── View revenue vs budget
  ├── Check overdue receivables
  ├── Approve large payments (>100M)
  └── View profit/loss snapshot

[MISA: ✅ CEO dashboard on mobile]
[FAST: ✅ Management reports]
[BRAVO: ✅ Full executive cockpit]
[Bookwise: ❌ No dashboard, no mobile]
```

---

## 6. Workflow Comparison

### 6.1 Document Approval Workflows

| Flow | MISA | FAST | BRAVO | Bookwise |
|---|---|---|---|---|
| Purchase request approval | ✅ Multi-level | ✅ Multi-level | ✅ Multi-level | ✅ ProcurementService (SoD) |
| Purchase order approval | ✅ Multi-level | ✅ Multi-level | ✅ Multi-level | ✅ Budget check |
| Payment approval | ✅ Dual signature | ✅ Dual signature | ✅ Multi-level | ⚠️ ApprovalRoutingService exists, not wired to payments |
| Sales order approval | ✅ Credit check | ✅ Credit check | ✅ Credit check | ❌ No SO module |
| Journal entry approval | ✅ Multi-level | ✅ Multi-level | ✅ Multi-level | ✅ ApprovalRoutingService |
| Period close approval | ✅ Dual auth | ✅ Dual auth | ✅ Multi-level | ✅ Tax + period approval |
| Custom approval rules | ✅ By amount/account | ✅ By amount/account | ✅ Scriptable | ✅ By amount (configurable) |

### 6.2 Reconciliation Workflows

| Type | MISA | FAST | BRAVO | Bookwise |
|---|---|---|---|---|
| Bank reconciliation | ✅ Auto (API bank feed) | ✅ Auto | ✅ Auto | ✅ CSV import |
| Sub-ledger reconciliation | ✅ Auto (daily) | ✅ Auto | ✅ Auto | ✅ On-demand (ReconciliationService) |
| Intercompany reconciliation | ✅ Auto | ✅ Auto | ✅ Auto | ✅ IntercompanyService |
| Tax reconciliation | ✅ Auto (GL vs e-invoice) | ✅ Auto | ✅ Auto | ✅ VatService::reconcileWithEInvoice |
| Inventory reconciliation | ✅ Periodic | ✅ Periodic | ✅ Perpetual | ✅ Physical count + periodic |

---

## 7. Chief Accountant's Risk Assessment

### 7.1 Control Weaknesses in Bookwise vs Market

| Control | MISA | FAST | BRAVO | Bookwise | Risk |
|---|---|---|---|---|---|
| Segregation of duties | ✅ Enforced | ✅ Enforced | ✅ Enforced | ⚠️ Procure-to-pay SoD (PR≠PO approver) | Low |
| Dual signature for payment | ✅ Required | ✅ Required | ✅ Required | ❌ Not enforced | **MEDIUM** (G01) |
| Period lock integrity | ✅ Cannot bypass | ✅ Cannot bypass | ✅ Cannot bypass | ✅ Cannot bypass (max-reopen) | None |
| Audit trail completeness | ✅ Full | ✅ Full | ✅ Full | ✅ Full (AuditLogger + ActionJournal) | None |
| Data backup | ✅ Auto cloud | ✅ Auto | ✅ IT-managed | ⚠️ Manual/IT-managed | Low |
| Access control | ✅ Role-based | ✅ Role-based | ✅ Role-based | ✅ Role-based | None |
| Password policy | ✅ Enforced | ✅ Enforced | ✅ IT-managed | ⚠️ Configurable | Low |

### 7.2 Compliance Gaps

| Requirement | TT 99 Reference | MISA | FAST | BRAVO | Bookwise |
|---|---|---|---|---|---|
| Self-designed form regulation | Điều 9 | ✅ Published | ✅ Published | ✅ Published | ❌ No Quy chế hạch toán documented |
| Signature fields on forms | Điều 16-18 | ✅ Electronic signature | ✅ Electronic signature | ✅ PKCS#7 digital signature | ⚠️ via e-invoice PKCS#7, not on internal forms |
| Amount in words | Mẫu 01-TT/02-TT | ✅ Auto | ✅ Auto | ✅ Auto | ✅ toVnWords() helper (not on all forms) |
| Print to 3 copies | Mẫu 01-TT/02-TT | ✅ Print layout | ✅ Print layout | ✅ Print layout | ❌ No print layout at all |
| Serial numbering per type | Điều 19 | ✅ Sequential | ✅ Sequential | ✅ Sequential | ✅ VoucherService |
| 10-year digital archive | NĐ 123/2020 | ✅ Included | ✅ Included | ✅ Included | ⚠️ ActionJournal files, no formal retention policy |

---

## 8. Recommended Implementation Roadmap

### Phase 1 (Weeks 1-4) — Core Missing Modules
```
HIGH Priority — Complete the O2C cycle
  [ ] Sales Order module (SO → delivery → invoice)
  [ ] BC09 Notes to FS (complete the 29-disclosure template)
  [ ] Subsidiary ledgers (sổ chi tiết per account)
  [ ] PDF/Excel export for all reports
```

### Phase 2 (Weeks 5-10) — Management Tools
```
HIGH Priority — Enterprise management features
  [ ] Budget & planning engine
  [ ] Custom report builder (ad-hoc queries)
  [ ] Tax dashboard + calendar + deadline alerts
  [ ] Dashboard/KPIs for CEO
```

### Phase 3 (Weeks 11-16) — Advanced Modules
```
Differentiator features
  [ ] Cost/Manufacturing accounting
  [ ] Project accounting (cost by project/contract)
  [ ] Contract management
  [ ] Batch payment processing
```

### Phase 4 (Weeks 17-20) — Integration
```
Efficiency automation
  [ ] Bank feed API connection (auto sync transactions)
  [ ] Purchase invoice auto-import from e-invoice XML
  [ ] Excel import for master data
  [ ] Global search
```

### Phase 5 (Weeks 21-24) — AI & Polish
```
Future differentiator
  [ ] AI invoice recognition (OCR for paper invoices)
  [ ] Mobile app (approvals + basic reporting)
  [ ] PIT dependent registration portal
  [ ] BHXH claims processing
```

---

## 9. Strategic Recommendations

### From Lead BA (20yr):
> **Build what differentiates, buy what commoditizes.**
>
> MISA dominates with UX ease, FAST with data depth, BRAVO with ERP breadth. Our edge: **zero-cost self-hosted PHP** that works without Composer dependencies. Target: SMEs that outgrow Excel but can't afford MISA (2M+/yr) or BRAVO (200M+).
>
> The 10 HIGH-priority gaps are non-negotiable for TT 99 compliance and basic enterprise readiness. Complete those first. Then focus on integrations (bank feed, auto invoice import) that reduce manual data entry — that's where real time savings live for SME accountants.

### From Chief Accountant (20yr):
> **Internal controls before fancy features.**
>
> Before adding Sales Order or Cost Accounting, fix the remaining control weaknesses:
> 1. Enforce dual-signature for payments (approval workflow wired to CashService)
> 2. Publish Quy chế hạch toán (internal accounting regulation per Điều 9 TT 99)
> 3. Add print layout for forms (Mẫu 01-TT/02-TT compliant)
> 4. Add formal retention policy for digital documents
>
> These cost little but close the compliance gap with MISA/FAST/BRAVO. A client-facing audit will flag their absence before missing Cost Accounting.
>
> For the modules themselves: every Vietnamese accountant expects 11 standard modules. We have 10. Missing Sales Order is the biggest gap — without it, AR invoicing has no upstream trigger, breaking the natural O2C flow that every accountant knows by heart.

---

## Appendix A: Module Maturity Matrix

| Module | MISA | FAST | BRAVO | Bookwise | Effort to Parity |
|---|---|---|---|---|---|
| General Ledger | 10/10 | 10/10 | 10/10 | 10/10 | — |
| Cash & Bank | 10/10 | 10/10 | 10/10 | 10/10 | — |
| AP/AR | 10/10 | 10/10 | 10/10 | 10/10 | — |
| Inventory | 9/10 | 9/10 | 10/10 | 9/10 | 1 week (negative stock warning) |
| Fixed Assets | 9/10 | 9/10 | 10/10 | 9/10 | 1 week (form print) |
| VAT/Tax | 10/10 | 10/10 | 10/10 | 10/10 | — |
| Payroll | 9/10 | 9/10 | 10/10 | 8/10 | 3 weeks (PIT portal, BHXH claims) |
| Sales Order | 9/10 | 9/10 | 10/10 | 0/10 | 3 weeks |
| Purchasing | 9/10 | 9/10 | 10/10 | 9/10 | — |
| Cost Accounting | 6/10 | 8/10 | 10/10 | 0/10 | 6 weeks |
| Budget | 8/10 | 8/10 | 9/10 | 0/10 | 4 weeks |
| Reporting | 8/10 | 9/10 | 9/10 | 5/10 | 4 weeks (custom reports + export) |
| Integration | 8/10 | 7/10 | 8/10 | 4/10 | 8 weeks (bank feed, auto-import) |
| Mobile | 7/10 | 6/10 | 0/10 | 0/10 | 12 weeks |
| AI/ML | 6/10 | 3/10 | 2/10 | 0/10 | 8 weeks |
| **Overall** | **8.5/10** | **8.2/10** | **8.7/10** | **5.8/10** | **~24 weeks to parity** |

## Appendix B: Scoring Key

- 10/10: Feature-complete, TT 99 compliant, production-grade UI
- 7-9/10: Full functionality, minor UX gaps
- 4-6/10: Core functionality implemented, missing advanced features
- 1-3/10: Basic/partial implementation
- 0/10: Not implemented
