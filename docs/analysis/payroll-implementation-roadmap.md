# Payroll Module — Implementation Roadmap & Execution Plan

> **BA Lead:** Phân tích chiến lược triển khai module Tiền lương  
> **Phiên bản:** 1.0  
> **Ngày:** 2026-05-29  
> **Cơ sở:** `docs/analysis/payroll-engine-brain-logic.md` (14 sections, 1928 lines)  
> **Hiện trạng code:** PayrollService 669 lines, 44 tests, **0 controllers, 0 views, 0 UI**

---

## Mục lục

1. [Current State Assessment](#1-current-state-assessment)
2. [Phase Architecture & Dependencies](#2-phase-architecture--dependencies)
3. [Phase 0.1 — Employee Master & Config](#3-phase-01--employee-master--config)
4. [Phase 0.2 — Attendance & Timekeeping](#4-phase-02--attendance--timekeeping)
5. [Phase 0.3 — Payroll Calculation Engine & Payslip](#5-phase-03--payroll-calculation-engine--payslip)
6. [Phase 0.4 — Approval Workflow & Period Close](#6-phase-04--approval-workflow--period-close)
7. [Phase 0.5 — Payment & Posting](#7-phase-05--payment--posting)
8. [Phase 1.1 — Reports & Tax Declarations](#8-phase-11--reports--tax-declarations)
9. [Phase 1.2 — Final Settlement & Adjustments](#9-phase-12--final-settlement--adjustments)
10. [Phase 2 — Advanced Features](#10-phase-2--advanced-features)
11. [Resource & Timeline Summary](#11-resource--timeline-summary)
12. [Risk Register & Mitigation](#12-risk-register--mitigation)
13. [Rollout & Cutover Plan](#13-rollout--cutover-plan)
14. [Definition of Done per Phase](#14-definition-of-done-per-phase)

---

## 1. Current State Assessment

### 1.1 What Already Exists (Do Not Rebuild)

| Component | Location | Lines | Status | Quality |
|---|---|---|---|---|
| `PayrollService` | `src/Accounting/Domain/Service/PayrollService.php` | 669 | ✅ Done | 44 tests pass |
| `PayrollEntry` model | `src/Accounting/Domain/Model/PayrollEntry.php` | — | ✅ Done | Mô hình đầy đủ |
| `PayrollPeriod` model | `src/Accounting/Domain/Model/PayrollPeriod.php` | — | ✅ Done | |
| `PayrollDetail` model | `src/Accounting/Domain/Model/PayrollDetail.php` | — | ✅ Done | |
| `Employee` model | `src/Accounting/Domain/Model/Employee.php` | — | ✅ Done | Cơ bản, cần mở rộng |
| `PayrollEntryRepositoryInterface` | `src/Accounting/Domain/Repository/` | — | ✅ Done | |
| `PayrollPeriodRepositoryInterface` | `src/Accounting/Domain/Repository/` | — | ✅ Done | |
| `SalaryComponentRepositoryInterface` | `src/Accounting/Domain/Repository/` | — | ✅ Done | |
| `EmployeeRepositoryInterface` | `src/Accounting/Domain/Repository/` | — | ✅ Done | |
| `PDOPayrollEntryRepository` | `src/Accounting/Infrastructure/Persistence/` | — | ✅ Done | |
| `PDOPayrollPeriodRepository` | `src/Accounting/Infrastructure/Persistence/` | — | ✅ Done | |
| `PDOSalaryComponentRepository` | `src/Accounting/Infrastructure/Persistence/` | — | ✅ Done | |
| `PDOEmployeeRepository` | `src/Accounting/Infrastructure/Persistence/` | — | ✅ Done | |
| `PayrollServiceTest` | `tests/PayrollServiceTest.php` | 250 | ✅ Done | 44 tests, 0 failed |

### 1.2 What Must Be Built

| Layer | Số lượng | Ghi chú |
|---|---|---|
| DB migrations mới | 12 | employees mở rộng + 11 tables mới |
| Models mới | 8 | Allowance, Attendance, Payslip, Overtime, Leave, Advance, Adjustment, Config |
| Repository Interfaces mới | 8 | |
| PDO Repositories mới | 8 | |
| Controllers mới | 8 | Employee, Attendance, Payroll, Payslip, Payment, Tax, FinalSettlement, Config |
| Views / UI Screens | 20 | |
| Services mới (hoặc mở rộng) | 5 | |
| Tests mới | ~140 | Ngoài 44 existing |

### 1.3 Key Architectural Decisions

| Decision | Rationale |
|---|---|
| **Employee model mở rộng, không viết lại** | Đã có Employee model + repository, chỉ thêm fields |
| **PayrollService giữ nguyên interface** | 44 tests phụ thuộc, không phá vỡ |
| **New services cho từng sub-domain** | AttendanceService, PaymentService riêng — không nhồi vào PayrollService |
| **Controllers theo module** | PayrollController riêng, EmployeeController riêng |
| **Views theo mẫu FA module** | Tham khảo `fixed_asset_acquisition.php` + `fixed_asset_disposal.php` |
| **Config trong DB, không hardcode** | Tỷ lệ BHXH, biểu thuế, lương tối thiểu = config-driven |

---

## 2. Phase Architecture & Dependencies

### 2.1 Dependency Graph

```
Phase 0.1: Employee Master + Config
  └── Không phụ thuộc gì
      ↓
Phase 0.2: Attendance & Timekeeping
  └── Phụ thuộc: Employee Master (0.1)
      ↓
Phase 0.3: Payroll Engine + Payslip
  └── Phụ thuộc: Employee (0.1) + Attendance (0.2)
      ↓
Phase 0.4: Approval Workflow + Period Close
  └── Phụ thuộc: Payroll Engine (0.3)
      ↓
Phase 0.5: Payment + Posting
  └── Phụ thuộc: Approval (0.4)
      ↓
Phase 1.1: Reports + Tax
  └── Phụ thuộc: Payment (0.5)
      ↓
Phase 1.2: Final Settlement + Adjustments
  └── Phụ thuộc: Payroll Engine (0.3)
      ↓
Phase 2: Advanced Features
  └── Phụ thuộc: Tất cả phases trước
```

### 2.2 Vertical Slice Pattern

Mỗi phase = 1 vertical slice hoàn chỉnh:

```
DB Migration → Model → Repository Interface → PDO Repository
  → Service → Controller → Routes → DI → Views → Tests
```

Đây là pattern bắt buộc theo §18 New Entity Checklist của AGENTS.md.

### 2.3 Team Sizing

| Phase | Developer | BA/QA | Tổng effort |
|---|---|---|---|
| 0.1 | 1 dev | 0.5 BA | ~16 dev-days |
| 0.2 | 1 dev | 0.5 BA | ~10 dev-days |
| 0.3 | 1 dev | 1 BA | ~16 dev-days |
| 0.4 | 1 dev | 0.5 BA | ~8 dev-days |
| 0.5 | 1 dev | 0.5 BA | ~12 dev-days |
| 1.1 | 1 dev | 1 BA | ~10 dev-days |
| 1.2 | 1 dev | 0.5 BA | ~8 dev-days |
| 2 | 1 dev | 0.5 BA | ~8 dev-days |
| **Tổng** | **1 dev** | **0.5 BA** | **~88 dev-days** |

### 2.4 Execution Strategy: MMF (Minimum Marketable Feature)

| MMF | Phases | Giá trị mang lại | ROI |
|---|---|---|---|
| **MMF-1: Tính lương cơ bản** | 0.1 + 0.2 + 0.3 | Thay thế Excel tính lương ngay | **CAO NHẤT** |
| **MMF-2: Duyệt + Chi lương** | 0.4 + 0.5 | Workflow + hạch toán tự động | CAO |
| **MMF-3: Báo cáo + Thuế** | 1.1 | Kê khai BHXH/TNCN tự động | TRUNG BÌNH |
| **MMF-4: Nâng cao** | 1.2 + 2 | Quyết toán, điều chỉnh, mobile | THẤP |

**Khuyến nghị:** Ưu tiên MMF-1 + MMF-2 (Phase 0.1 → 0.5) trước khi release cho user test.

---

## 3. Phase 0.1 — Employee Master & Config

**Mục tiêu:** Quản lý hồ sơ nhân viên + cấu hình hệ thống lương (tỷ lệ BHXH, biểu thuế, lương tối thiểu vùng)

### 3.1 Tasks

| ID | Task | Entity Checklist Step | Effort (giờ) | Phụ thuộc |
|---|---|---|---|---|
| 0.1.1 | **Migration: Mở rộng employees table** | §18.1 | 2 | — |
| 0.1.2 | **Migration: Tạo payroll_config table** | §18.1 | 1 | — |
| 0.1.3 | **Migration: Tạo allowances table** | §18.1 | 1 | — |
| 0.1.4 | **Migration: Tạo employee_allowances table** | §18.1 | 1 | 0.1.3 |
| 0.1.5 | **Migration: Tạo pit_dependents table** | §18.1 | 1 | — |
| 0.1.6 | **Mở rộng Employee model** (thêm fields: insurance_salary, region, dependents, bank, contract_type, status) | §18.2 | 3 | 0.1.1 |
| 0.1.7 | **Tạo Allowance model** | §18.2 | 2 | 0.1.3 |
| 0.1.8 | **Tạo EmployeeAllowance model** | §18.2 | 1 | 0.1.4 |
| 0.1.9 | **Tạo PayrollConfig model** | §18.2 | 2 | 0.1.2 |
| 0.1.10 | **Tạo PitDependent model** | §18.2 | 1 | 0.1.5 |
| 0.1.11 | **Tạo AllowanceRepositoryInterface** | §18.3 | 1 | — |
| 0.1.12 | **Tạo PayrollConfigRepositoryInterface** | §18.3 | 1 | — |
| 0.1.13 | **Tạo EmployeeAllowanceRepositoryInterface** | §18.3 | 1 | — |
| 0.1.14 | **Tạo PitDependentRepositoryInterface** | §18.3 | 1 | — |
| 0.1.15 | **Tạo PDOAllowanceRepository** | §18.4 | 2 | 0.1.11 |
| 0.1.16 | **Tạo PDOPayrollConfigRepository** | §18.4 | 2 | 0.1.12 |
| 0.1.17 | **Tạo PDOEmployeeAllowanceRepository** | §18.4 | 2 | 0.1.13 |
| 0.1.18 | **Tạo PDOPitDependentRepository** | §18.4 | 1 | 0.1.14 |
| 0.1.19 | **Tạo EmployeeController** (CRUD employees) | §18.5 | 6 | 0.1.6 |
| 0.1.20 | **Tạo PayrollConfigController** (config management) | §18.5 | 4 | 0.1.9 |
| 0.1.21 | **Tạo AllowanceController** (CRUD allowances) | §18.5 | 3 | 0.1.7 |
| 0.1.22 | **Thêm routes** (employee, config, allowance) | §18.6 | 2 | 0.1.19-21 |
| 0.1.23 | **Cập nhật DI container** (services.php) | §18.7 | 1 | 0.1.15-18 |
| 0.1.24 | **Tạo views: employee_list, employee_detail, allowance_list, payroll_config** | §18.8 | 12 | 0.1.19-21 |
| 0.1.25 | **Cập nhật sidebar** (layout.php) | §18.9 | 1 | 0.1.24 |
| 0.1.26 | **Tests: Employee CRUD** | §18.10 | 6 | 0.1.19 |
| 0.1.27 | **Tests: Config CRUD** | §18.10 | 3 | 0.1.20 |
| 0.1.28 | **Thêm permissions** (payroll, employee module) | §18.11 | 1 | — |
| 0.1.29 | **AuditLogger trong controller** | §18.12 | 1 | — |
| **Tổng** | | | **~65 giờ (~8 dev-days)** | |

### 3.2 Key Implementation Details

**Employee model mở rộng — fields cần thêm:**

```php
// Existing: id, code, name, department_id, position, phone, email,
//           gross_salary, bank_account, bank_name, tax_code, dependents,
//           region, contract_type

// Thêm:
private ?string $insuranceNumber;     // Số BHXH
private ?float $insuranceSalary;      // Lương đóng BHXH (riêng)
private string $status;               // active | probation | resigned | suspended
private ?DateTime $hireDate;          // Ngày vào làm
private ?DateTime $contractDate;      // Ngày ký HĐ
private ?DateTime $resignDate;        // Ngày nghỉ việc
private ?string $resignReason;        // Lý do nghỉ
private ?float $probationPercentage;  // % lương thử việc (mặc định 0.85)
private string $branchId;             // Chi nhánh
private ?array $allowances;           // EmployeeAllowance[]
private ?array $dependents;           // PitDependent[]
private int $annualLeaveBalance;      // Ngày phép tồn
```

**PayrollConfig — cấu trúc:**

```php
class PayrollConfig {
    private string $configKey;       // 'bhxh_rate_ee', 'tax_personal_deduction', ...
    private string $configValue;     // '0.08', '15500000'
    private ?DateTime $effectiveFrom; // Ngày hiệu lực
    private ?DateTime $effectiveTo;   // Ngày hết hiệu lực (null = còn hiệu lực)
    private string $description;      // Mô tả bằng tiếng Việt
    private string $category;         // 'insurance' | 'tax' | 'wage' | 'overtime'
}
```

**Config seed data cần có sẵn:**

| Key | Value | Category |
|---|---|---|
| bhxh_rate_ee | 0.08 | insurance |
| bhyt_rate_ee | 0.015 | insurance |
| bhtn_rate_ee | 0.01 | insurance |
| bhxh_rate_er | 0.175 | insurance |
| bhyt_rate_er | 0.03 | insurance |
| bhtn_rate_er | 0.01 | insurance |
| kpcd_rate_er | 0.02 | insurance |
| bhxh_ceiling_multiplier | 20 | insurance |
| bhyt_ceiling_multiplier | 14 | insurance |
| base_salary | 2340000 | wage |
| region_min_wage_i | 4960000 | wage |
| region_min_wage_ii | 4410000 | wage |
| region_min_wage_iii | 3860000 | wage |
| region_min_wage_iv | 3450000 | wage |
| tax_personal_deduction | 15500000 | tax |
| tax_dependent_deduction | 6200000 | tax |
| tax_bracket_1_max | 10000000 | tax |
| tax_bracket_2_max | 30000000 | tax |
| ... | ... | ... |
| default_working_days | 26 | attendance |
| late_threshold_minutes | 30 | attendance |
| late_to_absence_ratio | 3 | attendance |

### 3.3 Acceptance Criteria

| AC | Mô tả |
|---|---|
| AC-0.1-01 | CRUD employee: create, read, update, list, search |
| AC-0.1-02 | Employee validation: lương ≥ tối thiểu vùng, số CMND không trùng |
| AC-0.1-03 | Config: CRUD config, read by key, get effective config by date |
| AC-0.1-04 | Allowance: CRUD allowance types, gán cho employee |
| AC-0.1-05 | PIT dependents: CRUD dependents per employee, MST validation |
| AC-0.1-06 | Audit trail: mọi thay đổi ghi log |
| AC-0.1-07 | Full test suite ≥ 15 tests, 0 failures |

---

## 4. Phase 0.2 — Attendance & Timekeeping

**Mục tiêu:** Quản lý chấm công, tăng ca, nghỉ phép, vi phạm

### 4.1 Tasks

| ID | Task | Effort (giờ) | Phụ thuộc |
|---|---|---|---|
| 0.2.1 | Migration: attendance_records table | 2 | — |
| 0.2.2 | Migration: attendance_summary table | 1 | — |
| 0.2.3 | Migration: leave_requests table | 1 | — |
| 0.2.4 | Migration: overtime_requests table | 1 | — |
| 0.2.5 | Tạo AttendanceRecord model | 1 | 0.2.1 |
| 0.2.6 | Tạo AttendanceSummary model | 1 | 0.2.2 |
| 0.2.7 | Tạo LeaveRequest model | 1 | 0.2.3 |
| 0.2.8 | Tạo OvertimeRequest model | 1 | 0.2.4 |
| 0.2.9 | Tạo AttendanceRepositoryInterface | 1 | — |
| 0.2.10 | Tạo LeaveRepositoryInterface | 1 | — |
| 0.2.11 | Tạo OvertimeRepositoryInterface | 1 | — |
| 0.2.12 | Tạo PDOAttendanceRepository | 2 | 0.2.9 |
| 0.2.13 | Tạo PDOLeaveRepository | 1 | 0.2.10 |
| 0.2.14 | Tạo PDOOvertimeRepository | 1 | 0.2.11 |
| 0.2.15 | **Tạo AttendanceService** (import, validate, summarize) | 6 | 0.2.5-14 |
| 0.2.16 | Tạo AttendanceController | 4 | 0.2.15 |
| 0.2.17 | Thêm routes (attendance) | 1 | 0.2.16 |
| 0.2.18 | Cập nhật DI | 1 | 0.2.12-14 |
| 0.2.19 | Views: attendance_import, attendance_list, attendance_approve, overtime_list, leave_list | 8 | 0.2.16 |
| 0.2.20 | Cập nhật sidebar | 1 | 0.2.19 |
| 0.2.21 | Tests: AttendanceService | 6 | 0.2.15 |
| 0.2.22 | Permissions + Audit | 1 | — |
| **Tổng** | | **~43 giờ (~5 dev-days)** | |

### 4.2 Key Implementation Details

**AttendanceService — core methods:**

```php
class AttendanceService {
    // Import từ Excel hoặc nhập tay
    public function import(array $records, string $periodId): array;

    // Tự động quy đổi: đi muộn 3 lần = 1 ngày công mất
    public function summarize(string $periodId): array;

    // Validate attendance trước khi duyệt
    public function validate(string $periodId): array; // [errors[], warnings[]]

    // Duyệt chấm công
    public function approveAttendance(string $periodId, string $approvedBy): void;

    // Hủy duyệt
    public function reopenAttendance(string $periodId, string $approvedBy): void;
}
```

**Attendance import format (Excel/JSON):**

```json
{
  "employee_id": "emp_1",
  "period_code": "202606",
  "working_days": 24,
  "overtime_hours": {
    "weekday": 10,
    "weekend": 4,
    "holiday": 0,
    "night_weekday": 2,
    "night_weekend": 0,
    "night_holiday": 0
  },
  "unpaid_leave_days": 1,
  "paid_leave_days": 0,
  "late_count": 2,
  "early_leave_count": 0,
  "no_checkin_count": 1
}
```

### 4.3 Acceptance Criteria

| AC | Mô tả |
|---|---|
| AC-0.2-01 | Import attendance từ Excel/JSON |
| AC-0.2-02 | Tự động quy đổi vi phạm (đi muộn → công mất) |
| AC-0.2-03 | Validate: không vượt quá ngày trong tháng |
| AC-0.2-04 | Duyệt chấm công (trưởng bộ phận) |
| AC-0.2-05 | CRUD leave requests + approve |
| AC-0.2-06 | Overtime validation: ≤ 40h/tháng, ≤ 12h/ngày |
| AC-0.2-07 | Full test suite ≥ 15 tests, 0 failures |

---

## 5. Phase 0.3 — Payroll Calculation Engine & Payslip

**Mục tiêu:** Kết nối Employee + Attendance → tính lương → tạo payslip. **Trái tim của module.**

### 5.1 Tasks

| ID | Task | Effort (giờ) | Phụ thuộc |
|---|---|---|---|
| 0.3.1 | **Migration: Mở rộng payroll_entries** (thêm fields: total_allowance, total_overtime, total_bonus, total_deduction, total_insurance_er, kpcd) | 2 | — |
| 0.3.2 | **Migration: Mở rộng payroll_details** (thêm fields: allowance_amount, overtime_amount, overtime_hours breakdown, kpcd) | 2 | — |
| 0.3.3 | Migration: salary_advances table | 1 | — |
| 0.3.4 | Tạo SalaryAdvance model | 1 | 0.3.3 |
| 0.3.5 | Tạo SalaryAdvanceRepositoryInterface | 1 | — |
| 0.3.6 | Tạo PDOSalaryAdvanceRepository | 1 | 0.3.5 |
| 0.3.7 | **Mở rộng PayrollService: processPayroll với attendance** | 8 | Phase 0.2 |
| 0.3.8 | **Mở rộng PayrollService: thêm allowance + overtime vào calculateEmployeePay** | 6 | 0.3.7 |
| 0.3.9 | **Mở rộng PayrollService: trừ tạm ứng khi tính Net** | 3 | 0.3.6 |
| 0.3.10 | **Tạo PayrollController** (run payroll, view payslip, list entries) | 6 | 0.3.7-9 |
| 0.3.11 | Thêm routes | 1 | 0.3.10 |
| 0.3.12 | Cập nhật DI | 1 | 0.3.6 |
| 0.3.13 | Views: payroll_run, payslip_list, payslip_detail, payslip_pdf | 10 | 0.3.10 |
| 0.3.14 | Cập nhật sidebar | 1 | 0.3.13 |
| 0.3.15 | **Tests: Mở rộng PayrollServiceTest** (allowance, overtime, advance deduction) | 8 | 0.3.7-9 |
| 0.3.16 | Permissions + Audit | 1 | — |
| **Tổng** | | **~52 giờ (~7 dev-days)** | |

### 5.2 Key Implementation Details

**PayrollService mở rộng — processPayroll mới:**

```php
public function processPayroll(
    string $periodId,
    string $processedBy,
    ?string $attendanceSummaryId = null  // NEW: optional, use attendance if provided
): PayrollEntry;
```

**Logic calculateEmployeePay mở rộng:**

```
Gross = Basic (prorated by attendance) + Allowances (from employee_allowances)
        + Overtime (from attendance_summary × rates)
        + Bonus (one-time, manual)

Insurance = f(insurance_salary, region, config)

Taxable = Gross - TaxExemptAllowances - Insurance - PersonalDeduction - DependentDeduction
PIT = f(Taxable, brackets)

Deductions = AdvanceRepayment + OtherDeductions
Net = Gross - Insurance - PIT - Deductions
```

**Payslip generation:**

```php
public function generatePayslip(string $entryId, string $employeeId): array;
// Trả về: { employee, gross, allowances_detail, overtime_detail,
//           insurance_detail, tax_detail, deductions, net, breakdown_by_account }
```

### 5.3 Acceptance Criteria

| AC | Mô tả |
|---|---|
| AC-0.3-01 | processPayroll với attendance: tính đúng Gross từ công thực tế |
| AC-0.3-02 | Tự động tính allowance từ employee_allowances |
| AC-0.3-03 | Overtime tính đúng 6 loại với hệ số tương ứng |
| AC-0.3-04 | Tự động trừ tạm ứng vào Net |
| AC-0.3-05 | Insurance đúng trần/sàn/tỷ lệ từ config |
| AC-0.3-06 | PIT đúng biểu 5 bậc, đúng giảm trừ |
| AC-0.3-07 | Payslip PDF có đầy đủ các khoản |
| AC-0.3-08 | Kết quả khớp với 5+ kịch bản Excel đã verify |
| AC-0.3-09 | Full test suite ≥ 25 tests mới, 0 failures |
| AC-0.3-10 | Tổng Dr = Cr cho mọi bảng lương |

---

## 6. Phase 0.4 — Approval Workflow & Period Close

**Mục tiêu:** Workflow duyệt 3 cấp (HR → Kế toán trưởng → Giám đốc), period state machine, audit trail

### 6.1 Tasks

| ID | Task | Effort (giờ) | Phụ thuộc |
|---|---|---|---|
| 0.4.1 | Migration: Thêm status history tracking cho payroll_entries | 1 | — |
| 0.4.2 | **Mở rộng PayrollService: approval workflow** | 6 | Phase 0.3 |
| 0.4.3 | **Mở rộng PayrollService: period close/reopen** | 4 | 0.4.2 |
| 0.4.4 | **Tạo ApprovalController** (approve, reject, reopen) | 4 | 0.4.2-3 |
| 0.4.5 | Thêm routes | 1 | 0.4.4 |
| 0.4.6 | Cập nhật DI | 1 | — |
| 0.4.7 | Views: approval_list, approval_detail, period_management | 6 | 0.4.4 |
| 0.4.8 | Cập nhật sidebar | 1 | 0.4.7 |
| 0.4.9 | Tests: approval workflow, period close, reopen | 6 | 0.4.2-3 |
| 0.4.10 | Permissions (phân quyền duyệt) | 2 | — |
| 0.4.11 | Audit trail cho mọi approve/reject/close/reopen | 1 | — |
| **Tổng** | | **~33 giờ (~4 dev-days)** | |

### 6.2 Key Implementation Details

**Period state machine — mở rộng từ existing:**

```
OPEN (draft payroll)
  → processPayroll() → DRAFT
    → approvePayroll(keToanTruong) → APPROVED
      → closePayroll() → CLOSED
        → reopenPayroll(cfo) → OPEN (audit trail forced)
```

**Approval routing — reuse existing ApprovalRoutingService:**

```php
// Check existing ApprovalRoutingService pattern
// Mỗi bước approval kiểm tra:
// 1. Người duyệt có đúng role không
// 2. Entry có ở đúng status không
// 3. Ghi audit log
```

**Period close validation:**

```php
public function closePayroll(string $periodId, string $closedBy): PayrollPeriod {
    // Validate:
    // 1. Tất cả employees đã có payslip
    // 2. Tất cả attendance đã duyệt
    // 3. All entries are approved (not draft)
    // 4. Dr = Cr
    // 5. No unprocessed adjustments
}
```

### 6.3 Acceptance Criteria

| AC | Mô tả |
|---|---|
| AC-0.4-01 | Workflow 3 cấp: HR → KT trưởng → Giám đốc |
| AC-0.4-02 | Từ chối + lý do → gửi lại draft |
| AC-0.4-03 | Period close: block nếu còn NV chưa tính lương |
| AC-0.4-04 | Period close: block nếu còn attendance chưa duyệt |
| AC-0.4-05 | Period close: block nếu Dr ≠ Cr |
| AC-0.4-06 | Reopen period: chỉ CFO/Giám đốc, audit trail forced |
| AC-0.4-07 | Full test suite ≥ 15 tests mới, 0 failures |

---

## 7. Phase 0.5 — Payment & Posting

**Mục tiêu:** Chi lương (chuyển khoản + tiền mặt), sinh file ngân hàng, hạch toán tự động

### 7.1 Tasks

| ID | Task | Effort (giờ) | Phụ thuộc |
|---|---|---|---|
| 0.5.1 | **Mở rộng PayrollService: sinh bút toán qua JournalService** | 8 | Phase 0.4 |
| 0.5.2 | **Tạo PaymentService** (generate bank file, record cash payment, reconciliation) | 6 | 0.5.1 |
| 0.5.3 | Tạo PaymentController | 4 | 0.5.2 |
| 0.5.4 | Thêm routes (payment) | 1 | 0.5.3 |
| 0.5.5 | Cập nhật DI | 1 | 0.5.2 |
| 0.5.6 | Views: payment_list, payment_create, payment_bank_file, posting_preview | 8 | 0.5.3 |
| 0.5.7 | Cập nhật sidebar | 1 | 0.5.6 |
| 0.5.8 | **Tests: PaymentService, posting** | 8 | 0.5.1-2 |
| 0.5.9 | Permissions (duyệt chi) | 1 | — |
| 0.5.10 | Audit + duplicate check | 1 | — |
| **Tổng** | | **~39 giờ (~5 dev-days)** | |

### 7.2 Key Implementation Details

**Bút toán lương tự động — qua JournalService:**

```php
public function postPayroll(string $entryId, string $postedBy): array {
    // 1. Get all payroll details
    // 2. Group by department → determine account (622/627/641/642)
    // 3. Create journal entries via JournalService:

    // Journal 1: Ghi nhận lương
    //   Dr 622/627/641/642 (gross by department)
    //   Cr 334

    // Journal 2: Trích BHXH DN vào chi phí
    //   Dr 622/627/641/642 (21.5% + 2%)
    //   Cr 3383/3384/3386/3382

    // Journal 3: Khấu trừ BHXH NLĐ
    //   Dr 334
    //   Cr 3383/3384/3386

    // Journal 4: Khấu trừ thuế TNCN
    //   Dr 334
    //   Cr 3335

    // 4. Validate Dr = Cr
    // 5. Update entry status
    // 6. Audit log
}
```

**Bank file format (mẫu Vietcombank):**

```
Header: VCB|PAYROLL|20260625|202606|3
Detail: emp_1|Nguyen Van A|123456789|8425000|Luong thang 06/2026
Detail: emp_2|Tran Thi B|987654321|22100000|Luong thang 06/2026
Footer: 2|30525000
```

### 7.3 Acceptance Criteria

| AC | Mô tả |
|---|---|
| AC-0.5-01 | postPayroll tạo đúng 4 bút toán qua JournalService |
| AC-0.5-02 | Phân bổ chi phí đúng TK theo phòng ban |
| AC-0.5-03 | Dr = Cr verified |
| AC-0.5-04 | Sinh file chuyển khoản đúng format |
| AC-0.5-05 | Ghi nhận chi tiền mặt + phiếu chi |
| AC-0.5-06 | Duplicate payment detection (trùng TK + số tiền) |
| AC-0.5-07 | Audit trail: mọi giao dịch chi |
| AC-0.5-08 | Full test suite ≥ 15 tests mới, 0 failures |
| AC-0.5-09 | Trial balance: Dr = Cr sau posting |

---

## 8. Phase 1.1 — Reports & Tax Declarations

**Mục tiêu:** Báo cáo lương nội bộ + kê khai BHXH/TNCN

### 8.1 Tasks

| ID | Task | Effort (giờ) | Phụ thuộc |
|---|---|---|---|
| 1.1.1 | **Tạo ReportService: bảng lương chi tiết + tổng hợp** | 6 | Phase 0.5 |
| 1.1.2 | **Tạo BhxhDeclarationService: sinh D02-LT** | 4 | 1.1.1 |
| 1.1.3 | **Tạo PitDeclarationService: sinh 05/KK-TNCN** | 4 | 1.1.1 |
| 1.1.4 | **Tạo ReportController** | 4 | 1.1.1-3 |
| 1.1.5 | Thêm routes | 1 | 1.1.4 |
| 1.1.6 | Cập nhật DI | 1 | 1.1.1-3 |
| 1.1.7 | Views: report_salary_detail, report_salary_summary, report_bhxh, report_tncn, report_cost_allocation, report_year_end | 10 | 1.1.4 |
| 1.1.8 | Cập nhật sidebar | 1 | — |
| 1.1.9 | Export Excel cho mọi report | 4 | 1.1.7 |
| 1.1.10 | Tests: reports, BHXH declaration, PIT declaration | 6 | 1.1.1-3 |
| 1.1.11 | Permissions + Audit | 1 | — |
| **Tổng** | | **~42 giờ (~5 dev-days)** | |

### 8.2 Key Implementation Details

**Báo cáo mẫu — bảng lương chi tiết:**

| STT | Họ tên | Phòng ban | Lương Gross | Phụ cấp | Tăng ca | BHXH | TNCN | Tạm ứng | Thực nhận |
|---|---|---|---|---|---|---|---|---|---|
| 1 | Nguyễn Văn A | Kế toán | 20,000,000 | 2,000,000 | 1,500,000 | 2,100,000 | 257,500 | 0 | 21,142,500 |
| **Tổng** | | | **50,000,000** | **5,000,000** | **3,000,000** | **5,250,000** | **1,500,000** | **2,000,000** | **49,250,000** |

**D02-LT format (BHXH):**

| STT | Họ tên | Số BHXH | Lương đóng BHXH | BHXH (8%) | BHYT (1.5%) | BHTN (1%) | Tổng NLĐ |
|---|---|---|---|---|---|---|---|

**05/KK-TNCN format:**

Chỉ tiêu [21]: Tổng TNCT
Chỉ tiêu [22]: Tổng giảm trừ
Chỉ tiêu [23]: TNTT
Chỉ tiêu [24]: Thuế TNCN

### 8.3 Acceptance Criteria

| AC | Mô tả |
|---|---|
| AC-1.1-01 | Bảng lương chi tiết: all fields, tổng hợp cuối |
| AC-1.1-02 | Bảng lương tổng hợp: theo phòng ban |
| AC-1.1-03 | Phân bổ chi phí: 622/627/641/642 |
| AC-1.1-04 | Báo cáo BHXH: D02-LT format |
| AC-1.1-05 | Báo cáo TNCN: 05/KK-TNCN format |
| AC-1.1-06 | Export Excel: mọi báo cáo |
| AC-1.1-07 | Full test suite ≥ 10 tests mới, 0 failures |

---

## 9. Phase 1.2 — Final Settlement & Adjustments

**Mục tiêu:** Quyết toán nghỉ việc, điều chỉnh lương hồi tố, tạm ứng lương

### 9.1 Tasks

| ID | Task | Effort (giờ) | Phụ thuộc |
|---|---|---|---|
| 1.2.1 | **Tạo FinalSettlementService** | 8 | Phase 0.5 |
| 1.2.2 | **Tạo SalaryAdjustmentService** | 6 | Phase 0.5 |
| 1.2.3 | Tạo FinalSettlementController | 4 | 1.2.1 |
| 1.2.4 | Tạo SalaryAdjustmentController | 4 | 1.2.2 |
| 1.2.5 | Thêm routes | 1 | 1.2.3-4 |
| 1.2.6 | Cập nhật DI | 1 | 1.2.1-2 |
| 1.2.7 | Views: final_settlement, adjustment_list, adjustment_create | 6 | 1.2.3-4 |
| 1.2.8 | Cập nhật sidebar | 1 | — |
| 1.2.9 | Tests: final settlement, adjustment | 8 | 1.2.1-2 |
| **Tổng** | | **~39 giờ (~5 dev-days)** | |

### 9.2 Acceptance Criteria

| AC | Mô tả |
|---|---|
| AC-1.2-01 | Final settlement: prorated salary + annual leave payout + severance |
| AC-1.2-02 | Severance calculation: trừ BHTN, trừ đã nhận |
| AC-1.2-03 | Adjustment: tạo adjustment entry, audit trail forced |
| AC-1.2-04 | Adjustment không sửa dữ liệu gốc, chỉ tạo bút toán bổ sung |
| AC-1.2-05 | Full test suite ≥ 10 tests mới, 0 failures |

---

## 10. Phase 2 — Advanced Features

### 10.1 Tasks

| ID | Task | Effort (giờ) | Phụ thuộc |
|---|---|---|---|
| 2.1 | Migration: thêm salary_config cho payroll_config (nếu thiếu) | 1 | Phase 0.1 |
| 2.2 | **Tạo PayrollImportService** (import employees từ Excel) | 6 | Phase 0.1 |
| 2.3 | **Tạo LeaveBalanceService** (quản lý tồn phép) | 4 | Phase 0.2 |
| 2.4 | **Payslip tự động gửi email** | 4 | Phase 0.3 |
| 2.5 | **Tích hợp NH: import sao kê đối chiếu** | 6 | Phase 0.5 |
| 2.6 | Mobile-responsive UI cho employee self-service | 6 | Phase 0.3 |
| 2.7 | Dashboard payroll overview (widgets) | 4 | Phase 1.1 |
| 2.8 | Tests: import, leave balance, email, NH tích hợp | 6 | 2.2-6 |
| **Tổng** | | **~37 giờ (~5 dev-days)** | |

### 10.2 Acceptance Criteria

| AC | Mô tả |
|---|---|
| AC-2-01 | Import employees từ Excel (20+ fields) |
| AC-2-02 | Leave balance: tự động cộng dồn, trừ khi nghỉ |
| AC-2-03 | Email payslip: gửi PDF tự động |
| AC-2-04 | Bank reconciliation: import sao kê, đối chiếu tự động |
| AC-2-05 | Employee self-service: xem chấm công, xin nghỉ, xem payslip |
| AC-2-06 | Dashboard: tổng chi phí lương, biến động, deadline |

---

## 11. Resource & Timeline Summary

### 11.1 Overall Timeline

```
Phase 0.1: 8 dev-days  ────── Week 1-2
Phase 0.2: 5 dev-days  ────── Week 2-3
Phase 0.3: 7 dev-days  ────── Week 3-5 (MMF-1 done ✓)
Phase 0.4: 4 dev-days  ────── Week 5-6
Phase 0.5: 5 dev-days  ────── Week 6-7 (MMF-2 done ✓)
───────────────────────────────────────── Release 1 (end of Week 7)
Phase 1.1: 5 dev-days  ────── Week 8-9 (MMF-3 done ✓)
Phase 1.2: 5 dev-days  ────── Week 9-10
───────────────────────────────────────── Release 2 (end of Week 10)
Phase 2:   5 dev-days  ────── Week 11-12
───────────────────────────────────────── Release 3 (end of Week 12)
```

**Tổng thời gian: ~12 tuần (3 tháng) với 1 developer full-time.**

### 11.2 Effort Breakdown by Layer

| Layer | Effort (giờ) | % |
|---|---|---|
| DB Migrations | 16 | 5% |
| Models | 18 | 6% |
| Repository Interfaces | 10 | 3% |
| PDO Repositories | 16 | 5% |
| Services (new + mở rộng) | 66 | 22% |
| Controllers | 42 | 14% |
| Routes | 12 | 4% |
| DI Container | 10 | 3% |
| Views / UI | 75 | 25% |
| Tests | 60 | 20% |
| Sidebar + Permissions + Audit | 15 | 5% |
| **Tổng** | **~340 giờ** | **~88 dev-days** |

### 11.3 Resource Requirements

| Resource | Số lượng | Ghi chú |
|---|---|---|
| PHP Developer | 1 | Full-time (có thể 2 dev để tăng tốc) |
| BA / Tester | 0.5 | Part-time review + test |
| Kế toán trưởng (domain) | 0.25 | Review acceptance criteria |
| Total | ~1.75 FTE | |

**Khuyến nghị:** Nếu có 2 developer (backend + frontend), song song hóa Phase 0.1 + 0.2 → giảm timeline từ 12 tuần xuống ~8 tuần.

### 11.4 Key Milestones

| Milestone | Date | Deliverable | Gate |
|---|---|---|---|
| **M1** | Week 2 | Employee Master + Config | All CRUD working, 15+ tests |
| **M2** | Week 3 | Attendance + Timekeeping | Import + validate + approve |
| **M3** | Week 5 | **Tính lương cơ bản** | Gross→Net, 10+ kịch bản Excel-verified |
| **M4** | Week 6 | Approval Workflow | 3 cấp, period close |
| **M5** | Week 7 | **Chi lương + Hạch toán** | Bank file, posting, Dr=Cr |
| **Release 1** | **Week 7** | **MMF-1 + MMF-2: Sẵn sàng user test** | **Core payroll cycle** |
| **M6** | Week 9 | Reports + Tax declarations | BHXH, TNCN, Export |
| **Release 2** | **Week 10** | **MMF-3: Sẵn sàng production** | **Full payroll + tax** |
| **M7** | Week 11 | Final Settlement + Adjustments | Nghỉ việc, điều chỉnh |
| **M8** | Week 12 | Advanced features | Email, NH, Employee portal |
| **Release 3** | **Week 12** | **Full module** | **All features** |

---

## 12. Risk Register & Mitigation

### 12.1 Risk Assessment per Phase

| Phase | Risk | Probability | Impact | Mitigation |
|---|---|---|---|---|
| **0.1** | Employee model mở rộng phá vỡ existing tests | Low | High | Giữ nguyên interface cũ, thêm field mới không ảnh hưởng |
| **0.2** | Attendance import format không đáp ứng được all use cases | Medium | Medium | Làm việc với HR để xác định format trước, flexible parser |
| **0.3** | Kết quả tính lương không khớp với Excel của kế toán | Medium | **Critical** | Tạo 10+ kịch bản test từ Excel thực tế, verify từng số |
| **0.3** | PIT biểu thuế thay đổi giữa chừng | Low | **Critical** | Config-driven, test mỗi khi config thay đổi |
| **0.4** | Workflow không linh hoạt cho mọi doanh nghiệp | Medium | Medium | Approval routing configurable (1-3 cấp) |
| **0.5** | Bank file format khác nhau giữa các NH | High | Medium | Template-based, hỗ trợ VCB + Techcombank trước, mở rộng sau |
| **1.1** | Mẫu biểu BHXH thay đổi | Medium | Medium | Tách biệt data layer và format layer |
| **1.2** | Cách tính trợ cấp thôi việc sai luật | Low | High | Review với Kế toán trưởng, test 3+ kịch bản |
| **All** | Thiếu tài liệu cho người dùng cuối | Medium | Medium | Viết user guide ngay từ Phase 0.3 |

### 12.2 Top 3 Critical Risks

```
RISK #1: Kết quả tính lương sai (Phase 0.3)
  Hậu quả: Mất niềm tin, sai lương thật
  Mitigation:
    - Tạo Excel mẫu với 10+ kịch bản (thuế, BH, tăng ca, allowance)
    - So sánh kết quả engine vs Excel tự động trong test
    - Mời kế toán review kết quả trước khi release

RISK #2: Timeline kéo dài do scope creep
  Hậu quả: Không deliver đúng hạn
  Mitigation:
    - MMF-1 (tính lương) là cứng, không thêm tính năng
    - Mỗi phase có AC rõ ràng, không mở rộng
    - Tính năng "nice to have" đẩy xuống Phase 2

RISK #3: Bank file format không chuẩn
  Hậu quả: Chuyển tiền thất bại
  Mitigation:
    - Test với thật file mẫu của NH trước
    - Cho phép custom format template
    - Có bước preview trước khi export
```

---

## 13. Rollout & Cutover Plan

### 13.1 User Acceptance Testing (UAT)

| Phase | UAT Participants | Duration | Criteria |
|---|---|---|---|
| **Release 1** (Week 7) | 1 Kế toán lương + 1 HR | 2 tuần | Tính lương cho 5 NV thật song song với Excel |
| **Release 2** (Week 10) | Full team kế toán | 1 tuần | Kê khai BHXH/TNCN từ hệ thống |
| **Release 3** (Week 12) | Toàn bộ user | 1 tuần | All features, performance OK |

### 13.2 Cutover Strategy

```
GIAI ĐOẠN 1 — Song song (Week 7-9):
  - Hệ thống mới + Excel song song
  - So sánh kết quả hàng tháng
  - Fix sai sót
  - Kế toán làm quen UI

GIAI ĐOẠN 2 — Chuyển đổi (Week 10):
  - Chọn 1 tháng "cutover month"
  - Tháng đó: tính lương trên hệ thống mới
  - Kế toán trưởng review + xác nhận
  - Nếu OK → chính thức dùng hệ thống mới

GIAI ĐOẠN 3 — Ổn định (Week 11-12):
  - Tắt Excel (archive lại)
  - Chỉ dùng hệ thống mới
  - Support nhanh cho user
```

### 13.3 Rollback Plan

```yaml
rollback_criteria:
  - "Kết quả lương sai > 1% so với Excel"
  - "Không chi lương đúng hạn (chậm > 1 ngày)"
  - "Lỗi critical (sai số, mất dữ liệu)"

rollback_steps:
  1. Chốt số liệu trên hệ thống mới (export)
  2. Quay lại Excel cho tháng đó
  3. Fix bug
  4. Chạy lại UAT
  5. Thử lại tháng sau

data_preservation:
  - Không xóa dữ liệu cũ khi rollback
  - Luôn có full backup trước cutover
  - Dữ liệu payroll có thể import lại từ Excel
```

---

## 14. Definition of Done per Phase

Mỗi phase phải đạt tất cả các tiêu chí sau trước khi coi là hoàn thành:

```
[ ] Code tuân thủ AGENTS.md §5 conventions (naming, formatting, types)
[ ] PHP syntax check: php -l trên mọi file thay đổi
[ ] Full test suite: 0 failures
[ ] Happy path + ít nhất 1 failure case cho mọi service method mới
[ ] AuditLogger::log() cho mọi thay đổi dữ liệu quan trọng
[ ] Không breaking change cho existing code
[ ] Views: tiếng Việt, Bootstrap 5, responsive
[ ] Dr = Cr verified cho mọi bút toán
[ ] Period locking: read-only sau khi đóng
[ ] Fraud detection: ít nhất 3 dấu hiệu cho phase đó
[ ] User guide: viết hướng dẫn cho user (tối thiểu 1 trang/phase)
[ ] Không debug code, không TODO, không comment-out code
```

---

> **Tài liệu này:** BA Lead phê duyệt. Là cơ sở để dev team implement module Tiền lương.  
> **Cập nhật:** Khi có thay đổi về luật hoặc yêu cầu nghiệp vụ.  
> **Liên hệ:** BA Lead — mọi deviation cần được phê duyệt trước khi code.
