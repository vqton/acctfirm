# Gap 01: Sales Order Module — Parity Specification

> **Mức độ hiện tại:** 0/10 — **Mục tiêu:** 9/10  
> **Phạm vi:** Toàn bộ quy trình bán hàng doanh nghiệp Việt Nam  
> **Ngày:** 02/06/2026  
> **Tài liệu tham chiếu:** `10-gaps-use-cases-consolidated.md`, `AGENTS.md §1-§10`, MISA helpsme sales flow, Thông tư 99/2025/TT-BTC

---

## 1. Business Context & Rationale

### 1.1 Why This Matters

Bookwise hiện ghi nhận doanh thu qua JournalService (bút toán tay: Nợ 131 / Có 511) — không có quy trình bán hàng end-to-end. Kế toán phải nhập 3-4 chứng từ riêng rẽ cho một giao dịch bán hàng: hóa đơn AR → phiếu xuất kho → phiếu thu → hóa đơn điện tử. Mỗi bước nhập tay làm tăng nguy cơ sai lệch dữ liệu và mất audit trail từ đơn hàng đến doanh thu.

### 1.2 Competitive Landscape

| Phần mềm | Sales Order | Quotation | Credit Notes | E-Invoice Link |
|---|---|---|---|---|
| MISA helpsme | ✅ 7+ kiểu bán hàng | ✅ Có | ✅ Tự động | ✅ TT32 |
| BRAVO | ✅ Đầy đủ | ✅ Có | ✅ Tự động | ✅ TT32 |
| FAST | ✅ Đầy đủ | ✅ Có | ✅ Tự động | ✅ TT32 |
| EasyBooks | ✅ Cơ bản | ❌ | ✅ | ✅ |
| **Bookwise** | **❌ KHÔNG CÓ** | **❌** | **❌** | **⚠️ Qua InvoiceService** |

### 1.3 Market Impact

Đây là P0 gap. Thiếu Sales Order module đồng nghĩa với không thể cạnh tranh trong phân khúc SME ERP tại Việt Nam. Mọi đối thủ đều có O2C (Order-to-Cash) đầy đủ. Khách hàng SME kỳ vọng: báo giá → đơn hàng → xuất kho → hóa đơn → thu tiền trong một luồng duy nhất.

### 1.4 Regulatory Requirements

- **Thông tư 99/2025/TT-BTC §15:** Chứng từ bán hàng phải có số thứ tự tăng dần, liên tục
- **Thông tư 32/2025/TT-BTC:** Hóa đơn điện tử phải phát hành trong ngày giao hàng
- **Luật Quản lý thuế 38/2019/QH14:** Hóa đơn bán hàng phải phản ánh đúng doanh thu
- **VAS 14 (Doanh thu):** Doanh thu ghi nhận khi chuyển giao rủi ro và lợi ích

---

## 2. Module Architecture

### 2.1 Service Layer

```
SalesOrderService (CORE — oracle của toàn bộ O2C)
├── SalesOrderRepositoryInterface  (CRUD đơn hàng)
├── JournalServiceInterface         (bút toán kế toán)
├── InventoryServiceInterface       (kiểm tra/xuất kho)
├── ArService                      (công nợ phải thu)
├── CashServiceInterface           (thu tiền)
├── VoucherService                 (sinh số chứng từ — prefix SO)
├── InvoiceService                 (hóa đơn điện tử TT32)
├── EInvoiceGatewayInterface       (cổng T-VAN)
├── ConfigService                  (ngưỡng, quy tắc)
└── AuditLoggerInterface           (ghi audit trail)
```

### 2.2 New Files

| Layer | File | Purpose |
|---|---|---|
| Migration | `database/migrations/100_sales_orders.sql` | Tables |
| Model | `Domain/Model/SalesOrder.php` | Entity |
| Model | `Domain/Model/SalesOrderLine.php` | Value object |
| Repository Interface | `Domain/Repository/SalesOrderRepositoryInterface.php` | Contract |
| PDO Repository | `Infrastructure/Repository/PDOSalesOrderRepository.php` | Implementation |
| Service | `Domain/Service/SalesOrderService.php` | Core business logic |
| Controller | `Interfaces/HTTP/Sales/SalesOrderController.php` | API handlers |
| View | `public/views/sales_orders.php` | List view |
| View | `public/views/sales_order_form.php` | Create/edit form |
| Test | `tests/SalesOrderTest.php` | Integration tests |

### 2.3 Config Keys (business_config table)

| Key | Type | Default | Purpose |
|---|---|---|---|
| `sales.auto_create_invoice` | boolean | false | Tự động tạo hóa đơn khi xác nhận đơn |
| `sales.auto_create_delivery` | boolean | true | Tự động tạo phiếu xuất khi xác nhận |
| `sales.discount_approval_threshold` | decimal | 5000000 | Chiết khấu > X VND cần duyệt |
| `sales.max_credit_days` | int | 60 | Số ngày tối đa cho phép trả chậm |
| `sales.duplicate_order_window_hours` | int | 24 | Cửa sổ phát hiện đơn trùng |
| `sales.default_vat_rate` | decimal | 10 | Thuế suất GTGT mặc định |
| `sales.require_customer_tax_code` | boolean | false | Bắt buộc MST KH cho HĐ ĐT |
| `sales.inventory_check_on_confirm` | boolean | true | Kiểm tra tồn khi xác nhận |
| `sales.allow_partial_shipment` | boolean | true | Cho phép xuất một phần |
| `sales.price_override_approval_threshold` | decimal | 0 | % thay đổi giá cần duyệt |

---

## 3. Data Model

### 3.1 `sales_orders`

```sql
CREATE TABLE sales_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(20) NOT NULL COMMENT 'Số đơn hàng: SO{YYYY}-{000000}',
    customer_id INT UNSIGNED NOT NULL,
    order_date DATE NOT NULL,
    delivery_date DATE DEFAULT NULL,
    payment_terms VARCHAR(50) DEFAULT NULL COMMENT 'Điều khoản thanh toán: net_30, cod, deposit_50',
    payment_method VARCHAR(20) DEFAULT NULL COMMENT 'cash|bank|transfer|card|cod',
    status VARCHAR(30) NOT NULL DEFAULT 'draft'
        COMMENT 'draft|confirmed|pending_stock|partially_shipped|shipped|partially_invoiced|invoiced|partially_paid|paid|completed|cancelled',
    currency VARCHAR(3) NOT NULL DEFAULT 'VND',
    exchange_rate DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Tổng tiền hàng chưa thuế',
    discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    grand_total DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Tổng thanh toán',
    amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Đã thanh toán',
    amount_invoiced DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Đã xuất hóa đơn',
    notes TEXT DEFAULT NULL,
    is_quotation_converted TINYINT(1) NOT NULL DEFAULT 0,
    quotation_id INT UNSIGNED DEFAULT NULL COMMENT 'Báo giá gốc nếu chuyển từ quotation',
    created_by VARCHAR(100) NOT NULL,
    approved_by VARCHAR(100) DEFAULT NULL,
    cancelled_by VARCHAR(100) DEFAULT NULL,
    cancel_reason TEXT DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reference (reference),
    INDEX idx_customer (customer_id),
    INDEX idx_status (status),
    INDEX idx_order_date (order_date),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 `sales_order_lines`

```sql
CREATE TABLE sales_order_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sales_order_id INT UNSIGNED NOT NULL,
    line_no SMALLINT UNSIGNED NOT NULL COMMENT 'Số thứ tự dòng',
    item_id INT UNSIGNED DEFAULT NULL COMMENT 'NULL nếu là dịch vụ/mô tả tự do',
    item_code VARCHAR(50) DEFAULT NULL,
    item_name VARCHAR(255) NOT NULL,
    unit VARCHAR(30) DEFAULT NULL,
    qty_ordered DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    qty_shipped DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    qty_invoiced DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Sau chiết khấu, trước thuế',
    is_service TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = dịch vụ, không cần xuất kho',
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    INDEX idx_so_id (sales_order_id),
    INDEX idx_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.3 `sales_order_links`

```sql
CREATE TABLE sales_order_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sales_order_id INT UNSIGNED NOT NULL,
    linked_type VARCHAR(30) NOT NULL COMMENT 'sales_invoice|delivery_order|credit_note|receipt|e_invoice|return',
    linked_id VARCHAR(100) NOT NULL COMMENT 'ID của chứng từ liên kết (transaction_id, ar_invoice_id, e_invoice_id)',
    linked_reference VARCHAR(30) DEFAULT NULL COMMENT 'Số chứng từ liên kết (để hiển thị)',
    amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    notes VARCHAR(255) DEFAULT NULL,
    created_by VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_so_id (sales_order_id),
    INDEX idx_linked (linked_type, linked_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.4 `quotations`

```sql
CREATE TABLE quotations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(20) NOT NULL COMMENT 'Số báo giá: Q{YYYY}-{000000}',
    customer_id INT UNSIGNED NOT NULL,
    quotation_date DATE NOT NULL,
    valid_until DATE DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft'
        COMMENT 'draft|sent|accepted|rejected|expired|converted',
    currency VARCHAR(3) NOT NULL DEFAULT 'VND',
    exchange_rate DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    grand_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    notes TEXT DEFAULT NULL,
    terms_conditions TEXT DEFAULT NULL,
    sales_order_id INT UNSIGNED DEFAULT NULL COMMENT 'Đơn hàng được tạo từ báo giá này',
    created_by VARCHAR(100) NOT NULL,
    approved_by VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reference (reference),
    INDEX idx_customer (customer_id),
    INDEX idx_status (status),
    INDEX idx_date (quotation_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.5 `quotation_lines`

```sql
CREATE TABLE quotation_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quotation_id INT UNSIGNED NOT NULL,
    line_no SMALLINT UNSIGNED NOT NULL,
    item_id INT UNSIGNED DEFAULT NULL,
    item_code VARCHAR(50) DEFAULT NULL,
    item_name VARCHAR(255) NOT NULL,
    unit VARCHAR(30) DEFAULT NULL,
    qty DECIMAL(15,2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    is_service TINYINT(1) NOT NULL DEFAULT 0,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    INDEX idx_q_id (quotation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Process Flows

### 4.1 Order-to-Cash (O2C) Complete Lifecycle

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  Báo giá    │ ──▶ │ Đơn hàng    │ ──▶ │  Xuất kho   │ ──▶ │ Hóa đơn ĐT  │ ──▶ │   Thu tiền  │
│ (Quotation) │     │ (SalesOrd)  │     │ (Delivery)  │     │ (E-Invoice) │     │  (Receipt)  │
└─────────────┘     └─────────────┘     └─────────────┘     └─────────────┘     └─────────────┘
                          │                    │                    │                    │
                     Kiểm tra kho        Ghi nhận giá vốn     Ghi nhận doanh thu    Ghi nhận tiền
                     (InventorySvc)      Nợ 632 / Có 15x      Nợ 131 / Có 511      Nợ 111/112
                                          + 33311               Có 131
```

**Happy Path chi tiết:**

1. **Nhân viên bán hàng** tạo Quotation (Báo giá) cho khách hàng
2. KH chấp nhận báo giá → `convertToOrder()`: copy quotation_lines → sales_order_lines, status = `confirmed`
3. **Hệ thống** kiểm tra tồn kho: đủ → tiếp tục; thiếu → `pending_stock`, gửi thông báo
4. **Tùy chọn (config key `sales.auto_create_delivery`):**
   - Nếu `true`: tự động gọi `InventoryService::issueGoods()` → ghi nhận Giá vốn (Nợ 632 / Có 156)
   - Cập nhật `qty_shipped` trên mỗi sales_order_line
   - Tạo `sales_order_links` với `linked_type = 'delivery_order'`
5. **Tùy chọn (config key `sales.auto_create_invoice`):**
   - Nếu `true`: tự động gọi `ArService::recordInvoice()` + `InvoiceService::createFromTransaction()`
   - Tạo bút toán: Nợ 131 / Có 511 + Có 33311
   - Gọi EInvoiceGateway để phát hành hóa đơn điện tử
   - Cập nhật `qty_invoiced`
   - Tạo `sales_order_links` với `linked_type = 'sales_invoice'` và `'e_invoice'`
6. **Thu tiền:** Nếu `payment_method = 'cash'` hoặc `'bank'`:
   - Tự động gọi `CashService::recordReceipt()` hoặc `recordBankReceipt()`
   - Hoặc gọi `ArService::recordPayment()` — Nợ 111/112 / Có 131
   - Tạo `sales_order_links` với `linked_type = 'receipt'`
7. **Kết thúc:** `amount_paid >= grand_total` → status = `paid` → `completed`

### 4.2 Sales Order States

```
                    ┌──────────┐
                    │  draft   │
                    └────┬─────┘
                         │ confirm()
                    ┌────▼──────┐
                    │ confirmed │──────────────┐
                    └────┬──────┘              │
                         │ check stock         │ out of stock
                    ┌────▼──────────┐    ┌─────▼──────────┐
                    │ pending_stock │    │ confirmed      │
                    └────┬──────────┘    └─────┬──────────┘
                         │ stock arrives      │ ship()
                    ┌────▼──────────┐    ┌─────▼──────────┐
                    │ partially_    │    │    shipped     │
                    │ shipped       │◀───┘                │
                    └────┬──────────┘    └─────┬──────────┘
                         │ ship fully         │ invoice()
                    ┌────▼──────────┐    ┌─────▼────────────┐
                    │   shipped    │    │ partially_invoice │
                    └────┬─────────┘    └─────┬─────────────┘
                         │ invoice()          │ invoice fully
                    ┌────▼──────────┐    ┌─────▼──────────┐
                    │ partially_   │    │   invoiced     │
                    │ invoiced     │◀───┘                │
                    └────┬──────────┘    └─────┬──────────┘
                         │ invoice fully      │ pay()
                    ┌────▼──────────┐    ┌─────▼──────────┐
                    │   invoiced   │    │ partially_paid │
                    └────┬─────────┘    └─────┬───────────┘
                         │ pay()              │ pay fully
                    ┌────▼──────────┐    ┌─────▼──────────┐
                    │ partially_   │    │     paid       │
                    │ paid         │◀───┘                │
                    └────┬──────────┘    └─────┬──────────┘
                         │ pay fully           │
                    ┌────▼──────────┐          │
                    │    paid      │───────────┘
                    └────┬─────────┘
                         │ auto-complete
                    ┌────▼──────────┐
                    │  completed   │
                    └───────────────┘

CANCEL (từ bất kỳ state nào ngoại trừ paid/completed):
    ┌─────────────────────┐
    │     cancelled       │
    └─────────────────────┘
```

**State Transition Matrix:**

| Current State | Allowed Transitions | Action |
|---|---|---|
| `draft` | `confirmed`, `cancelled` | confirm(), cancel() |
| `confirmed` | `pending_stock`, `partially_shipped`, `shipped`, `cancelled` | checkStock(), ship() |
| `pending_stock` | `confirmed` (khi hàng về), `cancelled` | restock() |
| `partially_shipped` | `shipped`, `cancelled` | ship() |
| `shipped` | `partially_invoiced`, `invoiced`, `cancelled` | invoice() |
| `partially_invoiced` | `invoiced`, `cancelled` | invoice() |
| `invoiced` | `partially_paid`, `paid`, `cancelled` | pay() |
| `partially_paid` | `paid`, `cancelled` | pay() |
| `paid` | `completed` (auto) | — |
| `completed` | — | Terminal |
| `cancelled` | — | Terminal |

### 4.3 Alternative Paths

**4.3.1 Direct Sales (No Quotation)**

Nhân viên bán hàng tạo thẳng SalesOrder mà không qua Quotation. `is_quotation_converted = 0`, `quotation_id = NULL`.

**4.3.2 Service Sales (No Delivery)**

Khi tất cả dòng có `is_service = 1`:
- Bỏ qua bước xuất kho (không gọi InventoryService)
- Chuyển thẳng từ `confirmed` → `invoiced` sau khi invoice
- Hạch toán giống nhưng không có bút toán giá vốn (Nợ 632 / Có 156) — hoặc ghi nhận chi phí dịch vụ riêng

**4.3.3 Deposit/Advance Payment**

KH trả trước một phần:
1. Ghi nhận tiền ứng trước: `ArService::recordPrepayment()` — Nợ 111/112 / Có 131
2. Tạo `sales_order_links` với `linked_type = 'deposit'`
3. Khi xuất hóa đơn: trừ số dư tạm ứng vào tổng tiền
4. Nếu `payment_method = 'deposit_50'` và đã nhận đủ 50% → mới cho phép `confirm()`

**4.3.4 Partial Delivery & Partial Invoicing**

- `ship(qty)`: Chỉ xuất một phần, cập nhật `qty_shipped < qty_ordered`
- `invoice(qty)`: Chỉ xuất hóa đơn một phần, cập nhật `qty_invoiced < qty_shipped`
- State machine xử lý qua các state trung gian: `partially_shipped`, `partially_invoiced`
- Tồn kho chỉ giảm khi xuất thực tế (không giảm toàn bộ khi xác nhận)

**4.3.5 Credit Note / Return**

Flow:
1. KH trả hàng → tạo CreditNote từ SalesOrder cũ
2. Gọi `InventoryService::returnFromCustomer()`: Nợ 156 / Có 632 (hàng nhập lại kho)
3. Gọi `JournalService::postEntry()` đảo ngược doanh thu: Nợ 521 / Có 131
4. Gọi `EInvoiceGatewayInterface::replace()` nếu đã phát hành hóa đơn
5. Tạo `sales_order_links` với `linked_type = 'credit_note'`
6. Cập nhật `amount_invoiced` giảm tương ứng

### 4.4 Exception Paths

**4.4.1 Order Cancellation**

| Scenario | Allowed? | Action Required |
|---|---|---|
| Draft → cancelled | ✅ Direct | Clear lines, free stock reservation |
| Confirmed → cancelled | ✅ If no delivery/invoice | Reverse any stock reservation |
| After partial shipment | ✅ Partial | Auto-create return for shipped qty |
| After invoice | ⚠️ Complex | Must cancel invoice first (EInvoiceGateway) |
| After full payment | ❌ Blocked | Customer refund as separate process |
| After e-invoice published | ⚠️ Required | `EInvoiceGateway::cancel()` + adjustment journal |

**4.4.2 Out of Stock**

- Tại confirm: nếu `$service->checkStockAvailability()` trả về thiếu → status = `pending_stock`
- Trả về danh sách mặt hàng thiếu kèm số lượng tồn thực tế
- Gửi thông báo (notifications) cho nhân viên kho
- Khi hàng về: gọi `restock(orderId)` để chuyển `pending_stock` → `confirmed`

**4.4.3 Customer Credit Limit Exceeded**

- Kiểm tra tại `confirm()`: `ArService::getCustomerBalance(customerId) + orderTotal > creditLimit`
- Nếu vượt → block với message: "Đơn hàng vượt quá hạn mức tín dụng của khách hàng"
- Hạn mức lấy từ `customers.credit_limit`
- Nếu KH chưa có hạn mức (NULL) → bỏ qua kiểm tra
- Kế toán trưởng có thể override qua tham số `$bypassCreditLimit = true`

**4.4.4 Price Change After Confirmation**

- Nguyên tắc: Giá cố định sau confirm — không cho sửa unit_price nếu status >= `confirmed`
- Nếu cần thay đổi: cancel order → tạo order mới với giá mới
- Exception: Kế toán trưởng có quyền price override thông qua `ConfigService::get('sales.price_override_approval_threshold')`

---

## 5. Journal Entries

### 5.1 Deposit Received (Khách hàng ứng trước)

| TK | Tên | Nợ | Có | Ghi chú |
|---|---|---|---|---|
| 111/112 | Tiền mặt/Ngân hàng | ✓ | | Số tiền nhận ứng trước |
| 131 | Phải thu KH (chi tiết KH) | | ✓ | Ghi nhận công nợ âm (dư Có 131 = phải trả KH) |

### 5.2 Revenue Recognition (Ghi nhận doanh thu — khi xuất hóa đơn)

| TK | Tên | Nợ | Có | Ghi chú |
|---|---|---|---|---|
| 131 | Phải thu KH (chi tiết KH) | ✓ | | Tổng giá thanh toán (gồm VAT) |
| 511 | Doanh thu bán hàng | | ✓ | Giá bán chưa thuế |
| 33311 | Thuế GTGT đầu ra | | ✓ | VAT tính trên doanh thu |

Nếu có ứng trước: ghi giảm số dư 131 thay vì tăng:
| 131 | Phải thu KH | ✓ | | Chỉ phần còn lại sau khi trừ ứng trước |
| 511 | Doanh thu | | ✓ | |
| 33311 | Thuế GTGT | | ✓ | |

### 5.3 COGS (Giá vốn hàng bán — khi xuất kho)

| TK | Tên | Nợ | Có | Ghi chú |
|---|---|---|---|---|
| 632 | Giá vốn hàng bán | ✓ | | Tính theo FIFO/bình quân từ InventoryService |
| 156 | Hàng hóa | | ✓ | Giảm tồn kho tương ứng |

### 5.4 Payment Received (Thu tiền)

| TK | Tên | Nợ | Có | Ghi chú |
|---|---|---|---|---|
| 111/112 | Tiền mặt/Ngân hàng | ✓ | | Số tiền KH thanh toán |
| 131 | Phải thu KH | | ✓ | Giảm công nợ |

### 5.5 Return / Credit Note (Hàng trả lại)

| TK | Tên | Nợ | Có | Ghi chú |
|---|---|---|---|---|
| 156 | Hàng hóa (nhập lại kho) | ✓ | | Giá vốn hàng trả |
| 632 | Giá vốn hàng bán | | ✓ | Hoàn nhập giá vốn |
| 521 | Giảm trừ doanh thu | ✓ | | Doanh thu hàng trả (chưa thuế) |
| 33311 | Thuế GTGT đầu ra | ✓ | | Giảm thuế GTGT |
| 131 | Phải thu KH | | ✓ | Giảm công nợ (hoặc hoàn tiền) |

### 5.6 Discount (Chiết khấu thương mại)

| TK | Tên | Nợ | Có | Ghi chú |
|---|---|---|---|---|
| 521 | Giảm trừ doanh thu | ✓ | | Số tiền chiết khấu |
| 131 | Phải thu KH | | ✓ | Giảm công nợ |

### 5.7 Discount (Chiết khấu thanh toán — KH trả sớm)

| TK | Tên | Nợ | Có | Ghi chú |
|---|---|---|---|---|
| 635 | Chi phí tài chính | ✓ | | Số tiền chiết khấu |
| 131 | Phải thu KH | | ✓ | Giảm công nợ |

---

## 6. Business Rules & Validation Matrix

| ID | Rule | Severity | Condition | Error Message |
|---|---|---|---|---|
| SO-001 | Tồn kho đủ tại confirm | block | `stockQty < qtyOrdered && !allowNegativeStock` | "Mặt hàng [X] không đủ tồn kho. Hiện có [Y], cần [Z]." |
| SO-002 | Hạn mức tín dụng | block | `customerBalance + orderTotal > creditLimit` | "Đơn hàng vượt quá hạn mức tín dụng [X VND]. Vui lòng liên hệ Kế toán trưởng." |
| SO-003 | Ngưỡng duyệt chiết khấu | block | `discountAmount > config('sales.discount_approval_threshold') && !approvedByManager` | "Chiết khấu [X VND] vượt ngưỡng cho phép. Cần người có quyền duyệt." |
| SO-004 | Thay đổi giá sau confirm | block | `status >= confirmed && priceChanged && !$isOverride` | "Không thể thay đổi giá sau khi đã xác nhận đơn hàng. Vui lòng hủy và tạo đơn mới." |
| SO-005 | Sửa dòng đã xuất hóa đơn | block | `qtyInvoiced > 0 && lineModified` | "Dòng [X] đã được xuất hóa đơn. Không thể sửa. Vui lòng tạo credit note." |
| SO-006 | Hủy đơn sau thanh toán | block | `amountPaid >= grandTotal || status === paid` | "Đơn hàng đã thanh toán đủ. Không thể hủy. Vui lòng tạo phiếu hoàn tiền riêng." |
| SO-007 | Phát hiện đơn trùng | warn | `sameCustomer + sameItems + within24h` | "Khách hàng [X] đã đặt các mặt hàng tương tự trong 24h qua (Đơn [Y]). Có thể là đơn trùng?" |
| SO-008 | Bắt buộc nhập KH | block | `!customerId` | "Vui lòng chọn khách hàng." |
| SO-009 | Số lượng > 0 | block | `qtyOrdered <= 0` | "Số lượng phải lớn hơn 0." |
| SO-010 | Đơn giá > 0 | block | `unitPrice <= 0 && !isService` | "Đơn giá phải lớn hơn 0." |
| SO-011 | Ngày giao hàng >= ngày đặt | warn | `deliveryDate < orderDate` | "Ngày giao hàng không được trước ngày đặt hàng." |
| SO-012 | KH có MST nếu xuất HĐ | block | `config('requireCustomerTaxCode') && !customerTaxCode` | "Khách hàng chưa có mã số thuế. Vui lòng cập nhật trước khi xuất hóa đơn." |
| SO-013 | Không confirm đơn đã confirm | block | `status !== draft` | "Chỉ có thể xác nhận đơn hàng ở trạng thái nháp." |
| SO-014 | Xuất kho vượt số lượng | block | `qtyToShip > (qtyOrdered - qtyShipped)` | "Số lượng xuất vượt quá số lượng còn lại phải xuất." |
| SO-015 | Xuất hóa đơn vượt số xuất | block | `qtyToInvoice > (qtyShipped - qtyInvoiced) && !isService` | "Số lượng xuất hóa đơn vượt quá số lượng đã xuất kho." |

---

## 7. Integration Contracts

### 7.1 With InventoryService

**Methods consumed by SalesOrderService:**

```php
// Kiểm tra tồn kho tại confirm
// Nếu config('sales.inventory_check_on_confirm') = true
$itemRepo->findById(itemId)->getStockQty() >= $qtyOrdered

// Xuất kho (tại ship)
$inventoryService->issueGoods(
    itemId: $itemId,
    qty: $qtyToShip,
    issueType: 'sale',       // → Nợ 632 / Có 15x
    reference: $reference,   // SO reference
    createdBy: $createdBy
);

// Hàng trả lại (tại return)
$inventoryService->returnFromCustomer(
    itemId: $itemId,
    qty: $returnQty,
    reference: $reference,
    createdBy: $createdBy
);
```

### 7.2 With EInvoiceGatewayInterface

**Methods consumed by SalesOrderService (thông qua InvoiceService):**

```php
// Phát hành hóa đơn điện tử (tại invoice step)
$invoiceService->createFromTransaction(
    transactionId: $txnId,
    providerId: 'tvan_vnpt'
);

// Thay thế hóa đơn (tại return/credit note)
$invoiceService->replaceInvoice(
    einvoiceId: $eInvId,
    newData: [...]
);

// Hủy hóa đơn (tại cancel nếu đã publish)
$invoiceService->cancelInvoice(
    einvoiceId: $eInvId,
    reason: 'Hủy đơn hàng: ' . $cancelReason
);
```

**Contract note:** SalesOrderService gọi ở mức high-level qua InvoiceService, không gọi trực tiếp EInvoiceGatewayInterface. InvoiceService xử lý XML signing + gateway communication.

### 7.3 With JournalService

**Methods consumed by SalesOrderService:**

```php
// Ghi nhận doanh thu — tạo bút toán Nợ 131 / Có 511+33311
// Không gọi trực tiếp — qua ArService::recordInvoice()
// SalesOrderService gọi ArService, ArService gọi JournalService

// Ghi nhận giá vốn — Nợ 632 / Có 15x
// Không gọi trực tiếp — qua InventoryService::issueGoods()

// Ghi nhận điều chỉnh / đảo bút toán
$journalService->postEntry(
    description: 'Return: SO-{reference}',
    reference: $voucherRef,
    lines: $reversalLines,
    createdBy: $createdBy
);
```

### 7.4 With CashService

**Methods consumed by SalesOrderService:**

```php
// Tự động thu tiền nếu payment_method = 'cash' hoặc 'bank'
$result = $cashService->recordReceipt(
    amount: $paymentAmount,
    creditAccountCode: '131',            // Giảm công nợ phải thu
    description: "Thu tiền đơn hàng {$reference}",
    reference: Helpers::nextVoucherNo('PT'),
    createdBy: $createdBy
);

// Hoặc qua ngân hàng
$result = $cashService->recordBankReceipt(
    amount: $paymentAmount,
    creditAccountCode: '131',
    description: "Thu tiền NH đơn hàng {$reference}",
    reference: ...,
    createdBy: $createdBy
);
```

### 7.5 With ArService

**Methods consumed by SalesOrderService:**

```php
// Ghi nhận hóa đơn bán hàng (tại invoice step)
$arService->recordInvoice(
    customerId: $customerId,
    invoiceNumber: $soNumber,            // Số đơn hàng dùng làm số HĐ
    invoiceDate: date('Y-m-d'),
    dueDate: $dueDate,                   // Tính từ payment_terms
    netAmount: $totalAmount,             // Tổng tiền hàng (chưa VAT)
    vatAmount: $taxAmount,
    vatRate: $vatRate,
    description: "Bán hàng theo đơn {$reference}",
    createdBy: $createdBy,
    revenueAccount: '511'
);

// Ghi nhận ứng trước (deposit)
$arService->recordPrepayment(
    customerId: $customerId,
    amount: $depositAmount,
    description: "Ứng trước đơn hàng {$reference}",
    createdBy: $createdBy
);

// Ghi nhận chiết khấu thanh toán
$arService->recordSettlementDiscount(
    invoiceId: $arInvoiceId,
    discountAmount: $discountAmount,
    createdBy: $createdBy
);

// Kiểm tra hạn mức tín dụng
$customerBalance = $arService->getCustomerBalance($customerId);
// Hoặc từ customerRepo directly
```

### 7.6 With VoucherService

**SO Reference Numbering:**

```php
// Sinh số đơn hàng tự động
$voucherService->nextNumber('SO');
// Format: SO{YYYY}-{000000}  VD: SO2026-000042

// Sinh số báo giá
$voucherService->nextNumber('Q');
// Format: Q{YYYY}-{000000}   VD: Q2026-000015
```

SalesOrderService tạo reference tại thời điểm `create()` (draft). Reference được SELECT FOR UPDATE trong VoucherService để chống trùng dưới concurrent access.

### 7.7 With ConfigService

**Keys consumed by SalesOrderService:**

```php
$configService->get('sales.discount_approval_threshold', 5000000);
$configService->get('sales.max_credit_days', 60);
$configService->get('sales.duplicate_order_window_hours', 24);
$configService->get('sales.default_vat_rate', 10);
$configService->get('sales.require_customer_tax_code', false);
$configService->get('sales.inventory_check_on_confirm', true);
$configService->get('sales.allow_partial_shipment', true);
$configService->get('sales.auto_create_invoice', false);
$configService->get('sales.auto_create_delivery', true);
$configService->get('sales.price_override_approval_threshold', 0);
```

Tất cả config có default safety net — không thay đổi behavior khi chưa seed.

---

## 8. API Endpoints

### 8.1 Sales Orders

| Method | Path | Controller Method | Permission | Request Body | Response |
|---|---|---|---|---|---|
| GET | `/api/sales/orders` | `index()` | sales.read | Query: `status`, `customer_id`, `from`, `to`, `q` | `{data: [...]}` |
| POST | `/api/sales/orders` | `store()` | sales.create | `{customer_id, order_date, delivery_date, payment_terms, payment_method, notes, lines: [...]}` | `{data: {id, reference, status}}` 201 |
| GET | `/api/sales/orders/{id}` | `show($id)` | sales.read | — | `{data: {order, lines, links}}` |
| PUT | `/api/sales/orders/{id}` | `update($id)` | sales.update | `{delivery_date, notes, lines: [...]}` (chỉ khi draft) | `{data: {...}}` |
| DELETE | `/api/sales/orders/{id}` | `destroy($id)` | sales.delete | — | `{data: {deleted: true}}` (chỉ khi draft) |
| POST | `/api/sales/orders/{id}/confirm` | `confirm($id)` | sales.approve | `{approved_by}` | `{data: {status, journal_ref}}` |
| POST | `/api/sales/orders/{id}/cancel` | `cancel($id)` | sales.cancel | `{reason, cancel_by}` | `{data: {status, adjustments: [...]}}` |
| POST | `/api/sales/orders/{id}/ship` | `ship($id)` | sales.update | `{lines: [{item_id, qty}], warehouse_id, reference}` | `{data: {deliveries: [...], journal_ref}}` |
| POST | `/api/sales/orders/{id}/invoice` | `invoice($id)` | sales.create | `{lines: [{item_id, qty}], invoice_date, due_date, vat_rate}` | `{data: {ar_invoice_id, transaction_id, einvoice_id}}` |
| POST | `/api/sales/orders/{id}/receive-payment` | `receivePayment($id)` | sales.create | `{amount, payment_method, payment_date, reference}` | `{data: {transaction_id, balance_after}}` |
| POST | `/api/sales/orders/{id}/return` | `return($id)` | sales.create | `{lines: [{item_id, qty}], reason}` | `{data: {credit_note_id, adjustments}}` |
| GET | `/api/sales/orders/{id}/links` | `links($id)` | sales.read | — | `{data: [{linked_type, linked_id, reference, amount}]}` |

### 8.2 Quotations

| Method | Path | Controller Method | Permission | Request Body | Response |
|---|---|---|---|---|---|
| GET | `/api/sales/quotations` | `quotations()` | sales.read | — | `{data: [...]}` |
| POST | `/api/sales/quotations` | `createQuotation()` | sales.create | `{customer_id, items, valid_until, notes}` | `{data: {id, reference}}` 201 |
| GET | `/api/sales/quotations/{id}` | `showQuotation($id)` | sales.read | — | `{data: {...}}` |
| PUT | `/api/sales/quotations/{id}` | `updateQuotation($id)` | sales.update | — | `{data: {...}}` |
| DELETE | `/api/sales/quotations/{id}` | `destroyQuotation($id)` | sales.delete | — | `{deleted: true}` |
| POST | `/api/sales/quotations/{id}/send` | `sendQuotation($id)` | sales.update | — | `{data: {status: sent}}` |
| POST | `/api/sales/quotations/{id}/convert-to-order` | `convertToOrder($id)` | sales.create | `{order_date, delivery_date, payment_terms}` | `{data: {order_id, reference}}` 201 |

### 8.3 Response Formats

```json
// GET /api/sales/orders
{
    "data": [
        {
            "id": 1,
            "reference": "SO2026-000042",
            "customer_name": "Công ty TNHH ABC",
            "order_date": "2026-06-01",
            "delivery_date": "2026-06-10",
            "status": "confirmed",
            "grand_total": 55000000,
            "amount_paid": 0,
            "amount_invoiced": 0,
            "items_count": 3,
            "created_at": "2026-06-01 08:30:00",
            "created_by": "nvbanhang"
        }
    ]
}

// POST /api/sales/orders (store)
{
    "data": {
        "id": 1,
        "reference": "SO2026-000042",
        "status": "draft",
        "lines": [
            {"line_no": 1, "item_name": "Máy tính Dell", "qty_ordered": 5, "unit_price": 10000000, "line_total": 50000000}
        ],
        "total_amount": 50000000,
        "tax_amount": 5000000,
        "grand_total": 55000000
    }
}

// POST /api/sales/orders/{id}/confirm (success)
{
    "data": {
        "id": 1,
        "status": "confirmed",
        "auto_created": {
            "delivery_order_id": "jrn_abc123",
            "journal_ref": "PXK2026-000015"
        },
        "warnings": []
    }
}

// POST /api/sales/orders/{id}/confirm (out of stock)
{
    "error": "Không thể xác nhận đơn hàng.",
    "details": [
        {"item_id": "item_001", "item_name": "Máy tính Dell", "ordered": 5, "available": 2, "shortage": 3},
        {"item_id": "item_003", "item_name": "Màn hình LG", "ordered": 3, "available": 0, "shortage": 3}
    ],
    "status": "pending_stock"
}

// POST /api/sales/orders/{id}/cancel (success with adjustments)
{
    "data": {
        "id": 1,
        "status": "cancelled",
        "reversals": {
            "inventory_return": true,
            "invoice_cancelled": true,
            "e_invoice_cancelled": true,
            "journal_adjustment": "JV2026-000088"
        }
    }
}
```

---

## 9. UI/UX Flow

### 9.1 Sidebar Navigation (layout.php)

Add under the "Bán hàng" section (line 133 of layout.php):

```php
<div class="nav-section">Bán hàng</div>
<div class="nav-item">
    <a class="nav-link-s" data-bs-toggle="collapse" href="#menuSales">
        <i class="bi bi-bag"></i> Bán hàng
        <i class="bi bi-chevron-right ms-auto" style="width:auto;font-size:10px;"></i>
    </a>
    <div class="collapse sub-menu<?= isActive(['customers','sales_orders','sales_quotations','sales_delivery','ar_invoices','ar_aging','ar_statement'],$activeMenu)?' show':'' ?>" id="menuSales">
        <a href="/danh-muc/khach-hang" class="nav-link-s<?= isActive('customers',$activeMenu)?' active':'' ?>">
            <i class="bi bi-circle-fill"></i> Khách hàng
        </a>
        <a href="/ban/bao-gia" class="nav-link-s<?= isActive('sales_quotations',$activeMenu)?' active':'' ?>">
            <i class="bi bi-circle-fill"></i> Báo giá
        </a>
        <a href="/ban/don-dat-hang" class="nav-link-s<?= isActive('sales_orders',$activeMenu)?' active':'' ?>">
            <i class="bi bi-circle-fill"></i> Đơn đặt hàng
        </a>
        <a href="/ban/phieu-xuat-kho" class="nav-link-s<?= isActive('sales_delivery',$activeMenu)?' active':'' ?>">
            <i class="bi bi-circle-fill"></i> Phiếu xuất kho
        </a>
        <a href="/ban/cong-no-phai-thu" class="nav-link-s<?= isActive('ar_invoices',$activeMenu)?' active':'' ?>">
            <i class="bi bi-circle-fill"></i> Công nợ phải thu
        </a>
        <a href="/ban/phan-tich-tuoi-no" class="nav-link-s<?= isActive('ar_aging',$activeMenu)?' active':'' ?>">
            <i class="bi bi-circle-fill"></i> Phân tích tuổi nợ
        </a>
        <a href="/ban/so-chi-tiet-cong-no" class="nav-link-s<?= isActive('ar_statement',$activeMenu)?' active':'' ?>">
            <i class="bi bi-circle-fill"></i> Sổ chi tiết công nợ
        </a>
    </div>
</div>
```

### 9.2 Sales Orders List View (`public/views/sales_orders.php`)

Tương tự `ar_invoices.php` pattern:
- **Toolbar:** Tiêu đề "Đơn đặt hàng bán hàng", button "Tạo đơn hàng" (modal)
- **Filter bar:** Search (số ĐH/KH), status dropdown, customer dropdown, date range
- **Data table columns:** Số ĐH | Khách hàng | Ngày đặt | Ngày giao | Tổng tiền | Đã xuất HĐ | Đã thanh toán | Trạng thái | Actions
- **Action buttons per row:**
  - Draft: ✏️ Sửa | 🗑️ Xóa | ✅ Xác nhận
  - Confirmed: 📦 Xuất kho | 📄 Xuất HĐ | ❌ Hủy
  - Shipped: 📄 Xuất HĐ | 💰 Thu tiền | ❌ Hủy
  - Invoiced: 💰 Thu tiền | 🔄 Trả lại
  - Paid/Completed: 👁️ Xem | 📄 In
  - All: 🔗 Xem chứng từ liên kết
- **Status badges:** draft (inactive), confirmed (primary), pending_stock (warning), shipped (info), invoiced (type), paid (success), completed (active), cancelled (danger)
- **CSV export** button
- **Modal forms:** Create, Edit, Confirm, Ship, Invoice, Payment, Return, Cancel

### 9.3 Sales Order Detail/Form (`public/views/sales_order_form.php`)

- **Header:** Số đơn hàng, trạng thái badge, created_by, dates
- **Customer section:** Tên KH (read-only sau confirm), MST, địa chỉ, người liên hệ
- **Lines table:** editable grid (tương tự nhập kho):
  - Stt | Mã hàng | Tên hàng | ĐVT | SL đặt | SL xuất | SL HĐ | Đơn giá | CK % | CK tiền | Thuế % | Thành tiền | Hàng DV
  - Thêm dòng, xóa dòng
  - Tổng: Tiền hàng, Chiết khấu, Thuế GTGT, Tổng thanh toán
- **Footer:** Ghi chú, Điều khoản thanh toán, Phương thức thanh toán
- **Action buttons:** Lưu nháp | Xác nhận | Hủy | Xuất kho | Xuất HĐ | Thu tiền
- **Linked documents:** Tab "Chứng từ liên quan" — hiển thị sales_order_links (phiếu xuất, hóa đơn, phiếu thu)
- **Timeline:** Lịch sử trạng thái + audit log

### 9.4 Quotation View

Pattern tương tự nhưng đơn giản hơn:
- **Data table:** Số báo giá | KH | Ngày | Hiệu lực đến | Tổng tiền | Trạng thái | Actions
- **Actions:** Gửi (mark sent), Chuyển thành đơn hàng, Sửa, Xóa
- **Form:** Giống sales order form nhưng thêm valid_until và terms_conditions

---

## 10. Security & Audit

### 10.1 Permission Matrix

| Permission Module | Action | Endpoint |
|---|---|---|
| `sales` | `read` | GET /api/sales/orders, GET /api/sales/orders/{id}, GET /api/sales/orders/{id}/links |
| `sales` | `create` | POST /api/sales/orders, POST /api/sales/orders/{id}/invoice, /receive-payment, /return |
| `sales` | `update` | PUT /api/sales/orders/{id}, POST /api/sales/orders/{id}/ship |
| `sales` | `delete` | DELETE /api/sales/orders/{id} |
| `sales` | `approve` | POST /api/sales/orders/{id}/confirm (giá trị lớn, chiết khấu lớn) |
| `sales` | `cancel` | POST /api/sales/orders/{id}/cancel |

### 10.2 Audit Logging

Mọi thay đổi trạng thái quan trọng đều ghi audit log:

```php
// Tạo đơn hàng
AuditLogger::log('sales.order.create', 'sales_order', $orderId, null, [
    'reference' => $reference, 'customer' => $customerId,
    'grand_total' => $grandTotal, 'lines_count' => count($lines),
], $createdBy);

// Xác nhận đơn hàng
AuditLogger::log('sales.order.confirm', 'sales_order', $orderId,
    ['status' => 'draft'], ['status' => 'confirmed', 'approved_by' => $approvedBy], $approvedBy);

// Hủy đơn hàng
AuditLogger::log('sales.order.cancel', 'sales_order', $orderId,
    ['status' => $oldStatus], ['status' => 'cancelled', 'reason' => $reason], $cancelledBy);

// Xuất kho
AuditLogger::log('sales.order.ship', 'sales_order', $orderId,
    ['qty_shipped' => $oldQty], ['qty_shipped' => $newQty, 'items' => $shippedItems], $createdBy);

// Xuất hóa đơn
AuditLogger::log('sales.order.invoice', 'sales_order', $orderId,
    ['status' => $oldStatus, 'amount_invoiced' => $oldInvoiced],
    ['status' => $newStatus, 'amount_invoiced' => $newInvoiced, 'ar_invoice_id' => $arInvId], $createdBy);

// Thu tiền
AuditLogger::log('sales.order.payment', 'sales_order', $orderId,
    ['amount_paid' => $oldPaid],
    ['amount_paid' => $newPaid, 'payment_method' => $paymentMethod, 'txn_id' => $txnId], $createdBy);

// Trả lại hàng
AuditLogger::log('sales.order.return', 'sales_order', $orderId,
    [], ['return_amount' => $returnAmount, 'credit_note_id' => $cnId], $createdBy);
```

### 10.3 CSRF Protection

Mọi POST/PUT/DELETE endpoint bắt buộc:
```php
Auth::checkCsrf();
```

### 10.4 ActionJournal

Mọi request API sales được ghi vào ActionJournal (JSON Lines) — không thể xóa, phục vụ kiểm toán.

### 10.5 Audit Trail trong DB

Bảng `sales_order_links` lưu vết liên kết giữa SalesOrder và tất cả chứng từ phát sinh. Cho phép trace ngược: từ phiếu thu → hóa đơn → đơn hàng → báo giá.

---

## 11. Implementation Checklist

### Phase 1: Core O2C (Days 1-4)

```
[ ] 1. Migration: 100_sales_orders.sql (sales_orders + sales_order_lines + sales_order_links)
[ ] 2. Model: Domain/Model/SalesOrder.php (state machine, getters, setters, toArray)
[ ] 3. Model: Domain/Model/SalesOrderLine.php (value object with line_total calculation)
[ ] 4. Repository Interface: Domain/Repository/SalesOrderRepositoryInterface.php
[ ] 5. PDO Repository: Infrastructure/Repository/PDOSalesOrderRepository.php
[ ] 6. Service: Domain/Service/SalesOrderService.php (core logic: create, confirm, ship, invoice, pay, cancel, return)
[ ] 7. Controller: Interfaces/HTTP/Sales/SalesOrderController.php
[ ] 8. Route file: config/routes/api_sales.php (require in config/routes.php)
[ ] 9. DI: config/services/38_sales.php (require in config/services.php)
[ ] 10. View: public/views/sales_orders.php (list + CRUD modals)
[ ] 11. Sidebar: public/views/layout.php (Bán hàng section)
[ ] 12. Test: tests/SalesOrderTest.php (happy path: create → confirm → ship → invoice → pay → complete)
[ ] 13. Permissions: sales.* in RBAC seed
[ ] 14. Config keys: seed business_config with sales.* keys
```

### Phase 2: Quotations & Returns (Days 5-6)

```
[ ] 15. Migration: 101_quotations.sql (quotations + quotation_lines)
[ ] 16. Model: Domain/Model/Quotation.php
[ ] 17. Repository: PDOSalesOrderRepository mở rộng (quotation CRUD)
[ ] 18. Service: SalesOrderService mở rộng (createQuotation, convertToOrder, return)
[ ] 19. Controller: SalesOrderController mở rộng
[ ] 20. View: public/views/sales_quotations.php
[ ] 21. Route: quotation endpoints
[ ] 22. Test: Quotation → convert → return flow
```

### Phase 3: Polish & Hardening (Day 7)

```
[ ] 23. Duplicate order detection
[ ] 24. Credit limit check integration
[ ] 25. Discount approval workflow
[ ] 26. Price override guard
[ ] 27. E-Invoice auto-publish integration
[ ] 28. Full test suite: 15+ tests covering all state transitions + exceptions
[ ] 29. Audit log verification
[ ] 30. UI polish: confirm dialogs, loading states, error messages
```

---

## 12. Effort Estimate

| Phase | Scope | Days | Lines of Code (est.) |
|---|---|---|---|
| Phase 1 | Core O2C (DB → Model → Service → Controller → View → Test) | 4 | ~1,200 |
| Phase 2 | Quotations + Returns | 2 | ~500 |
| Phase 3 | Polish + Hardening + Full Test Suite | 1 | ~300 |
| **Total** | **Full module** | **7** | **~2,000** |

### Detailed Breakdown

| Task | Est. Hours | Complexity |
|---|---|---|
| DB schema design + migration SQL | 1 | Low |
| SalesOrder + SalesOrderLine models | 2 | Low |
| SalesOrderRepositoryInterface | 1 | Low |
| PDOSalesOrderRepository | 3 | Medium |
| SalesOrderService::create/confirm (core state machine) | 4 | High |
| SalesOrderService::ship (InventoryService integration) | 2 | Medium |
| SalesOrderService::invoice (ArService + InvoiceService) | 3 | High |
| SalesOrderService::pay (CashService integration) | 2 | Medium |
| SalesOrderService::cancel (multi-scenario reverse) | 3 | High |
| SalesOrderService::return (Inventory + Journal reversal) | 2 | Medium |
| SalesOrderController (8+ endpoints) | 4 | Medium |
| Routes + DI wiring | 1 | Low |
| Views (list + form + modals) | 4 | Medium |
| Quotation flow | 4 | Medium |
| Testing (15+ test cases) | 4 | Medium |
| Security audit + CSRF checks | 1 | Low |
| Config keys + business rules integration | 1 | Low |
| **Total** | **42 hours (7 days)** | |

### Risk Factors

| Risk | Impact | Mitigation |
|---|---|---|
| ArService không có beginTransaction ở multi-step | Data integrity | SalesOrderService tự wrap transaction khi gọi nhiều service |
| EInvoiceGateway timeout khi auto-publish | UX delay | Async retry queue (Phase 3) — fallback là publish thủ công |
| Concurrent order confirm + stock check | Overselling | SELECT FOR UPDATE trên stock_qty trước khi giảm |
| State machine complexity (nhiều partial state) | Bugs | State transition matrix test coverage mandatory |

---

> **KẾT LUẬN:** Sales Order Module là P0 gap lớn nhất của Bookwise. Module này quyết định khả năng cạnh tranh của sản phẩm trong phân khúc SME ERP Việt Nam. Ước lượng 7 ngày cho full stack implementation. Sau Phase 1, Bookwise có O2C cơ bản ngang hàng MISA helpsme. Phase 2 bổ sung quotation + return. Phase 3 hardening cho production readiness.
