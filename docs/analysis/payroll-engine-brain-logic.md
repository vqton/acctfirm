# Phân tích Hệ thống Tiền lương — BA Lead & Chief Accountant View

> **Phiên bản:** 2.0  
> **Phạm vi:** Toàn bộ nghiệp vụ kế toán tiền lương doanh nghiệp Việt Nam  
> **Đối tượng:** Ban Giám đốc, Kế toán trưởng, BA Lead, Developers, Auditors  
> **Cập nhật luật:** 05/2026 — Luật BHXH 2024, Luật Thuế TNCN 2025, Nghị định 158/2025, Nghị định 105/2026, Nghị định 293/2025, Nghị quyết 110/2025/UBTVQH15

---

## Mục lục

1. [Executive BA Analysis](#1-executive-ba-analysis)
2. [Scope Definition](#2-scope-definition)
3. [Payroll Functional Spec](#3-payroll-functional-spec)
4. [Full Payroll Lifecycle](#4-full-payroll-lifecycle)
5. [Use Cases](#5-use-cases)
6. [Payroll Calculation Engine](#6-payroll-calculation-engine)
7. [Workflow & Process](#7-workflow--process)
8. [Data Flow](#8-data-flow)
9. [User Journeys](#9-user-journeys)
10. [Validation & Internal Control](#10-validation--internal-control)
11. [Reporting & Reconciliation](#11-reporting--reconciliation)
12. [SME Pain Analysis](#12-sme-pain-analysis)
13. [Functional Rules Matrix](#13-functional-rules-matrix)
14. [Final Deliverables](#14-final-deliverables)

---

## 1. Executive BA Analysis

### 1.1 Business Context

Tiền lương là nghiệp vụ kế toán **phức tạp nhất và có tần suất xảy ra rủi ro cao nhất** trong doanh nghiệp SME Việt Nam. Đây là module duy nhất liên quan đến **3 cơ quan quản lý nhà nước** (Thuế, BHXH, Lao động) với **7+ loại báo cáo định kỳ** và **hơn 20 quy định pháp luật** phải tuân thủ đồng thời.

**Vì sao Payroll là ưu tiên số 1 cho phase tiếp theo:**

| Yếu tố | Phân tích |
|---|---|
| **Tần suất** | Hàng tháng, không thể trễ. Chậm lương = đình công, kiện tụng |
| **Độ phức tạp** | 7+ bộ luật chồng chéo (LĐ, BHXH, TNCN, VL, CĐ, KT, TC) |
| **Rủi ro tài chính** | Phạt BHXH đến 75tr, phạt thuế đến 50tr, phạt LĐ đến 50tr |
| **Rủi ro pháp lý** | Nhân viên kiện tụng, thanh tra BHXH, thanh tra thuế, thanh tra LĐ |
| **Khối lượng** | 20–200 nhân viên, 20–50 dòng bút toán/tháng, 3–5 báo cáo/tháng |
| **Hiện trạng** | 100% Excel — không audit, không version control, dễ sai |
| **Giá trị** | Tiết kiệm 3–5 ngày công/tháng cho kế toán, giảm 90% lỗi |

### 1.2 Hiện trạng hệ thống (As-Is)

**Quy trình hiện tại của SME Việt Nam:**

```
Excel từ HR
  ↓
Kế toán nhập lại vào Excel
  ↓
Công thức tính BHXH (thủ công)
  ↓
Công thức tính TNCN (tra bảng tay)
  ↓
Bảng lương Excel (file riêng từng tháng)
  ↓
Gửi giám đốc duyệt (in ra ký tay)
  ↓
Nhập lại vào Internet Banking
  ↓
Hạch toán tay vào PM kế toán
```

**Các vấn đề nghiêm trọng:**
- **30–50% tháng có sai lỗi** công thức Excel (kéo ô sai, VLOOKUP lỗi)
- **Không có audit trail** — không biết ai sửa, sửa gì, khi nào
- **Mất 3–5 ngày/tháng** xử lý payroll bằng Excel
- **Phiền hà cho nhân viên** — không có payslip, không tự tra cứu được
- **Rủi ro pháp lý** — không xuất được báo cáo khi thanh tra

### 1.3 Business Case

| Khoản mục | SME 50 NV | SME 200 NV |
|---|---|---|
| **Chi phí hiện tại** | | |
| Thời gian HR + Kế toán (ngày/tháng) | 5 ngày | 12 ngày |
| Chi phí nhân công ước tính (đ/tháng) | 4,000,000 | 9,600,000 |
| Rủi ro phạt BHXH/thuế (TB/năm) | 10,000,000 | 30,000,000 |
| **Tổng chi phí/năm** | **~58,000,000** | **~145,000,000** |
| | | |
| **Sau khi có hệ thống** | | |
| Thời gian xử lý (ngày/tháng) | 1 ngày | 2 ngày |
| Chi phí nhân công ước tính (đ/tháng) | 800,000 | 1,600,000 |
| Rủi ro phạt (giảm 90%) | 1,000,000 | 3,000,000 |
| **Tổng chi phí/năm** | **~10,600,000** | **~22,200,000** |
| | | |
| **Tiết kiệm/năm** | **~47,400,000** | **~122,800,000** |
| **ROI (năm đầu, sau chi phí dev)** | **~300%** | **~400%** |

### 1.4 Key Stakeholders

| Stakeholder | Vai trò | Quan tâm |
|---|---|---|
| **Giám đốc** | Duyệt chi lương | Chi phí lương, kiểm soát, đúng hạn |
| **Kế toán trưởng** | Duyệt bảng lương, kiểm soát nội bộ | Dr = Cr, audit trail, đúng luật |
| **Kế toán lương** | Tính lương, hạch toán | Nhanh, chính xác, tự động |
| **Trưởng bộ phận** | Duyệt chấm công | Đúng ngày công của team |
| **HR** | Nhập liệu, quản lý hồ sơ | Dễ nhập, dễ tra cứu |
| **Nhân viên** | Nhận lương, xem payslip | Đúng số, đúng hạn, minh bạch |
| **Cơ quan Thuế** | Nhận kê khai TNCN | Đúng biểu, đúng hạn |
| **Cơ quan BHXH** | Nhận kê khai BHXH | Đúng tỷ lệ, đúng đối tượng |

### 1.5 Risk Assessment — Payroll Module

| Rủi ro | Xác suất | Tác động | Mức | Biện pháp |
|---|---|---|---|---|
| **Lương âm** (Net < 0) | Thấp | Cao | **CAO** | Block trước khi duyệt |
| **Sai tỷ lệ BHXH** | Trung bình | Cao | **CAO** | Auto tính từ config, test hàng tháng |
| **Sai biểu thuế TNCN** | Thấp | Cao | **CAO** | Config-driven, test mỗi đợt thay đổi luật |
| **Chi lương sai người** | Thấp | Rất cao | **CAO** | Xác nhận kép, check file NH |
| **Chi trùng lương** | Thấp | Cao | **TRUNG BÌNH** | Check unique (employee, period) |
| **Chậm lương** | Trung bình | Cao | **CAO** | Workflow tự động, nhắc nhở |
| **Nhân viên ảo** | Trung bình | Rất cao | **CAO** | Cross-check chấm công vs payroll |
| **Chốt sai kỳ** | Thấp | Rất cao | **CAO** | Period lock, audit trail |
| **Thanh tra phát hiện sai** | Thấp | Rất cao | **CAO** | Audit trail đầy đủ, báo cáo đúng |

---

## 2. Scope Definition

### 2.1 In Scope

| Module/Chức năng | Mô tả | Ưu tiên |
|---|---|---|
| **Employee Master** | Quản lý hồ sơ nhân viên (lương, phụ cấp, BHXH, NPT, TK NH) | P0 |
| **Cấu hình lương** | Lương tối thiểu vùng, tỷ lệ BHXH, biểu thuế, hệ số tăng ca | P0 |
| **Chấm công** | Nhập/Import ngày công, tăng ca, nghỉ phép, đi muộn | P0 |
| **Tính lương** | Gross → BHXH → TNTT → Thuế TNCN → Net | P0 |
| **Bảng lương** | Chi tiết từng NV + tổng hợp phòng ban | P0 |
| **Payslip** | Bảng lương cá nhân (PDF/email) | P0 |
| **Duyệt lương** | HR → Kế toán trưởng → Giám đốc | P0 |
| **Chi lương** | Sinh file chuyển khoản, ghi nhận chi TM | P0 |
| **Hạch toán tự động** | Sinh bút toán (334, 338, 3335, 622/627/641/642) | P0 |
| **Kê khai BHXH** | Tổng hợp số liệu, sinh file D02-LT | P1 |
| **Kê khai TNCN** | Tổng hợp, sinh 05/KK-TNCN | P1 |
| **Quyết toán TNCN** | Cuối năm, tính lại cả năm | P1 |
| **Quyết toán nghỉ việc** | Final settlement + trợ cấp thôi việc | P1 |
| **Báo cáo lương** | Chi phí lương theo bộ phận, biến động | P1 |
| **Tạm ứng lương** | Ghi nhận, trừ khi trả lương | P1 |
| **Điều chỉnh hồi tố** | Bút toán điều chỉnh kỳ sau | P2 |
| **Quản lý nghỉ phép** | Đăng ký, duyệt, tồn phép | P2 |
| **Bảo hiểm sức khỏe** | Quản lý gói BH sức khỏe (nếu có) | P2 |
| **Chấm công online** | Check-in/out qua mobile web | P2 |
| **Tích hợp NH** | Tự động import sao kê đối chiếu | P3 |

### 2.2 Out of Scope (Phase 1)

- Tuyển dụng (Applicant Tracking System)
- Đào tạo (Training Management)
- Đánh giá KPI / Performance Review
- Quỹ lương / Dự toán lương
- Tính lương theo sản phẩm phức tạp
- HR Analytics / Workforce Planning
- Mobile App (chỉ responsive web)
- Tích hợp với phần mềm chấm công vật lý (third-party)
- Tính lương cho lao động nước ngoài phức tạp (có hiệp định tránh đánh thuế kép)

### 2.3 Integration Boundaries

```
HR Module (hiện tại)
  ↓ (dữ liệu nhân viên, chấm công)
Payroll Engine (module mới)
  ↓ (bút toán lương)
→ JournalService (ghi nhận bút toán)
  → AccountRepository (tra cứu tài khoản)
  → PostingRuleService (kiểm tra Dr-Cr)
  → AuditLogger (ghi audit)
```

**Nguyên tắc biên:** Payroll module **KHÔNG ghi trực tiếp** vào transactions/ledger_entries. Mọi bút toán đều qua JournalService.

### 2.4 Assumptions & Constraints

| Assumption | Basis |
|---|---|
| Dữ liệu nhân viên có sẵn từ HR module | Module HR đã có |
| Mỗi nhân viên có 1 phòng ban + 1 chức vụ | Cấu trúc tổ chức đơn giản |
| Lương Gross là cố định trong tháng (trừ thử việc, nghỉ, tăng ca) | Đa số SME |
| Không hỗ trợ tính lương theo sản phẩm đa biến | Phase 1 |
| Tỷ lệ BHXH / biểu thuế được config = không hardcode | Yêu cầu kiến trúc |
| 26 ngày công chuẩn (có thể config) | Default SME |
| Người dùng có trình độ kế toán Việt Nam cơ bản | UI bằng tiếng Việt |

---

## 3. Payroll Functional Spec

### 3.1 Employee Master Management

**Yêu cầu chức năng:**

| ID | Chức năng | Mô tả | Bắt buộc |
|---|---|---|---|
| EM-01 | Thêm nhân viên | Nhập họ tên, CMND/CCCD, ngày sinh, giới tính | ✅ |
| EM-02 | Thông tin hợp đồng | Ngày vào, ngày ký HĐ, loại HĐ (TD/TV), thời hạn | ✅ |
| EM-03 | Thông tin lương | Lương Gross, lương đóng BHXH, phụ cấp (từng loại) | ✅ |
| EM-04 | Thông tin BHXH | Số BHXH, ngày tham gia, loại BH | ✅ |
| EM-05 | Thông tin thuế | MST cá nhân, số NPT (kèm MST từng NPT) | ✅ |
| EM-06 | Thông tin ngân hàng | Số TK, tên NH, chi nhánh | ✅ |
| EM-07 | Thông tin tổ chức | Phòng ban, chức vụ, chi nhánh, vùng lương | ✅ |
| EM-08 | Trạng thái | Đang làm, thử việc, nghỉ việc, tạm nghỉ | ✅ |
| EM-09 | Lịch sử thay đổi lương | Ghi lại mọi thay đổi lương + phụ cấp | ✅ |
| EM-10 | Import/Export Excel | Bulk update nhân viên | ✅ |

### 3.2 Attendance & Timekeeping

**Yêu cầu chức năng:**

| ID | Chức năng | Mô tả | Bắt buộc |
|---|---|---|---|
| AT-01 | Nhập chấm công | Import từ Excel hoặc nhập tay | ✅ |
| AT-02 | Ngày công chuẩn | Config số ngày công/tháng (mặc định 26) | ✅ |
| AT-03 | Ngày công thực tế | Tự động tính từ chấm công | ✅ |
| AT-04 | Tăng ca | Nhập số giờ tăng ca theo loại (thường, CN, lễ, đêm) | ✅ |
| AT-05 | Nghỉ phép | Nhập số ngày nghỉ phép hưởng lương | ✅ |
| AT-06 | Nghỉ không lương | Nhập số ngày nghỉ không lương | ✅ |
| AT-07 | Đi muộn / về sớm | Nhập số lần, tự động quy đổi | ✅ |
| AT-08 | Duyệt chấm công | Trưởng bộ phận duyệt | ✅ |
| AT-09 | Khóa chấm công | Sau khi duyệt = read-only | ✅ |

### 3.3 Salary Calculation

**Yêu cầu chức năng:**

| ID | Chức năng | Mô tả | Bắt buộc |
|---|---|---|---|
| SC-01 | Tính lương Gross | Basic + allowances + bonus + commission + overtime | ✅ |
| SC-02 | Tính BHXH/BHYT/BHTN | Tự động theo tỷ lệ config, áp trần/sàn | ✅ |
| SC-03 | Tính TNTT | Gross - miễn thuế - BHXH - giảm trừ | ✅ |
| SC-04 | Tính thuế TNCN | Tra biểu 5 bậc | ✅ |
| SC-05 | Tính lương Net | Gross - BHXH - TNCN - tạm ứng - khấu trừ | ✅ |
| SC-06 | Tính chi phí DN | Gross + BHXH DN + KPCĐ | ✅ |
| SC-07 | Draft mode | Tính thử, chưa duyệt | ✅ |
| SC-08 | Re-calculate | Tính lại nếu có thay đổi (trước duyệt) | ✅ |
| SC-09 | Validate trước duyệt | Tự động kiểm tra lỗi | ✅ |

### 3.4 Payroll Approval

**Yêu cầu chức năng:**

| ID | Chức năng | Mô tả | Bắt buộc |
|---|---|---|---|
| PA-01 | Duyệt chấm công | Trưởng bộ phận → Approved | ✅ |
| PA-02 | Duyệt bảng lương | Kế toán trưởng → Approved | ✅ |
| PA-03 | Duyệt chi | Giám đốc → Approved | ✅ |
| PA-04 | Từ chối + lý do | Gửi lại cho người tạo | ✅ |
| PA-05 | Audit trail duyệt | Ai duyệt, lúc nào, ghi chú gì | ✅ |
| PA-06 | Hủy duyệt (trước chi) | CFO/Giám đốc hủy, có audit | ✅ |
| PA-07 | Notify | Gửi thông báo khi đến lượt duyệt | ✅ |

### 3.5 Payment

**Yêu cầu chức năng:**

| ID | Chức năng | Mô tả | Bắt buộc |
|---|---|---|---|
| PY-01 | Chi chuyển khoản | Sinh file theo format NH | ✅ |
| PY-02 | Chi tiền mặt | Sinh phiếu chi | ✅ |
| PY-03 | Ghi nhận đã chi | Cập nhật trạng thái đã chi | ✅ |
| PY-04 | Xác nhận nhận lương | Nhân viên ký nhận (TM) hoặc xác nhận online | ✅ |
| PY-05 | Tạm ứng lương | Ghi nhận, tự động trừ khi trả | ✅ |
| PY-06 | Đối chiếu sao kê | Import sao kê NH, đối chiếu | P2 |

### 3.6 Accounting Posting

**Yêu cầu chức năng:**

| ID | Chức năng | Mô tả | Bắt buộc |
|---|---|---|---|
| AP-01 | Tự động sinh bút toán | Khi duyệt lương xong | ✅ |
| AP-02 | Phân bổ chi phí | Theo phòng ban → 622/627/641/642 | ✅ |
| AP-03 | Ghi nhận BHXH DN | 3383/3384/3386/3382 | ✅ |
| AP-04 | Ghi nhận khấu trừ NLĐ | 334 → 3383/3384/3386 | ✅ |
| AP-05 | Ghi nhận thuế TNCN | 334 → 3335 | ✅ |
| AP-06 | Bút toán điều chỉnh | Adjustment entries | ✅ |

### 3.7 Reporting

**Yêu cầu chức năng:**

| ID | Chức năng | Mô tả | Bắt buộc |
|---|---|---|---|
| RP-01 | Bảng lương chi tiết | Theo nhân viên, tháng | ✅ |
| RP-02 | Bảng tổng hợp lương | Theo phòng ban, toàn công ty | ✅ |
| RP-03 | Báo cáo chi phí lương | 622/627/641/642 | ✅ |
| RP-04 | Báo cáo BHXH | Tổng hợp đóng BHXH | ✅ |
| RP-05 | Báo cáo TNCN | Tổng hợp thuế TNCN | ✅ |
| RP-06 | Báo cáo biến động | Nhân viên mới/nghỉ/thay đổi lương | ✅ |
| RP-07 | Export Excel | Mọi báo cáo đều export được | ✅ |
| RP-08 | Payslip PDF | Individual employee | ✅ |

### 3.8 System Config

**Yêu cầu chức năng:**

| ID | Chức năng | Mô tả | Bắt buộc |
|---|---|---|---|
| CF-01 | Tỷ lệ BHXH | 8/17.5/1.5/3/1/1/2% (có thể thay đổi) | ✅ |
| CF-02 | Trần BHXH | 20 × lương cơ sở | ✅ |
| CF-03 | Trần BHTN theo vùng | 20 × lương tối thiểu vùng | ✅ |
| CF-04 | Lương tối thiểu vùng | Vùng I/II/III/IV | ✅ |
| CF-05 | Biểu thuế TNCN | 5 bậc (2026), có thể thay đổi | ✅ |
| CF-06 | Giảm trừ gia cảnh | Bản thân, NPT | ✅ |
| CF-07 | Hệ số tăng ca | Thường/CN/Lễ/Đêm | ✅ |
| CF-08 | Ngày công chuẩn | 24–27 ngày | ✅ |
| CF-09 | Kỳ lương | Tháng hiện tại, có thể chọn kỳ | ✅ |

---

## 4. Full Payroll Lifecycle

### 4.1 Employee Lifecycle Map

```
┌─────────────────────────────────────────────────────────────────────┐
│                    EMPLOYEE LIFECYCLE — PAYROLL VIEW                │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  TUYỂN DỤNG → THỬ VIỆC → CHÍNH THỨC → BIẾN ĐỘNG → NGHỈ VIỆC      │
│     (ngày 0)   (≤60 ngày)  (HĐLĐ)      (bất kỳ)   (final date)    │
│                                                                      │
│  Mỗi giai đoạn = thay đổi payroll config:                           │
│  - Lương Gross (≥85% thử việc → 100% chính thức)                   │
│  - BHXH (thử việc <1 tháng=không, ≥1 tháng=có)                    │
│  - Phụ cấp (theo chức vụ mới)                                       │
│  - Người phụ thuộc (đăng ký MST)                                    │
│  - Tài khoản NH (xác thực)                                          │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### 4.2 Monthly Payroll Cycle

```
NGÀY 1-5:   Thu thập dữ liệu
            ├── Chấm công thực tế (check-in/out)
            ├── Tăng ca (đã đăng ký + duyệt)
            ├── Nghỉ phép, nghỉ ốm, nghỉ không lương
            └── Biến động: vào, ra, tăng lương, chuyển phòng

NGÀY 5-12:  Xử lý dữ liệu
            ├── HR tổng hợp chấm công
            ├── Trưởng bộ phận duyệt chấm công
            ├── Nhập điều chỉnh (quên chấm, bổ sung tăng ca)
            └── Duyệt điều chỉnh

NGÀY 12-20: Tính lương
            ├── Chạy payroll engine (draft)
            ├── Kế toán lương kiểm tra
            ├── Điều chỉnh nếu sai
            ├── Kế toán trưởng duyệt bảng lương
            └── CHỐT LƯƠNG

NGÀY 20-25: Chi lương
            ├── Sinh bảng lương chính thức
            ├── Sinh file chuyển khoản / phiếu chi TM
            ├── Giám đốc duyệt chi
            ├── Thực hiện chi
            ├── Gửi payslip cho nhân viên
            └── Hạch toán lương (tự động)

NGÀY 25-30: Báo cáo + Kê khai
            ├── Hạch toán BHXH + thuế TNCN
            ├── Kê khai BHXH (D02-LT)
            ├── Kê khai thuế TNCN (05/KK-TNCN)
            ├── Nộp BHXH (trước ngày 30)
            └── Nộp thuế TNCN (trước ngày 20 tháng sau)

NGÀY 30+:   Hoàn tất kỳ
            ├── Chốt kỳ lương (read-only)
            ├── Lưu audit trail
            └── Backup dữ liệu
```

### 4.3 Payroll Events & Triggers

| Event | Trigger | Hành động | Kỳ xử lý |
|---|---|---|---|
| Nhân viên mới | Ngày bắt đầu làm | Thêm master, config lương | Kỳ hiện tại |
| Thay đổi lương | Quyết định tăng lương | Cập nhật master, hiệu lực từ ngày X | Kỳ hiện tại (hoặc hồi tố) |
| Thử việc → chính thức | Hết thời gian thử việc | Cập nhật lương Gross, BHXH | Kỳ kế tiếp |
| Nhân viên nghỉ việc | Ngày cuối cùng | Final settlement, chốt BHXH | Ngay lập tức |
| Nghỉ thai sản | Ngày bắt đầu nghỉ | Ngừng lương, chờ BHXH | Trong thời gian nghỉ |
| Tạm ứng lương | Yêu cầu của NV | Ghi nhận, trừ khi trả lương | Kỳ hiện tại |
| Sai lương phát hiện sau chốt | Audit/Khiếu nại | Bút toán điều chỉnh kỳ sau | Kỳ kế tiếp |
| Thanh tra | Yêu cầu từ cơ quan | Xuất báo cáo lịch sử | Không giới hạn |

### 4.4 Period State Machine

```
┌─────────┐    Duyệt chấm công    ┌──────────┐    Duyệt lương    ┌─────────┐
│ PENDING │──────────────────────→│ APPROVED │──────────────────→│ CLOSED  │
│ (draft) │                       │ (locked) │                    │ (done)  │
└─────────┘                       └──────────┘                    └─────────┘
     ↑                                │                              │
     │                                │ Hủy duyệt                    │ Mở lại
     └────────────────────────────────┘                              │
         (sửa chấm công)                                            ↓
                                                               ┌─────────┐
                                                               │ REOPEN  │
                                                               │ (audit) │
                                                               └─────────┘
```

**Rule:** Chỉ CFO/Giám đốc mới mở lại kỳ đã đóng. Mỗi lần mở lại = audit trail bắt buộc + lý do.

### 4.5 Financial Impact Timeline

```
Ngày tính lương (20-25)           Ngày chi (25-27)              Ngày nộp BHXH/TNCN (20-30)
      │                                │                              │
      ▼                                ▼                              ▼
┌─────────────┐                 ┌─────────────┐              ┌─────────────┐
│ Ghi nhận CP │                 │ Giảm 334     │              │ Giảm 338    │
│ Tăng 334    │                 │ Giảm 111/112 │              │ Giảm 3335   │
│ Tăng 338    │                 │              │              │ Giảm 112    │
│ Tăng 3335   │                 │              │              │             │
└─────────────┘                 └─────────────┘              └─────────────┘
     │                                │                              │
     ▼                                ▼                              ▼
BC02 tăng chi phí               BC01 giảm tiền                 BC01 giảm nợ phải trả
```

---

## 5. Use Cases

### 5.1 UC-01: Tính lương nhân viên chính thức hàng tháng

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
| **Compliance Risk** | Sai lương → kiện tụng; Sai BHXH → phạt; Sai thuế → truy thu |

### 5.2 UC-02: Tiếp nhận nhân viên mới

| Field | Value |
|---|---|
| **Use Case Name** | Tiếp nhận nhân viên mới vào hệ thống lương |
| **Actors** | HR, Trưởng bộ phận |
| **Preconditions** | Nhân viên đã ký HĐLĐ |
| **Trigger** | Ngày đầu tiên đi làm / Ngày ký HĐLĐ |
| **Happy Path** | HR nhập hồ sơ: Họ tên, CMND/CCCD, lương Gross, phụ cấp, BHXH, NPT, TK NH, chi nhánh |
| **Alternative Path** | Chưa có số BHXH → tạm tính 0%, bổ sung sau |
| **Exception Path** | Lương < tối thiểu vùng → BLOCK |
| **Validation Rules** | • Lương ≥ tối thiểu vùng • Số CMND/CCCD tồn tại • TK NH hợp lệ |
| **Compliance Risk** | Không đăng ký BHXH trong 30 ngày → phạt |

### 5.3 UC-03: Nhân viên nghỉ việc — Final Settlement

| Field | Value |
|---|---|
| **Use Case Name** | Quyết toán khi nhân viên nghỉ việc |
| **Actors** | HR, Kế toán lương, Nhân viên |
| **Preconditions** | Nhân viên đã nộp đơn, đã hết thời gian báo trước |
| **Trigger** | Ngày nghỉ việc cuối cùng |
| **Happy Path** | 1. Tính lương đến ngày nghỉ → 2. Tính phép năm còn lại → 3. Tính trợ cấp thôi việc (nếu đủ 12 tháng) → 4. Trừ tạm ứng → 5. Chi lần cuối → 6. Chốt sổ BHXH → 7. Cập nhật trạng thái |
| **Alternative Path** | Nhân viên bị mất việc → tính trợ cấp mất việc (1 tháng/năm, tối thiểu 2 tháng) |
| **Exception Path** | Nhân viên kiện tụng → giữ lương chờ phán quyết |
| **Validation Rules** | • Tính đúng trợ cấp (trừ thời gian đóng BHTN) • Chốt đúng ngày BHXH |
| **Compliance Risk** | Không trả trợ cấp → bị kiện; Chốt BHXH sai → nhân viên khiếu nại |

### 5.4 UC-04: Xử lý tăng ca

| Field | Value |
|---|---|
| **Use Case Name** | Tính tiền tăng ca hàng tháng |
| **Actors** | Nhân viên, Trưởng bộ phận, HR, Kế toán lương |
| **Preconditions** | Có đăng ký tăng ca được duyệt trước; Có check-in/check-out thực tế |
| **Trigger** | Cuối tháng, tổng hợp tăng ca |
| **Happy Path** | 1. Nhân viên check-in/out → 2. Hệ thống ghi nhận giờ → 3. Tự động tính theo hệ số → 4. Duyệt tăng ca → 5. Cộng vào lương |
| **Exception Path** | Tăng ca > 40h/tháng → BLOCK. Tăng ca > 200h/năm → WARN (chỉ cho phép ngành đặc thù) |
| **Validation Rules** | • ≤ 40h/tháng • ≤ 200h/năm • ≤ 12h/ngày • Nhân viên đã đồng ý |
| **Compliance Risk** | Vượt giới hạn → phạt 5-50tr. Không trả đủ → bị kiện |

### 5.5 UC-05: Điều chỉnh lương hồi tố

| Field | Value |
|---|---|
| **Use Case Name** | Điều chỉnh lương hồi tố |
| **Actors** | Kế toán lương, Kế toán trưởng |
| **Preconditions** | Kỳ lương cũ đã chốt; có chứng từ điều chỉnh |
| **Trigger** | Phát hiện sai sót / Quyết định tăng lương từ tháng trước |
| **Happy Path** | 1. Lập phiếu điều chỉnh → 2. Duyệt (CFO/Giám đốc) → 3. Hạch toán bổ sung kỳ hiện tại → 4. Bù trừ vào lương kỳ này |
| **Exception Path** | Không mở lại kỳ cũ (đã khóa sổ kế toán) → chỉ điều chỉnh kỳ hiện tại |
| **Validation Rules** | • Audit trail bắt buộc • Ghi rõ lý do • Không sửa dữ liệu gốc |
| **Accounting Impact** | Nợ/Có 334 chênh lệch; Bút toán điều chỉnh riêng |

### 5.6 UC-06: Nhân viên nghỉ thai sản

| Field | Value |
|---|---|
| **Use Case Name** | Xử lý chế độ thai sản |
| **Actors** | Nhân viên, HR, Kế toán lương, BHXH |
| **Preconditions** | Nhân viên đã đóng BHXH ≥ 6 tháng; có giấy khai sinh/chứng nhận |
| **Happy Path** | 1. Nhân viên nộp hồ sơ → 2. HR làm chế độ BHXH → 3. BHXH duyệt → 4. Nhận tiền từ BHXH → 5. Chi cho nhân viên |
| **Accounting Impact** | Nợ 3383 / Có 334; Khi nhận: Nợ 111/112 / Có 3383; Khi trả: Nợ 334 / Có 111/112 |

### 5.7 UC-07: Xử lý vi phạm chấm công

| Field | Value |
|---|---|
| **Use Case Name** | Xử lý vi phạm chấm công |
| **Actors** | Hệ thống chấm công, HR, Trưởng bộ phận |
| **Preconditions** | Có thiết bị chấm công (vân tay/khuôn mặt/GPS) |
| **Happy Path** | 1. Nhân viên chấm công → 2. Hệ thống ghi nhận → 3. Tự động quy đổi: đi muộn 3 lần = 1 công mất → 4. HR duyệt → 5. Trừ vào lương |
| **Validation Rules** | • Dung sai 15-30 phút (tùy chính sách) • Có lý do hợp lệ → được bỏ qua |

### 5.8 UC-08: Quyết toán thuế TNCN cuối năm

| Field | Value |
|---|---|
| **Use Case Name** | Quyết toán thuế TNCN cuối năm |
| **Actors** | Kế toán thuế, Nhân viên, Cơ quan thuế |
| **Preconditions** | Đã kết thúc năm tài chính, tổng hợp đủ 12 tháng lương |
| **Trigger** | Trước ngày 31/03 năm sau (thời hạn quyết toán) |
| **Happy Path** | 1. Tổng hợp TNCN 12 tháng → 2. Tính lại thuế theo năm → 3. Xác định nộp thừa/thiếu → 4. Nộp bổ sung (nếu thiếu) hoặc đề nghị hoàn (nếu thừa) → 5. Cấp chứng từ khấu trừ cho nhân viên |
| **Exception Path** | Nhân viên có nhiều nguồn thu nhập → tự quyết toán riêng → hệ thống cung cấp dữ liệu |
| **Compliance Risk**| Quyết toán sai → phạt chậm, phạt thiếu; Không cấp chứng từ → phạt |

### 5.9 UC-09: Tạm ứng lương và trừ dần

| Field | Value |
|---|---|
| **Use Case Name** | Tạm ứng lương |
| **Actors** | Nhân viên, HR, Kế toán lương |
| **Preconditions** | Nhân viên đang làm việc, có đề xuất tạm ứng |
| **Trigger** | Yêu cầu từ nhân viên |
| **Happy Path** | 1. Nhân viên yêu cầu → 2. HR duyệt → 3. Kế toán chi (Nợ 334 / Có 111/112) → 4. Khi tính lương: tự động trừ vào Net |
| **Exception Path** | Tạm ứng > 30% lương → BLOCK (theo chính sách) |
| **Validation Rules** | • ≤ 30% lương Gross • Không tạm ứng cho NV đã nghỉ |

### 5.10 UC-10: Kê khai BHXH hàng tháng

| Field | Value |
|---|---|
| **Use Case Name** | Kê khai BHXH hàng tháng |
| **Actors** | Kế toán lương, Cơ quan BHXH |
| **Preconditions** | Bảng lương đã duyệt, đã chốt |
| **Trigger** | Sau khi chốt lương, trước ngày 30 hàng tháng |
| **Happy Path** | 1. Tổng hợp lương đóng BHXH → 2. Tách NLĐ (10.5%) + DN (21.5%) → 3. Sinh file D02-LT → 4. Nộp online → 5. Đối chiếu |
| **Compliance Risk**| Chậm nộp → lãi 0.03%/ngày; Sai số → truy thu + phạt |

---

## 6. Payroll Calculation Engine

### 6.1 Core Formulas

#### 6.1.1 Gross Salary

```
Gross Salary = Basic Salary + Allowances + Bonuses + Commission + Overtime
```

#### 6.1.2 Salary Calculation Methods

**Phương pháp 1 — Lương tháng cố định (công thức phổ biến nhất SME):**

```
Lương tháng = (Lương Gross / Số ngày công chuẩn) × Số ngày công thực tế
```

Trong đó:
- **Số ngày công chuẩn:** 26 ngày (mặc định, có thể 24-27 tùy doanh nghiệp)
- **Ngày công thực tế:** Tổng ngày làm việc - Nghỉ không lương - Đi muộn/về sớm quy đổi

**Phương pháp 2 — Lương theo sản phẩm:**

```
Lương sản phẩm = Số lượng sản phẩm × Đơn giá sản phẩm
```

**Phương pháp 3 — Lương theo giờ:**

```
Lương giờ = Lương Gross / (Số ngày công chuẩn × 8) × Số giờ thực tế
```

#### 6.1.3 Overtime Calculation

Căn cứ: Điều 98 Bộ Luật Lao động 2019

```
Tiền tăng ca = Số giờ tăng ca × Hệ số × (Lương Gross / (Số ngày công chuẩn × 8))
```

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

#### 6.1.4 Allowance Taxability

| Loại phụ cấp | Miễn thuế TNCN? | Đóng BHXH? | Ghi chú |
|---|---|---|---|
| Phụ cấp chức vụ | Không | Có | % lương cơ bản (10-30%) |
| Phụ cấp trách nhiệm | Không | Có | Số tiền cố định/tháng |
| Phụ cấp thu hút | Không | Có | % lương |
| Phụ cấp độc hại | Có | Không | Hệ số 0.1-0.4 × lương cơ sở |
| Phụ cấp khu vực | Có | Không | Hệ số 0.1-0.5 × lương cơ sở |
| Phụ cấp xăng xe | Nếu ≤ mức quy định | Không | 200k-500k/tháng |
| Phụ cấp điện thoại | Theo hóa đơn thực tế | Không | Cố định hoặc theo hóa đơn |
| Phụ cấp ăn trưa | Nếu ≤ 730k | Không | Tối đa 730k/tháng miễn thuế |
| Phụ cấp nhà ở | Nếu theo quy định | Không | |
| Phụ cấp trang phục | Nếu ≤ 5tr/năm | Không | Tối đa 5tr/năm miễn thuế |

### 6.2 Insurance Calculation

#### 6.2.1 Rates 2026

**Người lao động Việt Nam (tổng: 32% — NLĐ 10.5% + DN 21.5%):**

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

#### 6.2.2 Insurance Ceilings & Floors

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

**Sàn đóng BHXH:** Lương Gross không thấp hơn lương tối thiểu vùng.

#### 6.2.3 Insurance Calculation Example

Nhân viên lương Gross 50,000,000đ, Vùng I:

```
BHXH (NLĐ): min(50tr, 46.8tr) × 8% = 46.8tr × 8% = 3,744,000
BHYT (NLĐ): min(50tr, 46.8tr) × 1.5% = 46.8tr × 1.5% = 702,000
BHTN (NLĐ): min(50tr, 106.2tr) × 1% = 50tr × 1% = 500,000
Tổng NLĐ đóng: 4,946,000 (9.89% của 50tr — giảm dần % khi lương cao)
```

### 6.3 PIT (Personal Income Tax) Calculation

#### 6.3.1 Progressive Tax Table — 5 Brackets (From 2026)

Căn cứ: Luật Thuế TNCN 2025 (109/2025/QH15), Nghị quyết 110/2025/UBTVQH15

| Bậc | Thu nhập tính thuế/tháng | Thuế suất | Công thức rút gọn |
|---|---|---|---|
| 1 | Đến 10 triệu | 5% | TNTT × 5% |
| 2 | Trên 10 – 30 triệu | 10% | TNTT × 10% – 0.5tr |
| 3 | Trên 30 – 60 triệu | 20% | TNTT × 20% – 3.5tr |
| 4 | Trên 60 – 100 triệu | 30% | TNTT × 30% – 9.5tr |
| 5 | Trên 100 triệu | 35% | TNTT × 35% – 14.5tr |

**Khác biệt với trước 2026:**
- 7 bậc → 5 bậc
- Giảm trừ gia cảnh: 15.5tr (bản thân), 6.2tr (người phụ thuộc)

#### 6.3.2 PIT Formula

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

#### 6.3.3 PIT Example

Nhân viên Gross 30,000,000đ, 1 người phụ thuộc, Vùng I:

```
Bước 1: Lương Gross = 30,000,000
Bước 2: BHXH = min(30tr, 46.8tr) × 10.5% = 3,150,000
Bước 3: Giảm trừ bản thân = 15,500,000
Bước 4: Giảm trừ người phụ thuộc = 6,200,000
Bước 5: TNTT = 30,000,000 - 3,150,000 - 15,500,000 - 6,200,000 = 5,150,000
Bước 6: Thuế TNCN = 5,150,000 × 5% = 257,500 đ
```

#### 6.3.4 PIT Threshold by Dependents

| Số NPT | Thu nhập bắt đầu chịu thuế/tháng |
|---|---|
| 0 | > 15.5 triệu |
| 1 | > 21.7 triệu |
| 2 | > 27.9 triệu |
| 3 | > 34.1 triệu |
| n | > 15.5 + n × 6.2 triệu |

### 6.4 Net-to-Gross Reverse Calculation (For Final Settlement)

```
Lương Net mục tiêu (sau thuế) → Xác định lương Gross cần trả

Thuật toán:
  Bước 1: Ước lượng Gross ban đầu = Net + BHXH + (Net × thuế suất trung bình)
  Bước 2: Tính Net từ Gross ước lượng
  Bước 3: So sánh với Net mục tiêu
  Bước 4: Điều chỉnh Gross (tăng/giảm)
  Bước 5: Lặp lại bước 2-4 đến khi Net ≈ mục tiêu (tolerance ±1000đ)
```

### 6.5 Probation Salary Rules

```
Lương thử việc ≥ 85% lương chính thức (Điều 26 BLLĐ 2019)

Thời gian thử việc:
  - Vị trí yêu cầu trình độ chuyên môn: ≤ 60 ngày
  - Trung cấp/Công nhân kỹ thuật: ≤ 30 ngày
  - Lao động phổ thông: ≤ 6 ngày

BHXH:
  - HĐLĐ < 1 tháng: KHÔNG đóng BHXH
  - HĐLĐ ≥ 1 tháng: PHẢI đóng BHXH (Luật BHXH 2024 mở rộng)
```

### 6.6 Final Settlement Formulas

```
Trợ cấp thôi việc = 1/2 × Thời gian tính trợ cấp (năm) × Lương BQ 6 tháng

Trợ cấp mất việc = 1 × Thời gian tính trợ cấp (năm) × Lương BQ 6 tháng
Tối thiểu: 2 tháng lương

Thời gian tính trợ cấp = Tổng thời gian làm việc - Thời gian đóng BHTN - Thời gian đã nhận trợ cấp

Phép năm còn lại (thanh toán khi nghỉ) = Số ngày phép tồn × Lương ngày
```

### 6.7 Union Fee (KPCĐ) — Nghị định 105/2026

**Hiệu lực từ 16/05/2026:**

```
KPCĐ = 2% × Quỹ tiền lương làm căn cứ đóng BHXH

Kinh phí công đoàn: 2% — Doanh nghiệp đóng toàn bộ
Đoàn phí công đoàn: 1% lương — Nhân viên đóng (nếu là đoàn viên)
```

---

## 7. Workflow & Process

### 7.1 End-to-End Monthly Payroll Process

```
TUẦN 1 (Ngày 1-5): Thu thập dữ liệu
  ├── Chấm công (vân tay/khuôn mặt/GPS)
  ├── Tăng ca đăng ký
  ├── Nghỉ phép (đã duyệt)
  ├── Đi công tác
  └── Biến động nhân sự (vào/ra/thay đổi lương)

TUẦN 2 (Ngày 5-12): Xử lý dữ liệu
  ├── HR tổng hợp chấm công
  ├── Trưởng bộ phận duyệt chấm công
  ├── Nhập điều chỉnh (tăng ca, nghỉ bù, quên chấm)
  └── Duyệt điều chỉnh

TUẦN 3 (Ngày 12-20): Tính lương
  ├── Engine tính lương thử (draft)
  ├── Kế toán lương kiểm tra
  ├── Điều chỉnh (nếu sai)
  ├── Kế toán trưởng duyệt
  └── CHỐT LƯƠNG

TUẦN 4 (Ngày 20-25): Chi lương
  ├── Sinh bảng lương chính thức
  ├── Sinh file chuyển khoản (hoặc phiếu chi TM)
  ├── Giám đốc duyệt chi
  ├── Thực hiện chi lương
  ├── Gửi bảng lương nhân viên (payslip)
  └── Hạch toán lương

TUẦN 5 (Ngày 25-30): Hoàn tất
  ├── Hạch toán BHXH/BHYT/BHTN
  ├── Hạch toán thuế TNCN
  ├── Kê khai BHXH
  ├── Kê khai thuế TNCN
  ├── Nộp BHXH/BHYT/BHTN/KPCĐ
  └── Nộp thuế TNCN
```

### 7.2 Role Separation

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

### 7.3 Attendance → Payroll → Finance Flow

```
Attendance → Raw data (check-in/out times)
  ↓
HR Validation → Approved attendance (after review by manager)
  ↓
Payroll Engine → Salary calculation (using approved attendance)
  ↓
Payslip Generation → Final payslip
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

### 7.4 Payroll Closing Flow

```
1. Kiểm tra: tất cả NV đã tính lương?  → YES
2. Kiểm tra: tất cả chấm công đã duyệt? → YES
3. Kiểm tra: tất cả điều chỉnh đã duyệt? → YES
4. Kiểm tra: tổng Dr = Cr? → YES
5. Chốt bảng lương (status = closed)
6. Sinh bút toán kế toán
7. Sinh báo cáo lương
8. Lưu audit trail
```

### 7.5 Payslip Flow

```
Engine tính lương → Tạo draft payslip → Duyệt → Gửi email/Zalo → Lưu PDF → Xác nhận
```

### 7.6 Payment Flow

```
Cash Payment: Lập phiếu chi → Kế toán trưởng ký → Thủ quỹ chi → Nhân viên ký nhận

Bank Transfer: Sinh file chuyển tiền → Import Internet Banking → Giám đốc ký duyệt
  → Ngân hàng thực hiện → Sao kê đối chiếu
```

### 7.7 Audit Preparation Flow

```
Cơ quan thuế/BHXH/LĐ thanh tra → Xuất báo cáo:
  1. Bảng lương hàng tháng (cả năm)
  2. Bảng kê BHXH theo tháng
  3. Bảng kê TNCN theo tháng
  4. HĐLĐ của nhân viên
  5. Bảng chấm công
  6. Phiếu chi lương/chứng từ chuyển khoản
  7. Quy chế lương thưởng
```

### 7.8 Exception Handling Flow

```
Phát hiện lỗi:
  Trước duyệt → Sửa trực tiếp, không cần audit
  Sau duyệt, trước chi → Hủy duyệt → sửa → duyệt lại (có audit)
  Sau chi → Lập phiếu điều chỉnh kỳ sau → Bút toán điều chỉnh → Không mở lại kỳ cũ
  Sai BHXH → Điều chỉnh với cơ quan BHXH kỳ sau → Truy thu/hoàn trả
  Sai thuế → Kê khai bổ sung → Nộp bổ sung + lãi chậm nộp
```

---

## 8. Data Flow

### 8.1 Employee Master Data Flow

```
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

### 8.2 Attendance Data Flow

```
Thiết bị chấm công (vân tay/khuôn mặt/GPS)
  → Raw data (timestamp, employee_id, type)
    → HR System tổng hợp
      → Tính số công, tăng ca, đi muộn
        → Trưởng bộ phận duyệt
          → Approved attendance → Payroll Engine
```

### 8.3 Salary Calculation Data Flow

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
  │   └── DN: 21.5% × lương đóng
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

### 8.4 Insurance Data Flow

```
Bảng lương đã duyệt → Tính lương đóng BHXH
  → Tách: Phần NLĐ (10.5%) + Phần DN (21.5%)
    → Tổng hợp theo mã BHXH
      → Sinh file kê khai BHXH (D02-LT, D01-TS, TK3-TS)
        → Nộp cơ quan BHXH (online/trực tiếp)
          → Đối chiếu hàng tháng
```

### 8.5 PIT Declaration Data Flow

```
Bảng lương đã duyệt → Tính TNTT từng NV → Tính thuế TNCN
  → Khấu trừ khi trả lương (Nợ 334 / Có 3335)
    → Kê khai thuế TNCN (05/KK-TNCN) hàng tháng/quý
      → Nộp thuế (trước ngày 20 tháng sau)
        → Cuối năm: quyết toán thuế TNCN
          → Cấp chứng từ khấu trừ cho nhân viên
```

### 8.6 Payroll Posting Data Flow

```
Bảng lương đã duyệt → Sinh bút toán tự động:

1. Ghi nhận lương phải trả:
   Nợ 622 (SX) / 627 (SXC) / 641 (BH) / 642 (QL)
     Có 334 (Phải trả NLĐ)

2. Trích BHXH/BHYT/BHTN/KPCĐ vào chi phí:
   Nợ 622/627/641/642 (21.5% + 2% lương đóng BHXH)
     Có 3383 (BHXH) / 3384 (BHYT) / 3386 (BHTN) / 3382 (KPCĐ)

3. Khấu trừ BHXH vào lương NLĐ:
   Nợ 334
     Có 3383 (8%) / 3384 (1.5%) / 3386 (1%)

4. Khấu trừ thuế TNCN:
   Nợ 334
     Có 3335 (Thuế TNCN)

5. Chi lương (chuyển khoản):
   Nợ 334
     Có 112 (Tiền gửi NH)

6. Nộp BHXH:
   Nợ 3383 / 3384 / 3386 / 3382
     Có 112

7. Nộp thuế TNCN:
   Nợ 3335
     Có 112
```

### 8.7 Adjustment Data Flow

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

### 8.8 Final Settlement Data Flow

```
Nhân viên nghỉ việc → Tính lương đến ngày nghỉ (prorated)
  → Tính phép năm còn lại (nếu có)
    → Tính trợ cấp thôi việc:
      1. Xác định thời gian làm việc thực tế
      2. Trừ thời gian đã đóng BHTN
      3. Trừ thời gian đã nhận trợ cấp trước đó
      4. Trợ cấp = (Số năm × 0.5) × Lương BQ 6 tháng
    → Tổng thanh toán lần cuối → Chi trả
      → Chốt sổ BHXH (gửi cơ quan BHXH)
        → Cập nhật trạng thái nhân viên
```

### 8.9 Reporting Data Flow

```
Dữ liệu lương → Báo cáo nội bộ:
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

## 9. User Journeys

### 9.1 HR Officer Journey

```
NGÀY: Quản lý hồ sơ → Theo dõi chấm công → Tổng hợp bảng lương → Bàn giao cho Kế toán

HR Officer công việc hàng ngày:
  - Nhập hồ sơ nhân viên mới
  - Cập nhật biến động (tăng lương, chuyển phòng, nghỉ việc)
  - Duyệt đơn nghỉ phép, tăng ca
  - Xử lý quên chấm công

HR Officer công việc cuối tháng:
  - Xuất báo cáo chấm công
  - Gửi trưởng bộ phận duyệt
  - Nhập tăng ca, nghỉ bù
  - Tổng hợp bảng lương draft
```

### 9.2 Payroll Accountant Journey

```
NGÀY: Nhận dữ liệu từ HR → Chạy payroll engine → Kiểm tra bảng lương → Trình duyệt

Thao tác:
  1. Import dữ liệu chấm công đã duyệt
  2. Kiểm tra cấu hình (tỷ lệ BHXH, biểu thuế)
  3. Chạy payroll engine
  4. Kiểm tra kết quả (so sánh tháng trước, phát hiện bất thường)
  5. Điều chỉnh nếu sai
  6. Trình duyệt
```

### 9.3 Department Manager Journey

```
TUẦN: Duyệt chấm công → Xác nhận tăng ca/nghỉ → Phê duyệt điều chỉnh

Thao tác:
  1. Đầu tháng: duyệt chấm công của nhân viên trong phòng
  2. Xác nhận tăng ca, nghỉ phép
  3. Xác nhận KPI, hoa hồng
```

### 9.4 Finance Controller / Chief Accountant Journey

```
THÁNG: Kiểm tra bảng lương → Duyệt bút toán → Hạch toán lương → Báo cáo tài chính

Thao tác:
  1. Kiểm tra bảng lương tổng thể
  2. So sánh dự toán chi phí lương
  3. Duyệt bút toán lương
  4. Kiểm tra số dư 334, 338, 3335 cuối kỳ
```

### 9.5 Employee Self-Service Journey

```
THÁNG: Xem lịch sử chấm công → Gửi đơn nghỉ phép/tăng ca → Xem payslip → Xác nhận nhận lương
```

### 9.6 Month-End Timeline Journey

```
Ngày 20:  Chốt chấm công
Ngày 21-22: HR tổng hợp + trình duyệt
Ngày 22-23: Kế toán chạy payroll + trình duyệt
Ngày 24-25: Duyệt lương (Kế toán trưởng + Giám đốc)
Ngày 25-26: Sinh file chuyển khoản + duyệt NH
Ngày 26-27: Lương về tài khoản nhân viên
Ngày 28-30: Hạch toán, kê khai BHXH, kê khai thuế
```

### 9.7 Resignation Journey

```
Nhân viên nộp đơn → HR nhận → Xác định thời gian báo trước
  → Nhân viên làm việc nốt thời gian báo trước
    → Ngày cuối: HR làm quyết toán
      → Kế toán tính lần cuối + trợ cấp
        → Thanh toán + chốt sổ BHXH
          → Cập nhật trạng thái
```

---

## 10. Validation & Internal Control

### 10.1 Payroll Rule Validation

#### 10.1.1 Salary Validation

1. **Lương ≥ tối thiểu vùng:** Block nếu Gross < lương tối thiểu vùng
2. **Lương thử việc ≥ 85%:** Block nếu < 85% lương chính thức
3. **BHXH đúng tỷ lệ:** Auto tính, check với config
4. **BHXH đúng trần/sàn:** Auto cắt trần, check sàn
5. **TNCN đúng biểu thuế:** Auto tính, check 5 bậc
6. **Tăng ca ≤ 200h/năm:** Warn nếu vượt (block cho ngành không đặc thù)
7. **Nghỉ phép đúng quy định:** Auto tính số phép tồn
8. **Thời hạn nộp BHXH:** Warn + nhắc nhở

#### 10.1.2 Attendance Validation

1. **Đi muộn ≥ 30 phút:** tính 1 lần đi muộn
2. **Về sớm ≥ 30 phút:** tính 1 lần về sớm
3. **Quên chấm công:** nhân viên báo, trưởng bộ phận xác nhận
4. **Không chấm công không báo:** tính 0.5 công (cảnh cáo)
5. **Vắng không lý do:** tính 0 công, không lương

#### 10.1.3 Overtime Validation

1. **Phải đăng ký trước** khi làm tăng ca
2. **Duyệt trước:** trưởng bộ phận duyệt trước khi tăng ca
3. **Check-in/out thực tế:** phải có chấm công thực tế
4. **≤ 40h/tháng:** giới hạn cứng
5. **≤ 200h/năm:** giới hạn mềm (cảnh báo + phê duyệt cấp cao)
6. **≤ 12h/ngày:** giới hạn cứng

#### 10.1.4 Allowance Validation

1. **Phụ cấp chức vụ:** tự động theo chức vụ
2. **Phụ cấp trách nhiệm:** gán thủ công, có ngày hiệu lực
3. **Phụ cấp độc hại:** tự động theo danh sách vị trí độc hại
4. **Phụ cấp ăn trưa:** tối đa 730k/tháng (miễn thuế)
5. **Phụ cấp xăng xe:** cố định theo chính sách

### 10.2 Invalid Payroll Prevention

| Rule | Action | Severity |
|---|---|---|
| Lương âm (Net < 0) | BLOCK | Critical |
| TNTT âm | set TNTT = 0 (không thuế) | Normal |
| Thiếu thông tin (Gross, BHXH, NPT) | BLOCK | Critical |
| Nhân viên đã có lương tháng đó | BLOCK (trùng) | Critical |
| Chưa duyệt chấm công | BLOCK (không tính lương) | High |
| Chưa duyệt lương | BLOCK (không chi) | High |
| Kỳ lương đã đóng | BLOCK (read-only) | Critical |
| Nhân viên đã nghỉ việc | BLOCK (không tính lương) | Critical |

### 10.3 Duplicate Payment Prevention

| Check | Method | Severity |
|---|---|---|
| Check-in/out trùng | Phát hiện chấm công trùng IP/thời điểm | Medium |
| Lương tháng trùng | Unique (employee_id, period_id) | Critical |
| Chi trùng | Check file chuyển tiền trùng TK + số tiền | Critical |
| Đã nghỉ việc | Không tính lương cho NV đã nghỉ | Critical |

### 10.4 Unauthorized Adjustment Prevention

| Rule | Method |
|---|---|
| Phân quyền sửa lương Gross | Chỉ HR Manager mới được sửa |
| Audit log mọi thay đổi | Ghi lại ai, khi nào, cũ → mới |
| Duyệt kép cho thay đổi > 10% | Cần 2 người duyệt |
| Hồi tố chỉ CFO/Giám đốc duyệt | Phân quyền cứng |

### 10.5 Payroll Fraud Risk Detection

| Dấu hiệu | Mô tả | Mức độ |
|---|---|---|
| Nhân viên ảo | Có tên trong bảng lương nhưng không có chấm công | CAO |
| Lương tăng đột biến | Tăng > 30% so với tháng trước không có lý do | CAO |
| Hoa hồng bất thường | Hoa hồng > 50% lương Gross | TRUNG BÌNH |
| Tăng ca bất thường | Tăng ca > 60h/tháng | CAO |
| Nhiều NPT | > 5 người phụ thuộc trên một nhân viên | TRUNG BÌNH |
| Tài khoản NH trùng | 2 nhân viên chung 1 tài khoản NH | CAO |
| Nghỉ việc không chốt | Nhân viên nghỉ nhưng vẫn tính lương | CAO |

### 10.6 Segregation of Duties Matrix

| Chức năng | HR | Kế toán lương | Kế toán trưởng | Giám đốc |
|---|---|---|---|---|
| Nhập hồ sơ NV | ✅ | ❌ | ❌ | ❌ |
| Sửa lương Gross | ✅ (HR Mgr) | ❌ | ❌ | ❌ |
| Nhập chấm công | ✅ | ❌ | ❌ | ❌ |
| Duyệt chấm công | ❌ | ❌ | ❌ | ❌ |
| Tính lương | ❌ | ✅ | ❌ | ❌ |
| Kiểm tra lương | ❌ | ✅ | ✅ | ❌ |
| Duyệt lương | ❌ | ❌ | ✅ | ❌ |
| Duyệt chi | ❌ | ❌ | ❌ | ✅ |
| Chi lương | ❌ | ✅ | ❌ | ❌ |
| Hạch toán | ❌ | ✅ | ❌ | ❌ |
| Mở lại kỳ đã đóng | ❌ | ❌ | ✅ (CFO) | ✅ |

### 10.7 Period Locking Controls

```
Period Status Flow:
  OPEN → (tính lương) → APPROVED → (đã chi) → CLOSED → (read-only)
                                                  ↓
                                          REOPEN (chỉ CFO/GĐ)
                                                  ↓
                                          ADJUST → CLOSED

Validation:
  - Không post lương vào kỳ đã đóng
  - Không sửa bảng lương đã duyệt
  - Mở lại kỳ = audit trail bắt buộc + lý do
  - Sau khi mở lại: phải duyệt lại toàn bộ
```

### 10.8 Audit Trail Requirements

```
Mọi thay đổi trong payroll cycle phải được ghi lại:
  - Thay đổi thông tin nhân viên (lương, phụ cấp, chức vụ)
  - Thay đổi chấm công (điều chỉnh ngày công)
  - Thay đổi bảng lương trước chốt
  - Mở lại kỳ lương đã chốt (chỉ CFO/giám đốc)
  - Chi lương (tiền mặt/chuyển khoản)

Audit log fields:
  - Timestamp
  - Actor (user ID)
  - Action (type)
  - Resource (entity + ID)
  - Old value (JSON)
  - New value (JSON)
  - Reason (text)
  - IP address
```

---

## 11. Reporting & Reconciliation

### 11.1 Accounting Posting — Full Journal Entry Set

#### 11.1.1 Accounts Used

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
| 335 | Chi phí phải trả | Trích trước lương nghỉ phép |

#### 11.1.2 Standard Journal Entries

**1. Tính lương phải trả:**
```
Nợ 622 — Chi phí nhân công trực tiếp (sản xuất)
Nợ 627 — Chi phí sản xuất chung (quản đốc, nhân viên PXSX)
Nợ 641 — Chi phí bán hàng (nhân viên bán hàng)
Nợ 642 — Chi phí quản lý doanh nghiệp (văn phòng)
Nợ 241 — XDCB dở dang (công trình)
   Có 334 — Phải trả người lao động (tổng lương Gross)
```

**2. Trích BHXH/BHYT/BHTN/KPCĐ vào chi phí DN:**
```
Nợ 622 / 627 / 641 / 642 — (21.5% + 2%) = 23.5% lương đóng BHXH
   Có 3383 — BHXH (17.5%)
   Có 3384 — BHYT (3%)
   Có 3386 — BHTN (1%)
   Có 3382 — KPCĐ (2%)
```

**3. Khấu trừ BHXH vào lương NLĐ:**
```
Nợ 334 — Phải trả NLĐ (10.5% lương đóng BHXH)
   Có 3383 — BHXH (8%)
   Có 3384 — BHYT (1.5%)
   Có 3386 — BHTN (1%)
```

**4. Khấu trừ thuế TNCN:**
```
Nợ 334 — Phải trả NLĐ (thuế TNCN phải nộp)
   Có 3335 — Thuế TNCN
```

**5. Chi lương (chuyển khoản):**
```
Nợ 334 — Phải trả NLĐ (số thực nhận = Net)
   Có 112 — Tiền gửi ngân hàng
```

**6. Chi lương (tiền mặt):**
```
Nợ 334 — Phải trả NLĐ
   Có 111 — Tiền mặt
```

**7. Nộp BHXH/BHYT/BHTN/KPCĐ:**
```
Nợ 3383 — BHXH (25.5% lương đóng BHXH)
Nợ 3384 — BHYT (4.5%)
Nợ 3386 — BHTN (2%)
Nợ 3382 — KPCĐ (2%)
   Có 112 — Tiền gửi ngân hàng
```

**8. Nộp thuế TNCN:**
```
Nợ 3335 — Thuế TNCN
   Có 112 — Tiền gửi ngân hàng
```

**9. Tạm ứng lương:**
```
Nợ 334 — Phải trả NLĐ
   Có 111 / 112 — Tiền mặt / Tiền gửi NH
```

**10. Nhận tiền BHXH thai sản/ốm đau:**
```
Nợ 111 / 112 — Tiền nhận từ cơ quan BHXH
   Có 3383 — BHXH

Khi chi trả cho NLĐ:
Nợ 3383 — BHXH
   Có 334 — Phải trả NLĐ

Nợ 334 — Phải trả NLĐ
   Có 111 / 112 — Tiền mặt / NH
```

**11. Trích trước lương nghỉ phép:**
```
Nợ 622 / 627 / 641 / 642
   Có 335 — Chi phí phải trả

Khi thực tế nghỉ phép:
Nợ 335 — Chi phí phải trả
   Có 334 — Phải trả NLĐ
```

#### 11.1.3 Complete Posting Example

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

### 11.2 Trial Balance — Payroll Impact

```
Đầu kỳ: 334 = 0 (đã trả hết lương kỳ trước)
  + Ghi nhận lương: 334 tăng (Gross)
  - Khấu trừ BHXH: 334 giảm (10.5%)
  - Khấu trừ TNCN: 334 giảm (thuế)
  - Chi lương: 334 giảm (Net)
  = Cuối kỳ: 334 = 0 (đã trả hết)

Đầu kỳ: 3383/3384/3386/3382 = 0 (đã nộp)
  + Trích BHXH DN: 338 tăng (21.5%)
  + Khấu trừ NLĐ: 338 tăng (10.5%)
  - Nộp BHXH: 338 giảm (32%)
  = Cuối kỳ: 338 = 0 (đã nộp hết)

Dr = Cr check:
  Tổng Nợ (622+627+641+642) = 334 + 338 (DN phần)
  Tổng Có (334 + 338 + 3335) = 334 + 338 + 3335
  ⇒ Dr = Cr ✓
```

### 11.3 Monthly Reconciliation Checklist

| Hạng mục | Kiểm tra | Tần suất | Người thực hiện |
|---|---|---|---|
| Đối chiếu tổng lương | Bảng lương = Sổ cái 334 | Tháng | Kế toán lương |
| Đối chiếu BHXH | Bảng lương = Sổ cái 3383/3384/3386 | Tháng | Kế toán lương |
| Đối chiếu thuế TNCN | Bảng lương = Sổ cái 3335 | Tháng | Kế toán lương |
| Đối chiếu chi lương | Bảng lương Net = Sổ cái 111/112 | Tháng | Kế toán lương |
| Đối chiếu nhân sự | Số NV trong payroll = Số NV thực tế | Tháng | HR |
| Đối chiếu BHXH | Số đã nộp = Số phải nộp | Tháng | Kế toán lương |
| Đối chiếu tồn phép | Số phép tồn mỗi NV | Quý | HR |
| Đối chiếu NPT | Số NPT kê khai thuế | Quý | Kế toán thuế |
| Đối chiếu quyết toán TNCN | Tổng thuế cả năm | Năm | Kế toán thuế |

### 11.4 Internal Reports

| Report | Mục đích | Định kỳ |
|---|---|---|
| Bảng lương chi tiết | Từng nhân viên | Tháng |
| Bảng tổng hợp lương theo phòng ban | Chi phí phòng ban | Tháng |
| Báo cáo chi phí lương (622/627/641/642) | BC02 | Tháng |
| Báo cáo biến động nhân sự | Vào/ra/thay đổi | Tháng |
| Báo cáo chi phí BHXH | Tổng hợp đóng BHXH | Tháng |
| Báo cáo thuế TNCN | Tổng hợp khấu trừ | Tháng |
| Báo cáo tạm ứng | Công nợ tạm ứng | Tháng |
| Báo cáo phép năm | Tồn phép từng NV | Quý |
| Dự toán lương | So sánh thực tế vs kế hoạch | Tháng |
| Báo cáo thanh tra | Full history cho 1 NV | Theo yêu cầu |

### 11.5 Statutory Reports

| Report | Form code | Gửi cho | Hạn |
|---|---|---|---|
| Tờ khai thuế TNCN | 05/KK-TNCN | Cơ quan thuế | 20 hàng tháng |
| Quyết toán thuế TNCN | 05/QTT-TNCN | Cơ quan thuế | 31/03 năm sau |
| Danh sách LĐ tham gia BHXH | D02-LT | Cơ quan BHXH | 30 hàng tháng |
| Danh sách điều chỉnh BHXH | D01-TS | Cơ quan BHXH | Khi có biến động |
| Báo cáo tình hình SDLĐ | — | Sở LĐ-TB&XH | 15/06 và 15/12 |
| Báo cáo sử dụng lao động nước ngoài | — | Sở LĐ-TB&XH | Khi có biến động |
| Chứng từ khấu trừ thuế TNCN | — | Nhân viên | Trước 31/01 năm sau |

---

## 12. SME Payroll Pain Analysis

### 12.1 Excel Payroll Chaos

| Vấn đề | Mô tả | Tần suất | Hậu quả |
|---|---|---|---|
| Sai công thức | Kéo ô sai, lỗi VLOOKUP, thiếu hàng | 30-50% tháng | Sai lương |
| Version control | Nhiều file Excel, không biết file nào đúng | Hàng tháng | Sai số liệu |
| Không audit | Ai sửa? Sửa gì? Khi nào? | Liên tục | Mất kiểm soát |
| Chậm | 3-5 ngày/tháng xử lý Excel | Liên tục | Chậm lương |
| Lỗi tổng hợp | Cộng sai, thiếu người | Thường xuyên | Sai báo cáo |

### 12.2 Wrong Attendance

| Vấn đề | Mô tả | Hậu quả |
|---|---|---|
| Quên chấm công | Nhân viên quên check-in/out | Mất công, khiếu nại |
| Chấm công hộ | Nhân viên nhờ người khác chấm hộ | Gian lận công |
| Nhầm ca | Ghi sai ca làm việc | Sai lương tăng ca |
| Thiết bị hỏng | Máy chấm công hỏng, mất dữ liệu | Không có cơ sở tính lương |

### 12.3 Wrong Overtime

| Vấn đề | Mô tả | Hậu quả |
|---|---|---|
| Không đăng ký trước | Tăng ca không được duyệt | Trả lương sai |
| Sai hệ số | Áp dụng sai hệ số (thường → lễ) | Trả thiếu/trả thừa |
| Vượt giới hạn | Không kiểm soát 200h/năm | Phạt 5-50tr |
| Tính thủ công | Excel tính tăng ca sai | Nhân viên khiếu nại |

### 12.4 Missing Insurance

| Vấn đề | Mô tả | Hậu quả |
|---|---|---|
| Không đăng ký kịp | Quên đăng ký BHXH cho NV mới | Phạt, nhân viên thiệt thòi |
| Sai lương đóng BHXH | Không cập nhật lương mới | Phạt, truy thu |
| Sai mã NV | Trùng mã BHXH | Không tra cứu được |
| Chậm nộp | Nộp BHXH sau hạn | Lãi chậm đóng (0.03%/ngày) |
| Quên điều chỉnh | Khi lương thay đổi, quên điều chỉnh BHXH | Phạt, truy thu |

### 12.5 Wrong PIT Calculation

| Vấn đề | Mô tả | Hậu quả |
|---|---|---|
| Sai biểu thuế | Dùng biểu 7 bậc thay vì 5 bậc (2026) | Tính sai thuế |
| Sai giảm trừ | Không cập nhật 15.5tr/6.2tr | Nộp thừa/thiếu |
| Thiếu NPT | Nhân viên có NPT nhưng không khai báo | Nộp thừa thuế |
| Sai lương đóng BHXH | BHXH tính sai → TNTT sai | Tính thuế sai |
| Quên quyết toán | Cuối năm không quyết toán TNCN | Phạt chậm |

### 12.6 Late Payroll Processing

| Vấn đề | Mô tả | Hậu quả |
|---|---|---|
| Chậm chấm công | Trưởng bộ phận duyệt chậm | Chậm toàn bộ quy trình |
| Chậm tính lương | Kế toán quá tải | Nhân viên không nhận lương đúng hạn |
| Chậm duyệt | Giám đốc đi công tác | Chi lương muộn |
| Lỗi NH | File chuyển khoản sai format | Chuyển tiền thất bại |

### 12.7 Salary Leakage — Thất thoát lương

| Vấn đề | Mô tả | Thiệt hại ước tính |
|---|---|---|
| Nhân viên ảo | Có tên trong payroll nhưng không đi làm | 5-15% quỹ lương |
| Tăng ca ảo | Khai tăng ca không có thật | 5-10% quỹ tăng ca |
| Sai phụ cấp | Tính thừa phụ cấp | 2-5% quỹ lương |
| Chi trùng | Chuyển khoản 2 lần cho 1 người | Tốn thời gian thu hồi |

### 12.8 Duplicate Payment

| Vấn đề | Mô tả | Hậu quả |
|---|---|---|
| Chi lương 2 lần | Chuyển khoản trùng | Mất tiền, khó đòi lại |
| Chi sai người | Chuyển nhầm tài khoản | Mất tiền |
| Chi người đã nghỉ | Vẫn chuyển lương cho NV đã nghỉ | Mất tiền, khó đòi |

### 12.9 Weak Approval Control

| Vấn đề | Mô tả | Hậu quả |
|---|---|---|
| Một người làm hết | HR vừa nhập, vừa duyệt, vừa chi | Gian lận, mất kiểm soát |
| Không phân quyền | Ai cũng sửa được lương | Sai lương, mất audit |
| Duyệt muộn | TP duyệt chấm công sau khi đã chi | Không kiểm soát được |

### 12.10 Weak Audit Trail

| Vấn đề | Mô tả | Hậu quả |
|---|---|---|
| Không lưu lịch sử | Không biết ai sửa, sửa gì | Không kiểm toán được |
| Không backup | Mất dữ liệu → không có cơ sở trả lương | Kiện tụng |
| Thanh tra không đáp ứng | Không xuất được báo cáo | Phạt, đình chỉ |

### 12.11 HR vs Accounting Mismatch

| Vấn đề | Mô tả | Hậu quả |
|---|---|---|
| HR tính một kiểu | HR tính lương Gross khác với kế toán | Sai số liệu, mất thời gian đối chiếu |
| Kế toán tính một kiểu | Kế toán tự nhập lại từ đầu | Tốn gấp đôi công |
| Không đồng bộ | HR sửa thông tin nhưng kế toán không biết | Sai BHXH, sai lương |

### 12.12 Multi-branch Inconsistency

| Vấn đề | Mô tả | Hậu quả |
|---|---|---|
| Mỗi chi nhánh một kiểu | Cách tính lương khác nhau | Không thống nhất |
| Lương tối thiểu vùng khác | Chi nhánh vùng I vs vùng IV | Sai lương đóng BHXH |
| HR phân tán | Mỗi chi nhánh có HR riêng | Khó quản lý tập trung |
| Báo cáo không đồng nhất | Consolidate báo cáo lương khó | Mất thời gian hợp nhất |

---

## 13. Functional Rules Matrix

### 13.1 Salary Computation Rules

| Rule ID | Điều kiện | Hành vi | Mức độ | Căn cứ pháp luật |
|---|---|---|---|---|
| SAL-001 | Lương Gross < lương tối thiểu vùng | BLOCK: "Lương phải ≥ lương tối thiểu vùng" | REQUIRED | Điều 91 BLLĐ 2019, Nghị định 293/2025 |
| SAL-002 | Lương thử việc < 85% lương chính thức | BLOCK: "Lương thử việc phải ≥ 85%" | REQUIRED | Điều 26 BLLĐ 2019 |
| SAL-003 | Ngày công chuẩn = 0 hoặc > 31 | BLOCK: "Ngày công không hợp lệ" | REQUIRED | — |
| SAL-004 | Net < 0 (lương âm) | BLOCK: "Lương thực nhận không thể âm" | REQUIRED | — |
| SAL-005 | TNTT < 0 | Set TNTT = 0 (không tính thuế) | AUTO | Luật TNCN |
| SAL-006 | Thiếu lương Gross | BLOCK: "Chưa nhập lương Gross" | REQUIRED | — |
| SAL-007 | Thiếu thông tin BHXH | BLOCK: "Chưa có thông tin BHXH" | REQUIRED | — |
| SAL-008 | Nhân viên đã có lương trong kỳ | BLOCK: "Nhân viên đã được tính lương" | REQUIRED | — |
| SAL-009 | Nhân viên trạng thái "nghỉ việc" | BLOCK: "Nhân viên đã nghỉ việc" | REQUIRED | — |

### 13.2 Insurance Rules

| Rule ID | Điều kiện | Hành vi | Mức độ | Căn cứ pháp luật |
|---|---|---|---|---|
| INS-001 | Lương đóng BHXH > 46,800,000 (trần) | Tự động cắt trần: lương đóng = 46,800,000 | AUTO | Luật BHXH 2024, Nghị định 158/2025 |
| INS-002 | Lương đóng BHTN > trần vùng | Tự động cắt trần theo vùng | AUTO | Luật Việc làm 2025 |
| INS-003 | Lương Gross < lương tối thiểu vùng | Lương đóng BHXH = lương tối thiểu vùng | AUTO | — |
| INS-004 | NV thử việc, HĐLĐ < 1 tháng | Không đóng BHXH | AUTO | Luật BHXH 2024 |
| INS-005 | NV thử việc, HĐLĐ ≥ 1 tháng | PHẢI đóng BHXH | AUTO | Luật BHXH 2024 |
| INS-006 | Nghỉ không lương > 14 ngày/tháng | Không đóng BHXH tháng đó | AUTO | — |
| INS-007 | Tỷ lệ BHXH thay đổi | Config-driven, không hardcode | ARCH | — |

### 13.3 Overtime Rules

| Rule ID | Điều kiện | Hành vi | Mức độ | Căn cứ pháp luật |
|---|---|---|---|---|
| OVT-001 | Tăng ca > 12 giờ/ngày | BLOCK: "Vượt giới hạn 12h/ngày" | REQUIRED | Điều 107 BLLĐ 2019 |
| OVT-002 | Tăng ca > 40 giờ/tháng | BLOCK: "Vượt giới hạn 40h/tháng" | REQUIRED | Điều 107 BLLĐ 2019 |
| OVT-003 | Tăng ca > 200 giờ/năm (ngành thường) | BLOCK: "Vượt giới hạn 200h/năm" | REQUIRED | Điều 107 BLLĐ 2019 |
| OVT-004 | Tăng ca > 300 giờ/năm (ngành đặc thù) | BLOCK + yêu cầu phê duyệt đặc biệt | REQUIRED | Điều 107 BLLĐ 2019 |
| OVT-005 | Không có đăng ký tăng ca trước | WARN: "Tăng ca chưa được đăng ký" | WARN | — |
| OVT-006 | Tăng ca ban đêm ngày thường | Hệ số 195% (150% × 1.3) | AUTO | Điều 98 BLLĐ 2019 |
| OVT-007 | Tăng ca ban đêm ngày lễ | Hệ số 390% (300% × 1.3) | AUTO | Điều 98 BLLĐ 2019 |
| OVT-008 | Không có chấm công thực tế tăng ca | WARN: "Không có check-in/out tăng ca" | WARN | — |

### 13.4 PIT Rules

| Rule ID | Điều kiện | Hành vi | Mức độ | Căn cứ pháp luật |
|---|---|---|---|---|
| PIT-001 | TNTT ≤ 0 | Thuế TNCN = 0 | AUTO | Luật TNCN 2025 |
| PIT-002 | Giảm trừ bản thân sai | Tự động áp 15,500,000 | AUTO | Nghị quyết 110/2025 |
| PIT-003 | Giảm trừ NPT: chưa có MST | BLOCK: "Cần MST người phụ thuộc" | REQUIRED | Luật TNCN |
| PIT-004 | Giảm trừ NPT: đã hết hiệu lực | WARN + tự động ngừng giảm trừ | WARN | — |
| PIT-005 | Biểu thuế 5 bậc | Tự động tra bảng | AUTO | Luật TNCN 2025 |
| PIT-006 | Miễn thuế tăng ca | Tự động tách phần tăng ca khỏi TNCT | AUTO | Nghị quyết 110/2025 |
| PIT-007 | NPT > 5 | WARN: "Số NPT cao, cần kiểm tra" | WARN | — |

### 13.5 Attendance Rules

| Rule ID | Điều kiện | Hành vi | Mức độ |
|---|---|---|---|
| ATT-001 | Đi muộn ≥ 30 phút | Tính 1 lần đi muộn | AUTO |
| ATT-002 | Về sớm ≥ 30 phút | Tính 1 lần về sớm | AUTO |
| ATT-003 | 3 lần đi muộn | Quy đổi = 1 ngày công mất | AUTO (configurable) |
| ATT-004 | Quên chấm công + không báo | Tính 0.5 công | AUTO |
| ATT-005 | Vắng không lý do | 0 công, không lương | AUTO |
| ATT-006 | NV chưa duyệt chấm công | Không tính lương (draft OK) | REQUIRED |

### 13.6 Final Settlement Rules

| Rule ID | Điều kiện | Hành vi | Mức độ | Căn cứ pháp luật |
|---|---|---|---|---|
| FST-001 | Thời gian làm việc ≥ 12 tháng | Có trợ cấp thôi việc | REQUIRED | Điều 46 BLLĐ 2019 |
| FST-002 | Thời gian đã đóng BHTN | Trừ khỏi thời gian tính trợ cấp | AUTO | — |
| FST-003 | Trợ cấp mất việc < 2 tháng lương | Set = 2 tháng lương (tối thiểu) | AUTO | Điều 47 BLLĐ 2019 |
| FST-004 | Phép năm còn lại > 0 | Thanh toán bằng tiền | AUTO | Điều 113 BLLĐ 2019 |
| FST-005 | NV chưa chốt sổ BHXH | BLOCK trước khi cập nhật "Đã nghỉ" | REQUIRED | — |

### 13.7 Period Closing Rules

| Rule ID | Điều kiện | Hành vi | Mức độ |
|---|---|---|---|
| PRD-001 | Kỳ đã đóng = khóa | READ-ONLY, không sửa/xóa | REQUIRED |
| PRD-002 | Mở lại kỳ đã đóng | Chỉ CFO/Giám đốc, audit trail bắt buộc | REQUIRED |
| PRD-003 | Post vào kỳ sai | BLOCK: "Kỳ kế toán đã đóng" | REQUIRED |
| PRD-004 | Chốt lương khi còn NV chưa tính | BLOCK: "Còn NV chưa tính lương" | REQUIRED |
| PRD-005 | Chốt lương khi chấm công chưa duyệt | BLOCK: "Chấm công chưa duyệt" | REQUIRED |

### 13.8 Segregation of Duties Rules

| Rule ID | Điều kiện | Hành vi | Mức độ |
|---|---|---|---|
| SOD-001 | HR tự duyệt chấm công của mình | BLOCK: "Không tự duyệt" | REQUIRED |
| SOD-002 | Người tính lương = Người duyệt lương | BLOCK: "Phân tách nhiệm vụ" | REQUIRED |
| SOD-003 | Sửa lương Gross không có phê duyệt | BLOCK + audit | REQUIRED |
| SOD-004 | Thay đổi > 10% lương | Cần 2 người duyệt | REQUIRED |

### 13.9 Fraud Detection Rules

| Rule ID | Điều kiện | Hành vi | Mức độ |
|---|---|---|---|
| FRD-001 | NV không có chấm công nhưng có lương | BLOCK hoặc WARN CAO | CAO |
| FRD-002 | Lương tăng > 30% so với tháng trước | WARN + yêu cầu xác nhận | CAO |
| FRD-003 | 2 NV chung 1 tài khoản NH | WARN CAO | CAO |
| FRD-004 | NV đã nghỉ nhưng vẫn trong payroll | BLOCK | CAO |
| FRD-005 | Tăng ca > 60h/tháng | WARN CAO | CAO |

### 13.10 Regulatory Compliance Rules

| Rule ID | Yêu cầu | Hành vi | Căn cứ |
|---|---|---|---|
| REG-001 | Đăng ký BHXH trong 30 ngày | Nhắc nhở tự động | Luật BHXH 2024 |
| REG-002 | Nộp BHXH trước ngày 30 | Nhắc nhở + cảnh báo | — |
| REG-003 | Nộp thuế TNCN trước 20 tháng sau | Nhắc nhở | Luật TNCN |
| REG-004 | Quyết toán TNCN trước 31/03 | Nhắc nhở + cảnh báo | Luật TNCN |
| REG-005 | Báo cáo tình hình SDLĐ | Nhắc nhở 15/06 và 15/12 | — |
| REG-006 | Lưu trữ hồ sơ lương tối thiểu 5 năm | Cảnh báo khi xóa | Luật KT 2015 |

---

## 14. Final Deliverables

### 14.1 Database Schema — New Tables

| Table | Mục đích | Ghi chú |
|---|---|---|
| `employees` | Employee master data | Lương, phụ cấp, BHXH, NPT, TK NH |
| `allowances` | Danh sách phụ cấp (config) | Loại, tỷ lệ, miễn thuế, đóng BHXH |
| `employee_allowances` | Phụ cấp gán cho từng NV | Có ngày hiệu lực |
| `salary_periods` | Kỳ lương (tháng/năm) | Trạng thái: open/approved/closed |
| `salary_payslips` | Bảng lương từng NV | Gross, BHXH, TNTT, thuế, Net |
| `salary_details` | Chi tiết các khoản của payslip | Lương CB, tăng ca, phụ cấp, khấu trừ |
| `attendance_records` | Chấm công | Ngày, giờ vào/ra, loại |
| `attendance_summary` | Tổng hợp chấm công tháng | Số công, tăng ca, đi muộn |
| `leave_requests` | Đơn nghỉ phép | Loại, ngày, trạng thái duyệt |
| `overtime_requests` | Đăng ký tăng ca | Giờ, loại, trạng thái duyệt |
| `salary_advances` | Tạm ứng lương | Số tiền, đã trừ/chưa |
| `salary_adjustments` | Điều chỉnh lương hồi tố | Audit trail + lý do |
| `pit_dependents` | Người phụ thuộc của NV | MST, hiệu lực |
| `payroll_config` | Config tỷ lệ BHXH, biểu thuế | Có hiệu lực từ ngày |

### 14.2 Service Layer

| Service | Responsibility | Key Methods |
|---|---|---|
| `PayrollCalculationService` | Tính lương engine | `calculateGross()`, `calculateInsurance()`, `calculatePIT()`, `calculateNet()` |
| `SalaryPeriodService` | Quản lý kỳ lương | `createPeriod()`, `closePeriod()`, `reopenPeriod()` |
| `AttendanceService` | Chấm công | `processAttendance()`, `validateAttendance()`, `summarize()` |
| `PayslipService` | Bảng lương | `generatePayslip()`, `batchGenerate()`, `sendPayslip()` |
| `SalaryPaymentService` | Chi lương | `generateBankFile()`, `recordPayment()`, `reconcile()` |
| `PayrollPostingService` | Hạch toán | `generateJournalEntries()`, `postToGL()` |
| `BhxhDeclarationService` | Kê khai BHXH | `generateD02LT()`, `generateD01TS()` |
| `PitDeclarationService` | Kê khai TNCN | `generate05KK()`, `yearEndSettlement()` |
| `FinalSettlementService` | Quyết toán nghỉ việc | `calculateSeverance()`, `finalPay()` |
| `PayrollAuditService` | Audit trail | `logChange()`, `getHistory()`, `exportAudit()` |

### 14.3 Controller / API Endpoints

| Method | Endpoint | Mô tả |
|---|---|---|
| GET | `/api/payroll/employees` | Danh sách nhân viên |
| POST | `/api/payroll/employees` | Thêm nhân viên |
| PUT | `/api/payroll/employees/{id}` | Sửa nhân viên |
| GET | `/api/payroll/employees/{id}/payslips` | Lịch sử lương NV |
| POST | `/api/payroll/attendance/import` | Import chấm công |
| POST | `/api/payroll/attendance/approve` | Duyệt chấm công |
| GET | `/api/payroll/calculate/draft` | Tính lương thử |
| POST | `/api/payroll/calculate/run` | Chạy payroll |
| POST | `/api/payroll/payslips/approve` | Duyệt bảng lương |
| POST | `/api/payroll/payslips/close` | Chốt lương |
| GET | `/api/payroll/payslips/export` | Export bảng lương |
| POST | `/api/payroll/payment/bank-file` | Sinh file chuyển khoản |
| POST | `/api/payroll/payment/cash` | Ghi nhận chi tiền mặt |
| POST | `/api/payroll/posting/generate` | Sinh bút toán |
| GET | `/api/payroll/reports/summary` | Báo cáo tổng hợp |
| GET | `/api/payroll/reports/bhxh` | Báo cáo BHXH |
| GET | `/api/payroll/reports/tncn` | Báo cáo TNCN |
| POST | `/api/payroll/advances` | Tạm ứng lương |
| POST | `/api/payroll/adjustments` | Điều chỉnh lương |
| POST | `/api/payroll/final-settlement/{id}` | Quyết toán nghỉ việc |

### 14.4 Views / UI Screens

| Screen | Mô tả | Module |
|---|---|---|
| Employee Master | Quản lý hồ sơ nhân viên | HR |
| Employee Detail | Chi tiết 1 nhân viên (lương, phụ cấp, lịch sử) | HR |
| Attendance Import | Import/ nhập chấm công | HR |
| Attendance Approval | Duyệt chấm công | HR |
| Payroll Run | Chạy tính lương | Payroll |
| Payslip List | Bảng lương tháng | Payroll |
| Payslip Detail | Chi tiết lương 1 nhân viên | Payroll |
| Payroll Approval | Duyệt bảng lương | Payroll |
| Payment | Chi lương (CK/TM) | Payroll |
| Bank File Generator | Sinh file chuyển khoản | Payroll |
| Posting Preview | Xem bút toán trước khi ghi | Accounting |
| BHXH Declaration | Kê khai BHXH | Tax |
| PIT Declaration | Kê khai TNCN | Tax |
| Year-end Settlement | Quyết toán TNCN | Tax |
| Final Settlement | Quyết toán nghỉ việc | HR/Payroll |
| Salary Advance | Tạm ứng lương | HR |
| Salary Adjustment | Điều chỉnh lương hồi tố | Payroll |
| Reports | Báo cáo lương (chi tiết, tổng hợp, phân bổ) | Reports |
| Config | Cấu hình tỷ lệ, biểu thuế, lương tối thiểu | Admin |

### 14.5 Test Plan

| Test Suite | Scope | Số lượng test (tối thiểu) |
|---|---|---|
| Employee Master | CRUD, validation, import | 15 |
| Attendance | Import, validate, approve | 15 |
| Salary Calculation | Gross, BHXH (various ceilings), PIT (all 5 brackets), Net | 25 |
| Overtime | All 6 overtime types, limits | 12 |
| Allowance | Taxable vs non-taxable, BHXH vs non-BHXH | 10 |
| Insurance | Ceilings, floors, rates, foreign worker | 12 |
| PIT | All brackets, dependents, deductions, thresholds | 15 |
| Payslip | Generate, PDF, email | 8 |
| Approval Workflow | All roles, reject, reopen, audit | 15 |
| Payment | Bank file, cash, duplicate check | 10 |
| Posting | Journal entries, Dr = Cr, account codes | 12 |
| Final Settlement | Severance, prorated, annual leave | 10 |
| Period Closing | Lock, reopen, audit | 8 |
| Fraud Detection | All rules | 10 |
| Integration | JournalService, AccountRepository, AuditLogger | 8 |
| **Tổng** | | **~185 tests** |

### 14.6 Implementation Phases

| Phase | Nội dung | Thời gian ước tính |
|---|---|---|
| **P0 — Core** | Employee master, config, attendance, salary calculation, payslip | 2 tuần |
| **P0 — Approval** | Workflow approval (3 cấp), period management, audit trail | 1 tuần |
| **P0 — Payment** | Payment (CK/TM), bank file, posting to GL | 1 tuần |
| **P1 — Reports** | Reports (summary, detail, PIT, BHXH), payslip PDF | 1 tuần |
| **P1 — Tax** | BHXH declaration, PIT declaration, year-end settlement | 1 tuần |
| **P2 — Advanced** | Final settlement, adjustment, advances, leave management | 1 tuần |
| **P3 — Integration** | Mobile check-in, bank statement reconciliation, HR import/export | 1 tuần |

### 14.7 Acceptance Criteria

| Criterion | Mô tả | Pass/Fail |
|---|---|---|
| AC-01 | Tính lương Gross đúng cho 10+ kịch bản khác nhau | ✅ Pass |
| AC-02 | BHXH tự động tính đúng trần/sàn/tỷ lệ | ✅ Pass |
| AC-03 | PIT tự động tính đúng biểu 5 bậc | ✅ Pass |
| AC-04 | Dr = Cr cho mọi bút toán lương | ✅ Pass |
| AC-05 | Workflow duyệt 3 cấp hoạt động đúng | ✅ Pass |
| AC-06 | Period locking: không sửa được kỳ đã đóng | ✅ Pass |
| AC-07 | Audit trail ghi đầy đủ mọi thay đổi | ✅ Pass |
| AC-08 | Payment file đúng format, không trùng | ✅ Pass |
| AC-09 | Fraud detection phát hiện 7+ dấu hiệu | ✅ Pass |
| AC-10 | Test suite ≥ 185 tests, 0 failures | ✅ Pass |

---

> **Tuyên bố:** Tài liệu này được xây dựng dựa trên các quy định pháp luật Việt Nam cập nhật đến tháng 05/2026. Các tỷ lệ BHXH, biểu thuế TNCN, mức giảm trừ gia cảnh và lương tối thiểu vùng được căn cứ theo Luật BHXH 2024, Luật Thuế TNCN 2025, Luật Việc làm 2025, Nghị định 293/2025/NĐ-CP, Nghị định 158/2025, Nghị quyết 110/2025/UBTVQH15, Nghị định 105/2026/NĐ-CP và các văn bản hướng dẫn liên quan. Doanh nghiệp cần cập nhật khi có thay đổi về chính sách.

> **Đối tượng đọc:** Ban Giám đốc (Executive Summary), Kế toán trưởng (Internal Control), BA Lead (Functional Spec), Developers (Technical Spec), Auditors (Compliance).
