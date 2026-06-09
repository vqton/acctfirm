# BA + Chief Accountant Analysis: Mẫu 01-VT (Phiếu nhập kho) theo TT 99/2025/TT-BTC

> **Phiên bản:** 1.0  
> **Tác giả:** BA Lead (20k h) + Chief Accountant (20k h)  
> **Tham chiếu:** TT 99/2025/TT-BTC Phụ lục I, Thông tư 200/2014/TT-BTC, thuvienphapluat.vn, ketoanthienung.net, hoatieu.vn, webketoan.com  
> **Phạm vi:** Đánh giá goods_receipt.php + GoodsReceiptService hiện tại vs Mẫu 01-VT chuẩn → khuyến nghị PROD

---

## 1. EXECUTIVE SUMMARY

### 1.1 Verdict: PROD-ready? **PHẦN LỚN OK — cần sửa 7 gaps trước go-live**

| Khía cạnh | Đánh giá | Chi tiết |
|---|---|---|
| **Backend nghiệp vụ** | ✅ OK | GoodsReceiptService::postReceipt — đúng Dr/Cr, period lock, control account check, stock update |
| **Hạch toán kế toán** | ✅ OK | Nợ 15x / Có 331 (hoặc 1111) — đúng theo chế độ kế toán |
| **Form nhập liệu (full-page)** | ⚠️ Cần nâng cấp | Thiếu: Số hóa đơn/lệnh nhập, địa điểm kho, Nợ/Có codes, CT gốc kèm theo |
| **Cột 1 (SL theo CT) vs Cột 2 (SL thực nhập)** | ❌ KHÔNG ĐẠT | TT99 yêu cầu 2 cột riêng, hiện tại chỉ có qty_received |
| **In ấn (printed form)** | ❌ KHÔNG ĐẠT | Chưa có mẫu in Mẫu 01-VT chuẩn — chỉ có in browser (printPNK = window.print) |
| **Chữ ký số (signatures)** | ⚠️ Có HTML nhưng chưa in được | 5 ô chữ ký trên giao diện nhưng print template chưa render |
| **Người giao hàng** | ❌ KHÔNG ĐẠT | Thiếu trường riêng cho "Họ tên người giao" — chỉ có supplier_name |
| **VAT / FX** | ✅ OK | GoodsReceiptService không xử lý VAT (đã có ở e-invoice import) |

### 1.2 Risk Assessment

| ID | Risk | Severity | Likelihood | Mitigation |
|---|---|---|---|---|
| GR01 | In PNK không có chữ ký → audit fail | Critical | High | Thêm 5 signature blocks print template trước PROD |
| GR02 | Thiếu cột Số lượng theo CT (cột 1) → không đối chiếu được với hóa đơn | High | Medium | Thêm cột 1 riêng biệt trước PROD |
| GR03 | Không có số hóa đơn/lệnh nhập → mất audit trail gốc | High | Medium | Thêm field invoice_ref bắt buộc |
| GR04 | Thiếu địa điểm kho → mất thông tin kiểm kê | Medium | Low | Thêm location text field |
| GR05 | In ấn không có số tiền bằng chữ trên bản in | Medium | Medium | Print template phải render amount_in_words |
| GR06 | Người giao hàng gộp với supplier → sai nghiệp vụ | Medium | Low | Tách biệt người giao (deliverer) và NCC (supplier) |

---

## 2. TT 99/2025/TT-BTC — MẪU 01-VT CHUẨN

### 2.1 Cấu trúc mẫu theo Phụ lục I

```
ĐƠN VỊ: ..........................................
BỘ PHẬN: ........................................

        Mẫu số: 01 - VT
  (Kèm theo TT 99/2025/TT-BTC)

           PHIẾU NHẬP KHO
Ngày ... tháng ... năm ...
Số: ..........

Nợ: ............   Có: ............

- Họ và tên người giao: ................................................................
- Theo ......................... số ........ ngày ...... tháng ...... năm ............
- Nhập tại kho: ................................... địa điểm .............................

+------+---------------------------------------+------+----+----------+----------+-----------+-----------+
|  A   |           B                           |  C   | D  |    1     |    2     |     3     |     4     |
+------+---------------------------------------+------+----+----------+----------+-----------+-----------+
|  STT | Tên, nhãn hiệu, quy cách, phẩm chất   | Mã   |ĐVT | Số lượng| Số lượng| Đơn giá  | Thành     |
|      | vật tư, dụng cụ, sản phẩm, hàng hóa  | số   |    | theo CT | thực nhập|          | tiền      |
+------+---------------------------------------+------+----+----------+----------+-----------+-----------+
|  1   | Hàng hóa A                            | HA01 | cái|   100    |   100    |  50,000  |5,000,000  |
|  2   | Nguyên liệu B                         | NL01 | kg |    50    |    48    |  20,000  |  960,000  |
+------+---------------------------------------+------+----+----------+----------+-----------+-----------+
|                                           Cộng xxxx                               |    5,960,000  |
+------+---------------------------------------+------+----+----------+----------+-----------+-----------+

Tổng số tiền (viết bằng chữ): Năm triệu chín trăm sáu mươi nghìn đồng chẵn

Số chứng từ gốc kèm theo: ........................................................

  Ngày ... tháng ... năm ...
  Người lập phiếu       Người giao hàng       Thủ kho         Kế toán trưởng
  (Ký, họ tên)          (Ký, họ tên)        (Ký, họ tên)    (Ký, họ tên)
                                                         (Hoặc BP có nhu cầu nhập)
```

### 2.2 Các trường hợp áp dụng

| Loại nhập | Mô tả | Nguồn gốc |
|---|---|---|
| Mua ngoài | Vật tư, hàng hóa mua từ NCC | Hóa đơn mua hàng |
| Tự sản xuất | Sản phẩm sản xuất nội bộ nhập kho | Lệnh sản xuất |
| Thuê ngoài GC | Gia công chế biến thuê ngoài | Hợp đồng GC |
| Góp vốn | Nhận góp vốn bằng hàng hóa | Biên bản góp vốn |
| Kiểm kê thừa | Phát hiện thừa trong kiểm kê | Biên bản kiểm kê |
| Trả lại | Khách trả lại hàng (nhập kho) | Biên bản trả hàng |

### 2.3 Số liên

| Loại | Số liên | Phân phối |
|---|---|---|
| Mua ngoài | 2 liên | Liên 1: Lưu nơi lập; Liên 2: Thủ kho giữ → ghi Thẻ kho → chuyển Kế toán |
| Tự sản xuất | 3 liên | Liên 1: Lưu nơi lập; Liên 2: Thủ kho; Liên 3: Người giao hàng giữ |

### 2.4 Hướng dẫn ghi chi tiết từng cột

| Cột | Nội dung | Ai ghi | Khi nào |
|---|---|---|---|
| A | Số thứ tự dòng | Người lập phiếu | Khi lập |
| B | Tên, nhãn hiệu, quy cách, phẩm chất | Người lập phiếu | Khi lập |
| C | Mã số vật tư/hàng hóa | Người lập phiếu | Khi lập |
| D | Đơn vị tính | Người lập phiếu | Khi lập |
| 1 | Số lượng theo chứng từ (hóa đơn/lệnh nhập) | Người lập phiếu | Khi lập |
| 2 | Số lượng thực nhập vào kho | Thủ kho | Khi nhập xong |
| 3 | Đơn giá | Kế toán | Sau khi có giá |
| 4 | Thành tiền = Cột 2 × Cột 3 | Kế toán | Sau khi có giá |

### 2.5 Quy trình luân chuyển chứng từ

```
Bước 1: Bộ phận mua hàng/SX lập Phiếu nhập kho (2-3 liên)
  → Người lập phiếu ký
  → Chuyển cho người giao hàng mang đến kho

Bước 2: Tại kho
  → Thủ kho kiểm tra: tên hàng, quy cách, số lượng (cột 2)
  → Nếu có sai lệch → lập Biên bản kiểm nghiệm (03-VT)
  → Thủ kho + người giao hàng ký vào phiếu

Bước 3: Sau nhập kho
  → Liên 2: Thủ kho giữ → ghi Thẻ kho
  → Sau đó chuyển cho Phòng Kế toán

Bước 4: Phòng Kế toán
  → Kế toán ghi cột 3 (đơn giá), cột 4 (thành tiền)
  → Kế toán trưởng ký
  → Ghi sổ kế toán (Nợ 15x / Có 331)
```

---

## 3. USE CASES

### UC-01: Lập phiếu nhập kho mua hàng (happy path)

**Actor:** Kế toán viên (KTV)  
**Precondition:** Hàng hóa đã được giao đến kho, có hóa đơn mua hàng

**Flow:**
1. KTV mở giao diện Phiếu nhập kho (Mẫu 01-VT)
2. Hệ thống sinh số PNK tự động (PNK2026-000001)
3. Nhập ngày nhập kho (default = today)
4. Chọn loại nhập = "Mua hàng"
5. Nhập thông tin:
   - Họ tên người giao
   - Theo hóa đơn số ... ngày ...
   - Nhà cung cấp
   - Kho nhập + địa điểm
   - Bộ phận
6. Nhập từng dòng hàng:
   - Mã hàng / Tên hàng / ĐVT (chọn từ danh mục)
   - Cột 1: Số lượng theo hóa đơn
   - Cột 2: Số lượng thực nhập
   - Đơn giá (từ hóa đơn)
7. Hệ thống tự tính: Thành tiền = Cột 2 × Đơn giá
8. Xem tổng tiền + số tiền bằng chữ
9. Nhập số chứng từ gốc kèm theo
10. Submit → POST /api/goods-receipt/draft → 201 Created
11. KTV kiểm tra lại, nhấn "Ghi sổ"
12. Hệ thống: tạo bút toán Nợ 15x / Có 331 + cập nhật tồn kho

**Postcondition:**
- Status = posted
- Tồn kho tăng (SL thực nhập)
- Cost layer được tạo (đơn giá)
- Bút toán: Nợ 152/156/... / Có 331
- Số PNK = PNK + năm + số tự động
- Audit log created

### UC-02: In phiếu nhập kho Mẫu 01-VT

**Actor:** Kế toán viên  
**Precondition:** PNK đã posted

**Flow:**
1. Click icon in trên dòng PNK
2. Hệ thống hiển thị Mẫu 01-VT đầy đủ:
   - Header: Tên đơn vị, Bộ phận
   - Mẫu số 01-VT kèm theo TT 99/2025/TT-BTC
   - Số PNK + Ngày tháng năm
   - Nợ/Có account codes
   - Họ tên người giao hàng
   - Theo HĐ số ... ngày ...
   - Nhập tại kho: ... Địa điểm: ...
   - Bảng chi tiết: Cột A-D + Cột 1-2-3-4
   - Cộng: Tổng số tiền
   - Số tiền bằng chữ
   - Số chứng từ gốc kèm theo
   - 4-5 ô chữ ký (Người lập, Người giao, Thủ kho, Kế toán trưởng, Giám đốc)
3. Print preview → A4 portrait
4. Xuất PDF lưu hồ sơ

**Postcondition:** Phiếu in đúng Mẫu 01-VT, có thể ký tay

### UC-03: Nhập kho có sai lệch số lượng (Cột 1 ≠ Cột 2)

**Actor:** Kế toán viên, Thủ kho  
**Trigger:** Số lượng thực nhập ≠ số lượng trên hóa đơn

**Alternative flow:**
1. KTV nhập Cột 1 (theo HĐ) = 100
2. KTV nhập Cột 2 (thực nhập) = 95 (thiếu 5 do hỏng vỡ)
3. Hệ thống cảnh báo: "Số lượng thực nhập khác số lượng chứng từ"
4. KTV xác nhận lý do chênh lệch
5. Ghi sổ: Chỉ ghi nhận tồn kho cho số lượng thực nhập (95)
6. Biên bản kiểm nghiệm (03-VT) được tạo nếu cần

**Postcondition:**
- Tồn kho tăng 95 (không phải 100)
- Công nợ NCC ghi nhận theo giá trị thực nhập
- Audit trail ghi rõ chênh lệch

### UC-04: Nhập kho không theo đơn đặt hàng

**Actor:** Kế toán viên  
**Trigger:** Hàng mua không có PO (mua đột xuất, trả lại, kiểm kê thừa)

**Flow:**
1. KTV chọn loại nhập (mua không PO, trả lại, kiểm kê thừa)
2. Nhập thông tin tiêu chuẩn (người giao, NCC, kho)
3. Nhập hàng vào grid
4. Submit → Draft
5. Ghi sổ → Bút toán: Nợ 15x / Có 1111 (không có 331 vì không có NCC)

**Postcondition:**
- Tồn kho tăng
- Bút toán ghi nhận đúng nguồn gốc (không ghi 331 nếu không có NCC)

### UC-05: Từ chối nhập kho trong kỳ đã đóng

**Actor:** Hệ thống  
**Trigger:** received_date thuộc kỳ đã đóng

**Alternative flow:**
1. KTV nhập ngày = 2025-03-15
2. PeriodService::isPeriodOpen trả về false
3. Hệ thống throw: "Không thể nhập kho trong kỳ đã khóa. Ngày: 2025-03-15"
4. KTV phải chọn kỳ đang mở

### UC-06: Hủy phiếu nhập kho (draft → cancelled)

**Actor:** Kế toán viên  
**Precondition:** PNK ở trạng thái draft

**Flow:**
1. Mở PNK draft
2. Nhấn "Hủy"
3. Confirm dialog
4. POST /api/goods-receipt/{id}/cancel
5. Status → cancelled
6. Audit log: goods_receipt.cancel

**Postcondition:**
- PNK không thể ghi sổ
- Không ảnh hưởng tồn kho (vì chưa posted)

### UC-07: Tra soát PNK đã posted

**Actor:** Kế toán trưởng, Kiểm toán  
**Precondition:** PNK đã posted

**Flow:**
1. Mở PNK detail
2. Xem toàn bộ fields + bút toán đã tạo
3. Nếu sai → phải tạo PNK điều chỉnh (correction) hoặc PNK âm
4. Không được edit PNK đã posted

---

## 4. BUSINESS RULES

### 4.1 Validation Rules (16 rules)

| # | Rule | Error message | Severity |
|---|---|---|---|
| V01 | qty_received > 0 cho mỗi line | "Số lượng nhập phải lớn hơn 0" | block |
| V02 | unit_price ≥ 0 | "Đơn giá không được âm" | block |
| V03 | Phải có ít nhất 1 dòng hàng | "Phiếu nhập kho phải có ít nhất một dòng hàng" | block |
| V04 | Period đang mở (received_date) | "Không thể nhập kho trong kỳ đã khóa" | block |
| V05 | Tài khoản tồn kho hợp lệ (15x) | "Không tìm thấy tài khoản tồn kho cho loại hàng này" | block |
| V06 | Tổng Dr = Tổng Cr (tại JournalService) | "Số phát sinh Nợ ≠ Có" | block |
| V07 | Control account check (15x → chi tiết) | "Tài khoản xxx là TK tổng hợp" | block |
| V08 | Ngày nhập không trong tương lai | "Ngày nhập kho không được lớn hơn ngày hiện tại" | warn |
| V09 | Người giao hàng không được trống | "Vui lòng nhập họ tên người giao hàng" | block |
| V10 | item_id phải tồn tại | "Không tìm thấy mặt hàng: xxx" | block |
| V11 | Kho nhập phải tồn tại | "Không tìm thấy kho: xxx" | block |
| V12 | Nếu receipt_type = purchase → supplier_name bắt buộc | "Vui lòng chọn nhà cung cấp cho phiếu nhập mua hàng" | block |
| V13 | Số lượng thực nhập (cột 2) ≥ 0 và ≤ số lượng CT (cột 1) | "Số lượng thực nhập không được lớn hơn số lượng theo chứng từ" | warn |
| V14 | Số chứng từ gốc kèm theo (nếu có) → phải ghi rõ loại | "Vui lòng ghi rõ loại chứng từ gốc kèm theo" | warn |
| V15 | Đơn giá = 0 → cảnh báo (nhập kho 0 đồng) | "Đơn giá bằng 0. Xác nhận hàng nhập kho miễn phí?" | warn |
| V16 | Cột 1 ≥ Cột 2 (SL theo CT ≥ SL thực nhập) | "Số lượng thực nhập không được vượt quá số lượng chứng từ" | warn |

### 4.2 Business Rules (12 rules)

| # | Rule | Logic | Source |
|---|---|---|---|
| B01 | Số PNK tự động tăng theo năm | prefix PNK + năm + FOR UPDATE sequence | VoucherService |
| B02 | PNK lifecycle: draft → posted → cancelled | GoodsReceiptService | |
| B03 | Chỉ post PNK ở trạng thái draft | Kiểm tra status trước post | GoodsReceiptService |
| B04 | Post PNK = tạo bút toán: Nợ 15x / Có 331 (hoặc 111) | JournalService::postEntry | |
| B05 | Post PNK = cập nhật tồn kho + cost layer | InventoryService->updateStockAndCostLayer | |
| B06 | Account tồn kho phụ thuộc item_type: | inventoryAccountMap | |
| | merchandise → 156, raw_material → 152, product → 155, tool → 153, other → 152 | | |
| B07 | Nếu có supplier → credit 331; không supplier → credit 1111 | GoodsReceiptService::postReceipt | |
| B08 | Cột 4 (Thành tiền) = Cột 2 (SL thực nhập) × Cột 3 (Đơn giá) | Tự động tính | TT99 |
| B09 | Số tiền bằng chữ tự động sinh | VnWords::toWords | |
| B10 | In ấn phải có đủ 4-5 chữ ký | Người lập, Người giao, Thủ kho, KTT, (GĐ) | TT99 Phụ lục I |
| B11 | Audit log cho mọi thay đổi | AuditLogger::log | |
| B12 | Số lượng thực nhập (cột 2) là cơ sở ghi sổ kế toán | Cột 1 chỉ để đối chiếu | TT99 |

---

## 5. DATA FLOW

### 5.1 Tạo PNK nháp

```
Browser (form goods_receipt.php)
  → POST /api/goods-receipt/draft
    → GoodsReceiptController::createDraft()
      → Auth::checkCsrf()
      → Auth::requirePermission('inventory', 'create')
      → GoodsReceiptService::createDraft()
        → assertPeriodOpen(received_date)
        → Validate lines (V01, V02, V03)
        → VoucherService::nextNumber('PNK') [SELECT FOR UPDATE]
        → VnWords::toWords(totalAmount)
        → beginTransaction
        → Insert goods_receipts
        → Insert goods_receipt_lines (each line)
        → commit
        → AuditLogger::log('goods_receipt.create_draft')
        → Return JSON { id, gr_number, status='draft', ... }
      → JsonResponse::ok(201)
```

### 5.2 Ghi sổ PNK

```
Browser (click "Ghi sổ")
  → POST /api/goods-receipt/{id}/post
    → GoodsReceiptController::postReceipt()
      → Auth::checkCsrf()
      → Auth::requirePermission('inventory', 'update')
      → GoodsReceiptService::postReceipt(id, postedBy)
        → Find receipt (V09: not found → throw)
        → Check status = draft (V03: block if not draft)
        → assertPeriodOpen(received_date)
        → beginTransaction
        → For each line:
          → Find item (V10)
          → Determine inventory account by item_type (B06)
          → Accumulate Dr by account code
        → Build credit: 331 if supplier, else 1111 (B07)
        → JournalService::postEntry()
          → PostingRuleService::validate()
          → Dr = Cr check (V06)
          → Create Transaction + LedgerEntries
        → For each line:
          → InventoryService::updateStockAndCostLayer(B05)
        → Update goods_receipts status = 'posted'
        → commit
        → AuditLogger::log('goods_receipt.post')
        → Return JSON { ... status='posted', transaction_id }
      → JsonResponse::ok()
```

---

## 6. WORKFLOW

```
┌────────────┐   ┌────────────┐   ┌────────────┐   ┌────────────┐   ┌────────────┐
│ KTV nhập   │ → │ Validate   │ → │ Save draft  │ → │ KTV kiểm   │ → │ Ghi sổ     │
│ liệu PNK   │   │ input      │   │ PNK        │   │ tra lại     │   │ (post)     │
└────────────┘   └────────────┘   └────────────┘   └────────────┘   └────────────┘
     ↓                ↓                ↓                ↓                ↓
  Form 01-VT      Backend rules    INSERT INTO      Detail view     beginTransaction
  + người giao    V01-V16         goods_receipts                     JournalService
  + hóa đơn số    + period lock   goods_receipt_lines               InventoryService
  + kho/địa điểm   + item check    AuditLogger                       commit
  + cột 1, 2, 3, 4                                                          ↓
  + số tiền bằng chữ                                                    In PNK 01-VT
  + CT gốc kèm theo                                                     (print view)
```

---

## 7. USER JOURNEY: Lập phiếu nhập kho

```
1. [Sidebar] → KTV click "Kho" → "Phiếu nhập kho"
2. [List view] Thấy danh sách PNK (last 200)
   - Filter: tất cả / nháp / đã ghi sổ / đã hủy
   - Search: số PNK, nhà cung cấp
   - Export CSV
3. [Action] Click "Tạo PNK mới"
4. [Form header] Nhập:
   - Số PNK (tự động): PNK2026-000042
   - Ngày nhập (default: today)
   - Loại nhập: Mua hàng / Thu hồi SX / Khác
   - Kho nhập + Địa điểm (text)
   - Bộ phận
5. [Form: người giao] Nhập họ tên người giao hàng
6. [Form: hóa đơn] "Theo Hóa đơn số ... ngày ..."
7. [Form: NCC] Chọn/tìm nhà cung cấp (nếu loại = mua hàng)
8. [Grid] Nhập từng dòng hàng:
   - Click "Thêm dòng" → chọn hàng từ modal
   - Cột B: Tên hàng (auto fill)
   - Cột C: Mã hàng (auto fill)
   - Cột D: ĐVT (auto fill)
   - Cột 1: Số lượng theo hóa đơn
   - Cột 2: Số lượng thực nhập
   - Cột 3: Đơn giá
   - Cột 4: Thành tiền = Cột 2 × Cột 3 (auto calc)
9. [Form footer] Xem tổng tiền + số tiền bằng chữ
10. [Form] Nhập ghi chú + số CT gốc kèm theo
11. [Submit] POST /api/goods-receipt/draft → 201
12. [Detail] Mở detail PNK vừa tạo:
    - Xem lại toàn bộ thông tin
    - Nếu OK → "Ghi sổ" → posted
    - Nếu sai → "Hủy" → cancelled
13. [Posted] Click in → Mẫu 01-VT full page → Ctrl+P → lưu PDF
```

---

## 8. GAPS ANALYSIS — existing goods_receipt.php vs Mẫu 01-VT

| # | Yêu cầu TT99 | Hiện tại | Gap | Priority |
|---|---|---|---|---|
| G01 | Header: Tên đơn vị + Bộ phận trên form in | ❌ Không in header | Cần thêm vào print template | P0 |
| G02 | Mẫu số 01-VT kèm TT99 | ⚠️ Có subtitle "Mẫu số 01-VT (Kèm theo TT 99/2025/TT-BTC)" | Cần trên bản in | P0 |
| G03 | Số hóa đơn/lệnh nhập ("Theo ... số ... ngày ...") | ❌ Không có field | Thêm invoice_ref + invoice_date vào form | P0 |
| G04 | Cột 1 (Số lượng theo CT) vs Cột 2 (Số lượng thực nhập) | ❌ Chỉ có 1 cột qty_received | Thêm cột qty_in_document riêng | P0 |
| G05 | Người giao hàng (riêng, không gộp NCC) | ❌ Chỉ có supplier_name | Thêm deliverer_name field | P1 |
| G06 | Nhập tại kho + Địa điểm | ⚠️ Có warehouse_id, không có location text | Thêm location text field | P1 |
| G07 | Nợ/Có account codes trên form | ❌ Không hiển thị | Hiển thị account codes (Nợ 15x / Có 331) | P1 |
| G08 | Số chứng từ gốc kèm theo | ❌ Không có field | Thêm attach_doc field | P1 |
| G09 | In A4 portrait đúng Mẫu 01-VT | ⚠️ printPNK = window.print() | Cần print template riêng | P0 |
| G10 | 5 ô chữ ký trên bản in | ⚠️ Có HTML sig blocks trên form, chưa in | Print template phải render signatures | P0 |
| G11 | Số tiền bằng chữ trên bản in | ⚠️ Có amount_in_words trong API, chưa in | Print template render | P0 |
| G12 | Tự động tạo sổ kho (Thẻ kho) từ PNK | ⚠️ Tồn kho tự động cập nhật | OK (InventoryService) | – |
| G13 | Phân biệt 2 liên / 3 liên khi in | ❌ Không hỗ trợ | Thêm copy indicator trên print | P2 |
| G14 | Soft copy lưu audit trail | ✅ JournalService + AuditLogger | OK | – |
| G15 | E-invoice import → auto GR | ✅ EInvoiceImportService | OK | – |

**Tổng: 13 gaps (P0: 5, P1: 5, P2: 3)**

---

## 9. PRINT TEMPLATE: Mẫu 01-VT đề xuất

```
ĐƠN VỊ: ... CÔNG TY ABC ...
BỘ PHẬN: ... Phòng Kinh doanh ...

        Mẫu số: 01 - VT
  (Kèm theo TT 99/2025/TT-BTC)

           PHIẾU NHẬP KHO
Ngày 15 tháng 06 năm 2026
Số: PNK2026-000042

Nợ: 156              Có: 331

- Họ và tên người giao: Nguyễn Văn A
- Theo Hóa đơn số HD001234 ngày 14 tháng 06 năm 2026
- Nhập tại kho: Kho chính                  Địa điểm: Tầng 1, số 1 ABC

+---+--------------------------------+------+-----+----------+----------+-----------+-----------+
| A | B                              | C    | D   |    1     |    2     |     3     |     4     |
+---+--------------------------------+------+-----+----------+----------+-----------+-----------+
| 1 | Máy tính xách tay Dell XPS     | MT01 | cái |    10    |    10    | 25,000,000|250,000,000|
| 2 | Chuột không dây Logitech       | MT02 | cái |    10    |     8    |    500,000|  4,000,000|
+---+--------------------------------+------+-----+----------+----------+-----------+-----------+
|                                          Cộng                                           254,000,000|
+---+--------------------------------+------+-----+----------+----------+-----------+-----------+

Tổng số tiền (viết bằng chữ): Hai trăm năm mươi tư triệu đồng chẵn

Số chứng từ gốc kèm theo: 01 Hóa đơn GTGT số HD001234

  Ngày 15 tháng 06 năm 2026

  Người lập phiếu    Người giao hàng      Thủ kho         Kế toán trưởng     Giám đốc
  (Ký, họ tên)       (Ký, họ tên)       (Ký, họ tên)    (Ký, họ tên)      (Ký, họ tên)

  (Liên 2: Thủ kho giữ - Ghi Thẻ kho và chuyển Phòng Kế toán)
```

---

## 10. IMPLEMENTATION PLAN (P0-P2)

### Phase P0 — Bắt buộc trước PROD (5 items)

| Task | File | Mô tả | Effort | Test |
|---|---|---|---|---|
| T01: Cột 1 (SL theo CT) + Cột 2 (SL thực nhập) | goods_receipt.php + GoodsReceiptLine + DB migration | Thêm qty_in_document field, tách làm 2 cột | 4h | 5 tests |
| T02: Số hóa đơn/lệnh nhập + ngày | goods_receipt.php + GoodsReceipt + DB migration | Thêm invoice_ref + invoice_date fields | 2h | 3 tests |
| T03: Print template Mẫu 01-VT | goods_receipt_print.php | Full A4 portrait template, 5 signatures, all fields | 6h | 5 tests |
| T04: In header (tên đơn vị, bộ phận) + Mẫu số 01-VT | print template | Header block from company config | 1h | 0 test |
| T05: Render signatures + amount in words trên bản in | print template + API | Chữ ký + tổng tiền bằng chữ | 2h | 2 tests |

### Phase P1 — Nâng cao chất lượng (5 items)

| Task | File | Mô tả | Effort | Test |
|---|---|---|---|---|
| T06: Người giao hàng field | goods_receipt.php + GoodsReceipt + DB migration | Thêm deliverer_name | 1h | 1 test |
| T07: Địa điểm kho text field | goods_receipt.php + DB migration | Thêm warehouse_location | 1h | 1 test |
| T08: Nợ/Có account codes display | goods_receipt.php | Hiển thị TK Nợ 15x / Có 331 trên form | 0.5h | 0 test |
| T09: Số chứng từ gốc kèm theo | goods_receipt.php + DB migration | Thêm attach_doc field | 1h | 1 test |
| T10: Cảnh báo chênh lệch cột 1 ≠ cột 2 | goods_receipt.php (JS) + GoodsReceiptService | Warning khi SL thực nhập ≠ SL chứng từ | 1h | 2 tests |

### Phase P2 — Nice to have (3 items)

| Task | File | Mô tả | Effort | Test |
|---|---|---|---|---|
| T11: Số liên (2/3) khi in | print template | Copy indicator cho từng liên | 1h | 0 test |
| T12: Auto-create Biên bản kiểm nghiệm (03-VT) khi sai lệch | GoodsReceiptService + migration | Tự động sinh 03-VT nếu cột 1 ≠ cột 2 | 4h | 3 tests |
| T13: Tích hợp PO — tự động điền lines từ đơn hàng | goods_receipt.php + GoodsReceiptController | Nếu có po_id → load lines từ PO | 3h | 3 tests |

---

## 11. INTERNAL CONTROLS

| ID | Control | Type | Testing method |
|---|---|---|---|
| IC01 | Dr = Cr for every GR posting | Automated | JournalService check |
| IC02 | Period lock enforcement | Automated | GoodsReceiptService::assertPeriodOpen |
| IC03 | Control account block (15x → must use sub-accounts) | Automated | AccountRepository::isControl |
| IC04 | Posting rules validation | Automated | PostingRuleService |
| IC05 | Audit trail for every GR | Automated | AuditLogger::log |
| IC06 | Sequential PNK number | Automated | VoucherService (FOR UPDATE) |
| IC07 | Stock qty consistency = sum of GR lines | Automated | InventoryService::issueGoods/updateStockAndCostLayer |
| IC08 | Printed form = 4-5 signatures | Manual | In PNK → ký tay |
| IC09 | Segregation: lập phiếu ≠ ký duyệt | Manual | Permission: create ≠ approve |
| IC10 | Cột 2 (thực nhập) là cơ sở ghi sổ, cột 1 (CT) để đối chiếu | Automated | JournalService chỉ tính trên cột 2 |
| IC11 | Cancel GR = chỉ cancel draft, không ảnh hưởng stock | Automated | cancelReceipt kiểm tra status |
| IC12 | E-invoice import → auto GR: mapping đúng item + supplier | Automated | EInvoiceImportService |

---

## 12. CURRENT STATUS vs MISA/FAST/BRAVO reference

| Tính năng | Hệ thống | MISA | FAST | BRAVO | Ghi chú |
|---|---|---|---|---|---|
| Full-page form 01-VT | ✅ | ✅ | ✅ | ✅ | Tương đương |
| 2 cột: SL theo CT / SL thực nhập | ❌ | ✅ | ✅ | ✅ | P0 gap |
| Số hóa đơn/lệnh nhập | ❌ | ✅ | ✅ | ✅ | P0 gap |
| Mẫu 01-VT in ấn | ❌ | ✅ | ✅ | ✅ | P0 gap |
| 5 chữ ký | ❌ | ✅ | ✅ | ✅ | P0 gap |
| Người giao hàng riêng | ❌ | ✅ | ✅ | ✅ | P1 gap |
| Địa điểm kho | ❌ | ✅ | ✅ | ✅ | P1 gap |
| Nợ/Có codes | ❌ | ✅ | ✅ | ✅ | P1 gap |
| Tồn kho auto update | ✅ | ✅ | ✅ | ✅ | OK |
| Cost layer (FIFO/W.A.) | ✅ | ✅ | ✅ | ✅ | OK |
| Số tiền bằng chữ | ✅ | ✅ | ✅ | ✅ | OK |
| Audit trail | ✅ | ✅ | ✅ | ✅ | OK |
| Sequential PNK | ✅ | ✅ | ✅ | ✅ | OK |
| E-invoice auto GR | ✅ | ⚠️ (add-on) | ⚠️ (add-on) | ⚠️ (add-on) | Vượt trội |
| PO integration | ⚠️ (po_id nullable) | ✅ | ✅ | ✅ | P2 |
| Print batch | ❌ | ✅ | ❌ | ✅ | P2 |

---

## 13. KẾT LUẬN

**PROD verdict:** PHIẾU NHẬP KHO hiện tại **có thể vận hành cơ bản** nhưng **chưa đạt chuẩn Mẫu 01-VT** để in ấn và lưu hồ sơ giấy.

**Backend:** 90% OK — GoodsReceiptService tạo bút toán đúng, cập nhật tồn kho + cost layer, period lock, posting rules, sequential PNK, audit trail. 

**Frontend (input):** 70% OK — form full-page functional. Các thiếu sót chính:
- Thiếu cột 1 (SL theo CT) — chỉ có cột 2 (thực nhập)
- Thiếu trường "Theo hóa đơn số ... ngày ..."
- Thiếu người giao hàng riêng
- Thiếu địa điểm kho
- Thiếu Nợ/Có codes
- Thiếu số chứng từ gốc kèm theo

**Frontend (print):** 10% OK — chỉ có window.print(), chưa có print template chuẩn.

**Điểm mạnh vượt trội so với MISA/FAST/BRAVO:**
- Tự động tạo PNK từ e-invoice import (EInvoiceImportService) — đây là tính năng MISA/FAST/BRAVO không có sẵn
- Tích hợp đầy đủ với GL engine (JournalService)
- Cost layer FIFO/Weighted Average

**Khuyến nghị:** PROD-deploy với backend + form input hiện tại, nhưng phải có P0 tasks (T01-T05) trước khi coi là production-ready cho audit. Thời gian: ~4 ngày cho 1 developer (so với 2 ngày của Mẫu 02-TT vì PNK phức tạp hơn với 2 cột số lượng + hóa đơn reference).

**Ưu tiên cao nhất (P0):**
1. Thêm cột 1 (Số lượng theo chứng từ) — vì đây là yêu cầu bắt buộc của TT99, thiếu cột này làm mất khả năng đối chiếu với hóa đơn
2. Thêm số hóa đơn/lệnh nhập — audit trail gốc
3. Xây dựng print template Mẫu 01-VT đầy đủ — cần cho lưu hồ sơ giấy

---

## 14. TÀI LIỆU THAM KHẢO

- TT 99/2025/TT-BTC Phụ lục I — Mẫu 01-VT Phiếu nhập kho
- Thông tư 200/2014/TT-BTC (mẫu cũ, tham khảo so sánh)
- thuvienphapluat.vn — Mẫu 01-VT và hướng dẫn ghi
- ketoanthienung.net — Mẫu Excel/Word 01-VT
- hoatieu.vn — Mẫu 01-VT TT99
- webketoan.com — Mẫu 01-VT chuẩn
- docs/analysis/mau-02-tt-phieu-chi-tt99-analysis.md — Reference analysis (Mẫu 02-TT)
- database/migrations/127_alter_goods_receipts_for_01vt.php — Migration hiện tại
- database/migrations/133_add_goods_receipt_to_einvoice_imports.php — E-invoice GR integration
