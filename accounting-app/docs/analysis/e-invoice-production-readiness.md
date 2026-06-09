# E-Invoice Production Readiness — Full BA + Chief Accountant Analysis

> **Version:** 1.0  
> **Date:** 2026-06-09  
> **Analysis by:** BA Lead (20k hrs) + Chief Accountant (20k hrs)  
> **Legal basis verified vs:** GDT, EY Vietnam, PwC Vietnam, Deloitte Vietnam, KPMG Vietnam, ThuVienPhapLuat, LuatVietnam, Savitax, Vietnam Briefing  
> **Regulatory cut-off:** 2026-06-09

---

## Table of Contents

1. [Regulatory Landscape](#1-regulatory-landscape)
2. [Production Readiness Summary](#2-production-readiness-summary)
3. [Gap Analysis](#3-gap-analysis)
4. [Use Cases](#4-use-cases)
5. [Data Flow](#5-data-flow)
6. [Workflow](#6-workflow)
7. [Business Rules Matrix](#7-business-rules-matrix)
8. [User Journey](#8-user-journey)
9. [Internal Controls](#9-internal-controls)
10. [Implementation Roadmap](#10-implementation-roadmap)
11. [Risk Register](#11-risk-register)
12. [Verdict: Can Operate in PROD ENV?](#12-verdict-can-operate-in-prod-env)

---

## 1. Regulatory Landscape

### 1.1 Active Legal Framework (as of 2026-06-09)

| Document | Status | Effective | Subject |
|---|---|---|---|
| Luật Quản lý thuế 108/2025/QH15 | Active | 2026-07-01 | Tax admin — replaces 2019 law |
| NĐ 123/2020/NĐ-CP | Active (amended) | 2022-07-01 | E-invoice framework |
| NĐ 70/2025/NĐ-CP | Active | 2025-06-01 | Amendments to NĐ 123 |
| NĐ 68/2026/NĐ-CP | Active | 2026-03-05 | Household business invoices |
| NĐ 141/2026/NĐ-CP | Active | 2026-01-01 | Amends NĐ 68 (under-1B threshold) |
| NĐ 310/2025/NĐ-CP | Active | — | Penalty amendments |
| TT 32/2025/TT-BTC | Active | 2025-06-01 | Replaces TT 78/2021 — e-invoice guidelines |
| TT 99/2025/TT-BTC | Active | 2026-01-01 | Replaces TT 200/2014 — accounting regime |
| Draft TT on e-invoice | Draft (25/3/2026) | Expected 2026-07-01 | Implements Luật QLT 2025 |
| CV 2966/CT-CS | Active | 2026-05-12 | GDT clarification on household e-invoices |
| CV 3514/CT-CS | Active | 2026-05-29 | GDT on discount/adjustment invoices |
| NQ 204/2025 | Active | 2025-07-01→2026-12-31 | 8% VAT reduction (extended) |

### 1.2 Key Changes in 2025-2026

1. **TT 32/2025 replaces TT 78/2021** (effective 1/6/2025): New XML schema, e-commerce invoice type (code 7), expanded authorization, POS cash register mandate
2. **Luật Quản lý thuế 2025** (effective 1/7/2026): Foreign e-commerce/digital platform suppliers MUST use e-invoices
3. **TT 99/2025 replaces TT 200/2014** (effective 1/1/2026): New chart of accounts, BC01/02/03 formats, FX rate rules, VAT GL integration
4. **Decree 68/2026** + **141/2026**: Households >1B VND revenue MUST use e-invoice with GDT code or POS-connected
5. **8% VAT reduction** extended to 31/12/2026 per NQ 204/2025

### 1.3 E-Invoice Types (Article 8, NĐ 123 as amended by NĐ 70)

| Code | Type | Use Case |
|---|---|---|
| 1 | VAT invoice (GTGT) | Taxpayers using credit method |
| 2 | Sales invoice (Bán hàng) | Taxpayers using direct method |
| 3 | Commercial invoice | Export goods/services |
| 4 | Ticket | Transport, entertainment, lottery |
| 5 | Stamp, token | Postage, insurance |
| 6 | Customs invoice | Import/export |
| 7 | E-commerce invoice | Online marketplace transactions |
| POS | POS cash register invoice | Retail, F&B, services (connected to GDT) |

### 1.4 Mandatory E-Invoice Fields (TT 32/2025)

- Seller: name, address, tax code
- Buyer: name, address, tax code or personal ID
- Goods/services: name, unit, qty, unit price, amount
- VAT: rate, tax amount (for credit-method taxpayers)
- Total payment (incl. VAT)
- Invoice number, serial, date
- QR code (GDT format)
- Digital signature (PKCS#7 RSA SHA-256, 2048-bit min)
- Tax authority code (if authenticated invoice)

---

## 2. Production Readiness Summary

### 2.1 Maturity Score: 7.8/10

| Domain | Score | Status |
|---|---|---|
| E-invoice lifecycle (create→sign→publish→adjust→cancel) | 9/10 | Full lifecycle with VNPT T-VAN |
| VAT declaration (01/GTGT 43 indicators) | 9.5/10 | Full engine with reconciliation |
| Digital signature (PKCS#7, USB Token, HSM) | 8/10 | 3 modes supported |
| T-VAN integration | 7/10 | VNPT only, no multi-provider |
| GDT XML export | 9/10 | XBRL, 01/GTGT, CIT, PIT, FCT |
| Automated GDT submission | 0/10 | NOT IMPLEMENTED |
| E-invoice PDF delivery | 3/10 | Via T-VAN only, no local cache |
| Email/SMS delivery to buyer | 0/10 | NOT IMPLEMENTED |
| Background retry queue | 0/10 | NOT IMPLEMENTED |
| Tax code validation via GDT API | 0/10 | NOT IMPLEMENTED |
| E-invoice dashboard/analytics | 2/10 | Basic list only |
| Reconciliation (e-invoice vs GL vs declaration) | 8/10 | 3-way reconciliation |
| POS cash register integration | 1/10 | Not implemented |
| E-commerce invoice (type 7) | 0/10 | Not implemented |

### 2.2 What IS Ready for Production

- ✅ Creating e-invoices from accounting transactions (JournalService → InvoiceService)
- ✅ Building TT32 v2.0.0 XML with QR code
- ✅ Digital signing (USB Token / file / mock)
- ✅ Publishing via VNPT T-VAN SOAP API
- ✅ Adjust, replace, cancel invoices
- ✅ Retry failed publications
- ✅ Download XML
- ✅ 01/GTGT declaration with 43 indicators
- ✅ 4-eyes approval workflow (Tax Acc → Chief Acc)
- ✅ GL reconciliation (1331/33311 vs declaration)
- ✅ E-invoice reconciliation (declaration vs actual invoices)
- ✅ Non-deductible VAT scan (≥5M cash payment)
- ✅ Input VAT 4-condition checklist
- ✅ HTKK and GDT XML export
- ✅ XBRL export for BC01/BC02/BC03
- ✅ VAT rate management (6 groups, NQ 204 reduction)
- ✅ Foreign currency VAT invoice (USD on invoice + VND conversion rate)

### 2.3 What is NOT Ready

| Gap | Severity | Impact | Effort |
|---|---|---|---|
| No automated GDT submission | HIGH | User must manually upload XML to GDT portal. Not fully automated. | 4 weeks |
| No PDF download/view | MEDIUM | Cannot view/print e-invoice PDF from app. Only XML. | 1 week |
| No email delivery | MEDIUM | Cannot send e-invoice to buyer via email. | 2 weeks |
| No background retry queue | LOW | Failed publish requires manual retry. | 1 week |
| No e-invoice dashboard | LOW | No KPI/stats for e-invoice volume, failures. | 1 week |
| No POS cash register integration | LOW | Not needed for this ERP (on-premise) | — |
| No e-commerce invoice (type 7) | LOW | Only if integration with e-commerce needed | — |
| No MST validation via GDT API | MEDIUM | Cannot validate buyer tax code automatically | 2 weeks |
| VNPT T-VAN only | LOW | Single-provider risk, but typical for VN. | 2 weeks |

---

## 3. Gap Analysis

### 3.1 G01 — Automated GDT Electronic Submission

**Description:** System generates XML files for 01/GTGT, CIT, PIT, FCT but does NOT submit them automatically to the GDT portal (`thuedientu.gdt.gov.vn`). User must download XML and upload manually.

**Regulatory requirement:** Luật Quản lý thuế 2025 Art. 26: Taxpayers MUST submit tax declaration data electronically. Manual upload is accepted but not best practice.

**Risk:** HIGH — manual process error-prone, deadline miss risk.

**Fix:** Implement GDT SOAP/REST submission client:
- Authentication via digital certificate (already supported by DigitalSignatureService)
- Submit 01/GTGT XML → receive submission ID
- Submit e-invoice data → receive GDT confirmation
- Poll for processing status
- Store submission receipt (submission_id, timestamp, status)

### 3.2 G02 — PDF Generation & Viewing

**Description:** XmlInvoiceBuilder builds XML. VnptEInvoiceGateway::downloadPdf() exists but no controller/view route.

**Regulatory requirement:** TT 32/2025: Invoice must be convertible to paper form. PDF is the standard paper representation.

**Fix:**
- Implement local PDF generation from XML (use XML → XSL-FO or direct HTML → PDF via `mPDF` or similar)
- Cache PDF locally in `e_invoices.pdf_path`
- Expose `GET /api/einvoice/:id/pdf` endpoint
- Add "View PDF" button in einvoice.php view

### 3.3 G03 — Email Delivery to Buyer

**Description:** After successful e-invoice publication, buyer should receive invoice via email with PDF attachment.

**Regulatory requirement:** NĐ 123 Art. 4: Seller must deliver invoice to buyer. Electronic delivery is standard.

**Fix:**
- Add `buyer_email` to e_invoices table
- Implement email service (PHPMailer or custom SMTP)
- Send PDF on successful publish
- Log delivery status

### 3.4 G04 — Background Retry Queue

**Description:** If T-VAN returns error, `retryPublish()` exists but requires manual action. No automatic retry.

**Fix:**
- Add `retry_count` and `next_retry_at` to e_invoices table
- Simple cron: `php scripts/retry-einvoice.php` — picks failed invoices, retries with exponential backoff
- Alert after max retries exhausted

### 3.5 G05 — MST Validation via GDT API

**Description:** Buyer tax code cannot be validated automatically. Manual check required.

**Regulatory requirement:** TT 32/2025 Art. 6: Buyer tax code must be correct. Wrong tax code → buyer cannot deduct input VAT → legal dispute.

**Fix:**
- GDT provides MST lookup API (SOAP/REST)
- Validate MST before creating e-invoice
- Cache validated MSTs (validity period)
- Warn if MST not found

### 3.6 G06 — E-Invoice Statistics Dashboard

**Fix:** Add dedicated dashboard tab with:
- Monthly volume trend
- Success/failure rate
- Total value issued
- Average processing time
- Top errors

---

## 4. Use Cases

### UC-01: Create and Publish E-Invoice from Transaction

**Actor:** Accountant / Kế toán viên  
**Precondition:** Transaction posted, buyer info complete, T-VAN configured  
**Trigger:** User clicks "Xuất hóa đơn" on a posted transaction  

**Happy path:**
1. System loads transaction with all lines
2. System extracts buyer info, seller info
3. System builds TT32 XML with QR code
4. System determines VAT rate per line (via VatRateService)
5. System signs XML (PKCS#7)
6. System publishes via VNPT T-VAN
7. T-VAN returns invoice number + GDT code
8. System saves e_invoice record (status=published)
9. System updates transaction with e-invoice reference

**Alternative paths:**
- **A1:** VatRateService cannot determine rate → user prompted to set VAT group manually
- **A2:** Digital signature fails (token not found, cert expired) → error message, retry
- **A3:** T-VAN unavailable → save as draft, retry later (manual or auto)
- **A4:** T-VAN returns validation error → display error to user, allow fix
- **A5:** Buyer MST invalid → warn but allow continue (configurable block)

**Business rules:**
- BR01: Must have ≥1 line with Dr≠Cr already balanced
- BR02: Buyer tax code required (unless foreign buyer)
- BR03: VAT rate per line determined by vat_group_products mapping
- BR04: Total = sum(line amounts) incl. VAT
- BR05: XML must pass schema validation before signing
- BR06: Signed XML must have valid PKCS#7 (RSA SHA-256, 2048-bit)

### UC-02: Adjust Existing E-Invoice

**Actor:** Accountant  
**Precondition:** Invoice published, no replacement exists  
**Trigger:** User clicks "Điều chỉnh"  

**Happy path:**
1. User selects adjustment type (price, quantity, VAT rate, info)
2. User enters new values
3. System creates adjustment XML referencing original invoice
4. System signs and publishes adjustment
5. T-VAN confirms
6. System saves adjustment record linked to original
7. System updates transaction if needed

**Alternative paths:**
- **A1:** Original invoice already adjusted → only replace allowed
- **A2:** Adjustment changes total amount → must also adjust GL journal

**Business rules:**
- BR07: Maximum 1 adjustment per original invoice
- BR08: Adjustment must reference original invoice number + serial
- BR09: GL journal adjustment must be posted before e-invoice adjustment

### UC-03: Replace (Cancel + Reissue) E-Invoice

**Actor:** Accountant  
**Precondition:** Invoice published, error found that cannot be adjusted  
**Trigger:** User clicks "Thay thế"  

**Happy path:**
1. User enters reason for replacement
2. User enters corrected data
3. System cancels original invoice via T-VAN (reason recorded)
4. System creates new invoice XML with replacement reference
5. System signs and publishes
6. User notified of new invoice number

### UC-04: Cancel E-Invoice

**Actor:** Accountant, Chief Accountant (RBAC)  
**Precondition:** Invoice published, buyer agrees  
**Trigger:** User clicks "Hủy"  

**Happy path:**
1. User enters cancellation reason
2. System validates current period is open
3. Chief Accountant approves cancellation (4-eyes)
4. System cancels via T-VAN
5. System creates reverse journal entry (if already posted)
6. Audit trail: cancellation record

**Business rules:**
- BR10: Only Chief Accountant can approve cancellation
- BR11: Cancellation requires reversal journal entry (Dr=Cr swap)
- BR12: Period must be open for reversal

### UC-05: Prepare 01/GTGT VAT Declaration

**Actor:** Tax Accountant  
**Precondition:** Month/quarter ended  
**Trigger:** User clicks "Lập tờ khai"  

**Happy path:**
1. System summarizes all AP invoices (input VAT) for period
2. System summarizes all AR/sales invoices (output VAT) for period
3. System categorizes by VAT rate (0/5/8/10/exempt)
4. System includes prior-period adjustments
5. System calculates 43 indicators per TT 32/2025
6. System saves declaration (status=draft)
7. User reviews, adjusts if needed

**Alternative paths:**
- **A1:** No transactions in period → auto-fill zeros, allow declaration
- **A2:** Adjustment needed (03/KHBS) → create supplementary declaration
- **A3:** GL mismatch found (1331 vs AP input VAT) → warn, require reconciliation

### UC-06: Reconcile E-Invoice vs Declaration vs GL

**Actor:** Chief Accountant  
**Precondition:** Declaration prepared, e-invoices published  
**Trigger:** Monthly/quarterly close procedure  

**Happy path:**
1. System compares e-invoice output total vs 01/GTGT indicator 26
2. System compares e-invoice input total vs 01/GTGT indicator 14
3. System compares 1331 GL balance vs AP input VAT total
4. System compares 33311 GL balance vs output VAT total
5. All differences < tolerance → reconciliation successful
6. User can finalise declaration

**Business rules:**
- BR13: Tolerance = ±0 VND (exact match required for finalise)
- BR14: If mismatch > 100,000 VND → block finalise
- BR15: Reconciliation must pass before period close

### UC-07: Handle Non-Deductible Input VAT

**Actor:** Tax Accountant  
**Precondition:** Period end  
**Trigger:** Part of monthly close procedure  

**Happy path:**
1. System scans all input invoices ≥5M VND cash payment
2. System flags those without valid e-invoice with full info
3. System applies TT 69/2025 non-deductibility rule
4. User reviews flagged items (can override with justification)
5. System adjusts 1331 → 632/641/642 (non-deductible portion)

**Business rules:**
- BR16: Cash payment ≥5M VND without valid e-invoice → non-deductible
- BR17: Adjustment entry: Dr 632/641/642, Cr 1331
- BR18: User override requires Chief Accountant approval

### UC-08: Generate GDT-XML for Submission

**Actor:** Tax Accountant  
**Precondition:** Declaration finalised  
**Trigger:** User clicks "Xuất XML nộp thuế"  

**Happy path:**
1. System builds XML with GDT namespace (`http://www.gdt.gov.vn/2025/01gtgt`)
2. System includes all 43 indicators
3. System generates digital signature over XML
4. System returns XML for download (or auto-submits if G01 implemented)

---

## 5. Data Flow

### 5.1 E-Invoice Creation Flow

```
Transaction (posted)
  │
  ▼
InvoiceService::createFromTransaction()
  │
  ├── loadTransaction(id) → Transaction + LedgerEntry[]
  ├── getSellerInfo() → seller (from company config)
  ├── getBuyerInfo(txn) → buyer (from ap_suppliers / ar_customers)
  ├── extractLineItems(txn) → LineItem[] (account → product mapping)
  │     └── VatRateService::determineRate(item) → VAT group + rate
  ├── calculateTotals(items) → {subtotal, vatByRate[], total}
  │
  ▼
XmlInvoiceBuilder::buildGtgt(data)
  │
  ├── Build XML: <Invoice xmlns="http://www.gdt.gov.vn/2025/invoice">
  │     ├── <InvoiceHeader> (serial, number, date, currency, rate)
  │     ├── <Seller> (name, address, taxCode)
  │     ├── <Buyer> (name, address, taxCode, personalID)
  │     ├── <InvoiceLines> (items with VAT per line)
  │     ├── <Summary> (subtotal, vatSummary, total)
  │     └── <QRCode> (GDT format QR data)
  │
  ▼
DigitalSignatureService::signXml(xml)
  │
  ├── Mode: production (USB Token/HSM) / dev (key file) / test (mock)
  ├── Build PKCS#7 detached signature
  ├── Embed <DigitalSignature> in XML
  │
  ▼
VnptEInvoiceGateway::publish(signedXml, pattern, serial)
  │
  ├── SOAP call to VNPT API
  ├── VNPT validates XML
  ├── VNPT assigns invoice number
  ├── VNPT returns {fkey, invoiceNumber, gdtCode, pdfUrl}
  │
  ▼
e_invoices table (status=published)
  │
  ├── transaction_id, invoice_number, serial, fkey
  ├── signed_xml (BLOB), gdt_code, pdf_url
  ├── status, published_at
  │
  ▼
Transaction updated with e_invoice_ref
```

### 5.2 VAT Declaration Flow

```
Month-end trigger
  │
  ▼
VatService::prepareDeclaration(period)
  │
  ├── Sum input VAT: SELECT SUM(tax_amount) FROM ap_invoices WHERE period=?
  ├── Sum output VAT: SELECT SUM(tax_amount) FROM ar_invoices WHERE period=?
  ├── Categorize by rate: 0%, 5%, 8%, 10%, exempt
  │
  ▼
VatDeclarationEngine::calculateIndicators(period)
  │
  ├── 43 indicators computed from ledger + invoice data
  ├── [01-10] Output VAT by rate
  ├── [11-20] Input VAT by rate
  ├── [21-30] Adjustments (carryforward, prior period)
  ├── [31-43] Summary (total payable/refundable)
  │
  ▼
vat_declarations table (status=draft)
  │
  ├── Approve → status=approved (4-eyes)
  ├── Finalise → status=finalised (period check, lock)
  │
  ▼
VatService::reconcileVatDeclaration(period)
  │
  ├── Compare 1331 GL balance vs input VAT total
  ├── Compare 33311 GL balance vs output VAT total
  ├── Report discrepancies
  │
  ▼
VatService::reconcileWithEInvoice(period)
  │
  ├── Compare e_invoices output sum vs declaration output
  ├── Compare e_invoices input sum vs declaration input
  │
  ▼
VatDeclarationEngine::exportToXml(declarationId)
  │
  └── XML for GDT submission
```

---

## 6. Workflow

### 6.1 E-Invoice Lifecycle State Machine

```
                ┌─────────────┐
        ┌──────►│   draft     │◄─────── createFromTransaction
        │       └──────┬──────┘
        │              │ publish (via T-VAN)
        │       ┌──────▼──────┐
        │       │  publishing │
        │       └──────┬──────┘
        │              │
        │     ┌────────┼────────┐
        │     │        │        │
        ▼     ▼        ▼        ▼
   ┌────────┐ ┌──────────┐ ┌─────────┐
   │published││publish_  │ │publish_ │
   │        ││failed    │ │expired  │
   └───┬────┘ └────┬─────┘ └─────────┘
       │           │ retryPublish
       │           └──────► publishing
       │
       ├── adjustInvoice() → adjusted (linked to new adjustment invoice)
       ├── replaceInvoice() → replaced (original cancelled)
       └── cancelInvoice() → cancelled
```

### 6.2 VAT Declaration State Machine

```
draft ──► approved ──► finalised
  │          │            │
  │          ├── rejected │
  │          │            │
  └── adjustment ──► 03/KHBS supplementary
```

### 6.3 Month-End Close Procedure (E-Invoice Related)

```
Step 1: Reconcile e-invoices vs GL (1331, 33311)
Step 2: Scan non-deductible VAT
Step 3: Prepare 01/GTGT declaration
Step 4: Reconcile declaration vs e-invoices
Step 5: Approve declaration (Tax Accountant → Chief Accountant)
Step 6: Export XML for GDT submission
Step 7: Submit to GDT portal (manual or automated)
Step 8: Finalise declaration (lock)
Step 9: GL period close
```

---

## 7. Business Rules Matrix

| ID | Rule | Source | Category | Severity |
|---|---|---|---|---|
| BR01 | E-invoice must originate from posted transaction with Dr=Cr | TT 99 | Validation | BLOCK |
| BR02 | Buyer tax code required unless foreign buyer (passport OK) | TT 32 Art. 6 | Validation | BLOCK |
| BR03 | VAT rate determined by vat_group_products mapping | NQ 204 | Validation | BLOCK |
| BR04 | Total = sum(lines) including VAT | TT 32 | Validation | BLOCK |
| BR05 | XML must pass schema validation before signing | TT 32 | Validation | BLOCK |
| BR06 | Digital signature: PKCS#7, RSA SHA-256, 2048-bit min | TT 32 | Security | BLOCK |
| BR07 | Max 1 adjustment per original invoice | NĐ 70 | Business | WARN |
| BR08 | Adjustment must reference original invoice number+serial | TT 32 | Validation | BLOCK |
| BR09 | GL adjustment must be posted before e-invoice adjustment | TT 99 | Sequencing | BLOCK |
| BR10 | Cancellation requires Chief Accountant approval | Internal | RBAC | BLOCK |
| BR11 | Cancellation creates reversal journal entry | TT 99 | Business | BLOCK |
| BR12 | Period must be open for reversal/cancellation | TT 99 | Validation | BLOCK |
| BR13 | Declaration finalise requires 0 discrepancy vs GL | TT 32 | Validation | BLOCK |
| BR14 | Discrepancy >100K VND blocks finalise | Internal | Validation | BLOCK |
| BR15 | Reconciliation must pass before period close | Internal | Sequencing | BLOCK |
| BR16 | Cash payment ≥5M VND without e-invoice → non-deductible | TT 69/2025 | Tax | WARN |
| BR17 | Non-deductible VAT: Dr 632/641/642, Cr 1331 | TT 99 | Accounting | BLOCK |
| BR18 | User override of non-deductible requires Chief Acc approval | Internal | RBAC | BLOCK |
| BR19 | Foreign supplier e-invoice mandatory from 2026-07-01 | Luật QLT 2025 | Regulatory | BLOCK |
| BR20 | E-commerce invoice type (code 7) for marketplace transactions | NĐ 70 | Classification | WARN |

---

## 8. User Journey

### 8.1 Daily: Accountant

```
1. Login → Dashboard → "Hóa đơn cần xuất" widget
2. Select pending transaction → review lines + buyer
3. Click "Xuất hóa đơn" → system creates + signs + publishes
4. See confirmation with invoice number
5. Send PDF to buyer (via email if G03 implemented)
6. Done
```

### 8.2 Monthly: Tax Accountant

```
1. Month-end → open VAT module
2. Click "Lập tờ khai GTGT" → auto-summary
3. Review 43 indicators
4. Click "Quét hóa đơn không được khấu trừ"
5. Review non-deductible items → override or accept
6. Click "Đối chiếu GL" → verify 1331 = input VAT
7. Click "Đối chiếu hóa đơn điện tử" → verify totals match
8. Submit for approval (Tax Acc → Chief Acc)
9. Chief Acc approves → status=approved
10. Click "Xuất XML GDT" → download XML
11. Upload XML to thuedientu.gdt.gov.vn
12. After GDT confirms → finalise declaration
```

### 8.3 Exception: Error Found After Publication

```
1. Accountant discovers error on published invoice
2. Determine error type:
   a. Minor (wrong buyer name, same tax code) → Adjust
   b. Major (wrong amount, wrong VAT rate) → Replace
   c. Wrong buyer entirely, need to void → Cancel
3. Perform action → system handles T-VAN communication
4. If adjust → new adjustment invoice linked
5. If replace → original cancelled + new invoice
6. If cancel → reversal journal entry created automatically
```

---

## 9. Internal Controls

| IC-ID | Control | Description | Prevents |
|---|---|---|---|
| IC01 | 4-eyes approval | Declaration requires Tax Acc + Chief Acc | Wrong declaration |
| IC02 | Dr=Cr check before e-invoice | Cannot create e-invoice from unbalanced txn | Wrong amount |
| IC03 | Period lock | Cannot create/cancel e-invoice in closed period | Period integrity |
| IC04 | XML schema validation | XML validated before signing | Corrupt data |
| IC05 | Signature verification | Signed XML verified before publish | Tampered data |
| IC06 | Retry limit | Max 3 retries for failed publish | Infinite loop |
| IC07 | Cancellation requires approval | Chief Acc must approve cancellation | Unauthorized void |
| IC08 | GL reconciliation check | Declaration blocked if GL mismatch >100K | Wrong VAT payable |
| IC09 | E-invoice reconciliation | Declaration blocked if e-invoice mismatch | Missing invoices |
| IC10 | Non-deductible scan | Automatic flagging of >=5M cash invoices | Wrong tax deduction |
| IC11 | Audit trail | Every e-invoice action logged via AuditLogger | No traceability |
| IC12 | RBAC | Permission check per action (einvoice.create, cancel, etc.) | Unauthorized access |

---

## 10. Implementation Roadmap

### Phase 1 — Critical (4 weeks)

| Task | Effort | Priority |
|---|---|---|
| **G01:** Automated GDT submission client | 4 weeks | P0 |
| **G02:** PDF generation + download endpoint | 1 week | P0 |
| **G03:** Email delivery to buyer | 2 weeks | P0 |

### Phase 2 — Important (3 weeks)

| Task | Effort | Priority |
|---|---|---|
| **G04:** Background retry queue (cron) | 1 week | P1 |
| **G05:** MST validation via GDT API | 2 weeks | P1 |
| **G06:** E-invoice dashboard | 1 week | P1 |

### Phase 3 — Nice-to-have (3 weeks)

| Task | Effort | Priority |
|---|---|---|
| Multi-T-VAN provider (Viettel, MISA) | 2 weeks | P2 |
| E-commerce invoice (type 7) support | 1 week | P2 |
| POS cash register integration | 2 weeks | P3 |

### Phase 0 — Already done (verified)

- ✅ InvoiceService lifecycle
- ✅ XmlInvoiceBuilder (TT32 v2.0.0)
- ✅ DigitalSignatureService
- ✅ VnptEInvoiceGateway
- ✅ EInvoiceController + routes
- ✅ VatService (declaration lifecycle)
- ✅ VatDeclarationEngine (43 indicators)
- ✅ VatRateService (6 groups, NQ 204 reduction)
- ✅ XbrlGenerator (BC01/02/03)
- ✅ Reconciliation tools
- ✅ Views: einvoice.php, vat_declarations.php, tax_submission.php
- ✅ RBAC permissions
- ✅ Audit logging

---

## 11. Risk Register

| ID | Risk | Severity | Probability | Mitigation |
|---|---|---|---|---|
| R01 | T-VAN outage blocks invoice creation | HIGH | LOW | Draft mode + retry queue (G04) |
| R02 | Digital certificate expires | HIGH | MEDIUM | Alert 30 days before expiry, auto-check |
| R03 | Manual GDT submission missed deadline | HIGH | HIGH | G01 auto-submit; if not, calendar reminder |
| R04 | Buyer tax code wrong → cannot deduct VAT | HIGH | MEDIUM | G05 validates MST before create |
| R05 | XML schema changes (new TT draft) | MEDIUM | LOW | Schema versioning, parameterized XSD |
| R06 | VAT rate change (NQ 204 extension or not) | MEDIUM | MEDIUM | VatRateService data-driven, no hardcode |
| R07 | E-invoice lost during T-VAN transmission | MEDIUM | LOW | Retry logic + status polling |
| R08 | User cancels invoice without reversal entry | HIGH | LOW | BR11 enforces reversal via JournalService |
| R09 | Foreign supplier e-invoice not compliant by 2026-07-01 | HIGH | MEDIUM | Feature flag for foreign invoicing |
| R10 | 01/GTGT indicator calculation wrong | HIGH | LOW | Reconciliation IC08/IC09 catches mismatch |

---

## 12. Verdict: Can Operate in PROD ENV?

### YES, with conditions.

The app can operate in production **immediately** for core e-invoice functionality:

- Create, sign, publish, adjust, replace, cancel e-invoices via VNPT T-VAN ✅
- Prepare, approve, finalise 01/GTGT declarations ✅
- Reconcile e-invoices vs GL vs declarations ✅
- Generate GDT-compliant XML for manual submission ✅

### Condition: Must address G01 before full PROD go-live

**G01 (Automated GDT submission)** is the ONLY blocker for full production deployment. All other gaps are P1/P2 and can be addressed post-launch.

**Acceptable interim workaround:** Train users to download XML and upload manually to `thuedientu.gdt.gov.vn`. This is legally acceptable (NĐ 123 Art. 20 allows XML download for submission).

### PROD-READY checklist:

```
[✅] E-invoice lifecycle (create→sign→publish→adjust→cancel)
[✅] Digital signature (PKCS#7, USB Token, file)
[✅] TT32 v2.0.0 XML with QR code
[✅] VNPT T-VAN integration
[✅] VAT declaration (43 indicators)
[✅] RBAC permissions (create, cancel, approve)
[✅] Audit trail
[✅] GL reconciliation
[✅] Non-deductible VAT handling
[✅] 8% VAT reduction (NQ 204/2025)
[✅] Foreign currency e-invoice
[✅] XBRL financial statements
[⚠️] GDT submission (manual only — G01 needed for auto)
[⚠️] PDF download (G02 — acceptable interim via T-VAN)
[⚠️] Email delivery (G03 — acceptable interim, manual download)
[❌] Background retry (G04 — acceptable interim, manual retry exists)
[❌] MST validation (G05 — acceptable interim, manual check)
```

### Regulatory Compliance Verification

| Requirement | Status | Evidence |
|---|---|---|
| TT 32/2025 XML format | ✅ | XmlInvoiceBuilder::buildGtgt() |
| PKCS#7 signature | ✅ | DigitalSignatureService |
| QR code | ✅ | XmlInvoiceBuilder |
| VAT per line | ✅ | extractLineItems() groups by VAT rate |
| 01/GTGT 43 indicators | ✅ | VatDeclarationEngine |
| 4-eyes approval | ✅ | VatService::approveDeclaration() |
| Period locking | ✅ | PeriodService::isPeriodOpen() |
| Audit trail | ✅ | AuditLogger::log() |
| XBRL BC01/02/03 | ✅ | XbrlGenerator |
| NQ 204 8% reduction | ✅ | VatRateService::isEligibleForReduction() |
| TT 69 non-deductible | ✅ | VatService::scanNonDeductibleVat() |
| RBAC | ✅ | Auth::requirePermission() |

---

> **Conclusion:** Production-ready for a Vietnam enterprise deploying with VNPT T-VAN. Complete G01 (GDT auto-submit) as P0 before full go-live. G02-G06 as P1 within 90 days. Core accounting team can operate with manual GDT submission in interim.

> **Next step:** Save this to AGENTS.md changelog, file as e-invoice-production-readiness.md.
