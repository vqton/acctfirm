# GAP 02: Cost/Manufacturing Module — Deep-Dive Parity Specification

> **Phiên bản:** 1.0  
> **Trạng thái:** Draft  
> **Phân tích cho:** Bookwise Vietnamese Accounting System (TT 99/2025)  
> **Mức độ ưu tiên:** P0 — Blocking cho ~25% doanh nghiệp sản xuất Việt Nam  
> **Đối thủ:** BRAVO (MES-level manufacturing, BOM, routing, OEE), FAST (6-step cost allocation engine)  
> **Thời gian ước lượng:** 14-18 ngày (Phased)

---

## 1. Business Context & Regulatory Framework

### 1.1 Bối cảnh kinh doanh

Bookwise hiện có InventoryService (FIFO/Weighted Average cho xuất kho) nhưng **hoàn toàn không có tính giá thành sản phẩm sản xuất**. Doanh nghiệp sản xuất chiếm ~25% thị trường doanh nghiệp Việt Nam — phần lớn là SMEs trong lĩnh vực chế biến thực phẩm, may mặc, cơ khí, lắp ráp điện tử. Cả 3 đối thủ (MISA, FAST, BRAVO) đều có module tính giá thành:

| Đối thủ | Tính năng | Cấp độ |
|---|---|---|
| **BRAVO** | BOM, routing, work orders, OEE tracking, MES integration | MES-level |
| **FAST** | 6-step cost allocation engine, multi-stage, WIP 3 methods | Cost accounting |
| **MISA** | Production orders, BOM, cost allocation, variance analysis | Standard ERP |

### 1.2 Khung pháp lý — Thông tư 99/2025/TT-BTC

| TK | Tên | Loại | Bản chất |
|---|---|---|---|
| 621 | Chi phí NVL trực tiếp | Expense (6) | Tập hợp CP NVL cho SX — cuối kỳ kết chuyển sang 154 |
| 622 | Chi phí nhân công trực tiếp | Expense (6) | Tập hợp CP NC trực tiếp — lương, phụ cấp, BHXH, BHYT, BHTN, KPCĐ |
| 627 | Chi phí sản xuất chung | Expense (6) | Tập hợp CP SXC: điện, nước, khấu hao, CCDC, lương gián tiếp |
| 154 | Chi phí SXKD dở dang | Asset (2) | WIP — tập hợp 621+622+627, sau đó kết chuyển thành phẩm |
| 155 | Sản phẩm | Asset (2) | Thành phẩm nhập kho sau khi hoàn thành SX |

**Nguyên tắc hạch toán giá thành theo TT 99:**
1. Tập hợp toàn bộ CP vào 621/622/627 trong kỳ
2. Cuối kỳ kết chuyển: Nợ 154 / Có 621, 622, 627
3. Nhập kho thành phẩm: Nợ 155 / Có 154
4. Giá thành = (DDĐK + PS - DDCK) / Số lượng nhập kho

### 1.3 Các phương pháp tính giá thành phổ biến tại VN

| Phương pháp | Mô tả | Phù hợp |
|---|---|---|
| **Giản đơn (Simple)** | Tổng CP / SL nhập kho | SX 1 loại SP, chu kỳ ngắn |
| **Hệ số (Coefficient)** | Quy đổi SP về SP tiêu chuẩn | SX nhiều SP cùng NVL |
| **Tỷ lệ (Ratio)** | Phân bổ theo định mức | SX theo đơn hàng |
| **Định mức (Standard)** | CP định mức ± chênh lệch | SX ổn định, có định mức |
| **Phân bước (Process/Multi-stage)** | Tính giá BTP từng công đoạn | SX nhiều công đoạn |

---

## 2. Module Architecture

### 2.1 Services (MỚI)

```
CostService                                   — Facade chính cho module giá thành
├── CostAllocationService                     — Engine phân bổ chi phí (FAST 6-step)
├── ProductionOrderService                    — Quản lý lệnh sản xuất (vòng đời)
├── BomService                                — Quản lý định mức NVL (BOM)
├── WipEvaluationService                      — Đánh giá SPDD (3 phương pháp)
└── CostCalculationService                    — Tính giá thành và tạo bút toán

Module phụ trợ (tích hợp từ service hiện có):
├── InventoryService (issueGoods production)  — Xuất NVL → 154
├── InventoryService (receiveGoods product)   — Nhập thành phẩm → 155
├── JournalService (postEntry)                — Bút toán kết chuyển
├── PeriodService (closePeriod)               — Tích hợp vào quy trình đóng kỳ
└── ConfigService                             — Business rules
```

### 2.2 Dependency Graph

```
CostService
├── CostAllocationService
│   ├── JournalService (post kết chuyển 621/622/627 → 154)
│   ├── InventoryService (đọc cost layers NVL)
│   └── WipEvaluationService
├── ProductionOrderService
│   ├── BOMService (kiểm tra định mức)
│   ├── InventoryService (xuất/nhập)
│   └── JournalService
├── BOMService
│   └── ConfigService (cost.warn_excess_pct)
└── CostCalculationService
    ├── CostAllocationService
    └── JournalService (post 632 ± variance)
```

### 2.3 Repositories (MỚI)

```
BomRepositoryInterface → PDOBomRepository
BomLineRepositoryInterface → PDOBomLineRepository
ProductionOrderRepositoryInterface → PDOProductionOrderRepository
ProductionOrderMaterialRepositoryInterface → PDOProductionOrderMaterialRepository
ProductionOrderLaborRepositoryInterface → PDOProductionOrderLaborRepository
ProductionOrderOverheadRepositoryInterface → PDOProductionOrderOverheadRepository
WorkCenterRepositoryInterface → PDOWorkCenterRepository
CostPeriodRunRepositoryInterface → PDOCostPeriodRunRepository
WipEstimateRepositoryInterface → PDOWipEstimateRepository
```

### 2.4 Controllers (MỚI)

```
CostController           — /api/cost/* (run cost calc, reports)
ProductionOrderController — /api/production-orders/* (CRUD lifecycle)
BomController            — /api/bom/* (CRUD BOM)
WipController            — /api/wip/* (evaluate, report)
```

### 2.5 DI Registration Pattern

```php
// config/services/38_cost.php
$bomRepository = new PDOBomRepository($pdo);
$productionOrderRepository = new PDOProductionOrderRepository($pdo);
$workCenterRepository = new PDOWorkCenterRepository($pdo);
$costPeriodRunRepository = new PDOCostPeriodRunRepository($pdo);

$bomService = new BOMService($bomRepository, $itemRepository, $auditLogger);
$wipEvaluationService = new WipEvaluationService($pdo, $accountRepository);
$productionOrderService = new ProductionOrderService(
    $productionOrderRepository, $bomService, $inventoryService,
    $journalService, $voucherService, $auditLogger
);
$costAllocationService = new CostAllocationService(
    $pdo, $accountRepository, $journalService, $inventoryService, $configService
);
$costCalculationService = new CostCalculationService(
    $pdo, $accountRepository, $journalService, $costAllocationService, $wipEvaluationService
);
$costService = new CostService(
    $costCalculationService, $costAllocationService, $productionOrderService,
    $bomService, $wipEvaluationService, $costPeriodRunRepository
);
```

---

## 3. Data Model

### 3.1 Bảng `work_centers` — Trung tâm / công đoạn sản xuất

```sql
CREATE TABLE IF NOT EXISTS work_centers (
    id VARCHAR(50) PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    cost_type ENUM('machine_hour','labor_hour','unit_output') NOT NULL DEFAULT 'machine_hour',
    hourly_rate DECIMAL(15,2) DEFAULT 0,
    machine_rate DECIMAL(15,2) DEFAULT 0,
    overhead_rate DECIMAL(15,2) DEFAULT 0,       -- Tỷ lệ phân bổ CPSXC (đơn vị: VND/giờ hoặc %)
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.2 Bảng `bom` — Bill of Materials (Định mức NVL)

```sql
CREATE TABLE IF NOT EXISTS bom (
    id VARCHAR(50) PRIMARY KEY,
    product_id VARCHAR(50) NOT NULL,             -- FK → items.id (item_type = 'product')
    version INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
    effective_date DATE NOT NULL,
    notes TEXT,
    created_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES items(id),
    INDEX idx_product (product_id),
    INDEX idx_status (status),
    UNIQUE KEY uq_product_version (product_id, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.3 Bảng `bom_lines` — Định mức chi tiết NVL cho 1 SP

```sql
CREATE TABLE IF NOT EXISTS bom_lines (
    id VARCHAR(50) PRIMARY KEY,
    bom_id VARCHAR(50) NOT NULL,
    material_id VARCHAR(50) NOT NULL,            -- FK → items.id
    qty_per_unit DECIMAL(15,4) NOT NULL,         -- Số lượng NVL cho 1 SP
    wastage_pct DECIMAL(5,2) DEFAULT 0,          -- Tỷ lệ hao hụt (%)
    unit VARCHAR(50) NOT NULL,
    cost_type ENUM('raw_material','semi_finished','packaging','other') DEFAULT 'raw_material',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bom_id) REFERENCES bom(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES items(id),
    INDEX idx_bom (bom_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.4 Bảng `production_orders` — Lệnh sản xuất

```sql
CREATE TABLE IF NOT EXISTS production_orders (
    id VARCHAR(50) PRIMARY KEY,
    reference VARCHAR(50) NOT NULL UNIQUE,       -- Format: PO{YYYY}-{000000}
    product_id VARCHAR(50) NOT NULL,
    bom_id VARCHAR(50),                          -- FK → bom
    work_center_id VARCHAR(50),                  -- FK → work_centers
    qty DECIMAL(15,2) NOT NULL,                  -- Số lượng SX kế hoạch
    completed_qty DECIMAL(15,2) DEFAULT 0,       -- Số lượng đã hoàn thành
    start_date DATE,
    end_date DATE,
    due_date DATE,
    status ENUM('draft','released','in_progress','completed','costed','closed','cancelled')
        NOT NULL DEFAULT 'draft',
    notes TEXT,
    created_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES items(id),
    FOREIGN KEY (bom_id) REFERENCES bom(id),
    FOREIGN KEY (work_center_id) REFERENCES work_centers(id),
    INDEX idx_reference (reference),
    INDEX idx_status (status),
    INDEX idx_product (product_id),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.5 Bảng `production_order_materials` — NVL xuất cho lệnh SX

```sql
CREATE TABLE IF NOT EXISTS production_order_materials (
    id VARCHAR(50) PRIMARY KEY,
    production_order_id VARCHAR(50) NOT NULL,
    material_id VARCHAR(50) NOT NULL,
    planned_qty DECIMAL(15,2) NOT NULL DEFAULT 0,   -- Định mức × SL SX
    actual_qty DECIMAL(15,2) NOT NULL DEFAULT 0,    -- SL thực tế xuất kho
    unit_cost DECIMAL(15,2) DEFAULT 0,               -- Đơn giá xuất (tính bởi cost engine)
    total_cost DECIMAL(15,2) DEFAULT 0,              -- actual_qty × unit_cost
    transaction_id VARCHAR(50),                       -- FK → transactions (bút toán xuất kho)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES items(id),
    INDEX idx_po (production_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.6 Bảng `production_order_labor` — Nhân công trực tiếp cho lệnh SX

```sql
CREATE TABLE IF NOT EXISTS production_order_labor (
    id VARCHAR(50) PRIMARY KEY,
    production_order_id VARCHAR(50) NOT NULL,
    employee_id VARCHAR(50),                       -- FK → employees (nullable vì có thể ghi nhận tổng)
    labor_type VARCHAR(100) DEFAULT 'direct',      -- 'direct', 'indirect'
    actual_hours DECIMAL(10,2) NOT NULL DEFAULT 0,
    hourly_rate DECIMAL(15,2) DEFAULT 0,
    total_cost DECIMAL(15,2) DEFAULT 0,            -- actual_hours × hourly_rate
    transaction_id VARCHAR(50),                    -- FK → transactions (bút toán lương)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
    INDEX idx_po (production_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.7 Bảng `production_order_overhead` — CPSXC cho lệnh SX

```sql
CREATE TABLE IF NOT EXISTS production_order_overhead (
    id VARCHAR(50) PRIMARY KEY,
    production_order_id VARCHAR(50) NOT NULL,
    overhead_type VARCHAR(100) NOT NULL,          -- 'electricity', 'water', 'depreciation', 'ccdc', 'indirect_labor', 'maintenance', 'other'
    allocation_method ENUM('machine_hours','labor_hours','material_cost_pct','unit_output') NOT NULL,
    allocation_base DECIMAL(15,2) NOT NULL,       -- Giá trị cơ sở phân bổ (giờ máy, giờ công, % CP NVL...)
    rate DECIMAL(15,4) DEFAULT 0,                 -- Tỷ lệ phân bổ
    total_cost DECIMAL(15,2) DEFAULT 0,           -- allocation_base × rate
    transaction_id VARCHAR(50),                   -- FK → transactions
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
    INDEX idx_po (production_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.8 Bảng `cost_period_runs` — Lịch sử chạy tính giá thành

```sql
CREATE TABLE IF NOT EXISTS cost_period_runs (
    id VARCHAR(50) PRIMARY KEY,
    period_year SMALLINT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL,
    status ENUM('running','completed','failed','rolled_back') NOT NULL DEFAULT 'running',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    error_log TEXT,
    summary_json JSON,                            -- Tổng hợp kết quả tính giá
    initiated_by VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_period (period_year, period_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.9 Bảng `wip_estimates` — Đánh giá SPDD (kết quả từ WipEvaluationService)

```sql
CREATE TABLE IF NOT EXISTS wip_estimates (
    id VARCHAR(50) PRIMARY KEY,
    period_year SMALLINT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL,
    product_id VARCHAR(50) NOT NULL,
    method ENUM('raw_material_only','equivalent_units','stage_by_stage') NOT NULL,
    opening_qty DECIMAL(15,2) DEFAULT 0,
    opening_value DECIMAL(15,2) DEFAULT 0,
    input_qty DECIMAL(15,2) DEFAULT 0,            -- SL đưa vào SX trong kỳ
    completed_qty DECIMAL(15,2) DEFAULT 0,        -- SL hoàn thành nhập kho
    closing_qty DECIMAL(15,2) DEFAULT 0,          -- SL DD cuối kỳ
    closing_material_cost DECIMAL(15,2) DEFAULT 0,
    closing_labor_cost DECIMAL(15,2) DEFAULT 0,
    closing_overhead_cost DECIMAL(15,2) DEFAULT 0,
    closing_total DECIMAL(15,2) DEFAULT 0,        -- Tổng giá trị DD cuối kỳ
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES items(id),
    UNIQUE KEY uq_period_product (period_year, period_month, product_id),
    INDEX idx_period (period_year, period_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.10 Bảng `production_order_cost_summary` — Tổng hợp chi phí từng lệnh SX

```sql
CREATE TABLE IF NOT EXISTS production_order_cost_summary (
    id VARCHAR(50) PRIMARY KEY,
    production_order_id VARCHAR(50) NOT NULL,
    period_year SMALLINT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL,
    total_material_cost DECIMAL(15,2) DEFAULT 0,
    total_labor_cost DECIMAL(15,2) DEFAULT 0,
    total_overhead_cost DECIMAL(15,2) DEFAULT 0,
    total_cost DECIMAL(15,2) DEFAULT 0,
    unit_cost DECIMAL(15,2) DEFAULT 0,            -- total_cost / completed_qty
    status ENUM('estimated','finalised') DEFAULT 'estimated',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
    UNIQUE KEY uq_po_period (production_order_id, period_year, period_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.11 Bảng `product_cost_history` — Lịch sử giá thành sản phẩm

```sql
CREATE TABLE IF NOT EXISTS product_cost_history (
    id VARCHAR(50) PRIMARY KEY,
    product_id VARCHAR(50) NOT NULL,
    period_year SMALLINT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL,
    unit_cost DECIMAL(15,2) NOT NULL,
    cost_run_id VARCHAR(50),                      -- FK → cost_period_runs
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES items(id),
    FOREIGN KEY (cost_run_id) REFERENCES cost_period_runs(id),
    UNIQUE KEY uq_product_period (product_id, period_year, period_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 4. Process Flows

### 4.1 FAST 6-Step Cost Calculation (Period-end)

Quy trình này chạy cuối mỗi kỳ kế toán (tháng/quý). Mỗi bước đều có thể idempotent — có thể chạy lại nếu thất bại.

#### Bước 1: Tính số lượng sản xuất trong kỳ

Đếm số lượng thành phẩm nhập kho từ sản xuất trong kỳ (item_type = 'product', transaction có nguồn = SX).

```sql
-- Đếm số lượng thành phẩm nhập kho từ SX trong kỳ
SELECT
    txn_lines.item_id AS product_id,
    SUM(txn_lines.qty) AS produced_qty
FROM (
    SELECT
        le.account_id,
        t.id AS txn_id,
        t.description
    FROM ledger_entries le
    JOIN transactions t ON t.id = le.transaction_id
    WHERE t.date BETWEEN :start_date AND :end_date
      AND le.account_code = '155'
      AND le.is_debit = 1
      AND t.description LIKE '%SX%'
) AS receipt_txns
JOIN transaction_lines txn_lines ON txn_lines.transaction_id = receipt_txns.txn_id
GROUP BY txn_lines.item_id;
```

**PHP Implementation:**
```php
public function calculateProducedQty(string $startDate, string $endDate): array
{
    $pdo = $this->pdo;
    $stmt = $pdo->prepare("
        SELECT item_id, SUM(qty) AS qty
        FROM inventory_cost_layers icl
        JOIN transactions t ON t.id = icl.source_transaction_id
        WHERE t.date BETWEEN ? AND ?
          AND icl.source_type = 'production_receipt'
        GROUP BY item_id
    ");
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}
```

#### Bước 2: Tính & áp giá xuất kho NVL

Đọc đơn giá xuất NVL từ inventory_cost_layers theo FIFO/WA, ghi nhận vào production_order_materials.unit_cost.

```sql
-- Lấy tổng giá trị NVL xuất cho SX trong kỳ theo từng nguyên liệu
SELECT
    le_material.account_code AS material_account,
    SUM(le_material.amount) AS total_material_cost
FROM ledger_entries le_material
JOIN transactions t ON t.id = le_material.transaction_id
WHERE t.date BETWEEN :start_date AND :end_date
  AND le_material.is_debit = 0
  AND le_material.account_code IN ('152','153')  -- TK NVL, CCDC
  AND EXISTS (
      SELECT 1 FROM ledger_entries le2
      WHERE le2.transaction_id = t.id
        AND le2.is_debit = 1
        AND le2.account_code = '154'             -- Xuất cho SX
  )
GROUP BY le_material.account_code;
```

#### Bước 3: Tập hợp & phân bổ CP NVL chi tiết theo mã NVL

Đọc sổ kho chi tiết từng mã NVL, phân bổ vào từng sản phẩm theo:
- Định mức từ BOM (nếu có)
- Tỷ lệ sản lượng thực tế (nếu không có BOM)

```sql
-- Phân bổ CP NVL theo BOM
SELECT
    po.id AS production_order_id,
    po.product_id,
    pol.material_id,
    pol.actual_qty,
    pol.unit_cost,
    pol.total_cost
FROM production_orders po
JOIN production_order_materials pol ON pol.production_order_id = po.id
WHERE po.status IN ('completed', 'costed')
  AND po.end_date BETWEEN :start_date AND :end_date;
```

#### Bước 4: Tập hợp & phân bổ CP NVL không chi tiết, CP nhân công & CP chung

Phân bổ chi phí không gắn với lệnh SX cụ thể (CP nhân công gián tiếp, điện, nước, khấu hao):

```sql
-- Tính tổng CPSXC thực tế trong kỳ
SELECT
    SUM(amount) AS total_overhead
FROM ledger_entries le
JOIN transactions t ON t.id = le.transaction_id
WHERE t.date BETWEEN :start_date AND :end_date
  AND le.account_code = '627'
  AND le.is_debit = 1;

-- Phân bổ theo giờ máy cho từng lệnh SX
UPDATE production_order_overhead poo
SET total_cost = (
    poo.allocation_base * (
        SELECT SUM(amount) FROM ledger_entries le
        WHERE le.account_code = '627' AND le.is_debit = 1
          AND le.created_at BETWEEN :start_date AND :end_date
    ) / (
        SELECT SUM(allocation_base) FROM production_order_overhead
        WHERE created_at BETWEEN :start_date AND :end_date
    )
)
WHERE created_at BETWEEN :start_date AND :end_date;
```

#### Bước 5: Tổng hợp chi phí & tính giá thành đơn vị

**Công thức:** Giá thành = (DDĐK + PS - DDCK) / SL nhập kho

```sql
-- Tính giá thành đơn vị cho từng sản phẩm
SELECT
    poc.production_order_id,
    po.product_id,
    poc.total_material_cost,
    poc.total_labor_cost,
    poc.total_overhead_cost,
    poc.total_cost,
    po.completed_qty,
    CASE WHEN po.completed_qty > 0
        THEN ROUND(poc.total_cost / po.completed_qty, 2)
        ELSE 0
    END AS unit_cost
FROM production_order_cost_summary poc
JOIN production_orders po ON po.id = poc.production_order_id
WHERE poc.period_year = :year AND poc.period_month = :month;
```

**PHP Implementation:**
```php
public function calculateUnitCost(string $periodYear, string $periodMonth): array
{
    $pdo = $this->pdo;
    $stmt = $pdo->prepare("
        SELECT
            po.product_id,
            po.reference,
            SUM(pol.total_cost) AS material_cost,
            SUM(pl.total_cost) AS labor_cost,
            SUM(poo.total_cost) AS overhead_cost,
            (SUM(pol.total_cost) + SUM(pl.total_cost) + SUM(poo.total_cost)) AS total_cost,
            po.completed_qty,
            CASE WHEN po.completed_qty > 0
                THEN ROUND((SUM(pol.total_cost) + SUM(pl.total_cost) + SUM(poo.total_cost)) / po.completed_qty, 2)
                ELSE 0
            END AS unit_cost
        FROM production_orders po
        LEFT JOIN production_order_materials pol ON pol.production_order_id = po.id
        LEFT JOIN production_order_labor pl ON pl.production_order_id = po.id
        LEFT JOIN production_order_overhead poo ON poo.production_order_id = po.id
        WHERE po.status IN ('completed', 'costed')
          AND YEAR(po.end_date) = ?
          AND MONTH(po.end_date) = ?
        GROUP BY po.id
    ");
    $stmt->execute([$periodYear, $periodMonth]);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}
```

#### Bước 6: Cập nhật giá cho phiếu nhập thành phẩm

Áp giá thành tính được vào cost layer của thành phẩm vừa nhập kho:

```sql
-- Cập nhật unit_cost cho thành phẩm nhập kho từ SX
UPDATE inventory_cost_layers icl
JOIN production_order_cost_summary poc ON poc.production_order_id = icl.source_id
SET icl.unit_cost = poc.unit_cost,
    icl.total_cost = icl.qty * poc.unit_cost
WHERE icl.source_type = 'production_receipt'
  AND icl.created_at BETWEEN :start_date AND :end_date;
```

**Post-step — Kết chuyển chi phí:**
```sql
-- Bút toán: Nợ 154 / Có 621, 622, 627
-- và Nợ 155 / Có 154 (nhập kho thành phẩm)
```

### 4.2 Production Order Lifecycle

```
                    +----------+
                    |  DRAFT   |   ← Khởi tạo lệnh SX, nhập kế hoạch
                    +----+-----+
                         |
                    (Release — kiểm tra NVL tồn kho đủ?)
                         |
                    +----v-----+
            +------>| RELEASED |   ← Phát lệnh, xuất kho NVL
            |       +----+-----+
            |            |
            |       (Xuất NVL thực tế → 154)
            |            |
            |       +----v--------+
            |       | IN_PROGRESS |   ← SX đang diễn ra
            |       +----+--------+
            |            |
            |       (Nhập kho thành phẩm)
            |            |
            |       +----v---------+
            +-------+ COMPLETED    |   ← Hoàn thành SX, chờ tính giá
            |       +----+---------+
            |            |
            |       (Cost calculation engine chạy)
            |            |
            |       +----v------+
            |       |  COSTED   |   ← Đã tính giá thành
            |       +----+------+
            |            |
            |       (Xác nhận số liệu)
            |            |
            |       +----v------+
            +-------+  CLOSED   |   ← Đã khóa, chỉ đọc
                    +-----------+

  CANCELLED ← Mọi trạng thái đều có thể hủy (nếu chưa phát sinh giao dịch)
```

**State transitions & validation matrix:**

| Từ | → Tới | Điều kiện | Hành động |
|---|---|---|---|
| draft | released | NVL tồn kho ≥ planned_qty (cảnh báo nếu thiếu) | Kiểm tra BOM, tạo số CT |
| released | in_progress | Đã xuất NVL >= 1 lần | Ghi nhận ngày bắt đầu |
| in_progress | completed | completed_qty > 0 | Journal: nhập kho 155/Có 154 |
| completed | costed | CostPeriodRun đã chạy | Ghi unit_cost vào cost layer |
| costed | closed | Kế toán trưởng duyệt | Khóa, audit log |
| * | cancelled | Không có giao dịch phát sinh | Rollback tồn kho nếu có |

### 4.3 Multi-Stage Manufacturing (Sản xuất nhiều công đoạn)

Xử lý sản xuất N công đoạn, mỗi công đoạn tạo bán thành phẩm (BTP).

```
Công đoạn 1:                    Công đoạn 2:                    Công đoạn 3:
NVL (152)                       BTP-1 (154/v1)                  BTP-2 (154/v2)
  │                                │                                │
  ▼                                ▼                                ▼
154/v1 (CPSX GĐ1) ──► BTP-1   154/v2 (CPSX GĐ2) ──► BTP-2    154/v3 (CPSX GĐ3)
  │                                │                                │
  ▼                                ▼                                ▼
Nợ 154/v1 / Có 152            Nợ 154/v2 / Có 154/v1           Nợ 155 / Có 154/v3
```

**Implementation approach:** Mỗi công đoạn là một `work_center` riêng. Bán thành phẩm truyền giữa các công đoạn qua chuyển kho nội bộ 154 → 154.

**Ví dụ:** Sản xuất áo sơ mi (3 công đoạn: cắt → may → ủi/đóng gói)

```php
// Vòng 1: Cắt vải
$cutPo = $productionOrderService->create('shirt', 'PO2026-000001', 1000, $cutWorkCenterId, $bomId, $user);
// Xuất kho vải → 154 (cắt)
$inventoryService->issueGoods('fabric_001', 1100, 'production', 'PO2026-000001', $user);
$productionOrderService->start($cutPo['id']);
$productionOrderService->complete($cutPo['id'], 950); // 5% hao hụt

// Vòng 2: May
$sewPo = $productionOrderService->create('shirt', 'PO2026-000002', 950, $sewWorkCenterId, null, $user);
// Chuyển BTP từ công đoạn cắt → công đoạn may: Nợ 154/v2 / Có 154/v1
$costService->transferSemiFinished($cutPo['id'], $sewPo['id'], 950);
$productionOrderService->start($sewPo['id']);
$productionOrderService->complete($sewPo['id'], 940);

// Vòng 3: Ủi + đóng gói → thành phẩm (155)
$finishPo = $productionOrderService->create('shirt', 'PO2026-000003', 940, $finishWorkCenterId, null, $user);
$costService->transferSemiFinished($sewPo['id'], $finishPo['id'], 940);
$productionOrderService->start($finishPo['id']);
$productionOrderService->complete($finishPo['id'], 935);
// Nhập kho 155: Nợ 155 / Có 154/v3
$inventoryService->receiveGoods('shirt', 935, 0, [], 'PO2026-000003', $user);
```

**Bút toán chuyển giai đoạn (semi-finished transfer):**
```sql
-- Nợ 154 (công đoạn nhận) / Có 154 (công đoạn chuyển)
INSERT INTO ledger_entries (id, transaction_id, account_id, amount, is_debit)
VALUES ('led_recv', :txnId, :receivingWipAccount, :totalCost, 1),
       ('led_send', :txnId, :sendingWipAccount, :totalCost, 0);
```

**RỦI RO:** Chi phí dồn tích qua N công đoạn — nếu 1 công đoạn sai, toàn bộ chuỗi sai. Biện pháp: snapshot cost từng công đoạn trước khi chuyển.

### 4.4 WIP Evaluation (3 methods)

#### Method 1: Chi phí NVL trực tiếp (Raw material only)

Chỉ tính DD cuối kỳ = CP NVL chính. NC và SXC tính hết vào thành phẩm.

**Công thức:** `DD_CK = DD_NVL_ĐK + PS_NVL - NVL_dùng_cho_TP`

```sql
-- Đánh giá SPDD theo PP NVL trực tiếp
UPDATE wip_estimates
SET closing_material_cost = :totalMaterialInput * (:closingQty / (:completedQty + :closingQty))
WHERE id = :estimateId;
```

#### Method 2: Ước lượng sản phẩm tương đương (Equivalent Units)

DD cuối kỳ quy đổi thành sản phẩm tương đương theo % hoàn thành.

**Công thức:** `SL_TD = DD_CK × %HT_NVL + DD_CK × %HT_NC × ...`

```sql
-- Tính sản lượng tương đương
SET @equivalent_qty = :closingQty * :pctMaterialCompletion;
SET @unit_material_cost = (:openingMaterialValue + :inputMaterialValue) / (:completedQty + @equivalent_qty);
SET @closing_material = @equivalent_qty * @unit_material_cost;

-- Tương tự cho NC và SXC với % hoàn thành riêng
```

#### Method 3: Bán thành phẩm dở dang trên dây chuyền (Stage-by-stage)

DD cuối kỳ = tổng CP từng công đoạn tính đến công đoạn hiện tại. Phù hợp với multi-stage manufacturing.

```sql
-- Lấy DD cuối kỳ bằng cost đã ghi nhận trên 154 tại thời điểm cuối kỳ
SELECT
    product_id,
    SUM(total_material_cost + total_labor_cost + total_overhead_cost) AS wip_value
FROM production_order_cost_summary poc
JOIN production_orders po ON po.id = poc.production_order_id
WHERE po.status NOT IN ('completed', 'costed', 'closed')
  AND poc.period_year = :year
  AND poc.period_month = :month
GROUP BY product_id;
```

### 4.5 BOM Management

**Luồng xử lý BOM:**

```
1. Khai báo BOM:
   - SP X = NVL_A * 2 + NVL_B * 0.5 + BTP_Y * 1
   - Mỗi phiên bản BOM có version + effective_date

2. Khi tạo lệnh SX:
   - Sao chép BOM vào production_order_materials (snapshot tại thời điểm release)
   - planned_qty = bom_line.qty_per_unit × po.qty

3. Khi xuất NVL thực tế:
   - actual_qty = số lượng thực tế xuất kho
   - So sánh actual_qty vs planned_qty
   - Nếu chênh lệch > cost.warn_excess_pct (default 10%): cảnh báo

4. Version management:
   - Chỉ BOM active mới được dùng cho lệnh SX mới
   - Lệnh SX đã release giữ snapshot BOM cũ
   - Khi có BOM mới, lệnh SX cũ không bị ảnh hưởng
```

**BOM validation:**
```php
public function validateBomUsage(string $bomId, string $productId, float $qty): array
{
    $bom = $this->bomRepo->findById($bomId);
    if (!$bom || $bom->getStatus() !== 'active') {
        return ['valid' => false, 'error' => 'BOM không ở trạng thái active'];
    }
    if ($bom->getProductId() !== $productId) {
        return ['valid' => false, 'error' => 'BOM không thuộc sản phẩm này'];
    }
    if (strtotime($bom->getEffectiveDate()) > time()) {
        return ['valid' => false, 'error' => 'BOM chưa có hiệu lực'];
    }

    $lines = $this->bomLineRepo->findByBomId($bomId);
    $warnings = [];
    foreach ($lines as $line) {
        $item = $this->itemRepo->findById($line->getMaterialId());
        $required = $line->getQtyPerUnit() * $qty;
        $available = $item ? $item->getStockQty() : 0;
        if ($available < $required) {
            $warnings[] = "{$item->getName()}: cần {$required}, tồn {$available}";
        }
    }

    return ['valid' => true, 'warnings' => $warnings, 'lines' => $lines];
}
```

---

## 5. Cost Allocation Methods

### 5.1 Direct Material — actual usage

```
CP NVL từng SP = Σ (SL xuất thực tế × ĐG xuất từ inventory cost layer)
```

**Nguồn dữ liệu:** `production_order_materials.actual_qty × unit_cost`

### 5.2 Direct Labor — actual hours × rate

```
CP NC từng SP = Σ (Giờ công thực tế cho SP × Đơn giá giờ công)
```

```sql
-- Phân bổ CP NC theo giờ công thực tế
UPDATE production_order_labor
SET total_cost = actual_hours * hourly_rate
WHERE production_order_id = :poId;
```

### 5.3 Overhead — 3 allocation methods

| Method | allocation_base | Công thức | Khi nào dùng |
|---|---|---|---|
| Machine hours | Tổng giờ máy cho SP | CP = (Σ giờ máy của SP / Σ giờ máy) × Tổng SXC | SX cơ khí, gia công |
| Labor hours | Tổng giờ công cho SP | CP = (Σ giờ công của SP / Σ giờ công) × Tổng SXC | SX thủ công, lắp ráp |
| Material cost % | CP NVL trực tiếp | CP = CP NVL của SP × Tỷ lệ % | SX hóa chất, chế biến |

```php
public function allocateOverhead(string $periodYear, string $periodMonth, string $method): void
{
    $totalOverhead = $this->getTotalOverhead($periodYear, $periodMonth);

    $pdo = $this->pdo;
    $poStmt = $pdo->prepare("SELECT id, product_id FROM production_orders
        WHERE status IN ('completed','costed')
          AND YEAR(end_date) = ? AND MONTH(end_date) = ?");
    $poStmt->execute([$periodYear, $periodMonth]);
    $orders = $poStmt->fetchAll(\PDO::FETCH_ASSOC);

    $totalBase = 0;
    $baseData = [];

    foreach ($orders as $po) {
        $base = match ($method) {
            'machine_hours' => $this->getTotalMachineHours($po['id']),
            'labor_hours'   => $this->getTotalLaborHours($po['id']),
            'material_cost' => $this->getTotalMaterialCost($po['id']),
        };
        $totalBase += $base;
        $baseData[$po['id']] = $base;
    }

    foreach ($baseData as $poId => $base) {
        $allocated = $totalBase > 0 ? round($totalOverhead * $base / $totalBase, 2) : 0;
        $pdo->prepare("UPDATE production_order_cost_summary
            SET total_overhead_cost = ?
            WHERE production_order_id = ?")->execute([$allocated, $poId]);
    }
}
```

### 5.4 By-Product Cost Deduction

Sản phẩm phụ (by-product) được trừ khỏi tổng CP sản xuất:

```
CP chính = Tổng CP - Giá trị SP phụ ước tính
```

```sql
UPDATE production_order_cost_summary
SET total_cost = total_cost - :byProductValue
WHERE production_order_id = :poId;
```

---

## 6. Journal Entries

### 6.1 Xuất NVL cho sản xuất

```php
// InventoryService::issueGoods() đã hỗ trợ
$lines = [
    ['account_code' => '154', 'amount' => $totalNvlCost, 'is_debit' => true],
    ['account_code' => '152', 'amount' => $totalNvlCost, 'is_debit' => false],
];
```

### 6.2 Tiền lương nhân công trực tiếp

```php
$lines = [
    ['account_code' => '622', 'amount' => $totalLabor, 'is_debit' => true],
    ['account_code' => '334', 'amount' => $totalLabor, 'is_debit' => false],
];
// Hoặc chi tiết: Có 334 (lương thực lãnh) + Có 3383 (BHXH) + Có 3384 (BHYT) + Có 3386 (BHTN)
```

### 6.3 Chi phí sản xuất chung

```php
// Điện, nước:
$lines = [
    ['account_code' => '627', 'amount' => $elecCost, 'is_debit' => true],
    ['account_code' => '331', 'amount' => $elecCost, 'is_debit' => false],
];

// Khấu hao TSCĐ:
$lines = [
    ['account_code' => '627', 'amount' => $deprCost, 'is_debit' => true],
    ['account_code' => '214', 'amount' => $deprCost, 'is_debit' => false],
];

// Xuất CCDC cho SX:
$lines = [
    ['account_code' => '627', 'amount' => $ccdcCost, 'is_debit' => true],
    ['account_code' => '153', 'amount' => $ccdcCost, 'is_debit' => false],
];
```

### 6.4 Kết chuyển CP về 154 (cuối kỳ)

```php
// Kết chuyển: Nợ 154 / Có 621, 622, 627
$costService->transferCostToWip($periodYear, $periodMonth);

// Chi tiết:
$lines = [
    ['account_code' => '154', 'amount' => $totalCost, 'is_debit' => true],
    ['account_code' => '621', 'amount' => $materialCost, 'is_debit' => false],
    ['account_code' => '622', 'amount' => $laborCost, 'is_debit' => false],
    ['account_code' => '627', 'amount' => $overheadCost, 'is_debit' => false],
];
$this->journal->postEntry(
    "Cost transfer: period {$periodYear}-{$periodMonth}",
    "COST-{$periodYear}{$periodMonth}",
    $lines,
    $createdBy,
    true  // allowControl — 621/622/627 không phải control account
);
```

### 6.5 Nhập kho thành phẩm

```php
// InventoryService::receiveGoods() — nhưng cần override unit price bằng giá thành
$lines = [
    ['account_code' => '155', 'amount' => $fgCost, 'is_debit' => true],
    ['account_code' => '154', 'amount' => $fgCost, 'is_debit' => false],
];
```

### 6.6 Chuyển giai đoạn (multi-stage)

```php
// Nợ 154(công đoạn nhận) / Có 154(công đoạn chuyển)
$lines = [
    ['account_code' => '154', 'amount' => $semiCost, 'is_debit' => true],
    ['account_code' => '154', 'amount' => $semiCost, 'is_debit' => false],
];
// Ghi chú: cùng TK 154 nhưng qua work_center khác nhau
// Hạch toán nội bảng — không ảnh hưởng BC02
```

### 6.7 Cost variance (chênh lệch giá thành) — Nợ/Có 632

Khi giá thành thực tế khác giá tạm tính đã ghi nhận:

```php
if ($actualCost > $tempCost) {
    // Thiếu: ghi bổ sung Nợ 632 / Có 154
    $lines = [
        ['account_code' => '632', 'amount' => $variance, 'is_debit' => true],
        ['account_code' => '154', 'amount' => $variance, 'is_debit' => false],
    ];
} else {
    // Thừa: hoàn nhập Nợ 154 / Có 632
    $lines = [
        ['account_code' => '154', 'amount' => abs($variance), 'is_debit' => true],
        ['account_code' => '632', 'amount' => abs($variance), 'is_debit' => false],
    ];
}
```

---

## 7. Business Rules & Validation

| # | Rule | Severity | Check | SQL/Code |
|---|---|---|---|---|
| CR01 | Dr = Cr cho mọi bút toán giá thành | REQUIRED | JournalService đã enforce | `abs(totalDr - totalCr) <= 10` |
| CR02 | Không post vào control account | REQUIRED | 154 KHÔNG phải control account (thực tế) | `$account->isControl()` |
| CR03 | Kỳ kế toán phải mở | REQUIRED | PeriodService::isPeriodOpen() | Trước mọi post |
| CR04 | Tổng phân bổ = Tổng CPSXC thực tế | REQUIRED | allocation_total == actual_overhead | `SUM(production_order_overhead.total_cost) = SUM(ledger_entries WHERE account_code = '627')` |
| CR05 | Số lượng DD đầu kỳ = số lượng DD cuối kỳ trước | REQUIRED | WIP period chain | `wip_estimates[month] = wip_estimates[month-1]` |
| CR06 | completed_qty ≤ qty | REQUIRED | Trên PO | `IF completed_qty > qty THEN throw` |
| CR07 | Cảnh báo xuất NVL vượt định mức | WARN | actual_qty > planned_qty × (1 + warn_pct) | `cost.warn_excess_pct` từ ConfigService |
| CR08 | Không thể cancel PO đã phát sinh giao dịch | BLOCK | Tồn tại production_order_materials | `COUNT(production_order_materials) = 0 WHEN cancel` |
| CR09 | Mỗi sản phẩm chỉ có 1 cost_period_run đang running | BLOCK | Concurrent run check | `status = 'running'` |
| CR10 | Đơn giá thành phẩm ≥ 0 | REQUIRED | unit_cost > 0 | `IF unit_cost <= 0 THEN warn` |
| CR11 | Tỷ lệ phân bổ SXC phải được cấu hình trước khi chạy cost | REQUIRED | ConfigService key tồn tại | `cost.overhead_method` + `cost.overhead_rate.*` |
| CR12 | BOM chỉ active khi có effective_date ≤ ngày hiện tại | REQUIRED | BOM release | `effective_date <= CURDATE()` |
| CR13 | Multi-stage: giá trị BTP chuyển = giá trị đã tính tại công đoạn trước | REQUIRED | Semi-finished transfer | `total_cost(sending_po) = total_cost(receiving_po)` |
| CR14 | Chạy cost nhiều lần cho cùng kỳ phải idempotent | REQUIRED | Upsert, không double post | `ON DUPLICATE KEY UPDATE` + kiểm tra journal |
| CR15 | Không xóa production_orders — chỉ soft cancel | REQUIRED | Audit trail | Status = 'cancelled', ghi audit_log |

### 7.1 ConfigService Keys

| Key | Type | Default | Description |
|---|---|---|---|
| `cost.warn_excess_pct` | percent | 10 | % vượt định mức NVL trước khi cảnh báo |
| `cost.overhead_method` | string | labor_hours | Phương pháp phân bổ SXC mặc định |
| `cost.overhead_rate.machine_hour` | decimal | 0 | Tỷ lệ CP/giờ máy (nếu method = machine_hours) |
| `cost.overhead_rate.labor_hour` | decimal | 0 | Tỷ lệ CP/giờ công (nếu method = labor_hours) |
| `cost.overhead_rate.material_pct` | percent | 0 | % CP NVL (nếu method = material_cost) |
| `cost.wip_default_method` | string | raw_material_only | Phương pháp đánh giá SPDD mặc định |
| `cost.labor_rate.default` | decimal | 0 | Đơn giá giờ công mặc định |

---

## 8. Integration Contracts

### 8.1 Với InventoryService

```
Gọi:
  issueGoods(itemId, qty, 'production', reference, user)
      → Nợ 154 / Có 152/153 (Xuất NVL cho SX)
  receiveGoods(itemId, qty, unitCost, [], reference, user)
      → Nợ 155 / Có 331 (Nhập kho thành phẩm — nhưng cần override Cr 154)

Contract:
  - type = 'production' → issueType mapping sẵn có
  - CostCalculationService cập nhật unit_cost của thành phẩm sau khi chạy
```

### 8.2 Với JournalService

```
Gọi:
  postEntry(description, reference, lines, user, allowControl, module='cost')

Contract:
  - allowControl = true (154 không control account, 621/622/627 cũng không)
  - module = 'cost' → posting rules cho nghiệp vụ giá thành
  - Phải chạy trong DB transaction hiện có (không tự beginTransaction)

Posting rules cần bổ sung:
  (621, 154) → pass  (kết chuyển NVL)
  (622, 154) → pass  (kết chuyển NC)
  (627, 154) → pass  (kết chuyển SXC)
  (154, 155) → pass  (nhập kho thành phẩm)
  (154, 632) → pass  (điều chỉnh giá vốn)
```

### 8.3 Với PeriodService

```
Tích hợp vào canClose():
  Check 12: Tồn tại production_orders.status IN ('in_progress', 'released')
            → CẢNH BÁO (không chặn): còn lệnh SX chưa hoàn thành
  Check 13: Chưa chạy cost_period_runs cho kỳ này
            → CẢNH BÁO (không chặn): chưa tính giá thành

Tích hợp vào closePeriod():
  Step 0.5: Tự động chạy CostService::runPeriodCost()
            Nếu chưa chạy và cấu hình cost.auto_run = true
```

### 8.4 Với FsService

```
BC02 chỉ tiêu 24 (Giá vốn hàng bán - TK 632):
  - Cost variance adjustment ảnh hưởng trực tiếp TK 632
  - Điều chỉnh giá thành làm thay đổi giá vốn thành phẩm đã bán

Product Cost History:
  - Cung cấp dữ liệu cho BC09 (Báo cáo giá thành sản phẩm — Mẫu 06-TT99)
```

### 8.5 Với ConfigService

```php
// Đọc phương pháp phân bổ
$method = $config->getString('cost.overhead_method', 'labor_hours');
$warnPct = $config->getPercent('cost.warn_excess_pct', 10);
$wipMethod = $config->getString('cost.wip_default_method', 'raw_material_only');
```

---

## 9. API Endpoints (RESTful)

### 9.1 Master Data

| Method | Endpoint | Controller | Description |
|---|---|---|---|
| GET | /api/work-centers | ProductionOrderController | Danh sách trung tâm SX |
| POST | /api/work-centers | ProductionOrderController | Tạo mới |
| GET | /api/bom | BomController | Danh sách BOM |
| POST | /api/bom | BomController | Tạo BOM |
| PUT | /api/bom/:id | BomController | Cập nhật |
| POST | /api/bom/:id/activate | BomController | Kích hoạt BOM |
| POST | /api/bom/:id/new-version | BomController | Tạo phiên bản mới |
| GET | /api/bom/:id/lines | BomController | Xem định mức chi tiết |

### 9.2 Production Orders

| Method | Endpoint | Description |
|---|---|---|
| GET | /api/production-orders | Danh sách (filter: status, product, date range) |
| POST | /api/production-orders | Tạo mới (tự động snapshot BOM) |
| GET | /api/production-orders/:id | Chi tiết (kèm materials, labor, overhead) |
| PUT | /api/production-orders/:id | Cập nhật (chỉ khi draft) |
| POST | /api/production-orders/:id/release | Phát lệnh SX |
| POST | /api/production-orders/:id/start | Bắt đầu SX |
| POST | /api/production-orders/:id/complete | Hoàn thành SX |
| POST | /api/production-orders/:id/cancel | Hủy lệnh SX |
| POST | /api/production-orders/:id/cost | Tính giá cho lệnh SX (manual) |

### 9.3 Cost Calculation

| Method | Endpoint | Description |
|---|---|---|
| POST | /api/cost/run-period | Chạy tính giá thành kỳ (FAST 6-step) |
| GET | /api/cost/period-runs | Lịch sử chạy |
| GET | /api/cost/period-runs/:id/result | Kết quả chi tiết |
| GET | /api/cost/product-history | Lịch sử giá thành SP |
| GET | /api/cost/report | Báo cáo giá thành tổng hợp |
| POST | /api/wip/evaluate | Đánh giá SPDD (với method param) |
| GET | /api/wip/estimates/:period | Xem kết quả đánh giá SPDD |

### 9.4 Reports

| Method | Endpoint | Description |
|---|---|---|
| GET | /api/cost/report/cost-sheet | Bảng tính giá thành (Mẫu 06-TT99) |
| GET | /api/cost/report/variance | Báo cáo chênh lệch định mức/thực tế |
| GET | /api/cost/report/wip | Tình hình SPDD |
| GET | /api/cost/export/csv | Xuất CSV bảng tính giá thành |

---

## 10. UI/UX Flow

### 10.1 Màn hình chính

```
┌─────────────────────────────────────────────────────────────────────┐
│  Bookwise — Giá thành sản phẩm                                [..] │
├─────────────────────────────────────────────────────────────────────┤
│  ┌─ Quy trình cuối kỳ ──────────────────────────────────────────┐  │
│  │  ○ Bước 1: Đánh giá SPDD              [Chạy] [Xem kết quả]   │  │
│  │  ○ Bước 2: Tập hợp & phân bổ CP NVL   [Chạy] [Xem kết quả]   │  │
│  │  ○ Bước 3: Phân bổ CP NC              [Chạy] [Xem kết quả]   │  │
│  │  ○ Bước 4: Phân bổ CPSXC              [Chạy] [Xem kết quả]   │  │
│  │  ○ Bước 5: Tính giá thành đơn vị      [Chạy] [Xem kết quả]   │  │
│  │  ○ Bước 6: Cập nhật giá tồn kho       [Chạy] [Xem kết quả]   │  │
│  │  ○ Kết chuyển: Nợ 154 / Có 621,622,627 [Tạo bút toán]        │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ┌─ Kết quả tính giá tháng 06/2026 ─────────────────────────────┐  │
│  │  SP      | SL | CP NVL | CP NC | CPSXC | Tổng | Đơn giá      │  │
│  │  ─────────────────────────────────────────────────────────    │  │
│  │  SP-A    | 100| 50.0M  | 20.0M | 10.0M | 80M  | 800,000     │  │
│  │  SP-B    | 200| 80.0M  | 30.0M | 15.0M | 125M | 625,000     │  │
│  │  ─────────────────────────────────────────────────────────    │  │
│  │  Tổng    | 300| 130.0M | 50.0M | 25.0M | 205M |             │  │
│  └───────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ┌─ BOM (Định mức) ───────┐   ┌─ Lệnh SX ───────────────────────┐  │
│  │ [Quản lý BOM] [Thêm mới]│   │ [DS Lệnh SX] [Tạo mới]          │  │
│  │ ────────────────────────│   │ ──────────────────────────────── │  │
│  │ SP-A (v2, active)       │   │ PO2026-000001 → SP-A: Hoàn thành │  │
│  │   └ NVL-01 x2           │   │ PO2026-000002 → SP-B: Đang SX   │  │
│  │   └ NVL-03 x0.5         │   │ PO2026-000003 → SP-A: Nháp      │  │
│  │ SP-B (v1, active)       │   │                                  │  │
│  │   └ NVL-02 x1           │   │                                  │  │
│  └─────────────────────────┘   └──────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

### 10.2 Wizard tính giá thành

1. **Step 1 — Chọn kỳ:** Chọn tháng/năm cần tính giá
2. **Step 2 — Chọn phương pháp SPDD:** Raw material only / Equivalent units / Stage-by-stage
3. **Step 3 — Chọn phương pháp phân bổ SXC:** Machine hours / Labor hours / Material %
4. **Step 4 — Nhập % hoàn thành SPDD** (nếu chọn equivalent units)
5. **Step 5 — Nhập giá trị SP phụ** (nếu có)
6. **Step 6 — Xác nhận & chạy**

### 10.3 Trạng thái cost run (visual indicator)

```
🟢 Running    — Đang chạy (progress bar từng bước)
🟢 Completed  — Thành công, có thể xem kết quả
🔴 Failed     — Thất bại (hiển thị error_log + nút retry từ bước lỗi)
🔵 Rolled back— Đã rollback (chỉ Kế toán trưởng)
```

---

## 11. Implementation Checklist (Phased)

### Phase 1 — Single-Stage Costing (Ngày 1-7)

**Mục tiêu:** Tính giá thành đơn giản cho sản xuất 1 công đoạn, 1 loại SP.

```
[ ] 1. Model & Migration (all tables in §3)
[ ] 2. WorkCenterRepository + Controller (CRUD)
[ ] 3. ProductionOrderService (vòng đời draft→released→in_progress→completed)
[ ] 4. ProductionOrderController (CRUD + state transitions)
[ ] 5. ProductionOrderMaterialService (xuất NVL gắn với PO)
[ ] 6. CostAllocationService — FAST steps 1-4 (tập hợp CP)
[ ] 7. WipEvaluationService — Method 1 (raw material only) + Method 2 (equivalent)
[ ] 8. CostCalculationService — FAST steps 5-6 (tính giá + cập nhật)
[ ] 9. CostService — Facade cho quy trình tính giá
[ ] 10. Kết chuyển: Nợ 154 / Có 621,622,627 (bút toán cuối kỳ)
[ ] 11. CostPeriodRunRepository (lịch sử + idempotent)
[ ] 12. POST /api/cost/run-period endpoint
[ ] 13. Màn hình wizard tính giá thành (Step 1-6)
[ ] 14. Test: cost calculation (happy path + failure: incomplete data)
[ ] 15. Test: production order lifecycle (all transitions)
[ ] 16. Test: WIP evaluation (3 methods)
[ ] 17. Test: trial balance sau cost run (Dr=Cr)
```

### Phase 2 — Multi-Stage Manufacturing (Ngày 8-12)

**Mục tiêu:** Sản xuất nhiều công đoạn, chuyển BTP giữa các giai đoạn.

```
[ ] 1. Semi-finished transfer (Nợ 154/v2 / Có 154/v1)
[ ] 2. Multi-stage allocation engine (N vòng, mỗi vòng FAST steps 2-6)
[ ] 3. WipEvaluationService — Method 3 (stage-by-stage)
[ ] 4. Cost traceability: xem chi phí tích lũy qua N công đoạn
[ ] 5. By-product cost deduction
[ ] 6. Cost variance posting (Nợ/Có 632)
[ ] 7. Test: multi-stage cost flow (2 stages, 3 stages)
[ ] 8. Test: by-product deduction
```

### Phase 3 — BOM & Standard Costing (Ngày 13-18)

**Mục tiêu:** Định mức NVL, so sánh thực tế vs định mức, phân tích chênh lệch.

```
[ ] 1. BOMService (version management, effective_date, status)
[ ] 2. BOMController + CRUD UI
[ ] 3. Tự động snapshot BOM vào production_order_materials khi release
[ ] 4. Cảnh báo vượt định mức (cost.warn_excess_pct)
[ ] 5. BOM comparison report (planned vs actual)
[ ] 6. Standard cost vs actual cost variance analysis
[ ] 7. Cost sheet report (Mẫu 06-TT99)
[ ] 8. WIP report
[ ] 9. Product cost history view
[ ] 10. Export CSV cost report
[ ] 11. Tích hợp vào PeriodService::canClose()
[ ] 12. Test: BOM validation + warning
[ ] 13. Test: period close integration
[ ] 14. Audit trail: all cost operations logged
```

---

## 12. Effort Estimate

| Phase | Component | Ngày | Kỹ năng chính |
|---|---|---|---|
| **1** | Data model + migrations | 1.0 | SQL, migration pattern |
| **1** | WorkCenter + PO services | 1.5 | PHP, vòng đời state machine |
| **1** | Cost allocation (steps 1-4) | 1.5 | PHP, SQL aggregation |
| **1** | WIP evaluation (2 methods) | 1.0 | Toán kế toán, PHP |
| **1** | Cost calc + update (steps 5-6) | 1.0 | PHP, JournalService |
| **1** | API endpoints + controller | 0.5 | REST, JSON |
| **1** | UI wizard | 1.0 | Bootstrap, jQuery |
| **1** | Tests | 1.0 | PHP, assert helpers |
| | **Phase 1 total** | **8.5** | |
| **2** | Multi-stage engine | 2.0 | PHP, recursive allocation |
| **2** | By-product + variance | 1.0 | Kế toán, JournalService |
| **2** | Tests | 1.0 | |
| | **Phase 2 total** | **4.0** | |
| **3** | BOM service + controller | 1.5 | PHP, version management |
| **3** | BOM comparison + warning | 1.0 | ConfigService, alerts |
| **3** | Reports (cost sheet, WIP) | 1.5 | SQL, CSV export |
| **3** | Period close integration | 0.5 | PeriodService hook |
| **3** | Tests | 1.0 | |
| | **Phase 3 total** | **5.5** | |
| | **Tổng (với buffer 20%)** | **~18 ngày** | |

### 12.1 Risk Factors

| Risk | Impact | Mitigation |
|---|---|---|
| Posting rules chưa có cho nghiệp vụ cost | Block Phase 1 | Seed rules ngay từ migration đầu |
| WIP evaluation sai logic | Sai BC01/BC02 | Test với 3 phương pháp, so sánh kết quả |
| Multi-stage cost không idempotent | Double posting | CostPeriodRun kiểm tra status trước khi chạy |
| BOM version không tương thích với PO cũ | Mất snapshot | Snapshot BOM vào PO lúc release |
| Performance với hàng ngàn PO/tháng | Chậm | Index + batch processing trong cost run |

---

> **Ghi chú cuối:** Module giá thành là yêu cầu P0 cho ~25% doanh nghiệp sản xuất Việt Nam. FAST và BRAVO đã có giải pháp hoàn chỉnh. Phase 1 (single-stage) là đủ dùng cho SMEs sản xuất đơn giản. Phase 2-3 là yêu cầu từ doanh nghiệp sản xuất phức tạp (dệt may, cơ khí, lắp ráp).
