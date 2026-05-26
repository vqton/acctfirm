# AP/AR Engine Brain Logic — Phân tích Nghiệp vụ Công nợ Doanh nghiệp SME Việt Nam

> **Tác giả:** Chief Accountant — 20,000+ giờ chiến đấu với công nợ SME  
> **Cập nhật:** Tháng 5/2026  
> **Căn cứ pháp lý:** Thông tư 99/2025/TT-BTC, Thông tư 133/2016/TT-BTC, Thông tư 48/2019/TT-BTC, Luật Kế toán 2015, Nghị định 320/2025/NĐ-CP  
> **Tham chiếu:** Kế toán Thiên Ưng, Webketoan, ACC Group, Bizzi, Lac Viet

---

## Mục lục

1. [AP/AR Engine Brain Logic](#1-apar-engine-brain-logic)
2. [Real SME Finance Scenarios](#2-real-sme-finance-scenarios)
3. [Use Cases](#3-use-cases)
4. [Process Flow Logic](#4-process-flow-logic)
5. [AP/AR Rule Logic](#5-apar-rule-logic)
6. [Data Flow and Workflow Logic](#6-data-flow-and-workflow-logic)
7. [User Journey](#7-user-journey)
8. [SME Pain Analysis](#8-sme-pain-analysis)

---

# 1. AP/AR Engine Brain Logic

## 1.1 Why AP/AR Engine Exist

**Vấn đề:** Trong doanh nghiệp SME Việt Nam, công nợ là nguyên nhân số 1 dẫn đến:
- Mất dòng tiền (cash flow)
- Sai báo cáo tài chính
- Mất khách hàng vì sai sót đối chiếu
- Bị nhà cung cấp ngừng giao hàng vì chậm thanh toán
- Kiểm toán từ chối vì không đối chiếu được công nợ

**AP/AR Engine giải quyết:**
- Tự động ghi nhận công nợ khi phát sinh hóa đơn
- Tự động phân bổ thanh toán vào đúng hóa đơn
- Tự động tính tuổi nợ (aging) theo thời gian thực
- Tự động cảnh báo nợ quá hạn
- Tự động đối chiếu GL với sub-ledger
- Ngăn chặn double payment, sai sót đối tượng
- Lưu audit trail cho kiểm toán

## 1.2 How AP Lifecycle Work

AP lifecycle — vòng đời công nợ phải trả nhà cung cấp (TK 331):

```
Đặt hàng (PO) → Nhận hàng (Receipt) → Hóa đơn NCC (Invoice) → 
Đối chiếu 3-way (PO + Receipt + Invoice) → Ghi nhận công nợ → 
Lên kế hoạch thanh toán → Phê duyệt → Thanh toán → Đối chiếu → Đóng công nợ
```

**Mỗi bước có kiểm soát riêng:**

| Bước | Kiểm soát | Rủi ro nếu không có |
|---|---|---|
| PO | Phê duyệt theo hạn mức, ngân sách | Mua hàng không kiểm soát → vượt budget |
| Receipt | Kho xác nhận số lượng, chất lượng | Thanh toán hàng không đúng số lượng |
| Invoice | 3-way match: PO + receipt + invoice | Trả tiền sai đơn giá, sai thuế |
| Payment approval | Multi-level theo giá trị | Gian lận, chuyển tiền sai đối tượng |
| Payment execution | Ủy nhiệm chi, séc, tiền mặt | Mất tiền, double payment |
| Reconciliation | Đối chiếu sao kê NH + biên bản xác nhận | Sai số dư, không phát hiện gian lận |
| Closing | Khóa công nợ cuối kỳ, đối chiếu GL | BC01 sai → kiểm toán fail |

## 1.3 How AR Lifecycle Work

AR lifecycle — vòng đời công nợ phải thu khách hàng (TK 131):

```
Bán hàng (Sales Order) → Giao hàng (Delivery) → Xuất hóa đơn (Invoice) → 
Ghi nhận công nợ → Theo dõi tuổi nợ → Nhắc nợ → Thu tiền → 
Phân bổ thu tiền → Đối chiếu → Đóng công nợ
```

**Kiểm soát theo từng bước:**

| Bước | Kiểm soát | Rủi ro |
|---|---|---|
| Sales Order | Phê duyệt giá, hạn mức tín dụng | Bán chịu cho khách không có khả năng thanh toán |
| Delivery | Kho xuất đúng hàng, đúng số lượng | Xuất thiếu/xuất thừa → sai hóa đơn |
| Invoice | Xuất hóa đơn đúng GTGT, đúng thuế suất | Sai thuế → bị phạt, mất khấu trừ |
| Aging tracking | Phân loại nợ theo tuổi (0-30, 31-60, 61-90, >90) | Không phát hiện nợ xấu kịp thời |
| Collection | Nhắc nợ tự động, escalation | Chậm thu → thiếu dòng tiền |
| Payment allocation | Phân bổ đúng hóa đơn | Ghi nhận sai → lệch số dư |
| Reconciliation | Đối chiếu với khách hàng định kỳ | Tranh chấp, mất khách |
| Provisioning | Trích lập dự phòng nợ khó đòi (TT 48) | BCTC thiếu thận trọng → thuế TNDN sai |

## 1.4 How Invoice Matching Happen

**3-Way Matching (AP):**
1. **PO (Purchase Order):** Số lượng, đơn giá, điều khoản thanh toán, thuế suất
2. **Receipt (Phiếu nhập kho):** Số lượng thực nhận, chất lượng, kho
3. **Invoice (Hóa đơn NCC):** Số lượng, đơn giá, thuế GTGT, tổng tiền

**Quy tắc match:**
```
Nếu PO qty = Receipt qty = Invoice qty
  AND PO price = Invoice price
  AND PO tax rate = Invoice tax rate
→ Auto-match → Ghi nhận công nợ

Nếu sai lệch trong tolerance (+-5% hoặc +-500,000 VND)
→ Flag warning, chờ xác nhận

Nếu sai lệch ngoài tolerance
→ Block, gửi thông báo cho bộ phận mua hàng
```

**2-Way Matching (AR):**
1. **Delivery (Phiếu xuất kho):** Số lượng, chủng loại
2. **Invoice (Hóa đơn bán ra):** Số lượng, đơn giá, thuế, chiết khấu

## 1.5 How Debt Tracking Happen

**Sub-ledger tracking — theo dõi chi tiết từng đối tượng:**

```
TK 131 (Phải thu KH) — chi tiết theo từng khách hàng
  ├── Hóa đơn A: 50,000,000 (chưa đến hạn)
  ├── Hóa đơn B: 20,000,000 (quá hạn 30 ngày)
  └── Hóa đơn C: 10,000,000 (quá hạn 60 ngày)

TK 331 (Phải trả NCC) — chi tiết theo từng nhà cung cấp
  ├── Hóa đơn X: 100,000,000 (chưa đến hạn)
  └── Hóa đơn Y: 30,000,000 (quá hạn 15 ngày)
```

**Cập nhật real-time mỗi khi có giao dịch:**
- Invoice → tăng công nợ
- Payment/Credit note → giảm công nợ
- Debit note → tăng công nợ
- Discount → giảm công nợ
- Write-off → xóa công nợ (theo dõi ngoại bảng)

**Kiểm tra GL vs sub-ledger định kỳ:**
```sql
-- Phải thu: GL 131 = Σ sub-ledger 131
-- Phải trả: GL 331 = Σ sub-ledger 331
-- Nếu lệch > tolerance → cảnh báo
```

## 1.6 How Payment Allocation Happen

**Nguyên tắc kế toán (VAS 01 — Thận trọng):**

Khi thanh toán cho nhà cung cấp, hệ thống phải xác định:
1. Thanh toán cho hóa đơn nào?
2. Số tiền phân bổ cho từng hóa đơn?
3. Còn dư hay thiếu?

**Chiến lược allocation (theo thứ tự ưu tiên):**

| Priority | Strategy | Khi nào dùng |
|---|---|---|
| 1 | Exact invoice matching | KH chuyển tiền đúng số hóa đơn |
| 2 | FIFO (invoice cũ nhất) | KH chuyển tiền không ghi rõ hóa đơn |
| 3 | Overdue first | KH chuyển tiền nhưng không chỉ định |
| 4 | Largest invoice first | Doanh nghiệp muốn giảm số lượng hóa đơn tồn |
| 5 | Manual allocation | Kế toán tự chọn hóa đơn |

**Xử lý partial payment:**
```
Hóa đơn: 50,000,000
Thanh toán: 30,000,000
→ Công nợ còn: 20,000,000 (theo dõi tiếp)
→ Aging tính trên số dư còn lại
```

**Xử lý overpayment:**
```
Hóa đơn: 50,000,000
Thanh toán: 55,000,000
→ Công nợ = 0 (đã đóng)
→ Dư 5,000,000 ghi nhận là tạm ứng (trả trước cho NCC)
→ Hoặc yêu cầu NCC trả lại
```

## 1.7 How Collection Allocation Happen

**Khi khách hàng thanh toán:**

1. **Cash Application:** Xác định hóa đơn được thanh toán
2. **Auto-allocation:** Nếu khách hàng ghi rõ số hóa đơn → tự động phân bổ
3. **Manual allocation:** Nếu không rõ → kế toán chọn
4. **Partial collection:** Ghi nhận 1 phần, theo dõi phần còn lại
5. **Over-collection:** Ghi nhận tạm ứng của khách (TK 131 dư Có)

**Nguyên tắc thận trọng:**
```
Nếu khách nợ nhiều hóa đơn và không chỉ định thanh toán cho hóa đơn nào:
→ Ưu tiên thanh toán cho hóa đơn cũ nhất (FIFO)
→ Hoặc ưu tiên hóa đơn quá hạn lâu nhất
```

## 1.8 How Reconciliation Happen

**Đối chiếu công nợ — 3 cấp độ:**

### Cấp 1: GL vs Sub-ledger (Nội bộ)
```
Mục đích: Đảm bảo tổng số dư phải thu/phải trả trên sổ cái
khớp với tổng chi tiết công nợ theo đối tượng

Tần suất: Hàng ngày (cuối ngày)
Chịu trách nhiệm: Kế toán tổng hợp
```

### Cấp 2: Doanh nghiệp vs Đối tác (Ngoại bộ)
```
Mục đích: Đối chiếu số dư với khách hàng/nhà cung cấp
Phương thức: Biên bản xác nhận công nợ (theo mẫu)
Tần suất: Hàng tháng/quý (tối thiểu cuối năm)
Chịu trách nhiệm: Kế toán công nợ
```

### Cấp 3: Sub-ledger vs Giao dịch gốc (Chi tiết)
```
Mục đích: Kiểm tra từng hóa đơn, từng chứng từ thanh toán
Phương thức: Truy xuất chứng từ gốc
Tần suất: Khi kiểm toán
```

## 1.9 How Adjustment Happen

**Các loại điều chỉnh công nợ:**

| Loại | TK Nợ | TK Có | Nguyên nhân |
|---|---|---|---|
| Credit note (AP) | 331 | 152/156/632 | Trả lại hàng NCC |
| Credit note (AR) | 521/531 | 131 | Hàng bán trả lại |
| Debit note (AP) | 331 | 515/711 | Giảm giá, chiết khấu NCC |
| Debit note (AR) | 635/811 | 131 | Chiết khấu thanh toán cho KH |
| Discount (AP) | 331 | 515 | Chiết khấu thanh toán sớm |
| Discount (AR) | 635 | 131 | Chiết khấu cho KH thanh toán sớm |
| Write-off (AP) | 711 | 331 | Xóa nợ NCC (hiếm) |
| Write-off (AR) | 642/2293 | 131 | Xóa nợ phải thu khó đòi |
| Revaluation (FC) | 635/413 | 413/515 | Đánh giá lại công nợ ngoại tệ |

## 1.10 How Approval Happen

**Phê duyệt thanh toán — multi-level theo giá trị:**

| Hạn mức | Người duyệt | Ghi chú |
|---|---|---|
| < 10,000,000 | Kế toán trưởng | Kiểm tra hồ sơ, hóa đơn |
| 10,000,000 - 100,000,000 | Giám đốc tài chính | + Kiểm tra ngân sách |
| 100,000,000 - 1,000,000,000 | Tổng giám đốc | + Kiểm tra dòng tiền |
| > 1,000,000,000 | Hội đồng quản trị | + Đấu thầu/báo giá |

**Nguyên tắc bất kiêm nhiệm (Segregation of Duties):**
- Người đề nghị thanh toán ≠ Người phê duyệt ≠ Người thực hiện
- Kế toán công nợ đề nghị → Kế toán trưởng duyệt → Thủ quỹ/Giám đốc ký chi

## 1.11 How Audit Tracking Happen

**Audit trail cho mọi giao dịch công nợ:**
- Thời gian tạo/sửa/xóa
- Người thực hiện (user_id)
- IP address
- Giá trị cũ → giá trị mới
- Lý do thay đổi
- Chứng từ gốc (hóa đơn, biên bản, quyết định)

**Bất biến (immutable):**
- Không được xóa bút toán đã post
- Điều chỉnh bằng bút toán đảo
- Mọi thay đổi đều có audit trail

## 1.12 How Compliance Checking Happen

**Kiểm tra tuân thủ tự động:**

| Rule | Kiểm tra | Hậu quả nếu vi phạm |
|---|---|---|
| Hóa đơn có MST hợp lệ | Validate MST qua API Tổng cục Thuế | Không được khấu trừ VAT |
| Hóa đơn không trùng số | Check unique invoice number | Kê khai sai → phạt |
| Thuế suất đúng biểu | Check tax rate theo mặt hàng | Kê khai sai → truy thu |
| Hóa đơn trong kỳ | Check ngày hóa đơn vs kỳ kế toán | Sai kỳ → điều chỉnh |
| Đối tượng còn hoạt động | Check trạng thái KH/NCC | Thanh toán cho đối tượng ngừng hoạt động |
| Hạn mức tín dụng | Check remaining credit limit | Bán chịu vượt quá rủi ro |

---

# 2. Real SME Finance Scenarios

## 2.1 Supplier Invoice (Hóa đơn mua vào)

**Cảnh báo từ thực tế SME Việt Nam:**

**Kịch bản 1:** Nhà cung cấp xuất hóa đơn sai thuế suất
- Hóa đơn ghi 8% nhưng mặt hàng chịu thuế 10%
- → Kê khai sai → Bị truy thu + phạt chậm (TT 78)
- → Kế toán phát hiện → Yêu cầu xuất hóa đơn điều chỉnh

**Kịch bản 2:** Mua hàng không có hóa đơn (bán lẻ, chợ đầu mối)
- SME thường gặp ở VLXD, thực phẩm, nông sản
- → Không được khấu trừ VAT → Chi phí cao hơn
- → Phải yêu cầu NCC xuất hóa đơn hoặc chấp nhận không khấu trừ

**Kịch bản 3:** Hóa đơn xuất trước, hàng về sau
- Thường gặp với hàng nhập khẩu, hàng đặt trước
- → Ghi nhận tạm thời vào TK 151 (Hàng mua đang đi đường)
- → Khi hàng về → nhập kho → kết chuyển 151→152/156

## 2.2 Customer Invoice (Hóa đơn bán ra)

**Kịch bản 1:** Khách hàng yêu cầu xuất hóa đơn trước khi giao hàng
- SME thường gặp ở xây dựng, gia công
- → Nguy cơ: xuất hóa đơn nhưng không thu được tiền
- → Giải pháp: Chỉ xuất hóa đơn khi đã thu tiền hoặc có bảo lãnh

**Kịch bản 2:** Xuất hóa đơn sai MST khách hàng
- Sai 1 số → Hóa đơn không hợp lệ
- → Phải lập biên bản điều chỉnh, xuất hóa đơn thay thế
- → Rủi ro: bị phạt nếu không điều chỉnh kịp thời

## 2.3 Advance Payment (Trả trước cho NCC)

**Hạch toán:**
```
Nợ 331 (Phải trả NCC)
Có 111/112 (Tiền mặt/NH)
```

**Rủi ro thực tế:**
- Trả trước 100% nhưng NCC không giao hàng
- → Phải theo dõi riêng, yêu cầu bảo lãnh
- → Nếu không đòi được → ghi nhận chi phí khác (TK 632/642) + làm thủ tục xóa nợ

## 2.4 Advance Collection (Thu trước của KH)

**Hạch toán:**
```
Nợ 111/112 (Tiền mặt/NH)
Có 131 (Phải thu KH) — dư Có
```

**Lưu ý trên BCTC:**
- TK 131 dư Có → không bù trừ với dư Nợ
- → Trình bày bên Nợ phải trả (mục "Người mua trả tiền trước")

## 2.5 Partial Payment/Collection

**Standard process:**
```
Hóa đơn: 100,000,000
Lần 1: 30,000,000 → công nợ còn 70,000,000
Lần 2: 40,000,000 → công nợ còn 30,000,000
Lần 3: 30,000,000 → công nợ = 0 (đóng)
```

**Khi thanh toán nhiều lần, aging tính như thế nào?**
- Công nợ gốc 100,000,000, đến hạn 30/04
- Thanh toán 30,000,000 ngày 15/05
- → Số dư còn lại 70,000,000 vẫn bị tính quá hạn từ 30/04

## 2.6 Overpayment/Over-collection

**Nguyên nhân thực tế:**
- Khách hàng tính nhầm, chuyển khoản thừa
- Doanh nghiệp trả thừa cho NCC
- Chênh lệch tỷ giá khi thanh toán ngoại tệ

**Xử lý:**
- Nếu NCC/KH đồng ý: trừ vào lần thanh toán sau
- Nếu không: yêu cầu hoàn trả
- Nếu không xác định được: ghi nhận tạm thời vào TK 338/138

## 2.7 Underpayment/Under-collection

**Nguyên nhân:**
- Chiết khấu thanh toán (KH được giảm 1% nếu thanh toán sớm)
- Phí chuyển tiền (NH trừ phí trước khi chuyển)
- Tranh chấp chất lượng → KH tự ý trừ tiền

**Xử lý:**
```
Nếu KH được hưởng chiết khấu 1%:
  Nợ 635 (Chi phí tài chính) — 1,000,000
  Nợ 112 (Tiền NH) — 99,000,000
  Có 131 (Phải thu KH) — 100,000,000

Nếu KH tự ý trừ tiền do tranh chấp:
  → Ghi nhận tạm thời, chờ xử lý
  → Nếu chấp nhận: debit note
  → Nếu không: yêu cầu thanh toán đủ
```

## 2.8 Credit Note (Chứng từ điều chỉnh giảm)

**AP Credit Note — Trả lại hàng cho NCC:**

```
Nợ 331 (Phải trả NCC) — 110,000,000 (tổng GT)
Có 152/156 (Hàng tồn kho) — 100,000,000 (giá chưa thuế)
Có 1331 (Thuế GTGT được khấu trừ) — 10,000,000 (nếu đã kê khai)
```

**AR Credit Note — Hàng bán trả lại:**

```
Nợ 521 (Hàng bán trả lại) — 100,000,000 (giá bán chưa thuế)
Nợ 33311 (Thuế GTGT) — 10,000,000
Có 131 (Phải thu KH) — 110,000,000

Đồng thời:
Nợ 156 (Hàng tồn kho) — 80,000,000 (giá vốn)
Có 632 (Giá vốn hàng bán) — 80,000,000
```

## 2.9 Debit Note (Chứng từ điều chỉnh tăng)

**AP Debit Note — NCC báo tăng giá:**
```
Nợ 152/156 (Hàng tồn kho) — tăng giá chưa thuế
Nợ 1331 (Thuế GTGT) — tăng thuế
Có 331 (Phải trả NCC) — tổng tăng
```

**AR Debit Note — Tính thêm tiền cho KH:**
```
Nợ 131 (Phải thu KH) — tổng tăng
Có 511 (Doanh thu) — tăng giá chưa thuế
Có 33311 (Thuế GTGT) — tăng thuế
```

## 2.10 Payment/Collection Cancel

**Hủy thanh toán (đã chuyển tiền nhưng sai):**
```
1. NCC báo đã nhận được tiền nhưng sai hóa đơn
2. Làm văn bản đề nghị NCC chuyển trả
3. Khi nhận lại tiền:
   Nợ 111/112
   Có 331
4. Sau đó thanh toán lại đúng hóa đơn
```

## 2.11 Bad Debt (Nợ khó đòi)

**Phân loại theo TT 48/2019/TT-BTC:**

| Thời gian quá hạn | Tỷ lệ trích lập |
|---|---|
| > 6 tháng | 30% |
| > 1 năm | 50% |
| > 2 năm | 100% |

**Điều kiện được trích lập:**
1. Có chứng từ gốc (hợp đồng, biên bản đối chiếu)
2. Đã quá hạn thanh toán theo thỏa thuận
3. Có bằng chứng không thu hồi được

**Hạch toán dự phòng:**
```
Cuối niên độ:
Nợ 642 (Chi phí QLDN)
Có 2293 (Dự phòng phải thu khó đòi)

Khi xóa nợ:
Nợ 2293 (Nếu đã trích lập)
Nợ 642 (Phần chênh lệch)
Có 131 (Phải thu KH)
```

## 2.12 Foreign Currency Payment

**Hạch toán ngoại tệ theo TT 99/2025/TT-BTC:**
```
Mua hàng 10,000 USD, tỷ giá 25,500
Nợ 156 — 255,000,000 (10,000 * 25,500)
Nợ 1331 — 25,500,000 (nếu VAT)
Có 331 — 280,500,000

Thanh toán 10,000 USD, tỷ giá 25,700
Nợ 331 — 280,500,000 (ghi nhận theo tỷ giá tại ngày mua)
Nợ 635 — 2,000,000 (chênh lệch tỷ giá)
Có 1122 — 257,000,000 (10,000 * 25,700)
```

**Đánh giá lại cuối kỳ (TT 99 mới — không còn TK 413):**
```
Nợ 635 (Lỗ tỷ giá)
Có 131/331 (tăng nợ)
Hoặc Nợ 131/331 (giảm nợ)
Có 515 (Lãi tỷ giá)
```

---

# 3. Use Cases

## UC-AP-01: Ghi nhận hóa đơn mua hàng trong nước

| Trường | Giá trị |
|---|---|
| **Tên** | Ghi nhận hóa đơn mua hàng trong nước |
| **Mục tiêu** | Ghi nhận chính xác công nợ phải trả NCC khi có hóa đơn |
| **Tác nhân** | Kế toán mua hàng, Kế toán công nợ |
| **Điều kiện trước** | Hợp đồng mua bán, PO được duyệt, hàng đã nhập kho |
| **Trigger** | Nhận được hóa đơn GTGT từ NCC |
| **Happy path** | 3-way match OK → ghi nhận Nợ 152/156/1331/Có 331 |
| **Alternative** | Hàng về trước hóa đơn → ghi nhận tạm 331, chờ hóa đơn |
| **Exception** | Sai thông tin hóa đơn → yêu cầu NCC điều chỉnh |
| **Validation** | MST NCC hợp lệ, hóa đơn không trùng, thuế suất đúng |
| **Accounting** | Nợ 156/152, Nợ 1331, Có 331 |
| **Financial impact** | Tăng công nợ, tăng tồn kho, tăng VAT được khấu trừ |
| **Operational risk** | Nhầm số lượng/đơn giá → sai tồn kho và công nợ |
| **Compliance risk** | Hóa đơn giả/không hợp lệ → bị phạt, mất khấu trừ VAT |
| **Kết quả** | Công nợ NCC tăng, hàng tồn kho tăng |

## UC-AP-02: Thanh toán một phần cho NCC

| Trường | Giá trị |
|---|---|
| **Tên** | Thanh toán một phần cho NCC |
| **Mục tiêu** | Thanh toán một phần công nợ đúng hạn, tránh phạt |
| **Tác nhân** | Kế toán công nợ, Kế toán trưởng (duyệt) |
| **Điều kiện trước** | Có hóa đơn đã ghi nhận, còn hạn thanh toán |
| **Trigger** | Hạn thanh toán sắp đến, thiếu dòng tiền |
| **Happy path** | Thanh toán 70% → còn 30% → hẹn thanh toán tiếp |
| **Alternative** | NCC đồng ý gia hạn → không cần thanh toán partial |
| **Exception** | NCC không đồng ý partial → phải vay/thu hồi công nợ để trả đủ |
| **Validation** | Số tiền ≤ số dư, NCC còn hoạt động |
| **Accounting** | Nợ 331/Có 112 |
| **Financial impact** | Giảm tiền, giảm công nợ, tăng phí phạt nếu trả chậm |
| **Operational risk** | Partial payment không được ghi nhận → NCC không cập nhật |
| **Compliance risk** | Không có chứng từ cho phần còn lại |
| **Kết quả** | Công nợ giảm 70%, dư 30% tiếp tục theo dõi |

## UC-AP-03: Chiết khấu thanh toán sớm NCC

| Trường | Giá trị |
|---|---|
| **Tên** | Chiết khấu thanh toán sớm NCC |
| **Mục tiêu** | Hưởng chiết khấu khi thanh toán trước hạn |
| **Tác nhân** | Kế toán công nợ |
| **Điều kiện trước** | Hợp đồng có điều khoản chiết khấu thanh toán |
| **Trigger** | Thanh toán trong thời gian được hưởng chiết khấu |
| **Happy path** | Trả 99,000,000 thay vì 100,000,000 (chiết khấu 1%) |
| **Alternative** | Thanh toán đúng hạn → không hưởng chiết khấu |
| **Exception** | NCC không đồng ý chiết khấu dù đã thỏa thuận |
| **Validation** | Hóa đơn trong hạn chiết khấu, thỏa thuận có chữ ký |
| **Accounting** | Nợ 331: 100tr, Có 112: 99tr, Có 515: 1tr |
| **Financial impact** | Tiết kiệm chi phí tài chính |
| **Operational risk** | Nhầm tỷ lệ chiết khấu → tranh chấp |
| **Compliance risk** | Không có chứng từ cho khoản chiết khấu |
| **Kết quả** | Giảm công nợ, tăng doanh thu tài chính |

## UC-AP-04: Trả lại hàng NCC

| Trường | Giá trị |
|---|---|
| **Tên** | Trả lại hàng NCC |
| **Mục tiêu** | Điều chỉnh giảm công nợ khi trả lại hàng |
| **Tác nhân** | Kế toán kho, Kế toán công nợ |
| **Điều kiện trước** | Hàng đã nhập kho, đã ghi nhận công nợ, NCC đồng ý nhận lại |
| **Trigger** | Hàng kém chất lượng/sai quy cách |
| **Happy path** | Trả hàng → nhận credit note → giảm công nợ |
| **Alternative** | NCC xuất hóa đơn điều chỉnh giảm |
| **Exception** | NCC từ chối nhận lại → hòa giải/thưa kiện |
| **Validation** | Biên bản trả lại có chữ ký NCC, hàng còn nguyên vẹn |
| **Accounting** | Nợ 331, Có 152/156, Có 1331 |
| **Financial impact** | Giảm công nợ, giảm tồn kho, điều chỉnh VAT |
| **Operational risk** | Hàng đã qua sử dụng → NCC từ chối |
| **Compliance risk** | Không có hóa đơn điều chỉnh → không được giảm VAT |
| **Kết quả** | Công nợ giảm, tồn kho giảm |

## UC-AR-01: Ghi nhận hóa đơn bán hàng

| Trường | Giá trị |
|---|---|
| **Tên** | Ghi nhận hóa đơn bán hàng |
| **Mục tiêu** | Ghi nhận doanh thu và công nợ phải thu |
| **Tác nhân** | Kế toán bán hàng |
| **Điều kiện trước** | Hàng đã xuất kho, đã giao cho KH |
| **Trigger** | Xuất hóa đơn GTGT |
| **Happy path** | Nợ 131/Có 511, Có 33311 |
| **Alternative** | Bán thu tiền ngay → Nợ 111/112 |
| **Exception** | KH không chấp nhận hóa đơn → hủy, xuất lại |
| **Validation** | KH còn trong hạn mức tín dụng, MST hợp lệ |
| **Accounting** | Nợ 131, Có 511, Có 33311 |
| **Financial impact** | Tăng doanh thu, tăng công nợ, tăng VAT phải nộp |
| **Operational risk** | Xuất hóa đơn nhưng hàng chưa giao → sai doanh thu |
| **Compliance risk** | Sai thuế suất → phạt |
| **Kết quả** | Công nợ KH tăng, doanh thu tăng |

## UC-AR-02: Thu tiền một phần từ KH

| Trường | Giá trị |
|---|---|
| **Tên** | Thu tiền một phần từ KH |
| **Mục tiêu** | Ghi nhận một phần tiền thu, theo dõi số còn lại |
| **Tác nhân** | Thủ quỹ/Kế toán thanh toán |
| **Điều kiện trước** | Có hóa đơn, KH chuyển tiền |
| **Trigger** | Nhận được tiền từ KH (1 phần) |
| **Happy path** | Thu 30tr/100tr → ghi nhận, dư 70tr |
| **Alternative** | KH báo sẽ trả tiếp trong tháng |
| **Exception** | KH không trả tiếp → nợ quá hạn, gọi điện nhắc |
| **Validation** | Số tiền > 0, KH tồn tại |
| **Accounting** | Nợ 112, Có 131 |
| **Financial impact** | Tăng tiền, giảm công nợ |
| **Operational risk** | KH chuyển tiền không ghi rõ hóa đơn → phân bổ sai |
| **Compliance risk** | Không đối chiếu → KH khiếu nại |
| **Kết quả** | Công nợ giảm 30% |

## UC-AR-03: Chiết khấu thanh toán cho KH

| Trường | Giá trị |
|---|---|
| **Tên** | Chiết khấu thanh toán cho KH |
| **Mục tiêu** | KH thanh toán sớm → được hưởng chiết khấu |
| **Tác nhân** | Kế toán công nợ, Kế toán trưởng |
| **Điều kiện trước** | Hợp đồng có điều khoản chiết khấu |
| **Trigger** | KH thanh toán trong hạn được chiết khấu |
| **Happy path** | KH trả 99tr thay vì 100tr |
| **Alternative** | KH không yêu cầu chiết khấu |
| **Exception** | Tranh chấp tỷ lệ chiết khấu |
| **Validation** | Trong hạn chiết khấu, có thỏa thuận |
| **Accounting** | Nợ 112: 99tr, Nợ 635: 1tr, Có 131: 100tr |
| **Financial impact** | Giảm chi phí tài chính (so với trả chậm) |
| **Operational risk** | Chiết khấu quá cao → lỗ |
| **Compliance risk** | Không khai báo thuế cho khoản chiết khấu |
| **Kết quả** | Công nợ = 0, KH hài lòng |

## UC-AR-04: Trích lập dự phòng nợ khó đòi

| Trường | Giá trị |
|---|---|
| **Tên** | Trích lập dự phòng nợ khó đòi |
| **Mục tiêu** | Phản ánh đúng giá trị thuần của khoản phải thu |
| **Tác nhân** | Kế toán trưởng |
| **Điều kiện trước** | Cuối niên độ kế toán |
| **Trigger** | Lập BCTC năm |
| **Happy path** | Trích lập 30% cho nợ > 6 tháng |
| **Alternative** | Hoàn nhập dự phòng nếu KH đã trả |
| **Exception** | Nợ > 2 năm, không có khả năng thu hồi → xóa nợ |
| **Validation** | Theo aging, theo TT 48 |
| **Accounting** | Nợ 642/Có 2293 |
| **Financial impact** | Giảm lợi nhuận, giảm thuế TNDN |
| **Operational risk** | Trích thiếu → BCTC không thận trọng |
| **Compliance risk** | Trích vượt → chi phí không được trừ |
| **Kết quả** | Dự phòng được ghi nhận, BCTC thận trọng |

---

# 4. Process Flow Logic

## 4.1 End-to-End AP Process

```
[Procurement] → [Receiving] → [Invoice] → [Approval] → [Payment] → [Reconciliation]
     │              │              │            │             │              │
     v              v              v            v             v              v
  PO Created    Goods in      3-way match   Multi-level    Payment        Bank recon
  + Budget      + QC          + tolerance   approval       execution      + Biên bản
  check         + PNK         + flag        + Escalation   + Remittance   ĐC công nợ
```

## 4.2 End-to-End AR Process

```
[Sales] → [Delivery] → [Invoice] → [Aging] → [Collection] → [Reconciliation]
   │           │            │          │            │              │
   v           v            v          v            v              v
Order       Goods out    E-invoice   Aging       Dunning        Bank
+ Credit    + PXK        + VAT       report      + Call         matching
limit       + Vận        + ETAX      + Alert     + Email        + Biên bản
check       chuyển       + MISA                  + Legal        ĐC công nợ
```

## 4.3 Invoice-to-Payment Flow

**Bước chi tiết cho AP:**

1. **Nhận hóa đơn từ NCC**
   - Scan/import hóa đơn điện tử
   - OCR thông tin hóa đơn
   - Validate MST qua API Tổng cục Thuế

2. **3-Way Matching**
   - Đối chiếu PO vs Receipt vs Invoice
   - Nếu match → tự động duyệt
   - Nếu không match → flag cho kế toán

3. **Ghi nhận kế toán**
   - Nợ 152/156/1331
   - Có 331
   - Cập nhật sub-ledger

4. **Lên lịch thanh toán**
   - Theo điều khoản hợp đồng (Net 30/60/90)
   - Theo dòng tiền hiện tại
   - Ưu tiên: hóa đơn quá hạn → sắp đến hạn → còn hạn

5. **Phê duyệt thanh toán**
   - Theo hạn mức
   - Theo ngân sách

6. **Thực hiện thanh toán**
   - Ủy nhiệm chi (ưu tiên)
   - Séc
   - Tiền mặt (hạn chế, < 20tr)

7. **Đối chiếu**
   - Đối chiếu sao kê NH
   - Biên bản xác nhận công nợ với NCC

## 4.4 Invoice-to-Collection Flow

**Bước chi tiết cho AR:**

1. **Xuất hóa đơn**
   - E-invoice qua CQT
   - Gửi KH qua email

2. **Ghi nhận kế toán**
   - Nợ 131
   - Có 511/33311

3. **Theo dõi aging**
   - Tự động phân loại theo tuổi
   - Cảnh báo khi sắp đến hạn

4. **Nhắc nợ (Dunning)**
   - Trước hạn 7 ngày: email nhắc
   - Quá hạn 7 ngày: gọi điện
   - Quá hạn 30 ngày: công văn
   - Quá hạn 60 ngày: pháp lý

5. **Thu tiền**
   - Chuyển khoản
   - Tiền mặt (tại quầy)
   - Cà thẻ (POS)

6. **Phân bổ tiền thu**
   - Auto-allocation theo reference
   - Manual allocation nếu cần

## 4.5 Month-End Closing Dependency

**Các bước khóa sổ cuối tháng liên quan công nợ:**

```
1. Khóa nhập/xuất kho (cut-off date)
2. Ghi nhận hết hóa đơn trong kỳ
3. Đối chiếu GL 131/331 với sub-ledger
4. Gửi biên bản xác nhận công nợ cho KH/NCC
5. Nhận biên bản xác nhận từ KH/NCC
6. Xử lý chênh lệch (nếu có)
7. Trích lập dự phòng nợ khó đòi
8. Đánh giá lại công nợ ngoại tệ (nếu có)
9. Kết chuyển doanh thu/chi phí
10. Khóa kỳ
```

## 4.6 Audit Preparation Flow

**Chuẩn bị hồ sơ công nợ cho kiểm toán:**

| Hồ sơ | Chịu trách nhiệm |
|---|---|
| Biên bản xác nhận công nợ KH/NCC | Kế toán công nợ |
| Danh sách aging chi tiết | Kế toán công nợ |
| Bảng đối chiếu GL vs sub-ledger | Kế toán tổng hợp |
| Hợp đồng mua bán (chọn mẫu) | Bộ phận mua hàng |
| PO + Receipt + Invoice (chọn mẫu) | Kế toán kho |
| Giấy báo nợ/báo có NH | Thủ quỹ |
| Biên bản đối chiếu NH | Kế toán ngân hàng |
| Bảng trích lập dự phòng | Kế toán trưởng |

---

# 5. AP/AR Rule Logic

## 5.1 Invoice Validity Check

```
1. MST người bán/người mua còn hiệu lực (qua API TCT)
2. Hóa đơn không trùng số (check unique)
3. Ngày hóa đơn trong kỳ kế toán hiện tại
4. Thuế suất đúng biểu (0%/5%/8%/10%/không chịu thuế)
5. Tổng tiền = số lượng × đơn giá × (1 + VAT%)
6. Chữ ký số hợp lệ (hóa đơn điện tử)
```

## 5.2 Duplicate Invoice Detection

```
Phát hiện trùng lặp dựa trên:
1. Số hóa đơn + MST người bán → khớp chính xác
2. Tổng tiền + Ngày hóa đơn + MST → gần giống (fuzzy)
3. Reference + PO number → trùng PO
4. OCR hash → trùng nội dung

Khi phát hiện:
→ Block tự động
→ Thông báo cho kế toán
→ Chờ xác nhận
```

## 5.3 Payment Mismatch Detection

```
1. Số tiền thanh toán ≠ số dư hóa đơn
   → Partial (nếu 0 < amount < balance)
   → Overpayment (nếu amount > balance)
   → Underpayment (nếu amount < balance nhưng không có lý do)

2. Đối tượng thanh toán ≠ đối tượng hóa đơn
   → Block, yêu cầu kiểm tra

3. Tài khoản NH thanh toán ≠ tài khoản NH của NCC
   → Cảnh báo, chờ xác nhận
```

## 5.4 Aging Calculation

```
Công thức:
Tuổi nợ = Ngày hiện tại - Ngày đến hạn (due_date)

Nếu KH thanh toán partial:
→ Aging tính trên số dư còn lại
→ Ngày đến hạn vẫn giữ nguyên (không thay đổi)

Phân loại:
0-30 ngày: Nợ trong hạn
31-60 ngày: Nợ quá hạn nhẹ
61-90 ngày: Nợ quá hạn trung bình
91-180 ngày: Nợ quá hạn nặng
> 180 ngày: Nợ khó đòi tiềm năng
> 365 ngày: Nợ khó đòi
```

## 5.5 Overdue Debt Identification

```
Cảnh báo theo cấp độ:
- Level 1 (sắp đến hạn): Trước hạn 7 ngày
- Level 2 (quá hạn nhẹ): 1-30 ngày
- Level 3 (quá hạn TB): 31-60 ngày
- Level 4 (quá hạn nặng): 61-90 ngày
- Level 5 (khó đòi): > 90 ngày

Hành động theo level:
Level 1: Email nhắc
Level 2: Gọi điện
Level 3: Công văn + ngừng giao hàng
Level 4: Luật sư
Level 5: Xóa nợ (nếu không thu được)
```

## 5.6 Payment/Collection Allocation Decision

```
IF reference_number IS NOT NULL
   → match_exact_invoice()
ELSE IF customer_note CONTAINS invoice_number
   → parse_and_match()
ELSE
   → auto_allocate_by_priority()

Priority:
1. Overdue invoices (oldest first)
2. Near-due invoices
3. Largest invoices
4. Unallocated balance → customer deposit (131 Có)
```

## 5.7 Locked Period Protection

```
IF transaction_date IN closed_period
   → BLOCK with message "Kỳ kế toán đã đóng"
   → Only Chief Accountant can override
   → Override requires: reason + audit log + approval
```

## 5.8 Fraud Risk Detection

```
Quy tắc phát hiện gian lận:

1. Thanh toán cho NCC mới (created < 30 days)
   → Flag: review required

2. Thanh toán vượt hạn mức không có approval
   → Block

3. Thay đổi tài khoản NH của NCC
   → Flag: verify by phone

4. Trùng hóa đơn với số khác nhau
   → Flag: potential duplicate

5. Thanh toán nhiều lần gần nhau (splitting)
   → Flag: avoid approval limit

6. Xóa nợ không có approval
   → Block
```

## 5.9 Reconciliation Mismatch Handling

```
IF gl_balance != subledger_balance
   → Step 1: Check unposted transactions
   → Step 2: Check period cut-off
   → Step 3: Check rounding difference
   → Step 4: List individual differences

IF difference < tolerance (10,000 VND)
   → Auto-adjust (rounding)

IF difference > tolerance
   → Create investigation ticket
   → Assign to accountant
   → Track until resolved
```

---

# 6. Data Flow and Workflow Logic

## 6.1 Procurement → AP Relationship

```
Procurement                   AP/Finance
-----------                   ----------
Tạo PO                       Kiểm tra ngân sách
Phê duyệt PO                 Ghi nhận commit (dự chi)
                             
Nhận hàng                    
Lập PNK                      Nhập kho (Inventory)
                             
Nhận hóa đơn NCC             
                             Ghi nhận công nợ (AP)
                             Lên lịch thanh toán
                             Xuất tiền
```

**Vấn đề thường gặp:**
- Procurement đặt hàng vượt budget → AP không có tiền thanh toán
- Nhận hàng không đúng PO → AP không match được 3-way
- Hóa đơn không khớp PO → AP phải đàm phán lại

## 6.2 Sales → AR Relationship

```
Sales                        AR/Finance
-----                        ----------
Tạo đơn hàng                 Kiểm tra hạn mức tín dụng
Phê duyệt đơn                Xác nhận đơn
                             
Giao hàng                    
Xuất kho                     
                             Xuất hóa đơn
                             Ghi nhận công nợ (AR)
                             
                             Theo dõi aging
                             Nhắc nợ
                             Thu tiền
```

**Vấn đề thường gặp:**
- Sales bán chịu cho khách không có hạn mức → AR không thu được
- Sales hứa chiết khấu không có approval → AR mất tiền
- KH khiếu nại chất lượng → Sales giải quyết, AR chờ

## 6.3 Warehouse → Finance Relationship

```
Warehouse                    Finance
---------                    -------
Phiếu nhập kho (PNK)         Ghi nhận tăng hàng tồn kho
Phiếu xuất kho (PXK)         Ghi nhận giảm hàng tồn kho
Kiểm kê                      Điều chỉnh tồn kho (nếu có)
                             
                             Kết chuyển giá vốn (cuối kỳ)
                             Tính giá xuất kho (FIFO/WA)
```

**Vấn đề thường gặp:**
- Kho xuất nhập chậm → finance không kịp ghi nhận cuối kỳ
- Lệch tồn kho vật lý vs sổ sách → finance phải điều chỉnh

## 6.4 Month-End Reconciliation Workflow

```
Ngày T-7: Gửi biên bản xác nhận công nợ cho KH/NCC
Ngày T-5: Thu hồi biên bản, xử lý chênh lệch
Ngày T-3: Đối chiếu GL vs sub-ledger
Ngày T-2: Trích lập dự phòng
Ngày T-1: Đánh giá lại ngoại tệ
Ngày T:   Khóa kỳ
```

---

# 7. User Journey

## 7.1 AP Accountant (Kế toán công nợ phải trả)

| Khía cạnh | Mô tả |
|---|---|
| **Daily work** | Nhận hóa đơn, 3-way match, ghi nhận công nợ, lên lịch thanh toán, đối chiếu NCC |
| **Pain point** | Hóa đơn sai thông tin, NCC gọi điện hỏi ngày thanh toán, thiếu chứng từ |
| **Validation** | Kiểm tra MST, hóa đơn hợp lệ, chữ ký, đối chiếu PO |
| **Approval** | Đề xuất thanh toán theo lịch, đệ trình kế toán trưởng |
| **Risk point** | Double payment, trả sai NCC, hóa đơn giả |
| **Escalation path** | Kế toán trưởng → Giám đốc tài chính |

## 7.2 AR Accountant (Kế toán công nợ phải thu)

| Khía cạnh | Mô tả |
|---|---|
| **Daily work** | Xuất hóa đơn, theo dõi aging, gọi điện nhắc nợ, phân bổ tiền thu |
| **Pain point** | KH không trả đúng hạn, KH chuyển tiền không ghi reference, phải đuổi nợ |
| **Validation** | Hạn mức tín dụng, aging, đối chiếu KH |
| **Approval** | Quyết định chiết khấu, gia hạn nợ |
| **Risk point** | Thu không kịp → thiếu dòng tiền, xóa nợ không đúng quy trình |
| **Escalation path** | Kế toán trưởng → Luật sư (nếu nợ khó đòi) |

## 7.3 Finance Controller (Kế toán tổng hợp)

| Khía cạnh | Mô tả |
|---|---|
| **Daily work** | Đối chiếu GL vs sub-ledger, review báo cáo công nợ, kiểm soát số dư |
| **Pain point** | Lệch số dư GL và sub-ledger, phải truy tìm nguyên nhân |
| **Validation** | Đảm bảo Dr = Cr, GL khớp sub-ledger |
| **Approval** | Duyệt điều chỉnh, duyệt trích lập dự phòng |
| **Risk point** | Lệch số dư → BCTC sai, kiểm toán fail |
| **Escalation path** | Kế toán trưởng → Ban giám đốc |

## 7.4 Chief Accountant (Kế toán trưởng)

| Khía cạnh | Mô tả |
|---|---|
| **Daily work** | Review tổng thể, ký duyệt thanh toán lớn, ký BCTC |
| **Pain point** | Áp lực kiểm toán, áp lực đúng hạn báo cáo |
| **Validation** | Tuân thủ TT 99/2025, đúng luật thuế |
| **Approval** | Duyệt thanh toán lớn, duyệt mở kỳ, duyệt điều chỉnh hồi tố |
| **Risk point** | Sai BC → bị phạt, kiểm toán từ chối |
| **Escalation path** | Ban giám đốc → Hội đồng quản trị |

## 7.5 Auditor (Kiểm toán viên)

| Khía cạnh | Mô tả |
|---|---|
| **Daily work** | Kiểm tra biên bản xác nhận công nợ, aging, dự phòng |
| **Pain point** | Không có biên bản xác nhận, không track được aging |
| **Validation** | Gửi thư xác nhận (confirmation) đến KH/NCC |
| **Risk point** | Nếu xác nhận không về → đưa ra ý kiến ngoại trừ |
| **Escalation path** | Partner kiểm toán |

---

# 8. SME Pain Analysis

## 8.1 Excel Chaos

**Vấn đề:**
- Theo dõi công nợ trên Excel
- Mỗi người một file, không đồng bộ
- Công thức sai → số liệu sai
- Không phân quyền → ai cũng sửa được

**Hậu quả:**
- Sai lệch số dư → tranh chấp với KH/NCC
- Mất dữ liệu → không đối chiếu được
- Kiểm toán từ chối

**Giải pháp:**
=> Hệ thống AP/AR phải thay thế Excel hoàn toàn
=> Real-time đồng bộ, không cho sửa trực tiếp

## 8.2 Duplicate Payment (Trả tiền 2 lần)

**Vấn đề:**
- NCC gửi 2 hóa đơn giống nhau
- Kế toán không nhớ đã trả
- Thanh toán 2 lần cho cùng 1 hóa đơn

**Hậu quả:**
- Mất tiền, khó đòi lại
- Mất uy tín với NCC
- Phải làm thủ tục đòi lại (mất thời gian)

**Giải pháp:**
=> Kiểm tra trùng hóa đơn tự động
=> Cảnh báo khi hóa đơn đã được thanh toán

## 8.3 Missing Invoice (Mất hóa đơn)

**Vấn đề:**
- Hóa đơn giấy thất lạc
- Hóa đơn điện tử không tải về kịp
- NCC gửi qua email nhưng vào spam

**Hậu quả:**
- Không kê khai thuế kịp → mất khấu trừ VAT
- Sai công nợ → thanh toán thiếu
- Bị phạt nếu không kê khai đúng hạn

**Giải pháp:**
=> Lưu trữ hóa đơn tập trung
=> Tự động import hóa đơn điện tử từ NCC
=> Cảnh báo khi thiếu hóa đơn

## 8.4 Wrong Debt Balance (Sai số dư công nợ)

**Vấn đề:**
- GL 131/331 không khớp sub-ledger
- Công nợ trên sổ sách ≠ công nợ thực tế
- KH/NCC có số dư khác

**Hậu quả:**
- Thanh toán sai số tiền
- Tranh chấp kéo dài
- BCTC sai

**Giải pháp:**
=> Đối chiếu GL vs sub-ledger hàng ngày
=> Biên bản xác nhận công nợ hàng tháng

## 8.5 Cross-Branch Mismatch (Lệch công nợ giữa chi nhánh)

**Vấn đề:**
- Công ty có nhiều chi nhánh
- KH nợ chi nhánh A nhưng trả cho chi nhánh B
- Nội bộ không đối chiếu kịp

**Hậu quả:**
- Sai số dư từng chi nhánh
- Khó tổng hợp BCTC toàn công ty

**Giải pháp:**
=> Hệ thống tập trung, tất cả chi nhánh dùng chung
=> Tài khoản 136/336 tự động đối chiếu

## 8.6 Late Reconciliation (Đối chiếu chậm)

**Vấn đề:**
- Cuối năm mới đối chiếu một lần
- Phát hiện sai sót cũ không sửa được
- KH/NCC không còn hoạt động

**Hậu quả:**
- Sai sót kéo dài → số dư sai tích lũy
- Kiểm toán không xác nhận được

**Giải pháp:**
=> Đối chiếu định kỳ (hàng tháng)
=> Tự động gửi biên bản xác nhận

## 8.7 Cash Leakage (Thất thoát tiền)

**Vấn đề:**
- Thu tiền mặt không ghi nhận kịp
- Chi tiền mặt không có chứng từ
- Thủ quỹ lợi dụng sơ hở

**Hậu quả:**
- Mất tiền, truy cứu trách nhiệm
- Sai số dư quỹ

**Giải pháp:**
=> Hạn chế tiền mặt
=> Mọi thu/chi phải qua tài khoản NH
=> Kiểm kê quỹ đột xuất

## 8.8 Fraud Risk (Rủi ro gian lận)

**Vấn đề:**
- Tạo NCC giả → thanh toán tiền vào tài khoản cá nhân
- Thông đồng với NCC → nâng giá, nhận hoa hồng
- Tạo hóa đơn giả → rút tiền

**Hậu quả:**
- Mất tiền nghiêm trọng
- Truy cứu hình sự
- Mất uy tín doanh nghiệp

**Giải pháp:**
=> Segregation of duties
=> Kiểm tra NCC mới
=> Duyệt NCC qua nhiều cấp
=> Đối chiếu NH hàng ngày

## 8.9 Finance vs Sales Mismatch

**Vấn đề:**
- Sales bán chịu không kiểm tra hạn mức
- Sales hứa chiết khấu không có approval
- Sales không báo cho finance khi KH khiếu nại

**Hậu quả:**
- Finance không thu được tiền
- KH chờ chiết khấu không được → khiếu nại

**Giải pháp:**
=> Hạn mức tín dụng tự động
=> Chiết khấu phải được approval
=> Sales phải cập nhật lịch sử KH

## 8.10 Finance vs Procurement Mismatch

**Vấn đề:**
- Procurement đặt hàng không theo budget
- Procurement không báo cho finance khi thay đổi đơn giá
- Procurement nhận hàng không đúng PO

**Hậu quả:**
- Finance không có tiền thanh toán
- 3-way match fail → kéo dài thời gian xử lý

**Giải pháp:**
=> PO phải trong budget
=> Mọi thay đổi PO phải có approval
=> Receipt phải khớp PO

---

## Tổng kết

AP/AR Engine cho SME Việt Nam cần giải quyết **5 bài toán cốt lõi**:

1. **Tính chính xác:** Không sai số dư, không double payment, không mất hóa đơn
2. **Tính kịp thời:** Đối chiếu hàng ngày, cảnh báo real-time, aging real-time
3. **Tính minh bạch:** Audit trail đầy đủ, ai làm gì khi nào, không xóa dữ liệu
4. **Tính kiểm soát:** Phê duyệt multi-level, segregation of duties, hạn mức tín dụng
5. **Tính tuân thủ:** Đúng TT 99/2025, đúng TT 48/2019, đúng luật thuế

> **"Công nợ mà sai thì BCTC sai. BCTC sai thì thuế sai. Thuế sai thì doanh nghiệp chết."**
> — Chief Accountant, 20,000 giờ kinh nghiệm SME Việt Nam
