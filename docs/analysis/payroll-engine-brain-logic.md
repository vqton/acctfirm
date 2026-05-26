# Payroll Engine Brain Logic — Phân tích Nghiệp vụ Tiền lương Doanh nghiệp Việt Nam

> **Phiên bản:** 1.0
> **Phạm vi:** Toàn bộ quy trình tiền lương, bảo hiểm, thuế TNCN, quản lý nhân sự cho SME Việt Nam
> **Căn cứ pháp lý:**
> - Bộ Luật Lao động 2019 (hợp nhất 125/VBHN-VPQH 2025)
> - Luật BHXH 2024 (41/2024/QH15) — hiệu lực 01/07/2025
> - Luật Việc làm 2025 (74/2025/QH15) — hiệu lực 01/01/2026
> - Luật BHYT sửa đổi 2024 (51/2024/QH15)
> - Luật Thuế TNCN 2025 (109/2025/QH15) — hiệu lực 01/07/2026, áp dụng lương từ 01/01/2026
> - Luật Công đoàn 2024
> - Nghị định 73/2024/NĐ-CP (lương cơ sở 2.340.000đ)
> - Nghị định 74/2024/NĐ-CP (lương tối thiểu vùng 2024–2025)
> - Nghị định 158/2025/NĐ-CP (hướng dẫn Luật BHXH 2024)
> - Nghị định 293/2025/NĐ-CP (lương tối thiểu vùng 2026)
> - Nghị định 188/2025/NĐ-CP (hướng dẫn Luật BHYT)
> - Nghị quyết 110/2025/UBTVQH15 (giảm trừ gia cảnh mới)
> - Nghị định 105/2026/NĐ-CP (tài chính công đoàn — hiệu lực 16/05/2026)
> - Thông tư 99/2025/TT-BTC (Hệ thống tài khoản kế toán doanh nghiệp)

---

## Mục lục

1. [Payroll Engine Brain Logic](#1-payroll-engine-brain-logic)
2. [Real SME Payroll Scenarios](#2-real-sme-payroll-scenarios)
3. [Use Cases](#3-use-cases)
4. [Payroll Rule Logic](#4-payroll-rule-logic)
5. [Payroll Process Flow](#5-payroll-process-flow)
6. [Payroll Data Flow](#6-payroll-data-flow)
7. [Payroll Workflow and User Journey](#7-payroll-workflow-and-user-journey)
8. [SME Payroll Pain Analysis](#8-sme-payroll-pain-analysis)
9. [Hạch toán Kế toán Tiền lương](#9-hạch-toán-kế-toán-tiền-lương)

---

## 1. Payroll Engine Brain Logic

### 1.1 Why Payroll Engine Exist

**Vấn đề kinh doanh:**

Doanh nghiệp SME Việt Nam chi 25–35% tổng chi phí hoạt động cho tiền lương và các khoản trích theo lương. Đây là khoản chi lớn nhất và cũng là rủi ro pháp lý lớn nhất:

- **Mỗi tháng:** tính lương cho 10–500+ nhân viên với cấu trúc lương khác nhau
- **Mỗi nhân viên:** lương cơ bản, phụ cấp, thưởng, hoa hồng, tăng ca, công tác phí
- **Mỗi tháng:** trích nộp 32% tổng quỹ lương cho BHXH/BHYT/BHTN/KPCĐ
- **Mỗi tháng:** khấu trừ 10,5% lương nhân viên cho BHXH/BHYT/BHTN
- **Mỗi tháng:** tính thuế TNCN theo biểu lũy tiến 5 bậc (từ 2026)
- **Mỗi quý:** kê khai BHXH, BHYT, BHTN
- **Mỗi tháng/quý:** kê khai thuế TNCN
- **Mỗi năm:** quyết toán thuế TNCN, điều chỉnh BHXH

**Không có Payroll Engine:**
- Excel: 3–5 ngày làm việc mỗi tháng, sai sót 5–15%
- Sai lương → mất nhân viên, kiện tụng
- Sai BHXH → bị phạt 12–20% số tiền chậm đóng
- Sai thuế TNCN → bị phạt, truy thu, lãi chậm nộp
- Sai báo cáo tài chính → audit fail

**Payroll Engine giải quyết:**
- Tự động hóa toàn bộ vòng đời tính lương
- Đảm bảo tuân thủ pháp luật (luật thay đổi theo năm)
- Audit trail đầy đủ cho kiểm toán và thanh tra lao động
- Tích hợp với kế toán (bút toán lương tự động)
- Tích hợp với ngân hàng (chi lương tự động)

### 1.2 Payroll Cycle — Vòng đời Xử lý Lương

Mỗi tháng, quy trình xử lý lương diễn ra theo chu kỳ cố định:

```
Ngày 1-5:   Thu thập dữ liệu chấm công tháng trước
Ngày 5-10:  Nhập liệu bất thường (tăng ca, nghỉ phép, điều chỉnh)
Ngày 10-15: Tính lương thử → gửi trưởng bộ phận duyệt
Ngày 15-18: Điều chỉnh sau duyệt → chốt lương
Ngày 18-20: Tính BHXH/BHYT/BHTN/KPCĐ
Ngày 20-22: Tính thuế TNCN
Ngày 22-25: Phát lương (tiền mặt/chuyển khoản)
Ngày 25-30: Kê khai BHXH, kê khai thuế
Ngày 30-end: Hạch toán lương, phân bổ chi phí
```

**Độ trễ pháp lý:**
- Lương tháng N thường được tính và trả trong tháng N+1 (từ ngày 20–25)
- BHXH tháng N phải nộp trước ngày cuối cùng tháng N+1
- Thuế TNCN tháng N khấu trừ khi trả lương, nộp trước ngày 20 tháng N+1
- KPCĐ nộp cùng thời hạn BHXH (theo Nghị định 105/2026/NĐ-CP)

### 1.3 Cấu trúc Lương (Salary Structure)

**Lương Gross (Tổng thu nhập):**

```
Lương Gross = Lương cơ bản + Phụ cấp + Thưởng + Hoa hồng + Tăng ca
```

**Lương Net (Thực nhận):**

```
Lương Net = Lương Gross - BHXH/BHYT/BHTN (10.5%) - TNCN - Các khoản khấu trừ khác
```

**Chi phí thực tế doanh nghiệp:**

```
Chi phí thực = Lương Gross + BHXH/BHYT/BHTN/KPCĐ doanh nghiệp (23.5%)
            = Lương Gross × 123.5%
```

**Các thành phần lương:**

| Thành phần | Bắt buộc đóng BHXH | Tính thuế TNCN | Ghi chú |
|---|---|---|---|
| Lương cơ bản | ✅ Có | ✅ Có | Theo HĐLĐ |
| Phụ cấp chức vụ | ✅ Có | ✅ Có | Trưởng/phó phòng, quản đốc |
| Phụ cấp trách nhiệm | ✅ Có | ✅ Có | |
| Phụ cấp thâm niên | ✅ Có | ✅ Có | |
| Phụ cấp độc hại | ❌ Không | ❌ Miễn | Theo quy định |
| Phụ cấp khu vực | ❌ Không | ❌ Miễn | |
| Phụ cấp xăng xe | ❌ Không | ❌ Miễn | Theo quy chế công ty |
| Phụ cấp điện thoại | ❌ Không | ❌ Miễn | Nếu có hóa đơn |
| Phụ cấp ăn trưa | ❌ Không | ❌ Miễn | Theo quy định |
| Tiền thưởng | ❌ Không | ✅ Có | Thưởng lễ, Tết, KPI |
| Tiền hoa hồng | ❌ Không | ✅ Có | |
| Tiền tăng ca | ❌ Không | ✅ Có | |
| Công tác phí | ❌ Không | ❌ Miễn | Có hóa đơn chứng từ |

### 1.4 Cách tính Lương

#### 1.4.1 Công thức tính lương tháng

```
Lương tháng = (Lương Gross / Số ngày công chuẩn) × Số ngày công thực tế
```

**Số ngày công chuẩn:** 26 ngày (mặc định, có thể 24–27 tùy doanh nghiệp)

**Ngày công thực tế = Tổng ngày làm việc - Nghỉ không lương - Đi muộn/về sớm quy đổi**

#### 1.4.2 Quy đổi đi muộn/về sớm

Ví dụ: quy định 3 lần đi muộn = 1 ngày công mất
```
Số công mất = floor(Số lần đi muộn / 3)
```

#### 1.4.3 Tính lương theo sản phẩm

```
Lương sản phẩm = Số lượng sản phẩm × Đơn giá sản phẩm
```

#### 1.4.4 Tính lương theo giờ

```
Lương giờ = Lương Gross / (Số ngày công chuẩn × 8) × Số giờ thực tế
```

### 1.5 Cách tính Tăng ca (Overtime)

Căn cứ: Điều 98 Bộ Luật Lao động 2019

```
Tiền tăng ca = Số giờ tăng ca × Hệ số × (Lương Gross / (Số ngày công chuẩn × 8))
```

**Hệ số tăng ca:**

| Loại tăng ca | Hệ số | Ghi chú |
|---|---|---|
| Ngày thường (ngày làm việc) | 150% (1.5) | Làm thêm giờ |
| Ngày thường — ban đêm | 180% (1.5 × 1.3) = 195% | 150% + 30% ban đêm |
| Ngày nghỉ hàng tuần (CN) | 200% (2.0) | Thứ 7, CN |
| Ngày nghỉ — ban đêm | 260% (2.0 × 1.3) = 260% | |
| Ngày lễ, Tết | 300% (3.0) | 11 ngày lễ/năm |
| Ngày lễ — ban đêm | 390% (3.0 × 1.3) = 390% | |
| Làm thêm giờ ban đêm (giờ thường) | 130% | Đã bao gồm lương giờ thường |

**Giới hạn tăng ca:**
- Tối đa 40 giờ/tháng
- Tối đa 200 giờ/năm (300 giờ cho ngành đặc thù: dệt may, da giày, điện tử)
- Tối đa 12 giờ/ngày

### 1.6 Cách tính Phụ cấp (Allowance)

| Loại phụ cấp | Cách tính | Miễn thuế? | Đóng BHXH? |
|---|---|---|---|
| Phụ cấp chức vụ | % lương cơ bản (10–30%) | Không | Có |
| Phụ cấp trách nhiệm | Số tiền cố định/tháng | Không | Có |
| Phụ cấp thu hút | % lương | Không | Có |
| Phụ cấp độc hại | Hệ số 0.1–0.4 × lương cơ sở | Có | Không |
| Phụ cấp khu vực | Hệ số 0.1–0.5 × lương cơ sở | Có | Không |
| Phụ cấp xăng xe | Cố định (200k–500k/tháng) | Nếu ≤ mức quy định | Không |
| Phụ cấp điện thoại | Cố định hoặc theo hóa đơn | Theo hóa đơn thực tế | Không |
| Phụ cấp ăn trưa | Cố định (tối đa 730k/tháng miễn thuế) | Nếu ≤ 730k | Không |
| Phụ cấp nhà ở | Cố định | Nếu theo quy định | Không |
| Phụ cấp trang phục | Cố định (tối đa 5tr/năm miễn thuế) | Nếu ≤ 5tr/năm | Không |

### 1.7 Cách tính Bảo hiểm (Insurance Calculation)

#### 1.7.1 Tỷ lệ đóng 2026

**Người lao động Việt Nam (tổng: 32%):**

| Quỹ | NLĐ đóng | DN đóng | Tổng |
|---|---|---|---|
| BHXH (hưu trí + tử tuất) | 8% | 14% | 22% |
| BHXH (ốm đau + thai sản) | — | 3% | 3% |
| BHXH (tai nạn lao động, BNN) | — | 0.5% | 0.5% |
| BHYT | 1.5% | 3% | 4.5% |
| BHTN | 1% | 1% | 2% |
| **Tổng cộng** | **10.5%** | **21.5%** | **32%** |
| KPCĐ (ngoài) | — | 2% | 2% |
| **Tổng chi phí DN** | | **23.5%** | |

**Người lao động nước ngoài (tổng: 30%, không đóng BHTN):**

| Quỹ | NLĐ đóng | DN đóng | Tổng |
|---|---|---|---|
| BHXH | 8% | 17.5% | 25.5% |
| BHYT | 1.5% | 3% | 4.5% |
| **Tổng cộng** | **9.5%** | **20.5%** | **30%** |

#### 1.7.2 Công thức tính BHXH/BHYT/BHTN

```
Mức đóng BHXH/BHYT = min(Lương Gross, Trần BHXH) × Tỷ lệ

Trần BHXH/BHYT = 20 × Lương cơ sở = 20 × 2,340,000 = 46,800,000 đ/tháng

Mức đóng BHTN = min(Lương Gross, Trần BHTN theo vùng) × 1%

Trần BHTN = 20 × Lương tối thiểu vùng
  - Vùng I: 20 × 5,310,000 = 106,200,000 đ/tháng
  - Vùng II: 20 × 4,730,000 = 94,600,000 đ/tháng
  - Vùng III: 20 × 4,140,000 = 82,800,000 đ/tháng
  - Vùng IV: 20 × 3,700,000 = 74,000,000 đ/tháng
```

**Sàn đóng BHXH:** Lương Gross không thấp hơn lương tối thiểu vùng tại thời điểm đóng đối với lao động giản đơn nhất.

#### 1.7.3 Ví dụ tính BHXH

Nhân viên lương Gross 50,000,000đ, Vùng I:

```
BHXH (NLĐ): min(50tr, 46.8tr) × 8% = 46.8tr × 8% = 3,744,000
BHYT (NLĐ): min(50tr, 46.8tr) × 1.5% = 46.8tr × 1.5% = 702,000
BHTN (NLĐ): min(50tr, 106.2tr) × 1% = 50tr × 1% = 500,000
Tổng NLĐ đóng: 4,946,000 (9.89% của 50tr — giảm dần % khi lương cao)
```

### 1.8 Cách tính Thuế TNCN (PIT Calculation)

#### 1.8.1 Biểu thuế lũy tiến 5 bậc (từ kỳ tính thuế 2026)

Căn cứ: Luật Thuế TNCN 2025 (109/2025/QH15), Nghị quyết 110/2025/UBTVQH15

| Bậc | Thu nhập tính thuế/tháng | Thuế suất | Số thuế tính lũy tiến | Công thức rút gọn |
|---|---|---|---|---|
| 1 | Đến 10 triệu | 5% | TNTT × 5% | TNTT × 5% |
| 2 | Trên 10 – 30 triệu | 10% | 0.5tr + (TNTT-10tr)×10% | TNTT × 10% – 0.5tr |
| 3 | Trên 30 – 60 triệu | 20% | 2.5tr + (TNTT-30tr)×20% | TNTT × 20% – 3.5tr |
| 4 | Trên 60 – 100 triệu | 30% | 8.5tr + (TNTT-60tr)×30% | TNTT × 30% – 9.5tr |
| 5 | Trên 100 triệu | 35% | 20.5tr + (TNTT-100tr)×35% | TNTT × 35% – 14.5tr |

**Sự khác biệt so với trước 2026:**
- 7 bậc → 5 bậc
- Giảm trừ gia cảnh: 11tr → 15.5tr (bản thân)
- Giảm trừ người phụ thuộc: 4.4tr → 6.2tr/người

#### 1.8.2 Công thức tính thuế TNCN

```
Thu nhập tính thuế (TNTT) = Tổng thu nhập chịu thuế - Các khoản giảm trừ

Trong đó:
  Tổng thu nhập chịu thuế = Lương Gross - Các khoản miễn thuế
  
  Các khoản giảm trừ:
  - Giảm trừ bản thân: 15,500,000 đ/tháng
  - Giảm trừ người phụ thuộc: 6,200,000 đ/người/tháng
  - BHXH/BHYT/BHTN: 10.5% lương đóng BHXH
  - Từ thiện, nhân đạo, khuyến học (nếu có)
```

#### 1.8.3 Ví dụ tính PIT

Nhân viên Gross 30,000,000đ, 1 người phụ thuộc, Vùng I:

```
Bước 1: Lương Gross = 30,000,000
Bước 2: BHXH = min(30tr, 46.8tr) × 10.5% = 3,150,000
Bước 3: Giảm trừ bản thân = 15,500,000
Bước 4: Giảm trừ người phụ thuộc = 6,200,000
Bước 5: TNTT = 30,000,000 - 3,150,000 - 15,500,000 - 6,200,000 = 5,150,000
Bước 6: Thuế TNCN = 5,150,000 × 5% = 257,500 đ
```

#### 1.8.4 Ngưỡng chịu thuế theo số người phụ thuộc

| Số NPT | Thu nhập bắt đầu chịu thuế/tháng |
|---|---|
| 0 | > 15.5 triệu |
| 1 | > 21.7 triệu |
| 2 | > 27.9 triệu |
| 3 | > 34.1 triệu |
| n | > 15.5 + n × 6.2 triệu |

### 1.9 Quy trình Duyệt Lương (Approval Flow)

**Nguyên tắc:**
1. **Phân quyền:** Trưởng bộ phận duyệt chấm công → Kế toán trưởng duyệt lương → Giám đốc duyệt chi
2. **Phân tách:** Người tính lương ≠ Người duyệt lương ≠ Người chi lương
3. **Bất khả hồi tố:** Lương đã duyệt = read-only, chỉ được điều chỉnh bằng bút toán điều chỉnh riêng

**Luồng duyệt:**

```
HR nhập chấm công
  → Trưởng bộ phận duyệt chấm công (công nhận ngày công)
    → Kế toán lương tính lương (engine tự động)
      → Kế toán trưởng kiểm tra + duyệt
        → Giám đốc duyệt chi
          → Phát lương
```

### 1.10 Chốt lương (Payroll Closing)

**Nguyên tắc:**
1. Sau chốt: không sửa lương kỳ đó, chỉ bổ sung kỳ sau
2. Chốt lương đồng thời với chốt kỳ kế toán
3. Audit trail: ghi nhận timestamp + người thực hiện + dữ liệu trước/sau

**Các bước chốt:**
1. Kiểm tra tất cả nhân viên đã được tính lương
2. Kiểm tra tổng Dr = Cr của bút toán lương
3. Kiểm tra khớp tổng lương với dự toán (nếu có)
4. Khóa bảng lương
5. Sinh bút toán kế toán
6. Sinh báo cáo lương

### 1.11 Audit Trail

**Mọi thay đổi trong payroll cycle phải được ghi lại:**
- Thay đổi thông tin nhân viên (lương, phụ cấp, chức vụ)
- Thay đổi chấm công (điều chỉnh ngày công)
- Thay đổi bảng lương trước chốt
- Mở lại kỳ lương đã chốt (chỉ CFO/giám đốc)
- Chi lương (tiền mặt/chuyển khoản)

### 1.12 Compliance Checking

**Hệ thống phải tự động kiểm tra:**

| Kiểm tra | Hành vi | Hậu quả nếu sai |
|---|---|---|
| Lương ≥ tối thiểu vùng | Block | Phạt 20–75tr |
| Lương thử việc ≥ 85% | Block | Phạt 2–10tr |
| BHXH đúng tỷ lệ | Auto tính | Phạt 12–20% |
| BHXH đúng trần/sàn | Auto tính | Phạt + truy thu |
| TNCN đúng biểu thuế | Auto tính | Phạt + lãi chậm |
| Tăng ca ≤ 200h/năm | Warn | Phạt 5–50tr |
| Nghỉ phép đúng quy định | Auto tính | Bồi thường |
| Thời hạn nộp BHXH | Warn + nhắc | Phạt lãi suất |

---

## 2. Real SME Payroll Scenarios

### 2.1 Normal Employee Salary
- Nhân viên chính thức, HĐLĐ không xác định thời hạn
- Lương Gross 15tr, 0 người phụ thuộc, Vùng I
- Làm đủ 26 ngày công

### 2.2 Probation Salary
- Thử việc 60 ngày, lương ≥ 85% lương chính thức
- Nhân viên thử việc có HĐLĐ dưới 1 tháng → KHÔNG đóng BHXH
- Nhân viên thử việc có HĐLĐ từ 1 tháng → PHẢI đóng BHXH

### 2.3 Part-time Employee
- Làm việc < 8 giờ/ngày hoặc < 48 giờ/tuần
- Lương = (Lương Gross / 26 / 8) × Số giờ thực tế
- Vẫn phải đóng BHXH nếu HĐLĐ ≥ 1 tháng

### 2.4 Shift Employee
- Ca ngày: 6h–14h, Ca chiều: 14h–22h, Ca đêm: 22h–6h
- Ca đêm: phụ cấp ít nhất 30% lương giờ thực tế
- Làm ca đêm từ 22h–6h sáng

### 2.5 Overtime Employee
- Làm thêm giờ có sự đồng ý của nhân viên
- Tối đa 40h/tháng, 200h/năm
- Tính theo hệ số (150%–300%–390%)

### 2.6 Sales Commission
- Hoa hồng bán hàng theo doanh thu
- Hoa hồng = Doanh số × % hoa hồng
- Hoa hồng chịu thuế TNCN, không đóng BHXH

### 2.7 Attendance Missing / Late Check-in / Early Check-out
- Các lần vi phạm được quy đổi thành công mất
- Đi muộn/về sớm: phạt trừ lương theo phút hoặc quy đổi

### 2.8 Unpaid Leave
- Nghỉ không lương: trừ toàn bộ lương ngày đó
- Không đóng BHXH cho ngày nghỉ không lương (nếu nghỉ > 14 ngày/tháng → không đóng BHXH tháng đó)

### 2.9 Paid Leave (Annual Leave)
- 12 ngày phép năm (người làm việc bình thường)
- Cứ 5 năm tăng thêm 1 ngày
- Nghỉ phép: hưởng 100% lương

### 2.10 Holiday Salary
- 11 ngày lễ Tết được nghỉ hưởng nguyên lương
- Nếu đi làm: hưởng 300% lương

### 2.11 Bonus Payment
- Thưởng Tết, thưởng lễ, thưởng KPI, thưởng năng suất
- Chịu thuế TNCN, KHÔNG đóng BHXH
- Có thể chi bằng tiền mặt, chuyển khoản, hiện vật

### 2.12 Salary Advance
- Nhân viên ứng trước lương (tối đa 30% lương tháng)
- Hạch toán Nợ 334 / Có 111
- Khi trả lương: trừ vào lương thực nhận

### 2.13 Retroactive Salary Adjustment
- Điều chỉnh lương hồi tố (do quên, sai sót, tăng lương từ tháng trước)
- Cần audit trail riêng
- Bút toán bổ sung kỳ hiện tại (không sửa kỳ trước đã chốt)

### 2.14 Insurance Adjustment
- Điều chỉnh mức đóng BHXH (tăng/giảm lương)
- BHXH: bắt buộc kê khai lại với cơ quan BHXH
- Chênh lệch: truy thu/hoàn trả

### 2.15 PIT Recalculation
- Quyết toán thuế TNCN cuối năm
- Tính lại thuế cả năm, xác định nộp thừa/thiếu
- Nộp thừa: đề nghị hoàn/trừ kỳ sau
- Nộp thiếu: nộp bổ sung

### 2.16 Employee Resignation / Final Settlement
- Nghỉ việc: thanh toán toàn bộ quyền lợi
- Lương tháng cuối cùng (tính đến ngày nghỉ việc)
- Trợ cấp thôi việc (nếu đủ 12 tháng trở lên)
- Trợ cấp mất việc làm (nếu bị mất việc)
- Thanh toán phép năm còn lại
- Chốt sổ BHXH, trả sổ cho nhân viên

#### Công thức trợ cấp thôi việc
```
Trợ cấp thôi việc = 1/2 × Thời gian tính trợ cấp (năm) × Lương bình quân 6 tháng

Thời gian tính trợ cấp = Tổng thời gian làm việc - Thời gian đóng BHTN - Thời gian đã nhận trợ cấp
```

#### Công thức trợ cấp mất việc làm
```
Trợ cấp mất việc = 1 × Thời gian tính trợ cấp (năm) × Lương bình quân 6 tháng
Tối thiểu: 2 tháng lương
```

### 2.17 Multi-branch Payroll
- Doanh nghiệp có nhiều chi nhánh, vùng miền khác nhau
- Mỗi chi nhánh có thể có lương tối thiểu vùng khác nhau
- Lương đóng BHXH theo vùng của chi nhánh
- Cần phân quyền: HR chi nhánh nhập liệu, HR trung tâm duyệt

### 2.18 Cash Salary Payment
- Chi lương bằng tiền mặt
- Phiếu chi: Nợ 334 / Có 111
- Cần chữ ký nhận của nhân viên

### 2.19 Bank Transfer Salary Payment
- Chi lương qua chuyển khoản
- File chuyển tiền: sinh file theo format ngân hàng
- Ủy nhiệm chi: Nợ 334 / Có 112

### 2.20 Re-open Payroll Period
- Trường hợp ngoại lệ: cần mở lại kỳ lương đã chốt
- Chỉ CFO/Giám đốc mới có quyền
- Audit trail bắt buộc: ghi rõ lý do, thời gian mở, ai mở
- Sau khi điều chỉnh: chốt lại và ghi nhận bút toán điều chỉnh

---

## 3. Use Cases

### UC-01: Tính lương nhân viên chính thức

| Field | Value |
|---|---|
| **Use Case Name** | Tính lương nhân viên chính thức hàng tháng |
| **Business Goal** | Xác định lương thực nhận và chi phí doanh nghiệp |
| **Actors** | HR, Kế toán lương, Trưởng bộ phận |
| **Preconditions** | Nhân viên đã ký HĐLĐ, có thông tin lương, BHXH, người phụ thuộc |
| **Trigger** | Hết tháng, đến chu kỳ tính lương (ngày 20-25 hàng tháng) |
| **Happy Path** | 1. HR xuất dữ liệu chấm công → 2. Trưởng bộ phận duyệt → 3. Engine tính: Gross - BH - GTGC = TNTT → 4. Engine tra biểu thuế → 5. Net = Gross - BH - Thuế → 6. Duyệt → 7. Chi lương |
| **Alternative Path** | Điều chỉnh chấm công (nghỉ bù, tăng ca chưa duyệt) → quay lại bước 3 |
| **Exception Path** | Lương Gross < lương tối thiểu vùng → BLOCK. Lương Gross > trần BHXH → tự động cắt trần |
| **Validation Rules** | • Lương ≥ tối thiểu vùng • BHXH không vượt trần • TNTT ≥ 0 • Tổng Dr = Cr |
| **Payroll Rules** | Công thức Gross-to-Net chuẩn |
| **Accounting Impact** | Nợ 622/627/641/642 / Có 334; Nợ 334 / Có 3383/3384/3386/3335 |
| **HR Impact** | Nhân viên nhận lương đúng hạn, đúng số |
| **Compliance Risk** | Sai lương → kiện tụng; Sai BHXH → phạt; Sai thuế → truy thu |
| **Final Result** | Lương Net được chuyển vào tài khoản nhân viên, bảng lương lưu audit |

### UC-02: Tiếp nhận nhân viên mới

| Field | Value |
|---|---|
| **Use Case Name** | Tiếp nhận nhân viên mới vào hệ thống lương |
| **Business Goal** | Đảm bảo nhân viên mới được trả lương đúng kỳ |
| **Actors** | HR, Trưởng bộ phận |
| **Preconditions** | Nhân viên đã ký HĐLĐ, đã pass thử việc hoặc đang thử việc |
| **Trigger** | Ngày đầu tiên đi làm / Ngày ký HĐLĐ |
| **Happy Path** | HR nhập: Họ tên, CMND/CCCD, lương Gross, phụ cấp, BHXH, số NPT, tài khoản NH, chi nhánh |
| **Alternative Path** | Chưa có số BHXH → tạm tính 0%, bổ sung sau |
| **Exception Path** | Lương không hợp lệ (< tối thiểu vùng) → BLOCK |
| **Validation Rules** | • Lương ≥ tối thiểu vùng • Số CMND/CCCD tồn tại • Tài khoản NH hợp lệ • Số BHXH (nếu có) tồn tại |
| **Compliance Risk** | Không đăng ký BHXH trong 30 ngày → phạt |

### UC-03: Nhân viên nghỉ việc — Final Settlement

| Field | Value |
|---|---|
| **Use Case Name** | Quyết toán khi nhân viên nghỉ việc |
| **Business Goal** | Thanh toán đầy đủ quyền lợi cho nhân viên, chốt BHXH |
| **Actors** | HR, Kế toán lương, Nhân viên |
| **Preconditions** | Nhân viên đã nộp đơn, đã hết thời gian báo trước |
| **Trigger** | Ngày nghỉ việc cuối cùng |
| **Happy Path** | 1. Tính lương đến ngày nghỉ → 2. Tính phép năm còn lại → 3. Tính trợ cấp thôi việc (nếu đủ 12 tháng) → 4. Trừ tạm ứng/thiếu → 5. Chi lần cuối → 6. Chốt sổ BHXH → 7. Cập nhật trạng thái "Đã nghỉ việc" |
| **Alternative Path** | Nhân viên bị mất việc → tính trợ cấp mất việc (1 tháng/năm, tối thiểu 2 tháng) |
| **Exception Path** | Nhân viện kiện tụng → giữ lương chờ phán quyết |
| **Validation Rules** | • Tính đúng trợ cấp (trừ thời gian đóng BHTN) • Chốt đúng ngày BHXH |
| **Compliance Risk** | Không trả trợ cấp → bị kiện; Chốt BHXH sai → nhân viên khiếu nại |

### UC-04: Xử lý tăng ca tháng

| Field | Value |
|---|---|
| **Use Case Name** | Tính tiền tăng ca hàng tháng |
| **Business Goal** | Chi trả đúng tiền tăng ca theo quy định pháp luật |
| **Actors** | Nhân viên, Trưởng bộ phận, HR, Kế toán lương |
| **Preconditions** | Có đăng ký tăng ca được duyệt trước; Có check-in/check-out thực tế |
| **Trigger** | Cuối tháng, tổng hợp tăng ca |
| **Happy Path** | 1. Nhân viên check-in/out → 2. Hệ thống ghi nhận giờ → 3. Tự động tính theo hệ số → 4. Duyệt tăng ca → 5. Cộng vào lương |
| **Alternative Path** | Tăng ca chưa duyệt → gửi lại yêu cầu duyệt |
| **Exception Path** | Tăng ca > 40h/tháng → BLOCK (vượt giới hạn). Tăng ca > 200h/năm → WARN (chỉ cho phép với ngành đặc thù) |
| **Validation Rules** | • Tổng tăng ca ≤ 40h/tháng • ≤ 200h/năm • ≤ 12h/ngày • Nhân viên đã đồng ý |
| **Compliance Risk** | Vượt giới hạn tăng ca → phạt 5–50tr. Không trả đủ → bị kiện |

### UC-05: Điều chỉnh lương hồi tố

| Field | Value |
|---|---|
| **Use Case Name** | Điều chỉnh lương hồi tố |
| **Business Goal** | Sửa sai sót về lương của các tháng trước |
| **Actors** | Kế toán lương, Kế toán trưởng |
| **Preconditions** | Kỳ lương cũ đã chốt; có chứng từ điều chỉnh |
| **Trigger** | Phát hiện sai sót / Quyết định tăng lương từ tháng trước |
| **Happy Path** | 1. Lập phiếu điều chỉnh → 2. Duyệt (CFO/Giám đốc) → 3. Hạch toán bổ sung kỳ hiện tại → 4. Bù trừ vào lương kỳ này |
| **Exception Path** | Không thể mở lại kỳ cũ (đã khóa sổ kế toán) → chỉ được điều chỉnh vào kỳ hiện tại |
| **Validation Rules** | • Audit trail bắt buộc • Ghi rõ lý do • Không sửa dữ liệu gốc |
| **Accounting Impact** | Nợ/Có 334 chênh lệch; Bút toán điều chỉnh riêng |
| **Compliance Risk** | Điều chỉnh không có chứng từ → audit fail |

### UC-06: Nhân viên nghỉ thai sản

| Field | Value |
|---|---|
| **Use Case Name** | Xử lý chế độ thai sản |
| **Business Goal** | Đảm bảo nhân viên nghỉ thai sản nhận đủ quyền lợi BHXH |
| **Actors** | Nhân viên, HR, Kế toán lương, BHXH |
| **Preconditions** | Nhân viên đã đóng BHXH ≥ 6 tháng; có giấy khai sinh/chứng nhận |
| **Happy Path** | 1. Nhân viên nộp hồ sơ → 2. HR làm chế độ BHXH → 3. BHXH duyệt → 4. Nhận tiền từ BHXH → 5. Chi cho nhân viên |
| **Accounting Impact** | Nợ 3383 / Có 334; Khi nhận: Nợ 111/112 / Có 3383; Khi trả: Nợ 334 / Có 111/112 |

### UC-07: Chấm công và quy đổi vi phạm

| Field | Value |
|---|---|
| **Use Case Name** | Xử lý vi phạm chấm công |
| **Business Goal** | Tính đúng ngày công thực tế, xử phạt vi phạm |
| **Actors** | Hệ thống chấm công, HR, Trưởng bộ phận |
| **Preconditions** | Có thiết bị chấm công (vân tay/khuôn mặt/GPS) |
| **Happy Path** | 1. Nhân viên chấm công → 2. Hệ thống ghi nhận → 3. Tự động quy đổi: đi muộn 3 lần = 1 công mất → 4. HR duyệt → 5. Trừ vào lương |
| **Validation Rules** | • Cho phép dung sai 15–30 phút (tùy chính sách) • Có lý do hợp lệ → được bỏ qua |

---

## 4. Payroll Rule Logic

### 4.1 Salary Formula

```
Gross Salary = Basic Salary + Allowances + Bonuses + Commission + Overtime
Net Salary = Gross Salary - Employee Insurance - PIT - Other Deductions
Employer Cost = Gross Salary + Employer Insurance (23.5%)
```

### 4.2 Attendance Validation

1. **Đi muộn ≥ 30 phút:** tính 1 lần đi muộn
2. **Về sớm ≥ 30 phút:** tính 1 lần về sớm
3. **Quên chấm công:** nhân viên báo, trưởng bộ phận xác nhận
4. **Không chấm công không báo:** tính 0.5 công (cảnh cáo)
5. **Vắng không lý do:** tính 0 công, không lương

### 4.3 Overtime Approval

1. **Nguyên tắc:** phải đăng ký trước khi làm tăng ca
2. **Duyệt trước:** trưởng bộ phận duyệt trước khi tăng ca
3. **Check-in/out thực tế:** phải có chấm công thực tế
4. **Giới hạn cứng:** không cho phép > 40h/tháng
5. **Giới hạn mềm:** > 200h/năm → cảnh báo + yêu cầu phê duyệt cấp cao

### 4.4 Allowance Control

1. **Phụ cấp chức vụ:** tự động theo chức vụ trong hệ thống
2. **Phụ cấp trách nhiệm:** gán thủ công, có ngày hiệu lực
3. **Phụ cấp độc hại:** tự động theo danh sách vị trí độc hại
4. **Phụ cấp ăn trưa:** tối đa 730k/tháng (miễn thuế)
5. **Phụ cấp xăng xe:** cố định theo chính sách

### 4.5 Deduction Calculation

1. **BHXH:** 8% × min(Gross, Trần BHXH)
2. **BHYT:** 1.5% × min(Gross, Trần BHXH)
3. **BHTN:** 1% × min(Gross, Trần BHTN)
4. **Tạm ứng:** số tiền đã ứng trong tháng
5. **Bảo hiểm khác:** bảo hiểm sức khỏe (nếu có)
6. **Đoàn phí công đoàn:** 1% lương (nếu là đoàn viên)

### 4.6 Insurance Salary Identification — Xác định lương đóng BHXH

1. **Lương Gross ≤ Trần BHXH:** lương đóng = lương Gross
2. **Lương Gross > Trần BHXH:** lương đóng = 46,800,000 (trần)
3. **Lương Gross < Sàn:** lương đóng = lương tối thiểu vùng
4. **Lương đóng BHTN:** tính riêng theo trần vùng

### 4.7 PIT Taxable Income Identification

```
PIT Taxable Income = Gross Salary
  - Tax-exempt allowances (meal, transport, phone up to limit)
  - Mandatory insurance (10.5% of insurance salary)
  - Personal deduction (15,500,000)
  - Dependent deduction (6,200,000 × N)
  - Charity/humanitarian contributions
```

### 4.8 Dependent Deduction Handling

1. **Đăng ký:** nhân viên đăng ký người phụ thuộc qua HR
2. **Mã số thuế NPT:** bắt buộc có MST người phụ thuộc
3. **Hiệu lực:** từ tháng đăng ký
4. **Hết hiệu lực:** khi NPT không còn đủ điều kiện (VD: con hết 18t)
5. **Giới hạn:** không giới hạn số NPT (nhưng phải chứng minh)

### 4.9 Invalid Payroll Prevention

1. **Lương âm:** BLOCK (Net < 0)
2. **TNTT âm:** set TNTT = 0 (không thuế, không âm)
3. **Thiếu thông tin:** BLOCK nếu thiếu lương Gross, BHXH, số NPT
4. **Trùng lặp:** BLOCK nếu nhân viên đã có lương tháng đó
5. **Chưa duyệt:** BLOCK không cho chi lương

### 4.10 Duplicate Payment Prevention

1. **Check-in/out trùng:** phát hiện chấm công trùng IP/thời điểm
2. **Lương tháng trùng:** kiểm tra unique (employee_id, period_id)
3. **Chi trùng:** check file chuyển tiền trùng số tài khoản + số tiền
4. **Đã nghỉ việc:** không tính lương cho nhân viên đã nghỉ

### 4.11 Unauthorized Adjustment Prevention

1. **Phân quyền:** HR không được sửa lương Gross (chỉ HR Manager)
2. **Audit log:** mọi thay đổi ghi lại ai, khi nào, cũ→mới
3. **Duyệt kép:** thay đổi > 10% lương cần 2 người duyệt
4. **Hồi tố:** chỉ CFO/Giám đốc mới được duyệt

### 4.12 Payroll Fraud Risk Detection

| Dấu hiệu | Mô tả | Mức độ |
|---|---|---|
| Nhân viên ảo | Có tên trong bảng lương nhưng không có chấm công | CAO |
| Lương tăng đột biến | Tăng > 30% so với tháng trước không có lý do | CAO |
| Hoa hồng bất thường | Hoa hồng > 50% lương Gross | TRUNG BÌNH |
| Tăng ca bất thường | Tăng ca > 60h/tháng | CAO |
| Nhiều người phụ thuộc | > 5 NPT trên một nhân viên | TRUNG BÌNH |
| Tài khoản NH trùng | 2 nhân viên chung 1 tài khoản NH | CAO |
| Nghỉ việc không chốt | Nhân viên nghỉ nhưng vẫn tính lương | CAO |

---

## 5. Payroll Process Flow

### 5.1 End-to-End Payroll Lifecycle

```
THÁNG N (dữ liệu)
  │
  ├── Tuần 1 (Ngày 1–5): Thu thập dữ liệu
  │   ├── Chấm công (vân tay/khuôn mặt/GPS)
  │   ├── Tăng ca đăng ký
  │   ├── Nghỉ phép (đã duyệt)
  │   ├── Đi công tác
  │   └── Biến động nhân sự (vào/ra/thay đổi lương)
  │
  ├── Tuần 2 (Ngày 5–12): Xử lý dữ liệu
  │   ├── HR tổng hợp chấm công
  │   ├── Trưởng bộ phận duyệt chấm công
  │   ├── Nhập điều chỉnh (tăng ca, nghỉ bù, quên chấm)
  │   └── Duyệt điều chỉnh
  │
  ├── Tuần 3 (Ngày 12–20): Tính lương
  │   ├── Engine tính lương thử (draft)
  │   ├── Kế toán lương kiểm tra
  │   ├── Điều chỉnh (nếu sai)
  │   ├── Kế toán trưởng duyệt
  │   └── CHỐT LƯƠNG
  │
  ├── Tuần 4 (Ngày 20–25): Chi lương
  │   ├── Sinh bảng lương chính thức
  │   ├── Sinh file chuyển khoản (hoặc phiếu chi TM)
  │   ├── Giám đốc duyệt chi
  │   ├── Thực hiện chi lương
  │   ├── Gửi bảng lương nhân viên (payslip)
  │   └── Hạch toán lương
  │
  └── Tuần 5 (Ngày 25–30): Hoàn tất
      ├── Hạch toán BHXH/BHYT/BHTN
      ├── Hạch toán thuế TNCN
      ├── Kê khai BHXH
      ├── Kê khai thuế TNCN
      ├── Nộp BHXH/BHYT/BHTN/KPCĐ
      └── Nộp thuế TNCN
```

### 5.2 HR vs Accounting Relationship

```
HR: Quản lý nhân sự, chấm công, ngày nghỉ, biến động
  → Chuyển dữ liệu đã duyệt cho Kế toán lương

Kế toán lương: Nhận dữ liệu, chạy engine, tính BHXH/TNCN
  → Trình Kế toán trưởng duyệt

Kế toán trưởng: Kiểm tra tính hợp lệ, duyệt
  → Trình Giám đốc duyệt chi

Giám đốc: Duyệt chi
  → Kế toán thanh toán thực hiện chi
```

### 5.3 Attendance vs Payroll Relationship

```
Attendance → Raw data (check-in/out times)
  ↓
HR Validation → Approved attendance (after review by manager)
  ↓
Payroll Engine → Salary calculation (using approved attendance)
  ↓
Payslip Generation → Final payslip
```

### 5.4 Payroll vs Finance Relationship

```
Payroll (đã duyệt)
  ↓
Bút toán lương (automatic journal entries)
  ├── Nợ 622, 627, 641, 642 / Có 334 (lương)
  ├── Nợ 622, 627, 641, 642 / Có 3382, 3383, 3384, 3386 (BHXH DN)
  ├── Nợ 334 / Có 3383, 3384, 3386 (BHXH NLĐ)
  └── Nợ 334 / Có 3335 (thuế TNCN)
  ↓
Khoản phải thu/phải trả
  ├── 3383, 3384, 3386 → nộp BHXH
  ├── 3335 → nộp thuế
  └── 334 → chi lương
  ↓
Báo cáo tài chính
  ├── BC01: 334 (Nợ phải trả), 338 (Nợ phải trả)
  └── BC02: 622, 627, 641, 642 (Chi phí lương)
```

### 5.5 Payroll Closing Flow

```
1. Kiểm tra: tất cả NV đã tính lương? → YES
2. Kiểm tra: tất cả chấm công đã duyệt? → YES
3. Kiểm tra: tất cả điều chỉnh đã duyệt? → YES
4. Kiểm tra: tổng Dr = Cr? → YES
5. Chốt bảng lương (status = closed)
6. Sinh bút toán kế toán
7. Sinh báo cáo lương
8. Lưu audit trail
```

### 5.6 Payslip Flow

```
Engine tính lương
  → Tạo draft payslip cho từng NV
  → Duyệt
  → Ký số (digital signature) — optional
  → Gửi email/Zalo/App
  → Lưu PDF
  → Nhân viên xác nhận đã nhận (optional)
```

### 5.7 Payment Flow

```
Cash Payment:
  Lập phiếu chi → Kế toán trưởng ký → Thủ quỹ chi → Nhân viên ký nhận

Bank Transfer:
  Sinh file chuyển tiền (template NH)
  → Import vào Internet Banking
  → Giám đốc ký duyệt trên IB
  → Ngân hàng thực hiện chuyển
  → Sao kê NH đối chiếu
```

### 5.8 Audit Preparation Flow

```
Cơ quan thuế/BHXH/LĐ thanh tra
  → Xuất các báo cáo:
    1. Bảng lương hàng tháng (cả năm)
    2. Bảng kê BHXH theo tháng
    3. Bảng kê TNCN theo tháng
    4. HĐLĐ của nhân viên
    5. Bảng chấm công
    6. Phiếu chi lương/chứng từ chuyển khoản
    7. Quy chế lương thưởng
```

### 5.9 Exception Handling Flow

```
Phát hiện lỗi:
  Trước duyệt:
    → Sửa trực tiếp, không cần audit
  Sau duyệt, trước chi:
    → Hủy duyệt → sửa → duyệt lại (có audit)
  Sau chi:
    → Lập phiếu điều chỉnh kỳ sau
    → Bút toán điều chỉnh
    → Không mở lại kỳ cũ
  Sai BHXH:
    → Điều chỉnh với cơ quan BHXH kỳ sau
    → Truy thu/hoàn trả
  Sai thuế:
    → Kê khai bổ sung (lần 1, 2, ...)
    → Nộp bổ sung + lãi chậm nộp
```

---

## 6. Payroll Data Flow

### 6.1 Employee Master Data Flow

```
HR nhập:
  ┌─────────────────────┐
  │ Employee Master     │ ← Họ tên, CMND/CCCD, Ngày sinh
  │                     │ ← Ngày vào làm, Ngày ký HĐLĐ
  │                     │ ← Lương Gross, Phụ cấp
  │                     │ ← Chức vụ, Phòng ban, Chi nhánh
  │                     │ ← Số BHXH, Số thuế TNCN
  │                     │ ← Số NPT (kèm MST từng NPT)
  │                     │ ← Tài khoản NH, Mã số thuế
  │                     │ ← Trạng thái (đang làm/thử việc/nghỉ)
  └─────────────────────┘
       ↓
  Payroll Engine sử dụng để tính lương
```

### 6.2 Attendance Data Flow

```
Thiết bị chấm công (vân tay/khuôn mặt/GPS)
  → Raw data (timestamp, employee_id, type)
    → HR System tổng hợp
      → Tính số công, tăng ca, đi muộn
        → Trưởng bộ phận duyệt
          → Approved attendance
            → Payroll Engine
```

### 6.3 Salary Calculation Flow

```
Input:
  ├── Employee master (lương, phụ cấp)
  ├── Approved attendance (công, tăng ca)
  ├── Register (nghỉ phép, tạm ứng, biến động)
  └── Config (tỷ lệ BHXH, biểu thuế, ngưỡng)
      ↓
Payroll Engine:
  ├── Tính Gross:
  │   ├── Lương cơ bản = (Lương Gross / 26) × Công thực tế
  │   ├── Tăng ca = Giờ tăng ca × Hệ số × Lương giờ
  │   ├── Phụ cấp = Theo cấu hình
  │   ├── Hoa hồng = Theo doanh số
  │   └── Thưởng = Theo quyết định
  │
  ├── Tính BHXH/BHYT/BHTN:
  │   ├── Lương đóng BHXH = min(Gross, Trần)
  │   ├── BHTN: min(Gross, Trần vùng)
  │   ├── NLĐ: 10.5% × lương đóng
  │   └── DN: 23.5% × lương đóng
  │
  ├── Tính TNTT:
  │   ├── TNCT = Gross - Miễn thuế
  │   ├── Giảm trừ = 15.5tr + 6.2tr × NPT + BHXH
  │   └── TNTT = max(0, TNCT - Giảm trừ)
  │
  ├── Tính thuế TNCN (biểu 5 bậc)
  │
  └── Tính Net:
      ├── Net = Gross - BHXH - Thuế TNCN - Tạm ứng - Khác
      └── BLOCK nếu Net < 0
      ↓
Output:
  ├── Bảng lương (từng nhân viên)
  ├── Bảng tổng hợp (phòng ban, chi nhánh)
  ├── Bút toán kế toán
  ├── File chuyển khoản
  └── Payslip
```

### 6.4 Insurance Data Flow

```
Bảng lương đã duyệt
  → Tính lương đóng BHXH (min(Gross, Trần))
    → Tách: Phần NLĐ (10.5%) + Phần DN (21.5%)
      → Tổng hợp theo mã BHXH
        → Sinh file kê khai BHXH (D02-LT, D01-TS, TK3-TS)
          → Nộp cơ quan BHXH (online/trực tiếp)
            → Đối chiếu hàng tháng
```

### 6.5 PIT Declaration Flow

```
Bảng lương đã duyệt
  → Tính TNTT từng nhân viên
    → Tính thuế TNCN
      → Khấu trừ khi trả lương (Nợ 334 / Có 3335)
        → Kê khai thuế TNCN (05/KK-TNCN) hàng tháng/quý
          → Nộp thuế (trước ngày 20 tháng sau)
            → Cuối năm: quyết toán thuế TNCN
              → Cấp chứng từ khấu trừ cho nhân viên
```

### 6.6 Payroll Posting Flow

```
Bảng lương đã duyệt
  → Sinh bút toán tự động:
    ┌──────────────────────────────────────────────────┐
    │ 1. Ghi nhận lương phải trả:                     │
    │    Nợ 622 (SX) / 627 (SXC) / 641 (BH) / 642 (QL)│
    │    Có 334 (Phải trả NLĐ)                         │
    │                                                  │
    │ 2. Trích BHXH/BHYT/BHTN/KPCĐ vào chi phí:       │
    │    Nợ 622/627/641/642 (23.5% lương đóng BHXH)   │
    │    Có 3383 (BHXH)                                │
    │    Có 3384 (BHYT)                                │
    │    Có 3386 (BHTN)                                │
    │    Có 3382 (KPCĐ)                                │
    │                                                  │
    │ 3. Khấu trừ BHXH vào lương NLĐ:                 │
    │    Nợ 334                                       │
    │    Có 3383 (8%) / 3384 (1.5%) / 3386 (1%)       │
    │                                                  │
    │ 4. Khấu trừ thuế TNCN:                          │
    │    Nợ 334                                       │
    │    Có 3335 (Thuế TNCN)                           │
    │                                                  │
    │ 5. Chi lương (chuyển khoản):                    │
    │    Nợ 334                                       │
    │    Có 112 (Tiền gửi NH)                          │
    │                                                  │
    │ 6. Nộp BHXH:                                     │
    │    Nợ 3383 / 3384 / 3386 / 3382                  │
    │    Có 112                                       │
    │                                                  │
    │ 7. Nộp thuế TNCN:                                │
    │    Nợ 3335                                      │
    │    Có 112                                       │
    └──────────────────────────────────────────────────┘
```

### 6.7 Adjustment Flow

```
Phát hiện sai sót (kỳ trước đã chốt)
  → Lập chứng từ điều chỉnh (biên bản điều chỉnh lương)
    → Duyệt (Kế toán trưởng / Giám đốc)
      → Hạch toán bổ sung kỳ hiện tại:
        Nợ/Có 334 (chênh lệch)
        Nợ/Có 622/627/641/642 (chênh lệch)
        Nợ/Có 3383/3384/3386 (chênh lệch BHXH)
        Nợ/Có 3335 (chênh lệch thuế)
      → Audit trail: ghi rõ kỳ gốc, lý do, số tiền
```

### 6.8 Final Settlement Flow

```
Nhân viên nghỉ việc
  → Tính lương đến ngày nghỉ (prorated)
    → Tính phép năm còn lại (nếu có)
      → Tính trợ cấp thôi việc:
        1. Xác định thời gian làm việc thực tế
        2. Trừ thời gian đã đóng BHTN
        3. Trừ thời gian đã nhận trợ cấp trước đó
        4. Trợ cấp = (Số năm × 0.5) × Lương BQ 6 tháng
      → Tổng thanh toán lần cuối
        → Chi trả
          → Chốt sổ BHXH (gửi cơ quan BHXH)
            → Cập nhật trạng thái nhân viên
```

### 6.9 Reporting Flow

```
Dữ liệu lương
  → Báo cáo nội bộ:
    ├── Bảng lương chi tiết theo phòng ban
    ├── Bảng tổng hợp lương toàn công ty
    ├── Báo cáo chi phí lương theo bộ phận
    ├── Báo cáo biến động nhân sự
    └── Báo cáo dự toán lương

  → Báo cáo thuế/BHXH:
    ├── Tờ khai thuế TNCN (05/KK-TNCN)
    ├── Danh sách lao động tham gia BHXH (D02-LT)
    ├── Danh sách điều chỉnh BHXH (D01-TS)
    └── Báo cáo tình hình sử dụng lao động

  → Báo cáo kế toán:
    ├── Sổ cái TK 334 (Phải trả NLĐ)
    ├── Sổ cái TK 338 (Phải trả khác)
    ├── Sổ cái TK 3335 (Thuế TNCN)
    └── Bảng phân bổ chi phí lương
```

---

## 7. Payroll Workflow and User Journey

### 7.1 HR Officer Workflow

```
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│ Quản lý  │───→│ Theo dõi│───→│ Tổng hợp │───→│ Bàn giao │
│ hồ sơ    │    │ chấm     │    │ bảng     │    │ cho Kế   │
│ nhân sự  │    │ công     │    │ lương    │    │ toán     │
└──────────┘    └──────────┘    └──────────┘    └──────────┘
```

**HR Officer công việc hàng ngày:**
- Nhập hồ sơ nhân viên mới
- Cập nhật biến động (tăng lương, chuyển phòng, nghỉ việc)
- Duyệt đơn nghỉ phép, tăng ca
- Xử lý quên chấm công

**HR Officer công việc cuối tháng:**
- Xuất báo cáo chấm công
- Gửi trưởng bộ phận duyệt
- Nhập tăng ca, nghỉ bù
- Tổng hợp bảng lương draft

### 7.2 Payroll Accountant Workflow

```
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│ Nhận     │───→│ Chạy     │───→│ Kiểm tra │───→│ Trình    │
│ dữ liệu  │    │ payroll  │    │ bảng     │    │ duyệt    │
│ từ HR    │    │ engine   │    │ lương    │    │          │
└──────────┘    └──────────┘    └──────────┘    └──────────┘
```

**Thao tác:**
1. Import dữ liệu chấm công đã duyệt
2. Kiểm tra cấu hình (tỷ lệ BHXH, biểu thuế)
3. Chạy payroll engine
4. Kiểm tra kết quả (so sánh tháng trước, phát hiện bất thường)
5. Điều chỉnh nếu sai
6. Trình duyệt

### 7.3 Department Manager Workflow

```
┌──────────┐    ┌──────────┐    ┌──────────┐
│ Duyệt    │───→│ Xác nhận │───→│ Phê duyệt│
│ chấm     │    │ tăng ca  │    │ điều     │
│ công     │    │ /nghỉ    │    │ chỉnh    │
└──────────┘    └──────────┘    └──────────┘
```

**Thao tác:**
1. Đầu tháng: duyệt chấm công của nhân viên trong phòng
2. Xác nhận tăng ca, nghỉ phép
3. Xác nhận KPI, hoa hồng

### 7.4 Finance Controller Workflow

```
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│ Kiểm tra │───→│ Duyệt    │───→│ Hạch toán│───→│ Báo cáo  │
│ bảng     │    │ bút toán │    │ lương    │    │ tài      │
│ lương    │    │          │    │          │    │ chính    │
└──────────┘    └──────────┘    └──────────┘    └──────────┘
```

**Thao tác:**
1. Kiểm tra bảng lương tổng thể
2. So sánh dự toán chi phí lương
3. Duyệt bút toán lương
4. Kiểm tra số dư 334, 338, 3335 cuối kỳ

### 7.5 Employee Self-Service Journey

```
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│ Xem      │───→│ Gửi đơn  │───→│ Xem      │───→│ Xác nhận │
│ lịch sử  │    │ nghỉ     │    │ bảng     │    │ đã nhận  │
│ chấm     │    │ phép     │    │ lương    │    │ lương    │
│ công     │    │ /tăng ca │    │ (payslip)│    │          │
└──────────┘    └──────────┘    └──────────┘    └──────────┘
```

### 7.6 Approval Journey

```
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│ HR tạo   │───→│ TP duyệt │───→│ KT duyệt │───→│ GĐ duyệt │
│ draft    │    │ chấm     │    │ lương    │    │ chi      │
│          │    │ công     │    │          │    │          │
└──────────┘    └──────────┘    └──────────┘    └──────────┘
```

### 7.7 Exception Handling Journey

```
Phát hiện lỗi → Thông báo cho HR/Kế toán
  → Nếu trong giờ hành chính: sửa ngay
    → Nếu sắp đến hạn chi: ưu tiên xử lý
      → Nếu đã chi: lập điều chỉnh kỳ sau
```

### 7.8 Month-End Payroll Journey

```
Ngày 20: Chốt chấm công
Ngày 21-22: HR tổng hợp + trình duyệt
Ngày 22-23: Kế toán chạy payroll + trình duyệt
Ngày 24-25: Duyệt lương (Kế toán trưởng + Giám đốc)
Ngày 25-26: Sinh file chuyển khoản + duyệt NH
Ngày 26-27: Lương về tài khoản nhân viên
Ngày 28-30: Hạch toán, kê khai BHXH, kê khai thuế
```

### 7.9 Resignation Handling Journey

```
Nhân viên nộp đơn → HR nhận → Xác định thời gian báo trước
  → Nhân viên làm việc nốt thời gian báo trước
    → Ngày cuối: HR làm quyết toán
      → Kế toán tính lần cuối + trợ cấp
        → Thanh toán + chốt sổ BHXH
          → Cập nhật trạng thái
```

---

## 8. SME Payroll Pain Analysis

### 8.1 Excel Payroll Chaos

| Vấn đề | Mô tả | Tần suất | Hậu quả |
|---|---|---|---|
| Sai công thức | Kéo ô sai, lỗi VLOOKUP, thiếu hàng | 30–50% tháng | Sai lương |
| Version control | Nhiều file Excel, không biết file nào đúng | Hàng tháng | Sai số liệu |
| Không audit | Ai sửa? Sửa gì? Khi nào? | Liên tục | Mất kiểm soát |
| Chậm | 3–5 ngày/tháng xử lý Excel | Liên tục | Chậm lương |
| Lỗi tổng hợp | Cộng sai, thiếu người | Thường xuyên | Sai báo cáo |

### 8.2 Wrong Attendance

| Vấn đề | Mô tả | Hậu quả |
|---|---|---|
| Quên chấm công | Nhân viên quên check-in/out | Mất công, khiếu nại |
| Chấm công hộ | Nhân viên nhờ người khác chấm hộ | Gian lận công |
| Nhầm ca | Ghi sai ca làm việc | Sai lương tăng ca |
| Thiết bị hỏng | Máy chấm công hỏng, mất dữ liệu | Không có cơ sở tính lương |

### 8.3 Wrong Overtime

| Vấn đề | Mô tả | Hậu quả |
|---|---|---|
| Không đăng ký trước | Tăng ca không được duyệt | Trả lương sai |
| Sai hệ số | Áp dụng sai hệ số (thường → lễ) | Trả thiếu/trả thừa |
| Vượt giới hạn | Không kiểm soát 200h/năm | Phạt 5–50tr |
| Tính thủ công | Excel tính tăng ca sai | Nhân viên khiếu nại |

### 8.4 Missing Insurance

| Vấn đề | Mô tả | Hậu quả |
|---|---|---|
| Không đăng ký kịp | Quên đăng ký BHXH cho NV mới | Phạt, nhân viên thiệt thòi |
| Sai lương đóng BHXH | Không cập nhật lương mới | Phạt, truy thu |
| Sai mã NV | Trùng mã BHXH | Không tra cứu được |
| Chậm nộp | Nộp BHXH sau hạn | Lãi chậm đóng (0.03%/ngày) |
| Quên điều chỉnh | Khi lương thay đổi, quên điều chỉnh BHXH | Phạt, truy thu |

### 8.5 Wrong PIT Calculation

| Vấn đề | Mô tả | Hậu quả |
|---|---|---|
| Sai biểu thuế | Dùng biểu 7 bậc thay vì 5 bậc (2026) | Tính sai thuế |
| Sai giảm trừ | Không cập nhật 15.5tr/6.2tr | Nộp thừa/thiếu |
| Thiếu NPT | Nhân viên có NPT nhưng không khai báo | Nộp thừa thuế |
| Sai lương đóng BHXH | BHXH tính sai → TNTT sai | Tính thuế sai |
| Quên quyết toán | Cuối năm không quyết toán TNCN | Phạt chậm |

### 8.6 Late Payroll Processing

| Vấn đề | Mô tả | Hậu quả |
|---|---|---|
| Chậm chấm công | Trưởng bộ phận duyệt chậm | Chậm toàn bộ quy trình |
| Chậm tính lương | Kế toán quá tải | Nhân viên không nhận lương đúng hạn |
| Chậm duyệt | Giám đốc đi công tác | Chi lương muộn |
| Lỗi NH | File chuyển khoản sai format | Chuyển tiền thất bại |

### 8.7 Salary Leakage — Thất thoát lương

| Vấn đề | Mô tả | Thiệt hại ước tính |
|---|---|---|
| Nhân viên ảo | Có tên trong payroll nhưng không đi làm | 5–15% quỹ lương |
| Tăng ca ảo | Khai tăng ca không có thật | 5–10% quỹ tăng ca |
| Sai phụ cấp | Tính thừa phụ cấp | 2–5% quỹ lương |
| Chi trùng | Chuyển khoản 2 lần cho 1 người | Tốn thời gian thu hồi |

### 8.8 Duplicate Payment

| Vấn đề | Mô tả | Hậu quả |
|---|---|---|
| Chi lương 2 lần | Chuyển khoản trùng | Mất tiền, khó đòi lại |
| Chi sai người | Chuyển nhầm tài khoản | Mất tiền |
| Chi người đã nghỉ | Vẫn chuyển lương cho NV đã nghỉ | Mất tiền, khó đòi |

### 8.9 Weak Approval Control

| Vấn đề | Mô tả | Hậu quả |
|---|---|---|
| Một người làm hết | HR vừa nhập, vừa duyệt, vừa chi | Gian lận, mất kiểm soát |
| Không phân quyền | Ai cũng sửa được lương | Sai lương, mất audit |
| Duyệt muộn | TP duyệt chấm công sau khi đã chi | Không kiểm soát được |

### 8.10 Weak Audit Trail

| Vấn đề | Mô tả | Hậu quả |
|---|---|---|
| Không lưu lịch sử | Không biết ai sửa, sửa gì | Không kiểm toán được |
| Không backup | Mất dữ liệu → không có cơ sở trả lương | Kiện tụng |
| Thanh tra không đáp ứng | Không xuất được báo cáo | Phạt, đình chỉ |

### 8.11 HR vs Accounting Mismatch

| Vấn đề | Mô tả | Hậu quả |
|---|---|---|
| HR tính một kiểu | HR tính lương Gross khác với kế toán | Sai số liệu, mất thời gian đối chiếu |
| Kế toán tính một kiểu | Kế toán tự nhập lại từ đầu | Tốn gấp đôi công |
| Không đồng bộ | HR sửa thông tin nhưng kế toán không biết | Sai BHXH, sai lương |

### 8.12 Multi-branch Inconsistency

| Vấn đề | Mô tả | Hậu quả |
|---|---|---|
| Mỗi chi nhánh một kiểu | Cách tính lương khác nhau | Không thống nhất |
| Lương tối thiểu vùng khác | Chi nhánh vùng I vs vùng IV | Sai lương đóng BHXH |
| HR phân tán | Mỗi chi nhánh có HR riêng | Khó quản lý tập trung |
| Báo cáo không đồng nhất | Consolidate báo cáo lương khó | Mất thời gian hợp nhất |

---

## 9. Hạch toán Kế toán Tiền lương

### 9.1 Tài khoản sử dụng

| TK | Tên | Ghi chú |
|---|---|---|
| 334 | Phải trả người lao động | Có số dư Có (hoặc Nợ nếu trả thừa) |
| 3341 | Phải trả tiền lương | Chi tiết lương chính |
| 3348 | Phải trả các khoản khác | Thưởng, phúc lợi |
| 3382 | Kinh phí công đoàn | 2% quỹ lương đóng BHXH |
| 3383 | Bảo hiểm xã hội | 25.5% (DN 17.5% + NLĐ 8%) |
| 3384 | Bảo hiểm y tế | 4.5% (DN 3% + NLĐ 1.5%) |
| 3386 | Bảo hiểm thất nghiệp | 2% (DN 1% + NLĐ 1%) |
| 3335 | Thuế TNCN | Khấu trừ từ lương NLĐ |
| 622 | Chi phí nhân công trực tiếp | SX |
| 627 | Chi phí sản xuất chung | SXC |
| 641 | Chi phí bán hàng | BH |
| 642 | Chi phí quản lý doanh nghiệp | QLDN |
| 241 | XDCB dở dang | Công trình |

### 9.2 Các bút toán chuẩn

#### 9.2.1 Tính lương phải trả

```
Nợ 622 — Chi phí nhân công trực tiếp (sản xuất)
Nợ 627 — Chi phí sản xuất chung (quản đốc, nhân viên PXSX)
Nợ 641 — Chi phí bán hàng (nhân viên bán hàng)
Nợ 642 — Chi phí quản lý doanh nghiệp (văn phòng)
Nợ 241 — XDCB dở dang (công trình)
   Có 334 — Phải trả người lao động (tổng lương Gross)
```

#### 9.2.2 Trích BHXH/BHYT/BHTN/KPCĐ vào chi phí DN

```
Nợ 622 / 627 / 641 / 642 — (21.5% + 2%) = 23.5% lương đóng BHXH
   Có 3383 — BHXH (17.5%)
   Có 3384 — BHYT (3%)
   Có 3386 — BHTN (1%)
   Có 3382 — KPCĐ (2%)
```

#### 9.2.3 Khấu trừ BHXH vào lương NLĐ

```
Nợ 334 — Phải trả NLĐ (10.5% lương đóng BHXH)
   Có 3383 — BHXH (8%)
   Có 3384 — BHYT (1.5%)
   Có 3386 — BHTN (1%)
```

#### 9.2.4 Khấu trừ thuế TNCN

```
Nợ 334 — Phải trả NLĐ (thuế TNCN phải nộp)
   Có 3335 — Thuế TNCN
```

#### 9.2.5 Chi lương (chuyển khoản)

```
Nợ 334 — Phải trả NLĐ (số thực nhận = Net)
   Có 112 — Tiền gửi ngân hàng
```

#### 9.2.6 Chi lương (tiền mặt)

```
Nợ 334 — Phải trả NLĐ
   Có 111 — Tiền mặt
```

#### 9.2.7 Nộp BHXH/BHYT/BHTN/KPCĐ

```
Nợ 3383 — BHXH (25.5% lương đóng BHXH)
Nợ 3384 — BHYT (4.5%)
Nợ 3386 — BHTN (2%)
Nợ 3382 — KPCĐ (2%)
   Có 112 — Tiền gửi ngân hàng
```

#### 9.2.8 Nộp thuế TNCN

```
Nợ 3335 — Thuế TNCN
   Có 112 — Tiền gửi ngân hàng
```

#### 9.2.9 Tạm ứng lương

```
Nợ 334 — Phải trả NLĐ
   Có 111 / 112 — Tiền mặt / Tiền gửi NH
```

#### 9.2.10 Nhận tiền BHXH thai sản/ốm đau

```
Nợ 111 / 112 — Tiền nhận từ cơ quan BHXH
   Có 3383 — BHXH

Khi chi trả cho NLĐ:
Nợ 3383 — BHXH
   Có 334 — Phải trả NLĐ

Nợ 334 — Phải trả NLĐ
   Có 111 / 112 — Tiền mặt / NH
```

#### 9.2.11 Trích trước lương nghỉ phép

```
Nợ 622 / 627 / 641 / 642
   Có 335 — Chi phí phải trả

Khi thực tế nghỉ phép:
Nợ 335 — Chi phí phải trả
   Có 334 — Phải trả NLĐ
```

### 9.3 Ví dụ hạch toán hoàn chỉnh

**Nhân viên A: Lương Gross 20,000,000, lương đóng BHXH 20,000,000, 1 NPT, Vùng I**

```
Bước 1 — Tính lương:
  Nợ 642: 20,000,000
    Có 334: 20,000,000

Bước 2 — Trích BHXH DN:
  Nợ 642: 20,000,000 × 21.5% = 4,300,000
  Nợ 642: 20,000,000 × 2% = 400,000 (KPCĐ)
    Có 3383: 20,000,000 × 17.5% = 3,500,000
    Có 3384: 20,000,000 × 3% = 600,000
    Có 3386: 20,000,000 × 1% = 200,000
    Có 3382: 20,000,000 × 2% = 400,000

Bước 3 — Khấu trừ BHXH NLĐ:
  Nợ 334: 20,000,000 × 10.5% = 2,100,000
    Có 3383: 20,000,000 × 8% = 1,600,000
    Có 3384: 20,000,000 × 1.5% = 300,000
    Có 3386: 20,000,000 × 1% = 200,000

Bước 4 — Tính thuế TNCN:
  TNTT = 20,000,000 - 2,100,000 - 15,500,000 - 6,200,000 = -3,800,000
  TNTT ≤ 0 → không phải nộp thuế

Bước 5 — Chi lương:
  Nợ 334: 20,000,000 - 2,100,000 = 17,900,000
    Có 112: 17,900,000

Bước 6 — Nộp BHXH:
  Nợ 3383: 3,500,000 + 1,600,000 = 5,100,000
  Nợ 3384: 600,000 + 300,000 = 900,000
  Nợ 3386: 200,000 + 200,000 = 400,000
  Nợ 3382: 400,000
    Có 112: 6,800,000
```

---

> **Tuyên bố:** Tài liệu này được xây dựng dựa trên các quy định pháp luật Việt Nam cập nhật đến tháng 05/2026. Các tỷ lệ BHXH, biểu thuế TNCN, mức giảm trừ gia cảnh và lương tối thiểu vùng được căn cứ theo Luật BHXH 2024, Luật Thuế TNCN 2025, Luật Việc làm 2025, Nghị định 293/2025/NĐ-CP và các văn bản hướng dẫn liên quan. Doanh nghiệp cần cập nhật khi có thay đổi về chính sách.
