# Gap 07: Custom Report Builder — Parity Specification

> **Trạng thái:** Draft  
> **Priority:** HIGH (competitive differentiator)  
> **Mức hiện tại:** 0/10 — **Mục tiêu:** 8/10  
> **Effort:** 5-7 ngày  
> **Module:** ReportBuilder  
> **Phạm vi:** Custom Report Builder + Dashboard  
> **Tài liệu tham chiếu:** `10-gaps-use-cases-consolidated.md` Gap 7, `AGENTS.md §3`, `GlService.php`, `FsService.php`, `ReportExportService.php`

---

## 1. Business Context & Rationale

### 1.1 Why This Matters

Bookwise hiện chỉ có báo cáo cố định (BC01/BC02/BC03, sổ cái, bảng cân đối tài khoản). Kế toán viên không thể tạo báo cáo tùy chỉnh — mỗi yêu cầu mới phải chờ developer viết code + migration. Một kế toán trưởng cần xem "chi phí QLDN theo tháng theo từng dự án" phải chờ ít nhất 2 ngày.

Custom report builder giải quyết:
- Kế toán tự tạo báo cáo không cần code
- Doanh nghiệp có nhu cầu báo cáo riêng (theo ngành, theo nội bộ)
- Giảm tải cho developer
- Cạnh tranh với MISA/FAST (cả hai đều có "báo cáo tự tạo")

### 1.2 Competitive Landscape

| Tính năng | MISA AMIS | FAST | EasyBooks | **Bookwise (target)** |
|---|---|---|---|---|
| Báo cáo tự tạo (wizard) | ✅ 3 bước | ✅ Mạnh | ❌ | ✅ 4 bước |
| Pivot table | ✅ | ✅ | ❌ | ✅ |
| Biểu đồ | ✅ Cột/tròn/đường | ✅ | ❌ | ✅ |
| Dashboard tổng quan | ✅ | ✅ | ❌ | ✅ |
| Chia sẻ theo role | ✅ | ✅ | ❌ | ✅ |
| Export CSV/Excel | ✅ | ✅ | ✅ | ✅ |
| Lên lịch gửi email | ✅ | ✅ | ❌ | ✅ |
| Tham số đầu vào (kỳ, KH...) | ✅ | ✅ | ❌ | ✅ |
| Data source tự định nghĩa | ❌ (fixed) | ✅ SQL | ❌ | ✅ 6 sources |
| **Hiện tại Bookwise** | — | — | — | **0/10** |

### 1.3 User Personas

| Persona | Nhu cầu | Frequency |
|---|---|---|
| Kế toán trưởng | BC đối chiếu chi phí theo phòng ban, dự án | Hàng tuần |
| Kế toán kho | BC xuất/nhập tồn theo mặt hàng, kho | Hàng ngày |
| Kế toán công nợ | BC phân tích tuổi nợ theo nhóm KH | Hàng tháng |
| Giám đốc | Dashboard tổng quan doanh thu, chi phí | Hàng ngày |
| Kiểm toán nội bộ | BC trace nghiệp vụ bất thường | Đột xuất |

---

## 2. Architecture

### 2.1 Three-Layer Design

```
┌──────────────────────────────────────────────────────────────┐
│                   RENDERER LAYER                             │
│  HTML Table │ Chart.js (line/bar/pie) │ CSV Export │ PDF    │
│  PivotTable.js (pivot) │ Dashboard grid (Bootstrap 5 cards) │
└──────────────────────┬───────────────────────────────────────┘
                       ↓ (rows: array)
┌──────────────────────┴───────────────────────────────────────┐
│                  QUERY BUILDER LAYER                         │
│  SafeQueryBuilder — whitelist-based SQL generation           │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ $qb = new SafeQueryBuilder($pdo);                       ││
│  │ $rows = $qb->from('transactions')                      ││
│  │     ->select(['t.reference', 't.description', ...])     ││
│  │     ->where(['date_range' => [$from, $to]])             ││
│  │     ->groupBy(['DATE_FORMAT(t.date, "%Y-%m")'])        ││
│  │     ->orderBy(['date DESC'])                            ││
│  │     ->limit(10000)                                      ││
│  │     ->run();                                            ││
│  └─────────────────────────────────────────────────────────┘│
│  Security: whitelist only — no raw SQL passed through        │
└──────────────────────┬───────────────────────────────────────┘
                       ↓ (JSON config)
┌──────────────────────┴───────────────────────────────────────┐
│                  CONFIG LAYER                                 │
│  custom_reports table — JSON report definition                │
│  { columns, groups, filters, sort, chart_type, parameters }   │
│  CRUD via ReportBuilderController                             │
└──────────────────────────────────────────────────────────────┘
```

### 2.2 Security: Whitelist Approach

Đây là lớp bảo vệ quan trọng nhất. Mọi SQL động được sinh ra từ cấu hình phải qua whitelist. Không cho phép raw SQL từ user input.

```php
class SafeQueryBuilder
{
    private array $allowedTables = [
        'transactions' => [
            'join' => 'transactions t',
            'columns' => ['t.id', 't.reference', 't.description', 't.transaction_date',
                't.created_at', 't.status', 't.created_by', 't.module'],
        ],
        'ledger_entries' => [
            'join' => 'ledger_entries le',
            'columns' => ['le.id', 'le.transaction_id', 'le.account_id', 'le.amount',
                'le.is_debit', 'le.customer_id', 'le.supplier_id', 'le.employee_id',
                'le.project_id'],
        ],
        'items' => [
            'join' => 'items i',
            'columns' => ['i.id', 'i.code', 'i.name', 'i.category', 'i.unit',
                'i.opening_stock', 'i.current_stock', 'i.min_stock'],
        ],
        'customers' => [
            'join' => 'customers c',
            'columns' => ['c.id', 'c.code', 'c.name', 'c.tax_code', 'c.phone',
                'c.group_id', 'c.is_active'],
        ],
        'suppliers' => [
            'join' => 'suppliers s',
            'columns' => ['s.id', 's.code', 's.name', 's.tax_code', 's.phone',
                's.group_id', 's.is_active'],
        ],
        'accounts' => [
            'join' => 'accounts a',
            'columns' => ['a.id', 'a.code', 'a.name', 'a.type', 'a.balance',
                'a.is_control', 'a.parent_id'],
        ],
    ];

    // Pre-defined joins between data sources — kế toán viên chọn source,
    // hệ thống tự động JOIN bảng phụ. Không cho phép JOIN tùy ý.
    private array $dataSources = [
        'transactions_detail' => [
            'base' => 'transactions',
            'joins' => [
                'LEFT JOIN ledger_entries le ON le.transaction_id = t.id',
                'LEFT JOIN accounts a ON a.id = le.account_id',
                'LEFT JOIN customers c ON c.id = le.customer_id',
                'LEFT JOIN suppliers s ON s.id = le.supplier_id',
                'LEFT JOIN projects p ON p.id = le.project_id',
            ],
            'columns' => [
                't.id', 't.reference', 't.description', 't.transaction_date',
                't.status', 't.module', 't.created_by',
                'a.code AS account_code', 'a.name AS account_name',
                'le.amount', 'le.is_debit',
                'c.code AS customer_code', 'c.name AS customer_name',
                's.code AS supplier_code', 's.name AS supplier_name',
                'p.code AS project_code', 'p.name AS project_name',
            ],
        ],
        'ledger_account' => [
            'base' => 'ledger_entries',
            'joins' => [
                'JOIN accounts a ON a.id = le.account_id',
                'JOIN transactions t ON t.id = le.transaction_id',
                'LEFT JOIN customers c ON c.id = le.customer_id',
                'LEFT JOIN suppliers s ON s.id = le.supplier_id',
                'LEFT JOIN projects p ON p.id = le.project_id',
            ],
            'columns' => [
                'a.code AS account_code', 'a.name AS account_name',
                'DATE_FORMAT(t.transaction_date, "%Y-%m") AS period',
                'SUM(CASE WHEN le.is_debit = 1 THEN le.amount ELSE 0 END) AS total_debit',
                'SUM(CASE WHEN le.is_debit = 0 THEN le.amount ELSE 0 END) AS total_credit',
                'SUM(CASE WHEN le.is_debit = 1 THEN le.amount ELSE -le.amount END) AS net_amount',
            ],
        ],
        'inventory' => [
            'base' => 'items',
            'joins' => [
                'LEFT JOIN warehouse_stock ws ON ws.item_id = i.id',
                'LEFT JOIN warehouses w ON w.id = ws.warehouse_id',
            ],
            'columns' => [
                'i.code AS item_code', 'i.name AS item_name', 'i.category',
                'i.unit', 'i.current_stock', 'i.opening_stock',
                'w.name AS warehouse_name',
                'ws.quantity', 'ws.cost_value',
            ],
        ],
        'ar' => [
            'base' => 'ar_invoices',
            'joins' => [
                'JOIN customers c ON c.id = ar_invoices.customer_id',
                'LEFT JOIN ar_transactions art ON art.ar_invoice_id = ar_invoices.id',
            ],
            'columns' => [
                'ar_invoices.id', 'ar_invoices.invoice_no', 'ar_invoices.invoice_date',
                'ar_invoices.total_amount', 'ar_invoices.balance_due',
                'ar_invoices.due_date', 'ar_invoices.status',
                'c.code AS customer_code', 'c.name AS customer_name',
            ],
        ],
        'ap' => [
            'base' => 'ap_invoices',
            'joins' => [
                'JOIN suppliers s ON s.id = ap_invoices.supplier_id',
                'LEFT JOIN ap_transactions apt ON apt.ap_invoice_id = ap_invoices.id',
            ],
            'columns' => [
                'ap_invoices.id', 'ap_invoices.invoice_no', 'ap_invoices.invoice_date',
                'ap_invoices.total_amount', 'ap_invoices.balance_due',
                'ap_invoices.due_date', 'ap_invoices.status',
                's.code AS supplier_code', 's.name AS supplier_name',
            ],
        ],
        'tax' => [
            'base' => 'vat_declarations',
            'joins' => [],
            'columns' => [
                'id', 'period_code', 'declaration_type',
                'total_output_vat', 'total_input_vat', 'vat_payable',
                'status', 'created_at',
            ],
        ],
    ];

    private array $allowedAggregates = ['SUM', 'AVG', 'COUNT', 'MIN', 'MAX', 'COUNT_DISTINCT'];
    private array $allowedOperators = ['=', '!=', '<', '>', '<=', '>=', 'IN', 'NOT IN', 'BETWEEN', 'LIKE'];
    private array $allowedSortDirections = ['ASC', 'DESC'];
    private array $dateFunctions = [
        'YEAR', 'MONTH', 'QUARTER', 'DATE_FORMAT', 'WEEK', 'DAY',
    ];

    private \PDO $pdo;
    private int $maxRows = 10000;
    private int $timeoutSec = 30;

    public function __construct(\PDO $pdo) { $this->pdo = $pdo; }

    /**
     * Build and execute a report query from a validated config.
     *
     * @param string $dataSource  Key from $this->dataSources
     * @param array  $columns     Column aliases from data source definition
     * @param array  $filters     [column => [operator, value]] or [column => value]
     * @param array  $groups      Column aliases for GROUP BY
     * @param array  $sorts       [column => direction]
     * @param array  $params      Runtime parameter values (period_id, etc.)
     * @return array  ['rows' => [...], 'total' => int, 'sql' => string (debug)]
     */
    public function build(
        string $dataSource,
        array $columns,
        array $filters = [],
        array $groups = [],
        array $sorts = [],
        array $params = []
    ): array {
        // 1. Validate data source exists
        if (!isset($this->dataSources[$dataSource])) {
            throw new \InvalidArgumentException("Nguồn dữ liệu không hợp lệ: {$dataSource}");
        }
        $source = $this->dataSources[$dataSource];

        // 2. Validate columns — chỉ cho phép cột đã định nghĩa
        $validCols = array_fill_keys($source['columns'], true);
        $selectCols = [];
        foreach ($columns as $col) {
            if (!isset($validCols[$col])) {
                throw new \InvalidArgumentException("Cột không được phép: {$col}");
            }
            $selectCols[] = $col;
        }
        if (empty($selectCols)) {
            $selectCols = $source['columns'];
        }
        $selectSql = implode(', ', $selectCols);

        // 3. Validate groups — must be in columns or valid date functions
        $groupSql = '';
        if (!empty($groups)) {
            $groupParts = [];
            foreach ($groups as $g) {
                // Allow DATE_FORMAT(col, fmt) patterns
                if (preg_match('/^(DATE_FORMAT|YEAR|MONTH|QUARTER)\((.+)\)$/i', trim($g), $m)) {
                    $func = strtoupper($m[1]);
                    if (!in_array($func, $this->dateFunctions, true)) {
                        throw new \InvalidArgumentException("Hàm ngày không được phép: {$func}");
                    }
                    $groupParts[] = $g;
                } elseif (isset($validCols[$g])) {
                    $groupParts[] = $g;
                } else {
                    throw new \InvalidArgumentException("Cột nhóm không được phép: {$g}");
                }
            }
            $groupSql = ' GROUP BY ' . implode(', ', $groupParts);
        }

        // 4. Build WHERE from filters
        $whereParts = [];
        $bindings = [];
        foreach ($filters as $column => $filter) {
            if (!isset($validCols[$column])) {
                throw new \InvalidArgumentException("Cột lọc không được phép: {$column}");
            }
            // $filter = [operator, value] or just value (default '=')
            $operator = '=';
            $value = $filter;
            if (is_array($filter)) {
                $operator = strtoupper($filter[0]);
                $value = $filter[1];
            }
            if (!in_array($operator, $this->allowedOperators, true)) {
                throw new \InvalidArgumentException("Toán tử lọc không hợp lệ: {$operator}");
            }
            // Runtime parameter substitution: {param_name}
            if (is_string($value) && str_starts_with($value, '{') && str_ends_with($value, '}')) {
                $paramName = trim($value, '{}');
                $value = $params[$paramName] ?? $value;
            }
            if (in_array($operator, ['IN', 'NOT IN'], true)) {
                if (!is_array($value)) $value = [$value];
                $placeholders = implode(', ', array_fill(0, count($value), '?'));
                $whereParts[] = "{$column} {$operator} ({$placeholders})";
                $bindings = array_merge($bindings, $value);
            } elseif ($operator === 'BETWEEN') {
                if (!is_array($value) || count($value) !== 2) {
                    throw new \InvalidArgumentException('BETWEEN cần 2 giá trị');
                }
                $whereParts[] = "{$column} BETWEEN ? AND ?";
                $bindings[] = $value[0];
                $bindings[] = $value[1];
            } elseif ($operator === 'LIKE') {
                $whereParts[] = "{$column} LIKE ?";
                $bindings[] = $value;
            } else {
                $whereParts[] = "{$column} {$operator} ?";
                $bindings[] = $value;
            }
        }
        $whereSql = '';
        if (!empty($whereParts)) {
            $whereSql = ' WHERE ' . implode(' AND ', $whereParts);
        }

        // 5. Build ORDER BY
        $orderSql = '';
        if (!empty($sorts)) {
            $orderParts = [];
            foreach ($sorts as $col => $dir) {
                if (!isset($validCols[$col])) {
                    throw new \InvalidArgumentException("Cột sắp xếp không được phép: {$col}");
                }
                $dir = strtoupper($dir);
                if (!in_array($dir, $this->allowedSortDirections, true)) {
                    $dir = 'ASC';
                }
                $orderParts[] = "{$col} {$dir}";
            }
            $orderSql = ' ORDER BY ' . implode(', ', $orderParts);
        }

        // 6. Assemble SQL
        $fromSql = $source['base'] . ' t ' . implode(' ', $source['joins']);
        $sql = "SELECT {$selectSql} FROM {$fromSql}{$whereSql}{$groupSql}{$orderSql} LIMIT {$this->maxRows}";

        // 7. Execute with timeout protection
        $this->pdo->exec("SET SESSION MAX_EXECUTION_TIME={$this->timeoutSec}000");
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'rows' => $rows,
            'total' => count($rows),
            'sql' => $sql, // Debug only — không expose ra frontend production
        ];
    }
}
```

### 2.3 Rendering Pipeline

```
SafeQueryBuilder → rows (array of assoc)
    ↓
ReportRenderer
    ├── renderTable(array $rows, array $config): string  — HTML table với thead/tbody
    ├── renderChart(array $rows, array $config): string  — Chart.js config JSON
    ├── renderPivot(array $rows, array $config): string  — Pivot table data
    └── renderCsv(array $rows, array $headers): array    — CSV export content
```

### 2.4 Service Dependencies

```
ReportBuilderService
├── SafeQueryBuilder          (build SQL từ config)
├── ReportRenderer            (render HTML/Chart/CSV)
├── ReportRepositoryInterface (CRUD custom_reports)
├── PDO (read-only connection hoặc transaction cho save)
└── Auth (permissions — chỉ owner/admin mới sửa/xóa)
```

---

## 3. Data Model

### 3.1 custom_reports

```sql
-- NGHIỆP VỤ: Lưu trữ báo cáo tùy chỉnh do kế toán tạo
-- config JSON: chứa columns, filters, groups, sort, chart_type
-- parameters JSON: chứa mảng {name, label, type, default_value}
--   type: 'period', 'account', 'customer', 'supplier', 'project', 'date', 'text'
-- shared_with JSON: [{type: 'role'|'user', id: string}]
--   type=role: tất cả user trong role đó được xem
--   type=user: user cụ thể được xem
-- THỜI GIAN CHẠY: query_timeout giới hạn thời gian SQL chạy
CREATE TABLE IF NOT EXISTS custom_reports (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    data_source VARCHAR(50) NOT NULL COMMENT 'transactions_detail|ledger_account|inventory|ar|ap|tax',
    report_type ENUM('list', 'summary', 'pivot', 'chart', 'dashboard') NOT NULL DEFAULT 'list',
    config JSON NOT NULL COMMENT '{"columns":[...],"groups":[...],"filters":[...],"sort":{...},"chart_type":"bar"}',
    parameters JSON COMMENT '[{"name":"period_id","label":"Kỳ kế toán","type":"period","default_value":""}]',
    chart_type ENUM('none', 'bar', 'line', 'pie', 'doughnut', 'stacked_bar', 'horizontal_bar') DEFAULT 'none',
    max_rows INT UNSIGNED DEFAULT 10000,
    query_timeout INT UNSIGNED DEFAULT 30,
    shared_with JSON COMMENT '[{"type":"role","id":"ke_toan"},{"type":"user","id":"u123"}]',
    is_dashboard TINYINT(1) DEFAULT 0 COMMENT 'Xuất hiện trên Dashboard tổng quan',
    dashboard_position INT UNSIGNED DEFAULT 0 COMMENT 'Vị trí sắp xếp trên Dashboard',
    is_active TINYINT(1) DEFAULT 1,
    created_by VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_created_by (created_by),
    INDEX idx_is_active (is_active),
    INDEX idx_dashboard (is_dashboard, dashboard_position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Báo cáo tùy chỉnh';
```

### 3.2 custom_report_favorites

```sql
-- NGHIỆP VỤ: Báo cáo yêu thích của từng user — hiển thị nhanh trên sidebar
CREATE TABLE IF NOT EXISTS custom_report_favorites (
    id VARCHAR(36) PRIMARY KEY,
    report_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(50) NOT NULL,
    sort_order INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_report (user_id, report_id),
    FOREIGN KEY (report_id) REFERENCES custom_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Báo cáo yêu thích';
```

### 3.3 custom_report_schedules

```sql
-- NGHIỆP VỤ: Lên lịch tự động chạy báo cáo và gửi email định kỳ
-- frequency: daily|weekly|monthly|quarterly|yearly
-- last_run / next_run: quản lý lịch chạy
-- recipients: JSON array email addresses
CREATE TABLE IF NOT EXISTS custom_report_schedules (
    id VARCHAR(36) PRIMARY KEY,
    report_id VARCHAR(36) NOT NULL,
    frequency ENUM('daily','weekly','monthly','quarterly','yearly') NOT NULL,
    day_of_week TINYINT UNSIGNED DEFAULT NULL COMMENT '0=CN, 1=T2...6=T7 (weekly)',
    day_of_month TINYINT UNSIGNED DEFAULT NULL COMMENT '1-31 (monthly)',
    format ENUM('html','csv','pdf') NOT NULL DEFAULT 'csv',
    recipients JSON NOT NULL COMMENT '["email1@company.com","email2@company.com"]',
    subject VARCHAR(255) DEFAULT NULL COMMENT 'Override subject line',
    last_run TIMESTAMP NULL,
    next_run TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_next_run (next_run),
    FOREIGN KEY (report_id) REFERENCES custom_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Lịch gửi báo cáo tự động';
```

### 3.4 Config JSON Structure

```json
{
  "columns": [
    {"alias": "t.reference", "label": "Số chứng từ", "width": 120},
    {"alias": "t.transaction_date", "label": "Ngày", "width": 100},
    {"alias": "a.code", "label": "TK", "width": 80},
    {"alias": "le.amount", "label": "Số tiền", "width": 150, "aggregate": "SUM"},
    {"alias": "t.description", "label": "Diễn giải", "width": 300}
  ],
  "groups": [
    {"alias": "DATE_FORMAT(t.transaction_date, '%Y-%m')", "label": "Tháng"}
  ],
  "filters": [
    {"column": "t.transaction_date", "operator": "BETWEEN", "value": "{date_range}", "label": "Kỳ báo cáo"},
    {"column": "a.code", "operator": "LIKE", "value": "{account_code}", "label": "Tài khoản"}
  ],
  "sort": {
    "t.transaction_date": "DESC",
    "a.code": "ASC"
  },
  "chart": {
    "type": "bar",
    "x_column": "period",
    "y_column": "net_amount",
    "group_column": null,
    "title": "Doanh thu theo tháng",
    "show_legend": true,
    "show_data_labels": false
  },
  "pivot": {
    "rows": "account_code",
    "columns": "period",
    "values": "SUM(amount)",
    "totals": true
  }
}
```

---

## 4. Report Builder Process — 4-Step Wizard

### 4.1 Step 1 — Chọn Nguồn Dữ Liệu

Kế toán viên chọn 1 trong 6 data sources. Mỗi source có:
- Mô tả nghiệp vụ (VD: "Giao dịch phát sinh chi tiết — transactions + ledger_entries")
- Danh sách cột có sẵn (checkbox group)
- Gợi ý mục đích sử dụng

| Source | Mô tả | Dùng cho |
|---|---|---|
| `transactions_detail` | Giao dịch + bút toán + đối tượng | Trace từng giao dịch, kiểm tra, đối chiếu |
| `ledger_account` | Số dư theo tài khoản theo kỳ | Báo cáo tài chính phân tích, biến động TK |
| `inventory` | Tồn kho theo mặt hàng, kho | BC nhập xuất tồn, định giá kho |
| `ar` | Công nợ phải thu theo KH | BC tuổi nợ, BC thanh toán KH |
| `ap` | Công nợ phải trả theo NCC | BC tuổi nợ, BC thanh toán NCC |
| `tax` | Khai báo thuế GTGT/TNDN | BC thuế tổng hợp, phân tích |

**UI:** Radio button group với icon + mô tả. Sau khi chọn → Step 2 mở khóa.

### 4.2 Step 2 — Thiết Lập Cột & Nhóm

**Chọn cột hiển thị:**
- Dual listbox (available → selected) với drag-drop sắp xếp
- Mỗi cột có thể đặt: label hiển thị, width, alignment (left/right/center)
- Cột số mặc định right-align, định dạng `Helpers::fmt()`
- Cột ngày mặc định format `dd/MM/yyyy`

**Tính năng gom nhóm:**

Group columns cho phép chọn: một hoặc nhiều cột để gom nhóm. Khi có group, các cột không trong group hoặc aggregate sẽ bị loại khỏi SELECT.

| Group Level | UI | Effect |
|---|---|---|
| Level 1 | Dropdown "Nhóm chính" | Primary GROUP BY |
| Level 2 | Dropdown "Nhóm phụ" | Secondary GROUP BY |
| Level 3+ | Add button (tối đa 5) | Tertiary+ GROUP BY |

**Aggregate functions trên mỗi cột số:**
- Mỗi cột số có dropdown chọn aggregate: SUM (default), AVG, COUNT, MIN, MAX
- COUNT_DISTINCT cho cột text (đếm số KH, số chứng từ...)
- Khi có group, aggregate là mandatory cho cột số

### 4.3 Step 3 — Thiết Lập Bộ Lọc & Tham Số

**Ba loại filter:**

```yaml
fixed_filter:
  - Luôn áp dụng, ẩn khỏi giao diện chạy báo cáo
  - VD: status = 'posted'
  
runtime_parameter:
  - Hiển thị khi chạy báo cáo, user nhập giá trị
  - type: period, account, customer, supplier, project, date, text, select
  - Mặc định: period = kỳ hiện tại
  - Tối đa 10 parameters per report

conditional_parameter:
  - Parameter phụ thuộc vào parameter khác
  - VD: chọn account → hiện sub-accounts của account đó
```

**Parameter types:**

| Type | UI Widget | Source |
|---|---|---|
| `period` | Dropdown (accounting_periods) | `SELECT id, code FROM accounting_periods ORDER BY code DESC` |
| `account` | Dropdown + search (accounts) | `SELECT code, name FROM accounts ORDER BY code` |
| `account_group` | Dropdown (account type) | `SELECT DISTINCT type FROM accounts` |
| `customer` | Dropdown + search (customers) | `SELECT code, name FROM customers WHERE is_active=1` |
| `supplier` | Dropdown + search (suppliers) | `SELECT code, name FROM suppliers WHERE is_active=1` |
| `project` | Dropdown (projects) | `SELECT code, name FROM projects` |
| `date` | Date picker (flatpickr) | — |
| `date_range` | Date range picker | — |
| `text` | Text input | — |
| `select` | Dropdown (custom values) | Từ parameters.default_value (JSON array) |

### 4.4 Step 4 — Xem Trước & Lưu

**Preview panel (split screen):**
- Trái: form parameters (nếu có)
- Phải: kết quả report (table/chart/pivot tùy loại)
- Auto-refresh khi tham số thay đổi

**Save dialog:**
- Tên báo cáo (bắt buộc, unique cho user)
- Mô tả (optional)
- Loại báo cáo (list/summary/pivot/chart/dashboard)
- Loại biểu đồ (nếu chart)
- Chia sẻ với (role/user multi-select)
- Thêm vào Dashboard? (checkbox)
- Nút Save + Run (lưu và chạy ngay)

### 4.5 Step 5 — Schedule & Share (Post-Save)

Sau khi lưu, user có thể:
- **Schedule:** Đặt lịch tự động chạy (daily/weekly/monthly)
  - Chọn format (HTML/CSV/PDF)
  - Nhập email recipients
  - Hệ thống gửi email khi báo cáo sẵn sàng
  - Xử lý via cron job: `cron/send_scheduled_reports.php`
- **Share:** Cập nhật danh sách role/user được share
- **Favorite:** Đánh dấu yêu thích (xuất hiện trên sidebar)
- **Export:** Tải xuống CSV ngay

---

## 5. Report Types

### 5.1 List Report

BC dạng danh sách — từng dòng = một bản ghi.

```
VD: "Danh sách giao dịch tháng 6/2026"
─────────────────────────────────────────────────────────
Số CT    Ngày         TK      Số tiền      Diễn giải
─────────────────────────────────────────────────────────
PC-00001 02/06/2026   1111    5.000.000    Chi tạm ứng
PC-00001   3311              5.000.000    (đối ứng)
─────────────────────────────────────────────────────────
```

Implementation: `renderTable()`. Bootstrap table với `table-responsive`, sortable columns (via DataTables or manual jQuery), highlight rows.

### 5.2 Summary Report

BC tổng hợp có GROUP BY + aggregate.

```
VD: "Chi phí QLDN theo tháng"
─────────────────────────────────────────
Tháng      TK 6421      TK 6422      Tổng
─────────────────────────────────────────
2026-01   12.000.000    8.500.000    20.500.000
2026-02   11.500.000    8.200.000    19.700.000
─────────────────────────────────────────
Tổng      23.500.000   16.700.000    40.200.000
```

Implementation: Query với GROUP BY, totals row ở cuối.

### 5.3 Pivot Table

BC dạng cross-tabulation: hàng × cột × giá trị.

```
VD: "Doanh thu theo KH × Tháng"
───────────────────────────────────────
KH          T01        T02        T03
───────────────────────────────────────
Công ty A  50.000.000 45.000.000 55.000.000
Công ty B  30.000.000 28.000.000 32.000.000
───────────────────────────────────────
Tổng       80.000.000 73.000.000 87.000.000
───────────────────────────────────────
```

Implementation: Backend `pivot()` method hoặc frontend PivotTable.js library (CDN) cho client-side pivot nếu dữ liệu < 10K rows.
- Nếu dữ liệu > 5K rows → backend pivot (query SQL với CASE WHEN)
- Nếu dữ liệu ≤ 5K rows → frontend pivot (PivotTable.js)

### 5.4 Chart Report

Biểu đồ sử dụng Chart.js (đã có sẵn trong project).

```javascript
// Chart.js config được sinh bởi ReportRenderer
{
  type: 'bar',                      // bar, line, pie, doughnut, stackedBar, horizontalBar
  data: {
    labels: ['T01', 'T02', 'T03'],
    datasets: [{
      label: 'Doanh thu',
      data: [50000000, 45000000, 55000000],
      backgroundColor: '#0d6efd'
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: true },
      title: { display: true, text: 'Doanh thu theo tháng' }
    },
    scales: {
      y: { beginAtZero: true, ticks: { callback: 'formatVnd' } }
    }
  }
}
```

Chart types mapping:

| Report Type | Chart.js Type | When |
|---|---|---|
| Bar | `bar` | So sánh giữa các nhóm |
| Line | `line` | Xu hướng theo thời gian |
| Pie | `pie` | Cơ cấu, tỷ trọng |
| Doughnut | `doughnut` | Cơ cấu (thẩm mỹ hơn) |
| Stacked Bar | `bar` stacked | Tổng hợp nhiều nhóm |
| Horizontal Bar | `bar` indexAxis: 'y' | Nhiều danh mục, label dài |

### 5.5 Dashboard

Trang tổng quan với nhiều widget.

```
┌──────────────────────────────────────────────────────┐
│  📊 TỔNG QUAN TÀI CHÍNH                     Tháng 6  │
├──────────────┬──────────────┬──────────┬─────────────┤
│  Doanh thu   │  Chi phí     │ LN gộp   │ Tiền mặt   │
│  1.2 tỷ      │  890 tr      │ 310 tr   │ 450 tr      │
├──────────────┴──────────────┴──────────┴─────────────┤
│  ┌─────────────────┐  ┌─────────────────────────┐    │
│  │ Biểu đồ doanh   │  │ Bảng công nợ quá hạn    │    │
│  │ thu theo tháng  │  │ KH A: 50tr (quá 30 ng)  │    │
│  │ (line chart)    │  │ KH B: 20tr (quá 60 ng)  │    │
│  └─────────────────┘  └─────────────────────────┘    │
├──────────────┬───────────────────────────────────────┤
│  Bảng chi    │ ● ● ○  Báo cáo tuỳ chỉnh             │
│  phí theo    │   Kéo thả để sắp xếp                  │
│  phòng ban   │                                       │
└──────────────┴───────────────────────────────────────┘
```

Implementation: Dashboard layout using CSS Grid + Bootstrap cards. Mỗi widget là một custom_report với `is_dashboard=1`. Widgets sắp xếp bằng drag-drop (SortableJS).

**Dashboard chỉ số KPI (top row):** 4 ô KPI có thể cấu hình:
- Icon + label + value + % change vs kỳ trước
- Click vào → mở báo cáo chi tiết

---

## 6. Business Rules

### 6.1 Data Source Rules

| Rule | Giá trị | Lý do |
|---|---|---|
| Số data sources | 6 (fixed) | Mỗi source là 1 pre-defined query pattern. Không cho custom SQL. |
| Columns per source | 10-20 cột | Đủ cho 95% nhu cầu báo cáo |
| Joins | Pre-defined only | Không cho phép custom JOIN — rủi ro sai dữ liệu và perf |
| Row limit | 10,000 | Tránh memory overflow. Trên 10K → đề xuất export CSV |
| Query timeout | 30 giây | `MAX_EXECUTION_TIME` — kill query nếu quá lâu |
| Concurrent runs | 5 per user | Rate limit — tránh overload DB |

### 6.2 Report Definition Rules

| Rule | Giá trị | Lý do |
|---|---|---|
| Parameters per report | Tối đa 10 | UX — quá nhiều tham số gây rối |
| Columns per report | Tối đa 20 | Performance + readability |
| Group levels | Tối đa 5 | Query complexity |
| Chart series | Tối đa 20 | Chart.js performance |
| Report name | Unique per user | Tránh nhầm lẫn |
| Name length | 3-255 ký tự | — |
| Description length | Tối đa 1000 | — |

### 6.3 Permission Rules

| Action | Permission Required | Module |
|---|---|---|
| View report list | `report_builder`, `read` | report_builder |
| Create report | `report_builder`, `create` | report_builder |
| Edit own report | Owner OR admin | — |
| Edit any report | `report_builder`, `update` | report_builder |
| Delete report | Owner OR `report_builder`, `delete` | report_builder |
| Share report | Owner OR `report_builder`, `update` | report_builder |
| Run report | View permission (shared) | — |
| See on dashboard | Shared with role/user AND is_dashboard=1 | — |
| Schedule report | `report_builder`, `create` | report_builder |

**Visibility rules:**
- Report chỉ visible cho owner và người được share
- Admin thấy tất cả reports (có filter "Tất cả reports")
- Report trên Dashboard chỉ visible cho user có quyền xem

### 6.4 Period Auto-Parameter

Khi parameter type = `period` và không có giá trị mặc định, hệ thống tự động điền:
```php
// Nếu kỳ hiện tại đang mở → dùng kỳ đó
// Nếu không → dùng kỳ gần nhất
$defaultPeriod = $pdo->prepare(
    "SELECT code FROM accounting_periods
     WHERE is_closed = 0 OR id = (
         SELECT id FROM accounting_periods ORDER BY end_date DESC LIMIT 1
     )
     ORDER BY is_closed ASC, end_date DESC LIMIT 1"
);
```

### 6.5 Export Rules

- CSV export: UTF-8 BOM, mọi row, không giới hạn (streaming nếu > 10K rows)
- HTML export: dùng `ReportExportService::exportHtml()` pattern
- PDF: qua browser print (`window.print()`), giới hạn hiện tại của Bookwise

---

## 7. API Endpoints

### 7.1 Report CRUD

| Method | Endpoint | Mô tả | Auth |
|---|---|---|---|
| GET | `/api/reports` | List reports (của user + được share) | read |
| GET | `/api/reports/:id` | Get report config + last run data | read |
| POST | `/api/reports` | Create report | create |
| PUT | `/api/reports/:id` | Update report config | update |
| DELETE | `/api/reports/:id` | Soft delete (is_active=0) | delete |
| POST | `/api/reports/:id/clone` | Clone report | create |
| GET | `/api/reports/sources` | List available data sources + columns | read |

### 7.2 Report Execution

| Method | Endpoint | Mô tả | Auth |
|---|---|---|---|
| GET | `/api/reports/:id/run` | Run report with params (?period=2025&...) | read |
| POST | `/api/reports/:id/run` | Run report (POST body params) | read |
| GET | `/api/reports/:id/export/csv` | Download CSV | read |
| GET | `/api/reports/:id/export/html` | View HTML export (print-friendly) | read |

### 7.3 Favorites & Schedule

| Method | Endpoint | Mô tả | Auth |
|---|---|---|---|
| POST | `/api/reports/:id/favorite` | Toggle favorite | read |
| GET | `/api/reports/favorites` | List favorites | read |
| GET | `/api/reports/schedules` | List schedules | read |
| POST | `/api/reports/:id/schedule` | Create schedule | create |
| PUT | `/api/reports/:id/schedule/:sid` | Update schedule | update |
| DELETE | `/api/reports/:id/schedule/:sid` | Delete schedule | delete |

### 7.4 Dashboard

| Method | Endpoint | Mô tả | Auth |
|---|---|---|---|
| GET | `/api/dashboard` | Get dashboard widgets + data | read |
| PUT | `/api/dashboard/position` | Update widget positions | update |
| POST | `/api/reports/:id/pin-dashboard` | Add to/remove from dashboard | update |

### 7.5 Data Sources Meta

| Method | Endpoint | Mô tả |
|---|---|---|
| GET | `/api/reports/sources` | List data sources + available columns + filter types |
| GET | `/api/reports/parameters/periods` | List accounting periods (for parameter dropdown) |
| GET | `/api/reports/parameters/accounts` | List accounts (with search) |
| GET | `/api/reports/parameters/customers` | List active customers |
| GET | `/api/reports/parameters/suppliers` | List active suppliers |
| GET | `/api/reports/parameters/projects` | List active projects |

### 7.6 Response Format

```json
// GET /api/reports
{
  "data": [
    {
      "id": "rpt_abc123",
      "name": "Chi phí QLDN theo tháng",
      "description": "Chi phí quản lý doanh nghiệp phân tích theo tháng",
      "data_source": "transactions_detail",
      "report_type": "list",
      "chart_type": "bar",
      "is_dashboard": true,
      "is_favorite": true,
      "created_by": "admin",
      "created_at": "2026-06-01 10:00:00",
      "shared_with": [{"type": "role", "id": "ke_toan"}]
    }
  ],
  "total": 5
}

// GET /api/reports/:id/run
{
  "data": {
    "report": {
      "id": "rpt_abc123",
      "name": "Chi phí QLDN theo tháng"
    },
    "params": {"period": "2026", "account_code": "642%"},
    "config": {
      "columns": [
        {"alias": "DATE_FORMAT(t.transaction_date, '%Y-%m')", "label": "Tháng"},
        {"alias": "a.code", "label": "TK"},
        {"alias": "le.amount", "label": "Số tiền", "aggregate": "SUM"}
      ]
    },
    "rows": [
      {"Tháng": "2026-01", "TK": "6421", "Số tiền": 12000000},
      {"Tháng": "2026-01", "TK": "6422", "Số tiền": 8500000}
    ],
    "total": 24,
    "pivot": null,
    "chart_config": null,
    "execution_time_ms": 45
  }
}
```

---

## 8. UI/UX

### 8.1 Report Builder View (`/bao-cao/tuy-chinh`)

**Layout:**
```
┌────────────────────────────────────────────────────────┐
│  BÁO CÁO TÙY CHỈNH                    [+ Tạo mới]      │
├────────────────────────────────────────────────────────┤
│  [Tất cả] [Của tôi] [Được chia sẻ] [Yêu thích]        │
│  ┌────────────────────────────────────────────────────┐│
│  │ 🔍 Tìm kiếm báo cáo...           [Grid] [List]    ││
│  ├────────────────────────────────────────────────────┤│
│  │ ⭐ Chi phí QLDN theo tháng     (Bar chart)  [▶] [⋮]││
│  │ 📊 Doanh thu theo KH           (Table)     [▶] [⋮]││
│  │ 📈 Công nợ quá hạn             (Pivot)     [▶] [⋮]││
│  └────────────────────────────────────────────────────┘│
└────────────────────────────────────────────────────────┘
```

### 8.2 Step-by-Step Wizard

```
┌────────────────────────────────────────────────────────┐
│  TẠO BÁO CÁO MỚI                              Đóng [X]│
├────────────────────────────────────────────────────────┤
│  Bước 1: Nguồn dữ liệu    ○ ● ○ ○                     │
│  ┌────────────────────────────────────────────────────┐│
│  │ ○ Transactions & Ledger  — Giao dịch + bút toán   ││
│  │ ○ Ledger by Account      — Số dư theo TK theo kỳ  ││
│  │ ○ Inventory              — Tồn kho                 ││
│  │ ○ AR                     — Công nợ phải thu       ││
│  │ ○ AP                     — Công nợ phải trả       ││
│  │ ○ Tax                    — Khai báo thuế           ││
│  └────────────────────────────────────────────────────┘│
│                                        [Tiếp theo →]   │
├────────────────────────────────────────────────────────┤
│  Bước 2: Cột & Nhóm           ○ ○ ● ○                  │
│  ┌─────────────┬─────────────────────────────────────┐ │
│  │ Có sẵn      │ Đã chọn                             │ │
│  │─────────────│─────────────────────────────────────│ │
│  │ Số chứng từ │ [→] Tháng                          │ │
│  │ Ngày        │ [→] TK                              │ │
│  │ Diễn giải   │ [→] Số tiền (SUM) [▼]              │ │
│  │ Mô-đun      │ [→]             [★ Nhãn] [120px]   │ │
│  │ Người tạo   │                                     │ │
│  └─────────────┴─────────────────────────────────────┘ │
│  Nhóm: [+ Thêm nhóm]                                  │
│  ┌── Nhóm 1 ──────────────────────────────────────────┐│
│  │ [Tháng ▼]  ───  [Xóa]                             ││
│  └────────────────────────────────────────────────────┘│
│                                        [Tiếp theo →]   │
├────────────────────────────────────────────────────────┤
│  Bước 3: Bộ lọc & Tham số       ○ ○ ○ ●                │
│  ┌────────────────────────────────────────────────────┐│
│  │ Lọc cố định:                                       ││
│  │   [TK    ] [BẮT ĐẦU VỚI] [642%     ]   [Xóa]     ││
│  │                                                     ││
│  │ Tham số runtime:                                   ││
│  │   [Kỳ kế toán ▼] [period ▼] [Bắt buộc]  [Xóa]    ││
│  │   [+ Thêm tham số]                                 ││
│  └────────────────────────────────────────────────────┘│
│                                        [Xem trước →]   │
├────────────────────────────────────────────────────────┤
│  Bước 4: Xem trước & Lưu        ○ ○ ○ ●                │
│  ┌─ Tham số ───────────┬─ Kết quả ────────────────────┐│
│  │ Kỳ: [2026 ▼]        │ Tháng     TK     Số tiền    ││
│  │ TK: [642%   ]       │ 2026-01  6421   12.000.000  ││
│  │                     │ 2026-01  6422    8.500.000  ││
│  │ Loại: [List ▼]      │ 2026-02  6421   11.500.000  ││
│  │ Chart: [Bar ▼]      │ ...                         ││
│  │                     │ [Tải lại] [CSV]             ││
│  └─────────────────────┴──────────────────────────────┘│
│  Tên báo cáo: [Chi phí QLDN theo tháng              ] ││
│  Mô tả: [Chi phí quản lý doanh nghiệp theo tháng   ] ││
│  Chia sẻ với: [Kế toán trưởng ▼] [+ Thêm]            ││
│  ☐ Thêm vào Dashboard                                 ││
│                                    [Lưu] [Lưu & Chạy] ││
└────────────────────────────────────────────────────────┘
```

### 8.3 Dashboard View (`/`)

```
┌────────────────────────────────────────────────────────────┐
│  📊 TỔNG QUAN                              Tháng 6/2026   │
├──────────┬──────────┬──────────┬──────────────────────────┤
│  💰      │  📉      │  📈      │  ⏳                      │
│  DOANH THU│  CHI PHÍ │  LN GỘP  │  THUẾ TNDN              │
│  1.2 Tỷ  │  890 Tr  │  310 Tr  │  62 Tr                   │
│  ▲ 15%   │  ▼ 3%    │  ▲ 25%   │  = 0%                    │
├──────────┴──────────┴──────────┴──────────────────────────┤
│  [Filter: Tất cả ▼]  [Thêm widget]  [Sắp xếp] [Xuất]     │
│  ┌────────────────────────────────────────────────────┐   │
│  │  ═══ Chi phí QLDN theo tháng      [⋮] [×]         │   │
│  │  ┌─────────────────────────────────────────────┐   │   │
│  │  │ ██ ██ ██ ██ ██ ██                            │   │   │
│  │  │ ██ ██ ██ ██ ██ ██ ██ ██                      │   │   │
│  │  │ T01 T02 T03 T04 T05 T06                      │   │   │
│  │  └─────────────────────────────────────────────┘   │   │
│  └────────────────────────────────────────────────────┘   │
│  ┌──────────────────────┬──────────────────────────────┐  │
│  │  ═══ Công nợ quá hạn│  ═══ Doanh thu theo KH       │  │
│  │  [⋮] [×]             │  [⋮] [×]                     │  │
│  │  KH A: 50tr (30 ng)  │  ██ Cty A: 500tr            │  │
│  │  KH B: 20tr (60 ng)  │  ██ Cty B: 300tr            │  │
│  └──────────────────────┴──────────────────────────────┘  │
└────────────────────────────────────────────────────────────┘
```

### 8.4 jQuery / Bootstrap 5 Implementation

```javascript
// Report Builder — drag-drop column selector
// Sử dụng SortableJS (CDN) cho drag-drop giữa available/selected columns
$('.column-list').sortable({
    group: 'columns',
    animation: 150,
    onEnd: function(evt) { updateColumnOrder(); }
});

// Chart preview — real-time update khi thay đổi config
function updateChartPreview() {
    const config = buildChartConfig();
    if (window.chartInstance) window.chartInstance.destroy();
    const ctx = document.getElementById('chart-preview').getContext('2d');
    window.chartInstance = new Chart(ctx, config);
}

// Parameter auto-fill
$('select[name="period"]').on('change', function() {
    $('#report-preview').addClass('loading');
    $.get('/api/reports/' + reportId + '/run', { period: this.value })
        .done(function(res) { renderPreview(res.data); })
        .always(function() { $('#report-preview').removeClass('loading'); });
});
```

---

## 9. Implementation Checklist

### Phase 1 — Core Engine (2-3 days)

```
[ ] 1. Migration: custom_reports table
[ ] 2. Migration: custom_report_favorites table
[ ] 3. Migration: custom_report_schedules table
[ ] 4. Model: src/Accounting/Domain/Model/CustomReport.php
[ ] 5. Repository Interface: ReportRepositoryInterface
[ ] 6. PDO Repository: PDOReportRepository
[ ] 7. SafeQueryBuilder class — whitelist engine + 6 data sources
[ ] 8. ReportRenderer class — renderTable, renderChart, renderCsv
[ ] 9. ReportBuilderService — orchestrate query + render
[ ] 10. DI: services.php registration
[ ] 11. Tests: SafeQueryBuilderTest.php — test all whitelist rules
```

### Phase 2 — API & Controller (1-2 days)

```
[ ] 1. ReportBuilderController — CRUD endpoints
[ ] 2. ReportBuilderController — run/export endpoints
[ ] 3. DashboardController — dashboard widgets
[ ] 4. ScheduleController — CRUD schedules
[ ] 5. Routes: api_reports.php + dashboard.php
[ ] 6. CSRF protection on all POST/PUT/DELETE
[ ] 7. API tests: all endpoints happy path + failure
```

### Phase 3 — Views & UX (1-2 days)

```
[ ] 1. Report list view: /bao-cao/tuy-chinh
[ ] 2. Report builder wizard: 4-step modal/page
[ ] 3. Report run view: parameter form + results
[ ] 4. Chart preview with Chart.js
[ ] 5. CSV export button
[ ] 6. Dashboard view: widget grid + KPI cards
[ ] 7. Favorite toggle (star icon)
[ ] 8. Share dialog (role/user multi-select)
[ ] 9. Schedule dialog (frequency, recipients)
[ ] 10. Sidebar: "Báo cáo tùy chỉnh" menu item
[ ] 11. Permissions: RBAC seed for report_builder module
```

### Phase 4 — Polish & Schedule (1 day)

```
[ ] 1. Cron job: cron/send_scheduled_reports.php
[ ] 2. Email template for scheduled reports
[ ] 3. Loading states + error handling (all AJAX)
[ ] 4. Empty states (no reports yet, no data)
[ ] 5. Pivot table rendering (frontend or backend)
[ ] 6. Dashboard drag-drop position save
[ ] 7. Audit logging for report create/update/delete/run
[ ] 8. Performance test: 10K rows, 30s timeout
```

---

## 10. Effort Estimate

| Phase | Days | Dependencies |
|---|---|---|
| Phase 1 — Core Engine | 3 | None |
| Phase 2 — API & Controller | 2 | Phase 1 |
| Phase 3 — Views & UX | 2 | Phase 2 |
| Phase 4 — Polish & Schedule | 1 | Phase 3 |
| **Total** | **5-7** | — |

**Risk factors:**
- Pivot table complexity: pivot có thể tăng effort +1 day nếu cần backend pivot với CASE WHEN
- Dashboard drag-drop: SortableJS CDN dependency cần kiểm tra compatibility
- Schedule cron: cần đảm bảo PHP CLI có quyền gửi mail
- Security review: SafeQueryBuilder cần code review kỹ — whitelist phải đủ strict

---

## 11. Security & Risk Assessment

### 11.1 Threat Model

| Threat | Vector | Mitigation |
|---|---|---|
| SQL injection qua column name | User nhập column name custom | Whitelist — chỉ cho phép column từ pre-defined list |
| SQL injection qua filter value | User nhập `' OR 1=1 --` | PDO prepared statements — mọi giá trị filter đều là placeholder |
| Timeout / DoS | Query chạy quá lâu | `MAX_EXECUTION_TIME`, row limit, concurrent limit |
| XSS qua report name/description | User nhập `<script>` trong name | `htmlspecialchars()` khi render |
| Privilege escalation | User xem report không được share | Server-side permission check trên mọi endpoint |
| Data leak | User export report không được phép | Permission check trên export endpoint |

### 11.2 Audit Trail

```php
// Mọi thao tác quan trọng đều log audit
AuditLogger::log('report.create', 'custom_report', $reportId,
    null, ['name' => $reportName, 'source' => $dataSource],
    $userId);

AuditLogger::log('report.run', 'custom_report', $reportId,
    null, ['params' => $params, 'rows' => $rowCount],
    $userId);

AuditLogger::log('report.share', 'custom_report', $reportId,
    $oldShare, $newShare, $userId);

AuditLogger::log('report.export', 'custom_report', $reportId,
    null, ['format' => 'csv', 'rows' => $totalRows],
    $userId);
```

### 11.3 Query Safety Validation (Mandatory Pre-Merge)

```
[ ] SafeQueryBuilder: mọi column name được kiểm tra whitelist trước khi vào SQL
[ ] SafeQueryBuilder: prepared statements cho mọi giá trị (không concatenate)
[ ] SafeQueryBuilder: MAX_EXECUTION_TIME set trước mỗi query
[ ] SafeQueryBuilder: LIMIT 10000 hardcoded, không thể override từ config
[ ] ReportBuilderService: permission check trước khi run
[ ] ReportBuilderController: input validation (name length, param count)
[ ] Schedule: email recipients được validate + escape
[ ] Test: SQL injection attempt với mọi parameter type
[ ] Test: permission escalation attempt
[ ] Test: timeout vượt quá 30s
```

---

> **Tài liệu tham khảo:** `AGENTS.md §7 (Business Rules)`, `GlService.php` (query patterns), `FsService.php` (report generation), `ReportExportService.php` (CSV/HTML export), MISA AMIS "Báo cáo tự tạo" UX pattern
