# Tax Engine — Chief Accountant & BA Lead Analysis

> **Phiên bản:** 1.0
> **Phạm vi:** Module Thuế — VAT (GTGT), CIT (TNDN), PIT (TNCN), E-invoice, Foreign Contractor Tax
> **Đối tượng:** BA Lead, Chief Accountant, Tax Manager, Finance Controller, Auditors
> **Cập nhật:** 2026-05-29
> **Cơ sở pháp lý:** Luật VAT 48/2024/QH15 (01/07/2025), Luật TNDN 67/2025/QH15 (01/01/2026), Luật TNCN 109/2025/QH15 (2026), TT 20/2026/TT-BTC, NĐ 70/2025/NĐ-CP, TT 69/2025/TT-BTC, NĐ 320/2025/NĐ-CP, TT 32/2025/TT-BTC, NĐ 181/2025/NĐ-CP

---

## Mục lục

1. [Executive Business Analysis](#1-executive-business-analysis)
2. [Scope Definition](#2-scope-definition)
3. [TAX Functional Specification](#3-tax-functional-specification)
4. [Full TAX Lifecycle Analysis](#4-full-tax-lifecycle-analysis)
5. [Use Cases](#5-use-cases)
6. [TAX Engine Logic](#6-tax-engine-logic)
7. [Workflow & Process Analysis](#7-workflow--process-analysis)
8. [Data Flow Analysis (Business View)](#8-data-flow-analysis-business-view)
9. [User Journey Analysis](#9-user-journey-analysis)
10. [Validation & Internal Control Rules](#10-validation--internal-control-rules)
11. [Reporting & Reconciliation Analysis](#11-reporting--reconciliation-analysis)
12. [SME & Enterprise Pain Analysis](#12-sme--enterprise-pain-analysis)
13. [Functional Rules Matrix](#13-functional-rules-matrix)
14. [Final Deliverables](#14-final-deliverables)

---

## 1. Executive Business Analysis

### 1.1 Why TAX Module Exists

Thuế là nghĩa vụ pháp lý bắt buộc của mọi doanh nghiệp tại Việt Nam. Module Thuế trong ERP không phải là optional — nó là **sống còn**. Mỗi giao dịch kinh tế đều phát sinh nghĩa vụ thuế:

- **Bán hàng** → Output VAT (GTGT đầu ra) — TK 33311
- **Mua hàng** → Input VAT (GTGT đầu vào) — TK 1331/1332
- **Trả lương** → PIT (TNCN) — TK 3335
- **Lợi nhuận** → CIT (TNDN) — TK 8211/3334
- **Nhập khẩu** → Import VAT + Customs duties
- **Dịch vụ từ nước ngoài** → Foreign Contractor Tax (FCT)

Module Thuế tồn tại vì một lý do đơn giản: **Excel không thể quản lý thuế an toàn cho doanh nghiệp quy mô SME/Enterprise Việt Nam.**

### 1.2 Business Objectives của Tax Control

| Mục tiêu | Mô tả | KPI |
|---|---|---|
| **Tuân thủ pháp luật** | Đảm bảo kê khai đúng hạn, đúng số liệu | 0 phạt chậm nộp, 0 truy thu |
| **Tối ưu thuế hợp pháp** | Tận dụng tối đa khấu trừ VAT, chi phí hợp lý CIT | Giảm thuế phải nộp ≤ 5% so với mức tối thiểu hợp pháp |
| **Audit traceability** | Mọi bút toán thuế đều trace được từ chứng từ gốc | Audit trail đầy đủ, ≤ 1 giờ truy xuất |
| **Kiểm soát rủi ro** | Phát hiện VAT mismatch, sai tỷ lệ khấu trừ | 100% mismatch detected trước khi nộp tờ khai |
| **Tự động hóa** | Giảm thao tác thủ công, tăng chính xác | ≥ 90% số liệu tờ khai tự động từ giao dịch |

### 1.3 Enterprise Pain Points Được Giải Quyết

| Pain Point | Mức độ | Hậu quả | Giải pháp trong TAX module |
|---|---|---|---|
| **Sai tỷ lệ khấu trừ VAT** | 🔴 Nghiêm trọng | Truy thu + phạt 10-20% trên số thuế sai | Tax rate engine tự động gán rate dựa trên mã hàng/ dịch vụ + loại invoice |
| **Bỏ sót hóa đơn đầu vào** | 🟡 Trung bình | Mất quyền khấu trừ, nộp thừa thuế | Invoice matching engine: đối chiếu hóa đơn đã kê khai vs hóa đơn đã nhập |
| **Khai sai thuế suất 8% vs 10%** | 🔴 Nghiêm trọng | Phạt khai sai + truy thu | Forced rate check: không cho xuất hóa đơn nếu không xác định được rate |
| **CIT adjustment sai** | 🔴 Nghiêm trọng | Sai quyết toán thuế TNDN | CIT adjustment matrix: tự động xác định chi phí không được trừ |
| **PIT khấu trừ sai** | 🟡 Trung bình | Sai quyết toán TNCN | PIT engine: xử lý 5 bậc thuế mới 2026, giảm trừ gia cảnh |
| **Quên hạn nộp tờ khai** | 🟡 Trung bình | Phạt chậm nộp 0.03%/ngày | Deadline tracking + notification engine |
| **Multi-branch không đồng bộ** | 🟠 Cao | Sai số liệu tờ khai hợp nhất | Branch tax segregation: riêng từng chi nhánh cho khai thuế riêng |
| **Excel không trace được audit** | 🔴 Nghiêm trọng | Rủi ro kiểm toán, mất 3-5 ngày chuẩn bị | Audit trail built-in, mọi thay đổi có timestamp + actor |

### 1.4 SME Tax Operational Pain

SME Việt Nam (doanh thu < 50 tỷ/năm) đối mặt với những vấn đề thuế đặc thù:

1. **Excel hell**: Kế toán thuế dùng Excel để tổng hợp VAT đầu vào/đầu ra, copy-paste từ phần mềm hóa đơn điện tử sang tờ khai HTKK. Sai số do typo là chuyện thường ngày.

2. **Không phân biệt được 8% vs 10%**: Từ 2024-2026, chính sách giảm thuế 2% (từ 10% xuống 8%) cho một số nhóm hàng hóa/dịch vụ. SME thường xuyên nhầm lẫn — áp dụng 8% cho mặt hàng không được giảm, hoặc không áp dụng 8% cho mặt hàng được giảm. Kết quả là hóa đơn sai, khai thuế sai, bị phạt.

3. **Hóa đơn đầu vào không đủ điều kiện khấu trừ**: Thiếu chứng từ thanh toán không dùng tiền mặt (từ 01/07/2025, ngưỡng giảm từ 20M xuống 5M — **rủi ro rất lớn**). Hóa đơn sai tên, sai MST, sai địa chỉ đều dẫn đến mất quyền khấu trừ.

4. **CIT không biết chi phí nào bị loại**: SME thường cho toàn bộ chi phí vào chi phí hợp lý, không biết rằng chi phí không có hóa đơn đủ điều kiện, chi phí quảng cáo vượt 10% (TT 20/2026), chi phí lãi vay vượt 30% EBITDA (NĐ 132/2020) sẽ bị loại khi quyết toán.

5. **PIT không cập nhật giảm trừ mới**: Từ 2026, giảm trừ gia cảnh là 15.5M (bản thân) + 6.2M (NPT). Nhiều SME vẫn áp dụng mức cũ 11M + 4.4M.

6. **E-invoice lằng nhằng**: Chuyển đổi hóa đơn, điều chỉnh hóa đơn, hủy hóa đơn — quy trình rắc rối, nhiều SME sai thao tác dẫn đến vi phạm.

7. **Khai thuế nhầm kỳ**: Một số SME khai thuế theo tháng, một số theo quý. Nhầm kỳ dẫn đến nộp chậm.

8. **Thiếu audit trail**: Khi cơ quan thuế kiểm tra, SME không chứng minh được nguồn gốc số liệu tờ khai.

### 1.5 Mối Quan Hệ Giữa Kế Toán và Thuế

**Kế toán và thuế là hai mặt của một đồng xu, nhưng không phải lúc nào cũng trùng khớp.**

| Nghiệp vụ | Kế toán (Circular 99) | Thuế (Luật thuế) | Chênh lệch |
|---|---|---|---|
| Khấu hao TSCĐ | Theo TT 99 | Theo khung thời gian tối thiểu (TT 20/2026) | Có thể khác — phải điều chỉnh CIT |
| Chi phí trích trước | Được ghi nhận | Chỉ được trừ khi thực tế phát sinh | Loại khi quyết toán |
| Dự phòng | Được trích lập | Không được trừ trừ khi đủ điều kiện | Phải loại ra khỏi chi phí thuế |
| Đánh giá lại tài sản | Được ghi nhận | Không được trừ cho đến khi thực tế phát sinh | Chênh lệch tạm thời |
| Doanh thu chưa thực hiện | Ghi nhận là nợ phải trả | Có thể bị tính thuế ngay | Khác biệt vĩnh viễn/tạm thời |

Module Thuế phải xử lý cả hai góc nhìn: kế toán (cho BC01/02/03) và thuế (cho tờ khai).

### 1.6 Mối Quan Hệ Giữa TAX Module và E-Invoice

E-invoice (hóa đơn điện tử) là **nguồn dữ liệu đầu vào chính** của TAX module:

```
Hóa đơn bán ra (e-invoice) → Output VAT → VAT declaration
Hóa đơn mua vào (e-invoice) → Input VAT → VAT declaration
```

Tích hợp TAX module với e-invoice:
- **Một chiều bắt buộc**: TAX module phải nhận dữ liệu từ e-invoice system (FPT, MISA, Viettel, BKAV...)
- **Hai chiều lý tưởng**: TAX module gửi thông tin hóa đơn cần điều chỉnh/hủy cho e-invoice system
- **Real-time nếu có thể**: Cập nhật trạng thái hóa đơn ngay khi phát sinh

### 1.7 Mối Quan Hệ Giữa TAX Module và Audit/Tax Inspection

Kiểm tra thuế (thanh tra, kiểm tra tại doanh nghiệp) là **stress test lớn nhất của bộ máy kế toán**.

TAX module phải:
- **Chứng minh số liệu tờ khai**: Trace từ tờ khai → bảng kê → hóa đơn → chứng từ gốc
- **Cung cấp audit trail**: Ai làm gì, khi nào, thay đổi gì
- **So sánh số liệu**: Kế toán vs thuế, giải thích chênh lệch
- **Xuất báo cáo kiểm toán**: BC01/02/03 điều chỉnh theo thuế, reconciliation VAT

### 1.8 Risk of Excel/Manual Tax Processing

| Risk | Probability | Impact | Mitigation Through TAX Module |
|---|---|---|---|
| Key-in sai số liệu | Cao | Sai tờ khai → phạt | Auto-populate từ giao dịch gốc |
| Bỏ sót hóa đơn | Trung bình | Mất khấu trừ VAT | Hóa đơn matching engine |
| Nhầm kỳ khai thuế | Thấp | Phạt chậm nộp | Date validation + period lock |
| Sai thuế suất | Cao | Khai sai → phạt truy thu | Rate enforced at source |
| Mất chứng từ | Cao | Audit fail, mất khấu trừ | Digital attachment storage |
| Không trace được | Cao | Audit mất 3-7 ngày | Full audit trail built-in |

### 1.9 Vietnam Tax Authority Expectations

Cục Thuế mong đợi:
1. **Khai đúng hạn**: Ngày 20 tháng sau (tháng), ngày 30 sau quý
2. **Số liệu khớp**: Tổng số thuế khai = số thuế trên hóa đơn điện tử đã truyền
3. **Nộp đủ**: Số tiền nộp = số thuế phải nộp trên tờ khai
4. **Audit trail đầy đủ**: Hóa đơn, chứng từ thanh toán, hợp đồng
5. **Chứng từ hợp lệ**: Hóa đơn hợp pháp, đúng MST, đúng thông tin
6. **Phản hồi nhanh**: Trả lời công văn, giải trình số liệu
7. **Chuyển đổi số**: Sẵn sàng dữ liệu điện tử, không cần nộp bản giấy

---

## 2. Scope Definition

### 2.1 In-Scope Features

| Feature | Priority | Module | Mô tả |
|---|---|---|---|
| Tax master data | P0 | Tax | Tax codes, rates, VAT groups, account mapping |
| Output VAT tracking | P0 | VAT | Tự động ghi nhận VAT đầu ra từ hóa đơn bán hàng |
| Input VAT tracking | P0 | VAT | Tự động ghi nhận VAT đầu vào từ hóa đơn mua hàng |
| VAT declaration preparation | P0 | VAT | Tạo bảng kê 01/GTGT, tờ khai 01/GTGT |
| VAT input/output reconciliation | P1 | VAT | Đối chiếu số phát sinh VAT giữa GL và tờ khai |
| VAT refund management | P2 | VAT | Hồ sơ hoàn thuế, tracking trạng thái |
| CIT expense adjustment | P0 | CIT | Xác định chi phí không được trừ khi tính thuế |
| CIT temporary payment | P0 | CIT | Tạm nộp TNDN quý (tối thiểu 80% số phải nộp) |
| CIT finalization | P0 | CIT | Quyết toán thuế TNDN năm, tờ khai 03/TNDN |
| CIT carryforward tracking | P1 | CIT | Tracking lỗ được kết chuyển (tối đa 5 năm) |
| CIT incentive management | P2 | CIT | Quản lý ưu đãi thuế (miễn giảm, thuế suất ưu đãi) |
| PIT monthly declaration | P0 | PIT | Tờ khai khấu trừ thuế TNCN (05/KK-TNCN) |
| PIT finalization | P0 | PIT | Quyết toán thuế TNCN năm |
| PIT dependent registration | P1 | PIT | Đăng ký người phụ thuộc, giảm trừ gia cảnh |
| Foreign Contractor Tax | P1 | FCT | Khấu trừ thuế nhà thầu nước ngoài |
| E-invoice integration | P0 | E-Invoice | Import hóa đơn điện tử đầu vào/đầu ra |
| Tax adjustment/correction | P1 | Tax | Điều chỉnh tờ khai, khai bổ sung |
| Tax period management | P0 | Tax | Mở/đóng kỳ thuế, không cho sửa kỳ đã đóng |
| Tax reconciliation | P1 | Tax | Đối chiếu số liệu thuế với GL |
| Tax audit support | P2 | Tax | Báo cáo phục vụ kiểm tra thuế |
| Multi-branch tax handling | P1 | Tax | Tách riêng số liệu thuế theo chi nhánh |
| Tax document attachment | P1 | Tax | Đính kèm chứng từ gốc vào giao dịch thuế |
| Tax status lifecycle | P0 | Tax | Draft → Submitted → Approved → Locked |
| Tax deadline tracking | P1 | Tax | Cảnh báo hạn nộp tờ khai |
| Tax GL mapping | P0 | Tax | Tự động hạch toán các bút toán thuế |
| Tax reporting | P0 | Tax | Báo cáo thuế theo TT 99, TT 20/2026 |

### 2.2 Out-of-Scope Features

| Feature | Lý do loại trừ |
|---|---|
| E-invoice issuance engine | Đã có module hóa đơn điện tử riêng (FPT/MISA/Viettel) — TAX module chỉ nhận dữ liệu |
| Tax payment processing | Tích hợp với ngân hàng hoặc cổng nộp thuế điện tử — không phải logic ERP |
| Transfer pricing documentation | Đây là nghiệp vụ tư vấn, không phải chức năng ERP cốt lõi |
| International tax (CbCR, BEPS) | Chỉ áp dụng cho tập đoàn đa quốc gia cực lớn |
| Customs declaration | Hải quan là module riêng biệt |

### 2.3 Mandatory Controls

| Control ID | Mô tả | Basis |
|---|---|---|
| TC-001 | Không cho post giao dịch vào kỳ thuế đã đóng | TT 99, Luật Quản lý thuế |
| TC-002 | Tự động check tồn tại của mã số thuế (MST) | NĐ 70/2025 |
| TC-003 | Không cho xuất hóa đơn nếu chưa có thuế suất | NĐ 123/2020, NĐ 70/2025 |
| TC-004 | Bắt buộc nhập số hóa đơn cho mọi nghiệp vụ VAT | NĐ 123/2020 |
| TC-005 | Tự động cảnh báo khi hóa đơn ≥ 5M không có chứng từ thanh toán không dùng tiền mặt | TT 69/2025, NĐ 181/2025 |
| TC-006 | Không cho khai bổ sung khi đã có quyết định thanh tra | Luật Quản lý thuế |
| TC-007 | Bắt buộc có approval cho điều chỉnh thuế | Internal control |

### 2.4 Mandatory Compliance Requirements

| Requirement | Legal Basis | Penalty for Non-Compliance |
|---|---|---|
| Nộp tờ khai VAT đúng hạn | Luật VAT 48/2024, NĐ 125/2020 | Phạt 5-20M + 0.03%/ngày chậm nộp |
| Nộp tờ khai PIT đúng hạn | Luật QT thuế 38/2019 | Phạt 5-20M + 0.03%/ngày chậm nộp |
| Nộp tờ khai CIT tạm nộp quý | Luật TNDN 67/2025 | Phạt ≥ 20% số thiếu nếu tạm nộp < 80% |
| Xuất hóa đơn điện tử đúng hạn | NĐ 123/2020, NĐ 70/2025 | Phạt 10-30M |
| Lưu trữ hóa đơn điện tử 10 năm | NĐ 123/2020 | Phạt 5-10M |
| Thanh toán không dùng tiền mặt ≥ 5M | TT 69/2025 | Mất khấu trừ VAT |

### 2.5 Key Stakeholders

| Stakeholder | Role | Interest |
|---|---|---|
| **Kế toán thuế** | End user chính: nhập dữ liệu, kê khai, theo dõi | Tự động hóa, chính xác, đúng hạn |
| **Kế toán trưởng** | Approver, sign off tờ khai | Compliance, audit ready, accurate |
| **Giám đốc tài chính** | Oversight, budget | Tax efficiency, cash flow, risk mitigation |
| **Kiểm toán nội bộ** | Internal control review | Audit trail, segregation of duties |
| **Kiểm toán độc lập** | Audit FS + tax | Reconciliation, proper disclosure |
| **Cục Thuế** | External auditor | Compliance, accurate reporting |
| **Kế toán viên (AP/AR)** | Data entry, invoice processing | Nhập đúng thông tin thuế |

### 2.6 User Roles

| Role | Mô tả | Quyền hạn |
|---|---|---|
| Tax Accountant | Nhập liệu, kê khai, theo dõi | Read/Write trên tax transactions |
| Chief Accountant | Phê duyệt tờ khai, mở/đóng kỳ | Approve, lock/unlock periods |
| Tax Manager | Quản lý rủi ro thuế, đối chiếu | Read all, reconcile, adjust |
| Finance Controller | Giám sát tổng thể | Read all, approve adjustments |
| AP Accountant | Nhập hóa đơn đầu vào | Write input VAT data |
| AR Accountant | Xuất hóa đơn đầu ra | Write output VAT data |
| Auditor (read-only) | Kiểm tra số liệu | Read all, export reports |

### 2.7 Business Ownership Boundaries

| Nghiệp vụ | Module sở hữu dữ liệu | TAX module vai trò |
|---|---|---|
| Hóa đơn bán ra | AR/Sales Invoice | Đọc output VAT, không sửa |
| Hóa đơn mua vào | AP/Purchase Invoice | Đọc input VAT, không sửa |
| Lương và thuế TNCN | Payroll | Đọc PIT data, không sửa |
| TSCĐ khấu hao | FA | Đọc chi phí khấu hao cho CIT |
| Chứng từ ghi sổ | GL | Đọc số dư TK 133, 333 cho đối chiếu |
| Kết chuyển cuối kỳ | Period Close | Đọc dữ liệu sau kết chuyển |
| Hóa đơn điện tử | E-Invoice System | Import dữ liệu, không sửa trực tiếp |

---

## 3. TAX Functional Specification

### 3.1 Tax Master Setup

**Tax Type Master:**

| Field | Mô tả | Bắt buộc | Source |
|---|---|---|---|
| tax_type_id | UUID | ✅ | Auto-generated |
| tax_type_code | Mã loại thuế (VAT, CIT, PIT, FCT) | ✅ | User-defined |
| tax_type_name | Tên loại thuế (GTGT, TNDN, TNCN, NPT) | ✅ | User-defined |
| is_active | Còn hiệu lực? | ✅ | Default true |
| declaration_period | Tháng/Quý/Năm | ✅ | Per tax type |
| gl_account_code | TK kế toán mặc định | ✅ | Chart of accounts |
| declaration_deadline | Hạn nộp tờ khai | ✅ | Per tax type |

**Tax Code Master (Seed Data 2026):**

| tax_code | rate | effective | expiration | note |
|---|---|---|---|---|
| VAT10 | 10% | 2024-01-01 | null | Chuẩn |
| VAT8 | 8% | 2024-07-01 | 2026-12-31 | Giảm 2% (tạm thời) |
| VAT5 | 5% | 2024-01-01 | null | Thiết yếu |
| VAT0 | 0% | 2024-01-01 | null | Xuất khẩu |
| VAT_EXEMPT | 0% (exempt) | 2024-01-01 | null | Không chịu thuế |
| CIT20 | 20% | 2026-01-01 | null | Tiêu chuẩn |
| CIT17 | 17% | 2026-01-01 | null | DT 3-50 tỷ |
| CIT15 | 15% | 2026-01-01 | null | DT ≤ 3 tỷ |
| PIT_PROGRESSIVE | Lũy tiến 5 bậc | 2026-07-01 | null | 5 bậc mới |
| PIT_NONRESIDENT | 20% | 2024-01-01 | null | Cá nhân không cư trú |

**VAT Groups (Phân nhóm hàng hóa/dịch vụ để tự động gán thuế suất):**

| Group Code | Group Name | Default VAT Rate | Note |
|---|---|---|---|
| NON_ESSENTIAL | Hàng hóa thông thường | 10% → 8% (tạm thời) | Đa số |
| ESSENTIAL | Hàng thiết yếu | 5% | Nước sạch, thuốc, TB y tế |
| EXPORT | Hàng xuất khẩu | 0% | Cần bộ chứng từ xuất khẩu |
| EXEMPT | Hàng miễn thuế | Exempt | Giáo dục, y tế, bảo hiểm |
| AGRI | Nông sản thô | 5% (hoặc exempt) | Tùy chế biến |
| CONSTRUCTION | Xây dựng | 10% → 8% | Có supply vật tư? |
| IT_SERVICE | Dịch vụ CNTT | 10% | Software license |
| TRANSPORT | Vận tải | 10% | International: 0% |
| FINANCE | Tài chính NH | Exempt | Lãi vay, chứng khoán |
| REAL_ESTATE | Bất động sản | 10% | Chuyển nhượng BĐS |

**Tax Account Mapping:**

| Tax Type | GL Account | Debit/Credit | Mô tả |
|---|---|---|---|
| Input VAT (hàng hóa) | 1331 | Debit | VAT đầu vào được khấu trừ |
| Input VAT (TSCĐ) | 1332 | Debit | VAT đầu vào TSCĐ |
| Output VAT | 33311 | Credit | VAT đầu ra phải nộp |
| CIT expense | 8211 | Debit | Chi phí thuế TNDN |
| CIT payable | 3334 | Credit | Thuế TNDN phải nộp |
| PIT payable | 3335 | Credit | Thuế TNCN phải nộp |
| FCT VAT | 33312 | Credit | Thuế GTGT nhà thầu |
| FCT CIT | 3338 | Credit | Thuế TNDN nhà thầu |
| Import VAT | 33312 | Credit | Thuế GTGT hàng nhập khẩu |
| Tax penalty | 8212 | Debit | Phạt thuế |

### 3.2 Declaration Period Setup

| Tax Type | Mặc định | Có thể thay đổi? | Điều kiện |
|---|---|---|---|
| VAT | Tháng | Có thể chọn quý nếu DT < 50 tỷ/năm | Theo NĐ 181/2025 |
| CIT | Năm | Không | Cố định |
| CIT tạm nộp | Quý | Không | Bắt buộc |
| PIT | Tháng | Không | Khấu trừ tại nguồn |
| PIT quyết toán | Năm | Không | Bắt buộc |
| FCT | Phát sinh | Không | Theo từng hợp đồng |

Kỳ kê khai mặc định tạo theo năm: VAT (12 tháng/4 quý), PIT (12 tháng), CIT tạm nộp (4 quý), CIT quyết toán (1 năm). Không cho phép truy cập kỳ trước khi chưa hoàn thành kỳ hiện tại.

### 3.3 Multi-Branch Tax Handling

Nguyên tắc:
1. **Khai thuế riêng**: Mỗi chi nhánh có MST riêng → khai thuế riêng biệt
2. **Khai thuế tập trung**: Chi nhánh không có MST riêng → khai tập trung tại trụ sở chính
3. **Hóa đơn đầu vào**: Hóa đơn thuộc chi nhánh nào → ghi nhận VAT cho chi nhánh đó
4. **Phân bổ VAT**: Nếu hóa đơn dùng chung → phân bổ theo tỷ lệ doanh thu

Ví dụ: Công ty A (Hà Nội) + CN Đà Nẵng (MST riêng) + CN HCM (không MST riêng). Chi phí điện nước: phân bổ 60% trụ sở, 20% ĐN, 20% HCM.

### 3.4 Tax Status Lifecycle

```
                         ┌─────────┐
                         │  DRAFT  │
                         └────┬────┘
                              │ submit
                         ┌────▼────┐
                    ┌────│SUBMITTED│────┐
                    │    └────┬────┘    │
               need review    │        auto (không cần approval)
                    │    ┌────▼────┐    │
                    └───▶│APPROVED│◄───┘
                         └────┬────┘
                              │ lock (after deadline)
                         ┌────▼────┐
                         │ LOCKED  │
                         └────┬────┘
                              │ adjust/correct
                         ┌────▼────┐
                         │AMENDED  │
                         └─────────┘
```

| Status | Mô tả | Có thể sửa? | Cần approval? |
|---|---|---|---|
| DRAFT | Đang nhập liệu | ✅ | ❌ |
| SUBMITTED | Đã nộp tờ khai | ❌ (trừ adjustment) | ✅ (nếu cần) |
| APPROVED | Đã duyệt | ❌ | N/A |
| LOCKED | Đã khóa kỳ | ❌ | Chỉ Chief Accountant |
| AMENDED | Đã điều chỉnh | ✅ (adjustment) | ✅ |
| VOID | Hủy | ❌ | ✅ |

### 3.5 Tax Adjustment Handling

| Loại điều chỉnh | Mô tả | Xử lý trong hệ thống |
|---|---|---|
| Khai bổ sung (03/KHBS) | Sai số liệu tờ khai đã nộp | Tạo adjustment record, tính chênh lệch |
| Điều chỉnh giảm VAT đầu ra | Hàng bán bị trả lại, giảm giá | Tạo credit note, adjust output VAT |
| Điều chỉnh tăng VAT đầu ra | Xuất hóa đơn bổ sung | Tạo additional invoice |
| Điều chỉnh giảm VAT đầu vào | Hóa đơn mua sai, bị hủy | Điều chỉnh giảm input VAT |
| Điều chỉnh tăng VAT đầu vào | Nhận hóa đơn bổ sung | Điều chỉnh tăng input VAT |
| Điều chỉnh CIT | Sai chi phí, sai doanh thu | Tạo CIT adjustment journal |
| Điều chỉnh PIT | Sai số thuế khấu trừ | Điều chỉnh bảng lương |

Nguyên tắc: Không sửa số liệu gốc — tạo adjustment riêng. Adjustment có reference đến tờ khai gốc. Tự động tính toán số thuế chênh lệch. Bắt buộc nhập lý do điều chỉnh. Cần approval của Chief Accountant.

### 3.6 Tax Document Attachment

| Nghiệp vụ | Chứng từ bắt buộc | Định dạng |
|---|---|---|
| Hóa đơn đầu vào | Hóa đơn điện tử (XML/PDF) | PDF, XML |
| Hóa đơn đầu ra | Bản sao hóa đơn đã phát hành | PDF |
| Thanh toán ≥ 5M | Chứng từ thanh toán (UNC/QR) | PDF, Image |
| Xuất khẩu | Tờ khai hải quan, vận đơn | PDF |
| CIT adjustment | Hóa đơn, hợp đồng, biên bản | PDF |
| FCT | Hợp đồng, invoice nhà thầu | PDF |

Yêu cầu lưu trữ: Tối thiểu 10 năm (NĐ 123/2020), max 10MB/file, phân loại theo kỳ thuế/loại thuế/số hóa đơn, search theo số hóa đơn/MST/ngày/số tiền.

---

## 4. Full TAX Lifecycle Analysis

### 4.1 Sales Invoice Tax Recognition

**Quy trình:**
```
Doanh thu ghi nhận (AR/Sales)
  → Xác định loại hàng hóa/dịch vụ
  → Xác định thuế suất (dựa trên VAT group)
  → Kiểm tra: có được giảm 8% không?
  → Tạo hóa đơn: doanh thu + output VAT
  → Hạch toán: Nợ 131/Có 511 + Có 33311
  → Gửi dữ liệu sang e-invoice system
  → Cập nhật trạng thái hóa đơn
  → Output VAT ledger cập nhật
```

Xử lý đặc biệt: Hàng xuất khẩu (0% + bộ chứng từ), hàng miễn thuế (không output VAT), khu phi thuế quan (0%), tạm ứng (xuất ngay khi nhận tiền).

### 4.2 Purchase Invoice Tax Recognition

**Quy trình:**
```
Hóa đơn mua vào (AP/Purchase)
  → Validate MST người bán
  → Xác định thuế suất
  → Kiểm tra điều kiện khấu trừ:
    - Hóa đơn hợp pháp?
    - Thanh toán không dùng tiền mặt ≥ 5M?
    - Hàng hóa/dịch vụ dùng cho SXKD chịu thuế?
  → Hạch toán: Nợ 156/642/211 + Nợ 1331/1332/Có 331
  → Nhập vào sổ đăng ký hóa đơn đầu vào
  → Input VAT ledger cập nhật
```

Xử lý đặc biệt: Mua TSCĐ (TK 1332), hàng miễn thuế (không 133), hàng từ hộ KD (có thể không có VAT), hàng nhập khẩu (133 + 33312).

### 4.3 VAT Input/Output Reconciliation

Mục đích: Đảm bảo số liệu VAT trên GL khớp với số liệu VAT trên tờ khai.

**Output VAT reconciliation:** TK 33311 (GL balance) ↔ Bảng kê hóa đơn bán ra. Chênh lệch có thể do hóa đơn xuất chưa hạch toán, hạch toán sai TK, chênh lệch làm tròn, hóa đơn hủy/điều chỉnh.

**Input VAT reconciliation:** TK 1331+1332 ↔ Bảng kê mua vào. Chênh lệch do hóa đơn nhập chưa hạch toán, sai TK, nhầm 1331/1332, không đủ điều kiện khấu trừ.

Xử lý: Chênh lệch > 1% → bắt buộc điều tra. Chênh lệch < 0.5% → tolerance. Ghi nhận lý do bằng comment.

### 4.4 VAT Declaration Preparation

Bước 1: Export bảng kê (01-1/GTGT bán ra, 01-2/GTGT mua vào)
Bước 2: Tính các chỉ tiêu trên tờ khai 01/GTGT:
- [40] Tổng số thuế GTGT đầu ra
- [41] Tổng số thuế GTGT đầu vào được khấu trừ
- [43] Thuế GTGT phải nộp ([40] - [41])
- [44] Thuế GTGT còn được khấu trừ kỳ sau (nếu [41] > [40])

Bước 3: Nếu output > input → phải nộp. Nếu input > output → khấu trừ kỳ sau hoặc hoàn thuế.

### 4.5 VAT Submission

Quy trình: Tạo tờ khai DRAFT → Review → Submit → Nộp qua HTKK/cổng thuế → Nhận mã xác nhận → Ghi nhận → Lock.

Hạn nộp: Khai tháng (trước ngày 20 tháng sau), khai quý (trước ngày 30 sau quý).

### 4.6 CIT Expense Adjustment

**Quy trình cuối năm:**
```
EBT (Lợi nhuận kế toán trước thuế)
[+] Điều chỉnh tăng:
  - Chi phí không hóa đơn hợp lệ
  - Quảng cáo vượt 10% doanh thu (TT 20/2026)
  - Lãi vay vượt 30% EBITDA
  - Trích trước không được trừ
  - Dự phòng không đủ điều kiện
  - Khấu hao nhanh hơn khung
  - Lương chưa thực tế chi
[-] Điều chỉnh giảm:
  - Lãi tiền gửi, cổ tức, thu nhập miễn thuế
= Thu nhập tính thuế TNDN
× Thuế suất (15%/17%/20%)
= Thuế TNDN phải nộp
[-] Tạm nộp các quý
= Còn phải nộp (hoặc nộp thừa)
```

### 4.7 CIT Finalization

Thời điểm: Trước ngày 31/03 năm sau.

Các bước: Tổng hợp số liệu năm → CIT adjustment → Tính thu nhập tính thuế → Áp dụng ưu đãi → Kết chuyển lỗ (tối đa 5 năm) → Tính thuế → So tạm nộp → Nếu < 80% → tính chậm nộp → Tờ khai 03/TNDN.

### 4.8 PIT Declaration

Khai tháng (05/KK-TNCN): Trước ngày 20 tháng sau. Gồm tổng thu nhập, giảm trừ, thuế khấu trừ.

Khai quyết toán năm (05/QTT-TNCN): Trước ngày 31/03. Chi tiết từng lao động.

PIT 2026: Giảm trừ bản thân 15.5M/tháng, NPT 6.2M/tháng/người. 5 bậc thuế từ 07/2026: 5% (≤10M), 10% (10-30M), 20% (30-60M), 30% (60-100M), 35% (>100M). Non-resident: 20% flat.

### 4.9 Foreign Contractor Tax

Áp dụng khi nhà thầu nước ngoài không có cơ sở thường trú tại Việt Nam.

Tỷ lệ khấu trừ FCT:
| Loại | VAT | CIT |
|---|---|---|
| Dịch vụ + cho thuê máy móc | 5% | 5% |
| Sản xuất, vận tải, dịch vụ có supply hàng hóa | 3% | 2% |
| Kinh doanh khác | 2% | 2% |
| Phân phối, cung ứng hàng hóa | 1% | 1% |

Hạch toán: Dr 642/635/241 (giá trị trước thuế) / Cr 331 (sau thuế) + Cr 33312 (VAT) + Cr 3338 (CIT).

### 4.10 E-Invoice Cancellation/Replacement

Quy trình thay thế: Lập biên bản hủy → Tạo hóa đơn thay thế (reference hóa đơn cũ) → Gửi CQT → Đánh dấu hóa đơn cũ bị thay thế → Adjust output VAT.

Quy trình điều chỉnh: Lập biên bản điều chỉnh → Tạo hóa đơn điều chỉnh (tăng/giảm) → Ghi rõ "điều chỉnh cho hóa đơn số..." → Gửi CQT → Adjust output VAT.

### 4.11 Month-End Tax Closing Checklist

```
[ ] 1. Đối chiếu output VAT (TK 33311) với bảng kê hóa đơn bán ra
[ ] 2. Đối chiếu input VAT (TK 1331+1332) với bảng kê hóa đơn mua vào
[ ] 3. Kiểm tra hóa đơn ≥ 5M đã có chứng từ thanh toán chưa
[ ] 4. Xác định thuế GTGT phải nộp/kỳ sau
[ ] 5. Phân bổ VAT đầu vào dùng chung (nếu có)
[ ] 6. Tạo tờ khai VAT tháng
[ ] 7. Nộp tờ khai trước hạn
[ ] 8. Ghi nhận bút toán kết chuyển VAT
[ ] 9. Đối chiếu TK 3335 với bảng lương (PIT)
```

### 4.12 Year-End Tax Closing Checklist

```
[ ] 1. Đối chiếu toàn bộ VAT cả năm: TK 133, 33311 với tờ khai
[ ] 2. Xác định chi phí không được trừ (CIT)
[ ] 3. Tính lỗ được kết chuyển
[ ] 4. Tờ khai quyết toán TNDN (03/TNDN)
[ ] 5. Quyết toán thuế TNCN (05/QTT-TNCN)
[ ] 6. Kết chuyển lãi/lỗ TK 911 → 421
[ ] 7. Xác định số thuế TNDN phải nộp thêm
[ ] 8. Kiểm tra ưu đãi thuế
[ ] 9. Lập BC 01/02/03 sau thuế
```

---

## 5. Use Cases

### UC-01: Xuất Hóa Đơn Bán Ra Có VAT

| Field | Value |
|---|---|
| **Use Case Name** | Xuất hóa đơn GTGT bán hàng trong nước |
| **Business Objective** | Ghi nhận doanh thu và output VAT đúng quy định |
| **Actors** | AR Accountant, E-Invoice System |
| **Preconditions** | Hàng hóa/dịch vụ đã được giao; MST người mua hợp lệ |
| **Trigger Event** | Hoàn thành giao hàng hoặc nhận được thanh toán |
| **Happy Path** | AR chọn đơn hàng → Hệ thống xác định VAT group + thuế suất → Kiểm tra thông tin → Gửi lên e-invoice → Nhận mã CQT → Hạch toán Dr 131/Cr 511 + 33311 → Gửi hóa đơn khách |
| **Exception Paths** | MST không hợp lệ (reject); thuế suất không xác định (reject); e-invoice lỗi (retry 3 lần) |
| **Validation Rules** | MST bắt buộc; thuế suất bắt buộc; số tiền > 0 |
| **Tax Rules** | Thuế suất = group mặc định; xuất khẩu = 0% + cần chứng từ |
| **Accounting Rules** | Dr 131/Cr 511 + Cr 33311 |
| **Approval Rules** | > 100M: Kế toán trưởng; > 500M: CFO |
| **Compliance Risk** | Sai thuế suất → phạt; sai MST → hóa đơn không hợp lệ |

### UC-02: Nhập Hóa Đơn Mua Vào Có VAT

| Field | Value |
|---|---|
| **Use Case Name** | Nhập hóa đơn GTGT mua hàng trong nước |
| **Business Objective** | Ghi nhận input VAT đủ điều kiện khấu trừ |
| **Actors** | AP Accountant, E-Invoice System |
| **Preconditions** | Hàng hóa/dịch vụ đã nhận; hóa đơn điện tử từ nhà cung cấp |
| **Happy Path** | AP nhập hóa đơn → Validate MST, rate → Kiểm tra khấu trừ → Hạch toán Dr 156/642/211 + Dr 1331/1332/Cr 331 → Input VAT ledger cập nhật |
| **Exception Paths** | MST sai (reject); thuế suất sai (cảnh báo); hóa đơn trùng (reject) |
| **Validation Rules** | MST bắt buộc; rate khớp quy định; số hóa đơn không trùng |
| **Tax Rules** | Input VAT chỉ khấu trừ nếu đủ điều kiện (TT 69/2025) |
| **Compliance Risk** | Khấu trừ không đủ điều kiện → bị loại + phạt |

### UC-03: Kê Khai VAT Tháng

| Field | Value |
|---|---|
| **Use Case Name** | Lập và nộp tờ khai VAT tháng |
| **Business Objective** | Hoàn thành nghĩa vụ kê khai VAT đúng hạn |
| **Actors** | Tax Accountant, HTKK/Cổng thuế điện tử |
| **Preconditions** | Kỳ thuế tạm đóng; all giao dịch VAT đã ghi nhận |
| **Happy Path** | System generate 01/GTGT → Tax Accountant review → Đối chiếu e-invoice → Khớp → Submit → Gửi HTKK → Nhận mã xác nhận → Ghi nhận SUBMITTED |
| **Validation Rules** | Output vs bảng kê bán ra; Input vs bảng kê mua vào; không số âm vô lý |
| **Approval Rules** | Tax Accountant + Chief Accountant approve trước nộp |
| **Compliance Risk** | Chậm nộp → phạt 0.03%/ngày; khai sai → phạt 10-20% |

### UC-04: Quyết Toán Thuế TNDN Năm

| Field | Value |
|---|---|
| **Use Case Name** | Lập quyết toán thuế TNDN năm |
| **Business Objective** | Hoàn thành nghĩa vụ quyết toán TNDN |
| **Actors** | Tax Accountant, Chief Accountant |
| **Preconditions** | Kỳ kế toán năm đã đóng; FS đã lập |
| **Happy Path** | System generate CIT adjustment → Tính thu nhập tính thuế → Áp dụng thuế suất theo DT → Ưu đãi → Kết chuyển lỗ → Tính thuế → So tạm nộp → Tạo 03/TNDN → Approve → Nộp |
| **Tax Rules** | CIT rate: 20% (hoặc 15%/17%); lỗ kết chuyển tối đa 5 năm |
| **Approval Rules** | Tax Accountant prepare → Chief Accountant review → CFO approve |
| **Compliance Risk** | Sai chi phí không được trừ → truy thu; không kết chuyển lỗ → mất quyền |

### UC-05: Khấu Trừ Thuế TNCN

| Field | Value |
|---|---|
| **Use Case Name** | Tính và khấu trừ thuế TNCN từ tiền lương |
| **Business Objective** | Thực hiện nghĩa vụ khấu trừ thuế TNCN |
| **Actors** | Payroll Accountant, HR |
| **Preconditions** | Bảng lương tháng đã tính; dependent registration đã cập nhật |
| **Happy Path** | Payroll tính thu nhập chịu thuế → Trừ BH (10.5%), giảm trừ (15.5M + 6.2M×NPT) → Xác định thu nhập tính thuế → Áp dụng 5 bậc → Tính thuế → Dr 334/Cr 3335 → Nộp tờ khai 05/KK-TNCN |
| **Tax Rules** | 5 bậc từ 07/2026; giảm trừ 15.5M + 6.2M/NPT; BH = 10.5% |
| **Compliance Risk** | Không khấu trừ → DN tự chịu; khấu trừ sai → phạt |

### UC-06: Xử Lý Điều Chỉnh Thuế

| Field | Value |
|---|---|
| **Use Case Name** | Điều chỉnh khai thuế bổ sung |
| **Business Objective** | Sửa sai sót trong tờ khai đã nộp |
| **Actors** | Tax Accountant, Chief Accountant |
| **Preconditions** | Tờ khai gốc đã nộp; kỳ thuế đã khóa |
| **Happy Path** | Tạo adjustment request → Chọn kỳ → Hệ thống hiển thị tờ khai gốc → Nhập số liệu điều chỉnh → Tính chênh lệch → Nhập lý do → Chief Accountant approve → Tạo 03/KHBS → Nộp |
| **Tax Rules** | Khai bổ sung theo NĐ 126/2020; chỉ được khai bổ sung trước khi có QĐ thanh tra |
| **Approval Rules** | Tax Accountant prepare; Chief Accountant approve; CFO nếu > 50M |

### UC-07: Xử Lý Thanh Tra Thuế

| Field | Value |
|---|---|
| **Use Case Name** | Hỗ trợ thanh tra thuế tại doanh nghiệp |
| **Business Objective** | Cung cấp đầy đủ chứng từ, giải trình số liệu |
| **Actors** | Tax Accountant, Chief Accountant, CFO, Đoàn Thanh tra |
| **Preconditions** | Có quyết định thanh tra; data room đã chuẩn bị |
| **Happy Path** | Ghi nhận quyết định → System xuất data room → Đoàn yêu cầu → Xuất báo cáo → Giải trình → Ghi nhận biên bản → Ghi nhận xử lý |
| **Exception Paths** | Truy thu (tạo adjustment + nộp bổ sung); phạt (ghi nhận 8212); khiếu nại |

---

## 6. TAX Engine Logic

### 6.1 VAT Determination Logic

```
FUNCTION determineVATRate(item, transaction)
  // Bước 1: Xác định VAT group của hàng hóa/dịch vụ
  group = getVATGroup(item.category, item.type)
  
  // Bước 2: Lấy rate mặc định từ group
  baseRate = group.defaultRate
  
  // Bước 3: Kiểm tra overridden rate (nếu có)
  IF transaction.overrideRate != NULL
    rate = validateRate(transaction.overrideRate)
  ELSE
    rate = baseRate
  
  // Bước 4: Kiểm tra giảm 8% (tạm thời đến 31/12/2026)
  IF rate = 10% AND currentDate <= '2026-12-31'
    IF isEligibleForReduction(group, item)
      rate = 8%
  
  // Bước 5: Kiểm tra điều kiện đặc biệt
  IF transaction.isExport THEN rate = 0% (cần chứng từ xuất khẩu)
  IF transaction.isExportProcessingZone THEN rate = 0%
  IF group.isExempt THEN rate = NULL (không tính VAT)
  
  RETURN rate
END FUNCTION
```

### 6.2 VAT Input/Output Logic

```
Output VAT = SUM(line.taxableAmount × line.vatRate) for all lines

Input VAT deduction: kiểm tra 4 điều kiện:
  1. isValidInvoice(invoice)
  2. amount >= 5M → hasNonCashPayment(invoice) (TT 69/2025)
  3. isUsedForTaxableActivity(invoice)
  4. hasValidTaxCode(invoice)
  
  Nếu shared use: inputVAT = invoice.vatAmount × deductibleRatio

VAT payable per period:
  totalOutputVAT - totalInputVAT - carryForwardBalance
  > 0 → VAT phải nộp
  < 0 → carry forward next period
```

### 6.3 Deductible/Non-Deductible VAT Logic

VAT không được khấu trừ:
1. Hóa đơn không hợp lệ (sai MST, sai tên)
2. Hàng hóa/dịch vụ dùng cho SXKD không chịu thuế GTGT
3. Hàng bị tổn thất (hỏng, mất, hết hạn)
4. Quà tặng, quảng cáo, khuyến mại (một số trường hợp)
5. Thanh toán ≥ 5M không qua ngân hàng (từ 01/07/2025)
6. Xe ô tô ≥ 9 chỗ: chỉ khấu trừ VAT trên giá trị ≤ 1.6 tỷ

Xử lý: Nếu không deductible → VAT tính vào giá trị hàng hóa/chi phí (Dr 156/642/211 gồm VAT).

### 6.4 CIT Adjustment Logic

```
FOR each expense:
  IF NOT hasValidInvoice → adjustmentsIncrease.add(amount)
  IF isAdvertisement AND amount > 10% Revenue → excess add
  IF isInterest AND amount > 30% EBITDA → excess add
  IF isProvision AND NOT meetsConditions → add
  IF isAcceleratedDepreciation AND amount > maxAllowed → excess add
  IF isSalary AND NOT actuallyPaid → add

taxIncome = EBT + SUM(increases) - SUM(decreases)
lossCF = getPriorYearLosses() (max 5 years)
taxIncomeAfterLoss = max(taxIncome - lossCF, 0)
taxRate = getCITRate(revenue)  // 15%, 17%, or 20%
tax = taxIncomeAfterLoss × taxRate - incentives
```

### 6.5 PIT Taxable Income Logic

```
PIT >= 07/2026 (5 bậc mới):
  personalDeduction = 15,500,000
  dependentDeduction = 6,200,000 × dependentCount
  insurance = grossSalary × 10.5%
  taxableIncome = grossIncome - personalDeduction - dependentDeduction - insurance
  
  5 bậc: 5% (0-10M), 10% (10-30M), 20% (30-60M), 30% (60-100M), 35% (>100M)
```

### 6.6 Foreign Contractor Tax Logic

```
CASE serviceType:
  "services": vatRate=5%; citRate=5%
  "services_with_goods": vatRate=3%; citRate=2%
  "trading": vatRate=1%; citRate=1%
  "leasing": vatRate=5%; citRate=5%
  "other": vatRate=2%; citRate=2%

vatWithholding = contractValue × vatRate / (1 + vatRate)
citWithholding = contractValue × citRate
```

### 6.7 Reconciliation Logic

```
reconcileTaxVsLedger(periodId):
  glOutputVAT = getGLBalance("33311")
  declOutputVAT = getDeclarationOutputVAT()
  diff = glOutputVAT - declOutputVAT
  
  glInputVAT = getGLBalance("1331") + getGLBalance("1332")
  declInputVAT = getDeclarationInputVAT()
  diff = glInputVAT - declInputVAT
  
  FOR each: if abs(diff) < tolerance → MATCH, else → MISMATCH
```

---

## 7. Workflow & Process Analysis

### 7.1 VAT Declaration Workflow

```
[Period Closing] → [Generate Declaration] → [Reconcile with E-Invoice]
  → [Review Data] → [Approve] → [Submit to Tax Authority]
  → [Get Confirmation] → [Lock Period]
```

### 7.2 CIT Finalization Workflow

```
[Year-End GL Closing] → [Prepare FS] → [CIT Adjustment Engine]
  → [Non-deductible expenses] → [Taxable income calculation]
  → [Loss carryforward] → [Incentives] → [Create 03/TNDN]
  → [Compare with quarterly payments] → [Review + Approve]
  → [Submit + Pay]
```

### 7.3 PIT Declaration Workflow

```
[Payroll Close] → [Calculate PIT per Employee]
  → [Create 05/KK-TNCN] → [Submit] (before 20th monthly)
  → [Year-End: Create 05/QTT-TNCN] → [Submit before 31/03]
```

### 7.4 E-Invoice Workflow

```
[Create Sales Invoice] → [Assign VAT Rate] → [Digital Signature]
  → [Send to CQT] → [Receive Tax Code] → [Send to Buyer]
  → [Archive 10 years]
```

### 7.5 Month-End Closing Workflow

```
Step 1 (Ngày 25-28):
  [ ] Đối chiếu VAT đầu ra/đầu vào với e-invoice
  [ ] Kiểm tra hóa đơn ≥ 5M có chứng từ thanh toán không

Step 2 (Ngày 28-30):
  [ ] Kiểm tra chênh lệch GL vs tờ khai
  [ ] Xử lý adjustment entries

Step 3 (Ngày 01-05 tháng sau):
  [ ] Tạo tờ khai VAT → Review → Approve → Nộp trước ngày 20
```

### 7.6 Year-End Closing Workflow

```
Tháng 12: Review hóa đơn cả năm → Kiểm tra chi phí chưa có hóa đơn
Tháng 01: Kết chuyển DTCP → EBT → Xác định chi phí không được trừ
Tháng 02: Lập FS → Lập 03/TNDN → Lập 05/QTT-TNCN
Trước 31/03: Nộp quyết toán TNDN + TNCN + BC01/02
```

### 7.7 Audit Preparation Workflow

```
T-90: Review tổng thể compliance
T-60: Đối chiếu sổ sách, chuẩn bị data room
T-30: Hoàn thiện chứng từ, giải trình tồn đọng
T-7: Data room sẵn sàng, phân công người phụ trách
```

---

## 8. Data Flow Analysis (Business View)

### 8.1 Sales Invoice → Output VAT

```
Sales/AR → VAT Group Lookup → Output VAT journal Dr 131/Cr 511+33311
  → Output VAT Ledger → VAT Declaration chỉ tiêu [40]
  → E-Invoice System (gửi hóa đơn, nhận mã CQT)
```

### 8.2 Purchase Invoice → Input VAT

```
Purchase/AP → Validate (MST, rate, số HĐ) → Check deduction conditions
  → If deductible: Dr 156/642/211 + Dr 1331/1332 / Cr 331
  → If not: Dr 156/642/211 (gồm VAT) / Cr 331
  → Input VAT Ledger → VAT Declaration chỉ tiêu [41]
```

### 8.3 Payroll → PIT

```
Payroll → Gross salary → - Insurance (10.5%) → - Personal deduction (15.5M)
  → - Dependent deductions (6.2M × NPT) → Taxable income → × rates → PIT
  → Dr 334 / Cr 3335 → 05/KK-TNCN → 05/QTT-TNCN
```

### 8.4 GL → Tax Reports

```
TK 33311 → Output VAT
TK 1331+1332 → Input VAT
TK 3334 → CIT payable
TK 3335 → PIT payable
TK 8211 → CIT expense

→ Reconciliation GL vs Declaration → VAT/CIT reconciliation report
→ FS Impact: BC01 14A (133), 14C (333), BC02 30 (8211), BC03
```

### 8.5 Tax Closing → Financial Statements

```
VAT closing: Dr 33311 / Cr 1331 (số phải nộp); ghi nhận khấu trừ kỳ sau
CIT closing: Dr 8211 / Cr 3334 → Dr 911 / Cr 8211
PIT closing: Dr 334 / Cr 3335 (đã ghi nhận khi trả lương)
FS: BC01 (TK 133, 333), BC02 (TK 8211), BC03 (thuế đã nộp)
```

---

## 9. User Journey Analysis

### 9.1 Tax Accountant Workflow

**Ngày 1-5 đầu tháng:**
- Check cảnh báo (VAT mismatch, hóa đơn chưa match)
- Xử lý hóa đơn mới từ e-invoice
- Đối chiếu VAT đầu ra/đầu vào
- Kiểm tra hóa đơn ≥ 5M có chứng từ thanh toán không
- Xử lý adjustment (nếu có)
- Tạo tờ khai VAT → review → gửi Chief Accountant approve

**Ngày 15-20:**
- Nhận approval → Nộp VAT qua HTKK
- Nhận mã xác nhận → Ghi nhận → Khóa kỳ
- Lập + nộp tờ khai PIT tháng

**Cuối năm (T01-T03):**
- Tuần 1: Tổng hợp chi phí không được trừ
- Tuần 2: Lập CIT adjustment
- Tuần 3: Lập 03/TNDN
- Tuần 4: Lập 05/QTT-TNCN

### 9.2 AP Accountant Workflow (Tax Related)

Khi nhận hóa đơn mua vào:
1. Import từ e-invoice
2. System: check MST, check rate
3. Nếu auto match → post
4. Nếu không → manual review (MST, rate, tổng tiền)
5. Ghi nhận input VAT
6. Đính kèm hóa đơn (PDF/XML)
7. Post AP

### 9.3 AR Accountant Workflow (Tax Related)

Khi xuất hóa đơn bán ra:
1. Tạo invoice
2. System gán VAT group + rate
3. Kiểm tra MST, địa chỉ, tên khách
4. > 100M → approval
5. Gửi lên e-invoice system
6. Nhận mã CQT + số hóa đơn
7. Ghi nhận output VAT
8. Gửi hóa đơn cho khách

### 9.4 Chief Accountant Journey

**Hàng tháng:** Review VAT reconciliation → Approve VAT declaration → Check warnings

**Cuối quý:** Review CIT tạm nộp (đảm bảo ≥ 80%)

**Cuối năm:** Review CIT adjustment → Approve 03/TNDN → Approve 05/QTT-TNCN → Review FS

### 9.5 VAT Declaration Journey

System generate → Tax Accountant review → System reconcile with e-invoice → Match → Chief Accountant approve → Nộp HTKK → Ghi nhận mã → Payment request (nếu có thuế phải nộp)

### 9.6 CIT Finalization Journey

System export EBT → CIT adjustment engine → Non-deductible list → Review → Taxable income → Check rate/incentives/loss-carryforward → Generate 03/TNDN → Chief Accountant review → CFO approve → Nộp + nộp tiền

### 9.7 Tax Correction Request Journey

Tax Accountant phát hiện sai → Tạo adjustment request (kỳ, số liệu gốc/sửa, lý do) → System tính chênh lệch → Chief Accountant approve → Tạo 03/KHBS → Nộp HTKK → Ghi nhận → Audit trail cập nhật

### 9.8 Tax Inspection Handling Journey

CFO nhận QĐ → Ghi nhận vào hệ thống → System xuất data room → Tax Accountant chuẩn bị → Đoàn làm việc → Cung cấp chứng từ/giải trình → Ghi nhận biên bản → Nếu truy thu: tạo adjustment → Close

---

## 10. Validation & Internal Control Rules

### 10.1 Duplicate Tax Declaration Prevention

| Control | Mô tả | Implementation |
|---|---|---|
| Unique constraint | Mỗi kỳ/tax type chỉ 1 tờ khai gốc | DB unique index (tax_type, period, branch) |
| Duplicate invoice check | Không cho nhập hóa đơn trùng số | Check số HĐ + MST + ngày |
| Declaration lock | Khi SUBMITTED → không sửa | Immutable status |
| Adjustment only | Muốn sửa → tạo adjustment riêng | Separate table |

### 10.2 VAT Mismatch Detection

| Check | Threshold |
|---|---|
| Output VAT GL vs Declaration (chỉ tiêu [40]) | > 0.5% → WARN; > 1% → FAIL |
| Input VAT GL vs Declaration (chỉ tiêu [41]) | > 0.5% → WARN; > 1% → FAIL |
| Output VAT vs E-Invoice | Bất kỳ chênh lệch → WARN |
| Input VAT vs E-Invoice | Bất kỳ chênh lệch → WARN |
| VAT rate distribution change > 30% | → WARN |

### 10.3 Invalid Tax Code / Rate Detection

- MST format (10/13 số) → Reject nếu sai
- MST existence → Tra cứu Cổng TTĐT Cục Thuế
- Rate vs product group → Cảnh báo nếu khác
- 8% eligibility → Reject nếu không đủ điều kiện
- Export rate must be 0% → Reject nếu không

### 10.4 Non-Deductible Expense Detection

- Missing invoice → Auto reject
- Invalid invoice → Auto reject
- Non-cash payment ≥ 5M missing → Auto reject
- Personal expense → Flag + manual review
- Advertising > 10% revenue → Auto adjust
- Interest > 30% EBITDA → Auto adjust
- Provision not meeting conditions → Auto adjust

### 10.5 Segregation of Duties

| Role | Create | Approve | Post | Lock | Re-open |
|---|---|---|---|---|---|
| Tax Accountant | ✅ | ❌ | ✅ | ❌ | ❌ |
| Chief Accountant | ✅ | ✅ | ✅ | ✅ | ✅ |
| CFO | ✅ | ✅ (large) | ❌ | ✅ | ✅ |
| AP Accountant | ✅ (input VAT) | ❌ | ❌ | ❌ | ❌ |
| AR Accountant | ✅ (output VAT) | ❌ | ❌ | ❌ | ❌ |

Nguyên tắc: Người tạo ≠ Người approve (4-eyes). Người approve ≠ Người post. Chỉ Chief Accountant + CFO mới lock/unlock.

### 10.6 Closed Tax Period Protection

- Immutable after lock (DB level)
- Exception: adjustment entry (separate table)
- Re-open có lý do + approval + audit log
- Re-open tối đa 3 lần/kỳ

### 10.7 Audit Traceability

- Mọi giao dịch: UUID + created_at
- Mọi thay đổi: old/new value table
- Mọi action: actor (session user_id)
- Mọi approval: timestamp
- Mọi declaration: version history

---

## 11. Reporting & Reconciliation Analysis

### 11.1 VAT Declaration (01/GTGT) Key Indicators

| Chỉ tiêu | Mô tả | Source |
|---|---|---|
| [21]-[25] | Hàng hóa/dịch vụ bán ra theo từng loại thuế suất | Output VAT ledger (rate) |
| [40] | Tổng số thuế GTGT đầu ra | Auto từ [21]-[25] |
| [41] | Tổng số thuế GTGT đầu vào được khấu trừ | Input VAT ledger |
| [43] | Thuế GTGT phải nộp | [40] - [41] |
| [44] | Thuế GTGT còn được khấu trừ kỳ sau | Nếu [41] > [40] |

### 11.2 Input VAT Report

Columns: Số HĐ, ngày, MST người bán, tên, hàng hóa, giá chưa VAT, rate, VAT, thanh toán (≥ 5M), khấu trừ?, status.

Filters: Kỳ, người bán, trạng thái khấu trừ, khoảng tiền.

### 11.3 Output VAT Report

Columns: Số HĐ, ngày, MST mua, tên, hàng hóa, giá chưa VAT, rate, VAT, mã CQT, e-invoice status, payment status.

Filters: Kỳ, khách hàng, thuế suất, e-invoice status.

### 11.4 CIT Adjustment Report

Groups: Chi phí không hóa đơn, quảng cáo vượt 10%, lãi vay vượt 30% EBITDA, trích trước, dự phòng, khấu hao nhanh, lương chưa chi, phạt, khác + thu nhập miễn thuế (giảm).

### 11.5 Tax Reconciliation Report (VAT)

| Mục | GL | Tờ khai | Chênh lệch | Ghi chú |
|---|---|---|---|---|
| Output VAT (33311) | 100,000,000 | 99,800,000 | 200,000 | Hóa đơn T12 chưa hạch toán |
| Input VAT (1331+1332) | 85,000,000 | 85,000,000 | 0 | Khớp |
| VAT phải nộp | 15,000,000 | 14,800,000 | 200,000 | Cần xử lý |

### 11.6 Financial Statement Tax Impact

| BC 01 | TK | Note |
|---|---|---|
| 14A — Thuế GTGT được khấu trừ | 1331, 1332 | Nếu chưa khấu trừ hết |
| 14B — Thuế và các khoản phải thu NN | 333 (dư Nợ) | Nộp thừa |
| 14C — Thuế và các khoản phải nộp NN | 333 (dư Có) | Thuế phải nộp |

| BC 02 | TK | Note |
|---|---|---|
| 30 — Chi phí thuế TNDN | 8211 | Thuế TNDN phải nộp |
| 32 — Lợi nhuận sau thuế | 421 | LNST |

---

## 12. SME & Enterprise Pain Analysis

### 12.1 Excel Tax Chaos

**Symptom:** Kế toán thuế dùng Excel tổng hợp VAT, copy-paste từ e-invoice → HTKK. Sai typo thường xuyên.

**Scale:** SME trung bình xử lý 50-200 hóa đơn/tháng. Mỗi hóa đơn có 10+ field. Copy-paste thủ công = rủi ro sai rất cao.

**System fix:** Tự động import từ e-invoice, tự động populate declaration, zero manual data entry.

### 12.2 VAT Mismatch vs Ledger

**Symptom:** Cuối tháng, kế toán so sánh sổ cái TK 133/333 với bảng kê. Thường lệch 1-5 triệu do timing (hóa đơn cuối tháng chưa hạch toán). Nếu không phát hiện → sai tờ khai.

**System fix:** Real-time reconciliation, tự động phát hiện timing difference vs error.

### 12.3 Missing Invoices

**Symptom:** Hóa đơn mua vào thất lạc, không kịp kê khai trong tháng → mất quyền khấu trừ. SME thường phát hiện 2-3 tháng sau khi đã nộp tờ khai.

**System fix:** E-invoice auto-import, matching engine, cảnh báo hóa đơn chưa match.

### 12.4 Wrong VAT Rates (8% vs 10%)

**Symptom:** SME không phân biệt được hàng hóa nào được giảm 8%, hàng nào vẫn 10%. Hậu quả: hóa đơn sai → khách hàng khiếu nại, hoặc không claim được ưu đãi.

**System fix:** VAT group matrix + tự động gán rate + forced check khi xuất hóa đơn.

### 12.5 Wrong Deductible VAT

**Symptom:** Khấu trừ VAT cho hóa đơn không đủ điều kiện (≥ 5M không chứng từ NH, hóa đơn sai tên/MST).

**System fix:** Check trước khi ghi nhận input VAT; tự động loại VAT không được khấu trừ.

### 12.6 CIT Adjustment Mistakes

**Symptom:** Cuối năm, kế toán phải rà soát hàng trăm hóa đơn chi phí để xác định cái nào bị loại. Thường bỏ sót 30-50% các khoản phải loại.

**System fix:** CIT adjustment engine tự động quét toàn bộ chi phí, phát hiện khoản không được trừ.

### 12.7 Weak Audit Trail

**Symptom:** Khi có kiểm tra thuế, mất 3-5 ngày chuẩn bị chứng từ. Không chứng minh được nguồn gốc số liệu tờ khai.

**System fix:** Full audit trail built-in, data room auto-generation trong 30 phút.

### 12.8 Multi-Branch Inconsistency

**Symptom:** Mỗi chi nhánh có MST riêng, tự tổng hợp số liệu riêng. Kế toán thuế phải gộp thủ công từ 3-5 Excel files.

**System fix:** Multi-branch tax handling trong cùng một system, tự động phân tách/gộp.

### 12.9 Tax Inspection Stress

**Symptom:** Đoàn thanh tra đến, kế toán lo lắng vì không chắc số liệu có chính xác không. Phải chạy khắp nơi tìm chứng từ.

**System fix:** Data room sẵn sàng trong 30 phút, mọi số liệu đều trace được.

---

## 13. Functional Rules Matrix

93 rules across 12 categories — every rule has ID, name, category, rule type, failure severity, legal basis, and description.

### 13.1 VAT Rate Rules (VR01-VR11)

| ID | Tên | Category | Type | Severity | Legal Basis | Mô tả |
|---|---|---|---|---|---|---|
| VR01 | VAT rate default | Output VAT | Auto | HIGH | Circular 99 | Xác định thuế suất từ VAT group của hàng hóa/dịch vụ |
| VR02 | 10% rate | Output VAT | Auto | HIGH | Luật GTGT | Hàng hóa/dịch vụ thông thường |
| VR03 | 5% rate | Output VAT | Auto | HIGH | Luật GTGT | Dịch vụ vận tải, giáo dục, y tế, sách, nước sạch, thiết bị y tế, nuôi trồng |
| VR04 | 0% rate export | Output VAT | Auto | HIGH | Luật GTGT | Hàng xuất khẩu, XK tại chỗ, hàng miễn thuế GTGT |
| VR05 | 8% rate reduction | Output VAT | Auto | MEDIUM | NĐ 72/2024 | Tạm thời đến 31/12/2026, giảm 10%→8% cho một số nhóm |
| VR06 | 8% eligibility | Output VAT | Validation | HIGH | NĐ 72/2024 | Viễn thông, CNTT, NH-BH, CK, BĐS không được giảm |
| VR07 | VAT exempt | Output VAT | Validation | HIGH | Luật GTGT 2013 | Bảo hiểm, tín dụng, chứng khoán, BĐS cá nhân, dịch vụ y tế |
| VR08 | Non-taxable income | Output VAT | Validation | HIGH | Luật GTGT | Sản phẩm trồng trọt chưa chế biến, dịch vụ chưa chịu thuế |
| VR09 | Export zone 0% | Output VAT | Auto | HIGH | NĐ 123/2020 | Khu phi thuế quan, xuất khẩu vào khu công nghiệp |
| VR10 | VAT on commission | Output VAT | Auto | MEDIUM | TT 40/2021 | Hoa hồng đại lý đúng giá hưởng hoa hồng |
| VR11 | VAT on discount | Output VAT | Manual | MEDIUM | TT 40/2021 | Giảm giá sau bán → điều chỉnh hóa đơn |

### 13.2 Input VAT Rules (IR01-IR10)

| ID | Tên | Category | Type | Severity | Legal Basis | Mô tả |
|---|---|---|---|---|---|---|
| IR01 | Input VAT deduction validity | Input VAT | Validation | HIGH | TT 69/2025 | Hóa đơn hợp lệ + thanh toán NH ≥ 5M + SXKD chịu thuế |
| IR02 | Non-cash payment check | Input VAT | Validation | HIGH | TT 69/2025 | Hóa đơn ≥ 5M phải thanh toán qua NH |
| IR03 | Shared VAT allocation | Input VAT | Auto | MEDIUM | TT 69/2025 | VAT dùng chung cho SXKD chịu thuế/không chịu thuế → phân bổ |
| IR04 | VAT on TSCĐ | Input VAT | Auto | HIGH | TT 69/2025 | Mua TSCĐ: Dr 211 (không VAT) + Dr 1332 (VAT) |
| IR05 | VAT on car limit | Input VAT | Validation | HIGH | TT 69/2025 | Xe 9 chỗ chỉ khấu trừ VAT trên giá ≤ 1.6 tỷ |
| IR06 | VAT on import | Input VAT | Auto | HIGH | TT 38/2015 | Dr 1331/Cr 33312 + hóa đơn nhập khẩu |
| IR07 | VAT on lost goods | Input VAT | Validation | MEDIUM | TT 69/2025 | Hàng tổn thất → không khấu trừ VAT |
| IR08 | VAT on gift advertising | Input VAT | Validation | MEDIUM | TT 219/2013 | Quà tặng QC có chịu thuế? Phân bổ đúng |
| IR09 | Input VAT carryforward | Input VAT | Auto | HIGH | TT 69/2025 | Khấu trừ kỳ sau nếu input > output |
| IR10 | Input VAT refund request | Input VAT | Validation | MEDIUM | Luật GTGT | Hoàn thuế nếu tồn input ≥ 300M sau 12 tháng |

### 13.3 VAT Declaration Rules (VD01-VD09)

| ID | Tên | Category | Type | Severity | Legal Basis | Mô tả |
|---|---|---|---|---|---|---|
| VD01 | Declaration deadline | Declaration | Calendar | HIGH | NĐ 126/2020 | Tháng: trước 20; Quý: trước 30 sau quý |
| VD02 | Declaration format | Declaration | Validation | HIGH | TT 80/2020 | Đúng format 01/GTGT |
| VD03 | Declaration period lock | Declaration | Validation | HIGH | NĐ 126/2020 | Không nộp trùng kỳ |
| VD04 | Adjustment validity | Declaration | Validation | HIGH | Luật QLT 38/2019 | Khai bổ sung trước khi có QĐ thanh tra |
| VD05 | Late payment penalty | Declaration | Auto | MEDIUM | NĐ 125/2020 | 0.03%/ngày chậm nộp |
| VD06 | Late filing penalty | Declaration | Auto | MEDIUM | NĐ 125/2020 | 5-15M chậm nộp > 30 ngày |
| VD07 | Declaration matching | Declaration | Validation | HIGH | TT 69/2025 | Output/input khớp với e-invoice và GL |
| VD08 | Tax payable for refund | Declaration | Auto | MEDIUM | Luật GTGT | Refund nếu tồn input ≥ 300M sau 12 tháng |
| VD09 | Branch declaration | Declaration | Auto | MEDIUM | TT 80/2020 | Chi nhánh hạch toán phụ thuộc → khai tập trung |

### 13.4 Invoice Rules (IN01-IN09)

| ID | Tên | Category | Type | Severity | Legal Basis | Mô tả |
|---|---|---|---|---|---|---|
| IN01 | Invoice sequence | Invoice | Validation | HIGH | NĐ 123/2020 | Số hóa đơn tăng dần theo ký hiệu |
| IN02 | Invoice cancellation | Invoice | Validation | HIGH | NĐ 123/2020 | Lập BB hủy + thông báo CQT |
| IN03 | Invoice replacement | Invoice | Validation | HIGH | NĐ 123/2020 | Ghi rõ thay thế cho hóa đơn số... |
| IN04 | Invoice adjustment | Invoice | Validation | HIGH | NĐ 123/2020 | Ghi rõ điều chỉnh tăng/giảm cho hóa đơn số... |
| IN05 | E-invoice transmission | Invoice | Auto | HIGH | NĐ 123/2020 | Gửi CQT trong 24h |
| IN06 | E-invoice conversion | Invoice | Auto | MEDIUM | NĐ 123/2020 | Chuyển đổi HĐ điện tử không có mã → có mã |
| IN07 | Invoice archiving | Invoice | Validation | HIGH | NĐ 123/2020 | Lưu trữ 10 năm |
| IN08 | Invoice buyer info | Invoice | Validation | HIGH | NĐ 123/2020 | MST + tên + địa chỉ bắt buộc |
| IN09 | Invoice description | Invoice | Validation | MEDIUM | NĐ 123/2020 | Ghi rõ mặt hàng/dịch vụ, đơn vị tính |

### 13.5 CIT Rules (CR01-CR10)

| ID | Tên | Category | Type | Severity | Legal Basis | Mô tả |
|---|---|---|---|---|---|---|
| CR01 | CIT rate standard | CIT | Auto | HIGH | Luật TNDN | 20% on taxable income |
| CR02 | CIT rate SME | CIT | Auto | HIGH | Luật TNDN | 15% nếu DT < 3B; 17% nếu DT 3-20B |
| CR03 | CIT rate incentives | CIT | Auto | HIGH | Luật ĐT 2014 | KCN/KKT/CNC: 10-17% |
| CR04 | Non-deductible expense | CIT | Validation | HIGH | TT 20/2026 | Quảng cáo > 10% DT, lãi vay > 30% EBITDA, thiếu HĐ |
| CR05 | Advertisement limit | CIT | Validation | HIGH | TT 20/2026 | QC + KHTM + HH: 10% DT |
| CR06 | Interest deduction limit | CIT | Validation | HIGH | TT 20/2026 | Lãi vay không vượt 30% EBITDA |
| CR07 | Loss carryforward | CIT | Auto | HIGH | TT 20/2026 | Lỗ kết chuyển tối đa 5 năm |
| CR08 | Depreciation check | CIT | Validation | MEDIUM | TT 20/2026 | Khấu hao phải trong khung quy định |
| CR09 | Provision deduction | CIT | Validation | MEDIUM | TT 20/2026 | Dự phòng giảm giá HTK, nợ khó đòi phải đủ điều kiện |
| CR10 | CIT quarterly prepayment | CIT | Validation | HIGH | TT 20/2026 | Tạm nộp quý ≥ 80% quyết toán |

### 13.6 PIT Rules (PR01-PR10)

| ID | Tên | Category | Type | Severity | Legal Basis | Mô tả |
|---|---|---|---|---|---|---|
| PR01 | Personal deduction 2026 | PIT | Auto | HIGH | TT 109/2025 | Giảm trừ bản thân 15.5M/tháng |
| PR02 | Dependent deduction | PIT | Validation | HIGH | TT 109/2025 | 6.2M/NPT/tháng; phải đăng ký MST NPT |
| PR03 | PIT Brackets 2026 | PIT | Auto | HIGH | TT 109/2025 | 5 bậc mới từ 07/2026 |
| PR04 | Non-resident PIT | PIT | Auto | HIGH | TT 111/2013 | 20% flat on Vietnam-source income |
| PR05 | Foreigner PIT resident | PIT | Validation | HIGH | TT 111/2013 | 183 ngày threshold |
| PR06 | Insurance deduction | PIT | Auto | HIGH | TT 109/2025 | BHXH 8% + BHYT 1.5% + BHTN 1% |
| PR07 | Tax exemption threshold | PIT | Auto | MEDIUM | TT 109/2025 | Thu nhập ≤ giảm trừ → 0 PIT |
| PR08 | Dependent registration | PIT | Validation | MEDIUM | TT 111/2013 | Phải đăng ký trước 31/12 năm tính thuế |
| PR09 | PIT final settlement | PIT | Auto | HIGH | TT 109/2025 | Quyết toán trước 31/03 năm sau |
| PR10 | Monthly PIT declaration | PIT | Auto | HIGH | TT 109/2025 | Khai tháng trước 20 tháng sau |

### 13.7 FCT Rules (FR01-FR06)

| ID | Tên | Category | Type | Severity | Legal Basis | Mô tả |
|---|---|---|---|---|---|---|
| FR01 | FCT applicability | FCT | Validation | HIGH | TT 103/2014 | Nhà thầu NN không có cơ sở thường trú |
| FR02 | FCT rate allocation | FCT | Auto | HIGH | TT 103/2014 | 5/5, 3/2, 1/1, 2/2 tùy loại |
| FR03 | FCT withholding req | FCT | Validation | HIGH | TT 103/2014 | Bắt buộc khấu trừ khi thanh toán |
| FR04 | FCT threshold | FCT | Validation | MEDIUM | TT 103/2014 | ≥ 500K → khấu trừ |
| FR05 | FCT double tax treaty | FCT | Manual | MEDIUM | Luật QLT | Kiểm tra DTA trước khi apply rate |
| FR06 | FCT reporting | FCT | Auto | HIGH | TT 103/2014 | Tờ khai riêng cho FCT |

### 13.8 E-Invoice Rules (ER01-ER07)

| ID | Tên | Category | Type | Severity | Legal Basis | Mô tả |
|---|---|---|---|---|---|---|
| ER01 | E-invoice mandatory | E-Invoice | Validation | HIGH | NĐ 123/2020 | Bắt buộc từ 01/07/2022 |
| ER02 | E-invoice conversion deadline | E-Invoice | Calendar | HIGH | NĐ 123/2020 | Chuyển đổi HĐ mua vào trong 3 ngày |
| ER03 | E-invoice transmission | E-Invoice | Auto | HIGH | NĐ 123/2020 | Gửi CQT trong vòng 24h |
| ER04 | E-invoice buyer info | E-Invoice | Validation | HIGH | NĐ 123/2020 | Buyer MST + name + address |
| ER05 | E-invoice cancellation window | E-Invoice | Validation | MEDIUM | NĐ 123/2020 | Hủy trong 1 ngày lập |
| ER06 | E-invoice replacement | E-Invoice | Validation | MEDIUM | NĐ 123/2020 | Thay thế sau 1 ngày |
| ER07 | E-invoice second copy | E-Invoice | Auto | MEDIUM | NĐ 123/2020 | Bản sao không có giá trị CQT |

### 13.9 Period & Locking Rules (LR01-LR07)

| ID | Tên | Category | Type | Severity | Legal Basis | Mô tả |
|---|---|---|---|---|---|---|
| LR01 | Tax period lock | Calendar | Validation | HIGH | TT 80/2020 | Không sửa kỳ đã nộp |
| LR02 | Adjustment period limit | Calendar | Validation | HIGH | Luật QLT 38/2019 | Khai bổ sung trước khi có QĐ thanh tra |
| LR03 | Tax period open days | Calendar | Manual | MEDIUM | TT 80/2020 | Mở lại kỳ: tối đa 3 lần |
| LR04 | Tax period re-open reason | Calendar | Validation | MEDIUM | TT 80/2020 | Có lý do bắt buộc |
| LR05 | 01-KHBS submission | Calendar | Auto | HIGH | TT 80/2020 | Tờ khai bổ sung có số hiệu riêng |
| LR06 | Late lock penalty | Calendar | Auto | MEDIUM | NĐ 125/2020 | Cảnh báo nếu quá hạn |
| LR07 | Tax year closing | Calendar | Validation | HIGH | TT 80/2020 | Không sửa tờ khai năm sau 31/03 |

### 13.10 Authorization & Approval Rules (AR01-AR07)

| ID | Tên | Category | Type | Severity | Legal Basis | Mô tả |
|---|---|---|---|---|---|---|
| AR01 | Declaration approval | Auth | Validation | HIGH | Nội bộ | Tax Accountant prepare → Chief Accountant approve |
| AR02 | Threshold approval | Auth | Validation | HIGH | Nội bộ | > 100M → CFO approve |
| AR03 | Large tax payment approval | Auth | Validation | HIGH | Nội bộ | > 500M → CFO + CEO approve |
| AR04 | Adjustment approval | Auth | Validation | HIGH | Nội bộ | Mọi adjustment → Chief Accountant approve |
| AR05 | Period re-open approval | Auth | Validation | HIGH | Nội bộ | Chỉ Chief Accountant + CFO |
| AR06 | SoD violation | Auth | Validation | HIGH | Nội bộ | Không approve submission của mình |
| AR07 | E-invoice bulk approval | Auth | Validation | MEDIUM | Nội bộ | Bulk export > 100 → approve |

### 13.11 Reconciliation Rules (RR01-RR06)

| ID | Tên | Category | Type | Severity | Legal Basis | Mô tả |
|---|---|---|---|---|---|---|
| RR01 | GL vs declaration | Reconciliation | Validation | HIGH | TT 69/2025 | Chênh lệch < 0.5% |
| RR02 | E-invoice vs declaration | Reconciliation | Validation | HIGH | TT 69/2025 | Output/input khớp e-invoice |
| RR03 | 5M payment check | Reconciliation | Validation | HIGH | TT 69/2025 | Hóa đơn ≥ 5M có NH payment |
| RR04 | Input VAT unused report | Reconciliation | Auto | MEDIUM | Nội bộ | Danh sách hóa đơn chưa khấu trừ |
| RR05 | VAT rate analysis | Reconciliation | Auto | MEDIUM | Nội bộ | Biến động rate > 30% → investigate |
| RR06 | VAT volume anomaly | Reconciliation | Auto | MEDIUM | Nội bộ | Output VAT tăng > 50% MoM → investigate |

### 13.12 Compliance & Risk Rules (CRR01-CRR07)

| ID | Tên | Category | Type | Severity | Legal Basis | Mô tả |
|---|---|---|---|---|---|---|
| CRR01 | Tax inspection deadline | Risk | Calendar | HIGH | Luật QLT | 5 năm kể từ năm phát sinh |
| CRR02 | Data room availability | Risk | Validation | HIGH | Nội bộ | Data room export trong 30 phút |
| CRR03 | Declared amount vs paid | Risk | Auto | HIGH | TT 80/2020 | Nợ thuế > 90 ngày → enforce |
| CRR04 | Monthly PIT verification | Risk | Auto | MEDIUM | TT 111/2013 | Đối chiếu PIT với bảng lương |
| CRR05 | Loss position documentation | Risk | Validation | MEDIUM | TT 20/2026 | Hồ sơ kết chuyển lỗ phải đầy đủ |
| CRR06 | Tax incentive validity | Risk | Validation | HIGH | Luật ĐT 2014 | Hết hạn ưu đãi → chuyển sang 20% |
| CRR07 | Cross-border payment | Risk | Validation | HIGH | TT 103/2014 | Trước khi chuyển tiền ra nước ngoài → FCT check |

---

## 14. Final Deliverables: TAX Implementation Roadmap

### Phase 1: Core Tax Engine & VAT Functionality (4 weeks)

**Objectives:**
- Tự động xác định và quản lý thuế suất VAT (10%, 8%, 5%, 0%, miễn thuế)
- Tính output VAT trên hóa đơn bán ra
- Quản lý input VAT (khấu trừ/không khấu trừ)
- Cơ bản về e-invoice integration

**Deliverables:**
- VAT group master data table (migrations + seed)
- Tax rate determination service
- Output VAT calculation on sales invoices
- Input VAT tracking + deduction validation
- VAT on TSCĐ (1332)
- E-invoice integration module (push sales → receive tax code)

**New entities:**
- `vat_groups` table (rate, description, eligibility criteria, 8% reduction flag)
- `vat_group_products` table (product ↔ vat_group mapping)
- `vat_declarations` table (period, type, data JSON, status)
- `vat_submissions` table (status, tax_authority_ref, submitted_at, locked_by)

**Tests:** 35 tests (VAT group determination, rate calculation, deduction validation, declaration generation, e-invoice push)

### Phase 2: VAT Declaration & Submission (3 weeks)

**Objectives:**
- Tự động generate 01/GTGT
- Reconcile with e-invoice data
- Multi-branch declaration support
- HTKK export (XML)
- Historical period tracking

**Deliverables:**
- 01/GTGT auto-generation
- Output VAT reconciliation (GL vs declaration vs e-invoice)
- Input VAT reconciliation (GL vs declaration vs e-invoice)
- Adjustment handling (03/KHBS)
- HTKK XML export/import
- Multi-branch declaration consolidation/separation

**Tests:** 25 tests (declaration generation, reconciliation, adjustment flow, XML export)

### Phase 3: CIT Engine (3 weeks)

**Objectives:**
- Tự động xác định chi phí không được trừ
- Tính thu nhập tính thuế
- Ưu đãi thuế + kết chuyển lỗ
- CIT declaration (03/TNDN)

**Deliverables:**
- Non-deductible expense scanning engine
- CIT adjustment service
- Loss carryforward tracking
- Incentive management (CNC/KCN/KKT)
- 03/TNDN auto-generation
- CIT installment management

**New entities:**
- `cit_adjustments` table (source_entry, adjustment_type, amount, legal_basis)
- `loss_carryforwards` table (year, amount, remaining, used)
- `tax_incentives` table (type, rate, start_year, end_year, conditions)
- `cit_declarations` table (year, data JSON, status)
- `cit_installments` table (quarter, amount, paid, remaining)

**Tests:** 30 tests (adjustment logic, loss tracking, incentive calc, declaration)

### Phase 4: PIT & FCT (2 weeks)

**Objectives:**
- Tự động tính thuế TNCN từ bảng lương
- PIT declaration (05/KK-TNCN, 05/QTT-TNCN)
- FCT withholding management

**Deliverables:**
- PIT taxable income calculation (deductions, exemptions, 5 brackets 2026)
- Monthly PIT declaration (05/KK-TNCN)
- Annual PIT settlement (05/QTT-TNCN)
- FCT calculation + withholding
- PIT dependent registration portal
- PIT exemption/threshold check

**Tests:** 20 tests (PIT calculation, deduction management, FCT rate)

### Phase 5: Reconciliation, Reporting & Controls (3 weeks)

**Objectives:**
- Real-time VAT reconciliation
- Tax dashboard + reports
- Inspection data room
- Period locking + workflow

**Deliverables:**
- GL ↔ Tax declaration reconciliation report
- Tax dashboard (declaration status, pending payments, upcoming deadlines)
- Audit data room export (full traceability)
- Period lock workflow (draft → review → approve → submit → lock)
- Adjustments with approval chain
- Deadline monitoring (calendar + alerts)
- CIT inspection report
- VAT mismatched report
- Non-deductible expenses tracking
- Tax risk metrics

**Tests:** 25 tests (reconciliation, period lock, audit trail, data room export)

### Phase 6: E-Invoice & Integration (2 weeks)

**Objectives:**
- Full e-invoice lifecycle
- CQT integration
- Purchase invoice auto-import
- 5M non-cash payment check

**Deliverables:**
- E-invoice create/send/receive
- Tax code retrieval (+ retry logic)
- Purchase invoice auto-import
- Non-cash payment validation
- Invoice cancellation/replacement/adjustment
- E-invoice archive (10 year compliance)

**Tests:** 25 tests (e-invoice lifecycle, retry, validation, archive)

### Phase 7: Reports & Compliance (3 weeks)

**Objectives:**
- Complete tax reporting suite
- Compliance monitoring
- Tax planning dashboards
- Integration with Financial Statements

**Deliverables:**
- VAT status dashboard (real-time)
- Tax planning dashboard
- CIT status dashboard
- Tax payment status
- FS integration (BC01/02/03 tax line items)
- Tax burden analysis
- Year-end tax closing report
- Multi-year tax comparison
- Tax health score
- Deadline compliance

**Tests:** 25 tests (reporting, FS integration, multi-year comparison)

### Phase 8: Polish, Training & Handover (2 weeks)

**Objectives:**
- UX polish
- Performance optimization
- Documentation
- User acceptance testing

**Deliverables:**
- UI/UX improvements
- Help tooltips (Vietnamese)
- User manuals
- Process documentation
- Training materials
- UAT sign-off

**Tests:** Full regression (185 tests), performance benchmarks

---

### Implementation Summary Table

| Phase | Duration | Focus | New Tables | Tests |
|---|---|---|---|---|
| 1. Core Tax Engine | 4 weeks | VAT rate, Output/Input VAT, E-invoice basic | 4 | 35 |
| 2. VAT Declaration | 3 weeks | 01/GTGT, Reconciliation, Adjustment | 0 | 25 |
| 3. CIT Engine | 3 weeks | Non-deductible, Loss, Incentive | 5 | 30 |
| 4. PIT & FCT | 2 weeks | PIT 5 brackets, FCT | 0 | 20 |
| 5. Reconciliation & Controls | 3 weeks | Dashboard, Data room, Lock | 0 | 25 |
| 6. E-Invoice & Integration | 2 weeks | Full e-invoice lifecycle, CQT | 0 | 25 |
| 7. Reports & Compliance | 3 weeks | Tax dashboard, FS integration | 0 | 25 |
| 8. Polish & Handover | 2 weeks | UX, Docs, Training, UAT | 0 | 0 |
| **Total** | **22 weeks** | | **9** | **185** |

### Acceptance Criteria (Phase 1)

- VAT rate tự động gán từ product group
- Output VAT tính đúng trên mỗi hóa đơn bán ra
- Input VAT chỉ khấu trừ khi đủ 4 điều kiện
- TSCĐ → TK 1332 riêng
- E-invoice push → nhận được mã CQT (hoặc retry)
- Full audit trail cho mọi giao dịch VAT
- Dr = Cr cho mọi bút toán VAT
- Tất cả tests pass (35 tests Phase 1)

### Acceptance Criteria (Phase 2-7)

- Declaration 01/GTGT auto-generated
- Reconciliation: GL vs declaration vs e-invoice match
- CIT adjustment engine phát hiện ≥ 90% non-deductible expenses
- PIT calculation khớp với công thức 5 bậc 2026
- FCT đúng tỷ lệ theo TT 103/2014
- Data room export trong ≤ 30 phút
- Period lock không thể bypass
- Trial balance: Dr = Cr toàn hệ thống
- Audit trail đầy đủ cho mọi giao dịch
- 185 tests, 0 failures

---

> **Kết luận:** Tài liệu này cung cấp phân tích BA toàn diện cho TAX module — từ VAT, CIT, PIT, FCT đến e-invoice, reconciliation, internal controls và implementation roadmap. Tất cả dựa trên luật thuế Việt Nam 2025-2026. Implementation ước tính 22 tuần với 185 tests, 9 bảng mới. Tính năng giao thuế (tax calculation) phải đảm bảo Dr = Cr, audit trail đầy đủ, và period locking không thể bypass.
