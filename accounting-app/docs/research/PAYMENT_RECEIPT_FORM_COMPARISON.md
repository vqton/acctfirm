# Payment/Receipt Form Comparison: MISA vs Fast vs BRAVO vs Current App

## 1. Form Fields Comparison

| Field | MISA | Fast | BRAVO | Current |
|---|---|---|---|---|
| **Date** | Ngày HT, Ngày CT | Ngày HT, Ngày CT | Ngày CT, Ngày HT | ❌ Missing |
| **Voucher number** | Auto-numbered | Auto-numbered | Auto-numbered | ✅ Auto-numbered |
| **Transaction type** | ✅ Dropdown (Thu học phí, BHYT, etc.) | ✅ 15+ payment types | ✅ Per-form type | ❌ **Missing** |
| **Payer/Payee** | ✅ Customer/Employee selector | ✅ Customer/Supplier selector | ✅ Customer/Employee selector | ❌ **Missing** |
| **Amount** | ✅ | ✅ | ✅ | ✅ |
| **Debit account** | Auto (111) | Auto (111) | Auto (111) | ✅ Hardcoded 111 |
| **Credit account** | ✅ Auto-suggested by type | ✅ Filtered by type | ✅ Filtered by type | ❌ **62 raw accounts** |
| **Description** | ✅ Lý do nộp | ✅ Diễn giải | ✅ Lý do nộp | ✅ |
| **Reference docs** | ✅ Invoice/Contract ref | ✅ Invoice/PO ref | ✅ Invoice/Contract ref | ❌ **Missing** |
| **Customer/Supplier** | ✅ Linked to AR/AP | ✅ Linked to AR/AP | ✅ Linked to AR/AP | ❌ **Missing** |
| **Project/Cost center** | ✅ Optional | ✅ Optional | ✅ Optional | ❌ **Missing** |
| **VAT tracking** | ✅ Input/Output VAT | ✅ Full VAT sub-table | ✅ VAT fields | ❌ **Missing** |
| **Foreign currency** | ✅ | ✅ | ✅ | ✅ (via FC module) |
| **Attachment** | ✅ File upload | ✅ | ✅ Image/File | ❌ **Missing** |

## 2. Account Selection Pattern

| Product | How it works |
|---|---|
| **MISA** | User selects transaction type (e.g., "Thu từ khách hàng") → system auto-fills Nợ 111, Có 131. User can override. Dropdown shows ~10-15 relevant accounts, not 129. |
| **Fast** | Payment type selector with 15+ pre-defined types. Each type has default debit/credit accounts. Account picker filtered by transaction nature. |
| **BRAVO** | Form-specific with configurable default accounts. User can pick from filtered list. Supports multi-line entries (multiple debit/credit lines per voucher). |
| **Current** | No transaction type. No auto-fill. Shows 62 unfiltered accounts. User must know which account to pick. |

## 3. Workflow Comparison

| Step | MISA | Fast | BRAVO | Current |
|---|---|---|---|---|
| Create | Modal form | Full-page form | Desktop dialog | Modal form ✅ |
| Save as draft | ✅ (Cất) | ✅ | ✅ | ❌ **Missing** |
| Post (Ghi sổ) | ✅ Separate action | ✅ On save | ✅ On save | ✅ On submit |
| Unpost (Bỏ ghi) | ✅ | ✅ | ✅ | ❌ **Missing** |
| Edit after post | Must unpost first | Must unpost first | Must unpost first | ❌ **Not possible** |
| Delete | Only if unposted | Only if unposted | Only if unposted | ✅ Only before post |
| Print form | ✅ Customizable template | ✅ Customizable | ✅ Customizable | ❌ **Missing** |

## 4. Counterparty Account Filtering (TK Đối Ứng)

| Product | How accounts are filtered |
|---|---|
| **MISA** | Based on transaction type. For "Thu từ khách hàng" → only AR accounts (131). For "Thu bán hàng" → revenue accounts (511, 515). Plus user can override. |
| **Fast** | Based on payment type. Payment types grouped by nature: "Chi trả nhà cung cấp" → AP (331), "Chi phí" → expense accounts (641, 642), etc. |
| **BRAVO** | Configurable per document type. User can define which account types appear for each voucher type. |
| **Current** | One-size-fits-all: 62 accounts for receipt, 66 for payment. No transaction type context. **Not compliant.** |

## 5. Standard Phiếu Thu Form Layout (Consolidated)

```
┌─────────────────────────────────────────────┐
│            PHIẾU THU TIỀN MẶT                │
│              (Cash Receipt Voucher)           │
├─────────────────────────────────────────────┤
│  Số CT: [auto]    Ngày: [date picker]        │
│  Loại thu: [dropdown: Thu KH, Bán hàng,...]  │
│  Người nộp: [customer/employee search]       │
│  Địa chỉ: [auto from customer]               │
│  Lý do: [text]                                │
├─────────────────────────────────────────────┤
│  TK Nợ: 1111 (Tiền mặt VND)     [fixed]      │
│  TK Có: [filtered dropdown]                  │
│  Số tiền: [amount]                            │
│  Đối tượng: [customer/ supplier/ employee]   │
│  Hợp đồng/Dự án: [optional]                  │
│  Kèm theo: [reference docs]                  │
├─────────────────────────────────────────────┤
│  [Cất] [Ghi sổ] [In] [Hủy]                   │
└─────────────────────────────────────────────┘
```

## 6. Gaps Analysis: Current vs Standard

| # | Requirement | Standard | Current | Impact |
|---|---|---|---|---|
| G1 | Transaction type selector | ✅ Required | ❌ Missing | User must hand-pick from 62 accounts |
| G2 | Payer/payee field | ✅ Required | ❌ Missing | No AR/AP integration |
| G3 | Auto-default accounts by type | ✅ Required | ❌ Missing | Error-prone manual entry |
| G4 | Post/Unpost workflow | ✅ Required | ❌ Missing (no unpost) | Can't correct mistakes |
| G5 | Print form | ✅ Required | ❌ Missing | Can't produce paper voucher |
| G6 | Reference document linking | ✅ Required | ❌ Missing | No audit trail to source |
| G7 | VAT tracking | ✅ Required | ❌ Missing | Tax compliance issue |
| G8 | Project/contract tracking | ✅ Optional | ❌ Missing | Management reporting gap |
| G9 | Draft/save state | ✅ Required | ❌ Missing | User must complete in one go |
| G10 | Customer/Supplier auto-fill | ✅ Expected | ❌ Missing | Manual data entry |
| G11 | Multi-line entries | ✅ Expected | ❌ Single line | Can't split to multiple accounts |
| G12 | Account filtered by transaction type | ✅ Required | ❌ 62 accounts | Not compliant with standard UX |

## 7. Key Findings

1. **Transaction type selector is the #1 missing feature.** Every standard product has it. Without it, the user doesn't know which account to pick, and the dropdown of 62 accounts is overwhelming.

2. **Payer/payee field is #2.** Cash receipts come from someone (customer, employee, other). Cash payments go to someone (supplier, employee, tax authority). Without this field, you can't link to AR/AP sub-ledgers.

3. **Post/Unpost workflow is #3.** In all three products, posting is a separate explicit action. Our app posts immediately on save, which means no way to correct a mistake without deleting and re-entering.

4. **62 accounts for receipt is still too many.** MISA shows ~10-15 when a transaction type is selected. Fast shows relevant accounts per payment type. Our current 62 is "better than 129" but still far from standard.

5. **Print is mandatory for legal compliance.** Circular 99 requires paper vouchers for tax inspection. Our app has no print function for any voucher.

## 8. Verdict: Does Current Implementation Match Standard?

| Module | Standard Compliance | Assessment |
|---|---|---|
| Cash Receipt (Phiếu thu) | ❌ **Not compliant** | Missing: transaction type, payer field, post workflow, print, account filtering |
| Cash Payment (Phiếu chi) | ❌ **Not compliant** | Same gaps as receipt |
| Bank Receipt (GBC) | ❌ **Not compliant** | Same gaps |
| Bank Payment (GBN) | ❌ **Not compliant** | Same gaps |
| Account picker (TK ĐƯ) | ❌ **Not compliant** | 62 accounts vs standard ~10-15 filtered |

**Overall: NOT compliant with market standard.** The current form is a minimal skeleton compared to MISA/Fast/BRAVO. While the accounting engine (double-entry, period guard, audit log) is solid, the user-facing data entry forms lack the workflow and guidance that Vietnamese accountants expect.

## 9. Priority Recommendations

1. **Add transaction type selector** — Dropdown with common transaction natures. Each type defines default Nợ/Có accounts.
2. **Add payer/payee field** — Searchable customer/supplier/employee selector linked to AR/AP.
3. **Implement Post/Unpost** — Separate "save draft" from "post". Allow correction via unpost.
4. **Filter accounts by transaction type** — Show only relevant accounts (5-15) instead of 62.
5. **Add print function** — Generate paper voucher matching Circular 99 format.
6. **Add reference document field** — Link to invoice, PO, contract number.
7. **Add VAT tracking for payments** — Input VAT sub-table for expense payments.
