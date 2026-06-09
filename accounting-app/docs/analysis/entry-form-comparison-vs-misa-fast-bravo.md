# Entry Form (Chứng từ ghi sổ / Phiếu kế toán): BA+CA Analysis vs MISA, Fast, BRAVO + TT99 Compliance

> **Author:** Lead BA (20yr) + Chief Accountant (20yr)  
> **Date:** 2026-06-09  
> **Scope:** Journal entry form — the core UI for recording accounting transactions  
> **Methodology:** Public docs (MISA helpsme.faonline.vn/bravo.com.vn), help pages, TT99/2025/TT-BTC, GDT, thuvienphapluat, ketoanthienung.net, webketoan.com  
> **Reference:** Existing competitive gap analysis at `competitive-gap-analysis-vs-misa-fast-bravo.md`

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [TT99/2025/TT-BTC Regulatory Requirements](#2-tt992025tt-btc-regulatory-requirements)
3. [Top 3 Software Entry Form Deep-Dive](#3-top-3-software-entry-form-deep-dive)
4. [Bookwise Entry Form Current State](#4-bookwise-entry-form-current-state)
5. [Gap Analysis Matrix](#5-gap-analysis-matrix)
6. [Use Cases](#6-use-cases)
7. [Detailed Specification: Entry Form v2](#7-detailed-specification-entry-form-v2)
8. [Data Flow & Workflow](#8-data-flow--workflow)
9. [User Journey](#9-user-journey)
10. [Business Rules](#10-business-rules)
11. [Risk Assessment](#11-risk-assessment)
12. [Implementation Roadmap](#12-implementation-roadmap)
13. [Production Readiness Verdict](#13-production-readiness-verdict)

---

## 1. Executive Summary

**Verdict: NOT PRODUCTION-READY for entry form.** The app's backend (!JournalService, PostingRuleService, TransactionRepository) is robust — validation, control account protection, period locking, Dr=Cr invariant, voucher sequencing are all correctly implemented at the service layer. However, the **frontend entry form UI** has critical gaps vs TT99 compliance and industry standard UX:

| Dimension | MISA SME | Fast Accounting | BRAVO 8 | Bookwise (Current) | Gap |
|---|---|---|---|---|---|
| **Form standardization** | 37 TT99 templates built-in | Full TT99 form set | Full TT99 form set | 0 TT99 standard forms | **CRITICAL** |
| **Multi-line entry** | Grid with account picker | Grid with group posting | Grid with project/CC | ✅ Basic grid (modal) | Medium |
| **Account picker** | Code+name search+tree | Code+search | Code+search | Select dropdown only | **HIGH** |
| **VAT integration** | Tab-based with auto calc | Tab-based | Per-line VAT | Partial (separate module) | **HIGH** |
| **Attachment** | ✅ Built-in | ✅ Built-in | ✅ Built-in | ❌ None | **HIGH** |
| **Excel import** | ✅ Yes | ✅ Copy-Paste + import | ✅ Import with images | ❌ None | **HIGH** |
| **Print templates** | ✅ Word/Excel designer | ✅ Built-in | ✅ Built-in | ❌ None | **HIGH** |
| **Custom fields** | ✅ Per-form configurable | ✅ Screen layout config | ✅ Per-form columns | ❌ None | **HIGH** |
| **Status workflow** | 3 states | 5 states | 2+ states | 2 states (pending→posted) | Medium |
| **Approval workflow** | ✅ Optional | ✅ Optional | ✅ Built-in | ✅ Via ApprovalController | Medium |
| **Copy document** | ✅ Yes | ✅ Yes | ✅ Yes | ❌ None | Medium |
| **Group posting** | ✅ Implicit | ✅ Explicit (nhóm ĐK) | ✅ Implicit | ❌ None | **HIGH** |
| **INV per document** | ✅ Multiple | ✅ Multiple | ✅ Multiple | ❌ Single | **HIGH** |

---

## 2. TT99/2025/TT-BTC Regulatory Requirements

### 2.1 Hiệu lực

- **Effective:** 01/01/2026 (for FY starting on/after 01/01/2026)
- **Replaces:** TT 200/2014/TT-BTC, TT 133/2016/TT-BTC
- **Status:** ✅ Active (current date = 2026-06-09)

### 2.2 Biểu mẫu chứng từ kế toán (Phụ lục I)

33 form templates across 5 categories:

| Category | Forms | Key Templates |
|---|---|---|
| I. Lao động tiền lương | 8 forms | 01-LĐTL through 08-LĐTL |
| II. Hàng tồn kho | 7 forms | 01-VT through 07-VT |
| III. Bán hàng | 2 forms | 01-BH, 02-BH |
| IV. Tiền tệ | 10 forms | 01-TT through 09-TT (incl. 08a-TT, 08b-TT) |
| V. Tài sản cố định | 6 forms | 01-TSCĐ through 06-TSCĐ |

### 2.3 Mandatory Content per Entry (Điều 16 Luật Kế toán)

Every entry form MUST contain:

1. **Tên và số hiệu** của chứng từ kế toán (name + number)
2. **Ngày, tháng, năm** lập chứng từ (date)
3. **Tên, địa chỉ** của đơn vị/cá nhân lập (who created)
4. **Tên, địa chỉ** của đơn vị/cá nhân nhận (who received)
5. **Nội dung nghiệp vụ** kinh tế phát sinh (description)
6. **Số lượng, đơn giá và số tiền** ghi bằng số (quantity, unit price, amount in numbers)
7. **Tổng số tiền** ghi bằng số và bằng chữ (total in numbers + words)
8. **Chữ ký, họ và tên** của người lập, người duyệt (signatures)

### 2.4 Key TT99 Requirements for Entry Forms

| Requirement | TT99 Article | Compliance Critical |
|---|---|---|
| Mỗi nghiệp vụ chỉ lập chứng từ 1 lần | Điều 10.1 | ✅ Backend enforces |
| Chữ ký phải đầy đủ (người lập, KTT, Giám đốc) | Điều 10.2 | ⚠️ Signature slots missing in UI |
| Định khoản kế toán phải ghi trên chứng từ | Điều 16.6 | ✅ Backend validates |
| Số chứng từ tăng dần, liên tục theo kỳ | Điều 10.2 | ✅ VoucherService handles |
| Gạch bỏ phần để trống, không tẩy xóa | Điều 16.7 | ❌ Not enforced in UI |
| Số tiền ghi cả bằng số và bằng chữ | Điều 16.6 | ⚠️ Partial (js toWords exist) |
| Hóa đơn GTGT kèm chứng từ | Điều 10.3 | ❌ No attachment/invoice linking |
| Kiểm soát nội bộ trước khi ghi sổ | Điều 10.4 | ✅ ApprovalController exists |

---

## 3. Top 3 Software Entry Form Deep-Dive

### 3.1 MISA SME.NET / AMIS Kế Toán

**Form: "Chứng từ nghiệp vụ khác" (Other Transaction Voucher)**

```
┌─────────────────────────────────────────────────────────┐
│ [Chứng từ nghiệp vụ khác]                     [Mới][Sửa] │
├─────────────────────────────────────────────────────────┤
│ Số CT: JV-2026-000001  |  Ngày: 09/06/2026              │
│ Diễn giải: [Thanh toán tiền mua hàng...                ] │
│ Đối tượng: [Công ty TNHH ABC                    ] [🔍]   │
├─────────────────────────────────────────────────────────┤
│ Tab: [Hạch toán] [Thuế] [Thông tin bổ sung]             │
│ ┌──────┬────────┬──────┬──────┬──────────┬────────────┐ │
│ │ TK Nợ│ TK Có  │  VT  │ SL   │ Số tiền  │ Diễn giải  │ │
│ ├──────┼────────┼──────┼──────┼──────────┼────────────┤ │
│ │ 152  │        │ A001 │ 100  │ 50,000,000│ Mua NVL    │ │
│ │ 1331 │        │      │      │ 5,000,000 │ VAT 10%    │ │
│ │      │  331   │      │      │ 55,000,000│ Công nợ    │ │
│ └──────┴────────┴──────┴──────┴──────────┴────────────┘ │
│ Tổng Nợ: 55,000,000  |  Tổng Có: 55,000,000  |  [✓ Cân] │
│ Số tiền bằng chữ: Năm mươi lăm triệu đồng...             │
│ Kèm theo: 1 chứng từ gốc                                 │
├─────────────────────────────────────────────────────────┤
│ [Cất] [Cất và in] [Hủy]                                  │
└─────────────────────────────────────────────────────────┘
```

**Key Features:**
- **3-tab layout**: Hạch toán (posting) | Thuế (tax) | Thông tin bổ sung (custom)
- **Account picker**: Type code or name, auto-suggest with tree navigation
- **VAT auto-calculation**: Net = total / (1+rate), VAT = total - net
- **Multi-VAT invoice support**: Hạch toán gộp nhiều hóa đơn (consolidate multiple invoices into one entry)
- **Custom template designer**: Word/Excel merge fields with `##Detail_InventoryItemName##` syntax
- **Print**: Nguyen-dinh templates, user-customizable via "Thiết kế mẫu chứng từ"
- **Custom columns**: Right-click column header → "Sửa mẫu" → hide/show/rename/reorder
- **Attachment**: File upload linked to entry (PDF/JPEG of original invoice)
- **Nhóm định khoản**: Implicit by account pairing
- **Status workflow**: Lập → Chờ duyệt → Đã duyệt (3 states)
- **Copy**: "Nhân bản chứng từ" (duplicate existing entry)
- **Multiple currencies**: Per-entry currency + exchange rate

### 3.2 Fast Accounting Online

**Form: "Phiếu kế toán" (Accounting Voucher)**

```
┌─────────────────────────────────────────────────────────┐
│ [Phiếu kế toán]                              [Mới][Sửa] │
├─────────────────────────────────────────────────────────┤
│ Số CT: PK-2026-00001  |  Ngày hạch toán: 09/06/2026     │
│ Ngày CT: 09/06/2026    |  Quyển: 01 |  Loại: Thường     │
│ Diễn giải: [Bút toán điều chỉnh cuối kỳ...           ]  │
│ Đối tượng: [KH001 - Công ty XYZ                   ] [🔍]  │
├─────────────────────────────────────────────────────────┤
│ ┌────────┐ ┌──────┬──────┬──────────┬──────────┬──────┐ │
│ │Nhóm ĐK │ │TK Nợ │ TK Có│ Số tiền  │ ĐT HN    │ Ghi  │ │
│ ├────────┤ ├──────┼──────┼──────────┼──────────┼──────┤ │
│ │   1    │ │ 642  │      │ 10,000,000│         │      │ │
│ │   1    │ │      │  1111│ 10,000,000│         │      │ │
│ │   2    │ │ 154  │      │ 5,000,000 │ PA001   │      │ │
│ │   2    │ │      │  152 │ 5,000,000 │         │      │ │
│ └────────┘ └──────┴──────┴──────────┴──────────┴──────┘ │
│ Tổng Nợ: 15,000,000  |  Tổng Có: 15,000,000  [✓ Cân]  │
├─────────────────────────────────────────────────────────┤
│ Tab: [Hạch toán] [Thuế GTGT] [Chứng từ đi kèm]         │
├─────────────────────────────────────────────────────────┤
│ [Lưu] [Lưu & In] [In] [Import Excel] [Copy] [Hủy]      │
└─────────────────────────────────────────────────────────┘
```

**Key Features:**
- **Group posting (nhóm định khoản)**: Explicit `Nhóm ĐK` column for one-to-many / many-to-one validation per group — users define group IDs (1, 2, a, b...)
- **5-state workflow**: Lập chứng từ → Chưa chuyển sổ cái → Chờ duyệt → Chuyển KTTH → Chuyển sổ cái
- **Excel copy-paste**: Select cells in Excel → Ctrl+V directly into grid
- **Bulk import**: Import from Excel via "Tiện ích" → "Import chứng từ"
- **Custom screen layout**: "Khai báo màn hình nhập chứng từ" — per-user field visibility, default values
- **Thuế GTGT tab**: Input/output VAT tracking, links to tax declaration (bảng kê thuế)
- **Tiện ích**: Chèn dòng, Cập nhật cho các bản ghi (fill empty cells), Xóa chi tiết (clear all lines)
- **Quick fill**: Right-click on cell → fill value to empty cells in same column
- **Đính kèm chứng từ**: File attachment
- **In chứng từ**: F7 shortcut, customizable print layout
- **History tracking**: View audit of entry lifecycle

### 3.3 BRAVO 8 ERP

**Form: "Phiếu kế toán khác" / Chứng từ kế toán**

```
┌─────────────────────────────────────────────────────────┐
│ [Phiếu kế toán]              [BRAVO 8]      [Mới][Sửa]  │
├─────────────────────────────────────────────────────────┤
│ Số CT: PK-00001  |  Ngày: 09/06/2026  |  Trạng thái: ✅│
│ Diễn giải: [..................]                          │
│ Đối tượng: [NCC001         ] [Mã NV: ...] [BP: ...]      │
│ Hợp đồng: [HD-2026-001   ]  |  Vụ việc: [VU001       ]  │
├─────────────────────────────────────────────────────────┤
│ ┌──────┬──────┬──────────┬──────┬──────┬──────────────┐ │
│ │TK Nợ │ TK Có│ Số tiền  │ SL   │ ĐG   │ Mã VT/HT     │ │
│ ├──────┼──────┼──────────┼──────┼──────┼──────────────┤ │
│ │ 152  │      │ 50,000,000│ 100  │500,000│ A001         │ │
│ │ 1331 │      │ 5,000,000 │      │      │              │ │
│ │      │  331 │ 55,000,000│      │      │ NCC001       │ │
│ └──────┴──────┴──────────┴──────┴──────┴──────────────┘ │
│ Tab: [Chứng từ đi kèm] [Thuế] [Đính kèm] [Duyệt]       │
│ Số tiền bằng chữ:...                                     │
│ Kèm theo: 1 hóa đơn, 1 phiếu nhập kho                   │
├─────────────────────────────────────────────────────────┤
│ [Cất] [Lưu tạm] [In] [Duyệt] [Phân bổ] [Hủy]          │
└─────────────────────────────────────────────────────────┘
```

**Key Features:**
- **Excel import with images**: Import inventory items with photo attachments
- **Template designer**: Custom document numbering per document type
- **Approval workflow**: Multi-step (M_UsingApprovalProcess parameter) with tab display of approval history
- **Reverse data linking**: Edit on Gantt chart → auto-update entry form (for manufacturing)
- **Cost center tracking**: Per-line columns for cost center, project, work order
- **Multiple currencies per entry**: Track both VND and foreign currency
- **Flexible column configuration**: Per-user, per-document column visibility
- **Attachment**: PDF, images linked to entry
- **Link ngược**: Auto-populate from PO/contract, with drill-down to source documents
- **Bù trừ công nợ**: Debt offset within entry form
- **Phân bổ chi phí**: Auto cost allocation across lines
- **Đánh số chứng từ**: Force re-number documents (management override)
- **Quyền dữ liệu**: Row-level data permissions

---

## 4. Bookwise Entry Form Current State

### 4.1 journal.php (Chứng từ ghi sổ)

```javascript
// Modal with multi-line grid
// Lines built manually via DOM append, NOT using form-grid.js
// Account picker = <select> with all options (no search/typeahead)
// Dr/Cr = <select> with Nợ/Có
// Amount = <input type="number">
// Validation: #drCrStatus shows "Nợ = Có (x)" — frontend only
// Actions: Save draft (POST /api/journal/draft), Approve (POST /api/journal/approve)
```

**Current Form Layout:**
```
┌─────────────────────────────────────┐
│ Nhập bút toán                       │
├─────────────────────────────────────┤
│ Diễn giải: [.......................] │
│ Ngày: [2026-06-09] | Số CT: [....] │
├─────────────────────────────────────┤
│ Định khoản                       ✓  │
│ ┌────────┬────┬──────────┬──┐       │
│ │ TK     │ N/C│ Số tiền  │+ │       │
│ ├────────┼────┼──────────┼──┤       │
│ │ [--TK--]│ Nợ │ [number] │+ │       │
│ └────────┴────┴──────────┴──┘       │
│ Tổng: 0                             │
├─────────────────────────────────────┤
│ [Hủy] [Lưu nháp]                    │
└─────────────────────────────────────┘
```

### 4.2 Cash Receipts (Phiếu thu) cash_receipts.php

More sophisticated than journal.php — includes:
- **Loại thu** (receipt type) with template (default account, VAT flag)
- **VAT auto-calculation**: Net = total / (1+rate), VAT = total - net
- **Amount in words**: AJAX call to `/api/utils/to-words`
- **Payer search**: Autocomplete with AJAX (3 entity types)
- **Single credit account**: No multi-line posting

### 4.3 Cash Payments (Phiếu chi) cash_payments.php

Similar to receipts — single debit account, multi-line quantity grid for inventory items.

### 4.4 Backend Capabilities (Strong Points)

| Capability | Status | Location |
|---|---|---|
| Dr=Cr validation | ✅ Service layer | JournalService.php:799 |
| Control account protection | ✅ Block post to control accounts | JournalService.php:783 |
| Period locking | ✅ Check + hard deadline | JournalService.php:730-755 |
| Voucher sequencing | ✅ SELECT FOR UPDATE | VoucherService |
| Multi-line journal | ✅ Backend supports | JournalService.php:770-796 |
| VAT splitting | ✅ Per-line VAT (1331/33311) | CashService |
| Correction entries | ✅ Supplementary, negative, adjusting | JournalService |
| Approval workflow | ✅ Multi-level with delegation | ApprovalRoutingService |
| Soft delete | ✅ Restorable within window | JournalService |
| Bulk post | ✅ All-or-nothing | JournalService |
| Duplicate entry | ✅ [COPY] prefix + audit link | JournalService |
| XBRL export | ✅ BC01/02/03 | XbrlGenerator |
| Audit trail | ✅ ActionJournal + AuditLogger | AuditLogger |

---

## 5. Gap Analysis Matrix

### 5.1 Critical Gaps (Block Production)

| # | Gap | Severity | TT99 Ref | Competitor Baseline |
|---|---|---|---|---|
| G01 | No standard TT99 form templates (33 forms) | **BLOCKER** | Phụ lục I | All 3 have full set |
| G02 | No account picker (select dropdown only) | **BLOCKER** | — | All 3 have search+tree |
| G03 | No attachment/invoice linking | **BLOCKER** | Điều 10.3 | All 3 have built-in |
| G04 | No Excel import/copy-paste | **BLOCKER** | — | All 3 have import |
| G05 | No print templates | **BLOCKER** | Điều 10 (in chứng từ) | All 3 have print engine |
| G06 | No hierarchical account tree | **HIGH** | — | MISA + BRAVO have tree |
| G07 | No VAT tab per entry (VAT in separate module) | **HIGH** | — | All 3 have in-form VAT |
| G08 | No signature slots (người lập, KTT, GĐ) | **HIGH** | Điều 10.2 | All 3 display signature |
| G09 | No amount in words in journal entry (exists in cash only) | **HIGH** | Điều 16.6 | All 3 show in-words |
| G10 | No custom fields per form type | **HIGH** | — | MISA + BRAVO configurable |

### 5.2 High-Impact Gaps

| # | Gap | Severity | Notes |
|---|---|---|---|
| G11 | Single-line add pattern (manual DOM) | Medium | Should use form-grid.js |
| G12 | No group posting (nhóm ĐK) | Medium | Fast has explicit groups |
| G13 | No partner/customer field on journal entry | Medium | AP/AR entries need it |
| G14 | No project/cost center per line | Medium | BRAVO has per-line CC |
| G15 | No copy document function | Low | — |

---

## 6. Use Cases

### UC-01: Standard Journal Entry (Bút toán thông thường)

**Actor:** Kế toán viên  
**Precondition:** Period open, user has `journal.create` permission  
**Trigger:** User clicks "Nhập bút toán" or `Ctrl+N`  

**Happy Path:**
1. System opens entry form with auto-generated voucher number
2. User enters description, date, lines (account + Dr/Cr + amount)
3. System validates Dr=Cr in real-time
4. User clicks "Ghi sổ" (or "Lưu nháp")
5. System validates: period open, accounts exist, not control account, posting rules
6. System creates transaction via JournalService.postEntry()
7. System generates audit log
8. System confirms with voucher number

**Alternative Paths:**
- **A1: Dr != Cr** → System highlights imbalance, blocks save
- **A2: Account not found** → Error message
- **A3: Control account selected** → Error, suggest sub-account
- **A4: Period closed** → Block with message
- **A5: Posting rule violation** → Block or warn based on severity
- **A6: Concurrent voucher number conflict** → FOR UPDATE retry

### UC-02: Multi-Invoice Consolidation (Hạch toán gộp nhiều hóa đơn)

**Actor:** Kế toán viên  
**Precondition:** Multiple invoices from same supplier  
**Trigger:** User creates one entry for multiple invoices  

**Happy Path:**
1. User selects "Hạch toán gộp" mode
2. User enters/debit lines for expenses + VAT
3. User enters credit line for total AP
4. User attaches/lists invoice references in "Chứng từ đi kèm" tab
5. System creates one entry with reference to multiple invoices

### UC-03: Correction Entry (Bút toán điều chỉnh)

**Actor:** Kế toán viên / Kế toán trưởng  
**Precondition:** Original transaction exists (posted)  
**Trigger:** User selects entry → "Điều chỉnh"  

**Variants:**
- **Supplementary (bổ sung)**: Add missing amounts
- **Negative (đảo ngược)**: Reverse entire entry (red ink)
- **Adjusting (điều chỉnh)**: Move amounts between accounts

### UC-04: Entry with Project/Cost Center

**Actor:** Kế toán viên  
**Precondition:** Projects configured in system  
**Trigger:** User needs to allocate cost to project  

**Happy Path:**
1. User enters multi-line entry
2. Per line, user selects project/cost center from picker
3. System validates project exists
4. System posts entry with project allocation

### UC-05: Bulk Import from Excel

**Actor:** Kế toán viên (supervisor)  
**Precondition:** Excel file with correct column format  
**Trigger:** User selects "Import Excel" → "Phiếu kế toán"  

**Happy Path:**
1. User downloads template (with column headers)
2. User fills rows in Excel
3. User uploads file
4. System validates rows (account check, Dr=Cr per group)
5. System creates entries in bulk (transactional)
6. System returns result summary

### UC-06: Print Entry Form (In chứng từ)

**Actor:** Kế toán viên  
**Precondition:** Entry saved/posted  
**Trigger:** User clicks "In" on entry  

**Happy Path:**
1. System displays print preview (TT99 standard form)
2. User selects template (if multiple configured)
3. User clicks print
4. System renders HTML→PDF (or browser print)

---

## 7. Detailed Specification: Entry Form v2

### 7.1 Form Layout (Recommended)

```
┌─────────────────────────────────────────────────────────┐
│ [PHIẾU KẾ TOÁN]                    [Mới] [Sửa] [In] [Hủy]│
├─────────────────────────────────────────────────────────┤
│ Số chứng từ: JV-2026-000001  (auto)                     │
│ Ngày chứng từ: [09/06/2026] [📅]                         │
│ Ngày hạch toán: [09/06/2026] [📅]                        │
│ Diễn giải: [Nội dung nghiệp vụ.......................]   │
├─────────────────────────────────────────────────────────┤
│ ┌──────┬──────┬──────────┬──────────┬──────────┬──────┐ │
│ │ TK Nợ│ TK Có│ Số tiền  │ Đối tượng│ Dự án    │ Ghi  │ │
│ ├──────┼──────┼──────────┼──────────┼──────────┼──────┤ │
│ │ [🔍] │      │ 50,000,00│ NCC001   │          │  #1  │ │
│ │ [🔍] │      │ 5,000,000│          │          │  #1  │ │
│ │      │ [🔍] │ 55,000,00│          │ PRJ001   │  #1  │ │
│ └──────┴──────┴──────────┴──────────┴──────────┴──────┘ │
│ Tổng Nợ: 55,000,000  |  Tổng Có: 55,000,000  [✓ Cân]  │
│ Số tiền bằng chữ: Năm mươi lăm triệu...                  │
├─────────────────────────────────────────────────────────┤
│ Tab: [Chứng từ đi kèm] [Thuế] [Đính kèm] [Duyệt]       │
├─────────────────────────────────────────────────────────┤
│ [Lưu nháp] [Ghi sổ] [Cất & in] [Hủy]                   │
└─────────────────────────────────────────────────────────┘
```

### 7.2 Form Components

**Header Section:**
- Voucher number (auto-generated, editable only for management)
- Document date (date picker)
- Posting date (same as document date by default)
- Description (textarea, required)

**Grid Section (using form-grid.js v2):**
- Account picker (typeahead with code+name search, tree navigation optional)
- Debit/Credit toggle
- Amount (vi-VN formatted, auto-calc VAT if applicable)
- Partner picker (customer/supplier/employee for AP/AR accounts)
- Project/Cost center picker (optional)
- Group posting column (optional for one-to-many)
- Row number + remove button
- Total row (∑ Dr, ∑ Cr, balance indicator)
- Add row button

**Bottom Tabs:**
- **Chứng từ đi kèm**: References to source documents (invoice numbers, PO numbers)
- **Thuế**: VAT declaration details (if posting includes 1331/3331)
- **Đính kèm**: File upload (PDF invoice scans, supporting docs)
- **Duyệt**: Approval history (if multi-level approval configured)

**Footer:**
- Amount in words (auto-generated)
- Created by / Created at
- Save draft / Post / Save & Print / Cancel

### 7.3 Account Picker Specification

```
Requirements:
- Typeahead search by: code, name, or both
- Show code + name in results
- Show balance optionally
- Support 1000+ accounts with <500ms response
- Keyboard navigation (↑↓ to select, Enter to confirm)
- Filter active accounts only
- Show control account warning if parent selected
- Optional: tree view for hierarchical navigation

Implementation:
{ "id": "1111", "code": "1111", "name": "Tiền mặt Việt Nam", "balance": 50000000, "is_control": false }
```

### 7.4 VAT Integration

```
When line includes 1331 (input VAT):
- Auto-open VAT tab
- Show: VAT rate (dropdown: 0%, 5%, 8%, 10%), invoice number, invoice date
- Auto-calculate: Net = Amount / (1+rate), VAT = Amount - Net
- Link to e-invoice if available

When line includes 3331 (output VAT):
- Same as above but output direction
- Auto-link to sales invoice reference
```

---

## 8. Data Flow & Workflow

### 8.1 Entry Creation Flow

```
User Action                  Frontend                    Backend                     DB
───────────                  ────────                    ───────                     ──
Click "Nhập bút toán"  →  Open modal/form
                           GET /api/voucher/next         VoucherService.nextNumber   SELECT...FOR UPDATE
                           GET /api/accounts             AccountRepository.lookup    SELECT accounts
                           Populate pickers
                           
User fills fields       →  Real-time validation:
                              Dr=Cr check
                              Account exists
                              Amount > 0
                           (JS only)
                           
Click "Ghi sổ"          →  POST /api/journal/post    →  JournalService.postEntry
                              body: { description,        ├─ PeriodService.isPeriodOpen
                                     date,               ├─ AccountRepository.findByCode (each line)
                                     lines: [{            ├─ Control account check
                                       account_code,      ├─ Dr=Cr validation
                                       is_debit,          ├─ PostingRuleService.validate
                                       amount             ├─ beginTransaction
                                     }],                  ├─ Balance updates (foreach line)
                                     created_by,          │   ├─ if debit + asset → credit
                                     module,              │   ├─ if credit + liability → credit
                                     voucher_type         │   └─ AccountRepository.save
                                   }                      ├─ TransactionRepository.save
                                                          ├─ VoucherService.markUsed (commit seq)
                                                          ├─ AuditLogger.log
                                                          └─ commit / rollback
                           ←  201 { transaction_id,
                                     reference }
                           
Entry posted            →  Update list
                           Show success toast
```

### 8.2 Status Workflow

```
Current (2 states):
  pending ────→ posted ────→ reversed
  
Required (4+ states, with optional states):
  draft ──→ submitted ──→ approved ──→ posted ──→ reversed
    │           │             │
    └──cancel──┘      reject ┘         (soft delete)
         │               │
      cancelled       rejected ──→ draft (return)

[Optional: + Fast-style 5 states with "chuyển sổ cái" intermediate step]
```

### 8.3 Group Posting Logic

```
Without group posting (current):
  Dr 152   50,000,000
  Dr 1331   5,000,000
  Cr 331   55,000,000
  → Dr=Cr ✓ (55M = 55M)

With group posting (Fast-style):
  Group 1: Dr 152    50,000,000  |  Cr 111    50,000,000  (partial payment)
  Group 2: Dr 1331    5,000,000  |  Cr 111     5,000,000  (VAT payment)
  → Per-group Dr=Cr check, not just global
  
Benefit: Multiple sub-entries in one voucher with independent balance checks
```

---

## 9. User Journey

### Persona 1: Kế toán viên (Staff Accountant) — "Linh"

**Daily workflow:**
1. Login → Dashboard shows pending approvals, today's entries
2. Clicks "Nhập bút toán" → Modal opens with auto voucher number
3. Types "Mua hàng" in description
4. Selects account: starts typing "152" → picker shows 152, 1521, 1522...
5. Enters Nợ 152: 50,000,000
6. TAB → adds new row → Nợ 1331: 5,000,000
7. TAB → adds new row → Có 331: 55,000,000
8. Checks: #drCrStatus shows green ✓ "Nợ = Có (55,000,000)"
9. Clicks "Lưu nháp"
10. Later, supervisor approves → status changes

**Pain points with current app:**
- Account picker: dropdown with hundreds of options, no search — **takes 15s+ to find account**
- No amount in words — must type manually
- No way to attach invoice scan
- No auto-VAT calculation
- Cannot save and print in one click

### Persona 2: Kế toán trưởng (Chief Accountant) — "Mr. Tuấn"

**Monthly close workflow:**
1. Reviews pending entries via approval queue
2. Clicks entry → sees full detail: voucher, lines, attachments, audit trail
3. Signs electronically (approve/reject/return)
4. For complex adjustments: creates supplementary entry with reference to original
5. Before close: runs trial balance check

**Pain points:**
- Cannot see original invoice attachment inline with entry
- No print preview for signature
- No group posting for complex adjustments
- Cannot filter entries by partner/account

---

## 10. Business Rules

### 10.1 Validation Rules

| Rule ID | Rule | Severity | Current Status |
|---|---|---|---|
| BR01 | Tổng Nợ = Tổng Có (±10 tolerance) | BLOCK | ✅ Backend (JournalService) |
| BR02 | Tài khoản phải tồn tại | BLOCK | ✅ Backend |
| BR03 | Không post vào TK tổng hợp (trừ khi override) | BLOCK | ✅ Backend |
| BR04 | Số tiền mỗi dòng > 0 | BLOCK | ✅ Backend |
| BR05 | Kỳ kế toán phải đang mở | BLOCK | ✅ Backend (PeriodService) |
| BR06 | Tối thiểu 2 dòng (1 Nợ + 1 Có) | BLOCK | ✅ Backend |
| BR07 | Posting rules (Dr-Cr pair validation) | BLOCK/WARN | ✅ Backend (PostingRuleService) |
| BR08 | Số chứng từ tự động tăng, không trùng | BLOCK | ✅ Backend (VoucherService) |
| BR09 | Ngày chứng từ trong kỳ kế toán | WARN | ❌ Frontend only |
| BR10 | Tài khoản công nợ phải có mã đối tượng | WARN | ❌ Not implemented |
| BR11 | Dòng thuế (1331/3331) phải có rate | WARN | ⚠️ Partial (cash only) |
| BR12 | Nếu có dòng 1331 phải có hóa đơn đầu vào | WARN | ❌ Not implemented |

### 10.2 TT99 Business Rules (entry form specific)

| TT99 Rule | Description | Status |
|---|---|---|
| Điều 9.1 | DN có thể tham khảo mẫu tại Phụ lục I | ❌ No default templates |
| Điều 9.2 | DN có thể tự thiết kế mẫu nhưng phải đủ nội dung | ❌ No custom template engine |
| Điều 10.1 | Mỗi NVKT chỉ lập CT một lần | ✅ Backend enforces |
| Điều 10.4 | KTT không được ký "thừa ủy quyền" GĐ | ✅ RBAC enforces |
| Điều 16.1a | Tên và số hiệu CT | ✅ Auto-generated |
| Điều 16.1b | Ngày tháng năm lập CT | ✅ Date picker |
| Điều 16.1c | Tên địa chỉ đơn vị lập | ❌ Not shown (info in company config) |
| Điều 16.1d | Tên địa chỉ đơn vị nhận | ❌ No partner field on journal |
| Điều 16.1đ | Nội dung NVKT | ✅ Description field |
| Điều 16.1e | Số lượng, đơn giá, số tiền bằng số | ⚠️ Quantity not on journal entry |
| Điều 16.1e | Tổng số tiền bằng chữ | ⚠️ Only in cash module |
| Điều 16.1g | Chữ ký người lập, KTT, GĐ | ❌ No signature slots |
| Điều 16.7 | Gạch bỏ phần để trống, không tẩy xóa | ❌ Not enforced |

---

## 11. Risk Assessment

### 11.1 Production Risks if Current Entry Form Ships

| Risk | Impact | Probability | Mitigation |
|---|---|---|---|
| R001: User picks wrong account (no picker search) | Financial misstatement | HIGH | Add account search picker |
| R002: Audit failure — no attachment/paper trail | Regulatory non-compliance | HIGH | Add file attachment |
| R003: Incorrect VAT calculation (no in-form tax) | Tax declaration error | HIGH | Add VAT tab |
| R004: Missing amount in words (legal req) | Legal challenge | MEDIUM | Add to-words display |
| R005: No standard TT99 print form | Audit query | MEDIUM | Add print templates |
| R006: Data entry error from manual Excel re-entry | Human error | MEDIUM | Add Excel import |
| R007: No signature workflow | Legal enforceability | LOW | Add e-signature slots |

### 11.2 Mitigation Priority

```
P0 (Ship-blocking): R001, R002, R003
P1 (Week 1):       R004, R005
P2 (Month 1):      R006, R007
```

---

## 12. Implementation Roadmap

### Phase 1 — Foundation (Week 1) → Unblocks Production

| Task | Files | Effort |
|---|---|---|
| Account search picker (typeahead) | `public/assets/js/components/account-picker.js` | 2 days |
| Fix journal.php to use form-grid.js | `public/views/journal.php` | 1 day |
| Add amount-in-words to all entries | `public/views/journal.php`, cash forms | 0.5 day |
| Add attachment upload (per entry) | Migration: `alter transactions add attachment_json` | 1 day |
| Add partner/customer picker to entry | `public/views/journal.php` | 1 day |

### Phase 2 — TT99 Compliance (Week 2)

| Task | Files | Effort |
|---|---|---|
| Print templates for TT99 forms | `PrintTemplateService` (exists) + templates | 2 days |
| Standard form layouts (01-TT, 02-TT etc.) | `public/assets/css/tt99-forms.css` | 1 day |
| Signature slots display | `public/views/journal.php` | 0.5 day |
| VAT tab on entry form | `public/views/journal.php` | 1 day |

### Phase 3 — Productivity (Month 1)

| Task | Files | Effort |
|---|---|---|
| Excel import (bulk entry) | `ImportService` (exists) + new handler | 3 days |
| Copy document function | `JournalService.duplicateEntry` (exists) + UI | 1 day |
| Group posting (nhóm ĐK) | `public/views/journal.php` + validation | 2 days |
| Custom field configuration | Migration + UI | 2 days |

### Phase 4 — Polish (Month 2)

| Task | Files | Effort |
|---|---|---|
| Account tree navigation | `account-picker.js` | 2 days |
| Status workflow expansion (draft→submitted→approved→posted) | `Transaction.php` + views | 2 days |
| Project/cost center per line | journal.php + grid | 1 day |
| Quick fill (copy value to empty cells) | form-grid.js | 0.5 day |

---

## 13. Production Readiness Verdict

### 13.1 Summary

```
BACKEND:     ✅ READY (JournalService, PostingRuleService, VoucherService, PeriodService)
             ✅ Dr=Cr invariant at service layer
             ✅ Control account protection
             ✅ Period locking + hard deadline
             ✅ Posting rules (75 rules seeded)
             ✅ Audit trail (ActionJournal + AuditLogger)
             
FRONTEND:    ❌ NOT READY (critical UX gaps)
             ❌ No account search picker → user error risk
             ❌ No attachment → no paper trail
             ❌ No VAT integration in entry → tax error risk
             ❌ No TT99 standard forms → compliance risk
             ❌ No amount in words (partial) → legal risk
             ❌ No print templates → audit risk
             
OVERALL:     ❌ NOT PRODUCTION-READY for entry form
```

### 13.2 What We Need for a "GO"

**Minimum viable production entry form (MVP checklist):**

```
[ ] Account picker with search (typeahead)
[ ] Amount in words display
[ ] File attachment (PDF invoice scan)
[ ] VAT auto-calculation when 1331/3331 used
[ ] Print at least 3 TT99 standard forms (01-TT, 02-TT, phiếu kế toán)
[ ] Partner picker for AP/AR-linked entries
[ ] Signature display slots (người lập, KTT, GĐ)
```

### 13.3 What We Already Have (No Extra Cost)

- ✅ Full journal service with correct business logic
- ✅ Posting rules engine (Dr-Cr validation)
- ✅ Voucher sequencing (no gap, FOR UPDATE)
- ✅ Multi-line entry backend support
- ✅ PrintTemplateService (for custom templates)
- ✅ ImportService (for Excel bulk upload)
- ✅ Period locking + deadline enforcement
- ✅ Approval workflow (multi-level + delegation)
- ✅ Correction entries (supplementary/negative/adjusting)
- ✅ Audit logger + ActionJournal
- ✅ form-grid.js reusable component (just needs adoption in journal.php)
- ✅ VAS financial formatting

---

## References

1. TT 99/2025/TT-BTC — `https://thuvienphapluat.vn/van-ban/Doanh-nghiep/Thong-tu-99-2025-TT-BTC-565484.aspx`
2. MISA Chứng từ nghiệp vụ khác — `https://helpsme.misa.vn/2022/kb/lap_chung_tu_nghiep_vu_khac/`
3. Fast Accounting Phiếu kế toán — `https://help.faonline.vn/faohelp/lap-phieu-ke-toan/`
4. Fast Accounting Online — TT99 update — `https://fast.com.vn/fast-accounting-online-cap-nhat-theo-thong-tu-99-2025/`
5. BRAVO 8 new features — `https://www.bravo.com.vn/bravo/bravo-erp-vn/nhung-diem-moi-cua-phan-he-quan-ly-tai-chinh-ke-toan-tren-bravo-8/`
6. BRAVO 8 input tools — `https://www.bravo.com.vn/bravo/bravo-erp-vn/kham-pha-tinh-nang-cac-cong-cu-nhap-lieu-va-hien-thi-du-lieu-tren-bravo-8/`
7. Ketoanthienung — TT99 form list — `https://ketoanthienung.net/chung-tu-ke-toan-theo-thong-tu-99.htm`
8. MISA AMIS — 33 TT99 forms — `https://amis.misa.vn/251419/mau-chung-tu-ke-toan-theo-thong-tu-99-2025-btc/`
9. GDT — `https://www.gdt.gov.vn`
10. Luật Kế toán 2015 — Điều 16 (chứng từ kế toán)
