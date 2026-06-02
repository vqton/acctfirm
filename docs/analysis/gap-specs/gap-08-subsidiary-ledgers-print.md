# Gap 08: Subsidiary Ledgers + Print — Parity Specification

**Version:** 1.0  
**Status:** Draft  
**Priority:** High (quick-win: 3-4 days, zero new DB tables)  
**Domain:** Báo cáo kế toán — Sổ chi tiết & In ấn theo TT 99/2025/TT-BTC

---

## 1. Business Context & Regulatory Framework

### 1.1 Yêu cầu pháp lý

Thông tư 99/2025/TT-BTC (Circular 99) quy định doanh nghiệp phải lập các sổ kế toán chi tiết sau:

| Mẫu | Tên sổ | Bắt buộc |
|---|---|---|
| S05-DN | Sổ cái (General Ledger) | ✅ Tất cả DN |
| S06-DN | Sổ quỹ tiền mặt (Cash Book) | ✅ Có quỹ TM |
| S07-DN | Sổ tiền gửi ngân hàng (Bank Book) | ✅ Có TK NH |
| S12-DN | Sổ kho (Inventory Ledger) | ✅ Có hàng tồn kho |
| S13-DN | Sổ chi tiết công nợ (AR/AP Ledger) | ✅ Có công nợ |
| S33-DN | Sổ chi tiết bán hàng | ✅ Có bán hàng |
| — | Sổ chi tiết tài khoản (Detailed Sub-Ledger) | ✅ Kiểm toán yêu cầu |

### 1.2 Tình trạng hiện tại

Bookwise hiện có:
- **GlService::getGeneralLedger()** — sổ cái chi tiết (data + running balance)
- **GlService::getSubsidiaryLedger()** — sổ chi tiết theo đối tượng (131/331/334/project)
- **CashService::getCashBook()** — sổ quỹ tiền mặt TK 111
- **InventoryService** — đầy đủ cost layer, items, warehouses
- **ArService::getAgingReport()** và **ApService::getAgingReport()** — aging
- **ReportExportController** — CSV và HTML export cho GL ledger

**Thiếu:**
- Không có giao diện in PDF chuẩn TT 99 (chỉ có print via window.print())
- Không có sổ kho (Inventory Ledger — S12-DN) với nhập/xuất/tồn
- Không có sổ tiền gửi ngân hàng (Bank Book — S07-DN) riêng biệt
- Không có sổ chi tiết bán hàng (S33-DN)
- Không có unified report architecture — các sổ nằm rải rác ở CashService và GlService
- Không có Excel (XLSX) export

---

## 2. Ledger Types Required

### 2.1 Sổ Cái — General Ledger (S05-DN)

Mở rộng từ GlService::getGeneralLedger() hiện tại.

**Columns (detail mode):** Ngày | Số CT (Reference) | Diễn giải (Description) | TK ĐƯ (Contra Account) | PS Nợ (Debit) | PS Có (Credit) | Số dư (Running Balance)

**Columns (monthly mode):** Tháng | SDĐK | PS Nợ | PS Có | SDCK | Chi tiết Nợ theo TK ĐƯ

**Đặc thù:**
- Dư đầu kỳ (SDĐK) = số dư tại thời điểm đầu kỳ
- Số dư luỹ kế tính theo normal balance của TK (asset/expense → balance tăng bên Nợ, liability/equity/revenue → balance tăng bên Có)
- Đã implement ở GlService — cần PDF export và filter theo period_id

**API cần:** `GET /api/reports/general-ledger?account_code=&period_id=&from_date=&to_date=&format=json|html|pdf|csv`

### 2.2 Sổ Chi Tiết Tài Khoản — Detailed Sub-Ledger

Đã implement ở GlService::getSubsidiaryLedger() với group_by:
- `customer` (TK 131): object = Khách hàng
- `supplier` (TK 331): object = Nhà cung cấp
- `employee` (TK 334): object = Nhân viên
- `project`: object = Dự án

**GIỚI HẠN:** Hiện tại chỉ group được khi ledger_entries có sẵn trường object_id (customer_id, supplier_id, employee_id, project_id). Nếu dữ liệu lịch sử không có object_id, các dòng đó sẽ gom vào "(Không có)". Cần kiểm tra migration kiểm tra xem các trường này có trong `ledger_entries` không.

**Mở rộng cần thiết:**
- Thêm filter: entity_id (lọc 1 khách hàng cụ thể), entity_type
- PDF xuất riêng từng đối tượng hoặc gộp tất cả

### 2.3 Sổ Quỹ Tiền Mặt — Cash Book (S03a-DN / S06-DN)

Đã implement một phần ở CashService::getCashBook() — query trực tiếp ledger_entries với `account_code = '111'`.

**Đặc thù S03a-DN:**
- Cột: Ngày tháng | Số hiệu CT Thu | Số hiệu CT Chi | Diễn giải | TK ĐƯ | Số tiền Thu | Số tiền Chi | Tồn quỹ
- Dòng đầu: Số dư đầu quý/tháng
- Dòng cuối: Cộng phát sinh + Số dư cuối
- Yêu cầu riêng cho từng loại tiền: 1111 (VND), 1112 (USD), 1113 (Vàng)

**Cần cải thiện:**
- Thêm cột TK ĐƯ (contra account)
- Thêm cột "Thu" và "Chi" riêng biệt (hiện tại getCashBook đã làm)
- Filter theo sub-account: 1111, 1112, 1113
- Tính opening balance trước ngày bắt đầu (hiện tại tính từ đầu — cần sửa)

### 2.4 Sổ Tiền Gửi Ngân Hàng — Bank Book (S03b-DN / S07-DN)

**Chưa implement.** Cần tạo mới. Tương tự sổ quỹ nhưng cho TK 112.

**Columns (S07-DN):** Ngày | Số CT | Diễn giải | TK ĐƯ | Phát sinh Nợ (Gửi vào) | Phát sinh Có (Rút ra) | Số dư

**Đặc thù:**
- Filter theo từng tài khoản ngân hàng: 1121 (VND), 1122 (Ngoại tệ)
- Hiển thị tên ngân hàng
- Có thể kèm cột "Đã đối chiếu" (bank reconciliation flag)

```sql
-- Query cho Sổ NH:
SELECT t.reference, t.created_at, t.description,
       le.amount, le.is_debit,
       a2.code as contra_code
FROM ledger_entries le
JOIN transactions t ON t.id = le.transaction_id
JOIN accounts a ON a.id = le.account_id
LEFT JOIN ledger_entries le2 ON le2.transaction_id = le.transaction_id AND le2.id != le.id
LEFT JOIN accounts a2 ON a2.id = le2.account_id
WHERE a.code LIKE '112%'
  AND t.created_at BETWEEN ? AND ?
ORDER BY t.created_at ASC, le.id ASC
```

### 2.5 Sổ Kho — Inventory Ledger (S12-DN)

**Chưa implement.** Cần tạo mới. Đây là sổ quan trọng nhất cho thủ kho và kế toán kho.

**Columns (S12-DN):** Ngày tháng | Số CT | Diễn giải | Nhập SL | Nhập Tiền | Xuất SL | Xuất Tiền | Tồn SL | Tồn Tiền

**Nguồn dữ liệu:**
- `inventory_cost_layers` — chứa qty, unit_cost, addon_per_unit, created_at
- `transactions` JOIN `ledger_entries` — bút toán xuất/nhập kho
- `items` — tên mặt hàng, mã hàng, đơn vị tính
- `warehouses` — tên kho

```sql
-- Query cho sổ kho chi tiết theo item:
-- Không có bảng inventory_ledger riêng — cần tổng hợp từ cost layers + transactions
SELECT icl.created_at, icl.qty, icl.unit_cost, icl.addon_per_unit,
       icl.warehouse_id, icl.batch_code,
       t.reference, t.description
FROM inventory_cost_layers icl
LEFT JOIN transactions t ON t.id = icl.id -- không có FK trực tiếp
WHERE icl.item_id = ?
ORDER BY icl.created_at ASC
```

**VẤN ĐỀ:** `inventory_cost_layers` không có FK trực tiếp đến `transactions`. Cần xác định transaction_id tương ứng qua reference hoặc một cột bổ sung. Nếu không, sổ kho phải tổng hợp từ:
1. Nhập kho: `items` → `goods_receipts` → `transactions` (ledger_entries Dr 15x)
2. Xuất kho: `items` → `inventory_cost_layers` (qty giảm) → `transactions` (ledger_entries Cr 15x)

**GIẢI PHÁP:** Thêm cột `transaction_id` vào `inventory_cost_layers` (migration mới) hoặc tạo VIEW tổng hợp từ `ledger_entries` + `items`. Chi tiết xem §3.2.

### 2.6 Sổ Chi Tiết Công Nợ Phải Thu — AR Sub-Ledger (S13-DN)

Đã có ArService.getCustomerStatement() trả về danh sách hóa đơn theo KH. Cần mở rộng thành sổ chi tiết.

**Columns (S13-DN):** Ngày | Số CT | Diễn giải | Tổng số tiền | PS Nợ (Tăng) | PS Có (Giảm) | Số dư

**Khác với sổ cái TK 131:**
- Bao gồm chi tiết: hóa đơn, thanh toán, trả lại, chiết khấu, xóa nợ
- Hiển thị số dư cuối kỳ = Tổng hóa đơn chưa thanh toán
- Có cột tuổi nợ và trích lập dự phòng

**Nguồn dữ liệu chính:**
- `ar_invoices` — balance, due_date, status
- `ar_payments` — từng lần thanh toán/trả lại/chiết khấu
- `payment_allocations` — phân bổ thanh toán nhiều hóa đơn

### 2.7 Sổ Chi Tiết Công Nợ Phải Trả — AP Sub-Ledger (S13-DN)

Tương tự AR. Đã có ApService.getSupplierStatement().

**Columns:** Ngày | Số CT | Diễn giải | PS Nợ (Giảm) | PS Có (Tăng) | Số dư

**Đặc thù:**
- Số dư bên Có = công nợ phải trả
- Số dư bên Nợ = tạm ứng chưa thanh toán
- Có cột hợp đồng (contract_id)

### 2.8 Sổ Chi Tiết Bán Hàng — Sales Ledger (S33-DN)

**Chưa implement.** Cần tạo mới.

**Columns (S33-DN):** Ngày | Số HĐ | Tên KH | Doanh thu (511) | Thuế GTGT (33311) | Tổng thanh toán | Đã thu | Còn phải thu

**Nguồn dữ liệu:**
- `ar_invoices` — các hóa đơn bán hàng
- `transactions` JOIN `ledger_entries` — kiểm tra bút toán: Dr 131 / Cr 511 + Cr 33311
- `customers` — tên, mã KH

---

## 3. Data Architecture

### 3.1 Interface Design

```php
namespace Accounting\Domain\Contract;

// File: src/Accounting/Domain/Contract/SubLedgerReportInterface.php

interface SubLedgerReportInterface
{
    // Loại báo cáo: general_detail, general_monthly, cash_book, bank_book,
    //               inventory, ar_detail, ap_detail, sales
    public function getReportType(): string;

    // Tham số đầu vào cho báo cáo
    public function getParameters(): array;

    // Thiết lập tham số (fluent interface)
    public function withParams(array $params): static;

    // Số dư đầu kỳ
    public function getOpeningBalance(): float;

    // Danh sách giao dịch trong kỳ
    public function getTransactions(): array;

    // Số dư cuối kỳ
    public function getClosingBalance(): float;

    // Tổng hợp phát sinh
    public function getTotals(): array;

    // Render output: html|pdf|csv (mặc định html)
    public function render(string $format = 'html'): string;

    // Xuất file (Content-Disposition)
    public function export(string $format = 'pdf'): array;
}
```

### 3.2 SubLedgerReport Implementations

```
src/Accounting/Domain/Service/SubLedger/
├── SubLedgerReportInterface.php
├── AbstractSubLedgerReport.php      — base class chung
├── GeneralLedgerReport.php          — Sổ Cái (S05-DN)
├── DetailedSubLedgerReport.php      — Sổ chi tiết theo đối tượng
├── CashBookReport.php               — Sổ quỹ TM (S03a-DN)
├── BankBookReport.php               — Sổ NH (S03b-DN)
├── InventoryLedgerReport.php        — Sổ kho (S12-DN)
├── ArLedgerReport.php               — Sổ CN phải thu (S13-DN)
├── ApLedgerReport.php               — Sổ CN phải trả (S13-DN)
└── SalesLedgerReport.php            — Sổ bán hàng (S33-DN)
```

**AbstractSubLedgerReport** cung cấp:
- `renderHtml()` — template chung với header, table, footer
- `renderPdf()` — gọi HTML + DOMPDF/WKHTMLTOPDF wrapper
- `exportCsv()` — CSV export
- `exportXlsx()` — XLSX via PhpSpreadsheet (cần Composer — xem §9.3)
- Running balance calculation helper

**InventoryLedgerReport** — cần giải quyết vấn đề dữ liệu:

```php
// Giải pháp tạm thời: query từ ledger_entries + items
// Không cần migration mới — sử dụng transaction_id pattern từ reference
class InventoryLedgerReport extends AbstractSubLedgerReport
{
    public function getTransactions(): array
    {
        // Query tất cả ledger_entries cho các TK 152,153,155,156,157
        // trong khoảng thời gian, nhóm theo transaction_id
        // Xác định nhập/xuất dựa trên is_debit:
        //   - Dr 15x = NHẬP (tăng tồn)
        //   - Cr 15x = XUẤT (giảm tồn)
        // Running Qty: lấy từ items.stock_qty tại thời điểm
        // Running Amount: giá vốn luỹ kế từ cost layers
        //
        // HẠN CHẾ: Không trace được transaction_id trên inventory_cost_layers
        // → running amount dùng unit_cost bình quân tại thời điểm query
        $sql = "SELECT t.id, t.reference, t.created_at, t.description,
                       le.is_debit, le.amount,
                       a.code as account_code
                FROM ledger_entries le
                JOIN transactions t ON t.id = le.transaction_id
                JOIN accounts a ON a.id = le.account_id
                JOIN items i ON i.inventory_account = a.code
                WHERE a.code IN ('152','153','155','156','157')
                  AND i.id = ?
                  AND t.created_at BETWEEN ? AND ?
                ORDER BY t.created_at ASC, t.id ASC";
        // ...
    }
}
```

### 3.3 Running Balance — Pattern Chung

```php
// Tất cả các report dùng chung pattern này
abstract class AbstractSubLedgerReport
{
    protected function calculateRunningBalance(
        array $transactions,
        float $openingBalance,
        bool $isDebitNormalBalance
    ): array {
        $balance = $openingBalance;
        foreach ($transactions as &$txn) {
            if ($isDebitNormalBalance) {
                // Asset/Expense: balance tăng khi Dr, giảm khi Cr
                $balance += ($txn['is_debit'] ? $txn['amount'] : -$txn['amount']);
            } else {
                // Liability/Equity/Revenue: balance tăng khi Cr, giảm khi Dr
                $balance += ($txn['is_debit'] ? -$txn['amount'] : $txn['amount']);
            }
            $txn['running_balance'] = round($balance, 2);
        }
        return $transactions;
    }
}
```

---

## 4. Print Formats (PDF)

### 4.1 TT 99-Compliant Print Layout

Mỗi sổ in ra phải có cấu trúc sau:

1. **Phần đầu trang (Page Header):**
   - Tên công ty (in đậm, font 14pt)
   - Mã số thuế, địa chỉ (font 9pt)
   - Dòng kẻ ngang

2. **Tiêu đề báo cáo:**
   - Tên sổ: "SỔ QUỸ TIỀN MẶT" / "SỔ KHO" / etc (uppercase, font 16pt)
   - Mẫu số: "(Mẫu số S03a-DN)" (font 10pt)
   - Kỳ báo cáo: "Từ ngày ... đến ngày ..."

3. **Bảng dữ liệu:**
   - A4 landscape (297×210mm) cho sổ kho, portrait cho sổ khác
   - Font: "Times New Roman" 10pt
   - Border: 0.5pt solid
   - Header row: in đậm, centered
   - Dòng tổng: in đậm, border top double
   - Page break: tự động + "Còn trang sau →"

4. **Phần cuối trang (Page Footer):**
   - Dòng kẻ ngang
   - "Trang X / Y"
   - 3 cột ký: Người ghi sổ, Kế toán trưởng, Đại diện theo PL
   - Dòng ký: "(Ký, họ tên)" (font 10pt)
   - Mỗi cột cách nhau 80mm

5. **Điện tử:** Khu vực trống 20×20mm góc trái trên để đóng dấu điện tử (ảo thuật).

### 4.2 PDF Generation Strategy

**Option A — PHP library (RECOMMENDED for now):**
- Dùng HTML-to-PDF với render HTML trước, convert sau
- Cần xác định library hiện có: check nếu Composer đã có Dompdf, mPDF, hoặc TCPDF
- Nếu chưa có: viết PDF bằng HTML + CSS print media, xuất ra bằng window.print() hoặc TCPDF đơn giản

**Option B — No library (hiện tại):**
- Dùng `window.print()` với CSS print media (đã có ở `so_cai.php`)
- Export PDF bằng cách gọi: POST /api/reports/sub-ledger/export?format=pdf
- Response là HTML với CSS print → browser tự in ra PDF

**Khuyến nghị:** Dùng Option B cho phase 1 (giữ nguyên pattern hiện tại) và nâng cấp lên TCPDF/mPDF ở phase 2 khi đã có đủ budget.

### 4.3 CSV Export

```php
// Mở rộng ReportExportController với pattern hiện tại
public function exportCsvSubLedger(): void
{
    Auth::requirePermission('report', 'export');
    $type = $_GET['type'] ?? 'general';
    $account = $_GET['account'] ?? null;
    $periodId = $_GET['period_id'] ?? null;
    $entityId = $_GET['entity_id'] ?? null;

    $service = $GLOBALS['container'][SubLedgerService::class];
    $report = $service->createReport($type, [
        'account_code' => $account,
        'period_id' => $periodId,
        'entity_id' => $entityId,
    ]);

    $headers = $report->getCsvHeaders(); // mỗi report tự định nghĩa
    $rows = $report->getCsvRows();

    $this->export->exportCsv($headers, $rows, "{$type}_{$account}.csv");
}
```

---

## 5. API Endpoints

### 5.1 Unified Sub-Ledger API

```php
// config/routes/api_sub_ledger.php — file routes mới

// 1. Dữ liệu JSON cho frontend
$router->get('/api/reports/sub-ledger', function() use ($c) {
    $c['SubLedgerController']->getReport();
});

// 2. Xuất file (CSV/PDF)
$router->get('/api/reports/sub-ledger/export', function() use ($c) {
    $c['SubLedgerController']->export();
});

// 3. HTML preview (cho tab Xem trước)
$router->get('/api/reports/sub-ledger/preview', function() use ($c) {
    $c['SubLedgerController']->preview();
});
```

### 5.2 Request/Response Contract

**Request:**
```
GET /api/reports/sub-ledger
  ?type=general|cash_bank|bank_book|inventory|ar|ap|sales
  &account_code=111
  &period_id=42
  &entity_id=cus_001           (tùy chọn — lọc theo KH/NCC/vật tư)
  &entity_type=customer|supplier|item|employee
  &from_date=2025-01-01
  &to_date=2025-01-31
  &format=json                  (json|html)
```

**Response (JSON):**
```json
{
  "report_type": "cash_book",
  "account_code": "111",
  "account_name": "Tiền mặt Việt Nam",
  "period": {
    "id": 42,
    "code": "2025-01",
    "name": "Tháng 1/2025"
  },
  "opening_balance": 5000000,
  "transactions": [
    {
      "date": "2025-01-02",
      "reference": "PT000001",
      "description": "Thu tiền bán hàng",
      "contra_account": "511",
      "contra_account_name": "Doanh thu bán hàng",
      "debit": 10000000,
      "credit": 0,
      "running_balance": 15000000
    }
  ],
  "totals": {
    "debit": 50000000,
    "credit": 30000000
  },
  "closing_balance": 25000000
}
```

### 5.3 Endpoint cho từng loại sổ riêng (fallback)

Để maintain backward compatibility với frontend hiện tại:

| Endpoint | Method | Service |
|---|---|---|
| `/api/reports/general-ledger` | GET | GlService::getGeneralLedger() |
| `/api/reports/cash-book` | GET | CashService::getCashBook() (cải tiến) |
| `/api/reports/bank-book` | GET | New: BankBookReport |
| `/api/reports/inventory-ledger` | GET | New: InventoryLedgerReport |
| `/api/reports/ar-ledger` | GET | ArService (mở rộng) |
| `/api/reports/ap-ledger` | GET | ApService (mở rộng) |
| `/api/reports/sales-ledger` | GET | New: SalesLedgerReport |

---

## 6. UI/UX Flow

### 6.1 Navigation

```
Báo cáo (menu sidebar)
├── Sổ kế toán (submenu mới)
│   ├── Sổ cái (S05-DN) — đã có
│   ├── Sổ chi tiết tài khoản — đã có
│   ├── Sổ quỹ tiền mặt (S03a-DN) — cải tiến từ cash_book
│   ├── Sổ tiền gửi NH (S03b-DN) — MỚI
│   ├── Sổ kho (S12-DN) — MỚI
│   ├── Sổ chi tiết công nợ PT (S13-DN) — cải tiến
│   ├── Sổ chi tiết công nợ PTr (S13-DN) — cải tiến
│   └── Sổ chi tiết bán hàng (S33-DN) — MỚI
```

### 6.2 Unified Filter Bar (all reports)

```
┌─────────────────────────────────────────────────────────────────┐
│ [Loại sổ ▼] [Tài khoản ▼] [Kỳ ▼] [Đối tượng ▼] [Từ] → [Đến]  │
│ [Xem trước] [In PDF] [CSV] [Excel]                              │
└─────────────────────────────────────────────────────────────────┘
```

- **Loại sổ:** dropdown chọn type
- **Tài khoản:** dropdown động (load từ /api/gl/accounts)
- **Kỳ:** dropdown từ /api/periods (mặc định: kỳ hiện tại)
- **Đối tượng:** dropdown động (load từ /api/items, /api/ar/customers, /api/ap/suppliers tùy loại)
- **Từ/Đến ngày:** date input, ưu tiên hơn kỳ

### 6.3 Table Display & Interaction

- **Sticky header:** khi scroll, header luôn ở trên cùng
- **Striped rows:** `table-striped` Bootstrap
- **Highlights:** dòng "Dư đầu kỳ" và "Cộng phát sinh" có màu nền riêng
- **Pagination:** 100 rows/page (chỉ cho HTML, PDF in full)
- **Tooltip:** hover trên reference → show tooltip với transaction id

### 6.4 View Template Pattern

Mỗi view kế thừa layout.php, dùng pattern:

```php
<?php
$title = 'Sổ kho (S12-DN)';
$activeMenu = 'inventory_ledger';
ob_start();
?>
<!-- Filter bar -->
<div class="card p-3 mb-3 border-0 shadow-sm">
    <div class="row g-2 align-items-end">
        <!-- dynamic filters via JS -->
    </div>
</div>

<!-- Report header + Summary -->
<div id="reportHeader" class="mb-2"></div>

<!-- Transaction table -->
<div class="card-table">
    <table class="table table-hover table-sm" id="reportTable">
        <thead id="tableHead"></thead>
        <tbody id="dataBody"></tbody>
    </table>
</div>

<script>
function loadReport() { /* AJAX call to /api/reports/sub-ledger */ }
function printReport() { /* window.print() or /api/reports/sub-ledger/export?format=pdf */ }
function exportCsv()   { /* window.location = /api/reports/sub-ledger/export?format=csv */ }
$(document).ready(function() { loadReport(); });
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
```

---

## 7. Business Rules

### 7.1 Validation Rules

| Rule | Type | Message |
|---|---|---|
| Phải chọn ít nhất một tài khoản hoặc một đối tượng | Block | "Vui lòng chọn tài khoản hoặc đối tượng" |
| Không chọn cả tài khoản tổng hợp lẫn tài khoản con (gây nhầm lẫn) | Warn | "Tài khoản 111 là tài khoản tổng hợp. Bạn có muốn xem tất cả tài khoản con?" |
| Kỳ không tồn tại | Block | "Không tìm thấy kỳ kế toán" |
| Ngày bắt đầu > ngày kết thúc | Block | "Ngày bắt đầu phải trước ngày kết thúc" |
| Khoảng thời gian > 12 tháng | Warn | "Khoảng thời gian vượt quá 12 tháng. Báo cáo sẽ chậm." |
| Không có giao dịch trong kỳ | Block | "Không có giao dịch trong kỳ" (không tạo PDF) |

### 7.2 Period & Date Handling

- Nếu chọn `period_id`: bỏ qua `from_date`/`to_date` → dùng `period.start_date` → `period.end_date`
- Nếu không chọn period và không có date range: mặc định tháng hiện tại
- Kỳ đã đóng: vẫn cho phép xem sổ (read-only)
- Ngày chứng từ phải nằm trong kỳ báo cáo được chọn

### 7.3 Control Account Warning

Khi chọn tài khoản tổng hợp (control account: 111, 112, 131, 331, 333, 411, 421):

```php
// GlService / SubLedgerService
if ($account->isControl()) {
    // Block: không cho xem trực tiếp
    // Gợi ý: "Tài khoản {code} là tài khoản tổng hợp.
    //          Vui lòng chọn tài khoản cấp dưới để xem chi tiết."
    // Cung cấp nút "Xem tất cả tài khoản con" → gọi report cho từng TK con riêng
}
```

### 7.4 Empty Report Policy

- Nếu `opening_balance = 0` AND `transactions = []`: trả về lỗi "Không có dữ liệu"
- Không tạo file PDF rỗng
- CSV vẫn tạo với header + dòng "Không có dữ liệu"

---

## 8. Service & Controller Design

### 8.1 SubLedgerService (Factory)

```php
namespace Accounting\Domain\Service;

// File: src/Accounting/Domain/Service/SubLedgerService.php

class SubLedgerService
{
    private \PDO $pdo;
    private GlService $gl;
    private CashService $cash;
    private ArService $ar;
    private ApService $ap;
    private InventoryService $inventory;
    private PeriodService $period;

    // Factory method — trả về implementation phù hợp
    public function createReport(string $type, array $params): SubLedgerReportInterface
    {
        return match($type) {
            'general'         => new GeneralLedgerReport($this->pdo, $this->gl, $params),
            'cash_book'       => new CashBookReport($this->pdo, $this->cash, $params),
            'bank_book'       => new BankBookReport($this->pdo, $params),
            'inventory'       => new InventoryLedgerReport($this->pdo, $this->inventory, $params),
            'ar'              => new ArLedgerReport($this->pdo, $this->ar, $params),
            'ap'              => new ApLedgerReport($this->pdo, $this->ap, $params),
            'sales'           => new SalesLedgerReport($this->pdo, $this->ar, $params),
            default           => throw new \InvalidArgumentException("Loại sổ không hợp lệ: {$type}"),
        };
    }

    // Danh sách loại sổ cho frontend dropdown
    public function getReportTypes(): array
    {
        return [
            ['type' => 'general',     'name' => 'Sổ cái (S05-DN)',           'icon' => 'bi-journal'],
            ['type' => 'cash_book',   'name' => 'Sổ quỹ tiền mặt (S03a-DN)', 'icon' => 'bi-cash'],
            ['type' => 'bank_book',   'name' => 'Sổ TGNH (S03b-DN)',         'icon' => 'bi-bank'],
            ['type' => 'inventory',   'name' => 'Sổ kho (S12-DN)',           'icon' => 'bi-box'],
            ['type' => 'ar',          'name' => 'Sổ chi tiết PT (S13-DN)',    'icon' => 'bi-person-up'],
            ['type' => 'ap',          'name' => 'Sổ chi tiết PTr (S13-DN)',   'icon' => 'bi-person-down'],
            ['type' => 'sales',       'name' => 'Sổ bán hàng (S33-DN)',      'icon' => 'bi-receipt'],
        ];
    }
}
```

### 8.2 SubLedgerController

```php
namespace Accounting\Interfaces\HTTP;

// File: src/Accounting/Interfaces/HTTP/SubLedgerController.php

class SubLedgerController
{
    private SubLedgerService $service;
    private PeriodService $period;

    public function __construct(SubLedgerService $service, PeriodService $period)
    {
        $this->service = $service;
        $this->period = $period;
    }

    public function getReport(): void
    {
        Auth::requirePermission('report', 'read');
        $type = $_GET['type'] ?? 'general';
        $params = $this->buildParams();

        try {
            $report = $this->service->createReport($type, $params);
            JsonResponse::ok([
                'report_type' => $report->getReportType(),
                'account_code' => $params['account_code'] ?? '',
                'period' => $this->period->getPeriod($params['period_id'] ?? 0),
                'opening_balance' => $report->getOpeningBalance(),
                'transactions' => $report->getTransactions(),
                'totals' => $report->getTotals(),
                'closing_balance' => $report->getClosingBalance(),
            ]);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function export(): void
    {
        Auth::requirePermission('report', 'export');
        $type = $_GET['type'] ?? 'general';
        $format = $_GET['format'] ?? 'pdf';
        $params = $this->buildParams();

        try {
            $report = $this->service->createReport($type, $params);
            $result = $report->export($format);
            header("Content-Type: {$result['mime']}");
            header("Content-Disposition: attachment; filename=\"{$result['filename']}\"");
            echo $result['content'];
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    private function buildParams(): array
    {
        return [
            'account_code' => $_GET['account_code'] ?? null,
            'period_id' => isset($_GET['period_id']) ? (int)$_GET['period_id'] : null,
            'entity_id' => $_GET['entity_id'] ?? null,
            'entity_type' => $_GET['entity_type'] ?? null,
            'from_date' => $_GET['from_date'] ?? null,
            'to_date' => $_GET['to_date'] ?? null,
        ];
    }
}
```

---

## 9. Permissions

Thêm module `report` với actions:

| Module | Action | Mô tả |
|---|---|---|
| `report` | `read` | Xem báo cáo sổ chi tiết |
| `report` | `export` | Xuất CSV/PDF (được phép nếu có read) |
| `report` | `print` | In PDF (tương tự export) |

Cần seed vào RBAC:
```sql
INSERT IGNORE INTO permissions (module, action, description) VALUES
('report', 'read', 'Xem báo cáo'),
('report', 'export', 'Xuất báo cáo'),
('report', 'print', 'In báo cáo');
```

---

## 10. Implementation Checklist

### Phase 1 — Core Reports (Day 1-2)

```
[ ] 1. Interface: src/Accounting/Domain/Contract/SubLedgerReportInterface.php
[ ] 2. Base: src/Accounting/Domain/Service/SubLedger/AbstractSubLedgerReport.php
[ ] 3. GeneralLedgerReport (wraps GlService)
[ ] 4. CashBookReport (cải tiến từ CashService::getCashBook)
[ ] 5. BankBookReport (mới — query ledger_entries TK 112)
[ ] 6. Service: SubLedgerService (factory + routing)
[ ] 7. Controller: SubLedgerController
[ ] 8. Routes: config/routes/api_sub_ledger.php
[ ] 9. DI: config/services.php (đăng ký SubLedgerService + SubLedgerController)
```

### Phase 2 — Inventory + AR/AP Reports (Day 2-3)

```
[ ] 10. InventoryLedgerReport (query ledger_entries + cost layers)
[ ] 11. ArLedgerReport (wraps ArService + ar_invoices + ar_payments)
[ ] 12. ApLedgerReport (wraps ApService + ap_invoices + ap_payments)
```

### Phase 3 — Sales Report + Print (Day 3-4)

```
[ ] 13. SalesLedgerReport (query AR invoices + transactions)
[ ] 14. View: public/views/sub-ledger.php (unified filter bar + table)
[ ] 15. Print template: views/sub-ledger-print.php (HTML + CSS print media)
[ ] 16. Export: CSV support trong AbstractSubLedgerReport
[ ] 17. Sidebar: cập nhật layout.php với menu Báo cáo → Sổ kế toán
```

### Phase 4 — Quality + Tests (Day 4)

```
[ ] 18. Tests: tests/SubLedgerTest.php (happy path + failure)
[ ] 19. Permissions: seed report.* vào RBAC
[ ] 20. Audit: AuditLogger::log() trong controller
[ ] 21. PHP syntax check: php -l trên mọi file mới
[ ] 22. Full test suite: for f in tests/*.php; do php "$f"; done — 0 failures
```

---

## 11. Effort Estimate & Phasing

| Phase | Scope | Effort | Dependencies |
|---|---|---|---|
| 1 | Interface + General/Cash/Bank + Service + Controller | 2 days | GlService, CashService exist |
| 2 | Inventory + AR/AP reports | 1 day | InventoryService, ArService, ApService exist |
| 3 | Sales report + PDF/CSV export + Views | 1 day | Phase 1, Phase 2 |
| 4 | Tests + Quality | 0.5 day | All phases |

**Tổng:** 3-4.5 days (conservative), 2-3 days (optimistic)

### 11.1 Zero-New-DB-Tables Guarantee

All reports use existing tables:
| Report | Tables Used |
|---|---|
| GeneralLedger | `ledger_entries`, `transactions`, `accounts` |
| CashBook | `ledger_entries`, `transactions`, `accounts` |
| BankBook | `ledger_entries`, `transactions`, `accounts` |
| InventoryLedger | `ledger_entries`, `transactions`, `accounts`, `items`, `inventory_cost_layers` |
| ArLedger | `ar_invoices`, `ar_payments`, `customers`, `payment_allocations` (optional) |
| ApLedger | `ap_invoices`, `ap_payments`, `suppliers`, `payment_allocations` (optional) |
| SalesLedger | `ar_invoices`, `customers`, `ledger_entries`, `transactions` |

---

## 12. Risk Register

| ID | Risk | Impact | Mitigation |
|---|---|---|---|
| R1 | inventory_cost_layers không có transaction_id → không trace được giá xuất theo từng CT | Medium | Dùng unit_cost bình quân tại thời điểm query; thêm migration thêm cột transaction_id cho inventory_cost_layers nếu cần chính xác tuyệt đối |
| R2 | running balance sai nếu có bút toán điều chỉnh (correction) | High | Tính running balance theo thứ tự thời gian + id; bút toán correction có cùng ngày nhưng id lớn hơn |
| R3 | PDF in ra không đúng kích thước TT 99 | Medium | CSS print media + @page A4 landscape; test in thử trước khi release |
| R4 | Hiệu năng: query ledger_entries không index cho account_code + created_at | Medium | Đảm bảo index trên: `ledger_entries(account_id, transaction_id)`, `transactions(created_at)` |
| R5 | Số dư đầu kỳ sai nếu query không bao gồm tất cả giao dịch trước ngày bắt đầu | High | Dùng `t.created_at < from_date` (strictly less than), không dùng `<=` |

---

## 13. Open Questions / Future Work

1. **Excel (XLSX) export** — Hiện tại không có PhpSpreadsheet vì không dùng Composer (§4.2 constraint). Có thể thêm XLSX bằng CSV (đủ dùng) hoặc thêm thư viện nhúng đơn file (PhpXlsxWriter chỉ là 1 file PHP, không cần Composer).

2. **Batch print** — In nhiều sổ cùng lúc (ví dụ: in tất cả sổ chi tiết TK 131 cho tất cả KH trong 1 PDF).

3. **Email report** — Gửi PDF sổ chi tiết qua email cho KH/NCC định kỳ.

4. **Reconciliation integration** — So sánh sổ kho (InventoryLedger) với sổ cái TK 152/156 để phát hiện chênh lệch.

5. **Multi-currency** — Sổ quỹ và sổ NH hiển thị cả VND và nguyên tệ (USD, EUR).

---

## 14. Definition of Done

```
[ ] SubLedgerReportInterface defined
[ ] GeneralLedgerReport, CashBookReport, BankBookReport implement interface
[ ] InventoryLedgerReport, ArLedgerReport, ApLedgerReport, SalesLedgerReport implement interface
[ ] SubLedgerService factory + routing
[ ] SubLedgerController with getReport(), export(), preview()
[ ] Routes registered in config/routes.php
[ ] DI container updated in config/services.php
[ ] View at public/views/sub-ledger.php
[ ] Print template with CSS print media
[ ] CSV export works for all report types
[ ] Sidebar updated in layout.php
[ ] RBAC permissions seeded
[ ] Tests: tests/SubLedgerTest.php — 0 failures
[ ] Full suite: for f in tests/*.php; do php "$f"; done — 0 failures
[ ] PHP syntax: php -l trên mọi file thay đổi
[ ] AuditLogger::log() for export actions
```
