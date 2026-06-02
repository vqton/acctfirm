# Gap 03: Budget & Planning Module — Parity Specification

> **Mức độ hiện tại:** 0/10 — **Mục tiêu:** 8/10
> **Mức độ ưu tiên:** P1 (important — không bắt buộc pháp lý nhưng quyết định cạnh tranh ERP)
> **Phạm vi:** Lập dự toán ngân sách, kiểm soát chi tiêu, so sánh thực tế vs kế hoạch
> **Ngày:** 02/06/2026
> **Tài liệu tham chiếu:** `10-gaps-use-cases-consolidated.md`, MISA AMIS Budget, BRAVO Budget Control, Thông tư 99/2025/TT-BTC, AGENTS.md §1-§10

---

## 1. Business Context & Rationale

### 1.1 Why This Matters

Phân hệ ngân sách (Budget & Planning) là công cụ quản trị doanh nghiệp thiết yếu nhưng Bookwise chưa có. Kế toán hiện phải dùng Excel để lập dự toán — dẫn đến: không kiểm soát được chi tiêu theo hạn mức, không so sánh actual vs budget theo thời gian thực, không có audit trail cho quá trình lập và điều chỉnh dự toán.

Mặc dù không có ràng buộc pháp lý (không có Thông tư nào yêu cầu hệ thống kế toán phải có module budget), đây là P1 gap vì:
- Khách hàng SME muốn quản trị chi phí theo hạn mức
- Phân hệ ngân sách có ở MISA AMIS và BRAVO — thiếu = mất điểm cạnh tranh
- Kiểm soát nội bộ yêu cầu: "không chi vượt dự toán trừ khi được duyệt"

### 1.2 Competitive Landscape

| Phần mềm | Budget Planning | Budget Control | Actual vs Budget | Revision Workflow | Multi-year |
|---|---|---|---|---|---|
| MISA AMIS | ✅ Department-wise | ⚠️ Cảnh báo | ✅ Biểu đồ | ✅ Có | ✅ Có |
| BRAVO ERP | ✅ Đầy đủ | ✅ Chặn vượt | ✅ So sánh | ✅ Có | ✅ Có |
| FAST | ✅ Đầy đủ | ✅ Chặn vượt | ✅ So sánh | ✅ Có | ❌ |
| **Bookwise** | **❌ KHÔNG CÓ** | **❌** | **❌ Excel** | **❌** | **❌** |

### 1.3 Design Principles

1. **ConfigService-driven thresholds** — tỷ lệ cảnh báo/chặn đọc từ bảng `business_config`, không hardcode
2. **Optional control** — budget control là tính năng bật/tắt được (config key `budget.enabled`)
3. **Non-blocking by default** — budget vượt = cảnh báo, không chặn (config để nâng cấp lên block)
4. **Audit trail** — mọi thay đổi budget phải log (tạo, sửa, duyệt, điều chỉnh)
5. **Versioned** — budget có version history, cho phép so sánh các phiên bản

---

## 2. Data Model

### 2.1 New Tables

```sql
-- Kế hoạch ngân sách: mỗi năm có thể có nhiều phiên bản (draft → approved → revised)
CREATE TABLE IF NOT EXISTS budget_plans (
    id VARCHAR(36) PRIMARY KEY,                             -- uniqid('bdg_')
    year SMALLINT UNSIGNED NOT NULL,                        -- Năm kế hoạch (2026)
    name VARCHAR(255) NOT NULL,                             -- "Kế hoạch ngân sách 2026"
    type ENUM('revenue','cost','profit','all') NOT NULL,    -- Loại kế hoạch
    status ENUM('draft','submitted','approved','rejected','locked') NOT NULL DEFAULT 'draft',
    department_id VARCHAR(36) DEFAULT NULL,                 -- NULL = toàn công ty
    notes TEXT DEFAULT NULL,                                -- Ghi chú
    created_by VARCHAR(100) NOT NULL,
    approved_by VARCHAR(100) DEFAULT NULL,
    approved_at DATETIME DEFAULT NULL,
    locked_by VARCHAR(100) DEFAULT NULL,
    locked_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_budget_year (year),
    INDEX idx_budget_status (status),
    INDEX idx_budget_dept (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Chi tiết ngân sách: các khoản mục và dự toán từng tháng
CREATE TABLE IF NOT EXISTS budget_lines (
    id VARCHAR(36) PRIMARY KEY,                             -- uniqid('bdl_')
    budget_plan_id VARCHAR(36) NOT NULL,                    -- FK → budget_plans
    account_code VARCHAR(20) NOT NULL,                      -- TK: 5111, 632, 641, 642...
    account_name VARCHAR(255) DEFAULT NULL,                 -- Lưu snapshot tên TK tại thời điểm lập
    department_id VARCHAR(36) DEFAULT NULL,                 -- Phòng ban (NULL = all)
    -- 12 tháng: month_01..month_12
    month_01 DECIMAL(15,2) NOT NULL DEFAULT 0,
    month_02 DECIMAL(15,2) NOT NULL DEFAULT 0,
    month_03 DECIMAL(15,2) NOT NULL DEFAULT 0,
    month_04 DECIMAL(15,2) NOT NULL DEFAULT 0,
    month_05 DECIMAL(15,2) NOT NULL DEFAULT 0,
    month_06 DECIMAL(15,2) NOT NULL DEFAULT 0,
    month_07 DECIMAL(15,2) NOT NULL DEFAULT 0,
    month_08 DECIMAL(15,2) NOT NULL DEFAULT 0,
    month_09 DECIMAL(15,2) NOT NULL DEFAULT 0,
    month_10 DECIMAL(15,2) NOT NULL DEFAULT 0,
    month_11 DECIMAL(15,2) NOT NULL DEFAULT 0,
    month_12 DECIMAL(15,2) NOT NULL DEFAULT 0,
    total DECIMAL(15,2) GENERATED ALWAYS AS (
        month_01  + month_02  + month_03  + month_04
      + month_05  + month_06  + month_07  + month_08
      + month_09  + month_10  + month_11  + month_12
    ) STORED,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (budget_plan_id) REFERENCES budget_plans(id) ON DELETE CASCADE,
    INDEX idx_bline_plan (budget_plan_id),
    INDEX idx_bline_account (account_code),
    INDEX idx_bline_dept (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lịch sử phiên bản budget: ghi lại mọi thay đổi
CREATE TABLE IF NOT EXISTS budget_versions (
    id VARCHAR(36) PRIMARY KEY,                             -- uniqid('bdv_')
    budget_plan_id VARCHAR(36) NOT NULL,
    version_number SMALLINT UNSIGNED NOT NULL,              -- 1, 2, 3...
    change_description VARCHAR(500) NOT NULL,               -- "Điều chỉnh lần 1: tăng CP QLDN 10%"
    changed_by VARCHAR(100) NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    snapshot_json LONGTEXT NOT NULL,                        -- Full snapshot của budget_lines tại thời điểm này
    FOREIGN KEY (budget_plan_id) REFERENCES budget_plans(id) ON DELETE CASCADE,
    INDEX idx_bver_plan (budget_plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kịch bản dự toán: cho phép lập nhiều kịch bản (lạc quan, bi quan, cơ sở)
CREATE TABLE IF NOT EXISTS budget_scenarios (
    id VARCHAR(36) PRIMARY KEY,                             -- uniqid('bds_')
    name VARCHAR(100) NOT NULL,                             -- "Cơ sở", "Lạc quan", "Bi quan"
    description VARCHAR(500) DEFAULT NULL,
    adjustment_pct DECIMAL(5,2) NOT NULL DEFAULT 0,         -- % điều chỉnh so với base (âm = giảm)
    is_base TINYINT(1) NOT NULL DEFAULT 0,                  -- 1 = kịch bản cơ sở (mặc định)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.2 View: budget_actuals

```sql
-- So sánh thực tế vs dự toán: view materialized qua stored procedure hoặc tính real-time
-- Có thể materialize vào bảng budget_actuals_snapshots vào cuối tháng
CREATE OR REPLACE VIEW budget_actuals_view AS
SELECT
    bl.id AS budget_line_id,
    bl.budget_plan_id,
    bp.year,
    bl.account_code,
    bl.department_id,
    MONTH(t.created_at) AS month_num,
    CASE MONTH(t.created_at)
        WHEN 1 THEN bl.month_01 WHEN 2 THEN bl.month_02 WHEN 3 THEN bl.month_03
        WHEN 4 THEN bl.month_04 WHEN 5 THEN bl.month_05 WHEN 6 THEN bl.month_06
        WHEN 7 THEN bl.month_07 WHEN 8 THEN bl.month_08 WHEN 9 THEN bl.month_09
        WHEN 10 THEN bl.month_10 WHEN 11 THEN bl.month_11 WHEN 12 THEN bl.month_12
    END AS budget_amount,
    COALESCE(actuals.actual_amount, 0) AS actual_amount,
    (COALESCE(actuals.actual_amount, 0) -
        CASE MONTH(t.created_at)
            WHEN 1 THEN bl.month_01 WHEN 2 THEN bl.month_02 WHEN 3 THEN bl.month_03
            WHEN 4 THEN bl.month_04 WHEN 5 THEN bl.month_05 WHEN 6 THEN bl.month_06
            WHEN 7 THEN bl.month_07 WHEN 8 THEN bl.month_08 WHEN 9 THEN bl.month_09
            WHEN 10 THEN bl.month_10 WHEN 11 THEN bl.month_11 WHEN 12 THEN bl.month_12
        END
    ) AS variance,
    CASE
        WHEN CASE MONTH(t.created_at)
            WHEN 1 THEN bl.month_01 WHEN 2 THEN bl.month_02 WHEN 3 THEN bl.month_03
            WHEN 4 THEN bl.month_04 WHEN 5 THEN bl.month_05 WHEN 6 THEN bl.month_06
            WHEN 7 THEN bl.month_07 WHEN 8 THEN bl.month_08 WHEN 9 THEN bl.month_09
            WHEN 10 THEN bl.month_10 WHEN 11 THEN bl.month_11 WHEN 12 THEN bl.month_12
        END = 0 THEN NULL
        ELSE ROUND(
            (COALESCE(actuals.actual_amount, 0) /
                CASE MONTH(t.created_at)
                    WHEN 1 THEN bl.month_01 WHEN 2 THEN bl.month_02 WHEN 3 THEN bl.month_03
                    WHEN 4 THEN bl.month_04 WHEN 5 THEN bl.month_05 WHEN 6 THEN bl.month_06
                    WHEN 7 THEN bl.month_07 WHEN 8 THEN bl.month_08 WHEN 9 THEN bl.month_09
                    WHEN 10 THEN bl.month_10 WHEN 11 THEN bl.month_11 WHEN 12 THEN bl.month_12
                END
            ) * 100, 2)
    END AS variance_pct
FROM budget_lines bl
JOIN budget_plans bp ON bp.id = bl.budget_plan_id AND bp.status = 'approved'
LEFT JOIN (
    SELECT
        a.code AS account_code,
        MONTH(t.created_at) AS month_num,
        SUM(le.amount) AS actual_amount
    FROM ledger_entries le
    JOIN accounts a ON a.id = le.account_id
    JOIN transactions t ON t.id = le.transaction_id
    WHERE t.status = 'posted'
        AND YEAR(t.created_at) = bp.year
    GROUP BY a.code, MONTH(t.created_at)
) actuals ON actuals.account_code = bl.account_code
    AND actuals.month_num = MONTH(t.created_at)
WHERE bp.status = 'approved';
```

### 2.3 New Business Config Keys

| Config Key | Type | Default | Purpose |
|---|---|---|---|
| `budget.enabled` | `int` | `0` | Bật/tắt budget control (0 = disabled, 1 = warn only, 2 = warn+block) |
| `budget.warn_pct` | `percent` | `90` | % budget → cảnh báo vàng |
| `budget.block_pct` | `percent` | `110` | % budget → chặn chi tiêu |
| `budget.max_increase_pct` | `percent` | `20` | % tăng tối đa so với năm trước |
| `budget.revision_threshold_pct` | `percent` | `10` | % thay đổi → cần duyệt lại |
| `budget.control_account_codes` | `json` | `["641","642","635","632"]` | TK chịu kiểm soát budget |

---

## 3. Process Flows

### 3.1 Budget Creation (Top-Down / Bottom-Up)

```
TOP-DOWN (từ Ban lãnh đạo):
  CFO/CEO → nhập tổng mục tiêu lợi nhuận/doanh thu năm
          → hệ thống phân bổ xuống phòng ban theo % lịch sử
          → từng phòng ban nhận hạn mức → điều chỉnh → gửi duyệt

BOTTOM-UP (từ phòng ban):
  Trưởng phòng → nhập dự toán chi tiết theo TK/tháng
              → hệ thống tổng hợp lên công ty
              → CFO/CEO duyệt hoặc điều chỉnh
```

**Xử lý trong service:**

```php
// BudgetService::createTopDown(year, targetTotal, allocationMethod)
//   1. Tạo budget_plans record
//   2. Đọc doanh thu/chi phí năm trước từ ledger_entries
//   3. Phân bổ targetTotal cho các phòng ban theo allocationMethod
//   4. Tạo budget_lines với dự toán 12 tháng
//   5. Gửi thông báo cho trưởng phòng

// BudgetService::createBottomUp(year)
//   1. Phòng ban nhập budget_lines cho TK của mình
//   2. Hệ thống tổng hợp theo cấp
//   3. Gửi duyệt lên CFO
```

### 3.2 Budget Approval Workflow

```
draft ──submit──▶ submitted ──approve──▶ approved ──lock──▶ locked
                   │                       │
                   ├──reject──▶ draft       └──revise──▶ draft (nếu thay đổi > threshold)
                   │
                   └──return──▶ draft (có lý do từ chối)
```

Quy tắc approval:
- **draft → submitted:** Người lập gửi duyệt. Sau submit, không sửa được (trừ khi bị từ chối)
- **submitted → approved:** Kế toán trưởng / CFO duyệt
- **submitted → rejected:** Có lý do từ chối → trả về draft để sửa
- **approved → locked:** Kế toán trưởng khóa — không cho sửa, không cho điều chỉnh
- **approved → draft (revise):** Nếu cần điều chỉnh > `budget.revision_threshold_pct` → tạo version mới
- **draft (revised) → approved:** Điều chỉnh nhỏ < threshold → không cần duyệt lại

### 3.3 Budget Control (Check Before Spending)

Budget control được gọi ở 2 điểm:
1. **Trước khi post bút toán** (trong JournalService — Phase 1 validate)
2. **Trước khi tạo phiếu chi/phiếu thu** (CashService, ApService)

```
Flow:
  1. Kế toán nhập bút toán với account_code = 641 (CP QLDN)
  2. JournalService::createDraft()
  3. BudgetService::checkBudget(account_code, amount, period)
     a. budget.enabled = 0 → skip (không kiểm soát)
     b. Tìm budget_line cho account_code + department + period
     c. Tính actual_ytd + committed_ytd + current_amount
     d. So sánh với budget_amount:
        - < budget.warn_pct → OK (xanh)
        - >= budget.warn_pct → WARN (vàng — cho phép nhưng cảnh báo)
        - >= budget.block_pct → BLOCK (đỏ — từ chối) (nếu budget.enabled = 2)
  4. Ghi log vào budget_control_log
```

**Budget check signature:**

```php
public function checkBudget(
    string $accountCode,
    float $amount,
    string $period,          // '2026-06'
    ?string $departmentId = null,
    ?string $module = null   // 'cash', 'ap', 'journal'
): BudgetCheckResult;
// Returns: { status: 'ok'|'warn'|'block', current_usage_pct, budget_amount, actual_amount, message }
```

### 3.4 Actual vs Budget Comparison

#### 3.4.1 Period Comparison (Monthly/Quarterly)

```php
// BudgetService::compareActualVsBudget(budgetPlanId, period)
// Returns:
//   [
//     'account_code' => '641',
//     'account_name' => 'Chi phí QLDN',
//     'budget_amount' => 100_000_000,
//     'actual_amount' => 85_000_000,
//     'variance' => -15_000_000,       // actual < budget = âm (tiết kiệm)
//     'variance_pct' => -15.00,
//     'status' => 'under_budget',
//   ]
```

#### 3.4.2 YTD Comparison

```php
// BudgetService::getYtdVariance(budgetPlanId, asOfMonth)
// Tổng hợp từ tháng 1 → asOfMonth
```

#### 3.4.3 Full Year Forecast

```php
// BudgetService::getForecast(budgetPlanId)
// = actual YTD + (average monthly actual × remaining months)
// So sánh forecast vs budget → cảnh báo vượt năm
```

### 3.5 Budget Revision / Adjustment

Khi cần điều chỉnh budget sau khi đã approved:

```
Flow:
  1. Người dùng chọn "Điều chỉnh" trên budget đã approved
  2. Hệ thống tạo bản sao budget_lines hiện tại (snapshot)
  3. Người dùng sửa số liệu các tháng còn lại
  4. Tính % thay đổi so với approved:
     - < revision_threshold_pct → tự động update, log version
     - >= revision_threshold_pct → yêu cầu duyệt lại
  5. Lưu budget_versions record với full snapshot
  6. Nếu cần duyệt lại → status về draft
```

### 3.6 Multi-year Budgeting

Cho phép lập budget nhiều năm (3-5 năm) cho:
- Hoạch định chiến lược
- Dự án đầu tư dài hạn
- Khấu hao TSCĐ

```php
// BudgetService::createMultiYear(startYear, endYear, baseAmount, growthRatePct)
//   1. Tạo budget_plans cho từng năm
//   2. Áp dụng growthRatePct cho năm sau = year_n-1 × (1 + growthRatePct/100)
//   3. Cho phép điều chỉnh tay từng năm
```

---

## 4. Allocation Methods

### 4.1 Fixed Amount Per Period (Equal Split)

```
Total: 1.200.000.000 VND
Method: equal
→ Mỗi tháng: 100.000.000 VND
```

### 4.2 Percentage of Revenue

```
Doanh thu dự toán năm: 10.000.000.000 VND
CP QLDN = 5% doanh thu → 500.000.000 VND
Phân bổ theo tháng: dùng tỷ lệ doanh thu từng tháng của năm trước
```

### 4.3 Prior Year + Growth %

```
CP bán hàng năm trước: 800.000.000 VND
Growth rate: 10%
→ Năm nay: 880.000.000 VND
Phân bổ theo tháng: dùng tỷ lệ chi tiêu từng tháng của năm trước
```

### 4.4 Seasonal Distribution

```php
// SeasonalDistribution: mảng 12 phần tử chỉ % phân bổ, tổng = 100
$seasonal = [5, 5, 7, 8, 10, 12, 12, 10, 9, 8, 7, 7];
// Nếu total = 1.200.000.000:
// Tháng 1 = 60.000.000, Tháng 2 = 60.000.000... Tháng 7 = 144.000.000
```

### 4.5 Scenario-Based Adjustment

```
Base scenario: 1.000.000.000 VND
Optimistic: +15% → 1.150.000.000 VND (adjustment_pct = +15.00)
Pessimistic: -20% → 800.000.000 VND (adjustment_pct = -20.00)

Lưu trong budget_scenarios table. Áp dụng: budget_lines × (1 + adjustment_pct/100)
```

---

## 5. Business Rules

### 5.1 Rule Definitions

| ID | Rule | Severity | Config Key |
|---|---|---|---|
| BR-B01 | Budget không thể tăng > `max_increase_pct` so với năm trước | block | `budget.max_increase_pct` |
| BR-B02 | Chi tiêu ≥ `warn_pct` budget → cảnh báo | warn | `budget.warn_pct` |
| BR-B03 | Chi tiêu ≥ `block_pct` budget → chặn (nếu enabled=2) | block | `budget.block_pct` |
| BR-B04 | Chỉ budget admin/thủ trưởng mới sửa budget đã approved | block | (RBAC) |
| BR-B05 | Revision > `revision_threshold_pct` → cần duyệt lại | block | `budget.revision_threshold_pct` |
| BR-B06 | Budget kỳ đã locked = read-only | block | (status machine) |
| BR-B07 | Tổng budget các phòng ban ≤ tổng budget công ty | warn | (structural) |
| BR-B08 | Budget năm N+1 chỉ tạo được khi budget năm N đã approved | warn | (workflow) |

### 5.2 Rule Enforcement

```php
// BudgetRuleEngine: tập trung tất cả business rules
class BudgetRuleEngine
{
    private ConfigService $config;

    // Kiểm tra BR-B01
    public function validateIncrease(string $accountCode, int $year, float $proposedTotal): void
    {
        $maxPct = $this->config->getPercent('budget.max_increase_pct', 0.20);
        $priorYearTotal = $this->getPriorYearActual($accountCode, $year - 1);
        if ($priorYearTotal > 0) {
            $increase = ($proposedTotal - $priorYearTotal) / $priorYearTotal;
            if ($increase > $maxPct) {
                throw new \InvalidArgumentException(
                    sprintf('Mức tăng %.1f%% vượt quá hạn mức cho phép (%.1f%%). Vui lòng điều chỉnh.', $increase * 100, $maxPct * 100)
                );
            }
        }
    }

    // Kiểm tra BR-B05
    public function requiresReApproval(float $oldTotal, float $newTotal): bool
    {
        $threshold = $this->config->getPercent('budget.revision_threshold_pct', 0.10);
        $change = abs($newTotal - $oldTotal) / ($oldTotal ?: 1);
        return $change >= $threshold;
    }
}
```

---

## 6. Integration Contracts

### 6.1 Integration Points

| Module | Direction | Contract |
|---|---|---|
| **JournalService** | → BudgetService::checkBudget() | Gọi trước Phase 1 validate khi `sourceModule` có budget control |
| **CashService** | → BudgetService::checkBudget() | Kiểm tra hạn mức chi khi account_code thuộc danh sách control |
| **ApService** | → BudgetService::checkBudget() | Kiểm tra hạn mức mua hàng khi ghi nhận invoice |
| **AccountRepository** | BudgetService → | Đọc số dư tài khoản (tree balance) |
| **GlService** | BudgetService → | Đọc actual phát sinh từ ledger_entries |
| **FsService** | BudgetService → | BC09 section: thuyết minh chênh lệch budget vs actual |
| **ConfigService** | BudgetService → | Đọc ngưỡng: warn_pct, block_pct, max_increase_pct |
| **PeriodService** | BudgetService → | Xác định kỳ hiện tại, kỳ đã đóng |
| **AuditLoggerInterface** | BudgetService → | Ghi audit cho mọi thay đổi budget |

### 6.2 BudgetService Interface

```php
interface BudgetServiceInterface
{
    // ── CRUD ──
    public function createPlan(int $year, string $name, string $type, ?string $departmentId = null, string $createdBy): array;
    public function getPlan(string $id): array;
    public function listPlans(int $year, ?string $status = null, ?string $departmentId = null): array;
    public function updatePlan(string $id, array $data, string $updatedBy): array;
    public function deletePlan(string $id, string $deletedBy): void;

    // ── BUDGET LINES ──
    public function setLines(string $planId, array $lines, string $updatedBy): array;
    // lines: [{ account_code, department_id, month_01..month_12 }]
    public function getLines(string $planId, ?string $departmentId = null): array;

    // ── ALLOCATION METHODS ──
    public function allocateEqual(string $planId, float $total, string $accountCode): array;
    public function allocateByPercentOfRevenue(string $planId, string $accountCode, float $percent, int $priorYear): array;
    public function allocateByPriorYearGrowth(string $planId, string $accountCode, float $growthPct, int $priorYear): array;
    public function allocateSeasonal(string $planId, string $accountCode, float $total, array $seasonalPct): array;

    // ── APPROVAL WORKFLOW ──
    public function submit(string $planId, string $submittedBy): array;
    public function approve(string $planId, string $approvedBy, ?string $comment = null): array;
    public function reject(string $planId, string $rejectedBy, string $reason): array;
    public function lock(string $planId, string $lockedBy): array;

    // ── BUDGET CONTROL ──
    public function checkBudget(string $accountCode, float $amount, string $period, ?string $departmentId = null, ?string $module = null): BudgetCheckResult;
    public function getBudgetUsage(string $accountCode, int $year, int $month, ?string $departmentId = null): array;

    // ── ACTUAL vs BUDGET ──
    public function compareActualVsBudget(string $planId, ?int $month = null, ?string $departmentId = null): array;
    public function getYtdVariance(string $planId, int $asOfMonth): array;
    public function getForecast(string $planId): array;

    // ── REVISION ──
    public function revise(string $planId, string $changedBy, string $reason): array;
    // Returns new version number + snapshot

    // ── SCENARIOS ──
    public function createScenario(string $name, float $adjustmentPct, bool $isBase = false): array;
    public function applyScenario(string $planId, string $scenarioId): array;

    // ── MULTI-YEAR ──
    public function createMultiYear(int $startYear, int $endYear, float $baseAmount, float $growthRatePct): array;
    public function rollForward(int $fromYear, int $toYear): array;

    // ── EXPORT ──
    public function exportToCsv(string $planId): string;
    public function exportComparison(string $planId): string;
}
```

---

## 7. API Endpoints

### 7.1 Budget Plan Endpoints

| Method | Path | Action | Auth |
|---|---|---|---|
| `GET` | `/api/budget/plans?year=2026` | List plans | `budget.read` |
| `POST` | `/api/budget/plans` | Create plan | `budget.create` |
| `GET` | `/api/budget/plans/{id}` | Get plan detail | `budget.read` |
| `PUT` | `/api/budget/plans/{id}` | Update plan | `budget.update` |
| `DELETE` | `/api/budget/plans/{id}` | Delete plan (draft only) | `budget.delete` |

### 7.2 Budget Lines

| Method | Path | Action | Auth |
|---|---|---|---|
| `GET` | `/api/budget/plans/{id}/lines` | Get budget lines | `budget.read` |
| `PUT` | `/api/budget/plans/{id}/lines` | Set budget lines | `budget.update` |

### 7.3 Allocation

| Method | Path | Action | Auth |
|---|---|---|---|
| `POST` | `/api/budget/plans/{id}/allocate-equal` | Equal split | `budget.update` |
| `POST` | `/api/budget/plans/{id}/allocate-revenue-pct` | % of revenue | `budget.update` |
| `POST` | `/api/budget/plans/{id}/allocate-growth` | Prior year + growth | `budget.update` |
| `POST` | `/api/budget/plans/{id}/allocate-seasonal` | Seasonal distribution | `budget.update` |

### 7.4 Approval Workflow

| Method | Path | Action | Auth |
|---|---|---|---|
| `POST` | `/api/budget/plans/{id}/submit` | Submit for approval | `budget.submit` |
| `POST` | `/api/budget/plans/{id}/approve` | Approve | `budget.approve` |
| `POST` | `/api/budget/plans/{id}/reject` | Reject | `budget.approve` |
| `POST` | `/api/budget/plans/{id}/lock` | Lock (read-only) | `budget.lock` |
| `POST` | `/api/budget/plans/{id}/revise` | Revise (creates version) | `budget.update` |

### 7.5 Budget Control (called internally)

| Method | Path | Action | Auth |
|---|---|---|---|
| `GET` | `/api/budget/check?account_code=641&amount=10000000&period=2026-06` | Check budget | `budget.check` |
| `GET` | `/api/budget/usage/641?year=2026` | Get budget usage | `budget.read` |

### 7.6 Reports

| Method | Path | Action | Auth |
|---|---|---|---|
| `GET` | `/api/budget/reports/actual-vs-budget/{planId}?month=6` | Compare | `budget.report` |
| `GET` | `/api/budget/reports/ytd-variance/{planId}?as-of=6` | YTD variance | `budget.report` |
| `GET` | `/api/budget/reports/forecast/{planId}` | Full-year forecast | `budget.report` |
| `GET` | `/api/budget/reports/export-csv/{planId}` | CSV export | `budget.export` |

---

## 8. UI/UX

### 8.1 Screens

| Screen | Route | View File |
|---|---|---|
| Budget Dashboard | `/budget` | `public/views/budget/dashboard.php` |
| Budget Plan List | `/budget/plans` | `public/views/budget/plans.php` |
| Budget Plan Detail | `/budget/plans/{id}` | `public/views/budget/plan-detail.php` |
| Budget Line Editor | `/budget/plans/{id}/lines` | `public/views/budget/line-editor.php` |
| Budget Comparison | `/budget/reports/actual-vs-budget/{id}` | `public/views/budget/comparison.php` |
| Budget Usage | `/budget/usage/{account}` | `public/views/budget/usage.php` |

### 8.2 Dashboard Components

```
┌─────────────────────────────────────────────────────┐
│ 📊 TỔNG QUAN NGÂN SÁCH 2026                         │
├─────────────────┬───────────────────┬────────────────┤
│ Tổng doanh thu  │ Tổng chi phí      │ Lợi nhuận DT   │
│ Budget: 10B     │ Budget: 8B        │ Budget: 2B     │
│ Actual: 5.2B    │ Actual: 3.8B      │ Actual: 1.4B   │
│ (52% YTD)       │ (47.5% YTD)       │ (70% YTD)      │
├─────────────────┴───────────────────┴────────────────┤
│ Biểu đồ: Cột (Budget vs Actual theo tháng)           │
│ ┌───┬───┬───┬───┬───┬───┬───┬───┬───┬───┬───┬───┐  │
│ │ B │ A │ B │ A │ B │ A │ B │ A │ ...              │  │
│ └───┴───┴───┴───┴───┴───┴───┴───┴───┴───┴───┴───┘  │
├─────────────────────────────────────────────────────┤
│ ⚠️ Cảnh báo: CP QLDN đã dùng 85% budget (tháng 6)  │
│ ❌ CP Bán hàng vượt 115% budget (đã chặn)           │
└─────────────────────────────────────────────────────┘
```

### 8.3 Line Editor (Grid)

Grid nhập dự toán: rows = account_code, columns = tháng 1-12, cell nhập số.

```
┌──────────┬─────────┬──────┬──────┬──────┬─────┬──────────┐
│ TK       │ Tên TK  │ T1   │ T2   │ T3   │ ... │ Tổng     │
├──────────┼─────────┼──────┼──────┼──────┼─────┼──────────┤
│ 5111     │ DT BH   │ 800M │ 750M │ 900M │     │ 10.0B    │
│ 5112     │ DV      │ 100M │ 100M │ 100M │     │ 1.2B     │
│ 632      │ GVHB    │ 500M │ 470M │ 560M │     │ 6.5B     │
│ 641      │ CP BH   │ 50M  │ 50M  │ 55M  │     │ 650M     │
│ 642      │ CP QLDN │ 80M  │ 80M  │ 85M  │     │ 1.0B     │
├──────────┼─────────┼──────┼──────┼──────┼─────┼──────────┤
│ LN       │         │ 270M │ 250M │ 300M │     │ 3.05B    │
└──────────┴─────────┴──────┴──────┴──────┴─────┴──────────┘
```

Tính năng grid:
- Tab navigation giữa các cell
- Tự động tính tổng dòng và tổng cột
- Hiển thị % so với năm trước (cột phụ)
- Highlight khi > cảnh báo
- Nút "Áp dụng mẫu" (allocation methods)

---

## 9. Implementation Checklist

### Phase 1: Core Data & CRUD (2 days)

```
[ ] Migration: budget_plans, budget_lines, budget_versions, budget_scenarios
[ ] Model: BudgetPlan, BudgetLine, BudgetVersion, BudgetScenario
[ ] Repository Interface: BudgetPlanRepositoryInterface, BudgetLineRepositoryInterface
[ ] PDO Repository: PDOBudgetPlanRepository, PDOBudgetLineRepository
[ ] Service: BudgetService — CRUD methods
[ ] Controller: BudgetController — plans CRUD
[ ] Routes: /api/budget/plans
[ ] DI registration
[ ] Tests: CRUD + data integrity
```

### Phase 2: Budget Lines & Allocation Methods (1.5 days)

```
[ ] BudgetLineEditorService — batch update budget_lines
[ ] AllocationService — 4 allocation methods
[ ] BudgetRuleEngine — increase validation, re-approval check
[ ] Tests: each allocation method, validation rules
```

### Phase 3: Approval Workflow (1 day)

```
[ ] Status machine: draft → submitted → approved → locked
[ ] BudgetService::submit/approve/reject/lock
[ ] BudgetVersion snapshot on approve
[ ] RBAC: budget.create, budget.approve, budget.lock, budget.admin
[ ] Tests: full workflow
```

### Phase 4: Budget Control (1.5 days)

```
[ ] BudgetService::checkBudget() — warn/block logic
[ ] ConfigService keys: warn_pct, block_pct, enabled
[ ] Integrate with JournalService::postEntry (optional check)
[ ] BudgetCheckResult model
[ ] Tests: under budget, warn threshold, block threshold
```

### Phase 5: Actual vs Budget Reports (1 day)

```
[ ] budget_actuals_view creation
[ ] BudgetComparisonService — month/quarter/YTD/forecast
[ ] CSV export
[ ] Tests: comparison accuracy
```

### Phase 6: Views & UI (1.5 days)

```
[ ] Budget dashboard view (sidebar widget)
[ ] Budget plan list — filter by year/status
[ ] Budget plan detail — summary by account
[ ] Budget line editor — inline grid
[ ] Budget comparison — table + chart (Chart.js)
[ ] Budget usage monitor — per account
```

### Phase 7: Multi-year & Scenarios (1 day)

```
[ ] BudgetService::createMultiYear, rollForward
[ ] BudgetScenario CRUD
[ ] Scenario application (% adjustment)
[ ] Tests: multi-year roll, scenario math
```

---

## 10. Effort Estimate

| Phase | Description | Days | Dependencies |
|---|---|---|---|
| 1 | Core data model + CRUD | 2 | Database, AccountRepository |
| 2 | Budget lines + allocation methods | 1.5 | Phase 1 |
| 3 | Approval workflow | 1 | Phase 2 |
| 4 | Budget control (warn/block) | 1.5 | Phase 3, JournalService, ConfigService |
| 5 | Actual vs Budget reports | 1 | Phase 3, GlService |
| 6 | Views & UI | 1.5 | Phase 1-5 |
| 7 | Multi-year & scenarios | 1 | Phase 2 |
| **Total** | | **9.5 days** | |

### Risk Factors

| Risk | Impact | Likelihood | Mitigation |
|---|---|---|---|
| Budget check slows down JournalService::postEntry | High | Medium | Optional: chỉ check khi `budget.enabled > 0` |
| Budget_lines 12-column model không linh hoạt | Medium | Low | Dùng JSON column nếu cần >12 period |
| Concurrent budget check khi nhiều user post cùng lúc | Medium | Low | Budget check là read-only, không cần lock |
| Data migration từ Excel budget | Medium | High | Cung cấp CSV import template |

### Definition of Done

```
[ ] All 7 Phases complete — 0 failures in tests/budget/*.php
[ ] Budget check works: warn at 90%, block at 110% (configurable)
[ ] Approval workflow: draft → submitted → approved → locked
[ ] Allocation methods: equal, % revenue, growth, seasonal
[ ] Actual vs Budget view: month, quarter, YTD, forecast
[ ] CSV export of budget plan + comparison
[ ] Backward compatible: budget.enabled = 0 → không ảnh hưởng gì
[ ] Audit trail: AuditLogger::log() cho mọi thay đổi budget
[ ] No debug code, no TODO, no commented-out code
```
