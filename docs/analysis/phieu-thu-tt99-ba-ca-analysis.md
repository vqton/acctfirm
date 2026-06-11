# Phiếu thu (Mẫu 01-TT) — BA/CA Analysis v1.0

> **BA:** 20k giờ phân tích nghiệp vụ kế toán Việt Nam
> **CA:** 20k giờ hạch toán doanh nghiệp theo TT 99
> **Ngày:** 2026-06-10
> **Trạng thái:** DRAFT — chờ review Kế toán trưởng

---

## Mục lục

1. [Executive Summary](#1-executive-summary)
2. [BRD — Business Requirements Document](#2-brd)
3. [Use Cases](#3-use-cases)
4. [Happy Paths](#4-happy-paths)
5. [Alternative Paths](#5-alternative-paths)
6. [Business Processes](#6-business-processes)
7. [Business Rules](#7-business-rules)
8. [Data Flow](#8-data-flow)
9. [Workflow](#9-workflow)
10. [User Journey](#10-user-journey)
11. [Current Implementation Audit vs TT99](#11-current-implementation-audit-vs-tt99)
12. [Gap Analysis](#12-gap-analysis)
13. [Implementation Roadmap](#13-implementation-roadmap)

---

## 1. Executive Summary

### 1.1 Can it operate in PROD?

| Tiêu chí | Đánh giá | Chi tiết |
|----------|----------|----------|
| Chức năng (CRUD) | ✅ PASS | Tạo, sửa, xóa, in phiếu thu |
| Dr = Cr invariant | ✅ PASS | Qua JournalService |
| Posting rules | ✅ PASS | Kiểm tra posting_rules table |
| Period lock | ✅ PASS | PeriodService::isPeriodOpen() |
| VAT splitting | ✅ PASS | 1331/33311 handled |
| Payer search | ✅ PASS | Autocomplete từ customers/suppliers/employees |
| Số chứng từ tự động | ✅ PASS | VoucherService |
| **Tuân thủ TT99 Mẫu 01-TT** | ❌ FAIL | 4 gaps (xem §11) |

**Kết luận:** Có thể vận hành PROD cho nội bộ (nghiệp vụ thu tiền vẫn hoạt động). **Không đủ tuân thủ** nếu cần in phiếu thu theo đúng mẫu TT99 cho kiểm toán/thuế.

### 1.2 TT99 Regulatory Context

- **Thông tư:** 99/2025/TT-BTC ngày 27/10/2025
- **Mẫu số:** 01-TT (Phụ lục I)
- **Hiệu lực:** 01/01/2026
- **Thay thế:** Mẫu 01-TT của TT 200/2014/TT-BTC
- **Áp dụng:** Mọi doanh nghiệp Việt Nam (CĐKT theo TT99)

### 1.3 Scope

- IN: Thu tiền mặt VND, thu ngoại tệ (FC), in phiếu thu
- IN: Thu từ bán hàng, tạm ứng, thu hồi công nợ, thu khác
- OUT: Thu qua ngân hàng (Xem riêng: Mẫu 04-TT — Báo Có NH)
- OUT: Thu hóa đơn điện tử (E-invoice module)

---

## 2. BRD — Business Requirements Document

### 2.1 Functional Requirements

| ID | Requirement | Priority | Source |
|----|-------------|----------|--------|
| FR01 | Tạo Phiếu thu với đầy đủ thông tin theo Mẫu 01-TT | P0 | TT99 Phụ lục I |
| FR02 | Đánh số phiếu thu tự động theo năm | P0 | §14.76 AGENTS.md |
| FR03 | Ghi nhận bút toán Nợ 1111/Có TK đối ứng | P0 | Nghiệp vụ kế toán |
| FR04 | Kiểm tra Dr = Cr trước khi ghi sổ | P0 | §7.4 AGENTS.md |
| FR05 | Kiểm tra kỳ kế toán đang mở | P0 | §7.5 AGENTS.md |
| FR06 | Hỗ trợ thu ngoại tệ (quy đổi VND) | P0 | TT99 §39 |
| FR07 | In Phiếu thu theo đúng Mẫu 01-TT | P0 | TT99 Phụ lục I |
| FR08 | Tách VAT đầu ra (33311) khi thu tiền bán hàng có VAT | P0 | TT99 |
| FR09 | Tra cứu người nộp (KH/NCC/NV) | P1 | UX |
| FR10 | Lưu địa chỉ người nộp | P1 | TT99 Mẫu 01-TT |
| FR11 | Cho phép sửa phiếu thu (chỉ draft) | P1 | Nghiệp vụ |
| FR12 | Hủy phiếu thu đã ghi sổ (bút toán đảo) | P1 | §3.4 AGENTS.md |
| FR13 | Theo dõi số lượng chứng từ gốc kèm theo | P2 | TT99 Mẫu 01-TT |
| FR14 | Ký số/Giấy (Giám đốc, KTT, Thủ quỹ) | P2 | TT99 Mẫu 01-TT |
| FR15 | Gửi email phiếu thu | P3 | UX |

### 2.2 Non-Functional Requirements

| ID | Requirement | Standard |
|----|-------------|----------|
| NFR01 | Response time < 2s cho 95% requests | Performance |
| NFR02 | Audit trail cho mọi thao tác | §10.3 AGENTS.md |
| NFR03 | Phân quyền theo RBAC (read/create/post/delete) | §10.2 AGENTS.md |
| NFR04 | Tối thiểu 1 bản sao lưu/tồn tại (audit) | Compliance |
| NFR05 | CSRF protection cho POST/PUT/DELETE | §9.4 AGENTS.md |

---

## 3. Use Cases

### UC-01: Thu tiền bán hàng trực tiếp

| Field | Value |
|-------|-------|
| Actor | Kế toán viên (Cashier) |
| Trigger | Khách hàng đến nộp tiền mua hàng |
| Pre-condition | Có hóa đơn bán hàng, hàng đã xuất kho |
| Post-condition | Phiếu thu posted, công nợ 131 giảm/doanh thu 511 tăng |

**Journal entry:**
```
Nợ 1111 (Tiền mặt VND): 11,000,000
Có 511 (Doanh thu bán hàng): 10,000,000
Có 33311 (Thuế GTGT đầu ra): 1,000,000
```

### UC-02: Thu hồi tạm ứng

| Field | Value |
|-------|-------|
| Actor | Kế toán viên |
| Trigger | Nhân viên hoàn tạm ứng còn thừa |
| Pre-condition | Có giấy thanh toán tạm ứng đã duyệt |
| Post-condition | Phiếu thu posted, tạm ứng 141 giảm |

**Journal entry:**
```
Nợ 1111 (Tiền mặt VND): 5,000,000
Có 141 (Tạm ứng): 5,000,000
```

### UC-03: Thu hồi công nợ phải thu (KH trả nợ)

| Field | Value |
|-------|-------|
| Actor | Kế toán viên |
| Trigger | Khách hàng đến trả tiền nợ |
| Pre-condition | Có công nợ 131 chưa thu hồi |
| Post-condition | Phiếu thu posted, công nợ 131 giảm |

**Journal entry:**
```
Nợ 1111 (Tiền mặt VND): 20,000,000
Có 131 (Phải thu KH): 20,000,000
```

### UC-04: Thu ngoại tệ (USD)

| Field | Value |
|-------|-------|
| Actor | Kế toán viên |
| Trigger | KH nộp USD |
| Pre-condition | Có tỷ giá tại thời điểm thu |
| Post-condition | Phiếu thu (kèm Bảng kê ngoại tệ), ghi nhận FC |

**Journal entry:**
```
Nợ 1112 (Tiền mặt USD) [USD]: $1,000
Nợ 1112 (Tiền mặt VND) [VND]: 25,450,000 (tỷ giá 25,450)
Có 131 (Phải thu KH): 25,450,000
```

### UC-05: Thu từ các nguồn khác (cổ tức, lãi tiền gửi, thanh lý TSCĐ)

| Field | Value |
|-------|-------|
| Actor | Kế toán viên |
| Trigger | Phát sinh khoản thu bất thường |
| Pre-condition | Có chứng từ gốc hợp lệ |
| Post-condition | Phiếu thu posted |

**Journal entry:**
```
Nợ 1111: 50,000,000
Có 515 (Doanh thu tài chính): 50,000,000
(hoặc Có 711 - Thu nhập khác)
```

### UC-06: In phiếu thu

| Field | Value |
|-------|-------|
| Actor | Kế toán viên / Kế toán trưởng |
| Trigger | Cần in phiếu thu cho người nộp giữ/lưu trữ |
| Pre-condition | Phiếu thu đã posted |
| Post-condition | Phiếu in theo đúng Mẫu 01-TT |

---

## 4. Happy Paths

### HP-01: Thu tiền mặt VND (cơ bản)

1. KTV mở màn hình Phiếu thu
2. Hệ thống tự sinh số phiếu thu (PT2026-000001)
3. KTV chọn loại thu từ danh sách mẫu (hoặc tự nhập)
4. Hệ thống tự động điền TK Có mặc định theo loại thu
5. KTV nhập số tiền (VD: 10,000,000)
6. Hệ thống tự động tính VAT (nếu có) + hiển thị số tiền bằng chữ
7. KTV tìm kiếm người nộp (KH/NCC/NV) — hệ thống tự động điền tên + địa chỉ
8. KTV nhập diễn giải, số chứng từ gốc kèm theo
9. KTV nhấn "Lưu" → hệ thống validate:
   - TK Có tồn tại, không phải control account
   - Số tiền > 0
   - Kỳ kế toán còn mở
   - Dr = Cr
10. Hệ thống ghi nhận bút toán, tạo phiếu thu (status = posted)
11. Hệ thống ghi audit trail + action journal
12. Hệ thống hiển thị phiếu thu vừa tạo, cho phép in

### HP-02: Thu tiền từ thu hồi tạm ứng (có Giấy thanh toán tạm ứng)

1-3. Như HP-01
4. KTV chọn mẫu "Thu hồi tạm ứng" → TK Có = 141
5. KTV chọn nhân viên từ danh sách (người nộp)
6. KTV nhập số tiền
7. Hệ thống ghi nhận bút toán Nợ 1111/Có 141
8. KTV in phiếu thu (2 liên: 1 cho nhân viên, 1 lưu)

### HP-03: Thu ngoại tệ (USD)

1-3. Như HP-01
4. KTV chọn "Ngoại tệ" → form hiển thị thêm trường:
   - Loại ngoại tệ (USD/EUR/...)
   - Số ngoại tệ
   - Tỷ giá (tự động lấy từ ExchangeRateService, có thể sửa tay)
5. Hệ thống tự động tính số tiền VND quy đổi
6. Hệ thống kiểm tra Bảng kê ngoại tệ kèm theo (bắt buộc)
7. KTV nhập số Bảng kê ngoại tệ
8. Hệ thống ghi nhận bút toán Nợ 1112 (FC+VND) / Có TK đối ứng

---

## 5. Alternative Paths

### AP-01: Số dư âm — từ chối

| Step | Mô tả |
|------|-------|
| 1 | KTV nhập số tiền > số dư còn lại của TK Có |
| 2 | Hệ thống báo lỗi "Số dư tài khoản [code] không đủ" |
| 3 | KTV sửa lại số tiền hoặc chọn TK khác |

### AP-02: Control account — tự động chuyển TK con

| Step | Mô tả |
|------|-------|
| 1 | KTV chọn TK 111 (tổng hợp) thay vì 1111 |
| 2 | Hệ thống báo lỗi: "TK 111 là tài khoản tổng hợp. Vui lòng chọn TK chi tiết." |
| 3 | Hệ thống đề xuất TK con (1111, 1112, 1113) |
| 4 | KTV chọn TK con |

### AP-03: Kỳ kế toán đã đóng — từ chối

| Step | Mô tả |
|------|-------|
| 1 | KTV nhập ngày thuộc kỳ đã đóng |
| 2 | Hệ thống báo lỗi "Kỳ kế toán [period] đã đóng. Vui lòng chọn kỳ khác." |
| 3 | KTV sửa ngày hoặc liên hệ Kế toán trưởng để mở kỳ |

### AP-04: Sửa phiếu thu đã posted — bắt buộc hủy + tạo mới

| Step | Mô tả |
|------|-------|
| 1 | KTV sửa phiếu thu đã posted |
| 2 | Hệ thống báo lỗi "Phiếu thu đã ghi sổ. Vui lòng hủy và tạo phiếu mới." |
| 3 | KTV xác nhận hủy → hệ thống tạo bút toán đảo |
| 4 | KTV tạo phiếu thu mới với thông tin đúng |

### AP-05: Người nộp không tồn tại trong hệ thống

| Step | Mô tả |
|------|-------|
| 1 | KTV tìm kiếm người nộp không có kết quả |
| 2 | KTV nhấn "Thêm người nộp mới" |
| 3 | Hệ thống mở modal thêm KH/NCC/NV nhanh |
| 4 | KTV nhập tên + địa chỉ + MST |
| 5 | Hệ thống lưu người nộp mới, tiếp tục tạo phiếu thu |

---

## 6. Business Processes

### 6.1 Quy trình thu tiền mặt

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│ Phát sinh   │────→│ KTV lập     │────→│ KTT/GĐ duyệt│
│ nghiệp vụ   │     │ Phiếu thu   │     │ (ký)        │
│ thu tiền    │     │             │     │             │
└─────────────┘     └─────────────┘     └─────────────┘
                                                  │
                                                  ↓
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│ Ghi sổ KT   │←────│ Thủ quỹ     │←────│ Nhập quỹ    │
│ (bút toán)  │     │ ký nhận     │     │ (đếm tiền)  │
└─────────────┘     └─────────────┘     └─────────────┘
       │
       ↓
┌─────────────┐     ┌─────────────┐
│ Lưu trữ     │←────│ In phiếu thu│
│ (3 liên)    │     │ (3 bản)     │
└─────────────┘     └─────────────┘
```

### 6.2 Quy trình xử lý ngoại tệ

```
Thu ngoại tệ
    │
    ├── Kiểm tra tỷ giá tại thời điểm thu
    │       (lấy từ ExchangeRateService hoặc NH)
    │
    ├── Lập Bảng kê ngoại tệ (Mẫu 07-TT) kèm Phiếu thu
    │
    └── Ghi nhận:
        - Nợ 1112 (FC): Số ngoại tệ
        - Nợ 1112 (VND): Số FC × tỷ giá
        - Có TK đối ứng: Số VND quy đổi
```

---

## 7. Business Rules

### 7.1 Validation Rules

| ID | Rule | Error Message | Severity |
|----|------|---------------|----------|
| VR01 | Số tiền > 0 | "Số tiền thu phải lớn hơn 0" | BLOCK |
| VR02 | TK Có phải là TK con (không tổng hợp) | "Tài khoản [code] là tài khoản tổng hợp" | BLOCK |
| VR03 | TK Có phải tồn tại trong COA | "Tài khoản [code] không tồn tại" | BLOCK |
| VR04 | Kỳ kế toán đang mở | "Kỳ kế toán [period] đã đóng" | BLOCK |
| VR05 | Ngày thu <= ngày hiện tại | "Ngày thu không được lớn hơn ngày hiện tại" | BLOCK |
| VR06 | Mọi khoản thu có VAT phải có hóa đơn GTGT | "Thu bán hàng có VAT bắt buộc kèm hóa đơn GTGT" | WARN |
| VR07 | Tổng Dr = Tổng Cr (sai lệch ±10 VND) | "Số phát sinh Nợ không khớp với số phát sinh Có" | BLOCK |
| VR08 | Ngoại tệ bắt buộc kèm Bảng kê ngoại tệ | "Thu ngoại tệ phải kèm Bảng kê ngoại tệ (Mẫu 07-TT)" | BLOCK |
| VR09 | Số phiếu thu tự động tăng trong kỳ | "Số phiếu thu không liên tục. Vui lòng kiểm tra." | WARN |
| VR10 | Tỷ giá > 0 (ngoại tệ) | "Tỷ giá phải lớn hơn 0" | BLOCK |

### 7.2 Posting Rules (Dr-Cr pairs)

| Dr | Cr | Module | Notes |
|----|----|--------|-------|
| 1111 | 511 | sales | Thu bán hàng (có VAT → thêm 33311) |
| 1111 | 131 | ar | Thu hồi công nợ KH |
| 1111 | 141 | advance | Thu hồi tạm ứng |
| 1111 | 331 | ap | Thu lại từ NCC (hoàn tiền) |
| 1111 | 515 | finance | Lãi tiền gửi/ cổ tức (thu bằng TM) |
| 1111 | 711 | other | Thu nhập khác (thanh lý TSCĐ...) |
| 1111 | 138 | other | Thu hồi ký quỹ, ký cược |
| 1111 | 338 | other | Thu hồi các khoản phải trả khác |
| 1111 | 411 | equity | Góp vốn bằng tiền mặt |
| 1111 | 344 | other | Nhận ký quỹ, ký cược bằng TM |
| 1112 | 511 | sales_fc | Thu bán hàng bằng ngoại tệ |
| 1112 | 131 | ar_fc | Thu hồi công nợ KH bằng ngoại tệ |

### 7.3 Control Account Protection

- 111 (Tiền mặt) → block, yêu cầu TK con (1111, 1112, 1113)
- 112 (TGNH) → block, yêu cầu TK con (1121, 1122)
- 131 (Phải thu KH) → block, yêu cầu TK con

### 7.4 VAT Splitting Rules

| VAT Rate | Nợ | Có | Có |
|----------|----|----|----|
| 0% | 1111: Giá bán | 511/131: Giá bán | — |
| 5% | 1111: Giá bán × (1+5%) | 511/131: Giá bán | 33311: VAT |
| 8% (giảm thuế) | 1111: Giá bán × (1+8%) | 511/131: Giá bán | 33311: VAT |
| 10% | 1111: Giá bán × (1+10%) | 511/131: Giá bán | 33311: VAT |

---

## 8. Data Flow

### 8.1 Current Architecture

```
Browser (cash_receipts.php - jQuery)
    │
    │ POST /api/cash/receipts {amount, credit_account_code, ...}
    ▼
CashController::createReceipt()
    │
    ├── Auth::requirePermission('cash', 'create')
    ├── Auth::checkCsrf()
    │
    ▼
CashService::recordReceipt($amount, $creditAccountCode, $description,
                            $reference, $createdBy, $vatAmount, $vatRate)
    │
    ├── PeriodService::isPeriodOpen($date)
    ├── AccountRepository::findByCode($creditAccountCode)
    ├── PostingRuleService::validate(1111, $creditAccountCode, $module)
    │
    ▼
JournalService::postEntry($ledgerEntries, ...)
    │
    ├── PDO::beginTransaction()
    ├── INSERT transactions
    ├── INSERT ledger_entries (x2 or x3 if VAT)
    ├── UPDATE voucher_sequences
    ├── AuditLogger::log()
    ├── PDO::commit()
    │
    ▼
JsonResponse::ok($transaction->toArray(), 201)
```

### 8.2 Data Model (Phiếu thu)

```
Phiếu thu (1)
    │
    ├── Số phiếu: PT-YYYY-NNNNNN (string)
    ├── Ngày: YYYY-MM-DD (date)
    ├── Người nộp: name + address + type [KH|NCC|NV] + id
    ├── Số tiền: DECIMAL(15,2)
    ├── Ngoại tệ: currency_code + exchange_rate + fc_amount (nullable)
    ├── VAT: vat_rate + vat_amount (nullable)
    ├── Diễn giải: text
    ├── Kèm theo: document_count (int)
    ├── Trạng thái: draft | posted | cancelled
    │
    └── Bút toán kế toán (1-n)
        ├── LedgerEntry 1: Dr 1111 - Số tiền
        ├── LedgerEntry 2: Cr TK đối ứng - Số tiền
        └── LedgerEntry 3 (nếu có VAT): Cr 33311 - VAT
```

### 8.3 Tables Used

| Table | Role | Key Fields |
|-------|------|------------|
| transactions | Lưu phiếu thu | id, date, description, reference, status, voucher_type, created_by |
| ledger_entries | Lưu bút toán | transaction_id, account_code, is_debit, amount |
| voucher_sequences | Sinh số CT | prefix, year, last_number |
| fc_transactions | Chi tiết ngoại tệ | transaction_id, account_code, currency_code, fc_amount, exchange_rate |
| audit_log | Audit trail | action, resource, resource_id, old_value, new_value, actor |
| action_journal | Request journal | method, uri, status, request_body, response_body, ms, user_id |

---

## 9. Workflow

### 9.1 Trạng thái phiếu thu (State Machine)

```
        ┌──────────┐
        │  DRAFT   │
        └────┬─────┘
             │ Post
             ↓
       ┌──────────┐
       │  POSTED  │
       └────┬─────┘
            │ Cancel (bút toán đảo)
            ↓
       ┌──────────┐
       │CANCELLED │
       └──────────┘
```

### 9.2 Workflow Steps (Chi tiết)

**Step 1 — Lập phiếu (Draft)**
- KTV vào màn hình Phiếu thu
- Hệ thống sinh số phiếu mới
- KTV nhập: ngày, người nộp, TK Có, số tiền, VAT, diễn giải
- Hệ thống validate cơ bản (số tiền > 0, TK tồn tại)
- Lưu tạm (DRAFT) hoặc Ghi sổ (POSTED)

**Step 2 — Kiểm soát (Kế toán trưởng)**
- Nếu số tiền > ngưỡng duyệt: yêu cầu KTT duyệt trước khi post
- Dưới ngưỡng: tự động post

**Step 3 — Nhập quỹ (Thủ quỹ)**
- Thủ quỹ nhận phiếu thu (liên 1)
- Đếm tiền, kiểm tra chứng từ gốc
- Ký xác nhận đã nhận đủ tiền

**Step 4 — Ghi sổ (Kế toán)**
- KTV nhận lại phiếu thu đã ký
- Kiểm tra Dr = Cr
- Ghi sổ kế toán (nếu chưa post tự động)

**Step 5 — Lưu trữ**
- In phiếu thu (3 liên)
- Liên 1: Lưu tại quỹ (Thủ quỹ)
- Liên 2: Giao người nộp
- Liên 3: Lưu kế toán (kèm chứng từ gốc)

---

## 10. User Journey

### 10.1 Kế toán viên (Người lập phiếu)

```
Bước 1: Đăng nhập → Dashboard
Bước 2: Chọn menu "Thu tiền" / "Phiếu thu" (thu/quy-tien-mat)
Bước 3: Xem danh sách phiếu thu hôm nay
Bước 4: Nhấn "Thêm phiếu thu" → Modal form mở
Bước 5: Chọn loại thu (template dropdown):
        - "Thu bán hàng có VAT" → TK Có = 511
        - "Thu hồi tạm ứng" → TK Có = 141
        - "Thu hồi công nợ" → TK Có = 131
        - "Thu khác" → tự chọn TK Có
Bước 6: Nhập thông tin → Hệ thống tự tính Amount-in-words
Bước 7: Tìm người nộp → chọn từ autocomplete
Bước 8: Nhấn "Ghi sổ" hoặc "Lưu nháp"
Bước 9: Xem kết quả → In phiếu (nếu cần)
```

### 10.2 Kế toán trưởng (Người duyệt)

```
Bước 1: Nhận thông báo "Phiếu thu cần duyệt"
Bước 2: Xem chi tiết phiếu thu (đính kèm chứng từ gốc)
Bước 3: Kiểm tra: loại thu, TK hạch toán, số tiền, người nộp
Bước 4: Duyệt (Post) hoặc Từ chối (có lý do)
Bước 5: Nếu duyệt → phiếu thu posted, Thủ quỹ nhận thông báo
```

### 10.3 Thủ quỹ (Người nhận tiền)

```
Bước 1: Nhận phiếu thu đã duyệt (bản in hoặc màn hình)
Bước 2: Kiểm tra chứng từ gốc
Bước 3: Đếm tiền / kiểm tra ngoại tệ
Bước 4: Ký nhận "Đã nhận đủ số tiền"
Bước 5: Lưu liên 1 vào sổ quỹ
```

---

## 11. Current Implementation Audit vs TT99

### 11.1 Compliance Matrix

| # | TT99 Field | Current Status | Detail |
|---|------------|---------------|--------|
| 1 | Tên đơn vị, địa chỉ | ✅ Layout | Từ layout.php header |
| 2 | Mẫu số 01-TT | ❌ MISSING | Form không hiển thị mã mẫu |
| 3 | Quyển số | ✅ VoucherService | Số quyển theo năm |
| 4 | Số CT tự động tăng | ✅ VoucherService | PT2026-NNNNNN |
| 5 | Nợ (1111) | ✅ Harcoded | Dr mặc định 1111 |
| 6 | Có (TK đối ứng) | ✅ Account picker | Chọn từ COA |
| 7 | Ngày tháng | ✅ Date picker | transaction_date |
| 8 | Họ tên người nộp | ✅ Autocomplete | payer_name |
| 9 | Địa chỉ người nộp | ❌ MISSING | Không lưu địa chỉ |
| 10 | Lý do nộp | ✅ Description | Diễn giải |
| 11 | Số tiền (bằng số) | ✅ Amount input | |
| 12 | Số tiền (bằng chữ) | ✅ Auto-generated | amount_in_words |
| 13 | Kèm theo (SL chứng từ) | ❌ MISSING | Không có field |
| 14 | Chứng từ gốc | ❌ MISSING | Không có field |
| 15 | Giám đốc ký | ❌ NO SIGN | Chưa tích hợp chữ ký |
| 16 | Kế toán trưởng ký | ❌ NO SIGN | Chưa tích hợp chữ ký |
| 17 | Người nộp tiền ký | ❌ NO SIGN | Chưa tích hợp chữ ký |
| 18 | Người lập phiếu ký | ❌ NO SIGN | Chưa tích hợp chữ ký |
| 19 | Thủ quỹ ký | ❌ NO SIGN | Chưa tích hợp chữ ký |
| 20 | "Đã nhận đủ số tiền" | ❌ MISSING | Thiếu dòng xác nhận |
| 21 | Tỷ giá ngoại tệ | ⚠️ PARTIAL | Trong service nhưng UI thiếu |
| 22 | Số tiền quy đổi | ⚠️ PARTIAL | Trong service nhưng UI thiếu |
| 23 | Bảng kê ngoại tệ | ❌ MISSING | Không có field liên kết |
| 24 | Liên gửi ra ngoài đóng dấu | ❌ MISSING | In không có chỗ đóng dấu |

### 11.2 Gap Scoring

| Category | Score | Max |
|----------|-------|-----|
| Core functionality (create/post/cancel) | 5 | 5 |
| Regulatory compliance (TT99 fields) | 8 | 14 |
| Audit (audit trail, action journal) | 5 | 5 |
| Security (RBAC, CSRF, period lock) | 5 | 5 |
| UX (autocomplete, templates, VAT calc) | 8 | 8 |
| **Total** | **31/37** | |

**Maturity score: 31/37 = 8.4/10**

---

## 12. Gap Analysis

### 12.1 Gaps

| ID | Gap | Severity | Impact |
|----|-----|----------|--------|
| G01 | Thiếu địa chỉ người nộp | P1 | Không đúng Mẫu 01-TT |
| G02 | Thiếu số chứng từ gốc kèm theo | P2 | Khó kiểm soát chứng từ |
| G03 | Thiếu "Đã nhận đủ số tiền" trong in | P1 | Phiếu in không hợp lệ |
| G04 | Thiếu mã "Mẫu số 01-TT" trên form | P2 | Không nhận diện được mẫu |
| G05 | Chữ ký số (5 chữ ký) | P2 | Chưa có e-signature |
| G06 | UI thu ngoại tệ trên form | P1 | FC chỉ trong API, không có UI |
| G07 | Thiếu Bảng kê ngoại tệ kèm theo | P2 | TT99 yêu cầu cho thu FC |
| G08 | Thiếu dấu "Liên gửi ra ngoài" trên in | P3 | Chỉ ảnh hưởng in ấn |

### 12.2 Quick Wins (P1 — 1-2h mỗi cái)

| Gap | Fix | File |
|-----|-----|------|
| G01 | Thêm trường `payer_address` vào modal + DB | `cash_receipts.php`, CashController |
| G03 | Thêm dòng "Đã nhận đủ số tiền" vào print template | `cash_receipts.php` |
| G04 | Thêm tiêu đề "Mẫu số 01-TT" vào form/print | `cash_receipts.php` |
| G02 | Thêm trường `document_count` (int, nullable) | DB migration + form |

### 12.3 Medium (P2 — 4-8h mỗi cái)

| Gap | Fix |
|-----|-----|
| G05 | Tích hợp DigitalSignatureService vào CashController |
| G06 | Thêm UI ngoại tệ vào modal (currency, rate, FC amount) |
| G07 | Tạo Bảng kê ngoại tệ form (Mẫu 07-TT) |

---

## 13. Implementation Roadmap

### Phase 1 — Quick Wins (P1, ~4h)

```
[1h] G04: Thêm "Mẫu số 01-TT" vào form + print
[1h] G01: Thêm payer_address field (DB + UI + Controller)
[1h] G03: Thêm "Đã nhận đủ số tiền" vào print
[1h] G02: Thêm document_count (DB + UI)
```

### Phase 2 — FC UI (P1, ~8h)

```
[4h] G06: Thêm UI ngoại tệ vào modal form
[4h] G07: Tạo Bảng kê ngoại tệ (Mẫu 07-TT)
```

### Phase 3 — Signature (P2, ~16h)

```
[8h] G05: Tích hợp chữ ký số (DigitalSignatureService)
[8h] G08: Cải thiện in ấn (dấu, liên)
```

---

> **Version:** 1.0
> **Next Review:** Sau Phase 1 implementation
