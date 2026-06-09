# BA + Chief Accountant Analysis: Mẫu 02-TT (Phiếu chi) theo TT 99/2025/TT-BTC

> **Phiên bản:** 1.0  
> **Tác giả:** BA Lead (20k h) + Chief Accountant (20k h)  
> **Tham chiếu:** TT 99/2025/TT-BTC Phụ lục I, NĐ 123/2020/NĐ-CP, ketoanthienung.net, webketoan.com  
> **Phạm vi:** Đánh giá cash_payments.php hiện tại vs Mẫu 02-TT chuẩn → khuyến nghị PROD

---

## 1. EXECUTIVE SUMMARY

### 1.1 Verdict: PROD-ready? **PARTIAL YES — cần sửa 6 gaps trước go-live**

| Khía cạnh | Đánh giá | Chi tiết |
|---|---|---|
| **Backend nghiệp vụ** | ✅ OK | CashService::recordPayment — đúng Dr/Cr, VAT split, period lock, control account check |
| **Giấy tờ (journal entry)** | ✅ OK | Sinh bút toán chuẩn Nợ TK đối ứng / Có 1111 |
| **Form nhập liệu (modal)** | ⚠️ Cần nâng cấp | Thiếu: Quyển số, địa chỉ người nhận, số chứng từ gốc kèm theo |
| **In ấn (printed form)** | ❌ KHÔNG ĐẠT | Chưa có mẫu in Mẫu 02-TT chuẩn — chỉ có generic printTransaction |
| **Chữ ký số (signatures)** | ❌ KHÔNG ĐẠT | Thiếu 5 ô chữ ký: GĐ, KTT, Thủ quỹ, Lập phiếu, Người nhận |
| **Ngoại tệ (FX)** | ❌ KHÔNG ĐẠT | Thiếu tỷ giá, số ngoại tệ, quy đổi |
| **VAT splitting** | ✅ OK | Đã có VAT cho các loại chi mua hàng |

### 1.2 Risk Assessment

| ID | Risk | Severity | Likelihood | Mitigation |
|---|---|---|---|---|
| PC01 | In phiếu chi không có chữ ký → audit fail | Critical | High | Thêm 5 signature blocks trước PROD |
| PC02 | Mất số quyển + số CT liên tục | High | Medium | Thêm Quyển số field, auto-validate |
| PC03 | Thiếu địa chỉ người nhận → xuất toán | Medium | Medium | Thêm address field bắt buộc |
| PC04 | Ngoại tệ không ghi tỷ giá | High | Low | Thêm FX fields cho payment FC |

---

## 2. USE CASES

### UC-01: Lập phiếu chi tiền mặt (happy path)

**Actor:** Kế toán viên  
**Precondition:** Quỹ tiền mặt > 0, period đang mở, người dùng có permission `cash.create`

**Flow:**
1. KTV mở modal Phiếu chi
2. Chọn loại chi → tự động điền TK Nợ
3. Nhập ngày chứng từ (default = hôm nay)
4. Tìm/chọn người nhận từ payer search
5. Nhập số tiền → tự động hiện bằng chữ
6. (Nếu có VAT) nhập thêm VAT rate → tự tính tiền chưa thuế
7. Nhập diễn giải (reason)
8. Submit → POST /api/cash/payments → 201 Created
9. Đóng modal, refresh list

**Postcondition:** 
- Bút toán: Nợ TK xxx / Có 1111
- Status = posted (post ngay)
- Số CT = PC + năm + số tự động
- Audit log created

### UC-02: In phiếu chi Mẫu 02-TT

**Actor:** Kế toán viên  
**Precondition:** Phiếu chi đã posted

**Flow:**
1. Click icon in trên dòng phiếu chi
2. Hệ thống hiển thị Mẫu 02-TT đầy đủ:
   - Header: Tên đơn vị, Địa chỉ
   - Mẫu số 02-TT kèm theo TT 99/2025/TT-BTC
   - Quyển số + Số CT
   - Nợ/Có account codes
   - Họ tên + địa chỉ người nhận
   - Lý do chi
   - Số tiền bằng số + bằng chữ
   - Kèm theo ... chứng từ gốc
   - 5 ô chữ ký
   - Tỷ giá + quy đổi (nếu ngoại tệ)
3. Print preview → A4 portrait

**Postcondition:** Phiếu in đúng Mẫu 02-TT, có thể lưu PDF

### UC-03: Chi tiền mặt có VAT đầu vào

**Actor:** Kế toán viên  
**Precondition:** Loại chi = mua hàng (hàng hóa/dịch vụ có VAT)

**Alternative flow (thay cho UC-01 bước 6):**
1. KTV chọn loại chi có hasVAT=true
2. Form hiện thêm VAT rate + VAT amount + Tiền chưa thuế
3. KTV nhập tổng tiền (đã bao gồm VAT)
4. VAT rate mặc định 10% (config theo vat_groups)
5. Hệ thống tự tính: VAT = total × rate/(100+rate); Net = total − VAT
6. Submit → bút toán: Nợ 156 (net) + Nợ 1331 (VAT) / Có 1111 (total)

### UC-04: Chi ngoại tệ

**Actor:** Kế toán viên  
**Trigger:** Loại chi = ngoại tệ

**Alternative flow:**
1. KTV chọn loại chi = chi ngoại tệ
2. Form hiện thêm: Loại ngoại tệ (USD/EUR/JPY...), Tỷ giá, Số ngoại tệ, Quy đổi VND
3. Tỷ giá mặc định từ exchange_rates (có thể override)
4. Số tiền VND = Số ngoại tệ × Tỷ giá
5. Submit → bút toán: Nợ TK xxx (VND) / Có 1113 (VND)
6. Ghi nhận chênh lệch tỷ giá nếu có

**Postcondition:** 
- Giao dịch ngoại tệ ghi nhận đúng tỷ giá
- Audit trail: ghi tỷ giá tại thời điểm chi

### UC-05: Kiểm soát — từ chối chi tiêu vượt quỹ

**Actor:** Hệ thống  
**Trigger:** amount > số dư TK 111 hiện tại

**Alternative flow:**
1. KTV nhập số tiền > balance
2. Hệ thống: tính balance = tổng Nợ 111 - tổng Có 111 (đã posted)
3. Nếu amount > balance → throw InvalidArgumentException
4. Hiển thị error: "Số dư quỹ tiền mặt không đủ. Số dư hiện tại: xxx VND"

### UC-06: Tra soát phiếu chi đã posted

**Actor:** Kế toán trưởng, Kiểm toán  
**Precondition:** Phiếu chi tồn tại, có permission

**Flow:**
1. Click vào phiếu chi trong list
2. Xem chi tiết: Toàn bộ fields + bút toán + audit trail
3. Nếu sai → không edit (posted = immutable)
4. Phải lập phiếu điều chỉnh (correction) để sửa

---

## 3. BUSINESS RULES

### 3.1 Validation Rules (13 rules)

| # | Rule | Error message | Severity |
|---|---|---|---|
| V01 | amount > 0 | "Số tiền phải lớn hơn 0" | block |
| V02 | debit_account_code tồn tại | "Không tìm thấy tài khoản: xxx" | block |
| V03 | debit_account không phải control account | "Tài khoản xxx là TK tổng hợp. Vui lòng hạch toán vào TK chi tiết" | block |
| V04 | Period đang mở (transaction_date) | "Kỳ kế toán đã đóng" | block |
| V05 | Nếu hasVAT → rate hợp lệ (0, 5, 8, 10) | "Thuế suất không hợp lệ" | block |
| V06 | Payer name không được trống | "Vui lòng nhập người nhận tiền" | block |
| V07 | Description không được trống | "Vui lòng nhập lý do chi" | block |
| V08 | Tổng Dr = Tổng Cr | "Số phát sinh Nợ ≠ Có" (JournalService) | block |
| V09 | Ngày chứng từ không được trong tương lai | "Ngày chứng từ không được lớn hơn ngày hiện tại" | warn |
| V10 | (Quyển số) nếu có → phải đúng format | "Số quyển không hợp lệ" | block |
| V11 | (Ngoại tệ) tỷ giá > 0 | "Tỷ giá phải lớn hơn 0" | block |
| V12 | (Chứng từ gốc kèm theo) nếu ghi số → phải ghi loại | "Vui lòng ghi rõ loại chứng từ gốc" | warn |
| V13 | Số tiền bằng chữ phải khớp số tiền | "Số tiền bằng chữ không khớp" | warn |

### 3.2 Business Rules (10 rules)

| # | Rule | Logic | Source |
|---|---|---|---|
| B01 | Số CT tự động tăng theo năm | prefix PC + năm + FOR UPDATE sequence | VoucherService |
| B02 | Phiếu chi = posted ngay (no draft) | Status = posted sau khi tạo | CashService |
| B03 | Chỉ post vào TK con 1111/1112/1113 | Không post vào 111 (control) | AccountRepository check |
| B04 | VAT split: Nợ 1331 (đầu vào) | Chỉ khi hasVAT = true | CashService |
| B05 | Chi trả NCC → tự động ghi giảm công nợ 331 | Dùng ApService nếu payer_type = supplier | Tích hợp sau |
| B06 | Chi tạm ứng → tự động ghi giảm tạm ứng | Dùng AdvancePaymentService | Tích hợp sau |
| B07 | Chi nhân viên → tự động ghi lương | Nếu employee type → dùng PayrollService | Tích hợp sau |
| B08 | Quỹ ngoại tệ phải ghi song song VND + FC | 1113 (FC) + tỷ giá + quy đổi | FX module |
| B09 | In ấn phải có đủ 5 chữ ký | GĐ, KTT, Thủ quỹ, Lập phiếu, Người nhận | TT99 Phụ lục I |
| B10 | Số quyển + số CT duy nhất trong 1 kỳ | Composite unique (quyen_so, so_ct, ky) | Migration needed |

---

## 4. DATA FLOW

```
Browser (modal input)
  → POST /api/cash/payments
    → CashController::createPayment()
      → Auth::checkCsrf()
      → Validate input (amount, debit_account_code, payer, ...)
      → Helpers::nextVoucherNo('PC') [SELECT FOR UPDATE]
      → CashService::recordPayment()
        → Validate: amount>0, account exists, not control account
        → Period lock check (via transaction_date)
        → JournalService::postEntry()
          → PostingRuleService::validate() [block invalid Dr/Cr pairs]
          → Create Transaction (status=posted)
          → Create LedgerEntries (Dr debit_account / Cr 1111)
          → VoucherService::nextNumber() [SELECT FOR UPDATE]
          → AuditLogger::log()
        → Return { transaction_id, reference, status }
      → Save payer info to transactions table
      → JsonResponse::ok(201)
    → Update UI list
```

---

## 5. WORKFLOW

```
┌──────────┐   ┌──────────┐   ┌──────────┐   ┌──────────┐   ┌──────────┐
│ KTV nhập │ → │ Validate │ → │ Post bút │ → │ In phiếu │ → │ Lưu hồ   │
│ liệu     │   │ input    │   │ toán     │   │ chi 02-TT│   │ sơ       │
└──────────┘   └──────────┘   └──────────┘   └──────────┘   └──────────┘
     ↓              ↓              ↓              ↓              ↓
  Modal form    Backend rules   JournalService   Print view    Archive
  + payer       V01-V13        Dr/Cr check     5 signatures   Audit trail
  + amount      + period lock  + posting rule   + A4 format    + physical
  + VAT split   + control acct                  + BOM BCT     file
```

---

## 6. USER JOURNEY: Lập phiếu chi

```
1. [Dashboard] → KTV click "Tiền mặt & NH" → "Phiếu chi"
2. [List view] Thấy danh sách phiếu chi (last 200)
   - Có thể filter, search, export CSV
3. [Action] Click "Phiếu chi" → modal #paymentModal
4. [Modal: Ngày] Default = today, có thể sửa
5. [Modal: Loại chi] Dropdown từ template config:
   - Mua hàng (có VAT) → TK 156
   - Chi phí QLDN → TK 642
   - Chi phí bán hàng → TK 641
   - Trả NCC → TK 331
   - Tạm ứng → TK 141
   - Chi phí khác → TK 811
6. [Modal: Người nhận] Payer search → 3 tabs: KH/NCC/NV
7. [Modal: Số tiền] Input number → auto amount in words
8. [Modal: VAT] Nếu có VAT → hiện rate + tính tự động
9. [Modal: Diễn giải] Text input (reason)
10. [Submit] 201 → success toast → modal close
11. [View] List refresh → dòng mới xuất hiện với status posted
12. [Print] Click icon in → Mẫu 02-TT full page → Ctrl+P
13. [File] Lưu PDF vào hồ sơ kế toán (physical/electronic)
```

---

## 7. GAPS ANALYSIS — existing cash_payments.php vs Mẫu 02-TT

| # | Yêu cầu TT99 | Hiện tại | Gap | Priority |
|---|---|---|---|---|
| G01 | Tên đơn vị + địa chỉ trên form | ❌ Không có | Cần thêm in header khi in phiếu | P0 |
| G02 | Mẫu số 02-TT kèm TT99 | ❌ Không có | Cần thêm in reference | P0 |
| G03 | Quyển số + Số CT | ⚠️ Số CT có (PC prefix), quyển số không | Thêm quyển số field | P1 |
| G04 | Nợ/Có account code trên form | ⚠️ Có TK Nợ, không có Có | Thêm dòng "Có: 1111" | P1 |
| G05 | Địa chỉ người nhận | ❌ Không có | Thêm trường địa chỉ | P1 |
| G06 | Số tiền bằng chữ trên form | ⚠️ Có dưới form (amountWords) | Cần trên form chính | P2 |
| G07 | Kèm theo ... chứng từ gốc | ❌ Không có | Thêm field đính kèm | P2 |
| G08 | 5 ô chữ ký (GĐ, KTT, TQ, LP, NN) | ❌ Không có | Print template Mẫu 02-TT | P0 |
| G09 | Tỷ giá + quy đổi ngoại tệ | ❌ Không có | FX fields cho FC payment | P1 |
| G10 | In A4 portrait đúng mẫu | ⚠️ printTransaction generic | Cần print template riêng | P0 |
| G11 | Kiểm tra số dư quỹ trước chi | ❌ Không kiểm tra | Thêm balance check trong recordPayment | P1 |
| G12 | Soft copy lưu audit trail | ✅ OK | JournalService + AuditLogger | – |

**Tổng: 11 gaps (P0: 4, P1: 5, P2: 2)**

---

## 8. PRINT TEMPLATE: Mẫu 02-TT đề xuất

```
ĐƠN VỊ: ... CÔNG TY ABC ...
ĐỊA CHỈ: ... Số 1, Đường XYZ ...

          Mẫu số: 02 - TT
   (Kèm theo TT 99/2025/TT-BTC)

        PHIẾU CHI
Ngày ... tháng ... năm ...
─────────────────────────────────────
Quyển số: 01         Số: PC2026-000042
Nợ: 642              Có: 1111

Họ và tên người nhận: Nguyễn Văn A
Địa chỉ: 123 Phố ABC, Hà Nội
Lý do chi: Chi phí văn phòng tháng 6
Số tiền: 5.000.000
(Bằng chữ): Năm triệu đồng chẵn

Kèm theo: 02 chứng từ gốc
─────────────────────────────────────
  Giám đốc   Kế toán trưởng  Thủ quỹ
  (Ký, họ tên) (Ký, họ tên) (Ký, họ tên)

Người lập phiếu   Người nhận tiền
  (Ký, họ tên)    (Ký, họ tên)

Đã nhận đủ số tiền: Năm triệu đồng chẵn
─────────────────────────────────────
+ Tỷ giá ngoại tệ: 25.480 VND/USD
+ Số tiền quy đổi: 196.28 USD
(Liên gửi ra ngoài phải đóng dấu)
```

---

## 9. IMPLEMENTATION PLAN (P0-P2)

### Phase P0 — Bắt buộc trước PROD (4 items)

| Task | File | Effort | Test |
|---|---|---|---|
| T01: Print template Mẫu 02-TT với 5 signatures + company header | PrintTemplateService + view | 4h | 5 tests |
| T02: Thêm in ấn phiếu chi riêng (không dùng printTransaction generic) | cash_payments.php | 2h | 1 test |
| T03: Balance check trước recordPayment (số dư 111) | CashService::recordPayment | 2h | 3 tests |
| T04: Kiểm tra đóng góp cho BC01/BC02 (phiếu chi ảnh hưởng BC) | FsService | 1h | 2 tests |

### Phase P1 — Nâng cao chất lượng (5 items)

| Task | File | Effort | Test |
|---|---|---|---|
| T05: Thêm quyển số field (booklet number) | Migration + CashController + view | 3h | 3 tests |
| T06: Địa chỉ người nhận trên form | view + CashController | 1h | 1 test |
| T07: FX fields (currency, rate, FC amount) | CashController + view | 4h | 5 tests |
| T08: In "Có: 1111" trên màn hình | view | 0.5h | 0 test |
| T09: Composite unique (quyển, số, kỳ) | Migration | 2h | 2 tests |

### Phase P2 — Nice to have (2 items)

| Task | File | Effort | Test |
|---|---|---|---|
| T10: "Kèm theo ... chứng từ gốc" field | view + controller | 1h | 1 test |
| T11: Auto-link với ApService/AdvancePayment | CashService | 4h | 5 tests |

---

## 10. INTERNAL CONTROLS

| ID | Control | Type | Testing method |
|---|---|---|---|
| IC01 | Dr = Cr on every payment | Automated | JournalService check |
| IC02 | Period lock enforcement | Automated | PeriodService::isPeriodOpen |
| IC03 | Control account block (111 → must use 1111) | Automated | AccountRepository::isControl |
| IC04 | Posting rules validation | Automated | PostingRuleService |
| IC05 | Audit trail for every payment | Automated | AuditLogger::log |
| IC06 | Sequential voucher number | Automated | VoucherService (FOR UPDATE) |
| IC07 | Printed form = 5 signatures | Manual | In phiếu → ký tay |
| IC08 | Cash balance sufficiency | Automated | T01 (balance check) |
| IC09 | Segregation of duties: lập phiếu ≠ ký duyệt | Manual | Permission: create ≠ approve |
| IC10 | Ngoại tệ: ghi song song VND + FC | Automated | CashService::recordPaymentFC |

---

## 11. CURRENT STATUS vs MISA/FAST/BRAVO reference

| Tính năng | Hệ thống | MISA | FAST | BRAVO | Ghi chú |
|---|---|---|---|---|---|
| Modal nhập nhanh | ✅ | ✅ | ✅ | ✅ | Tương đương |
| Mẫu 02-TT in ấn | ❌ | ✅ | ✅ | ✅ | P0 gap |
| 5 chữ ký | ❌ | ✅ | ✅ | ✅ | P0 gap |
| Balance check | ❌ | ✅ | ✅ | ✅ | P1 gap |
| Quyển số | ❌ | ✅ | ✅ | ✅ | P1 gap |
| Ngoại tệ | ⚠️ (có recordPaymentFC) | ✅ | ✅ | ✅ | P1 gap |
| VAT split | ✅ | ✅ | ✅ | ✅ | OK |
| Audit trail | ✅ | ✅ | ✅ | ✅ | OK |
| Sequential CT | ✅ | ✅ | ✅ | ✅ | OK |
| Print batch | ❌ | ✅ | ❌ | ✅ | P2 |

---

## 12. KẾT LUẬN

**PROD verdict:** PHIẾU CHI hiện tại **có thể vận hành** nhưng **chưa đạt chuẩn Mẫu 02-TT** để in ấn và lưu hồ sơ giấy.

**Backend:** 95% OK — CashService::recordPayment tạo bút toán đúng, VAT split đúng, posting rules đúng, sequential CT đúng, audit trail đúng.

**Frontend (input):** 70% OK — modal functional, thiếu quyển số, địa chỉ, chứng từ gốc.

**Frontend (print):** 20% OK — cần xây dựng print template Mẫu 02-TT từ đầu.

**Khuyến nghị:** PROD-deploy với backend + modal input, nhưng phải có P0 tasks (T01-T04) trước khi coi là production-ready cho audit. Thời gian: ~2 ngày cho 1 developer.

---

## 13. TÀI LIỆU THAM KHẢO

- TT 99/2025/TT-BTC Phụ lục I — Mẫu 02-TT Phiếu chi
- NĐ 123/2020/NĐ-CP Điều 12, 20 — Hóa đơn chứng từ
- ketoanthienung.net/mau-phieu-chi-theo-thong-tu-99.htm — Mẫu Word/Excel + cách lập
- webketoan.com — Mẫu 02-TT chuẩn
- docs/decisions/adr-006.md — Vietnamese message audit
- docs/decisions/adr-011.md — ConfigService business rules
