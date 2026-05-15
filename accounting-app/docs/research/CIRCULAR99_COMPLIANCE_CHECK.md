# Compliance Check: Circular 99/2025/TT-BTC — Cash Receipt/Payment

## Mẫu 01-TT (Phiếu thu) — Required Fields

| # | Field | Required | Current | Status |
|---|---|---|---|---|
| 1 | Tên đơn vị, địa chỉ | ✅ | ❌ Not on form | **FAIL** |
| 2 | Số quyển, số phiếu | ✅ Sequential per period | ✅ Auto-numbered | ✅ |
| 3 | Ngày tháng năm lập | ✅ | ❌ No date picker | **FAIL** |
| 4 | Họ tên người nộp tiền | ✅ | ❌ Missing | **FAIL** |
| 5 | Địa chỉ người nộp | ✅ | ❌ Missing | **FAIL** |
| 6 | Lý do nộp (diễn giải) | ✅ | ✅ Description field | ✅ |
| 7 | Số tiền (bằng số) | ✅ | ✅ Amount field | ✅ |
| 8 | Số tiền (bằng chữ) | ✅ | ❌ Missing | **FAIL** |
| 9 | Số CT gốc kèm theo | ✅ | ❌ Missing | **FAIL** |
| 10 | Chữ ký Người lập phiếu | ✅ | ❌ Only created_by | **FAIL** |
| 11 | Chữ ký Kế toán trưởng | ✅ | ❌ | **FAIL** |
| 12 | Chữ ký Giám đốc | ✅ | ❌ | **FAIL** |
| 13 | Chữ ký Thủ quỹ | ✅ | ❌ | **FAIL** |
| 14 | Chữ ký Người nộp tiền | ✅ | ❌ | **FAIL** |
| 15 | Tỷ giá ngoại tệ (nếu có) | If FC | Separate FC module | ⚠️ |
| 16 | In thành 3 liên | ✅ | ❌ No print | **FAIL** |

## Mẫu 02-TT (Phiếu chi) — Required Fields

Same structure, replacing "Người nộp" with "Người nhận tiền". Identical gaps.

## Article 9 — Self-Designed Forms

Circular 99 **permits** enterprises to design their own forms, provided:
> Điều 9: Doanh nghiệp được thiết kế thêm hoặc sửa đổi, bổ sung biểu mẫu chứng từ
> ... phải đảm bảo tuân thủ quy định tại Điều 16 Luật Kế toán 2015
> ... phải phản ánh đầy đủ, kịp thời, trung thực, minh bạch
> ... dễ kiểm tra, kiểm soát và đối chiếu
> ... phải ban hành Quy chế hạch toán kế toán

This means our form can be digital and simplified. But we must:
1. Capture all essential transactional data (amount, accounts, counterparty, date)
2. Be able to print/reconstruct the official form when needed
3. Have an internal accounting regulation (Quy chế hạch toán) documenting our form design

## Verdict: Minimum Viable Compliance

| Requirement | Can we pass? | What's needed |
|---|---|---|
| Transaction recorded correctly | ✅ Dr/Cr, amount, period guard all work | Nothing |
| Audit trail | ✅ Journal + audit log | Nothing |
| Voucher numbering | ✅ Sequential auto-number | Nothing |
| **Payer/payee name** | ❌ Must add | Add customer/supplier/employee field |
| **Amount in words** | ❌ Must add | Add toVnWords() display |
| **Date field** | ❌ Must add | Add date picker to form |
| **Print form (Mẫu 01-TT)** | ❌ Must add | Print template matching Circular 99 |
| **Signature workflow** | ❌ Must add | At minimum: created_by + approved_by + cashier |
| **Reference docs** | ❌ Must add | Free-text field for document reference |
| **Accounting regulation** | ❌ Must create | Internal document per Article 9 |

## Priority for Compliance

1. **Add date picker** — 1 line of HTML. Without it, transaction date is ambiguous.
2. **Add amount in words** — `Helpers::toVnWords()` already exists. Just need to display it.
3. **Add payer/payee field** — Required by Circular 99 and basic audit trail.
4. **Add print function** — Generate PDF matching Mẫu 01-TT/02-TT format. Required for tax inspection.
5. **Add signature fields** — Digital signature or at minimum 3-level approval (lập → kiểm soát → duyệt).
6. **Create Quy chế hạch toán** — One-page document formalizing our digital form design per Article 9.

## Bottom Line

**Not compliant today.** But the gap is narrow — 4 fields + print + signature workflow. The accounting engine (double-entry, audit log, period guard) is solid. The form just needs the missing mandatory fields to meet Circular 99 standards.
