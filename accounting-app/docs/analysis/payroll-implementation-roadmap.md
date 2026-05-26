# Payroll Module — Roadmap & Execution Plan

> **Phiên bản:** 1.0
> **Lead BA:** (generated from analysis)
> **Căn cứ:** `docs/analysis/payroll-engine-brain-logic.md` (1,465 lines, 9 sections)
> **Hiện trạng codebase:** Migration 056 + Models + Repository Interfaces + PDO implementations (partial) đã tồn tại. Service, Controller, Routes, Views, Tests CHƯA có.

---

## 1. Tổng quan hiện trạng (As-Is Assessment)

### 1.1 Đã có sẵn ✅

| Thành phần | Chi tiết |
|---|---|
| **Migration** | `056_create_payroll_tables.php` — 6 tables (salary_components, salary_formulas, payroll_periods, payroll_entries, payroll_details, payroll_detail_lines) |
| **Models** | PayrollPeriod, PayrollEntry, PayrollDetail, SalaryComponent, SalaryFormula |
| **Repository Interfaces** | PayrollEntryRepositoryInterface, PayrollPeriodRepositoryInterface, SalaryComponentRepositoryInterface, SalaryFormulaRepositoryInterface |
| **PDO Repos** | PDOPayrollEntryRepository, PDOPayrollPeriodRepository, PDOSalaryComponentRepository **(missing: PDOSalaryFormulaRepository)** |
| **Master Data** | Employee model + EmployeeRepository + EmployeeController (thiếu trường BHXH, PIT) |
| **Master Data** | Department model + DepartmentRepository + DepartmentController |
| **Sidebar** | 6 placeholder links đã có trong menu "Tiền lương" |

### 1.2 Cần xây dựng ❌

| Nhóm | Số lượng | Chi tiết |
|---|---|---|
| **Migration bổ sung** | 1–2 | Employee fields (insurance_salary, bank_account, dependent_count, tax_code...), region + shift master data |
| **Service** | 1 | `PayrollService.php` — core engine: Gross→Net, BHXH, PIT, posting |
| **Controller** | 1 | `PayrollController.php` — 15+ endpoints |
| **Views** | 6+ | Bảng lương, Tính lương, Bảo hiểm, Thuế TNCN, Phiếu lương, Kê khai BHXH |
| **Routes** | 15+ | `/luong/...` REST endpoints |
| **DI** | 2+ | PayrollService + PayrollController |
| **Permissions** | 1 | Module `payroll` với actions: read, create, update, delete, post, approve, export, close |
| **Tests** | 1 | `PayrollServiceTest.php` — 50+ test cases |
| **PDO Repository** | 1 | `PDOSalaryFormulaRepository.php` |

---

## 2. Kiến trúc module (Target Architecture)

### 2.1 Dependency Graph

```
PayrollController
  → PayrollService
      → EmployeeRepository (lấy lương Gross, phụ cấp, phòng ban)
      → DepartmentRepository (phân bổ chi phí 622/627/641/642)
      → PayrollPeriodRepository (quản lý kỳ lương)
      → PayrollEntryRepository (lưu bảng lương)
      → PayrollDetailRepository (lưu chi tiết lương)
      → SalaryComponentRepository (cấu hình khoản lương)
      → JournalService (sinh bút toán — Nợ 642/Có 334; Nợ 334/Có 3383...)
      → VoucherService (sinh số chứng từ lương)
      → AuditLoggerInterface (ghi audit trail)
      → AccountRepository (tra cứu tài khoản 334, 3383, 3384, 3386, 3335)
```

### 2.2 Module Boundaries

```
PayrollService KHÔNG gọi:
  - InventoryService (module riêng)
  - CashService (dùng JournalService để ghi bút toán chi lương)
  - ApService/ArService (module riêng)

PayrollService GỌI JournalService để:
  - Ghi bút toán lương (Nợ 642/Có 334)
  - Ghi bút toán BHXH (Nợ 334/Có 3383, Nợ 642/Có 3383)
  - Ghi bút toán thuế TNCN (Nợ 334/Có 3335)
  - Ghi bút toán chi lương (Nợ 334/Có 112)
```

### 2.3 Core Payroll Engine Data Flow

```
Input:
  payroll_period (kỳ lương: 2026-05)
  × employee records (danh sách nhân viên đang làm)
  × attendance data (chấm công — từ file import hoặc UI nhập)
  × salary_components (cấu hình khoản lương, BHXH, thuế)
  × SalaryFormulas (công thức tính: Gross→Net)
  → PayrollService::calculate()
    ├── Step 1: Tính Gross (lương cơ bản + phụ cấp + tăng ca + hoa hồng)
    ├── Step 2: Tính BHXH/BHYT/BHTN (8%/1.5%/1% — kiểm tra trần)
    ├── Step 3: Tính TNTT (Gross - 10.5% BH - 15.5tr - 6.2tr×NPT)
    ├── Step 4: Tính thuế TNCN (biểu 5 bậc: 5%–35%)
    ├── Step 5: Tính Net (Gross - BH - Thuế)
    ├── Step 6: Tính chi phí DN = Gross + 23.5% × lương đóng BHXH
    └── Step 7: Validate (Dr=Cr, Net≥0, không âm)
  → PayrollEntry (bảng lương tổng hợp)
  → PayrollDetail[] (chi tiết từng nhân viên)
  → Journal entries (bút toán tự động)
```

---

## 3. Execution Plan — 6 Phases

### Phase 1: Infrastructure & Data Model (Foundation)

**Mục tiêu:** Hoàn thiện CSDL, model, repositories cho payroll.

| # | Task | File | Phụ thuộc |
|---|---|---|---|
| 1.1 | Migration: Add employee insurance/PIT fields | `057_add_employee_payroll_fields.php` | — |
| 1.2 | Migration: Region master data | `058_create_regions_table.php` | — |
| 1.3 | Implement PDOSalaryFormulaRepository | `PDOSalaryFormulaRepository.php` | — |
| 1.4 | Add payroll_detail_lines account_code support | Sửa migration 056 (nếu chưa chạy) hoặc migration mới | — |
| 1.5 | Seed base salary_components + salary_formulas | Seed data trong migration 056 hoặc file riêng | 1.1 |
| 1.6 | Add permissions: module 'payroll' | Migration seed RBAC | — |

**Migration 057 fields cần thêm vào employees:**
```sql
ALTER TABLE employees ADD COLUMN IF NOT EXISTS (
  gross_salary DECIMAL(15,2) DEFAULT 0,        -- Lương Gross hiện tại
  insurance_salary DECIMAL(15,2) DEFAULT 0,    -- Lương đóng BHXH
  bank_account VARCHAR(50) DEFAULT NULL,        -- TK NH nhận lương
  bank_name VARCHAR(255) DEFAULT NULL,          -- Tên ngân hàng
  tax_code VARCHAR(20) DEFAULT NULL,            -- MST cá nhân
  dependent_count INT DEFAULT 0,                -- Số NPT
  probation_date DATE DEFAULT NULL,             -- Hết thử việc
  contract_type ENUM('indefinite','definite','probation','part_time') DEFAULT 'definite',
  region_id VARCHAR(36) DEFAULT NULL,           -- Vùng lương tối thiểu
  pay_method ENUM('cash','transfer') DEFAULT 'transfer',
  is_foreigner TINYINT(1) DEFAULT 0            -- NLĐ nước ngoài
);
```

**Seed salary_formulas:**
```
- GROSS_TO_NET: gross - insurance_ee - tax
- INSURANCE_EE: 0.105 × min(gross, ceiling)
- INSURANCE_ER: 0.235 × min(gross, ceiling)
- PIT_CALC: 5-bracket progressive formula
- OVERTIME_DAY: 1.5 × hourly_rate
- OVERTIME_WEEKEND: 2.0 × hourly_rate
- OVERTIME_HOLIDAY: 3.0 × hourly_rate
```

---

### Phase 2: Payroll Core Engine (Service Layer)

**Mục tiêu:** Xây dựng PayrollService với đầy đủ business logic.

| # | Task | Phức tạp | Phụ thuộc |
|---|---|---|---|
| 2.1 | `PayrollService::createPeriod()` — Tạo kỳ lương mới | Thấp | 1.1 |
| 2.2 | `PayrollService::calculate()` — Core engine Gross→Net | **CAO** | 1.2–1.6 |
| 2.3 | `PayrollService::calculateEmployeePayroll()` — Tính lương 1 NV | Trung bình | 2.2 |
| 2.4 | `PayrollService::calculateInsurance()` — Tính BHXH/BHYT/BHTN | Trung bình | 2.3 |
| 2.5 | `PayrollService::calculateTax()` — Tính thuế TNCN 5 bậc | Trung bình | 2.4 |
| 2.6 | `PayrollService::postPayroll()` — Sinh bút toán kế toán | **CAO** | 2.2, JournalService |
| 2.7 | `PayrollService::approvePayroll()` — Duyệt bảng lương | Thấp | 2.6 |
| 2.8 | `PayrollService::closePayroll()` — Chốt kỳ lương | Thấp | 2.7 |
| 2.9 | `PayrollService::generatePayslips()` — Tạo phiếu lương | Trung bình | 2.6 |
| 2.10 | `PayrollService::generateBankFile()` — File chuyển khoản | Trung bình | 2.6 |
| 2.11 | `PayrollService::adjustPayroll()` — Điều chỉnh hồi tố | Trung bình | 2.7–2.8 |
| 2.12 | `PayrollService::getPayrollSummary()` — Báo cáo tổng hợp | Thấp | 2.6 |

#### 2.2 — Chi tiết Core Engine (calculate)

```php
public function calculate(string $periodId, ?array $employeeIds = null): PayrollEntry
{
    // 1. Load period + validate (must be 'open')
    // 2. Load employees (all active, or filtered)
    // 3. For each employee:
    //    a. Load salary components (earning + allowance)
    //    b. Calculate gross = basic_salary × (working_days / 26) + allowances + overtime
    //    c. Calculate insurance_salary = min(gross, REGIONAL_CEILING)
    //    d. Calculate insurance_ee = insurance_salary × 10.5%
    //    e. Calculate taxable_income = max(0, gross - insurance_ee - personal_deduction - dependent_deductions)
    //    f. Calculate tax = progressive_5_bracket(taxable_income)
    //    g. Calculate net = gross - insurance_ee - tax - advances - other_deductions
    //    h. Calculate cost = gross + insurance_salary × 23.5%
    //    i. Create PayrollDetail
    //    j. Create PayrollDetailLines for each component
    // 4. Aggregate totals -> PayrollEntry
    // 5. Validate: total_net >= 0, no negative values
    // 6. Save PayrollEntry + PayrollDetails + PayrollDetailLines
    // 7. Return PayrollEntry
}
```

#### 2.6 — Chi tiết Post Payroll (sinh bút toán)

```php
public function postPayroll(string $entryId): void
{
    // 1. Load PayrollEntry + PayrollDetails
    // 2. Begin transaction
    // 3. Group details by department -> cost account (622/627/641/642)
    // 4. Journal entry 1: Salary expense
    //    Dr 622/627/641/642 (total_gross by dept)
    //    Cr 334 (total_gross)
    //
    // 5. Journal entry 2: Employer insurance + union fee
    //    Dr 622/627/641/642 (total_insurance_er + union_fee)
    //    Cr 3383 (BHXH er), Cr 3384 (BHYT er), Cr 3386 (BHTN er), Cr 3382 (KPCĐ)
    //
    // 6. Journal entry 3: Employee insurance deduction
    //    Dr 334 (total_insurance_ee)
    //    Cr 3383 (BHXH ee), Cr 3384 (BHYT ee), Cr 3386 (BHTN ee)
    //
    // 7. Journal entry 4: PIT withholding
    //    Dr 334 (total_tax)
    //    Cr 3335 (thuế TNCN)
    //
    // 8. Update PayrollEntry status = 'posted'
    // 9. Commit transaction
    // 10. Audit log: payroll.posted
}
```

---

### Phase 3: API Layer (Controller + Routes)

**Mục tiêu:** RESTful API cho payroll.

| # | Endpoint | Method | Controller Method | Phụ thuộc |
|---|---|---|---|---|
| 3.1 | `/api/payroll/periods` | GET | listPeriods() | 2.1 |
| 3.2 | `/api/payroll/periods` | POST | createPeriod() | 2.1 |
| 3.3 | `/api/payroll/periods/:id` | GET | getPeriod() | 2.1 |
| 3.4 | `/api/payroll/periods/:id/close` | POST | closePeriod() | 2.8 |
| 3.5 | `/api/payroll/calculate/:periodId` | POST | calculate() | 2.2–2.5 |
| 3.6 | `/api/payroll/entries` | GET | listEntries() | 2.6 |
| 3.7 | `/api/payroll/entries/:id` | GET | getEntry() | 2.6 |
| 3.8 | `/api/payroll/entries/:id/details` | GET | getEntryDetails() | 2.6 |
| 3.9 | `/api/payroll/entries/:id/approve` | POST | approve() | 2.7 |
| 3.10 | `/api/payroll/entries/:id/post` | POST | postEntry() | 2.6 |
| 3.11 | `/api/payroll/entries/:id/payslips` | GET | getPayslips() | 2.9 |
| 3.12 | `/api/payroll/entries/:id/bank-file` | GET | generateBankFile() | 2.10 |
| 3.13 | `/api/payroll/entries/:id/adjust` | POST | adjustEntry() | 2.11 |
| 3.14 | `/api/payroll/summary/:periodId` | GET | summary() | 2.12 |
| 3.15 | `/api/payroll/salary-components` | CRUD | SalaryComponentController | 1.5 |
| 3.16 | `/api/payroll/employees/:id/payroll-detail` | GET | getEmployeeDetail() | 2.3 |
| 3.17 | `/api/payroll/entries/:id/pay` | POST | paySalaries() | 2.6 |

---

### Phase 4: Presentation Layer (Views)

**Mục tiêu:** 6 giao diện người dùng cho module tiền lương.

| # | View | URL | File | Tính năng chính |
|---|---|---|---|---|
| 4.1 | Bảng lương | `/luong/bang-luong` | `payroll_entry.php` | Danh sách bảng lương theo kỳ, CRUD, post |
| 4.2 | Tính lương | `/luong/tinh-luong` | `payroll_calculate.php` | Chọn kỳ, chọn NV, chạy engine, xem preview |
| 4.3 | Trích bảo hiểm | `/luong/trich-bao-hiem` | `payroll_insurance.php` | Chi tiết BHXH/BHYT/BHTN từng NV |
| 4.4 | Tính thuế TNCN | `/luong/tinh-thue` | `payroll_tax.php` | Chi tiết thuế TNCN từng NV |
| 4.5 | Phiếu lương | `/luong/phieu-luong` | `payroll_payslip.php` | In phiếu lương từng NV |
| 4.6 | Kê khai BHXH | `/luong/ke-khai-bhxh` | `payroll_declaration.php` | Dữ liệu kê khai BHXH |

**Cập nhật layout.php:**
- `href="/luong/bang-luong"` thay cho `#`
- Thêm các menu items mới
- Active state detection cho payroll sub-menu

---

### Phase 5: Testing

**Mục tiêu:** 50+ test cases, full coverage của core engine.

| # | Test group | Số test | Coverage |
|---|---|---|---|
| 5.1 | Period management | 5 | create, open, close, reopen, status validation |
| 5.2 | Gross calculation | 10 | basic salary, allowances, overtime (150%/200%/300%), deductions |
| 5.3 | Insurance calculation | 8 | EE 10.5%, ER 23.5%, ceiling, floor, region-specific, foreign employees |
| 5.4 | PIT calculation | 10 | 5 brackets, dependent deduction, personal deduction, zero tax, high income |
| 5.5 | Net calculation | 5 | gross → net, negative net (block), rounding |
| 5.6 | Journal posting | 8 | Dr=Cr, 4 journal entries, cost allocation by department |
| 5.7 | Approval workflow | 5 | draft→approved→posted, reject |
| 5.8 | Exception cases | 8 | employee resigned, period closed, insurance ceiling exceeded, zero working days, foreign employees, multi-branch |
| 5.9 | Audit logging | 3 | every action logged, old/new values |

---

### Phase 6: Compliance & Integration

**Mục tiêu:** Tích hợp với các module hiện có.

| # | Task | Phụ thuộc |
|---|---|---|
| 6.1 | Tích hợp chi lương qua CashService/JournalService | Phase 2 |
| 6.2 | Tích hợp GL: xem sổ cái TK 334, 3383, 3384, 3386, 3335 | Phase 2 |
| 6.3 | Tích hợp PeriodService: kiểm tra lương đã chốt trước khi đóng kỳ | Phase 2 |
| 6.4 | Tích hợp FsService: chi phí lương vào BC02 | Phase 2 |
| 6.5 | Xuất file kê khai BHXH (D02-LT, D01-TS, TK3-TS) | Phase 4 |
| 6.6 | Xuất file chuyển tiền lương (theo template ngân hàng) | Phase 4 |
| 6.7 | Import chấm công từ file Excel/CSV | Phase 4 |
| 6.8 | Audit trail: PayrollController ghi ActionJournal | Phase 3 |

---

## 4. Detailed Timeline

| Phase | Nội dung | File count | Test count | Effort (relative) |
|---|---|---|---|---|
| **P1** | Infrastructure & Data | 5 files | — | ⭐⭐ |
| **P2** | Core Engine (PayrollService) | 1 file | ~50 | ⭐⭐⭐⭐⭐ |
| **P3** | Controller + Routes | 2 files | — | ⭐⭐ |
| **P4** | Views (6 screens) | 6 files | — | ⭐⭐⭐ |
| **P5** | Tests | 1 file | 50+ | ⭐⭐⭐ |
| **P6** | Compliance & Integration | 3–5 files | — | ⭐⭐ |

**Tổng: ~20 files mới, ~50 tests, 0 existing file modifications (except routes.php, services.php, layout.php)**

### Dependency order:

```
P1 ──→ P2 ──→ P3 ──→ P4
  │       │       └──→ P6
  │       └──→ P5
  └──→ P5 (Infrastructure tests)
```

---

## 5. Risk Assessment

| Risk | Severity | Likelihood | Mitigation |
|---|---|---|---|
| **Business logic sai (Gross→Net)** | Critical | Low | TDD: test mọi bracket, mọi loại BH |
| **Dr ≠ Crsau post** | Critical | Low | Transaction + JournalService validation |
| **PIT 5-bracket sai (luật mới 2026)** | High | Low | Verify vs Luật 109/2025/QH15 |
| **Insurance ceiling thay đổi** | Medium | Medium | Config-driven, không hardcode |
| **Employee model thiếu field** | Medium | Low | Migration bổ sung 057 |
| **Concurrent payroll calculation** | Medium | Low | SELECT FOR UPDATE trên period |
| **Multi-branch inconsistency** | Medium | Medium | Region-based config, per-branch validation |
| **Phạt chậm nộp BHXH** | High | Low | Auto reminder, deadline enforcement |

### 5.1 Key Compliance Risks

| Quy định | Risk | Xử lý trong engine |
|---|---|---|
| Lương thử việc ≥ 85% | Trả thấp hơn → phạt 2–10tr | Validate trong calculate(): check probation employees |
| BHXH trần 46.8tr | Vượt trần → tính sai | Auto cap at min(gross, ceiling) |
| BHTN trần theo vùng | Sai vùng → sai BHTN | Region-based config |
| Tăng ca ≤ 200h/năm | Vượt → phạt 5–50tr | Accumulate + warn/block |
| Nộp thuế TNCN trước 20 hàng tháng | Chậm → lãi + phạt | Deadline reminder |
| Nộp BHXH trước cuối tháng sau | Chậm → lãi 0.03%/ngày | Deadline reminder |

---

## 6. Files to Create — Complete Checklist

### Phase 1 — Infrastructure
```
[ ] database/migrations/057_add_employee_payroll_fields.php
[ ] src/Accounting/Infrastructure/Persistence/PDOSalaryFormulaRepository.php
```

### Phase 2 — Core Engine
```
[ ] src/Accounting/Domain/Service/PayrollService.php (500–800 lines)
```

### Phase 3 — API
```
[ ] src/Accounting/Interfaces/HTTP/PayrollController.php (200–400 lines)
[ ] config/routes.php (add ~17 endpoints in existing defineRoutes())
[ ] config/services.php (add PayrollService + PayrollController)
```

### Phase 4 — Views
```
[ ] public/views/payroll_entry.php        (bảng lương)
[ ] public/views/payroll_calculate.php    (tính lương)
[ ] public/views/payroll_insurance.php    (trích bảo hiểm)
[ ] public/views/payroll_tax.php          (tính thuế TNCN)
[ ] public/views/payroll_payslip.php      (phiếu lương)
[ ] public/views/payroll_declaration.php  (kê khai BHXH)
[ ] public/views/layout.php               (update sidebar links)
```

### Phase 5 — Tests
```
[ ] tests/PayrollServiceTest.php (50+ tests)
```

### Phase 6 — Compliance
```
[ ] src/Accounting/Domain/Service/PayrollDeclarationService.php (optional)
[ ] (existing) PeriodService — add payroll check in canClose()
```

---

## 7. Post-Launch Enhancement Backlog

Backlog items không nằm trong MVP nhưng cần cho production:

| Item | Mức độ ưu tiên | Ghi chú |
|---|---|---|
| Import chấm công Excel/CSV | Trung bình | SME không có máy chấm công |
| Tích hợp máy chấm công (vân tay/khuôn mặt) | Thấp | Cần API từ nhà cung cấp |
| Employee self-service portal (xem payslip, đăng ký NPT) | Thấp | Giai đoạn 2 |
| Auto-kê khai BHXH online (cổng DVC) | Trung bình | Cần tích hợp API BHXH |
| Quyết toán thuế TNCN cuối năm | Trung bình | Module riêng |
| Báo cáo lao động (tình hình sử dụng LĐ) | Thấp | Yêu cầu của Sở LĐTBXH |
| Tính lương Gross từ Net (reverse calculation) | Trung bình | Cho hợp đồng Net |
| Bảng lương theo dự án (phân bổ chi phí) | Thấp | Kế toán quản trị |
| Phân bổ chi phí lương theo nhiều tiêu chí | Thấp | Nâng cao |
| Multi-currency salary (cho NLĐ nước ngoài) | Thấp | Cần Fx integration |

---

## 8. Go/No-Go Criteria

**Pre-requisites before starting Phase 2 (Core Engine):**
- [ ] Migration 057 đã chạy, employees có đủ field
- [ ] PDOSalaryFormulaRepository implemented
- [ ] Salary components seeded (đủ 13 components mặc định)
- [ ] Salary formulas seeded (gross_to_net, insurance, tax)
- [ ] Permissions module 'payroll' added
- [ ] Tất cả existing tests vẫn pass

**Phase 2 completion criteria:**
- [ ] `PayrollService::calculate()` — 100% test pass
- [ ] Net = Gross - BH - Thuế (đúng với mọi bracket)
- [ ] Dr = Cr cho mọi bút toán post
- [ ] Insurance ceiling enforced
- [ ] PIT 5-bracket đúng Luật 109/2025/QH15
- [ ] Audit log đầy đủ

**Production readiness:**
- [ ] Full test suite pass (0 failures)
- [ ] Manual smoke test: tạo kỳ → tính lương → post → chi
- [ ] Rollback plan documented

---

> **Tóm tắt cho CFO:** Payroll module là module lớn nhất còn lại (~20 files, ~50 tests, 6 phases). Core engine (Phase 2) là rủi ro cao nhất — cần TDD + verification với luật 2026. Time-to-value: 3 phases đầu (P1→P3) cho API hoàn chỉnh, P4 cho UI.
