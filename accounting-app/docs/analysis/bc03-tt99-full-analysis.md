# BC03 — Báo cáo Lưu chuyển Tiền tệ (Mẫu B03-DN theo TT 99/2025/TT-BTC)
## Full BA + Chief Accountant Analysis

**Version:** 1.0  
**Date:** 2026-06-08  
**Author:** BA Lead (20yr) + Chief Accountant (20yr)  
**Regulatory Basis:** Thông tư 99/2025/TT-BTC (hiệu lực 01/01/2026), VAS 24 (BCLCTT), VAS 27 (BCTC giữa niên độ)  
**Sources:** ketoanthienung.net, ketoanleanh.edu.vn, webketoan.com, MISA AMIS, FAST, EasyBooks, thuvienphapluat.vn, GDT, Công báo Chính phủ, E&Y, PwC, Deloitte, KPMG Vietnam publications

---

## Mục lục

1. [Tổng quan & Mục đích](#1-t%E1%BB%95ng-quan--m%E1%BB%A5c-%C4%91%C3%ADch)
2. [Cấu trúc BC03 theo TT99](#2-c%E1%BA%A5u-tr%C3%BAc-bc03-theo-tt99)
3. [Thay đổi TT99 vs TT200](#3-thay-%C4%91%E1%BB%95i-tt99-vs-tt200)
4. [Phân tích Seed Data BC03](#4-ph%C3%A2n-t%C3%ADch-seed-data-bc03)
5. [XBRL Mapping Analysis](#5-xbrl-mapping-analysis)
6. [Data Flow](#6-data-flow)
7. [Use Cases & Scenarios](#7-use-cases--scenarios)
8. [Business Rules Matrix](#8-business-rules-matrix)
9. [Gap Analysis](#9-gap-analysis)
10. [Workflow & User Journey](#10-workflow--user-journey)
11. [Internal Controls](#11-internal-controls)
12. [Implementation Recommendations](#12-implementation-recommendations)

---

## 1. Tổng quan & Mục đích

### 1.1 Mục đích

BC03 phản ánh tình hình lưu chuyển tiền tệ trong kỳ: các luồng tiền thu vào, chi ra từ 3 hoạt động (kinh doanh, đầu tư, tài chính), số dư tiền & tương đương tiền cuối kỳ. Là 1 trong 4 báo cáo bắt buộc trong hệ thống BCTC doanh nghiệp (Điều 17 TT99).

### 1.2 Đối tượng sử dụng

- **Nội bộ:** Ban lãnh đạo đánh giá khả năng thanh khoản, dự báo dòng tiền
- **Bên ngoài:** Cơ quan thuế (GDT), ngân hàng, nhà đầu tư, kiểm toán
- **Nộp thuế:** File XML + PDF ký số qua Cổng TTĐT Tổng cục Thuế (GDT)

### 1.3 Thời hạn nộp

| Loại | Hạn | Đối tượng |
|------|-----|-----------|
| Năm | 90 ngày sau kết thúc niên độ | Tất cả DN |
| Quý | 45 ngày | DN niêm yết, FDI |
| Giữa niên độ | Theo quy định VAS 27 | DN niêm yết |

### 1.4 Nguyên tắc kế toán (VAS 24 + TT99 Điều 17)

1. Phân loại luồng tiền theo 3 hoạt động: KD, ĐT, TC
2. Trình bày riêng biệt các luồng tiền thu/chi (trừ trường hợp báo cáo thuần)
3. Giao dịch ngoại tệ: quy đổi theo tỷ giá tại thời điểm phát sinh
4. Giao dịch phi tiền tệ (mua TSCĐ bằng nợ, cho thuê tài chính,...): KHÔNG trình bày
5. Tiền & tương đương tiền: thời hạn thu hồi ≤ 3 tháng kể từ ngày mua
6. Báo cáo trên cơ sở thuần: thu/chi hộ khách hàng, giao dịch vòng quay nhanh ≤ 3 tháng
7. Đầu kỳ & cuối kỳ bắt buộc đối chiếu với BC01 MS 110

---

## 2. Cấu trúc BC03 theo TT99

### 2.1 Mẫu biểu

| Mẫu số | Áp dụng | Phạm vi |
|--------|---------|---------|
| B03-DN | BC năm (trực tiếp hoặc gián tiếp) | DN hoạt động liên tục |
| B03-DN | BC năm (DN không đáp ứng giả định HĐLT) | DN giải thể/phá sản |
| B03a-DN | Giữa niên độ dạng đầy đủ | DN niêm yết |
| B03b-DN | Giữa niên độ dạng tóm lược | DN niêm yết |

### 2.2 Cấu trúc chỉ tiêu — Phương pháp gián tiếp

**I. Lưu chuyển tiền từ hoạt động kinh doanh** (MS 01-20)

| MS | Tên chỉ tiêu | Loại | Công thức/TK |
|----|-------------|------|-------------|
| 01 | Lợi nhuận trước thuế | from_bc02 | BC02 MS 50 |
| 02 | Khấu hao TSCĐ và BĐSĐT | manual | Cộng vào LN trước thuế |
| 03 | Các khoản dự phòng | account_delta | TK 2291,2292,2293,2294,2295 |
| 04 | Lãi/lỗ chênh lệch tỷ giá | manual | Điều chỉnh LN trước thuế |
| 05 | Lãi/lỗ từ hoạt động đầu tư, TC | investment_adjust | (BC02: 22-(23-24)+21) |
| 06 | Chi phí đi vay | from_bc02_24 | BC02 MS 24 |
| 07 | Các khoản điều chỉnh khác | manual | Quỹ KHCN, quỹ bình ổn giá |
| **08** | **LN từ HĐKD trước thay đổi VLĐ** | **sum** | **01+02+03+04+05+06+07** |
| 09 | Tăng, giảm các khoản phải thu | delta_neg | TK 131,1362,1363,1368,1388,... |
| 10 | Tăng, giảm hàng tồn kho | delta_neg | TK 151,152,153,154,155,156,157,158 |
| 11 | Tăng, giảm các khoản phải trả | delta_pos | TK 331,333,334,335,336,337,338,344 |
| 12 | Tăng, giảm chi phí chờ phân bổ | delta_neg | TK 242 |
| 13 | Tăng, giảm chứng khoán kinh doanh | delta_neg | TK 121 |
| 14 | Chi phí đi vay đã trả | manual | TK 635,335 (lãi vay đã trả) |
| 15 | Thuế TNDN đã nộp | manual | TK 3334 |
| 16 | Tiền thu khác từ HĐKD | manual | TK 711,133,141,244,... |
| 17 | Tiền chi khác cho HĐKD | manual | TK 811,244,333,338,... |
| **20** | **LCTT thuần từ HĐKD** | **sum** | **08+09+10+11+12+13+14+15+16+17** |

**II. Lưu chuyển tiền từ hoạt động đầu tư** (MS 21-30)

| MS | Tên chỉ tiêu | Loại | Công thức/TK |
|----|-------------|------|-------------|
| 21 | Tiền chi mua sắm TSCĐ | delta_neg | TK 211,213,217,241 |
| 22 | Tiền thu từ thanh lý TSCĐ | manual | TK 711 (thanh lý) |
| 23 | Tiền chi cho vay | delta_neg | TK 1281,1282,1283,1288 |
| 24 | Tiền thu hồi cho vay | manual | TK 1281,1282,1283,1288 |
| 25 | Tiền chi đầu tư góp vốn | delta_neg | TK 221,222,2281 |
| 26 | Tiền thu hồi đầu tư | manual | TK 221,222,2281 |
| 27 | Tiền thu lãi cho vay, cổ tức | manual | TK 515,635 (lãi) |
| **30** | **LCTT thuần từ HĐĐT** | **sum** | **21+22+23+24+25+26+27** |

**III. Lưu chuyển tiền từ hoạt động tài chính** (MS 31-40)

| MS | Tên chỉ tiêu | Loại | Công thức/TK |
|----|-------------|------|-------------|
| 31 | Tiền thu từ phát hành CP | delta_pos | TK 4111 |
| 32 | Tiền trả lại vốn góp | delta_neg | TK 419 |
| 33 | Tiền thu từ đi vay | delta_pos | TK 3411,3431 |
| 34 | Tiền trả nợ gốc vay | delta_neg_only | TK 3411,3431 |
| 35 | Tiền trả nợ gốc thuê TC | manual | TK 3412 |
| 36 | Cổ tức, LN đã trả cho CSH | manual | TK 421 |
| **40** | **LCTT thuần từ HĐTC** | **sum** | **31+32+33+34+35+36** |

**Tổng hợp** (MS 50-70)

| MS | Tên chỉ tiêu | Loại | Công thức |
|----|-------------|------|----------|
| **50** | **LCTT thuần trong kỳ** | **calculated** | **20+30+40** |
| **60** | **Tiền & tương đương tiền đầu kỳ** | **cash_begin** | BC01 MS 110 kỳ trước |
| **61** | **Ảnh hưởng của thay đổi tỷ giá** | **manual** | Chênh lệch tỷ giá do đánh giá lại |
| **70** | **Tiền & tương đương tiền cuối kỳ** | **calculated** | **50+60+61** |

### 2.3 Cấu trúc chỉ tiêu — Phương pháp trực tiếp

**I. Lưu chuyển tiền từ hoạt động kinh doanh** (MS 01-10)

| MS | Tên chỉ tiêu | Loại | Phân loại |
|----|-------------|------|----------|
| 01 | Tiền thu từ bán hàng, CCDV | direct_receipt | Đối ứng TK 511,131,121 |
| 02 | Tiền chi trả cho NCC | direct_payment | Đối ứng TK 331,152,153,156 |
| 03 | Tiền chi trả cho NLĐ | direct_payment | Đối ứng TK 334,3383 |
| 04 | Chi phí đi vay đã trả | direct_payment | Đối ứng TK 635,335 |
| 05 | Thuế TNDN đã nộp | direct_payment | Đối ứng TK 3334 |
| 06 | Tiền thu khác từ HĐKD | direct_receipt_other | Misc receipts |
| 07 | Tiền chi khác cho HĐKD | direct_payment_other | Misc payments |
| **10** | **LCTT thuần từ HĐKD** | **sum** | **01..07** |

(MS 21-27 Đầu tư, 31-36 Tài chính, 50/60/70 Tổng hợp — tương tự gián tiếp)

---

## 3. Thay đổi TT99 vs TT200

### 3.1 Ma trận thay đổi

| MS | TT 200/2014 | TT 99/2025 | Loại thay đổi | Ảnh hưởng |
|----|------------|-----------|--------------|-----------|
| 04 | Tiền lãi vay đã trả | Chi phí đi vay đã trả | Tên chỉ tiêu | Thuật ngữ rộng hơn (gồm phí phát hành TP, lãi TP chuyển đổi) |
| 06 | Chi phí lãi vay | Chi phí đi vay | Tên chỉ tiêu | Đồng bộ với MS 04 |
| 12 | Tăng, giảm chi phí trả trước | Tăng, giảm chi phí chờ phân bổ | Tên chỉ tiêu | Thuật ngữ rộng hơn |
| — | — | Bắt buộc phương pháp trực tiếp cho DN lớn | Chính sách | DN > 10B doanh thu phải lập cả 2 phương pháp |
| 60 | Tiền đầu kỳ | Tiền và tương đương tiền đầu kỳ | Tên chỉ tiêu | Rõ ràng hơn |
| 61 | Ảnh hưởng của thay đổi tỷ giá | (giữ nguyên) | Không đổi | — |
| 70 | Tiền cuối kỳ | Tiền và tương đương tiền cuối kỳ | Tên chỉ tiêu | Rõ ràng hơn |

### 3.2 Ảnh hưởng đến BookWise

**Không breaking change.** Tất cả thay đổi chỉ là tên gọi. Công thức, cấu trúc, mã số giữ nguyên. Cập nhật label trong seed data và UI.

---

## 4. Phân tích Seed Data BC03

### 4.1 Đánh giá tổng thể

| Tiêu chí | Giá trị | Đánh giá |
|----------|---------|----------|
| Số chỉ tiêu gián tiếp | 37 | ✅ Đầy đủ (TT99 có 37 chỉ tiêu) |
| Số chỉ tiêu trực tiếp | 26 | ✅ Đầy đủ |
| Loại formula | 12 loại | ✅ Đa dạng, phù hợp |
| Chỉ tiêu manual (cần nhập tay) | 10/37 | ⚠️ Nhiều chỉ tiêu manual — cần BA review |
| Chỉ tiêu account_delta | 7 | ✅ Dùng delta cuối kỳ - đầu kỳ |
| Chỉ tiêu từ BC02 | 2 | ✅ from_bc02 (MS 50), from_bc02_24 (MS 24) |
| Kiểm tra chéo BC01 | ✅ MS 70 vs BC01 MS 110 | — |

### 4.2 Chi tiết các chỉ tiêu cần đánh giá

| MS | Tên | Loại hiện tại | Đánh giá BA/CA |
|----|-----|--------------|---------------|
| 02 | Khấu hao TSCĐ và BĐSĐT | manual | ⚠️ Nên tự động tính từ TK 214 (account_delta). Manual OK nếu DN có nhiều BĐSĐT cần tách. |
| 04 | Lãi/lỗ chênh lệch tỷ giá | manual | ⚠️ Nên tự động tính delta TK 515 (lãi TG) - 635 (lỗ TG). Manual OK nếu DN muốn kiểm soát. |
| 14 | Chi phí đi vay đã trả | manual | ⚠️ Nên dùng TK 6351 (lãi vay đã trả). Hiện tại đã có G02 fix (TK 635 sub-accounts). |
| 22 | Tiền thu từ thanh lý TSCĐ | manual | ✅ Đúng. Không thể tự động vì cần biết giá trị thu thực tế. |
| 24 | Tiền thu hồi cho vay | manual | ✅ Đúng. Phụ thuộc hợp đồng vay thực tế. |
| 26 | Tiền thu hồi đầu tư | manual | ✅ Đúng. Phụ thuộc giá bán thực tế. |
| 27 | Tiền thu lãi cho vay, cổ tức | manual | ⚠️ Có thể tự động từ TK 515. |
| 35 | Tiền trả nợ gốc thuê TC | manual | ✅ Đúng. Hợp đồng thuê tài chính cụ thể. |
| 36 | Cổ tức đã trả cho CSH | manual | ✅ Đúng. Phụ thuộc quyết định của HĐQT. |
| 61 | Ảnh hưởng thay đổi tỷ giá | manual | ⚠️ Có thể tự động từ chênh lệch tỷ giá cuối kỳ. |

### 4.3 Seed data cần fix

| Mục | Hiện tại | Cần sửa | Mức độ |
|-----|----------|---------|--------|
| MS 04 TK 635 → 6351 | `account_delta` 635 | `6351` (lãi vay sau G02 fix) | P1 — đã fix G02 |
| MS 04 lãi/lỗ TG | manual | Có thể thêm `account_delta` 515,6352,6358 | P2 |
| MS 02 khấu hao | manual | Có thể thêm `account_delta` 214 | P3 |
| XBRL map mở rộng | 7/37 items | Cần map 30+ items | P1 |

---

## 5. XBRL Mapping Analysis

### 5.1 Current XBRL Map (BC03)

```php
private const BC03_MAP = [
    '01' => 'LoiNhuanTruocThue_BC03',
    '02' => 'DieuChinhChoCacKhoan',
    '20' => 'Tien_DauKy',
    '30' => 'LuuChuyenTienThu_TuHDKD',
    '50' => 'LuuChuyenTienThu_TuHDDT',
    '60' => 'LuuChuyenTienThu_TuHDTC',
    '70' => 'Tien_CuoiKy',
];
```

### 5.2 Đánh giá

| Vấn đề | Chi tiết | Priority |
|--------|----------|----------|
| Chỉ có 7/37 items | 30 items bị skip → thiếu thông tin | P1 |
| MS 20 map sai | MS 20 (LCTT từ HĐKD) không có tag riêng | P1 |
| GDT tag tạm thời | Cần xác nhận GDT taxonomy chính thức | P2 |
| Thiếu MS 61 | Ảnh hưởng tỷ giá — có thể cần tag | P2 |
| Direct method không map | BC03D không có XBRL | P3 |

---

## 6. Data Flow

### 6.1 Sơ đồ luồng dữ liệu — Phương pháp gián tiếp

```
BC02 (KQKD)                              BC01 (CĐKT)
   │                                         │
   ├─ MS 50 ─────────────────────┐            │
   │                            │            │
   ├─ MS 22,23,24,21 ──┐        │            │
   │                   │        │            │
   ▼                   ▼        ▼            ▼
┌─────────────────────────────────────────────────┐
│                FsService::generateBC03()          │
│                                                   │
│  Step 1: Đọc fs_line_items WHERE statement='BC03'│
│  Step 2: Tính từng chỉ tiêu theo formula_type:    │
│    • from_bc02 → BC02 MS 50                       │
│    • from_bc02_24 → BC02 MS 24                    │
│    • account_delta → balance cuối - balance đầu   │
│    • investment_adjust → -(22-(23-24)+21)         │
│    • delta_neg → (cuối - đầu) nếu giảm            │
│    • delta_pos → (cuối - đầu) nếu tăng            │
│    • sum → cộng các chỉ tiêu con                  │
│    • calculated → biểu thức số học                │
│    • cash_begin → BC01 MS 110 từ snapshot kỳ trước│
│    • manual → user input                          │
│  Step 3: Tính MS 08 = 01+02+...+07                │
│  Step 4: Tính MS 20 = 08+09+...+17                │
│  Step 5: Tính MS 30 = 21+22+...+27                │
│  Step 6: Tính MS 40 = 31+32+...+36                │
│  Step 7: Tính MS 50 = 20+30+40                    │
│  Step 8: Lấy MS 60 từ snapshot BC01 kỳ trước      │
│  Step 9: Tính MS 70 = 50+60+61                    │
│  Step 10: Lưu snapshot → fs_snapshots             │
└─────────────────────────────────────────────────┘
   │
   ▼
┌─────────────────────────────────────────────────┐
│              Kiểm tra chéo                        │
│  • MS 70 == BC01 MS 110 (Tiền cuối kỳ)           │
│  • MS 70 = MS 50 + MS 60 + MS 61                 │
└─────────────────────────────────────────────────┘
```

### 6.2 Sơ đồ luồng dữ liệu — Phương pháp trực tiếp

```
Sổ phụ TK 111, 112 (Cash/Bank)
   │
   └─ Phân loại từng giao dịch theo đối ứng:
      │
      ├─ Đối ứng TK 511,131 → MS 01 (Thu bán hàng)
      ├─ Đối ứng TK 331,152,156 → MS 02 (Chi mua hàng)
      ├─ Đối ứng TK 334 → MS 03 (Chi lương)
      ├─ Đối ứng TK 635,335 → MS 04 (Chi lãi vay)
      ├─ Đối ứng TK 3334 → MS 05 (Nộp TNDN)
      ├─ Đối ứng TK 211,213,241 → MS 21 (Mua TSCĐ)
      ├─ Đối ứng TK 1281,1282,1283 → MS 23 (Cho vay)
      ├─ Đối ứng TK 221,222,2281 → MS 25 (Góp vốn)
      ├─ Đối ứng TK 3411,3431 (phát sinh Có) → MS 33 (Nhận vay)
      ├─ Đối ứng TK 3411,3431 (phát sinh Nợ) → MS 34 (Trả nợ)
      └─ Không đối ứng đặc biệt → MS 06/07 (Thu/chi khác)
```

### 6.3 Nguồn dữ liệu

| Loại | Nguồn | Tần suất |
|------|-------|----------|
| Số dư tài khoản | `accounts.balance` | Real-time (cập nhật khi post journal) |
| Số dư đầu kỳ (BC01) | `fs_snapshots` WHERE statement='BC01' AND period = prior | Khi đóng kỳ |
| BC02 MS 50 | `generateBC02()` | Khi tạo BC02 |
| BC02 MS 24 | `generateBC02()` | Khi tạo BC02 |
| Chỉ tiêu manual | `business_config` (key BC03.manual.{period}) | Theo kỳ |
| Ledger details | `ledger_entries` (direct method) | Khi tạo BC03 |

---

## 7. Use Cases & Scenarios

### 7.1 UC-01: Lập BC03 theo phương pháp gián tiếp

**Actor:** Kế toán viên  
**Precondition:** Kỳ kế toán đã được post đầy đủ bút toán, BC02 đã được generate

**Happy path:**

1. User mở BC03 view (`/bao-cao/luu-chuyen-tien-te`)
2. Chọn phương pháp "Gián tiếp", chọn kỳ "2026"
3. Hệ thống gọi `GET /api/fs/bc03?period=2026`
4. FsService.generateBC03('2026'):
   a. Đọc 37 line items từ fs_line_items
   b. Gọi generateBC02('2026') → lấy MS 50, MS 24
   c. Đọc số dư tài khoản từ accounts.balance
   d. Đọc BC01 snapshot kỳ trước → MS 60 (tiền đầu kỳ)
   e. Tính toán từng chỉ tiêu
   f. Validate: MS 70 = MS 50 + MS 60 + MS 61 (±1 VND)
5. Kiểm tra chéo: MS 70 = BC01 MS 110
6. Lưu snapshot vào fs_snapshots
7. Hiển thị báo cáo với 37 dòng, 3 cột: Mã số, Chỉ tiêu, Năm nay, Năm trước
8. Validation OK → user nhấn "Xuất XBRL" hoặc "Xuất CSV"

**Alternative paths:**

| Path | Trigger | Response |
|------|---------|----------|
| **A1: BC02 chưa được generate** | BC02 snapshot không tồn tại | Hệ thống tự động gọi generateBC02(). Nếu lỗi → báo lỗi "Vui lòng tạo BC02 trước" |
| **A2: BC01 kỳ trước không có** | Không tìm thấy snapshot | MS 60 = 0 (kỳ đầu tiên của hệ thống) |
| **A3: Có chỉ tiêu manual chưa nhập** | MS 02,04,14,15,16,17,22,24,26,27,35,36,61 = 0 | Hiển thị 0. User cần nhập thủ công. Thêm cảnh báo màu vàng. |
| **A4: Validation fail (MS 70 ≠ BC01 MS 110)** | Chênh lệch > 1 VND | Hiển thị lỗi đỏ. Không cho xuất XBRL. Gợi ý kiểm tra chỉ tiêu 61 (tỷ giá). |
| **A5: Kỳ đã đóng** | Kỳ kế toán status=closed | Cho phép xem nhưng không cho sửa manual. Cảnh báo "Kỳ đã đóng — dữ liệu chỉ để tham khảo". |
| **A6: Năm trước chưa có dữ liệu** | DN mới thành lập | Cột "Năm trước" để trống (không hiển thị 0) |

### 7.2 UC-02: Lập BC03 theo phương pháp trực tiếp

**Actor:** Kế toán viên  
**Precondition:** Kỳ kế toán đã được post, có giao dịch thu/chi tiền

**Happy path:**

1. User mở BC03 view, chọn "Trực tiếp"
2. Hệ thống gọi `GET /api/fs/bc03-direct?period=2026`
3. FsService.generateBC03Direct('2026'):
   a. Đọc 26 line items từ fs_line_items WHERE statement='BC03D'
   b. Đọc tất cả giao dịch từ ledger_entries có account_id = 111,112
   c. Phân loại từng giao dịch theo đối ứng (opponent mapping)
   d. Tổng hợp vào MS 01-07
   e. Kiểm tra tổng tiền thu/chi có khớp với chênh lệch TK 111+112 không
4. Kiểm tra: MS 70 (direct) ≈ MS 70 (indirect) — chênh lệch do rounding
5. Hiển thị kết quả

**Alternative paths:**

| Path | Trigger | Response |
|------|---------|----------|
| **B1: Sổ tiền không khớp với chênh lệch BC01** | Tổng giao dịch 111+112 ≠ delta BC01 MS 110 | Cảnh báo. Nguyên nhân: giao dịch tương đương tiền, hoặc kỳ trước chưa snapshot. |
| **B2: Giao dịch không phân loại được** | Đối ứng không khớp mapping | Gộp vào MS 06 (thu khác) hoặc MS 07 (chi khác) |
| **B3: BC01 snapshot không có** | Kỳ đầu tiên | MS 60 = 0 |

### 7.3 UC-03: Nhập chỉ tiêu manual

**Actor:** Kế toán viên / Kế toán trưởng

**Happy path:**

1. User xem BC03, thấy MS 02 = 0 (chưa nhập khấu hao)
2. User nhấn vào ô value của MS 02
3. Ô chuyển sang editable input (giống BC02 MS 21/70/71 pattern)
4. User nhập số liệu khấu hao (VD: 500,000,000)
5. User nhấn "Lưu" (gọi `POST /api/fs/bc03/manual-values` với body `{"02": 500000000}`)
6. Hệ thống lưu vào `business_config` (key `BC03.manual.2026`)
7. BC03 tự động tính lại với value mới
8. Hiển thị giá trị mới + cảnh báo "Đã lưu"

**Alternative paths:**

| Path | Trigger | Response |
|------|---------|----------|
| **C1: Không có quyền sửa** | User không có permission | Ẩn nút sửa / disable input |
| **C2: Giá trị không hợp lệ** | Nhập số âm cho MS 02 | Từ chối với lỗi validation |
| **C3: Kỳ đã đóng** | Period closed | Từ chối lưu. Hiển thị "Kỳ đã đóng — không thể sửa". |

### 7.4 UC-04: Kiểm tra và xuất XBRL BC03

**Actor:** Kế toán trưởng  
**Precondition:** BC03 đã được generate, validation pass

**Happy path:**

1. Kế toán trưởng kiểm tra BC03
2. Xác nhận MS 70 khớp với BC01 MS 110
3. Nhấn "Xuất XBRL (GDT)"
4. Hệ thống gọi `GET /api/fs/xbrl/bc03?period=2026`
5. XbrlGenerator.generateBC03():
   a. Đọc dữ liệu BC03 từ generateBC03()
   b. Map sang GDT tags (7 items hiện tại)
   c. Sinh XML với namespace GDT
   d. Validate XML well-formed
6. Trả về file `application/xml` với Content-Disposition attachment
7. User tải file, kiểm tra trên HTKK

**Alternative paths:**

| Path | Trigger | Response |
|------|---------|----------|
| **D1: BC03 mất cân đối** | MS 70 ≠ MS 50+60+61 | Từ chối xuất. "BC03 mất cân đối — không thể xuất XML." |
| **D2: MS 70 ≠ BC01 MS 110** | Chênh lệch > 1 VND | Cảnh báo nhưng vẫn cho xuất (kế toán trưởng quyết định) |
| **D3: Tag GDT chưa chính thức** | Taxonomy pending | Sử dụng URL placeholder (sẽ cập nhật sau) |

### 7.5 UC-05: Đối chiếu BC03 với BC01

**Actor:** Kiểm toán viên / Kế toán trưởng

**Happy path:**

1. Mở cả BC01 và BC03
2. So sánh:
   - BC01 MS 110 (Tiền & tương đương tiền) == BC03 MS 70
   - BC01 MS 110 kỳ trước == BC03 MS 60
3. Nếu không khớp → kiểm tra:
   - Giao dịch phi tiền tệ (mua TSCĐ bằng nợ)
   - Khoản tương đương tiền (đầu tư < 3 tháng)
   - Chênh lệch tỷ giá (MS 61)
4. Ghi nhận biên bản đối chiếu

### 7.6 UC-06: Kế toán trưởng duyệt BC03

**Actor:** Kế toán trưởng  
**Precondition:** Tất cả chỉ tiêu đã được nhập, validation pass

**Happy path:**

1. Kế toán trưởng review toàn bộ BC03
2. Kiểm tra các chỉ tiêu manual có chứng từ đầy đủ
3. Xác nhận chênh lệch tỷ giá (MS 61)
4. Nhấn "Phê duyệt BC03"
5. Hệ thống ghi audit log: `audit_logger.log('fs.approve', 'bc03', periodCode, null, ['status':'approved'], actor)`
6. BC03 được đánh dấu "Đã phê duyệt"
7. Khoá không cho sửa (trừ khi KTT huỷ duyệt)

---

## 8. Business Rules Matrix

### 8.1 Validation Rules

| ID | Rule | Severity | Mô tả |
|----|------|----------|-------|
| R01 | MS 70 = MS 50 + MS 60 + MS 61 (±1 VND) | BLOCK | Tổng thể BC03 phải cân đối |
| R02 | MS 70 == BC01 MS 110 (±1 VND) | WARN | Kiểm tra chéo với Bảng CĐKT |
| R03 | MS 60 == BC01 MS 110 kỳ trước | WARN | Tiền đầu kỳ phải khớp |
| R04 | MS 20 = tổng MS 08+09+10+11+12+13+14+15+16+17 | BLOCK | Công thức tổng HĐKD |
| R05 | MS 30 = tổng MS 21+22+23+24+25+26+27 | BLOCK | Công thức tổng HĐĐT |
| R06 | MS 40 = tổng MS 31+32+33+34+35+36 | BLOCK | Công thức tổng HĐTC |
| R07 | MS 50 = MS 20 + MS 30 + MS 40 | BLOCK | Tổng hợp lưu chuyển tiền thuần |
| R08 | MS 08 = MS 01+02+03+04+05+06+07 | BLOCK | LN trước thay đổi VLĐ |
| R09 | MS 01 > 0 and MS 20 < 0 → cảnh báo | WARN | DN lãi nhưng dòng tiền âm (cảnh báo thanh khoản) |
| R10 | MS 20 < 0 2 năm liên tiếp → cảnh báo | WARN | Dòng tiền HĐKD âm 2 năm — rủi ro thanh khoản |
| R11 | Kỳ đã đóng → read-only | BLOCK | Không sửa BC03 kỳ đã đóng |
| R12 | Chỉ tiêu manual phải ≥ 0 | BLOCK | Ngoại trừ MS 04 (lỗ tỷ giá), MS 05 (lỗ đầu tư) |

### 8.2 Business Rules

| ID | Rule | Loại | Mô tả |
|----|------|------|-------|
| BR01 | account_delta = balance(cuối) - balance(đầu kỳ) | Tính toán | Delta theo BC01 snapshot |
| BR02 | delta_neg = min(0, delta) | Tính toán | Chỉ lấy delta âm — thể hiện luồng tiền giảm |
| BR03 | delta_pos = max(0, delta) | Tính toán | Chỉ lấy delta dương — thể hiện luồng tiền tăng |
| BR04 | delta_neg_only = min(0, delta) (ghi trị tuyệt đối) | Tính toán | Chỉ lấy delta âm — MS 34 |
| BR05 | investment_adjust = -(22 - (23 - 24) + 21) từ BC02 | Tính toán | Loại lãi/lỗ đầu tư khỏi LN HĐKD |
| BR06 | from_bc02 = BC02 MS 50 | Liên BC | Lợi nhuận trước thuế từ BC02 |
| BR07 | from_bc02_24 = BC02 MS 24 | Liên BC | Chi phí đi vay từ BC02 (đã fix G02) |
| BR08 | Tiền thu từ thanh lý TSCĐ = thực tế thu (manual) | Thủ công | Không thể tự động |
| BR09 | LN trước thuế nếu lỗ → MS 01 ghi trong ngoặc | Hiển thị | Số âm = ghi (xxx) |
| BR10 | Kỳ đầu tiên thành lập → MS 60 = 0 | Đặc biệt | Không có snapshot kỳ trước |
| BR11 | DN lớn (>10B) bắt buộc lập cả 2 phương pháp | Chính sách | Theo TT99 Điều 17 |

---

## 9. Gap Analysis

| ID | Priority | Gap | Mô tả | Phạm vi |
|----|----------|-----|-------|---------|
| BC03-G01 | **P1** | XBRL BC03 map incomplete | Chỉ 7/37 items có GDT tag. Cần bổ sung 30 items. | XbrlGenerator.php |
| BC03-G02 | **P1** | Manual input UI chưa có | 10 chỉ tiêu manual (MS 02,04,14,15,16,17,22,24,26,27,35,36,61) không có UI nhập liệu | View, Controller, API |
| BC03-G03 | **P1** | Prior period snapshot có thể thiếu | Kỳ đầu hoặc snapshot bị xóa → MS 60 = 0, MS 09-11 delta sai | FsService fallback |
| BC03-G04 | **P2** | Không tự động tính MS 02 (khấu hao) | Hiện manual. Có thể dùng account_delta TK 214. | FsService + seed |
| BC03-G05 | **P2** | Không tự động tính MS 04 (lãi/lỗ TG) | Hiện manual. Có thể dùng account_delta TK 515,6352,6358. | FsService + seed |
| BC03-G06 | **P2** | Không có cảnh báo thanh khoản (R09, R10) | LN > 0 nhưng dòng tiền âm — không có cảnh báo. | FsService validation |
| BC03-G07 | **P2** | Không có phê duyệt BC03 workflow | KTT không thể approve/reject BC03 | Controller, ApprovalRoutingService |
| BC03-G08 | **P3** | Direct method XBRL không map | BC03D chưa có XBRL export | XbrlGenerator |
| BC03-G09 | **P3** | Không support kỳ quý (Q1, Q2...) | BC03 giữa niên độ có thể cần period dạng quý | FsService period parsing |
| BC03-G10 | **P3** | Không support kỳ tháng | BC03 giữa niên độ có thể cần period dạng tháng | FsService period parsing |
| BC03-G11 | **P3** | Không tự động loại trừ giao dịch phi tiền tệ | Mua TSCĐ bằng nợ, thuê TC — cần loại khỏi BC03 | Direct method classifier |

---

## 10. Workflow & User Journey

### 10.1 Workflow: Lập BC03 cuối kỳ

```
┌─────────────┐
│  Đóng kỳ kế  │  PeriodService.close()
│  toán        │
└──────┬──────┘
       │
       ▼
┌─────────────┐
│  Generate   │  FsService.generateBC02()
│  BC02       │
└──────┬──────┘
       │
       ▼
┌─────────────┐     ┌──────────────┐
│  Generate   │────▶│  Nhập manual │
│  BC03       │     │  MS 02,04,...│
│  (tự động)  │     └──────┬───────┘
└──────┬──────┘            │
       │                   │
       ▼                   ▼
┌─────────────┐     ┌──────────────┐
│  Kiểm tra   │     │  Kiểm tra    │
│  tự động    │     │  thủ công    │
│  (R01-R08)  │     │  (R09-R10)   │
└──────┬──────┘     └──────┬───────┘
       │                   │
       └────────┬──────────┘
                │
                ▼
       ┌────────────────┐
       │  Xuất XBRL     │
       │  (nếu OK)      │
       └────────────────┘
```

### 10.2 User Journey: Kế toán viên

```
Day 1: Mở BC03 → thấy MS 70 != MS 50+60+61 (đỏ)
  → Kiểm tra MS 61 (tỷ giá) chưa nhập
  → Vào sổ tỷ giá, tính chênh lệch
  → Nhập MS 61 = -15,000,000 (lỗ tỷ giá)
  → BC03 cân đối ✅

Day 2: Kiểm tra MS 02 (khấu hao) = 0 (chưa nhập)
  → Vào bảng tính khấu hao
  → Nhập MS 02 = 120,000,000
  → MS 08 và MS 20 tự động cập nhật

Day 3: Kiểm tra MS 14 (chi phí đi vay đã trả)
  → Vào sổ phụ TK 6351
  → Nhập MS 14 = -250,000,000
  → MS 20 tự động cập nhật

Day 4: Kiểm tra chéo BC03 vs BC01
  → MS 70 = 1,200,000,000
  → BC01 MS 110 = 1,200,000,000 ✅
  → Kế toán trưởng phê duyệt

Day 5: Xuất XBRL → nộp qua HTKK → done ✅
```

### 10.3 User Journey: Kế toán trưởng (review & phê duyệt)

```
Step 1: Nhận thông báo "BC03 kỳ 2026 đã được tạo"
Step 2: Mở BC03 → toggle qua lại Indirect/Direct
Step 3: So sánh MS 70 (indirect) vs MS 70 (direct)
  → Chênh lệch ≤ 1% → OK
Step 4: Kiểm tra MS 61 (tỷ giá)
  → Yêu cầu KV giải trình nếu > 5% MS 70
Step 5: Kiểm tra manual items có chứng từ
  → MS 22,24,26,27,35,36 cần có hợp đồng/biên bản
Step 6: Phê duyệt → audit trail ghi lại
Step 7: Yêu cầu KV xuất XBRL → nộp GDT
```

---

## 11. Internal Controls

| ID | Control | Mô tả | Tần suất |
|----|---------|-------|----------|
| IC01 | MS 70 = MS 50+60+61 | Tổng thể cân đối | Mỗi lần generate |
| IC02 | MS 70 = BC01 MS 110 | Kiểm tra chéo BC01 | Mỗi lần generate |
| IC03 | MS 60 = BC01 MS 110 kỳ trước | Tiền đầu kỳ | Mỗi lần generate |
| IC04 | MS 20 + MS 30 + MS 40 = MS 50 | Cộng 3 hoạt động | Mỗi lần generate |
| IC05 | Audit trail | Log mọi thay đổi manual | Mỗi lần save manual |
| IC06 | Period lock | Không sửa BC03 kỳ đã đóng | Khi save/generate |
| IC07 | RBAC | Chỉ KTT mới approve | Khi approve |
| IC08 | Snapshot bất biến | Snapshot không sửa/xóa | After generate |
| IC09 | Phát hiện bất thường | LN > 0, dòng tiền âm → cảnh báo | Mỗi lần generate |
| IC10 | Đối chiếu Direct vs Indirect | MS 70 cả 2 pp phải xấp xỉ nhau | Khi generate cả 2 |

---

## 12. Implementation Recommendations

### 12.1 Priority Matrix

| ID | Priority | Effort | Impact | Recommendation |
|----|----------|--------|--------|---------------|
| BC03-G01 | P1 | 2 days | HIGH | Mở rộng XBRL map lên 37 items. Cần trao đổi với GDT về taxonomy chính thức. |
| BC03-G02 | P1 | 1 day | HIGH | Thêm UI nhập manual (giống BC02 pattern). Cần support 10 chỉ tiêu. |
| BC03-G03 | P1 | 0.5 day | MEDIUM | Fallback nếu snapshot kỳ trước không tồn tại (đọc từ accounts.balance). |
| BC03-G04 | P2 | 0.5 day | MEDIUM | Thêm account_delta 214 để tự động tính khấu hao. |
| BC03-G05 | P2 | 0.5 day | MEDIUM | Thêm account_delta 515,6352,6358 cho lãi/lỗ TG. |
| BC03-G06 | P2 | 0.5 day | LOW | Thêm cảnh báo thanh khoản vào validateBC03(). |
| BC03-G07 | P2 | 1 day | MEDIUM | Thêm approve/reject workflow cho BC03. |
| BC03-G08 | P3 | 1 day | LOW | Mở rộng XBRL cho direct method. |
| BC03-G09 | P3 | 0.5 day | LOW | Thêm period parsing cho quý. |
| BC03-G10 | P3 | 0.5 day | LOW | Thêm period parsing cho tháng. |
| BC03-G11 | P3 | 1 day | LOW | Loại trừ giao dịch phi tiền tệ. |

### 12.2 Implementation Order

```
Phase 1 (P0 — Legal MUST): BC03-G01, BC03-G02, BC03-G03
  → XBRL + Manual UI + Snapshot fallback
  → Hoàn thành trước khi nộp thuế

Phase 2 (P1 — Business Critical): BC03-G04, BC03-G05, BC03-G06, BC03-G07
  → Auto calc + Warnings + Workflow

Phase 3 (P2 — Compliance): BC03-G08, BC03-G09, BC03-G10, BC03-G11
  → Full features
```

### 12.3 Key Files to Modify

| File | Change | Phase |
|------|--------|-------|
| `src/Accounting/Domain/Service/XbrlGenerator.php` | Mở rộng BC03_MAP 7→37 items | P1 |
| `src/Accounting/Interfaces/HTTP/Financial/FsController.php` | Thêm saveManualValues(), modify bc03() | P1 |
| `src/Accounting/Domain/Service/FsService.php` | Thêm getManualValues()/saveManualValues(), modify generateBC03() | P1 |
| `config/routes/api_financial.php` | Thêm POST /api/fs/bc03/manual-values | P1 |
| `public/views/fs_bc03.php` | Thêm editable inputs cho MS 02,04,14,... | P1 |
| `database/migrations/` | Cập nhật seed data nếu cần | P2 |
| `tests/Bc03Test.php` | Thêm test cho các thay đổi | P1-P3 |

---

## Phụ lục: So sánh chi tiết TT200 → TT99

| MS | TT 200/2014 | TT 99/2025 | Ghi chú |
|----|------------|-----------|---------|
| 04 | Tiền lãi vay đã trả | Chi phí đi vay đã trả | Thống nhất thuật ngữ |
| 06 | Chi phí lãi vay | Chi phí đi vay | Thống nhất thuật ngữ |
| 12 | Tăng, giảm chi phí trả trước | Tăng, giảm chi phí chờ phân bổ | Mở rộng phạm vi |
| 14 | Tiền lãi vay đã trả (gián tiếp) | Chi phí đi vay đã trả (gián tiếp) | Thống nhất thuật ngữ |
| — | — | Bắt buộc XBRL | Mới |
| — | — | Bắt buộc direct method | DN > 10B doanh thu |
