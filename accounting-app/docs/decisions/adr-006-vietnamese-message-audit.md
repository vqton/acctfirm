# ADR-006: Vietnamese Language Audit — Toàn bộ thông báo người dùng bằng tiếng Việt

## Status
Accepted

## Date
2026-05-27

## Context
Hệ thống kế toán doanh nghiệp Việt Nam có người dùng cuối là kế toán, thủ quỹ, nhân viên kho, nhân viên lương — tất cả đều là người Việt. Trước đây, nhiều thông báo lỗi, thông báo xác nhận, và exception messages vẫn ở dạng tiếng Anh hoặc tiếng Việt chưa chuẩn:

- `"Supplier not found"` thay vì `"Không tìm thấy nhà cung cấp"`
- `"Invoice already paid"` thay vì `"Hóa đơn này đã được thanh toán"`
- `"Ghi nhận báo Có thành công"` — chưa rõ ràng, thiếu "giấy"
- Confirmation dialog còn chung chung: `"Xóa?"`, `"Đóng kỳ lương này?"`

Điều này gây ra:
1. Nhầm lẫn nghiệp vụ do dùng sai thuật ngữ kế toán
2. Người dùng không hiểu thông báo lỗi → gọi điện hỗ trợ → tăng chi phí vận hành
3. Rủi ro audit trail khi user thao tác sai vì không hiểu thông báo
4. Ấn tượng sản phẩm không chuyên nghiệp

## Decision
Chuẩn hóa toàn bộ thông báo người dùng cuối sang tiếng Việt chuẩn mực, theo các nguyên tắc:

1. **Dùng đúng thuật ngữ kế toán Việt Nam:** "hóa đơn", "chứng từ", "bút toán", "ghi sổ", "khóa sổ", "dự phòng phải thu khó đòi", "đối chiếu ngân hàng", "phiếu thu/chi", "báo Có/Nợ"
2. **Thông báo có cấu trúc rõ ràng:** Mô tả vấn đề → Hướng dẫn xử lý (nếu recoverable)
3. **Consistent tone:** Lịch sự, chuyên nghiệp, dễ hiểu
4. **Bảo toàn thông tin nghiệp vụ:** Không làm mất thông tin về mã chứng từ, số tiền, tài khoản kế toán
5. **Message classification:** Validation, blocking error, recoverable error, success, confirmation

### Phạm vi
- All `JsonResponse::error()` messages trong Controllers
- All `throw new \InvalidArgumentException()` messages trong Services
- All `showToast()` messages trong Views
- All confirmation dialogs (`confirm()` calls)
- Success messages sau mỗi thao tác

### Không thay đổi
- Code identifiers (tên biến, tên hàm, tên class) — giữ tiếng Anh
- Database column names và table names
- API route paths
- Log messages dành cho developer (debug/info)
- `JsonResponse::ok()` data payloads

## Scope
- ~57 files modified
- ~200+ messages translated/improved
- 0 test regressions (49 test files, 0 failures)

## Alternatives Considered
- **Giữ nguyên tiếng Anh:** Không phù hợp với người dùng kế toán Việt Nam. Rủi ro sai sót nghiệp vụ.
- **Dịch máy (Google Translate):** Không đảm bảo đúng thuật ngữ kế toán. VD: "write off" dịch máy ra "viết tắt" thay vì "xóa sổ".
- **Chỉ dịch một phần:** Gây inconsistency, người dùng khó hiểu khi message lẫn tiếng Anh-tiếng Việt.

## Consequences
Positive:
- Giảm confusion cho người dùng cuối
- Giảm support calls
- Cải thiện ấn tượng chuyên nghiệp của sản phẩm
- Audit trail rõ ràng hơn khi user hiểu đúng thông báo
- Dễ dàng maintain messages tập trung

Negative:
- Phải maintain cả 2 ngôn ngữ nếu sau này có multi-language
- Tốn effort review để đảm bảo đúng thuật ngữ kế toán

## File Statistics

| Layer | Files | Messages |
|---|---|---|
| Controllers (Auth, MasterData, Payroll, Financial, Cash, Inventory) | ~23 | ~78 |
| Domain Services (ApService, ArService, CashService, PeriodService, PayrollService, InventoryService, GlService) | ~6 | ~70 |
| Models (Transaction, LedgerEntry) | ~2 | ~8 |
| Views (main, cash, inventory, payroll, journal, roles, users) | ~22 | ~50 |

## Key Terminology Decisions

| English (từ cũ) | Vietnamese (từ mới) | Ghi chú |
|---|---|---|
| supplier | nhà cung cấp | Thuật ngữ kế toán chuẩn |
| customer | khách hàng | |
| invoice | hóa đơn | |
| payment | thanh toán / thu tiền | Phân biệt AP (trả) và AR (thu) |
| write off | xóa sổ | Không dùng "xóa" đơn thuần |
| credit limit | hạn mức tín dụng | |
| aging report | phân tích tuổi nợ | |
| provision | dự phòng phải thu khó đòi | Theo TT 48/2019/TT-BTC |
| bank credit memo | giấy báo Có | |
| bank debit memo | giấy báo Nợ | |
| transit | tiền đang chuyển / hàng đi đường | Phân biệt cash vs inventory |
| impairment | dự phòng giảm giá | Hàng tồn kho |
| physical count | kiểm kê | |
| reconciliation | đối chiếu | |
| close period | khóa sổ | Không dùng "đóng kỳ" |
| post entry | ghi sổ | |
