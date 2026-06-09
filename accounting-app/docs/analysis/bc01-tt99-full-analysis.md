# BC01 — Báo cáo Tình hình Tài chính theo TT 99/2025/TT-BTC
## Phân tích Nghiệp vụ — BA Lead + Kế toán trưởng

> **Phiên bản:** 1.0  
> **Ngày:** 2026-06-08  
> **Tham chiếu:** TT 99/2025/TT-BTC (hiệu lực 01/01/2026, thay thế TT 200/2014/TT-BTC)  
> **Nguồn:** ketoanthienung.net, ketoanleanh.edu.vn, webketoan.com, MISA, EasyBooks, Fast, thuvienphapluat.vn, einvoice.vn, BTC official  

---

## 1. Tổng quan quy định

### 1.1 Hiệu lực & áp dụng

| Yếu tố | Giá trị |
|---|---|
| Văn bản | TT 99/2025/TT-BTC ngày 27/10/2025 |
| Hiệu lực | 01/01/2026 |
| Áp dụng | Năm TC bắt đầu từ/sau 01/01/2026 |
| Thay thế | TT 200/2014/TT-BTC, TT 75/2015, TT 53/2016, TT 195/2012 |
| Ký | Bộ trưởng Nguyễn Đức Tâm |

### 1.2 Thay đổi chính so với TT 200

1. **Đổi tên:** Bảng CĐKT → Báo cáo Tình hình Tài chính (B01-DN)
2. **Thêm chỉ tiêu:** Mã 124 (DP đầu tư NH đến đáo hạn), 125 (Đầu tư NH khác), 126 (DP tổn thất đầu tư NH khác)
3. **Sửa mã số:** Nhiều mã số thay đổi (VD: 135→136, 136→137, 139→137…)
4. **Bỏ chỉ tiêu:** Phải thu về cho vay NH/DH, chỉ tiêu phải thu về CPH
5. **Đổi tên TK:** 112 (Tiền gửi NH → Tiền gửi không kỳ hạn), 155 (Thành phẩm → Sản phẩm)
6. **Bổ sung TK:** 215 (Tài sản sinh học), 1383 (Thuế TTĐB hàng NK), 82112 (Thuế tối thiểu toàn cầu)
7. **Bỏ TK:** 1562 (Chi phí thu mua), 611 (Mua hàng — PP KKDK)
8. **Hệ thống:** 71 TK cấp 1 (giảm 5 so với TT 200)
9. **B01-DNKLT:** Mẫu riêng cho DN không hoạt động liên tục
10. **Phân loại:** Trình bày theo thanh khoản giảm dần
11. **Ngoại tệ:** Cho phép chọn ngoại tệ làm đơn vị tiền tệ kế toán nếu đủ điều kiện
12. **Quy chế hạch toán:** DN phải ban hành khi sửa đổi TK/chỉ tiêu BCTC

### 1.3 Bộ BCTC đầy đủ (DN hoạt động liên tục)

| Mẫu | Tên | Mã số |
|---|---|---|
| B01-DN | Báo cáo Tình hình Tài chính | BC01 |
| B02-DN | Báo cáo KQ Hoạt động Kinh doanh | BC02 |
| B03-DN | Báo cáo Lưu chuyển Tiền tệ | BC03 |
| B09-DN | Thuyết minh BCTC | BC09 |

---

## 2. Cấu trúc BC01 — Báo cáo Tình hình Tài chính

### 2.1 Sơ đồ cấu trúc

```
TỔNG CỘNG TÀI SẢN (280)
├── TÀI SẢN NGẮN HẠN (100)
│   ├── Tiền và tương đương tiền (110)
│   │   ├── Tiền (111): 111+112+113 (trừ tiền hạn chế)
│   │   └── Tương đương tiền (112): 1281+1288 (gốc ≤3 tháng)
│   ├── Đầu tư tài chính NH (120)
│   │   ├── CK kinh doanh (121): 121
│   │   ├── DP giảm giá CK KD (122): 2291 (âm)
│   │   ├── Đầu tư NH đến đáo hạn (123): 1281,1282,1283,1288
│   │   ├── DP đầu tư NH đến đáo hạn (124): 2292 (âm) [MỚI]
│   │   ├── Đầu tư NH khác (125): 2281 [MỚI]
│   │   └── DP tổn thất đầu tư NH khác (126): 2292 (âm) [MỚI]
│   ├── Phải thu NH (130)
│   ├── Hàng tồn kho (140)
│   ├── TS sinh học NH (150) [MỚI]
│   └── TS NH khác (160)
│       ├── CP chờ PB NH (161): 242
│       ├── Thuế GTGT được khấu trừ (162): 133
│       ├── Thuế và khoản khác phải thu NN (163): 1383, 333 (dư Nợ)
│       ├── Giao dịch mua bán lại TPCP (164): 171
│       └── TS NH khác (165): 2288, tiền hạn chế SD
├── TÀI SẢN DÀI HẠN (200)
│   ├── Phải thu DH (210)
│   ├── TSCĐ (220)
│   ├── TS sinh học DH (230) [MỚI]
│   ├── BĐS đầu tư (240)
│   ├── TS dở dang DH (250)
│   ├── Đầu tư TC DH (260)
│   └── TS DH khác (270)

TỔNG CỘNG NGUỒN VỐN (440)
├── NỢ PHẢI TRẢ (300)
│   ├── Nợ NH (310)
│   │   ├── Phải trả NB NH (311): 331 (dư Có)
│   │   ├── Người mua trả tiền trước NH (312): 131 (dư Có)
│   │   ├── Phải trả cổ tức, LN (313): 332
│   │   ├── Thuế và các khoản phải nộp NN (314): 333 (dư Có)
│   │   ├── Phải trả NLĐ (315): 334 (dư Có)
│   │   └── ...
│   └── Nợ DH (330)
│       └── ...
└── VỐN CHỦ SỞ HỮU (400)
    └── ...
```

### 2.2 Mã số chỉ tiêu và nguồn dữ liệu

| Mã số | Tên chỉ tiêu | Loại | Công thức/nguồn | Ghi chú TT99 so với TT200 |
|---|---|---|---|---|
| 100 | TS NH | sum | 110+120+130+140+150+160 | **Không đổi** |
| 110 | Tiền và tương đương | sum | 111+112 | **Không đổi** |
| 111 | Tiền | account | 111+112+113 (trừ hạn chế) | **Đổi tên TK112** (Tiền gửi NH→Tiền gửi không kỳ hạn) |
| 112 | Tương đương tiền | account | 1281,1288 (gốc ≤3 tháng) | **Không đổi** |
| 120 | Đầu tư TC NH | sum | 121+122+123+124+125+126 | **Thêm 124,125,126** |
| 121 | CK kinh doanh | account | 121 | **Không đổi** |
| 122 | DP giảm giá CK KD | account | 2291 (âm) | **Không đổi** |
| 123 | Đầu tư NH đến đáo hạn | account | 1281,1282,1283,1288 | **Đổi tên + thêm "NH"** |
| 124 | DP đầu tư NH đến đáo hạn | account | 2292 (âm) | **MỚI** |
| 125 | Đầu tư NH khác | account | 2281 | **MỚI** |
| 126 | DP tổn thất đầu tư NH khác | account | 2292 (âm) | **MỚI** |
| 130 | Phải thu NH | sum | 131+132+133+134+135+136+137 | **Mã số thay đổi** |
| 131 | Phải thu KH NH | account | 131 (dư Nợ) | **Không đổi** |
| 132 | Trả trước NB NH | account | 331 (dư Nợ) | **Không đổi** |
| 133 | Phải thu nội bộ NH | account | 1362,1363,1368 | **Không đổi** |
| 134 | TS phát sinh từ HĐ/Thanh toán theo tiến độ HĐXD | account | 137/337 | **Sửa đổi** — phân biệt TS từ HĐ (137) và thanh toán theo tiến độ (337) |
| 135 | Phải thu NH khác | account | 1388,334,338,141,244 | **Mã số cũ 136→135** |
| 136 | DP phải thu NH khó đòi | account | 2293 (âm) | **Mã số cũ 137→136** |
| 137 | TS thiếu chờ xử lý | account | 1381 | **Mã số cũ 139→137** |
| 140 | Hàng tồn kho | sum | 141+142 | **Không đổi** |
| 150 | TS sinh học NH | sum | 151+152+153 | **MỚI** (tách từ TS dài hạn cũ) |
| 160 | TS NH khác | sum | 161+162+163+164+165 | **Không đổi** |
| 161 | CP chờ PB NH | account | 242 | **Đổi tên** (CP trả trước→CP chờ PB) |
| 162 | Thuế GTGT được khấu trừ | account | 133 | **Không đổi** |
| 163 | Thuế và khoản khác phải thu NN | account | 1383, 333 (dư Nợ) | **Thêm 1383** (thuế TTĐB hàng NK) |
| 164 | Giao dịch mua bán lại TPCP | account | 171 | **Không đổi** |
| 165 | TS NH khác | account | 2288, tiền hạn chế SD | **Sửa** bao gồm tiền hạn chế sử dụng |
| 200 | TS DH | sum | 210+220+230+240+250+260+270 | **Thêm 230** (TS sinh học DH) |
| 280 | TỔNG TÀI SẢN | sum | 100+200 | **Không đổi** |

Nguồn vốn tương tự (300—440). Thay đổi chính:
- **Bỏ** chỉ tiêu "Phải thu về cho vay NH/DH"
- **Sửa mã số** nhiều chỉ tiêu phải thu

---

## 3. Use Cases

### UC-01: Lập BC01 cuối kỳ

**Mô tả:** Kế toán tổng hợp lập BC01 tại thời điểm cuối kỳ kế toán (tháng/quý/năm).

**Điều kiện tiên quyết:**
- Kỳ kế toán chưa đóng (hoặc đã đóng nhưng đang mở lại để điều chỉnh)
- Tất cả bút toán trong kỳ đã được post
- Đã kết chuyển doanh thu/chi phí (nếu cuối năm)
- Trial balance Dr = Cr

**Happy path:**
1. Kế toán chọn kỳ báo cáo (năm 2026, quý 1/2026)
2. Hệ thống đọc số dư từ `accounts.balance` cho từng tài khoản
3. Hệ thống xác định số dư Nợ/Có theo công thức từng chỉ tiêu
4. Hệ thống tính toán các chỉ tiêu tổng hợp (sum)
5. Hệ thống kiểm tra cân đối: Tổng TS = Tổng NV
6. BC01 hiển thị gồm 2 cột: Số cuối kỳ, Số đầu năm
7. Kế toán kiểm tra, xuất BC01 (màn hình / PDF / Excel / XBRL)

**Alternative paths:**
- **A1: BC mất cân đối** → Hệ thống báo lỗi chi tiết chênh lệch, không cho xuất
- **A2: Không có số liệu chỉ tiêu** → Ẩn chỉ tiêu (theo TT99 cho phép), đánh lại số thứ tự
- **A3: Kỳ đã đóng** → BC01 vẫn xem được (read-only), không cho sửa
- **A4: Năm đầu tiên áp dụng TT99** → Cột đầu năm lấy từ BC01 theo TT200 (cần mapping)

**Business rules:**
- Mã số 111: Loại trừ tiền hạn chế sử dụng → chuyển sang 165
- Mã số 112: Chỉ bao gồm đầu tư gốc ≤3 tháng, không rủi ro
- Mã số 134: Phân biệt TS từ HĐ (137) và thanh toán theo tiến độ (337)
- Mã số 142: Loại trừ DP cho CP SXKD dở dang DH và vật tư DH
- Mã số 163: Chỉ lấy dư Nợ của 333 (nộp thừa thuế), KHÔNG lấy dư Có
- Mã số 314: Chỉ lấy dư Có của 333 (còn phải nộp)
- Tất cả chỉ tiêu dự phòng: ghi bằng số âm (ngoặc đơn)

---

### UC-02: Xuất BC01 theo định dạng XBRL (GDT)

**Mô tả:** Doanh nghiệp nộp BC01 điện tử qua cổng thuedientu.gdt.gov.vn.

**Happy path:**
1. Kế toán chọn "Xuất XBRL"
2. Hệ thống sinh XML theo schema GDT
3. Tự động gắn namespace `http://www.gdt.gov.vn/2025/btc`
4. File XML tải xuống, kế toán nộp qua cổng thuế
5. Ký số, nộp tờ khai

---

### UC-03: Tổng hợp BC01 giữa trụ sở chính và đơn vị trực thuộc

**Mô tả:** DN có nhiều đơn vị trực thuộc cần tổng hợp BC01.

**Business rules:**
- Loại trừ giao dịch nội bộ (136/336)
- Kỹ thuật tương tự hợp nhất BCTC
- Cột đầu năm: số dư đầu kỳ của kỳ kế toán mới

---

### UC-04: Chuyển đổi số dư từ TT200 sang TT99

**Mô tả:** Đầu năm 2026, DN chuyển từ TT200 sang TT99.

**Các bước:**
1. Rà soát danh mục TK cũ và mới
2. Mapping TK bị bỏ (1562, 611) sang TK mới
3. Cập nhật phần mềm với hệ thống TK TT99
4. Kiểm tra số dư đầu kỳ 2026 khớp với cuối kỳ 2025
5. Ban hành Quy chế hạch toán kế toán (yêu cầu mới của TT99)
6. Đào tạo nhân sự

---

## 4. Data Flow

```
NGUỒN DỮ LIỆU
    │
    ├── accounts.balance (số dư từng TK)
    ├── fs_line_items (định nghĩa chỉ tiêu)
    │       ├── formula_type: account/sum/manual
    │       ├── formula_detail: danh sách TK / sum mã
    │       └── sign_convention: positive/negative
    │
    ▼
XỬ LÝ CHÍNH (FsService)
    │
    ├── Bước 1: Đọc tất cả chỉ tiêu BC01 từ fs_line_items
    ├── Bước 2: Với mỗi chỉ tiêu loại "account":
    │   ├── Đọc accounts.balance cho từng TK trong formula_detail
    │   └── Áp dụng sign_convention (±)
    ├── Bước 3: Với mỗi chỉ tiêu loại "sum":
    │   └── Tổng hợp từ các chỉ tiêu con
    ├── Bước 4: Với mỗi chỉ tiêu loại "calculated":
    │   └── Thực hiện phép tính trên công thức
    ├── Bước 5: Kiểm tra 280 = 440 (±10 tolerance)
    └── Bước 6: Trả về JSON / XML / mảng dữ liệu
    │
    ▼
ĐẦU RA
    ├── Màn hình (dashboard BC01)
    ├── PDF (in ấn)
    ├── Excel (CSV export)
    └── XBRL (XML nộp thuế)
```

### 4.1 Chi tiết xử lý chỉ tiêu 163 (quan trọng)

**Đúng theo TT99:** `1383, 333 (dư Nợ)`

- `1383` = Thuế TTĐB của hàng NK (dư Nợ) → TK mới trong TT99
- `333` = Thuế và các khoản phải nộp NN → chỉ lấy **dư Nợ** (nộp thừa)
- KHÔNG lấy dư Có của 333 → đó thuộc về chỉ tiêu 314 (Nợ phải trả)

**Lỗi trong seed data cũ:**
- Line 163 có `1383,333` dùng `account` type → cả dư Nợ và dư Có đều lấy
- → 3334 (dư Có 2.8M) bị đưa vào TS → sai
- Fix: đã migrate line 163 về `1383` (chỉ giữ thuế TTĐB hàng NK)
- Tuy nhiên, cần xem xét: nếu 333 có dư Nợ (nộp thừa) thì vẫn phải vào 163

**Giải pháp chính xác:**
- formula_type nên là `account_balance` với hướng: `debit_balance_only`
- Hoặc: formula_detail vẫn `1383,333` nhưng xử lý đặc biệt: chỉ lấy dư Nợ
- Hiện tại: để `1383` (thiếu trường hợp nộp thừa thuế)

### 4.2 Chi tiết xử lý chỉ tiêu 314

**Đúng theo TT99:** `333 (dư Có)` — các khoản thuế còn phải nộp NN

- Hiện tại DB: `33311,33312,3332,3333,3334,3335,3336,3337,33381,33382,3339` (liệt kê hết sub-accounts)
- Vấn đề: Nếu 3338 (thuế BVMT) dư Nợ → sai. Chỉ lấy dư Có.
- Giải pháp: Dùng `account` type với control account 333, nhưng chỉ lấy dư Có
- Hoặc: Giữ nguyên liệt kê sub-accounts hiện tại (dễ hiểu, dễ kiểm soát)

---

## 5. Business Rules Matrix

| ID | Rule | Severity | Source | Impl |
|---|---|---|---|---|
| R01 | TS = Nợ PV + VCSH | REQUIRED | VAS 21 | Kiểm tra ±10đ |
| R02 | Kỳ đóng = read-only | REQUIRED | TT99 Đ20 | Period lock |
| R03 | Không post vào control account | REQUIRED | TT99 | Posting rules |
| R04 | Chỉ tiêu không số liệu → ẩn | RECOMMENDED | TT99 Đ20 | UI filter |
| R05 | Năm đầu TT99 → cột đầu năm từ TT200 | REQUIRED | TT99 Đ31 | Mapping |
| R06 | Ẩn/tổng chỉ lấy dư Nợ của TK | REQUIRED | TT99 PL IV | formula_type |
| R07 | 314 chỉ lấy dư Có của 333 | REQUIRED | TT99 PL IV | formula_type |
| R08 | Tiền hạn chế SD → 165/274 | REQUIRED | TT99 PL IV | Distinct logic |
| R09 | Đầu tư gốc ≤3 tháng → 112 | REQUIRED | TT99 PL IV | Date calc |
| R10 | DP ghi âm (ngoặc đơn) | REQUIRED | TT99 PL IV | sign_convention |
| R11 | Loại trừ giao dịch nội bộ | REQUIRED | TT99 Đ20 | Consolidation |
| R12 | TS sinh học tách NH/DH | REQUIRED | TT99 PL IV | Theo thời gian |
| R13 | Bỏ chỉ tiêu phải thu CPH | REQUIRED | TT99 PL IV | Remove from seed |
| R14 | DN không HĐLT → mẫu DNKLT | REQUIRED | TT99 Đ22 | Form switch |
| R15 | DN phải ban hành Quy chế hạch toán nếu sửa TK/chỉ tiêu | REQUIRED | TT99 Đ9,11,12,18 | Documentation |
| R16 | Ngoại tệ kế toán → BCTC VN vẫn phải bằng VND | REQUIRED | TT99 Đ6 | Conversion |
| R17 | BCTC không được phát hành lại | REQUIRED | TT99 Đ14 | Immutable |
| R18 | Hạn nộp 90 ngày từ cuối năm | REQUIRED | TT99 Đ15 | Deadline calc |
| R19 | Phải thu KH: lấy dư Nợ 131 | REQUIRED | TT99 PL IV | Detail balance |
| R20 | Trả trước NB: lấy dư Nợ 331 | REQUIRED | TT99 PL IV | Detail balance |

---

## 6. Gap Analysis — BookWise vs TT99

### 6.1 Thiếu sót hiện tại

| # | Khoản mục | TT99 yêu cầu | BookWise hiện tại | Mức độ |
|---|---|---|---|---|
| G01 | Mã 124 DP đầu tư NH đến đáo hạn | Có | Chưa có trong seed | **CAO** |
| G02 | Mã 125 Đầu tư NH khác | Có | Chưa có trong seed | **CAO** |
| G03 | Mã 126 DP tổn thất đầu tư NH khác | Có | Chưa có trong seed | **CAO** |
| G04 | Mã 150 TS sinh học NH | Có | Chưa có trong seed | **CAO** |
| G05 | Mã 230 TS sinh học DH | Có | Chưa có trong seed | **CAO** |
| G06 | Mã 134 sửa thành TS từ HĐ (137) / thanh toán tiến độ (337) | Có | Chỉ có 337 cũ | **TRUNG BÌNH** |
| G07 | Mã 135 trở thành Phải thu NH khác (cũ 136) | Sửa mã | Chưa cập nhật | **TRUNG BÌNH** |
| G08 | Chỉ tiêu phải thu CPH | Bỏ | Còn trong seed | **THẤP** |
| G09 | Chỉ tiêu phải thu cho vay NH/DH | Bỏ | Còn trong seed | **THẤP** |
| G10 | Mã 163 chỉ lấy dư Nợ 333 | Phân biệt Nợ/Có | Đã fix 333x nhưng chưa xử lý dư Nợ | **TRUNG BÌNH** |
| G11 | Mã 314 chỉ lấy dư Có 333 | Phân biệt Nợ/Có | Giống G10 | **TRUNG BÌNH** |
| G12 | Loại trừ giao dịch nội bộ BC01 tổng hợp | Cần | Chưa implement | **THẤP** (multi-entity) |
| G13 | Mẫu B01-DNKLT cho DN không HĐLT | Cần | Chưa có | **THẤP** |
| G14 | Thuyết minh B09-DN | Cần | Chưa có | **CAO** |

### 6.2 Ưu tiên xử lý

**P0 (Phải có trước production):**
- G01-G05: Thêm chỉ tiêu mới (mã 124,125,126,150,230) → seed migration
- G07: Sửa mã số chỉ tiêu phải thu
- G14: B09-DN (Thuyết minh BCTC)

**P1 (Cần có trong quý):**
- G06: Sửa mã 134
- G10-G11: Xử lý phân biệt dư Nợ/dư Có cho 163 và 314
- G08-G09: Xóa chỉ tiêu không còn

**P2 (Nice to have):**
- G12: Loại trừ nội bộ
- G13: Mẫu DNKLT

---

## 7. User Journey — Kế toán tổng hợp lập BC01

```
NGÀY 1: Chuẩn bị
├── Kiểm tra kỳ kế toán còn mở
├── Rà soát bút toán dở dang
├── Kiểm tra trial balance
└── Đối chiếu số dư TK chi tiết

NGÀY 2: Xử lý cuối kỳ
├── Phân bổ chi phí chờ phân bổ (242)
├── Tính khấu hao TSCĐ
├── Trích lập dự phòng (229)
├── Kiểm kê hàng tồn kho
├── Điều chỉnh chênh lệch tỷ giá
└── Kết chuyển doanh thu/chi phí (911)

NGÀY 3: Lập BC01
├── Mở module Báo cáo Tài chính
├── Chọn "BC01 — Báo cáo Tình hình Tài chính"
├── Chọn kỳ báo cáo (năm 2026)
├── Hệ thống hiển thị BC01
├── Kiểm tra: TS = NV (±tolerance)
├── So sánh cột đầu năm vs cuối kỳ
└── Phát hiện chênh lệch → trace ngược

NGÀY 4: Kiểm tra và xuất
├── Kế toán trưởng duyệt BC01
├── Xuất PDF (lưu trữ)
├── Xuất XBRL (nộp thuế điện tử)
├── Ghi nhận snapshot (nếu cần)
└── Đóng kỳ kế toán
```

---

## 8. Workflow Diagram (Text)

```
                 ┌─────────────────────────────────────┐
                 │  1. Kế toán chọn kỳ báo cáo         │
                 │     (năm/tháng/quý)                  │
                 └──────────┬──────────────────────────┘
                            ▼
                 ┌─────────────────────────────────────┐
                 │  2. Hệ thống đọc accounts.balance    │
                 │     cho tất cả TK trong kỳ           │
                 └──────────┬──────────────────────────┘
                            ▼
                 ┌─────────────────────────────────────┐
                 │  3. Xác định số dư từng chỉ tiêu     │
                 │     theo formula_type:               │
                 │     - account: balance của TK        │
                 │     - sum: tổng các chỉ tiêu con     │
                 │     - calculated: phép tính          │
                 └──────────┬──────────────────────────┘
                            ▼
                 ┌─────────────────────────────────────┐
                 │  4. Áp dụng sign_convention          │
                 │     - positive: abs(balance)         │
                 │     - negative: -abs(balance)        │
                 └──────────┬──────────────────────────┘
                            ▼
                 ┌─────────────────────────────────────┐
                 │  5. Kiểm tra cân đối: 280 = 440      │
                 │     Sai lệch ≤ 10 VND               │
                 └──────┬──────────────┬───────────────┘
                        ▼              ▼
                 ┌─────────────┐  ┌─────────────────────┐
                 │ Cân đối     │  │ Mất cân đối          │
                 │ OK          │  │ Báo lỗi + chênh lệch │
                 └──────┬──────┘  └──────────┬──────────┘
                        ▼                   ▼
                 ┌─────────────┐        Quay lại bước 1
                 │ Hiển thị    │        (fix data)
                 │ BC01        │
                 └──────┬──────┘
                        ▼
        ┌───────────────┼───────────────┐
        ▼               ▼               ▼
   ┌─────────┐    ┌─────────┐    ┌──────────┐
   │Xuất PDF │    │Xuất XBRL│    │Xem màn   │
   │         │    │(GDT)    │    │hình      │
   └─────────┘    └─────────┘    └──────────┘
```

---

## 9. Kiểm tra nội bộ (Internal Controls)

| Control | Mô tả | Tần suất |
|---|---|---|
| C01 | Trial balance Dr = Cr | Mỗi lần post |
| C02 | BC01 280 = 440 ±10 | Mỗi lần xem BC01 |
| C03 | Cột đầu năm = cuối kỳ trước | Khi đổi kỳ |
| C04 | Không có bút toán nào trong kỳ đã đóng | Trước khi xem BC01 |
| C05 | Số dư 333: tổng số dư Nợ + tổng dư Có các sub-account = số dư 333 | Đối chiếu |
| C06 | Mã 111 + 112 = số dư tiền thực tế (đối chiếu ngân hàng) | Hàng tháng |
| C07 | Mã 131: tổng dư Nợ chi tiết 131 = tổng KH phải thu | Hàng tháng |
| C08 | Mã 314: chỉ lấy dư Có của 333, không lấy dư Nợ | Khi lập BC01 |
| C09 | DP: số dư 229x không vượt quá số dư gốc của TS tương ứng | Cuối năm |
| C10 | Kiểm kê hàng tồn kho: số lượng thực tế khớp sổ sách | Cuối năm |

---

## 10. Kết luận

### 10.1 Đã làm đúng

- Cấu trúc BC01 với 280/440 tổng hợp
- Seed data bao phủ hầu hết chỉ tiêu chính
- Xử lý sign_convention (positive/negative)
- Kiểm tra cân đối ±10
- Migration 114 fix line 163
- Xuất XBRL (commit v3.1)

### 10.2 Cần làm tiếp

1. **Seed migration cho mã 124,125,126,150,230** (chỉ tiêu mới TT99)
2. **Sửa mã số chỉ tiêu phải thu NH** (135,136,137)
3. **Xóa chỉ tiêu cũ** (phải thu CPH, phải thu cho vay NH/DH)
4. **Xử lý phân biệt dư Nợ/dư Có** cho 163 và 314
5. **B09-DN Thuyết minh BCTC** (mẫu mới, nhiều thay đổi lớn)
6. **Cập nhật tên TK** theo TT99 (112, 155, 242...)
7. **B01-DNKLT** cho DN không hoạt động liên tục

### 10.3 Lưu ý pháp lý

- DN phải ban hành Quy chế hạch toán kế toán khi sửa đổi TK/chỉ tiêu BCTC (khoản 2 Điều 11, khoản 1 Điều 18)
- BCTC không được phát hành lại (Điều 14)
- Hạn nộp: 90 ngày từ cuối năm
- Nếu không thể sửa chỉ tiêu BCTC → báo cáo Bộ Tài chính (Điều 19)
