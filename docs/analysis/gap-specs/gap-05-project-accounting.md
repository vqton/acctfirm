# Gap 05: Project Accounting Module — Parity Specification

> **Mức độ hiện tại:** 0/10 — **Mục tiêu:** 9/10  
> **Phạm vi:** Quản lý chi phí, doanh thu, lãi/lỗ theo dự án xây lắp  
> **Benchmark:** FAST Construction, MISA SME Xây dựng  
> **Ngày:** 02/06/2026  
> **Tham chiếu:** `10-gaps-use-cases-consolidated.md` Gap 5, TT 99/2025/TT-BTC, VAS 15 — Hợp đồng xây dựng, Circular 200/2014/TT-BTC §31-34

---

## 1. Business Context & Rationale

### 1.1 Why This Matters

Bookwise hiện không có module quản lý dự án. Mọi chi phí được hạch toán vào tài khoản mà không gắn với dự án, do đó không thể trả lời các câu hỏi cơ bản:

- Dự án X đang lãi hay lỗ? Dự toán còn bao nhiêu?
- Chi phí thực tế của từng hạng mục so với dự toán thế nào?
- Giá trị nghiệm thu đã xuất hóa đơn? Đã thu được bao nhiêu?

Trong khi đó, ~15% doanh nghiệp Việt Nam hoạt động trong ngành xây lắp (xây dựng, lắp đặt, hạ tầng). FAST Construction và MISA SME Xây dựng là các sản phẩm riêng biệt với đầy đủ quy trình theo dõi dự án.

### 1.2 Competitive Landscape

| Phần mềm | Project Costing | Progress Billing | P&L by Project | Material Transfer | Budget Control |
|---|---|---|---|---|---|
| FAST Construction | ✅ | ✅ | ✅ | ✅ | ✅ |
| MISA SME Xây dựng | ✅ | ✅ | ✅ | ✅ | ✅ |
| BRAVO Xây dựng | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Bookwise** | **❌** | **❌** | **❌** | **❌** | **❌** |

### 1.3 Market Impact

Đây là gap P1. Nếu không có Project Accounting, Bookwise mất toàn bộ thị trường DN xây lắp. Đặc thù ngành xây dựng: chi phí phát sinh theo dự án, doanh thu ghi nhận theo phần trăm hoàn thành (POC), nghiệm thu theo giai đoạn — không thể dùng kế toán doanh nghiệp thông thường.

### 1.4 Regulatory Requirements

- **TT 99/2025/TT-BTC §41-47:** TK 154 — Chi phí SXKD dở dang phải theo dõi chi tiết theo từng công trình, hạng mục
- **VAS 15:** Hợp đồng xây dựng — ghi nhận doanh thu theo phương pháp tỷ lệ phần trăm hoàn thành (POC)
- **TT 32/2025/TT-BTC:** Hóa đơn điện tử cho nghiệm thu giai đoạn
- **Luật Xây dựng 50/2014/QH13:** Biên bản nghiệm thu là cơ sở xuất hóa đơn
- **Thông tư 200/2014/TT-BTC §31-34:** Kế toán chi phí sản xuất và giá thành sản phẩm xây lắp

---

## 2. Data Model

### 2.1 New Tables

```sql
-- DỰ ÁN / CÔNG TRÌNH
-- Mỗi dự án là một đối tượng tập hợp chi phí (cost object)
CREATE TABLE IF NOT EXISTS projects (
    id VARCHAR(50) PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    project_type ENUM('construction','service','internal','maintenance') NOT NULL DEFAULT 'construction',
    status ENUM('planning','active','suspended','completed','closed') NOT NULL DEFAULT 'planning',
    customer_id VARCHAR(50),
    contract_id VARCHAR(50),             -- Liên kết hợp đồng (bảng contracts)
    start_date DATE,
    end_date DATE,
    contract_value DECIMAL(15,2) DEFAULT 0,      -- Giá trị hợp đồng (đã bao gồm thuế)
    contract_value_net DECIMAL(15,2) DEFAULT 0,   -- Giá trị hợp đồng (chưa thuế)
    retention_rate DECIMAL(5,2) DEFAULT 5.00,     -- Tỷ lệ giữ lại (%)
    budget_total DECIMAL(15,2) DEFAULT 0,         -- Tổng dự toán
    estimated_cost DECIMAL(15,2) DEFAULT 0,       -- Tổng chi phí ước tính (POC calculation)
    parent_project_id VARCHAR(50),                -- Hỗ trợ phân cấp: dự án → hạng mục
    location VARCHAR(255),                         -- Địa điểm thi công
    manager VARCHAR(100),                          -- Chủ nhiệm dự án
    notes TEXT,
    created_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_status (status),
    INDEX idx_customer (customer_id),
    INDEX idx_contract (contract_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_project_id) REFERENCES projects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- HẠNG MỤC CÔNG VIỆC (Phases)
-- Chia dự án thành các giai đoạn nghiệm thu
CREATE TABLE IF NOT EXISTS project_phases (
    id VARCHAR(50) PRIMARY KEY,
    project_id VARCHAR(50) NOT NULL,
    phase_name VARCHAR(200) NOT NULL,
    phase_order INT DEFAULT 0,             -- Thứ tự thực hiện
    planned_start_date DATE,
    planned_end_date DATE,
    planned_value DECIMAL(15,2) DEFAULT 0,          -- Giá trị kế hoạch (chưa thuế)
    completion_pct DECIMAL(5,2) DEFAULT 0.00,       -- % hoàn thành (cập nhật từ nghiệm thu)
    status ENUM('planned','in_progress','completed','cancelled') DEFAULT 'planned',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_project (project_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DỰ TOÁN CHI TIẾT
-- Kế hoạch chi phí của dự án, dùng để so sánh thực tế vs dự toán
CREATE TABLE IF NOT EXISTS project_estimates (
    id VARCHAR(50) PRIMARY KEY,
    project_id VARCHAR(50) NOT NULL,
    phase_id VARCHAR(50),                  -- Gắn với hạng mục (nếu có)
    line_type ENUM('material','labor','machine','subcontractor','overhead','other') NOT NULL,
    item_code VARCHAR(50),                 -- Mã vật tư (nếu là NVL)
    description VARCHAR(255),
    quantity DECIMAL(15,2) DEFAULT 0,
    unit_price DECIMAL(15,2) DEFAULT 0,
    total_cost DECIMAL(15,2) DEFAULT 0,    -- quantity × unit_price
    actual_cost DECIMAL(15,2) DEFAULT 0,   -- Cập nhật từ chi phí thực tế
    is_approved TINYINT(1) DEFAULT 0,       -- Dự toán đã duyệt?
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_project (project_id),
    INDEX idx_phase (phase_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (phase_id) REFERENCES project_phases(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DỰ TOÁN VỐN (Tổng hợp theo khoản mục)
-- Dùng cho báo cáo tổng hợp thực tế vs dự toán
CREATE TABLE IF NOT EXISTS project_budget_lines (
    id VARCHAR(50) PRIMARY KEY,
    project_id VARCHAR(50) NOT NULL,
    budget_line_type ENUM('material','labor','machine','subcontractor','overhead','other') NOT NULL,
    budget_amount DECIMAL(15,2) DEFAULT 0,
    revised_amount DECIMAL(15,2) DEFAULT 0,    -- Dự toán sau điều chỉnh
    actual_amount DECIMAL(15,2) DEFAULT 0,      -- Chi phí thực tế (cập nhật từ ledger)
    committed_amount DECIMAL(15,2) DEFAULT 0,   -- Đã cam kết (PO, hợp đồng phụ)
    remaining_amount DECIMAL(15,2) AS (revised_amount - actual_amount - committed_amount) VIRTUAL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_type (project_id, budget_line_type),
    INDEX idx_project (project_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NGHIỆM THU (Completion Certificates)
-- Biên bản nghiệm thu giai đoạn — cơ sở xuất hóa đơn
CREATE TABLE IF NOT EXISTS project_completion_certs (
    id VARCHAR(50) PRIMARY KEY,
    cert_no VARCHAR(50) NOT NULL,               -- Số biên bản (tự động tăng)
    project_id VARCHAR(50) NOT NULL,
    phase_id VARCHAR(50),
    cert_date DATE NOT NULL,
    description TEXT,
    completion_pct DECIMAL(5,2) NOT NULL,        -- % hoàn thành của giai đoạn này
    cumulative_pct DECIMAL(5,2) NOT NULL,        -- % lũy kế
    cert_value DECIMAL(15,2) NOT NULL,           -- Giá trị nghiệm thu (chưa thuế)
    previous_cert_value DECIMAL(15,2) DEFAULT 0, -- Giá trị nghiệm thu lũy kế trước
    this_cert_value DECIMAL(15,2) AS (cert_value - previous_cert_value) VIRTUAL, -- Giá trị đợt này
    invoice_id VARCHAR(50),                      -- Liên kết với hóa đơn sau khi xuất
    invoice_status ENUM('not_issued','issued','paid') DEFAULT 'not_issued',
    approved_by VARCHAR(100),
    notes TEXT,
    created_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_project (project_id),
    INDEX idx_phase (phase_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (phase_id) REFERENCES project_phases(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ĐIỀU CHUYỂN VẬT TƯ GIỮA DỰ ÁN
CREATE TABLE IF NOT EXISTS project_material_transfers (
    id VARCHAR(50) PRIMARY KEY,
    transfer_no VARCHAR(50) NOT NULL,           -- Số phiếu điều chuyển
    from_project_id VARCHAR(50) NOT NULL,
    to_project_id VARCHAR(50) NOT NULL,
    item_id VARCHAR(50),
    quantity DECIMAL(15,2) NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,           -- Đơn giá điều chuyển (giá vốn)
    total_amount DECIMAL(15,2) NOT NULL,         -- quantity × unit_price
    transaction_id_from VARCHAR(50),             -- Bút toán giảm chi phí dự án A
    transaction_id_to VARCHAR(50),               -- Bút toán tăng chi phí dự án B
    transfer_date DATE NOT NULL,
    notes TEXT,
    created_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_from_project (from_project_id),
    INDEX idx_to_project (to_project_id),
    FOREIGN KEY (from_project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (to_project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PHÂN BỔ CHI PHÍ CHUNG
-- Cấu hình phân bổ chi phí máy thi công, chi phí chung cho nhiều dự án
CREATE TABLE IF NOT EXISTS project_cost_allocation_rules (
    id VARCHAR(50) PRIMARY KEY,
    rule_name VARCHAR(200) NOT NULL,
    cost_type ENUM('machine','overhead','other') NOT NULL,
    allocation_basis ENUM('revenue_pct','labor_hours','material_cost','machine_hours','direct_cost','equal') NOT NULL,
    source_account_code VARCHAR(20) NOT NULL,     -- TK tập hợp chi phí chung (ví dụ 154_chung, 627, 642)
    target_account_code VARCHAR(20) NOT NULL,      -- TK phân bổ (154)
    is_active TINYINT(1) DEFAULT 1,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CHI TIẾT PHÂN BỔ THEO DỰ ÁN
CREATE TABLE IF NOT EXISTS project_cost_allocation_details (
    id VARCHAR(50) PRIMARY KEY,
    rule_id VARCHAR(50) NOT NULL,
    project_id VARCHAR(50) NOT NULL,
    basis_value DECIMAL(15,2) DEFAULT 0,          -- Giá trị cơ sở phân bổ (VD: doanh thu dự án)
    allocation_pct DECIMAL(7,4) DEFAULT 0.0000,   -- Tỷ lệ phân bổ (%) = basis_value / total_basis
    actual_amount DECIMAL(15,2) DEFAULT 0,         -- Số tiền phân bổ kỳ này
    period VARCHAR(7),                              -- Kỳ: YYYY-MM
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rule_project_period (rule_id, project_id, period),
    INDEX idx_project (project_id),
    FOREIGN KEY (rule_id) REFERENCES project_cost_allocation_rules(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 2.2 Schema Changes to Existing Tables

```sql
-- Add dimension columns to ledger_entries
-- Enables GlService to group/subsidiary by project
ALTER TABLE ledger_entries
    ADD COLUMN project_id VARCHAR(50) DEFAULT NULL AFTER account_id,
    ADD COLUMN phase_id VARCHAR(50) DEFAULT NULL AFTER project_id,
    ADD COLUMN contract_id VARCHAR(50) DEFAULT NULL AFTER phase_id,
    ADD INDEX idx_project (project_id),
    ADD INDEX idx_phase (phase_id),
    ADD INDEX idx_contract (contract_id);
```

### 2.3 Model Changes

| Class | Change |
|---|---|
| `LedgerEntry` | Add `?string $projectId`, `?string $phaseId` properties + getters + constructor param |
| `Transaction` | Add `?string $projectId` for single-project transactions (optional; ledger_entries level is authoritative) |
| `ProjectService` | **New**: `Domain/Service/ProjectService.php` — core business logic |
| `ProjectRepositoryInterface` | **New**: `Domain/Repository/ProjectRepositoryInterface.php` |
| `PDOProjectRepository` | **New**: `Infrastructure/Repository/PDOProjectRepository.php` |
| `ProjectController` | **New**: `Interfaces/HTTP/Project/ProjectController.php` |
| `ProjectReportController` | **New**: `Interfaces/HTTP/Project/ProjectReportController.php` |
| `CompletionCertController` | **New**: `Interfaces/HTTP/Project/CompletionCertController.php` |
| `MaterialTransferController` | **New**: `Interfaces/HTTP/Project/MaterialTransferController.php` |

### 2.4 Config Keys (business_config table)

| Key | Type | Default | Purpose |
|---|---|---|---|
| `project.budget_warn_pct` | decimal | 90.00 | Cảnh báo khi chi phí thực tế vượt X% dự toán |
| `project.loss_warn_pct` | decimal | 10.00 | Cảnh báo khi lỗ dự kiến vượt X% hợp đồng |
| `project.max_poc_pct` | decimal | 95.00 | % hoàn thành tối đa (giữ lại 5% đến khi bàn giao) |
| `project.auto_allocate_overhead` | boolean | true | Tự động phân bổ chi phí chung cuối kỳ |
| `project.retention_default_pct` | decimal | 5.00 | Tỷ lệ giữ lại mặc định |

---

## 3. Process Flows

### 3.1 Project Setup & Budgeting

```
Kế toán trưởng
  → POST /api/projects (create project with code, name, contract)
  → POST /api/projects/{id}/phases (add work phases)
  → POST /api/projects/{id}/budget (import budget from Excel or manual entry)
  → POST /api/projects/{id}/budget/approve (approve budget — after this, warnings on overspend)
  → PATCH /api/projects/{id}/budget (revised budget if scope changes)

Budget import from Excel dự toán xây dựng:
  - Parse file Excel (mẫu dự toán gồm mã vật tư, khối lượng, đơn giá)
  - Match item_code với bảng items
  - Insert vào project_estimates
  - Aggregate vào project_budget_lines

Quy tắc:
  - Budget có thể nhập manual (không cần Excel) cho đơn giản
  - Budget sửa đổi (revised) lưu history — audit trail
```

### 3.2 Cost Collection

```
Mọi nghiệp vụ có chi phí liên quan đến dự án đều gắn project_id:

A. Mua hàng nhập kho (Procurement → Inventory):
   - PO gắn project_id → khi nhập kho, ghi project_id vào ledger_entries
   - Hạch toán: Nợ 152(project) / Có 331

B. Xuất kho cho dự án:
   - InventoryService: xuất kho với project_id
   - Hạch toán: Nợ 154(project) / Có 152
   - Giá xuất kho theo phương pháp định giá của item (FIFO/BQGQ)

C. Lương nhân công trực tiếp:
   - Payroll → gắn project_id
   - Hạch toán: Nợ 154(project) / Có 334

D. Khấu hao máy thi công:
   - FixedAssetService: phân bổ khấu hao vào dự án
   - Hạch toán: Nợ 154(project) / Có 214

E. Chi phí nhà thầu phụ:
   - AP → hóa đơn nhà thầu phụ gắn project_id
   - Hạch toán: Nợ 154(project) / Có 331

F. Chi phí chung (gián tiếp):
   - Ghi nhận vào tài khoản tập hợp (ví dụ 627, 642) không gắn project
   - Cuối kỳ phân bổ theo allocation rules

G. Chi phí máy thi công dùng chung:
   - Ghi nhận vào 154_máy_thi_công (cost pool)
   - Cuối kỳ phân bổ cho các dự án theo số giờ máy sử dụng
```

### 3.3 Progress Billing (Nghiệm thu → Hóa đơn)

```
1. Kỹ sư/hiệu trưởng công trường lập biên bản nghiệm thu giai đoạn
   → POST /api/projects/{id}/completion-certs
   {
     phase_id, cert_date, completion_pct, cert_value,
     cumulative_pct, previous_cert_value, description
   }

2. Hệ thống kiểm tra:
   - completion_pct ≤ 100
   - cumulative_pct ≤ project.max_poc_pct (trừ 5% giữ lại)
   - cert_value ≤ remaining contract value

3. Kế toán kiểm tra → xuất hóa đơn
   → POST /api/projects/completion-certs/{id}/issue-invoice
   - Tạo hóa đơn qua SalesOrderService (hoặc InvoiceService)
   - Hạch toán: Nợ 131(Customer) / Có 511 (ghi chú: project X, phase Y)

4. Ghi nhận doanh thu theo POC:
   - Hệ thống tự động tính:
     Revenue_recognized = Total_contract_value × completion_pct
     Cost_recognized = Total_estimated_cost × completion_pct
   - Bút toán cuối kỳ:
     Nợ 632 / Có 154 (giá vốn tương ứng)
     (Điều chỉnh nếu đã ghi nhận từ kỳ trước)
```

### 3.4 Cost Allocation Engine

```
Chạy cuối kỳ (PeriodService integration):

1. Tập hợp chi phí chung từ các cost pool account:
   - Chi phí máy thi công (pool account)
   - Chi phí chung phân xưởng (627)
   - Chi phí quản lý (642) — nếu được cấu hình

2. Xác định cơ sở phân bổ và tỷ lệ:
   For each allocation rule:
     - Tính total_basis = SUM(basis_value) over active projects
     - For each project:
         allocation_pct = project_basis / total_basis
         allocated_amount = total_cost_pool × allocation_pct

3. Tạo bút toán phân bổ:
   - 1 transaction cho mỗi dự án:
     Nợ 154(project) / Có cost_pool_account

4. Ghi nhận vào project_cost_allocation_details

Ví dụ: Chi phí máy thi công 100.000.000đ
  - Dự án A: 120 giờ máy / 200 tổng giờ = 60% → 60.000.000đ
  - Dự án B: 80 giờ máy / 200 tổng giờ = 40% → 40.000.000đ
```

### 3.5 Material Transfer Between Projects

```
1. POST /api/projects/material-transfers
   {
     from_project_id, to_project_id, item_id,
     quantity, unit_price, transfer_date
   }

2. Quy trình:
   - Kiểm tra tồn kho: dự án A còn đủ số lượng?
   - Lấy giá vốn xuất kho (FIFO/BQGQ)
   - Double-entry:
     Giảm chi phí dự án A: Nợ 154(A) / Có 154(A)  (điều chỉnh giảm)
     Tương đương: Nợ 154(A) âm đỏ? Không — dùng bút toán:
       Dr 154(B) (project B)  /  Cr 154(A) (project A)

   Thực tế hạch toán (3 bước):
     a. Ghi nhận xuất khỏi A:  Điều chỉnh giảm chi phí A
        Nợ 154(A) (số âm) / Có vật tư (số âm) — KHÔNG ĐƯỢC
     b. Đúng: 2 bút toán riêng
        Từ A: Nợ 152 (nhập lại kho) / Có 154(A) (giảm chi phí A)
        Từ B: Nợ 154(B) (tăng chi phí B) / Có 152 (xuất kho)
     c. Nếu điều chuyển thẳng (không qua kho):
        Nợ 154(B) (project B) — Có 154(A) (project A)
        => Posting rule phải cho phép Dr 154 / Cr 154 (internal transfer)

3. Tạo 2 transaction (hoặc 1 với allowed Dr/Cr rule):
   - Từ A: Nợ 154(B) / Có 154(A)  với module='project_transfer'
   - Ghi nhận vào project_material_transfers
```

### 3.6 Revenue Recognition (Percentage of Completion)

```
Cuối mỗi kỳ kế toán (PeriodService::closePeriod integration):

1. Tính POC cho mỗi dự án active:
   POC = actual_costs_incurred / total_estimated_costs × 100
   Giới hạn: min(POC, project.max_poc_pct)

2. Xác định doanh thu cần ghi nhận:
   Revenue_cumulative = contract_value × POC / 100
   Revenue_this_period = Revenue_cumulative - Revenue_previous_periods

3. Bút toán cuối kỳ (nếu chưa ghi nhận qua nghiệm thu):
   Nợ 131 — Phải thu khách hàng (chưa xuất hóa đơn!)
   Có 511 — Doanh thu hợp đồng xây dựng

   Lưu ý: Thông thường doanh thu xây lắp ghi nhận khi có nghiệm thu (BC),
   không chờ cuối kỳ. POC dùng để kiểm tra tính hợp lý và báo cáo quản trị.

4. Ghi nhận giá vốn:
   Cost_cumulative = estimated_total_cost × POC / 100
   Cost_this_period = Cost_cumulative - Cost_previous_periods
   Bút toán:
   Nợ 632 (Giá vốn hàng bán)
   Có 154 (Chi phí SXKD dở dang)

5. Cảnh báo nếu:
   - POC > budget_warn_pct (chi phí vượt dự toán)
   - Revenue - Cost < 0 (dự án lỗ)
   - Loss > loss_warn_pct of contract value
```

### 3.7 Project Closure

```
1. Kiểm tra điều kiện đóng:
   - Tất cả phases completed
   - Tất cả completion certs đã issued + paid
   - Retention còn lại (thường 5%) đã được giải ngân
   - Không còn chi phí chưa ghi nhận
   - Số dư 154(project) = 0 (đã kết chuyển hết vào 632)

2. POST /api/projects/{id}/close
   - Nếu 154(project) còn dư Nợ → tự động kết chuyển vào 632
     Nợ 632 / Có 154 (phần còn lại của chi phí)
   - Cập nhật status = 'closed'

3. KIỂM SOÁT: Chỉ Kế toán trưởng mới được đóng dự án.
   Phải có audit trail: ai đóng, khi nào, số dư cuối cùng.
```

### 3.8 Profit/Loss Analysis by Project

```
Báo cáo theo dự án, so sánh dự toán vs thực tế:

Project P&L
├── Doanh thu (511 by project)
│   ├── Kế hoạch: contract_value (hoặc phân bổ theo tỷ lệ)
│   ├── Thực tế: SUM(credit 511 where project_id = X)
│   └── Còn lại: Kế hoạch - Thực tế
├── Chi phí (154 + 632 by project)
│   ├── Vật liệu (152 → 154 by project)
│   ├── Nhân công (334 → 154 by project)
│   ├── Máy thi công (214/pool → 154 by project)
│   ├── Nhà thầu phụ (331 → 154 by project)
│   └── Chi phí chung (627 → 154 by project)
├── Lợi nhuận gộp = Doanh thu - Chi phí
├── Tỷ suất LN = LN / Doanh thu × 100
└── So sánh dự toán:
    ├── Dự toán chi phí (project_estimates)
    ├── Thực tế (ledger_entries by project)
    ├── Chênh lệch (tuyệt đối + %)
    └── Cảnh báo nếu > budget_warn_pct
```

---

## 4. Journal Entries

| # | Nghiệp vụ | Dr | Cr | Ghi chú |
|---|---|---|---|---|
| 1 | Xuất kho NVL cho dự án | 154(proj) | 152 | Giá xuất theo FIFO/BQGQ |
| 2 | Tiền lương công nhân trực tiếp | 154(proj) | 334 | Phân bổ theo bảng chấm công |
| 3 | Khấu hao máy thi công | 154(proj) | 214 | Phân bổ theo số giờ |
| 4 | Hóa đơn nhà thầu phụ | 154(proj) | 331 | Có hóa đơn VAT (1331) |
| 5 | Nghiệm thu giai đoạn → xuất hóa đơn | 131(customer) | 511 | Gắn project reference |
| 6 | Giá vốn theo POC cuối kỳ | 632 | 154(proj) | actual_cost × POC |
| 7 | Chi phí máy chung phân bổ | 154(projA) | 154_pool | Theo giờ máy |
| 8 | Điều chuyển vật tư giữa dự án | 154(projB) | 154(projA) | Dr/Cr internal transfer |
| 9 | Kết chuyển 154 còn lại khi đóng | 632 | 154(proj) | Số dư còn lại |
| 10 | Ứng trước cho nhà thầu phụ | 331(proj) | 111/112 | Ứng theo hợp đồng phụ |

**Detailed entry for entry #1 — Xuất kho NVL cho dự án:**
```php
// Nợ 154 (project X) / Có 152
$journal->postEntry(
    description: "Xuất kho NVL cho dự án {$projectCode}: {$itemsSummary}",
    reference: $voucherNo,
    lines: [
        ['account_code' => '154', 'amount' => $totalCost, 'is_debit' => true, 'project_id' => $projectId],
        ['account_code' => '152', 'amount' => $totalCost, 'is_debit' => false, 'project_id' => $projectId],
    ],
    createdBy: $user,
    module: 'inventory',
    voucherType: 'PXK'
);
```

**Detailed entry for entry #6 — Giá vốn theo POC:**
```php
// Nợ 632 / Có 154 (project X)
$poc = $this->calculatePOC($projectId); // actual / estimated × 100
$costToRecognize = $this->getCostToRecognize($projectId, $poc);

$journal->postEntry(
    description: "Kết chuyển giá vốn dự án {$projectCode} kỳ {$period}: POC {$poc}%",
    reference: $voucherNo,
    lines: [
        ['account_code' => '632', 'amount' => $costToRecognize, 'is_debit' => true, 'project_id' => $projectId],
        ['account_code' => '154', 'amount' => $costToRecognize, 'is_debit' => false, 'project_id' => $projectId],
    ],
    createdBy: $user,
    module: 'period_close',
    voucherType: 'JV'
);
```

**Account code note:** TK 154 (Chi phí SXKD dở dang) là tài khoản asset cấp 2, KHÔNG phải control account trong COA Circular 99 — cho phép post trực tiếp. Tuy nhiên, trong thực tế, nhiều doanh nghiệp xây lắp tạo tài khoản con 1541 (xây lắp), 1542 (lắp đặt), hoặc chi tiết theo dự án. Với project dimension (project_id trong ledger_entries), không cần tạo sub-account cho từng dự án — GL subsidiary ledger with group by project sẽ filter được.

---

## 5. Business Rules

### 5.1 General Validation

| Rule | Severity | Description |
|---|---|---|
| BR-P01 | REQUIRED | Mọi transaction có `project_id` khi module project accounting enabled |
| BR-P02 | REQUIRED | `project_budget_lines` được cập nhật tự động khi có chi phí gắn project |
| BR-P03 | REQUIRED | 154 balance must be >= 0 (không âm — chi phí dở dang không thể âm) |
| BR-P04 | REQUIRED | POC ∈ [0, 100] — không vượt 100% |
| BR-P05 | REQUIRED | `cumulative_pct` trong completion certs phải tăng dần |

### 5.2 Budget Control

| Rule | Severity | Description |
|---|---|---|
| BR-P06 | WARN | Chi phí thực tế > `project.budget_warn_pct`% budget → cảnh báo vàng |
| BR-P07 | BLOCK | Chi phí thực tế > 100% budget → BLOCK, cần kế toán trưởng duyệt vượt |
| BR-P08 | WARN | Dự án lỗ > `project.loss_warn_pct`% contract value → cảnh báo đỏ |

### 5.3 Progress Billing

| Rule | Severity | Description |
|---|---|---|
| BR-P09 | REQUIRED | Không thể xuất hóa đơn > contract_value - retention |
| BR-P10 | REQUIRED | `this_cert_value` = cert_value - previous_cert_value (tự động) |
| BR-P11 | REQUIRED | Mỗi completion cert phải có biên bản nghiệm thu (document upload) |
| BR-P12 | BLOCK | Không thể tạo cert mới nếu cert trước chưa issued invoice |

### 5.4 Material Transfer

| Rule | Severity | Description |
|---|---|---|
| BR-P13 | REQUIRED | Không thể chuyển nhiều hơn chi phí NVL đã ghi nhận cho dự án A |
| BR-P14 | REQUIRED | from_project_id ≠ to_project_id |
| BR-P15 | REQUIRED | unit_price must be ≥ 0 (giá vốn tại thời điểm xuất) |

### 5.5 Project Closure

| Rule | Severity | Description |
|---|---|---|
| BR-P16 | REQUIRED | Không thể đóng dự án nếu 154(project) còn dư Nợ (trừ khi tự động kết chuyển) |
| BR-P17 | REQUIRED | Sau khi đóng, không thể post thêm transaction gắn project |
| BR-P18 | REQUIRED | Chỉ Kế toán trưởng (permission `project.close`) mới được đóng |
| BR-P19 | REQUIRED | Phải có audit trail: số dư cuối, ngày đóng, người đóng |

### 5.6 Cost Allocation

| Rule | Severity | Description |
|---|---|---|
| BR-P20 | REQUIRED | Tổng phân bổ = 100% cho mỗi cost pool (tránh thiếu/hụt) |
| BR-P21 | WARN | Chênh lệch phân bổ > 10.000đ do làm tròn → điều chỉnh vào dự án lớn nhất |
| BR-P22 | REQUIRED | Phân bổ chỉ chạy cho kỳ hiện tại — không phân bổ hồi tố |

---

## 6. Integration Points

| Module | Integration Detail | Direction |
|---|---|---|
| **InventoryService** | Xuất kho (issueToProduction) gắn `project_id`. Hạch toán: Nợ 154(proj) / Có 152. `InventoryService.php:issueToProduction()` nhận thêm `projectId`. | Project → Inventory |
| **AP / Purchase** | Hóa đơn mua hàng, dịch vụ, nhà thầu phụ gắn `project_id`. `ApService.recordInvoice()` nhận thêm `projectId`. | Project → AP |
| **AR / Sales** | Hóa đơn bán hàng, nghiệm thu gắn `project_id` trên cả dòng doanh thu và công nợ. `ArService.recordInvoice()` nhận thêm `projectId`. | Project → AR |
| **JournalService** | Mọi bút toán liên quan dự án phải set `project_id` trong `$lines[i]['project_id']`. JournalService cần forward `project_id` vào `LedgerEntry`. | Core |
| **Payroll** | Lương nhân công trực tiếp gắn `project_id`. Tỷ lệ phân bổ theo bảng chấm công hoặc hệ số. | Project → Payroll |
| **FixedAsset (FA)** | Khấu hao máy thi công phân bổ vào dự án. `FixedAssetService.depreciate()` nhận thêm `projectId` hoặc allocation config. | Project → FA |
| **PeriodService** | Cuối kỳ: (1) Phân bổ chi phí chung, (2) Ghi nhận POC revenue, (3) Cảnh báo vượt budget/lỗ. | Period → Project |
| **FsService** | BC09 (Thuyết minh BCTC) có section dự án — doanh thu, chi phí, lãi/lỗ theo từng công trình (mẫu TT 99). | Project → FS |
| **GlService** | Xem sổ chi tiết 154 theo dự án (`groupBy=project`). GlService đã có sẵn code cho group by project. | Project → GL |
| **Cash** | Thu tiền theo tiến độ dự án. `CashService.recordReceipt()` nhận thêm `projectId`. | Project → Cash |
| **VoucherService** | Số CT prefix cho bút toán dự án: `PXK` (xuất kho), `PNK` (nhập kho), `DC` (điều chuyển), `NT` (nghiệm thu). | Project → Voucher |

---

## 7. API Endpoints

### 7.1 Project CRUD

```
GET    /api/projects                                — List projects (filter: status, customer, date)
GET    /api/projects/{id}                           — Project detail + summary stats
GET    /api/projects/{id}/cost-summary              — Chi phí theo khoản mục: thực tế vs dự toán
GET    /api/projects/{id}/profit-loss               — P&L: doanh thu, chi phí, lãi/lỗ
GET    /api/projects/{id}/phases                    — Danh sách hạng mục
POST   /api/projects                                — Create project
PUT    /api/projects/{id}                           — Update project info
PATCH  /api/projects/{id}/status                    — Update status (suspend/resume)
POST   /api/projects/{id}/close                     — Close project (KT duyệt)
DELETE /api/projects/{id}                           — Soft delete (admin)
```

### 7.2 Phases & Certs

```
GET    /api/projects/{id}/phases                    — List phases
POST   /api/projects/{id}/phases                    — Add phase
PUT    /api/projects/{id}/phases/{phaseId}          — Update phase
GET    /api/projects/{id}/completion-certs          — List completion certs
POST   /api/projects/{id}/completion-certs          — Create completion cert
POST   /api/projects/completion-certs/{certId}/issue-invoice  — Issue invoice from cert
```

### 7.3 Budget & Estimates

```
GET    /api/projects/{id}/estimates                 — Dự toán chi tiết
POST   /api/projects/{id}/estimates/import          — Import từ Excel
POST   /api/projects/{id}/estimates                 — Add estimate line
PUT    /api/projects/{id}/estimates/{estId}         — Update estimate
GET    /api/projects/{id}/budget                    — Budget summary by line type
POST   /api/projects/{id}/budget/approve            — Approve budget
```

### 7.4 Transfers & Allocations

```
POST   /api/projects/material-transfers             — Create material transfer
GET    /api/projects/material-transfers             — List transfers
GET    /api/projects/cost-allocation-rules          — List allocation rules
POST   /api/projects/cost-allocation-rules          — Create rule
POST   /api/projects/cost-allocation-rules/{id}/run — Run allocation for period
```

### 7.5 Reports

```
GET    /api/reports/projects/pnl                    — P&L summary (all projects)
GET    /api/reports/projects/pnl/{projectId}        — P&L detail (single project)
GET    /api/reports/projects/budget-vs-actual       — So sánh dự toán vs thực tế
GET    /api/reports/projects/cost-summary           — Tổng hợp chi phí theo khoản mục
GET    /api/reports/projects/dashboard              — Dashboard: traffic lights, top N projects
GET    /api/gl/subsidiary?account=154&groupBy=project  — Sổ chi tiết 154 theo dự án (existing GlService)
```

---

## 8. UI/UX

### 8.1 Project Dashboard

```
┌─────────────────────────────────────────────────────────────────────┐
│  Project Dashboard                          [Period: T06/2026]     │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐              │
│  │ Active       │  │ On Budget    │  │ At Risk      │              │
│  │ Projects: 12 │  │         8    │  │         4    │              │
│  └──────────────┘  └──────────────┘  └──────────────┘              │
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────────┐│
│  │ Project List           Traffic Lights: 🟢 🟡 🔴               ││
│  ├─────┬──────────┬────────┬────────┬────────┬────────┬───────────┤│
│  │Code │ Project  │ Budget │ Actual │  POC   │  P&L   │  Status   ││
│  ├─────┼──────────┼────────┼────────┼────────┼────────┼───────────┤│
│  │CT01 │CT Nhà A  │ 10.0B  │  8.5B  │  85%   │ +1.2B  │ 🟢 Active ││
│  │CT02 │Đường B   │ 25.0B  │ 24.0B  │  95%   │ -0.5B  │ 🔴 AtRisk ││
│  │CT03 │Cầu C     │  5.0B  │  4.2B  │  82%   │ +0.3B  │ 🟡 Warn   ││
│  └─────┴──────────┴────────┴────────┴────────┴────────┴───────────┘│
│                                                                     │
│  Traffic Light Logic:                                               │
│  🟢 Green:  P&L > 0 AND Actual/Budget < 90%                       │
│  🟡 Yellow: P&L > 0 AND Actual/Budget >= 90% (near budget)        │
│  🔴 Red:    P&L < 0 (loss project) OR Actual > Budget              │
└─────────────────────────────────────────────────────────────────────┘
```

### 8.2 Project Detail View

```
┌─────────────────────────────────────────────────────────────────────┐
│ Project: CT01 — Chung cư ABC             [🟢 Active]              │
│ Customer: Công ty XYZ     Manager: Nguyễn Văn A                    │
├─────────────────────────────────────────────────────────────────────┤
│ Summary                                                             │
│ Contract: 10,000,000,000  │ Budget: 9,500,000,000                  │
│ Actual:   8,500,000,000   │ POC: 89.5%   │ P&L: +1,200,000,000    │
│ Retention:    500,000,000 │ Billed: 8,500,000,000  │ Received: 7,000,000,000│
├─────────────────────────────────────────────────────────────────────┤
│ Cost Breakdown   [Real vs Budget]                                  │
│ ┌──────────────────────────────────────────────────────────────┐  │
│ │ Material  │ Budget: 4.0B ████████████████░░░░ 80% 3.2B     │  │
│ │ Labor     │ Budget: 2.0B ████████████████████ 95% 1.9B     │  │
│ │ Machine   │ Budget: 1.5B ██████████████░░░░░░ 70% 1.05B    │  │
│ │ Subcon    │ Budget: 1.5B ██████████████░░░░░░ 73% 1.1B     │  │
│ │ Overhead  │ Budget: 0.5B ████████████████████ 100% 0.5B 🔴│  │
│ └──────────────────────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────────────────┤
│ Phases / Milestones                                                │
│ ┌──┬────────────┬────────┬──────┬────────┬────────┬──────────────┐│
│ │# │ Phase      │ Planned│Actual│  POC   │ Status │ Cert/Invoice ││
│ ├──┼────────────┼────────┼──────┼────────┼────────┼──────────────┤│
│ │1 │ Móng       │ 3.0B   │ 2.8B │ 100%   │ ✅     │ NT-001 ✅     ││
│ │2 │ Thô        │ 4.0B   │ 3.8B │ 100%   │ ✅     │ NT-002 ✅     ││
│ │3 │ Hoàn thiện │ 2.0B   │ 1.5B │  75%   │ 🏗️     │ —             ││
│ │4 │ Bàn giao   │ 1.0B   │ 0.4B │  40%   │ 🏗️     │ —             ││
│ └──┴────────────┴────────┴──────┴────────┴────────┴──────────────┘│
└─────────────────────────────────────────────────────────────────────┘
```

### 8.3 View Files (Bootstrap 5 + jQuery)

```
public/views/projects.php              — Project list with traffic lights
public/views/project_detail.php         — Project detail + cost breakdown
public/views/project_form.php           — Create/edit project
public/views/project_phases.php         — Phases management
public/views/project_budget.php         — Budget vs actual
public/views/project_certs.php          — Completion certificates
public/views/project_material_transfer.php  — Material transfer form
public/views/project_pnl.php            — P&L report (single project)
public/views/projects_pnl.php           — P&L summary (all projects)
```

---

## 9. Implementation Checklist (Phased)

### Phase 1: Foundation (Days 1-2) — Migration + Model + CRUD

```
[ ] 1.1 Migration: NNN_create_projects_table (enhance existing 018)
[ ] 1.2 Migration: NNN_create_project_phases_table
[ ] 1.3 Migration: NNN_create_project_estimates_table
[ ] 1.4 Migration: NNN_create_project_budget_lines_table
[ ] 1.5 Migration: NNN_add_project_id_to_ledger_entries
[ ] 1.6 Model: Domain/Model/Project.php + ProjectPhase.php + ProjectEstimate.php
[ ] 1.7 Interface: ProjectRepositoryInterface
[ ] 1.8 PDO Repo: PDOProjectRepository
[ ] 1.9 Service: ProjectService (CRUD + budget management)
[ ] 1.10 Controller: ProjectController (CRUD endpoints)
[ ] 1.11 Routes: config/routes.php — project routes
[ ] 1.12 DI: config/services.php — register ProjectService + PDOProjectRepository
[ ] 1.13 Views: projects.php + project_form.php
[ ] 1.14 Sidebar: public/views/layout.php — add "Dự án" menu
[ ] 1.15 Permissions: Seed RBAC entries for 'project' module
```

### Phase 2: Cost Collection (Days 2-3) — Integrate with Inventory/AP/Journal

```
[ ] 2.1 Update LedgerEntry model: add ?string $projectId + ?string $phaseId
[ ] 2.2 Update JournalService: forward project_id from lines to LedgerEntry
[ ] 2.3 Update InventoryService: issueToProduction accepts projectId
[ ] 2.4 Update ApService: recordInvoice accepts projectId
[ ] 2.5 Update PayrollService: salary allocation accepts projectId
[ ] 2.6 GlService: verify groupBy=project works — fix queries if columns missing
[ ] 2.7 Test: post transactions with project_id → verify in ledger_entries
[ ] 2.8 Test: read GL subsidiary ledger grouped by project
```

### Phase 3: Budget & Reporting (Days 3-4)

```
[ ] 3.1 Service: project_budget_lines auto-update on cost entry
[ ] 3.2 Controller: budget endpoints (GET, POST, approve)
[ ] 3.3 Report: cost-summary — actual vs budget by line type
[ ] 3.4 Report: P&L by project (single + all)
[ ] 3.5 Warning: budget_warn_pct check on cost entry
[ ] 3.6 Warning: loss_warn_pct check on POC calculation
[ ] 3.7 Views: project_budget.php + project_pnl.php + projects_pnl.php
```

### Phase 4: Progress Billing & POC (Days 4-5)

```
[ ] 4.1 Migration: project_completion_certs table
[ ] 4.2 Service: CompletionCertService — create, validate, issue invoice
[ ] 4.3 Controller: CompletionCertController
[ ] 4.4 Integration: cert → invoice (link to SalesOrderService or direct AR entry)
[ ] 4.5 Service: POC calculation — auto compute each period
[ ] 4.6 Journal: cost recognition entry (Nợ 632 / Có 154) on period close
[ ] 4.7 Views: project_certs.php
[ ] 4.8 Test: full lifecycle — setup → cost → cert → invoice → POC
```

### Phase 5: Advanced (Days 5-6)

```
[ ] 5.1 Migration: project_material_transfers table
[ ] 5.2 Migration: project_cost_allocation_rules + details tables
[ ] 5.3 Service: MaterialTransferService — validate, post, audit
[ ] 5.4 Service: CostAllocationService — run allocation, post journal
[ ] 5.5 Rules: posting rules for Dr 154 / Cr 154 (internal transfer)
[ ] 5.6 Integration: budget import from Excel
[ ] 5.7 Dashboard: traffic lights, top N projects
[ ] 5.8 Views: project_detail.php (full dashboard), material_transfer.php
```

### Phase 6: Polish & Go-Live (Day 6-7)

```
[ ] 6.1 Project closure flow (BR-P16/17/18)
[ ] 6.2 Audit trail: all project actions logged
[ ] 6.3 CSV export: project list, P&L summary, cost detail
[ ] 6.4 Test: full lifecycle test (setup → cost → transfer → cert → invoice → close)
[ ] 6.5 Test: trial balance — Dr = Cr across all project transactions
[ ] 6.6 Test: budget warning and block scenarios
[ ] 6.7 Test: concurrent transactions on same project
[ ] 6.8 Performance: indexes on project_id in ledger_entries
[ ] 6.9 Documentation: user guide for project accounting
```

---

## 10. Effort Estimate

| Phase | Days | Complexity | Dependencies |
|---|---|---|---|
| Phase 1: Foundation | 2 | Medium | Existing migrations, DI pattern |
| Phase 2: Cost Collection | 1.5 | High | InventoryService, JournalService, ApService |
| Phase 3: Budget & Reporting | 1.5 | Medium | ProjectService, GlService |
| Phase 4: Progress Billing & POC | 2 | High | VAS 15, SalesOrder/AR integration |
| Phase 5: Advanced | 1.5 | High | Allocation engine, material transfer |
| Phase 6: Polish & Go-Live | 1 | Medium | Testing, docs, optimization |
| **Total** | **~9.5 days** | | |

**Notes:**
- Phase 1-2 có thể làm song song với Frontend (views)
- Phase 4 phụ thuộc vào SalesOrder module (Gap 1) — nếu chưa có SalesOrder, cert → invoice có thể AR direct
- Phase 5: Cost allocation engine có thể delay sang phase 2 nếu ưu tiên go-light trước

---

## 11. Risk Register

| ID | Risk | Impact | Mitigation |
|---|---|---|---|
| PR-01 | Posting rules chưa có Dr 154 / Cr 154 (internal transfer) | Block material transfer | Thêm rule cho module 'project_transfer' |
| PR-02 | LedgerEntries không có project_id column | Cost không trace được theo dự án | Migration phải chạy trước Phase 2 |
| PR-03 | VAS 15 POC phức tạp (estimated cost thay đổi) | Revenue recognition sai | Lưu estimated cost snapshot tại thời điểm tính POC |
| PR-04 | Chi phí chung phân bổ sai | P&L dự án không chính xác | Cho phép kế toán override tỷ lệ phân bổ |
| PR-05 | Dự án lỗ lớn → BC09 sai → audit warning | Pháp lý | Loss warning là REQUIRED, không thể tắt |
| PR-06 | Material transfer không đồng bộ cost layers | Giá vốn sai | Phải lấy unit_price từ cost layer tại thời điểm chuyển |
| PR-07 | Concurrent access trên cùng dự án | Budget check sai | Sử dụng FOR UPDATE khi cập nhật project_budget_lines |

---

## 12. Glossary

| Thuật ngữ | Giải thích |
|---|---|
| **154** | TK Chi phí SXKD dở dang — tập hợp chi phí xây lắp chưa hoàn thành |
| **POC** | Percentage of Completion — phương pháp ghi nhận doanh thu theo % hoàn thành |
| **Nghiệm thu (Completion Cert)** | Biên bản xác nhận khối lượng hoàn thành, cơ sở xuất hóa đơn |
| **Dự toán (Estimate)** | Kế hoạch chi phí chi tiết theo từng khoản mục |
| **Hạng mục (Phase)** | Phân đoạn công việc trong dự án, có mốc nghiệm thu riêng |
| **Cost Pool** | Tài khoản tập hợp chi phí chung trước khi phân bổ |
| **Điều chuyển** | Chuyển vật tư/chi phí giữa các dự án |
| **Giữ lại (Retention)** | 5% giá trị hợp đồng giữ lại đến khi bàn giao, bảo hành |
| **VAS 15** | Chuẩn mực kế toán Việt Nam số 15 — Hợp đồng xây dựng |
