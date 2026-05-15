# Implementation Roadmap — Treasury UX Enhancement

**Version:** 1.0
**Last Updated:** 2026-05-15
**Base Spec:** `docs/specs/TREASURY_USE_CASE_SPECIFICATION.md`

---

## Current State Assessment

| Feature | Status | Detail |
|---|---|---|
| Account picker filter | ✅ Fixed | 126 accounts, excludes cash/control/911 |
| Cash receipt (generic) | ✅ | `recordReceipt(amount, creditAccount)` |
| Cash payment (generic) | ✅ | `recordPayment(amount, debitAccount)` |
| Bank receipt (generic) | ✅ | `recordBankReceipt(amount, creditAccount)` |
| Bank payment (generic) | ✅ | `recordBankPayment(amount, debitAccount)` |
| Cash in transit | ✅ | Covered |
| Bank reconciliation | ✅ | Covered |
| **Transaction-type selector** | ❌ | User picks from 126 accounts manually |
| **Auto-fill Dr/Cr by transaction type** | ❌ | No pre-defined journal templates |
| **VAT auto-calculation** | ❌ | User enters VAT amount manually |
| **UI per transaction type** | ❌ | Same generic form for all types |

---

## Implementation Plan

### Phase 1: Transaction-Type Selector for Cash Receipt (1.5 days)

**Goal:** Replace the flat account dropdown with a transaction-type selector that auto-fills the credit account and handles VAT correctly per Circular 99.

| Transaction Type | Dr | Cr | VAT handling | UC |
|---|---|---|---|---|
| Thu tiền bán hàng | 111 | 511 | + Cr 33311 (auto from rate) | UC-001 |
| Thu hồi công nợ | 111 | 131 | No VAT | UC-003 |
| Thu nhập tài chính | 111 | 515 | + Cr 33311 if applicable | UC-002 |
| Thu nhập khác | 111 | 711 | + Cr 33311 if applicable | UC-002 |
| Rút tiền NH về quỹ | 111 | 112 | No VAT | UC-020 |
| Nhận vốn góp | 111 | 4111 | No VAT | UC-004 |
| Bán đầu tư | 111 | 121/228 | Gain/loss auto-calc | UC-005 |
| Thu ký quỹ/ký cược | 111 | 344 | No VAT | UC-003 |
| Nhận trợ cấp NN | 111 | 3339 | No VAT | UC-006 |

**Files to modify:**

| File | Change |
|---|---|
| `public/views/cash_receipts.php` | Add transaction-type dropdown. Show/hide VAT field based on type. Auto-fill credit account. |
| `src/Accounting/Interfaces/HTTP/CashController.php` | Add `receiptTemplates()` endpoint returning templates per type. |
| `tests/CashTest.php` | Add tests for each transaction type. |

---

### Phase 2: Transaction-Type Selector for Cash Payment (1.5 days)

**Goal:** Same pattern for cash payment forms.

| Transaction Type | Dr | Cr | VAT handling | UC |
|---|---|---|---|---|
| Mua hàng tồn kho | 152/156 | 111 | + Dr 1331 (auto from rate) | UC-010 |
| Mua TSCĐ | 211/213 | 111 | + Dr 1331 if applicable | UC-010 |
| Chi phí SXKD | 621/627/641/642 | 111 | + Dr 1331 if applicable | UC-011 |
| Thanh toán NCC | 331 | 111 | No VAT (already in invoice) | UC-012 |
| Nộp thuế | 333 | 111 | No VAT | UC-012 |
| Trả lương | 334 | 111 | No VAT | UC-012 |
| Trả vay | 341 | 111 | No VAT | UC-012 |
| Mua đầu tư | 121/128/221 | 111 | No VAT | UC-014 |
| Chi phí tài chính | 635 | 111 | + Dr 1331 if applicable | UC-013 |
| Gửi tiền NH | 112 | 111 | No VAT | UC-019 |
| Ký quỹ/ký cược | 244 | 111 | No VAT | UC-021 |
| Tạm ứng | 141 | 111 | No VAT | UC-003 |

**Files to modify:**

| File | Change |
|---|---|
| `public/views/cash_payments.php` | Add transaction-type dropdown. Auto-fill debit account. |
| `tests/CashTest.php` | Add tests. |

---

### Phase 3: Bank Transaction-Type Selectors (1.5 days)

**Goal:** Apply same pattern to Giấy báo Có (bank receipt) and Giấy báo Nợ (bank payment).

**Bank Receipt types:**
- Khách hàng thanh toán (Dr 112 — Cr 131)
- Thu tiền bán hàng (Dr 112 — Cr 511 + Cr 33311)
- Thu nhập tài chính/khác (Dr 112 — Cr 515/711)
- Nhận vốn góp (Dr 112 — Cr 4111)
- Thu hồi đầu tư (Dr 112 — Cr 121/228)
- Tiền gửi từ quỹ (Dr 112 — Cr 111)

**Bank Payment types:**
- Thanh toán NCC (Dr 331 — Cr 112)
- Mua hàng (Dr 152/156 — Cr 112)
- Chi phí (Dr 642/641 — Cr 112)
- Trả lương (Dr 334 — Cr 112)
- Nộp thuế (Dr 333 — Cr 112)
- Mua đầu tư (Dr 121/221 — Cr 112)
- Rút quỹ tiền mặt (Dr 111 — Cr 112)
- Ký quỹ (Dr 244 — Cr 112)
- Trả cổ tức (Dr 332 — Cr 112)

---

### Phase 4: Backend Refactor — Typed Service Methods (2 days)

**Goal:** Replace generic methods with typed methods that enforce correct accounts and reduce user error. Current generic methods remain for API backward compatibility.

| Method | Internal logic |
|---|---|
| `recordCashSale(amount, vatRate, customer)` | Dr 111 — Cr 511 + Cr 33311 |
| `recordCashARPayment(amount, customerId, invoiceId)` | Dr 111 — Cr 131 |
| `recordCashExpense(amount, expenseAccount, vatAmount)` | Dr 642/641 + Dr 1331 — Cr 111 |
| `recordCashSupplierPayment(amount, supplierId, invoiceId)` | Dr 331 — Cr 111 |
| `recordCashInventoryPurchase(amount, inventoryAccount, vatAmount)` | Dr 152/156 + Dr 1331 — Cr 111 |
| `recordBankARPayment(amount, customerId, invoiceId)` | Dr 112 — Cr 131 |
| `recordBankSupplierPayment(amount, supplierId, invoiceId)` | Dr 331 — Cr 112 |

---

### Phase 5: Tests + Docs (1 day)

| Task | Effort |
|---|---|
| Add tests for each typed method | 0.5d |
| Update PROGRESS.md | 0.25d |
| Update treasury UC doc with implementation notes | 0.25d |

---

## Effort Summary

| Phase | Module | Days |
|---|---|---|
| P1 | Cash Receipt transaction-type selector | 1.5 |
| P2 | Cash Payment transaction-type selector | 1.5 |
| P3 | Bank Receipt + Payment transaction-type selectors | 1.5 |
| P4 | Backend typed methods | 2 |
| P5 | Tests + docs | 1 |
| **Total** | | **7.5 days** |

---

## Go-Live Minimum

**Phase 1 alone (1.5 days)** provides immediate UX improvement for cash receipt — the most common daily transaction. Phases 2-4 add depth but P1 alone addresses the core feedback: "I don't know which account to pick."

**Recommendation:** Implement P1 first, validate with user, then proceed P2 → P3 → P4.
