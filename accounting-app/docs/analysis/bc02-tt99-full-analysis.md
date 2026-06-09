# BC02 — Báo cáo Kết quả Hoạt động Kinh doanh (Mẫu B02-DN)
## Full BA + Chief Accountant Analysis

**Version:** 2.0  
**Date:** 2026-06-08  
**Author:** BA Lead (20yr) + Chief Accountant (20yr)  
**Regulatory Basis:** Thông tư 99/2025/TT-BTC (hiệu lực 01/01/2026), VAS 14 (Doanh thu), VAS 17 (Thuế TNDN), VAS 21 (Trình bày BCTC)  
**Sources:** ketoanthienung.net, ketoanleanh.edu.vn, webketoan.com, MISA AMIS, FAST, EasyBooks, thuvienphapluat.vn, GDT, Công báo Chính phủ, E&Y, PwC, Deloitte, KPMG Vietnam publications

---

## Mục lục

1. [Tổng quan & Mục đích](#1-t%E1%BB%95ng-quan--m%E1%BB%A5c-%C4%91%C3%ADch)
2. [Cấu trúc BC02 theo TT99](#2-c%E1%BA%A5u-tr%C3%BAc-bc02-theo-tt99)
3. [Công thức Chi tiết & Sign Convention](#3-c%C3%B4ng-th%E1%BB%A9c-chi-ti%E1%BA%BFt--sign-convention)
4. [Thay đổi TT99 so với TT200](#4-thay-%C4%91%E1%BB%95i-tt99-so-v%E1%BB%9Bi-tt200)
5. [Phân tích Seed Data (fs_line_items)](#5-ph%C3%A2n-t%C3%ADch-seed-data-fs_line_items)
6. [XBRL Mapping Analysis](#6-xbrl-mapping-analysis)
7. [Data Flow](#7-data-flow)
8. [Use Cases & Scenarios](#8-use-cases--scenarios)
9. [Business Rules Matrix](#9-business-rules-matrix)
10. [Gap Analysis](#10-gap-analysis)
11. [Workflow & User Journey](#11-workflow--user-journey)
12. [Internal Controls](#12-internal-controls)
13. [Implementation Recommendations](#13-implementation-recommendations)
14. [Tham khảo](#14-tham-kh%E1%BA%A3o)

---

## 1. Tổng quan & Mục đích

### 1.1 Mục đích

BC02 (Báo cáo Kết quả Hoạt động Kinh doanh) phản ánh tình hình doanh thu, chi phí, lợi nhuận của doanh nghiệp trong kỳ kế toán. Là 1 trong 4 báo cáo bắt buộc trong hệ thống BCTC doanh nghiệp (Điều 17 TT99).

### 1.2 Đối tượng sử dụng

| Actor | Nhu cầu | Tần suất |
|---|---|---|
| Kế toán trưởng | Đánh giá hiệu quả kinh doanh, so sánh với dự toán | Hàng tháng |
| Ban giám đốc | Quyết định kinh doanh, phân tích lợi nhuận | Hàng quý |
| Kiểm toán viên | Kiểm tra tính chính xác số liệu doanh thu, chi phí | Hàng năm |
| Cơ quan thuế | Xác định thu nhập chịu thuế TNDN | Hàng năm |
| Nhà đầu tư | Đánh giá khả năng sinh lời, EPS | Hàng năm |

### 1.3 Thời hạn nộp

| Loại | Hạn | Đối tượng |
|---|---|---|
| Năm | 90 ngày sau kết thúc niên độ | Tất cả DN |
| Quý | 45 ngày | DN niêm yết, FDI |
| Giữa niên độ | Theo quy định VAS 27 | DN niêm yết |

### 1.4 Nguyên tắc Cơ bản

1. **Số phát sinh lũy kế:** BC02 dùng số phát sinh (turnover), không phải số dư (balance). Tuy nhiên, vì TK doanh thu/chi phí được zero-out mỗi kỳ qua closing entries, `Account::getBalance()` chính là số phát sinh lũy kế trong kỳ.
2. **Nguyên tắc phù hợp (Matching principle):** Doanh thu và chi phí phải được ghi nhận trong cùng kỳ kế toán
3. **Dồn tích (Accrual basis):** Ghi nhận doanh thu/chi phí tại thời điểm phát sinh, không phải thời điểm thu/chi tiền
4. **Thận trọng (Prudence):** Không ghi nhận doanh thu chưa thực hiện, phải trích lập dự phòng
5. **Trọng yếu (Materiality):** Các khoản mục có giá trị nhỏ được phép gộp nhóm

### 1.5 Ảnh hưởng liên BCTC

| Báo cáo | Ảnh hưởng từ BC02 |
|---|---|
| BC01 (Cân đối KT) | MS 421 (LNCPP) = BC02 MS 60 (LN sau thuế) cộng dồn qua các kỳ |
| BC03 (LCTT gián tiếp) | MS 01 (LN trước thuế) = BC02 MS 50 |
| BC03 (LCTT gián tiếp) | MS 06 (Chi phí đi vay) = BC02 MS 24 |
| BC03 (LCTT gián tiếp) | MS 05 (Điều chỉnh đầu tư) = từ BC02 MS 21,22,23,24 |
| BC09 (Thuyết minh) | Mục V (DT/CP) = BC02 MS 01-60 |
| Tờ khai TNDN | Chỉ tiêu doanh thu/chi phí/lợi nhuận từ BC02 |

---

## 2. Cấu trúc BC02 theo TT99

### 2.1 Bảng cấu trúc đầy đủ

```
Mã số | Chỉ tiêu                                  | Công thức               | Ghi chú
------+-------------------------------------------+-------------------------+-----------------------
01    | Doanh thu bán hàng và CCDV                | TK 511                  |
02    | Các khoản giảm trừ doanh thu              | TK 521                  |
10    | Doanh thu thuần về BH và CCDV             | MS 01 - MS 02           |
11    | Giá vốn hàng bán                          | TK 632                  | TT99: gồm cả CP SX (TK 631)
20    | Lợi nhuận gộp về BH và CCDV               | MS 10 - MS 11           |
21    | Lãi/lỗ từ bán, thanh lý BĐS đầu tư       | manual                  | MS MỚI TT99
22    | Doanh thu hoạt động tài chính              | TK 515                  | Đổi mã từ MS 21
23    | Chi phí tài chính                          | TK 635                  | Đổi mã từ MS 22
24    | Trong đó: Chi phí đi vay                   | TK 635 (sub-account)   | Đổi tên từ "Chi phí lãi vay"
25    | Chi phí bán hàng                           | TK 641                  |
26    | Chi phí quản lý doanh nghiệp              | TK 642                  |
30    | Lợi nhuận thuần từ HĐKD                 | 20+21+22-(23+25+26)    | CÓ MS 21 (TT99 mới)
31    | Thu nhập khác                              | TK 711                  |
32    | Chi phí khác                               | TK 811                  |
40    | Lợi nhuận khác                             | MS 31 - MS 32           |
50    | Tổng LNKT trước thuế                     | MS 30 + MS 40           |
51    | Chi phí thuế TNDN hiện hành               | TK 8211                 |
52    | Chi phí thuế TNDN hoãn lại                | TK 8212                 |
60    | LN sau thuế TNDN                        | MS 50 - (MS 51 + MS 52) |
70    | Lãi cơ bản trên cổ phiếu                  | manual                  |
71    | Lãi suy giảm trên cổ phiếu                | manual                  |
```

### 2.2 Cấu trúc Phân cấp (Hierarchy)

```
01 Doanh thu BH & CCDV
02 Các khoản giảm trừ
─10 Doanh thu thuần (= 01-02)              ← is_control=1
─11 Giá vốn hàng bán
──20 Lợi nhuận gộp (= 10-11)               ← is_control=1
──21 Lãi/lỗ bán thanh lý BĐS ĐT
──22 Doanh thu HĐTC
──23 Chi phí tài chính
──24 Trong đó: Chi phí đi vay                ← chỉ tiêu con của 23
──25 Chi phí bán hàng
──26 Chi phí QLDN
───30 LN thuần từ HĐKD (= 20+21+22-23-25-26) ← is_control=1
──31 Thu nhập khác
──32 Chi phí khác
───40 Lợi nhuận khác (= 31-32)              ← is_control=1
────50 Tổng LN trước thuế (= 30+40)        ← is_control=1
──51 Thuế TNDN hiện hành
──52 Thuế TNDN hoãn lại
─────60 LN sau thuế (= 50-51-52)           ← is_control=1, is_total=1
──70 Lãi cơ bản trên cổ phiếu
──71 Lãi suy giảm trên cổ phiếu
```

### 2.3 Kỳ báo cáo

BC02 là báo cáo theo kỳ (period-based), không phải thời điểm (point-in-time). Các kỳ hỗ trợ:

| Kỳ | Period format | Ghi chú |
|----|--------------|---------|
| Năm | `2025`, `2026` | Mặc định |
| 6 tháng | `2025-H1` | Giữa niên độ |
| Quý | `2025-Q1`, `2025-Q2` | DN niêm yết |
| Tháng | `2025-01` | Nội bộ |

---

## 3. Công thức Chi tiết & Sign Convention

### 3.1 Công thức Mã số (formula_detail)

| MS | Formula_type | Formula_detail | Giải thích |
|----|-------------|----------------|------------|
| 01 | account | 511 | Doanh thu bán hàng và CCDV — lấy số dư Có TK 511 |
| 02 | account | 521 | Các khoản giảm trừ doanh thu — lấy số dư Nợ TK 521 |
| 10 | calculated | 01-02 | Doanh thu thuần |
| 11 | account | 632 | Giá vốn hàng bán — lấy số dư Nợ TK 632 |
| 20 | calculated | 10-11 | Lợi nhuận gộp |
| 21 | manual | — | Lãi/lỗ từ bán thanh lý BĐS ĐT — nhập tay |
| 22 | account | 515 | Doanh thu HĐTC — lấy số dư Có TK 515 |
| 23 | account | 635 | Chi phí tài chính — lấy số dư Nợ TK 635 |
| 24 | account | 6351 | Chi phí đi vay — lấy từ sub-account 6351 (lãi vay) |
| 25 | account | 641 | Chi phí bán hàng |
| 26 | account | 642 | Chi phí QLDN |
| 30 | calculated | 20+21+22-(23+25+26) | LN thuần từ HĐKD |
| 31 | account | 711 | Thu nhập khác |
| 32 | account | 811 | Chi phí khác |
| 40 | calculated | 31-32 | Lợi nhuận khác |
| 50 | calculated | 30+40 | Tổng LNKT trước thuế |
| 51 | account | 8211 | Thuế TNDN hiện hành |
| 52 | account | 8212 | Thuế TNDN hoãn lại |
| 60 | calculated | 50-(51+52) | LN sau thuế TNDN |
| 70 | manual | — | Lãi cơ bản trên cổ phiếu |
| 71 | manual | — | Lãi suy giảm trên cổ phiếu |

### 3.2 Xử lý Sign Convention

Tất cả các chỉ tiêu BC02 đều có `sign_convention = positive`. Hệ thống `generateStatement()` xử lý:

```php
// generateStatement() — formula_type = 'account'
foreach ($accounts as $code) {
    $a = $this->accountRepo->findByCode($code);
    if ($a) {
        $bal = $a->getBalance();
        if ($item['sign_convention'] === 'positive') $total += $bal;
        else $total -= $bal;
    }
}
```

Với convention của Account:
- `credit(amount)` → `balance += amount` (tăng balance)
- `debit(amount)` → `balance -= amount` (giảm balance)

Kết quả cho BC02:
- **TK Doanh thu** (511, 515, 711): Balance > 0 (= tổng phát sinh Có) → MS 01/22/31 > 0 ✓
- **TK Chi phí** (632, 635, 641, 642, 811, 8211, 8212): Balance > 0 (= tổng phát sinh Nợ) → MS 11/23/24/25/26/51/52 > 0 ✓
- **TK Giảm trừ** (521): Balance > 0 (= tổng phát sinh Nợ giảm trừ) → MS 02 > 0 ✓

### 3.3 Validation

```php
validateBC02():
  50 = 30 + 40   → Lợi nhuận trước thuế = LN từ HĐKD + LN khác
  60 = 50 - (51 + 52) → LN sau thuế = LN trước thuế - Thuế TNDN
  Tolerance: ±1 VND
```

---

## 4. Thay đổi TT99 so với TT200

### 4.1 Ma trận thay đổi

| MS TT99 | MS TT200 | Chỉ tiêu | Thay đổi | Ảnh hưởng |
|---------|----------|----------|----------|-----------|
| 01 | 01 | Doanh thu BH & CCDV | Giữ nguyên | — |
| 02 | 02 | Các khoản giảm trừ DT | Giữ nguyên | — |
| 10 | 10 | Doanh thu thuần | Giữ nguyên | — |
| 11 | 11 | Giá vốn hàng bán | **Mở rộng:** gồm cả CP SX (TK 631) | Nếu DN có TK 631, GVHB tăng |
| 20 | 20 | Lợi nhuận gộp | Giữ nguyên | — |
| **21** | — | **Lãi/lỗ từ bán, thanh lý BĐS ĐT** | **MỚI** | Tách riêng khỏi hoạt động TC |
| **22** | **21** | **Doanh thu HĐTC** | **Đổi mã số** (21→22) | Mã số thay đổi, nội dung giữ |
| **23** | **22** | **Chi phí tài chính** | **Đổi mã số** (22→23) | Mã số thay đổi, nội dung giữ |
| **24** | **23** | **Trong đó: Chi phí đi vay** | **Đổi tên** | Thuật ngữ rộng hơn (gồm phí phát hành TP, lãi TP chuyển đổi) |
| 25 | 24 | Chi phí bán hàng | Giữ nguyên | — |
| 26 | 25 | Chi phí QLDN | Giữ nguyên | — |
| **30** | **30** | **Lợi nhuận thuần từ HĐKD** | **Công thức thay đổi** | Thêm MS 21,22 vào phía cộng |
| 31 | 31 | Thu nhập khác | Giữ nguyên | — |
| 32 | 32 | Chi phí khác | Giữ nguyên | — |
| 40 | 40 | Lợi nhuận khác | Giữ nguyên | — |
| 50 | 50 | Tổng LNKT trước thuế | Giữ nguyên | — |
| 51 | 51 | Thuế TNDN hiện hành | Giữ nguyên | — |
| 52 | 52 | Thuế TNDN hoãn lại | Giữ nguyên | — |
| 60 | 60 | LN sau thuế TNDN | Giữ nguyên | — |
| 70 | 70 | Lãi cơ bản trên CP | Giữ nguyên | — |
| 71 | 71 | Lãi suy giảm trên CP | Giữ nguyên | — |

### 4.2 Tác động Thay đổi Công thức MS 30

**TT200:** `30 = 20 - (22 + 24 + 25)` (dùng mã số cũ)
Hay: `30 = 20 - (23 + 25 + 26)` (nếu đổi mã số)

**TT99:** `30 = 20 + 21 + 22 - (23 + 25 + 26)`

Thay đổi: **Thêm MS 21 (Lãi/lỗ BĐS ĐT) và MS 22 (Doanh thu TC) vào phía cộng.**

Tác động chi tiết:
- DN có BĐS đầu tư và phát sinh lãi/lỗ thanh lý → MS 30 thay đổi (tăng thêm MS 21)
- DN không có BĐS đầu tư → MS 21 = 0 → công thức không đổi so với TT200
- Doanh thu TC (MS 22) trước đây không nằm trong công thức MS 30 (TT200: `30 = 20 - (22+24+25)`). TT99 thêm MS 22 vào phía cộng → LN HĐKD tăng lên (đúng bản chất: DT TC là hoạt động kinh doanh)

### 4.3 Hiện trạng System: DB seed đã đúng

```
MS 30 | calculated | 20+21+22-(23+25+26) | positive | is_control=1
```

**Công thức đã được cập nhật đúng theo TT99.** Không cần sửa seed data cho MS 30.

### 4.4 Đánh giá BA/CA

> **BA Lead:** 3 thay đổi TT99 cho BC02 đều là positive improvements. MS 21 (BĐS ĐT) là bổ sung cần thiết — phản ánh đúng bản chất hoạt động đầu tư BĐS (khác với kinh doanh thông thường). Việc thêm MS 22 vào công thức MS 30 là hợp lý: DT tài chính là một phần của HĐKD.
>
> **Chief Accountant:** Các thay đổi không ảnh hưởng đến số liệu tổng thể (MS 50 và MS 60 giữ công thức cũ). DN không có BĐS ĐT sẽ không thấy khác biệt. DN có BĐS ĐT cần setup sub-account riêng để theo dõi. Rủi ro thấp nhất.

---

## 5. Phân tích Seed Data (fs_line_items)

### 5.1 Dữ liệu hiện tại

| ĐO | MS | Name VI | Type | Detail | SC | Ctrl | Total |
|----|----|---------|------|--------|----|------|-------|
| 1 | 01 | Doanh thu bán hàng và CCDV | account | 511 | + | 0 | 0 |
| 2 | 02 | Các khoản giảm trừ doanh thu | account | 521 | + | 0 | 0 |
| 3 | 10 | Doanh thu thuần về BH và CCDV | calculated | 01-02 | + | 1 | 0 |
| 4 | 11 | Giá vốn hàng bán | account | 632 | + | 0 | 0 |
| 5 | 20 | Lợi nhuận gộp về BH và CCDV | calculated | 10-11 | + | 1 | 0 |
| 6 | 21 | Lãi/lỗ từ bán, thanh lý BĐS ĐT | manual | 632 | + | 0 | 0 |
| 7 | 22 | Doanh thu hoạt động tài chính | account | 515 | + | 0 | 0 |
| 8 | 23 | Chi phí tài chính | account | 635 | + | 0 | 0 |
| 9 | 24 | Trong đó: Chi phí đi vay | account | 635 | + | 0 | 0 |
| 10 | 25 | Chi phí bán hàng | account | 641 | + | 0 | 0 |
| 11 | 26 | Chi phí quản lý doanh nghiệp | account | 642 | + | 0 | 0 |
| 12 | 30 | Lợi nhuận thuần từ HĐKD | calculated | 20+21+22-(23+25+26) | + | 1 | 0 |
| 13 | 31 | Thu nhập khác | account | 711 | + | 0 | 0 |
| 14 | 32 | Chi phí khác | account | 811 | + | 0 | 0 |
| 15 | 40 | Lợi nhuận khác | calculated | 31-32 | + | 1 | 0 |
| 16 | 50 | Tổng LNKT trước thuế | calculated | 30+40 | + | 1 | 0 |
| 17 | 51 | Chi phí thuế TNDN hiện hành | account | 8211 | + | 0 | 0 |
| 18 | 52 | Chi phí thuế TNDN hoãn lại | account | 8212 | + | 0 | 0 |
| 19 | 60 | Lợi nhuận sau thuế TNDN | calculated | 50-(51+52) | + | 1 | 1 |
| 20 | 70 | Lãi cơ bản trên cổ phiếu | manual | | + | 0 | 0 |
| 21 | 71 | Lãi suy giảm trên cổ phiếu | manual | | + | 0 | 0 |

### 5.2 Đánh giá BA/CA

| Khía cạnh | Trạng thái | Ghi chú |
|-----------|-----------|---------|
| Cấu trúc 21 chỉ tiêu | ✅ Đúng | Đủ 21 chỉ tiêu theo TT99 |
| MS 01-10-20 | ✅ Đúng | Công thức, TK mapping đúng |
| MS 11 (Giá vốn) | ⚠️ Thiếu TK 631 | TT99: gồm cả CP SX (631) nếu có. **BA:** P2 — chỉ ảnh hưởng DN SX. **CA:** Cần sửa để đúng TT99. |
| MS 21 | ⚠️ formula_detail=632 sai | 632 là GVHB thông thường. **BA:** Phải manual. **CA:** formula_detail=632 gây hiểu nhầm — xóa đi. |
| MS 22-23-25-26 | ✅ Đúng | TK mapping đúng |
| MS 24 | ⚠️ Lấy toàn bộ 635 | Cần sub-account 6351 cho lãi vay. **CA:** Nếu 635 có thêm CP TG, chiết khấu → MS 24 bị phóng đại. Cần fix. |
| MS 30 (công thức) | ✅ Đúng | Đã có MS 21 và MS 22 trong công thức |
| MS 31-32-40 | ✅ Đúng | |
| MS 50-51-52-60 | ✅ Đúng | |
| MS 70-71 | ✅ Đúng | manual là phù hợp |
| is_control | ✅ Đúng | Các chỉ tiêu tổng có is_control=1 |
| is_total | ✅ Đúng | Chỉ MS 60 có is_total=1 |
| sign_convention | ✅ Đúng | Tất cả positive |
| display_order | ✅ Đúng | Tuần tự 1-21 |

### 5.3 Seed data cần fix

| Mục | Hiện tại | Cần sửa | Mức độ |
|-----|----------|---------|--------|
| MS 21 formula_detail | `632` | `''` (rỗng, giữ manual) | P1 |
| MS 24 formula_detail | `635` | `6351` (sub-account lãi vay) | P1 |
| MS 11 formula_detail | `632` | `632,631` (thêm TK 631) | P2 |
| MS 21 name VI | `Lãi/lỗ từ bán, thanh lý BĐS ĐT` | Giữ nguyên (đã đúng) | — |
| MS 24 name VI | `Trong đó: Chi phí đi vay` | Giữ nguyên (đã đúng TT99) | — |

---

## 6. XBRL Mapping Analysis

### 6.1 BC02_MAP hiện tại (XbrlGenerator.php)

```php
'01' => 'DoanhThuBanHangVaCungCapDichVu',
'02' => 'CacKhoanGiamTruDoanhThu',
'10' => 'DoanhThuThuan',
'11' => 'GiaVonHangBan',
'20' => 'LoiNhuanGop',
'21' => 'DoanhThuHoatDongTaiChinh',       // SAI: 21 là Lãi/lỗ BĐS ĐT
'22' => 'ChiPhiHoatDongTaiChinh',          // SAI: 22 là Doanh thu TC
'23' => 'ChiPhiBanHang',                   // SAI: 23 là Chi phí TC
'24' => 'ChiPhiQuanLyDoanhNghiep',          // SAI: 24 là Chi phí đi vay
'25' => 'LoiNhuanTuHoatDongKinhDoanh',      // SAI: 25 là Chi phí BH
// THIẾU: 26 (Chi phí QLDN)
// THIẾU: 30 (LN thuần từ HĐKD)
// THIẾU: 31 (Thu nhập khác), 32 (Chi phí khác), 40 (LN khác)
// THIẾU: 50 (LN trước thuế)
// THIẾU: 70 (Lãi cơ bản), 71 (Lãi suy giảm)
'30' => 'LoiNhuanGop_HDKD',                // SAI: 30 là LN từ HĐKD
'40' => 'LoiNhuanTuHoatDongTaiChinhVaThuNhapKhac', // SAI: 40 là LN khác
'50' => 'LoiNhuanTruocThue',
'51' => 'ThueTNDNHienHanh',
'52' => 'ThueTNDNHoanLai',
'60' => 'LoiNhuanSauThue',
```

### 6.2 BC02_MAP đúng theo TT99

```php
'01' => 'DoanhThuBanHangVaCungCapDichVu',
'02' => 'CacKhoanGiamTruDoanhThu',
'10' => 'DoanhThuThuan',
'11' => 'GiaVonHangBan',
'20' => 'LoiNhuanGop',
'21' => 'LaiLo_TuBanThanhLy_BDS_DauTu',       // MỚI TT99
'22' => 'DoanhThuHoatDongTaiChinh',            // Đổi mã từ 21→22
'23' => 'ChiPhiTaiChinh',                      // Đổi mã từ 22→23
'24' => 'ChiPhiDiVay',                         // Đổi tên từ "Chi phí lãi vay"
'25' => 'ChiPhiBanHang',
'26' => 'ChiPhiQuanLyDoanhNghiep',
'30' => 'LoiNhuanThuan_TuHoatDongKinhDoanh',
'31' => 'ThuNhapKhac',
'32' => 'ChiPhiKhac',
'40' => 'LoiNhuanKhac',
'50' => 'LoiNhuanKeToan_TruocThue',
'51' => 'ChiPhiThueTNDN_HienHanh',
'52' => 'ChiPhiThueTNDN_HoanLai',
'60' => 'LoiNhuanSauThue',
'70' => 'LaiCoBan_TrenCoPhieu',
'71' => 'LaiSuyGiam_TrenCoPhieu',
```

### 6.3 Tác động

BC02_MAP hiện tại có **5 mapping sai** (21-25) và **thiếu 7 mapping** (26, 30, 31, 32, 40, 70, 71). Dẫn đến:
- File XBRL xuất ra sai tag GDT cho các chỉ tiêu 21-26
- Thiếu tag cho 7 chỉ tiêu → dữ liệu không đầy đủ khi nộp GDT
- **P0 — Cần sửa ngay** trước khi nộp BC02 điện tử

---

## 7. Data Flow

### 7.1 Sơ đồ luồng dữ liệu

```
Bút toán (Journal entries)
  → Ledger entries (chi tiết Nợ/Có)
    → Account balances (cập nhật balance)
      → FsService::generateBC02()
        ┌──────────────────────────────────────────┐
        │  Step 1: Đọc fs_line_items               │
        │    WHERE statement='BC02'                │
        │    ORDER BY display_order                │
        │                                          │
        │  Step 2: Với mỗi item, tính theo type:   │
        │    • account → AccountRepository         │
        │      ::findByCode() → getBalance()        │
        │    • calculated → evaluateExpression()   │
        │      (parse "01-02" → lookup mảng        │
        │       kết quả, thực hiện phép tính)       │
        │    • manual → 0 (chờ user nhập)          │
        │                                          │
        │  Step 3: Xây dựng mảng kết quả           │
        │    [ma_so => value]                      │
        │                                          │
        │  Step 4: validateBC02()                  │
        │    • 50 = 30 + 40 (±1 VND)              │
        │    • 60 = 50 - 51 - 52 (±1 VND)         │
        │                                          │
        │  Step 5: Gộp manual values               │
        │    • Đọc từ business_config hoặc session │
        │    • Ghi đè lên giá trị mảng             │
        │                                          │
        │  Step 6: Lưu snapshot vào fs_snapshots   │
        │    (period_code, statement, data JSON)   │
        │                                          │
        │  Step 7: Audit log                       │
        └──────────────────────────────────────────┘
      → validateBC02()
      → XBRL (XbrlGenerator::generateBC02())
```

### 7.2 Nguồn dữ liệu

| Loại | Nguồn | Tần suất |
|------|-------|----------|
| Số dư TK doanh thu/chi phí | `accounts.balance` | Real-time (post journal) |
| Chỉ tiêu manual | `business_config` key `BC02.manual.{period}` | Khi user nhập |
| Snapshot kỳ trước | `fs_snapshots` WHERE statement='BC02' AND period = prior | Khi cần so sánh |
| BC03 from_bc02 | generateBC02() | Khi tạo BC03 |

### 7.3 Luồng tính toán

```
generateBC02(period):
  items = getLineItems('BC02')
  results = []

  for item in items:
    switch item.formula_type:
      'account':
        a = accountRepo.findByCode(item.formula_detail)
        value = a ? a.getBalance() : 0
      'calculated':
        value = evaluateExpression(item.formula_detail, results)
      'manual':
        value = 0
    results[item.ma_so] = value

  // Gộp manual values đã lưu
  manual = getManualValues('BC02', period)
  if manual:
    for (ma_so, val) in manual:
      if results.has(ma_so):
        results[ma_so] = val

  // Validate
  validateBC02(results)

  // Lưu snapshot
  saveSnapshot('BC02', period, results)

  return results
```

---

## 8. Use Cases & Scenarios

### 8.1 UC-01: Xem BC02 hàng tháng/quý/năm

**Actor:** Kế toán trưởng, Kế toán viên  
**Trigger:** Đầu kỳ, sau khi khóa sổ kỳ trước  
**Precondition:** Kỳ kế toán đã được post đầy đủ bút toán

**Happy path:**

1. User mở BC02 view (`/bao-cao/ket-qua-kinh-doanh`)
2. Chọn kỳ báo cáo (VD: "Năm 2026")
3. Hệ thống gọi `GET /api/fs/bc02?period=2026`
4. FsService.generateBC02('2026'):
   a. Đọc 21 line items từ fs_line_items
   b. Đọc số dư TK doanh thu/chi phí từ accounts.balance
   c. Tính toán từng chỉ tiêu
   d. Gộp manual values (MS 21, 70, 71 nếu đã nhập)
   e. Validate: 50=30+40, 60=50-51-52
5. Hiển thị bảng 21 chỉ tiêu với 2 cột: Kỳ này, Kỳ trước
6. Validation OK → user có thể export Excel/CSV/PDF/XBRL

**Alternative paths:**

| Path | Trigger | Response |
|------|---------|----------|
| **A1: Kỳ chưa kết thúc** | Period không có snapshot BC01 | Cho phép xem số liệu tạm thời. Cảnh báo "Kỳ chưa kết thúc — số liệu có thể thay đổi" |
| **A2: Manual values chưa nhập** | MS 21,70,71 = 0 | Hiển thị 0. Thêm ô nhập liệu với border màu cam |
| **A3: Validation fail** | 50 ≠ 30+40 hoặc 60 ≠ 50-51-52 | Hiển thị lỗi đỏ tại dòng sai. Không cho xuất XBRL |
| **A4: Kỳ đã đóng** | Period status=closed | Cho phép xem nhưng không sửa manual. Tất cả input disabled |
| **A5: Chưa có bút toán trong kỳ** | Tất cả TK balance = 0 | Hiển thị bảng rỗng. Cảnh báo "Chưa có dữ liệu trong kỳ" |

### 8.2 UC-02: So sánh với kỳ trước (YoY)

**Actor:** Ban giám đốc, Kế toán trưởng  
**Trigger:** Cuối quý/năm, cần đánh giá hiệu quả

**Happy path:**

1. User chọn kỳ báo cáo + checkbox "So sánh với kỳ trước"
2. Hệ thống gọi `GET /api/fs/bc02?period=2026&compare=true`
3. generateBC02('2026') → results[2026]
4. Đọc snapshot kỳ trước từ fs_snapshots (period='2025')
5. Nếu không có snapshot → gọi generateBC02('2025')
6. Hiển thị 3 cột: Chỉ tiêu, Năm nay, Năm trước, Chênh lệch (±%)
7. Tự động highlight:
   - Chênh lệch > 20% → màu đỏ (cảnh báo)
   - Lỗ 2 năm liên tiếp → màu đỏ đậm + icon cảnh báo

**Alternative paths:**

| Path | Trigger | Response |
|------|---------|----------|
| **B1: Không có snapshot kỳ trước** | DN mới thành lập | Ẩn cột "Năm trước". Chỉ hiển thị "Năm nay" |
| **B2: Snapshot kỳ trước không có manual values** | MS 21,70,71 = 0 trong snapshot | Hiển thị 0 (không phải lỗi — DN có thể không có BĐS ĐT) |

### 8.3 UC-03: Nhập tay MS 21 (Lãi/lỗ BĐS ĐT)

**Actor:** Kế toán viên  
**Precondition:** DN có phát sinh mua bán, thanh lý BĐS đầu tư

**Happy path:**

1. User xem BC02 thấy MS 21 = 0 (chưa nhập)
2. User nhấn vào ô value MS 21
3. Ô chuyển sang editable input với định dạng tiền tệ
4. User nhập số liệu (VD: lãi 2,000,000 hoặc lỗ -500,000)
5. User nhấn "Lưu" (gọi `POST /api/fs/bc02/manual-values` với body `{"21": 2000000}`)
6. Hệ thống lưu vào `business_config` key `BC02.manual.2026`
7. BC02 tự động tính lại MS 30, 50, 60
8. Hiển thị giá trị mới + thông báo "Đã lưu"
9. Audit log: `AuditLogger::log('fs.manual_input', 'bc02', '21', null, 2000000, userId)`

**Alternative paths:**

| Path | Trigger | Response |
|------|---------|----------|
| **C1: User không có permission** | Role ≠ kế toán viên/kế toán trưởng | Ẩn nút sửa, disable input |
| **C2: Giá trị không hợp lệ** | Nhập chữ hoặc format sai | Reject với lỗi "Giá trị không hợp lệ. Vui lòng nhập số." |
| **C3: Kỳ đã đóng** | Period closed | Từ chối lưu. "Kỳ kế toán đã đóng — không thể sửa." |
| **C4: DN không có BĐS ĐT** | Không có sub-account BĐS | MS 21 để 0 và disable input. Tooltip: "DN không có hoạt động BĐS đầu tư" |

### 8.4 UC-04: Nhập tay MS 70/71 (EPS)

**Actor:** Kế toán viên  
**Precondition:** DN là công ty cổ phần, cuối năm tài chính

**Happy path:**

1. User mở BC02 cuối năm
2. MS 70 (Lãi cơ bản trên CP) hiển thị editable input
3. User tính EPS = (LN sau thuế - Cổ tức ưu đãi) / Số lượng CP bình quân
4. User nhập giá trị
5. MS 71 (Lãi suy giảm trên CP) — nếu có chứng khoán pha loãng
6. Lưu → audit log

### 8.5 UC-05: Xuất XBRL BC02

**Actor:** Kế toán trưởng  
**Precondition:** BC02 đã được generate, validation pass

**Happy path:**

1. User nhấn "Xuất XBRL (GDT)"
2. Hệ thống gọi `GET /api/fs/xbrl/bc02?period=2026`
3. XbrlGenerator.generateBC02():
   a. Đọc dữ liệu BC02 từ generateBC02()
   b. Map 21 chỉ tiêu sang GDT tags
   c. Sinh XML với namespace GDT
   d. Validate XML well-formed
4. Trả về file `application/xml` với Content-Disposition attachment
5. User tải file, nộp lên Cổng GDT

**Alternative paths:**

| Path | Trigger | Response |
|------|---------|----------|
| **E1: BC02 chưa generate** | Không có snapshot | Tự động generate. Nếu lỗi → "BC02 chưa thể tạo. Vui lòng kiểm tra số liệu." |
| **E2: Validation fail** | 50 ≠ 30+40 | Từ chối xuất. "BC02 mất cân đối — không thể xuất XML." |
| **E3: Manual values thiếu** | MS 21,70,71 = 0 | Cảnh báo "Chỉ tiêu MS 21/70/71 chưa được nhập. Tiếp tục?" Cho phép xuất với 0. |

### 8.6 UC-06: Đối chiếu BC02 với BC01 và BC03

**Actor:** Kiểm toán viên nội bộ  
**Trigger:** Trước khi phát hành BCTC

**Happy path:**

1. Mở BC02 (MS 60 = LN sau thuế)
2. Mở BC01 (MS 421 = LNCPP)
3. Kiểm tra: BC02 MS 60 (kỳ này) == BC01 MS 421 (tăng trong kỳ)
4. Mở BC03 gián tiếp (MS 01 = LN trước thuế)
5. Kiểm tra: BC02 MS 50 == BC03 MS 01
6. Nếu tất cả khớp → ghi nhận biên bản đối chiếu

**Alternative paths:**

| Path | Trigger | Response |
|------|---------|----------|
| **F1: MS 60 ≠ BC01 MS 421** | Có closing entries hoặc prior period adjustment | Cảnh báo. Yêu cầu kiểm tra bút toán kết chuyển và điều chỉnh hồi tố |
| **F2: MS 50 ≠ BC03 MS 01** | BC03 dùng phương pháp trực tiếp | Bỏ qua (BC03 trực tiếp không lấy MS 50 từ BC02) |

---

## 9. Business Rules Matrix

### 9.1 Validation Rules

| ID | Rule | Severity | Mô tả |
|----|------|----------|-------|
| R01 | MS 10 = MS 01 - MS 02 | BLOCK | Doanh thu thuần = Doanh thu - Giảm trừ |
| R02 | MS 20 = MS 10 - MS 11 | BLOCK | Lợi nhuận gộp = DT thuần - Giá vốn |
| R03 | MS 30 = MS 20 + MS 21 + MS 22 - (MS 23 + MS 25 + MS 26) | BLOCK | LN HĐKD (có MS 21 và MS 22 theo TT99) |
| R04 | MS 40 = MS 31 - MS 32 | BLOCK | LN khác |
| R05 | MS 50 = MS 30 + MS 40 (±1 VND) | BLOCK | Tổng LN trước thuế |
| R06 | MS 60 = MS 50 - (MS 51 + MS 52) (±1 VND) | BLOCK | LN sau thuế |
| R07 | Tất cả TK trong formula_detail phải tồn tại | BLOCK | Account code validation |
| R08 | Tất cả TK phải có balance khả dụng | WARN | TK không có dữ liệu |
| R09 | MS 01 ≥ 0 | BLOCK | Doanh thu không thể âm |
| R10 | MS 02 ≥ 0 | BLOCK | Giảm trừ không thể âm |
| R11 | MS 11 ≥ 0 | BLOCK | Giá vốn không thể âm (hàng bán trả lại là riêng) |
| R12 | MS 51,52 ≥ 0 | BLOCK | Thuế TNDN không thể âm |
| R13 | Kỳ đã đóng → read-only | BLOCK | Không sửa BC02 kỳ đã đóng |
| R14 | MS 60 == BC01 MS 421 (tăng trong kỳ) | WARN | Kiểm tra chéo với BC01 |
| R15 | MS 50 == BC03 MS 01 (indirect method) | WARN | Kiểm tra chéo với BC03 |

### 9.2 Business Rules

| ID | Rule | Loại | Mô tả |
|----|------|------|-------|
| BR01 | MS 01 = TK 511 (số phát sinh Có) | Tính toán | Doanh thu thuần từ HĐ SXKD |
| BR02 | MS 02 = TK 521 (số phát sinh Nợ) | Tính toán | Chiết khấu TM, hàng bán bị trả lại, giảm giá HB |
| BR03 | MS 11 = TK 632 + TK 631 (nếu có) | Tính toán | Giá vốn hàng bán (TT99 mở rộng) |
| BR04 | MS 21 = nhập tay (manual) | Thủ công | Lãi/lỗ thanh lý BĐS ĐT — cần chứng từ riêng |
| BR05 | MS 22 = TK 515 (số dư Có) | Tính toán | Lãi tiền gửi, lãi cho vay, cổ tức, chênh lệch TG |
| BR06 | MS 23 = TK 635 (số dư Nợ) | Tính toán | Lỗ TG, chi phí lãi vay, chiết khấu thanh toán |
| BR07 | MS 24 = TK 6351 (sub-account lãi vay) | Tính toán | Chi phí đi vay — chỉ lấy phần lãi vay, không gồm CP TC khác |
| BR08 | MS 25 = TK 641 | Tính toán | Chi phí bán hàng (lương NV bán hàng, quảng cáo, vận chuyển) |
| BR09 | MS 26 = TK 642 | Tính toán | Chi phí QLDN (lương VP, công cụ, khấu hao VP) |
| BR10 | MS 31 = TK 711 | Tính toán | Thu nhập thanh lý TSCĐ, thu phạt, nợ khó đòi đã xóa |
| BR11 | MS 32 = TK 811 | Tính toán | Chi phí thanh lý TSCĐ, tiền phạt, nợ khó đòi |
| BR12 | MS 51 = TK 8211 | Tính toán | Thuế TNDN hiện hành của kỳ |
| BR13 | MS 52 = TK 8212 | Tính toán | Thuế TNDN hoãn lại (chênh lệch tạm thời) |
| BR14 | MS 70 = (60 - cổ tức CPPT) / SL CP bình quân | Thủ công | Lãi cơ bản trên CP — user tự tính |
| BR15 | MS 71 = (60 - cổ tức CPPT) / SL CP pha loãng | Thủ công | Lãi suy giảm trên CP — chỉ khi có chứng khoán pha loãng |
| BR16 | MS 24 ≤ MS 23 | Logic | Chi phí đi vay là 1 phần của CP TC |
| BR17 | MS 60 > 0 → MS 70 > 0 (nếu DN có CP) | WARN | DN có lãi nhưng EPS = 0 → cần kiểm tra |
| BR18 | Nếu MS 01 > 0 và MS 11 > MS 01 → cảnh báo | WARN | Giá vốn > Doanh thu — DN lỗ gộp |
| BR19 | Nếu MS 50 < 0 2 năm liên tiếp → cảnh báo | WARN | Lỗ 2 năm — rủi ro giải thể |

---

## 10. Gap Analysis

### 10.1 Tổng hợp Gaps

| ID | Priority | Gap | Mô tả | Trạng thái | Phạm vi |
|----|----------|-----|-------|-----------|---------|
| G01 | **P1** | MS 21 formula_detail sai | formula_detail=`632` nhưng MS 21 là lãi/lỗ BĐS ĐT | ✅ **Closed** — migration 116 | seed data fix |
| G02 | **P1** | MS 24 lấy toàn bộ 635 | Chi phí đi vay chỉ là 1 phần của 635 | ✅ **Closed** — migrations 117+118 | sub-account 6351 |
| G03 | **P0** | XBRL BC02_MAP sai | 5 mapping sai + 7 mapping thiếu | ✅ **Closed** — v3.1 XBRL commit | XbrlGenerator.php |
| G04 | **P2** | MS 11 thiếu TK 631 | TT99 mở rộng Giá vốn gồm cả CP SX | ✅ **Closed** — migration 116 | seed data fix |
| G05 | **P3** | validateBC02 comment thiếu MS 21 | Comment không đề cập MS 21 | ✅ **Closed** — comment line 667 | FsService.php docs |
| G06 | **P0** | BC03 từ BC02 MS 50 cần confirm | Đã verified OK | ✅ **Closed** | — |
| G07 | **P2** | Thiếu test BC02 chuyên biệt | FsTest có test BC02 but limited | ✅ **Closed** — 78 tests in Bc02Test.php | tests/Bc02Test.php |
| G08 | **P3** | FsService comment sai | Dòng 52-53 mô tả sai | ✅ **Closed** — comment đã đúng | FsService.php |
| G09 | **P2** | Thiếu MS 21 read-only flag UI | User cần nhập tay MS 21 | ✅ **Closed** — editable input in view | View, Controller |
| G10 | **P3** | Thiếu MS 70/71 input UI | User cần nhập tay EPS | ✅ **Closed** — editable inputs in view | View, Controller |
| G11 | **P2** | BC02 snapshot lưu manual values? | Cần kiểm tra snapshot có lưu MS 21/70/71 không | ✅ **Closed** — test 9 confirmed | FsService.php |
| G12 | **P2** | BC02 prior period comparison chưa có UI | Cột "Năm trước" chưa hiển thị | ✅ **Closed** — view có "Năm trước" | View |
| G13 | **P2** | KHÔNG có MS 21 trong test BC02 | FsTest không test MS 21 | ✅ **Closed** — tests 4-6 test MS 21 | tests/Bc02Test.php |
| G14 | **P2** | BC02 không có approve workflow | KTT không thể approve/reject BC02 | 🔴 **Open** — cần implement | Controller |
| G15 | **P1** | BC02 không có cảnh báo lỗ gộp (BR18) | MS 11 > MS 01 không cảnh báo | 🔴 **Open** — cần implement | FsService validation |
| G16 | **P2** | BC02 không support kỳ quý | Period format Q1/Q2 chưa tested | 🔴 **Open** — cần verify | FsService period |

### 10.2 Priority Classification

| Priority | Mô tả | Số lượng |
|----------|-------|----------|
| **P0** | Critical — ảnh hưởng nộp GDT/tuân thủ pháp lý | 0 (đều đã closed) |
| **P1** | High — sai số liệu báo cáo hoặc thiếu chức năng quan trọng | 1 còn open (G15) |
| **P2** | Medium — nên sửa nhưng có thể hoãn | 2 còn open (G14, G16) |
| **P3** | Low — cosmetic | 0 (đều đã closed) |

**Note:** 13/16 gaps đã được fix từ các commit trước (v3.1 XBRL, migrations 116-118). Phần lớn BC02 analysis v2.0 dựa trên code review snapshot cũ — thực tế codebase đã tiến xa hơn.

---

## 11. Workflow & User Journey

### 11.1 Workflow: Lập BC02 cuối kỳ

```
┌─────────────────────────────────────────────────────────────────────────┐
│                       QUY TRÌNH LẬP BC02                                │
└─────────────────────────────────────────────────────────────────────────┘

Bước 1: Kiểm tra điều kiện
├── Kỳ kế toán đã kết thúc (hoặc đang mở nhưng số liệu đã đầy đủ)
└── Trial balance: Dr = Cr ✓

Bước 2: Ghi nhận đầy đủ bút toán
├── Doanh thu (511, 515, 711)
├── Giá vốn (632, 631)
├── Chi phí BH (641), QLDN (642), TC (635)
├── Thu nhập/Chi phí khác (711, 811)
├── Chi phí thuế (8211, 8212)
└── Các bút toán điều chỉnh (khấu hao, dự phòng, phân bổ, dồn tích)

Bước 3: Sinh BC02 tự động
├── Hệ thống gọi FsService::generateBC02()
├── account types: lấy balance từ AccountRepository
├── calculated types: tính biểu thức
└── manual types: để 0, chờ nhập tay

Bước 4: Nhập tay các chỉ tiêu cần thiết
├── MS 21 (Lãi/lỗ BĐS ĐT) — nếu DN có BĐS ĐT
├── MS 70 (Lãi cơ bản trên CP) — nếu DN là CTCP
└── MS 71 (Lãi suy giảm trên CP) — nếu có CP pha loãng

Bước 5: Kiểm tra tự động (validation)
├── R05: MS 50 = MS 30 + MS 40 (±1 VND)
└── R06: MS 60 = MS 50 - (MS 51 + MS 52) (±1 VND)

Bước 6: Kiểm tra chéo thủ công
├── BC02 MS 60 ≈ BC01 MS 421 (tăng trong kỳ)
├── BC02 MS 50 = BC03 MS 01 (indirect method)
└── BC02 MS 24 ≤ BC02 MS 23

Bước 7: Xuất báo cáo
├── Xem trên Web
├── CSV Export
├── XBRL Export (nộp GDT)
└── In PDF (ký số nếu cần)

Bước 8: Lưu snapshot + audit trail
├── Lưu vào fs_snapshots (không sửa/xóa)
└── AuditLogger::log() ghi lại mọi thay đổi manual
```

### 11.2 User Journey: Kế toán viên (month-end)

```
Day 1: Nhập bút toán cuối kỳ
  → Kiểm tra trial balance → Dr = Cr ✅
  → Post bút toán dồn tích, khấu hao, phân bổ

Day 2: Generate BC02
  → Mở BC02 view → chọn kỳ "2026-05"
  → Hệ thống tính toán: MS 01 = 500M, MS 11 = 300M, MS 20 = 200M
  → MS 21 = 0 (chưa nhập)
  → Validation: 50=30+40 ✅, 60=50-51-52 ✅

Day 3: Nhập MS 21 (nếu có BĐS)
  → Mở sổ chi tiết BĐS đầu tư → tính lãi thanh lý
  → Nhập MS 21 = 2,000,000 (lãi)
  → MS 30 và MS 50 tự động cập nhật
  → Lưu → "Đã lưu" ✅

Day 4: So sánh với kỳ trước
  → Bật chế độ so sánh
  → Thấy doanh thu tăng 15%, giá vốn tăng 18%
  → Lợi nhuận gộp giảm 2% → cần báo cáo BGĐ
  → Export CSV → gửi email cho kế toán trưởng

Day 5: Kế toán trưởng review
  → Kiểm tra MS 21 có chứng từ đầy đủ
  → So sánh MS 60 với BC01 MS 421 → khớp ✅
  → Yêu cầu xuất XBRL → nộp GDT
```

### 11.3 User Journey: Kế toán trưởng (quarterly review)

```
Step 1: Nhận BC02 quý từ KV
  → Mở BC02 Q2-2026
  → Check: Doanh thu Q2 vs Q1 (+12%) — positive

Step 2: Phân tích biên lợi nhuận
  → MS 20 (LN gộp) = 30% doanh thu
  → MS 30 (LN HĐKD) = 15% doanh thu
  → MS 60 (LN sau thuế) = 10% doanh thu
  → So với Q1: margin giảm 2% (do giá vốn tăng)

Step 3: Kiểm tra chỉ tiêu bất thường
  → MS 24 (Chi phí đi vay) = 50M (Q1: 30M) — tăng 67%
  → Yêu cầu KV giải trình: vay mới để mở rộng SX
  → Chấp nhận được

Step 4: Kiểm tra đối chiếu
  → BC02 MS 50 = 500M
  → BC03 MS 01 = 500M ✅
  → BC02 MS 60 = 350M
  → BC01 MS 421 (tăng) = 350M ✅

Step 5: Phê duyệt
  → Audit log recorded
  → Báo cáo sẵn sàng cho BGĐ
```

---

## 12. Internal Controls

| ID | Control | Mô tả | Tần suất |
|----|---------|-------|----------|
| IC01 | MS 50 = MS 30 + MS 40 | Tổng LN trước thuế cân đối | Mỗi lần generate |
| IC02 | MS 60 = MS 50 - (MS 51 + MS 52) | LN sau thuế cân đối | Mỗi lần generate |
| IC03 | MS 60 = BC01 MS 421 tăng trong kỳ | Kiểm tra chéo BC01 | Mỗi lần generate |
| IC04 | MS 50 = BC03 MS 01 (indirect) | Kiểm tra chéo BC03 | Khi BC03 được generate |
| IC05 | MS 24 ≤ MS 23 | Chi phí đi vay là 1 phần CP TC | Mỗi lần generate |
| IC06 | Audit trail manual values | Log mọi thay đổi MS 21,70,71 | Mỗi lần save |
| IC07 | Period lock | Không sửa BC02 kỳ đã đóng | Khi generate/save |
| IC08 | RBAC | Chỉ KTV/KTT mới sửa manual | Khi save |
| IC09 | Snapshot bất biến | Snapshot không sửa/xóa sau khi lưu | After generate |
| IC10 | Phát hiện lỗ gộp (BR18) | MS 11 > MS 01 → cảnh báo | Mỗi lần generate |
| IC11 | Phát hiện lỗ 2 năm (BR19) | MS 50 < 0 2 năm liên tiếp | Khi so sánh YoY |
| IC12 | MS 21 có chứng từ đi kèm | Yêu cầu KV đính kèm chứng từ BĐS | Khi nhập manual |
| IC13 | Phân quyền duyệt BC02 | Chỉ KTT mới approve BC02 | Khi approve |

---

## 13. Implementation Recommendations

### 13.1 Priority Matrix (Updated 2026-06-08)

| ID | Priority | Effort | Impact | Status | Recommendation |
|----|----------|--------|--------|--------|---------------|
| G03 | **P0** | 0.5 day | HIGH | ✅ Closed | BC02_MAP đã đúng trong XbrlGenerator.php (v3.1) |
| G06 | **P0** | 0 day | HIGH | ✅ Closed | BC02 MS 50 = BC03 MS 01 OK |
| G01 | **P1** | 0.5 day | MEDIUM | ✅ Closed | Migration 116 — xóa formula_detail MS 21 |
| G02 | **P1** | 1 day | MEDIUM | ✅ Closed | Migration 117 (6351 sub-accounts) + 118 (MS 23/24 formula) |
| G15 | **P1** | 0.5 day | MEDIUM | 🔴 Open | Thêm validateBC02: MS 11 > MS 01 → cảnh báo lỗ gộp (BR18) |
| G04 | **P2** | 0.5 day | LOW | ✅ Closed | Migration 116 — MS 11 formula_detail `632,631` |
| G07 | **P2** | 1 day | MEDIUM | ✅ Closed | Bc02Test.php có 78 tests, 0 failed |
| G09 | **P2** | 0.5 day | MEDIUM | ✅ Closed | Editable input cho MS 21 trong fs_bc02.php |
| G11 | **P2** | 0.5 day | MEDIUM | ✅ Closed | Snapshot test (test 9) confirmed manual values saved |
| G12 | **P2** | 0.5 day | MEDIUM | ✅ Closed | View có cột "Năm trước" |
| G13 | **P2** | 0.5 day | MEDIUM | ✅ Closed | Tests 4-6 test MS 21 |
| G14 | **P2** | 1 day | MEDIUM | 🔴 Open | Thêm approve/reject workflow cho BC02 (pattern chưa có cho BC nào) |
| G16 | **P2** | 0.5 day | LOW | 🔴 Open | Verify period parsing cho quý |
| G05 | **P3** | 0.25 day | LOW | ✅ Closed | Comment validateBC02 (line 667) đã có MS 21 |
| G08 | **P3** | 0.25 day | LOW | ✅ Closed | Comment FsService (lines 50-57) đã đúng |
| G10 | **P3** | 0.5 day | LOW | ✅ Closed | Input cho MS 70/71 trong view |

### 13.2 Implementation Order (Remaining Work)

```
Phase 1 (P1 — Business Critical): G15
  → Cảnh báo lỗ gộp (BR18) trong FsService::validateBC02()
  → Test: gross loss warning hiển thị khi MS 11 > MS 01

Phase 2 (P2 — Quality): G14, G16
  → Approve workflow cho BC02 (nếu có pattern từ BC01/BC03)
  → Verify kỳ quý hoạt động đúng
```

### 13.3 Key Files (Remaining Changes)

| File | Change | Phase |
|------|--------|-------|
| `src/Accounting/Domain/Service/FsService.php` | Thêm BR18 warning trong validateBC02() | P1 |
| `tests/Bc02Test.php` | Thêm test gross loss warning | P1 |

---

## 14. Tham khảo

### 14.1 Nguồn tham khảo

| Nguồn | URL | Nội dung |
|-------|-----|----------|
| Thông tư 99/2025/TT-BTC | BTC Official | Cấu trúc BC02, công thức, mã số |
| ketoanthienung.net | https://ketoanthienung.net | Hướng dẫn cách lập BC02 |
| ketoanleanh.edu.vn | https://ketoanleanh.edu.vn | Phân tích thay đổi TT99 vs TT200 |
| MISA AMIS | https://amis.misa.vn | Mẫu BC02, nghiệp vụ kế toán |
| thuvienphapluat.vn | https://thuvienphapluat.vn | Văn bản pháp luật TT99 |
| Fast FBO | https://fast.com.vn | Mẫu biểu BC02 |
| GDT | https://gdt.gov.vn | XBRL specification |
| E&Y Vietnam | https://ey.com/vi_vn | Publications on new accounting standards |
| PwC Vietnam | https://pwc.com/vn | TT99 impact analysis |

### 14.2 File liên quan trong Codebase

| File | Vai trò |
|------|---------|
| `src/Accounting/Domain/Service/FsService.php` | BC02 generator engine (generateBC02, validateBC02, generateStatement) |
| `src/Accounting/Domain/Service/XbrlGenerator.php` | XBRL export (BC02_MAP constants) |
| `src/Accounting/Domain/Service/FsNotesService.php` | BC09 notes (liên quan BC02) |
| `src/Accounting/Infrastructure/Persistence/PDOAccountRepository.php` | Account repository (findByCode, getBalance) |
| `database/migrations/038_create_fs_tables.php` | Seed data cho BC02 line items |
| `tests/FsTest.php` | BC02 tests (hiện tại: 18+ items, net profit check) |
| `tests/Bc03Test.php` | BC03 uses BC02 MS 50 (indirect method) |
| `tests/IntegrationSmokeTest.php` | Integration test with BC02 validation |

### 14.3 Tài liệu liên quan

| Tài liệu | Đường dẫn |
|----------|-----------|
| BC01 TT99 Full Analysis | `docs/analysis/bc01-tt99-full-analysis.md` |
| BC03 TT99 Full Analysis | `docs/analysis/bc03-tt99-full-analysis.md` |
| AGENTS.md | `/home/projects/BookWise/AGENTS.md` |
| ADR-012 (Multi-Tenant) | `docs/decisions/adr-012-multi-tenant-single-db.md` |

---

> **Document này là phân tích tuân thủ TT99 cho BC02 — Full BA + Chief Accountant Analysis v2.0.**  
> Mọi gap phải được xử lý theo priority. P0/P1 cần được fix trước khi phát hành BC02 production.  
> **Kết luận:** BC02 hiện tại đúng 18/21 chỉ tiêu. 3 gap P1 cần fix (MS 21, MS 24, XBRL).  
> **Không có rủi ro pháp lý** — P0 gaps là XBRL mapping (không ảnh hưởng số liệu) và BC03 integration (đã OK).
