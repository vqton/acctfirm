# Gap 04: Contract Management — Parity Specification

> **Mức độ hiện tại:** 0/10 — **Mục tiêu:** 8/10  
> **Phạm vi:** Contract lifecycle cho cả mua (purchase) và bán (sales)  
> **Ngày:** 02/06/2026  
> **Tài liệu tham chiếu:** `10-gaps-use-cases-consolidated.md` (Gap 4), `AGENTS.md §1-§10`, MISA SME contract flow, FAST Construction contract/subcontract, BRAVO ERP contract lifecycle, Thông tư 99/2025/TT-BTC

---

## 1. Business Context & Rationale

### 1.1 Why This Matters

Bookwise hiện quản lý hợp đồng bằng file Word riêng — không có tracking giá trị thực hiện, không cảnh báo vượt hợp đồng, không liên kết với AP/AR. Kế toán phải đối chiếu thủ công giữa hợp đồng và từng hóa đơn/phiếu chi.

Hợp đồng là nền tảng pháp lý của mọi giao dịch mua bán. Thiếu contract management dẫn đến:
- Thanh toán vượt giá trị hợp đồng (overpayment risk)
- Không theo dõi được tiến độ thực hiện (performance tracking mù)
- Không có cơ sở để đối chiếu công nợ theo hợp đồng
- Không kiểm soát được biến động (variation order) — ký phụ lục xong không ghi nhận
- Không có data để phân tích doanh số theo hợp đồng

### 1.2 Competitive Landscape

| Phần mềm | Purchase Contract | Sales Contract | Payment Schedule | Amendment | Liquidation |
|---|---|---|---|---|---|
| MISA SME | ✅ Nhập kho/thanh toán theo HĐ | ✅ Có tracking | ✅ Có | ✅ Phụ lục | ✅ Thanh lý |
| FAST Construction | ✅ Hợp đồng thầu/phụ thầu | ✅ Có | ✅ Theo giai đoạn | ✅ Variation | ✅ Nghiệm thu |
| BRAVO ERP | ✅ Đầy đủ | ✅ Đầy đủ | ✅ Theo tiến độ | ✅ Có | ✅ Có |
| **Bookwise** | **❌ Word file** | **❌ Word file** | **❌** | **❌** | **❌** |

### 1.3 Regulatory Requirements

- **Thông tư 99/2025/TT-BTC §15:** Chứng từ kế toán phải có đầy đủ căn cứ pháp lý (hợp đồng)
- **Bộ Luật Dân sự 2015 (Luật 91/2015/QH13):** Hợp đồng là căn cứ phát sinh quyền và nghĩa vụ
- **Luật Thương mại 2005 (Luật 36/2005/QH11):** Hợp đồng mua bán hàng hóa
- **VAS 14 (Doanh thu):** Doanh thu ghi nhận khi thỏa mãn điều kiện trên hợp đồng (chuyển giao rủi ro)
- **VAS 15 (Hợp đồng XDCB):** Ghi nhận doanh thu theo tiến độ (percentage of completion) — TK 337

### 1.4 Scope

**In scope:**
- Purchase contracts (hợp đồng mua hàng/nhập khẩu)
- Sales contracts (hợp đồng bán hàng/cung cấp dịch vụ)
- Payment schedule tracking (theo dõi lịch thanh toán theo hợp đồng)
- Fulfillment links (liên kết nhập kho/hóa đơn/phiếu thu/chi với hợp đồng)
- Amendments / Variation orders (phụ lục hợp đồng — tăng/giảm giá trị)
- Contract liquidation (thanh lý kết thúc hợp đồng)
- Expiry alerts (cảnh báo hết hạn)
- Dashboard: danh sách hợp đồng, giá trị thực hiện, % hoàn thành

**Out of scope (Phase 2):**
- Construction contracts with percentage-of-completion (TK 337 — riêng module XDCB)
- Subcontractor back-to-back contracts (FAST-style)
- Automatic penalty/liquidated damages calculation
- Multi-currency contracts
- Contract approval workflow (routing to manager/chief accountant)

---

## 2. Data Model

### 2.1 `contracts`

```sql
CREATE TABLE contracts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(30) NOT NULL COMMENT 'Số hợp đồng (tự động sinh: HDMB-YYYY-NNNNNN hoặc HDMU-YYYY-NNNNNN)',
    type ENUM('purchase','sales','service','construction') NOT NULL COMMENT 'Loại hợp đồng',
    partner_id VARCHAR(50) NOT NULL COMMENT 'customer_id (sales) hoặc supplier_id (purchase)',
    partner_name VARCHAR(255) NOT NULL COMMENT 'Denormalized — hiển thị nhanh',
    partner_code VARCHAR(30) NOT NULL COMMENT 'Denormalized — hiển thị nhanh',

    -- Thời hạn
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    signed_date DATE DEFAULT NULL COMMENT 'Ngày ký',
    effective_date DATE DEFAULT NULL COMMENT 'Ngày hiệu lực',

    -- Giá trị
    total_value DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Tổng giá trị hợp đồng (đã bao gồm thuế)',
    currency_code VARCHAR(10) NOT NULL DEFAULT 'VND',
    tax_rate DECIMAL(5,2) DEFAULT NULL COMMENT 'Thuế suất mặc định (%)',
    tax_amount DECIMAL(15,2) DEFAULT 0.00,
    net_value DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Giá trị chưa thuế',

    -- Tracking
    fulfilled_value DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Giá trị đã thực hiện (tổng hóa đơn / nhập kho + chi phí)',
    billed_value DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Giá trị đã xuất hóa đơn (sales)',
    paid_value DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Giá trị đã thanh toán (purchase)',
    received_value DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Giá trị đã thu (sales)',

    -- Quản lý
    status ENUM('draft','active','suspended','completed','liquidated','cancelled') NOT NULL DEFAULT 'draft',
    payment_terms TEXT DEFAULT NULL COMMENT 'Điều khoản thanh toán (text tự do)',
    description TEXT DEFAULT NULL COMMENT 'Nội dung hợp đồng / điều khoản chính',
    notes TEXT DEFAULT NULL COMMENT 'Ghi chú nội bộ',

    -- Người dùng
    created_by VARCHAR(50) NOT NULL,
    approved_by VARCHAR(50) DEFAULT NULL COMMENT 'Người phê duyệt hợp đồng',
    responsible_person VARCHAR(50) DEFAULT NULL COMMENT 'Người phụ trách hợp đồng (bên mua/bán)',

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_contract_status (status),
    INDEX idx_contract_partner (partner_id),
    INDEX idx_contract_type (type),
    INDEX idx_contract_end_date (end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 `contract_payment_schedule`

```sql
CREATE TABLE contract_payment_schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id INT UNSIGNED NOT NULL,
    installment_no TINYINT UNSIGNED NOT NULL COMMENT 'Số đợt (1, 2, 3...)',
    description VARCHAR(255) DEFAULT NULL COMMENT 'Diễn giải đợt thanh toán (VD: Đợt 1 — 30% sau khi ký)',
    due_date DATE DEFAULT NULL COMMENT 'Ngày đến hạn thanh toán',
    amount DECIMAL(15,2) NOT NULL COMMENT 'Số tiền đợt này',
    paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Đã thanh toán',
    status ENUM('pending','partial','paid','overdue') NOT NULL DEFAULT 'pending',

    -- Liên kết
    linked_transaction_id VARCHAR(50) DEFAULT NULL COMMENT 'transaction_id nếu đã có thanh toán',
    linked_invoice_id INT UNSIGNED DEFAULT NULL COMMENT 'ar_invoice_id / ap_invoice_id',

    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    INDEX idx_schedule_contract (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.3 `contract_fulfillment_links`

```sql
CREATE TABLE contract_fulfillment_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id INT UNSIGNED NOT NULL,

    -- Document liên kết
    document_type ENUM('ar_invoice','ap_invoice','receipt','payment','delivery_note',
                       'warehouse_import','warehouse_export','einvoice') NOT NULL,
    document_id INT UNSIGNED NOT NULL COMMENT 'ID của document gốc',
    transaction_id VARCHAR(50) DEFAULT NULL COMMENT 'Transaction ID từ JournalService',

    amount DECIMAL(15,2) NOT NULL COMMENT 'Giá trị giao dịch',
    description VARCHAR(255) DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    UNIQUE KEY uq_contract_doc (contract_id, document_type, document_id),
    INDEX idx_fulfillment_contract (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.4 `contract_amendments`

```sql
CREATE TABLE contract_amendments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id INT UNSIGNED NOT NULL,
    amendment_no VARCHAR(30) NOT NULL COMMENT 'Số phụ lục (tự động sinh: PL-HDMB-YYYY-NNNNNN)',
    amendment_date DATE NOT NULL COMMENT 'Ngày ký phụ lục',
    description TEXT NOT NULL COMMENT 'Nội dung điều chỉnh',

    -- Thay đổi giá trị
    previous_value DECIMAL(15,2) NOT NULL COMMENT 'Giá trị hợp đồng cũ',
    new_value DECIMAL(15,2) NOT NULL COMMENT 'Giá trị hợp đồng mới (sau phụ lục)',
    change_amount DECIMAL(15,2) GENERATED ALWAYS AS (new_value - previous_value) STORED,

    -- Thay đổi thời hạn
    previous_end_date DATE DEFAULT NULL,
    new_end_date DATE DEFAULT NULL,

    -- Trạng thái
    status ENUM('draft','approved','cancelled') NOT NULL DEFAULT 'draft',
    created_by VARCHAR(50) NOT NULL,
    approved_by VARCHAR(50) DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    INDEX idx_amendment_contract (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.5 `contract_templates`

```sql
CREATE TABLE contract_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT 'Tên mẫu hợp đồng',
    type ENUM('purchase','sales','service') NOT NULL,
    content TEXT NOT NULL COMMENT 'Nội dung mẫu (HTML/plain text với placeholder {partner_name}, {total_value}...)',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Process Flows

### 3.1 Purchase Contract Lifecycle (Hợp đồng mua hàng)

```
[SOẠN THẢO]         [KÝ DUYỆT]        [THỰC HIỆN]           [KẾT THÚC]
                                                                                
draft ────► active ──────────────► completed ──► liquidated                    
               │                       ▲                                         
               │                       │                                         
               ▼                       │                                         
           suspended ──────────────────┘                                         
          (tạm ngưng)           (phục hồi)                                        
                                                                                
cancelled (bất kỳ lúc nào trước liquidated)                                     
```

**Steps (Purchase):**

1. **Tạo hợp đồng:** Nhập supplier, điều khoản, giá trị, ngày bắt đầu/kết thúc, lịch thanh toán
2. **Ký duyệt:** Chuyển trạng thái `draft` → `active`. Ghi nhận reference (sinh tự động)
3. **Thực hiện:** Mỗi lần nhập kho (InventoryService::receive) hoặc ghi nhận hóa đơn mua (ApService::recordInvoice):
   - Hệ thống tự động tạo `contract_fulfillment_links` → cập nhật `fulfilled_value`
   - Thanh toán (ApService::recordPayment) → cập nhật `paid_value`
   - Kiểm tra: nếu `fulfilled_value + pending_value > total_value` → cảnh báo
4. **Kết thúc:** Khi đã nhập kho đủ + thanh toán đủ → đề xuất chuyển `completed`
5. **Thanh lý:** Lập biên bản thanh lý → `liquidated`. Hợp đồng kết thúc.

### 3.2 Sales Contract Lifecycle (Hợp đồng bán hàng)

```
draft ────► active ──────────────► completed ──► liquidated
               │                       ▲
               ▼                       │
           suspended ──────────────────┘
```

**Steps (Sales):**

1. **Tạo hợp đồng:** Nhập customer, sản phẩm/dịch vụ, giá trị, tiến độ thanh toán, milestone
2. **Ký duyệt:** `draft` → `active`. Sinh lịch thanh toán tự động.
3. **Thực hiện:**
   - Mỗi lần xuất hóa đơn (ArService::recordInvoice) → tự động link → cập nhật `fulfilled_value` + `billed_value`
   - Thu tiền (ArService::recordPayment) → cập nhật `received_value` + payment schedule
   - Nếu `billed_value > total_value` (không có amendment) → BLOCK
4. **Kết thúc:** Đã xuất đủ hóa đơn + thu đủ tiền → đề xuất `completed`
5. **Thanh lý:** `liquidated`

### 3.3 Contract Fulfillment Tracking

Mỗi giao dịch liên quan đến hợp đồng đều được ghi nhận qua `contract_fulfillment_links`:

| Document Type | Module | Cập nhật contract field |
|---|---|---|
| `ap_invoice` | ApService | `fulfilled_value` += net + vat |
| `ar_invoice` | ArService | `fulfilled_value` += total, `billed_value` += total |
| `receipt` | CashService / ArService | `received_value` += amount |
| `payment` | CashService / ApService | `paid_value` += amount |
| `warehouse_import` | InventoryService | `fulfilled_value` += giá trị nhập (purchase) |
| `warehouse_export` | InventoryService | `fulfilled_value` += giá vốn (sales) |
| `einvoice` | EInvoiceService | `billed_value` += giá trị (nếu chưa có ar_invoice) |

**Rule:** Mỗi (contract_id, document_type, document_id) là unique — 1 lần link duy nhất.

### 3.4 Payment Schedule & Milestone Billing

**Tự động sinh lịch thanh toán:**

Khi tạo hợp đồng, nếu không nhập thủ công:
- Purchase: Thanh toán 100% khi nhận hàng (due_date = end_date)
- Sales: 100% khi giao hàng

**Hoặc nhập tay theo đợt:**

| Đợt | Mô tả | Tỷ lệ | Ngày dự kiến |
|---|---|---|---|
| 1 | Đặt cọc (deposit) | 30% | Ngày ký hợp đồng |
| 2 | Giao hàng đợt 1 | 40% | 15 ngày sau ký |
| 3 | Nghiệm thu + bảo hành | 30% | Ngày kết thúc |

**Validation:**
- Tổng `amount` của tất cả các đợt phải = `total_value` của hợp đồng (tolerance ±1,000 VND)
- Không cho phép đợt có `amount <= 0`
- Không cho phép tạo đợt mới khi contract status = liquidated/cancelled
- Khi `paid_amount >= amount` (với purchase) hoặc `received_amount` (với sales) → tự động set status `paid`

### 3.5 Contract Amendment / Variation Order

**Khi cần thay đổi giá trị/thời hạn hợp đồng:**

1. Tạo amendment record ghi nhận sự thay đổi
2. Cập nhật contract: `total_value`, `end_date` (nếu có)
3. Điều chỉnh payment schedule nếu cần (thêm/xóa đợt)
4. Audit trail: ghi lại `previous_value` → `new_value`

**Business rules:**
- Amendment chỉ được tạo khi contract status = `active` hoặc `suspended`
- Amendment phải được phê duyệt (approved_by)
- Mỗi amendment là một dòng riêng — không sửa trực tiếp contract value
- Khi amendment approved → tự động cập nhật `total_value` trên contract

### 3.6 Contract Liquidation / Termination

**Liquidation (Thanh lý hợp đồng):**
1. Kiểm tra điều kiện: fulfillment >= total_value (hoặc có lý do thanh lý sớm)
2. Tạo biên bản thanh lý (liquidation record — có thể là document_type 'liquidation' trong fulfillment_links)
3. Chuyển status `completed` → `liquidated`
4. Ghi nhận bút toán kết thúc hợp đồng (nếu có chênh lệch)

**Early termination (Chấm dứt trước hạn):**
1. Ghi nhận lý do chấm dứt
2. Tính toán phần giá trị đã thực hiện vs tổng giá trị
3. Ghi nhận bút toán bù trừ (nếu chưa thanh toán đủ)
4. Chuyển status → `cancelled`

### 3.7 Contract Expiry Alerts

Hệ thống tự động cảnh báo dựa trên `end_date`:

| Thời gian | Hành động |
|---|---|
| 30 ngày trước end_date | Cảnh báo: "Hợp đồng {reference} sắp hết hạn. Giá trị thực hiện: {fulfilled_value}/{total_value}" |
| 7 ngày trước end_date | Cảnh báo mức cao: "Sắp hết hạn — còn {X} ngày" |
| Ngày end_date | Tự động chuyển nhắc: "Hợp đồng đã hết hạn. Vui lòng gia hạn hoặc thanh lý." |
| 30 ngày sau end_date | Nếu chưa liquidated/cancelled → cảnh báo lên quản lý |

**Chặn giao dịch (if configured):**
- Nếu contract status ≠ `active` → không cho phép tạo fulfillment link mới
- Nếu contract expired (end_date < today) → cảnh báo trước khi tạo link mới
- Nếu `fulfilled_value + amount > total_value` (và không có amendment) → BLOCK giao dịch

---

## 4. Accounting Impact

### 4.1 Contract Deposit / Advance Payment

**Purchase (đặt cọc cho NCC):**
```
Nợ 331 (chi tiết NCC)
Có 111/112 (tiền mặt/NH)
```
→ Link deposit payment với contract → cập nhật `paid_value`

**Sales (khách hàng ứng trước):**
```
Nợ 111/112 (tiền mặt/NH)
Có 131 (chi tiết KH)
```
→ Link receipt với contract → cập nhật `received_value`

### 4.2 Progress Billing (Xuất hóa đơn theo tiến độ)

**Sales — Xuất hóa đơn đợt:**
```
Nợ 131 (chi tiết KH)
Có 511 (Doanh thu bán hàng)
Có 33311 (VAT đầu ra)
```
→ Link ar_invoice với contract → cập nhật `billed_value`, `fulfilled_value`

### 4.3 Retention Money (Giữ lại 5-10% bảo hành)

Khi xuất hóa đơn tổng giá trị hợp đồng, retention money được tách riêng:

```
Nợ 131 — Phải thu KH (giá trị hóa đơn)
Có 511 (doanh thu chưa thuế)
Có 33311 (VAT đầu ra)
```

Ghi nhận retention dưới dạng payment schedule riêng với field `is_retention = true`:
- Retention đợt cuối cùng: do_date = end_date + 365 ngày (thời gian bảo hành)
- Khi hết bảo hành → xuất hóa đơn retention → thu tiền

### 4.4 Contract Closure Entries

**Nếu giá trị thực hiện < giá trị hợp đồng (chiết khấu thanh lý):**

Purchase (NCC giảm giá khi thanh lý):
```
Nợ 331 (số dư còn lại)
Có 711 (Thu nhập khác — phần NCC không đòi)
```

Sales (giảm giá cho KH khi kết thúc hợp đồng):
```
Nợ 521 (Các khoản giảm trừ doanh thu)
Có 131 (số dư còn lại của KH)
```

### 4.5 Contract Cost Accrual (Dự phòng chi phí)

Nếu hợp đồng purchase chưa thanh lý nhưng đã nhập kho đủ:
```
Nợ 642 (Chi phí quản lý)
Có 335 (Chi phí phải trả — dự phòng phần chưa thanh toán)
```
→ Khi thanh toán thực tế → đảo bút toán dự phòng.

---

## 5. Business Rules

### 5.1 Validation Rules

| # | Rule | Error/Cảnh báo |
|---|---|---|
| BR01 | `total_value > 0` | "Giá trị hợp đồng phải lớn hơn 0" |
| BR02 | `end_date >= start_date` | "Ngày kết thúc phải sau ngày bắt đầu" |
| BR03 | `start_date >= signed_date` (nếu có signed_date) | "Ngày hiệu lực không được trước ngày ký" |
| BR04 | Tổng payment schedule amount = total_value (±1,000) | "Tổng lịch thanh toán không khớp giá trị hợp đồng" |
| BR05 | `partner_id` phải tồn tại trong customers/suppliers | "Đối tác không tồn tại" |
| BR06 | Contract reference là unique | "Số hợp đồng đã tồn tại" |

### 5.2 Status Transition Rules

| From | To | Điều kiện |
|---|---|---|
| `draft` | `active` | total_value > 0, partner valid, dates hợp lệ |
| `active` | `suspended` | Lý do tạm ngưng bắt buộc |
| `suspended` | `active` | Cho phép phục hồi bất kỳ lúc nào |
| `active`/`suspended` | `completed` | `fulfilled_value >= total_value * 0.995` |
| `completed` | `liquidated` | Biên bản thanh lý, không còn công nợ |
| `draft`/`active`/`suspended` | `cancelled` | Lý do hủy bắt buộc, xác nhận |
| `completed`/`liquidated` | (none) | Final — không thể chuyển tiếp |

### 5.3 Fulfillment Rules

| # | Rule | Hành động |
|---|---|---|
| BR10 | `fulfilled_value + new_amount > total_value` | BLOCK giao dịch (trừ khi có amendment phê duyệt) |
| BR11 | `billed_value > total_value` (sales) | BLOCK xuất hóa đơn |
| BR12 | `contract.status != 'active'` | BLOCK tạo fulfillment link |
| BR13 | `end_date < today` (contract expired) | Cảnh báo + optional BLOCK |
| BR14 | `paid_value > total_value` (purchase) | Cảnh báo — overpayment risk |
| BR15 | `received_value > total_value` (sales) | Cảnh báo — overcollection risk |

### 5.4 Alert Rules

| # | Thời điểm | Message |
|---|---|---|
| AL01 | 30 days before end_date | "Hợp đồng {reference} sắp hết hạn ({days_remaining} ngày). Giá trị thực hiện: {pct}%" |
| AL02 | end_date reached | "Hợp đồng {reference} đã đến hạn. Vui lòng gia hạn hoặc thanh lý." |
| AL03 | 90 days after end_date (not liquidated) | "Hợp đồng {reference} quá hạn {days} ngày chưa thanh lý." |
| AL04 | fulfilled_value > 80% total_value | "Hợp đồng {reference} đang hoàn thành (80%+) — chuẩn bị nghiệm thu." |
| AL05 | Tạo fulfillment link cho contract đã liquidated | "Hợp đồng {reference} đã thanh lý — không thể ghi nhận thêm." |

---

## 6. Integration with AP/AR/Cash/Inventory/EInvoice

### 6.1 Integration Points

| Module | ContractService Callback | Trigger |
|---|---|---|
| `ApService::recordInvoice` | `ContractService::linkDocument('ap_invoice', ...)` | Sau khi ghi nhận hóa đơn mua |
| `ApService::recordPayment` | `ContractService::linkDocument('payment', ...)` | Sau khi thanh toán NCC |
| `ApService::recordPrepayment` | `ContractService::linkDocument('payment', ...)` | Sau khi tạm ứng |
| `ArService::recordInvoice` | `ContractService::linkDocument('ar_invoice', ...)` | Sau khi xuất hóa đơn bán |
| `ArService::recordPayment` | `ContractService::linkDocument('receipt', ...)` | Sau khi thu tiền KH |
| `ArService::recordPrepayment` | `ContractService::linkDocument('receipt', ...)` | Sau khi KH ứng trước |
| `InventoryService::receive` | `ContractService::linkDocument('warehouse_import', ...)` | Sau khi nhập kho |
| `InventoryService::deliver` | `ContractService::linkDocument('warehouse_export', ...)` | Sau khi xuất kho |
| `EInvoiceService::issue` | `ContractService::linkDocument('einvoice', ...)` | Sau khi phát hành hóa đơn ĐT |

### 6.2 ContractService Interface

```php
interface ContractServiceInterface
{
    // ── CRUD ──
    public function createContract(array $data, string $createdBy): array;
    public function updateContract(int $id, array $data, string $updatedBy): array;
    public function getContract(int $id): ?array;
    public function findContracts(array $filters = []): array;
    public function deleteContract(int $id, string $deletedBy): void; // soft delete

    // ── Status ──
    public function activateContract(int $id, string $approvedBy): array;
    public function suspendContract(int $id, string $reason, string $userId): array;
    public function completeContract(int $id, string $userId): array;
    public function liquidateContract(int $id, ?string $liquidationNote, string $userId): array;
    public function cancelContract(int $id, string $reason, string $userId): array;

    // ── Tracking ──
    public function linkDocument(int $contractId, string $documentType, int $documentId,
                                 ?string $transactionId, float $amount,
                                 ?string $description, string $createdBy): array;
    public function getFulfillmentSummary(int $contractId): array;
    public function getPaymentSchedule(int $contractId): array;
    public function markSchedulePaid(int $scheduleId, float $amount,
                                     string $transactionId, int $invoiceId): void;

    // ── Amendments ──
    public function createAmendment(array $data, string $createdBy): array;
    public function approveAmendment(int $amendmentId, string $approvedBy): array;
    public function getAmendments(int $contractId): array;

    // ── Alerts ──
    public function getExpiringContracts(int $daysUntil = 30): array;
    public function getOverdueContracts(): array;

    // ── Reports ──
    public function getContractSummaryReport(string $type = null,
                                              string $status = null): array;
    public function getPartnerContractReport(string $partnerId): array;
}
```

### 6.3 Callback Pattern

Việc link document vào hợp đồng được thực hiện qua callback — không sửa code của service cũ mà thêm hook:

```php
// Pattern: ContractCallbackHandler được inject vào service factory
class ContractCallbackHandler
{
    private ContractService $contractService;

    // Gọi từ ApService / ArService sau khi ghi nhận hóa đơn
    public function onInvoiceCreated(
        string $type,     // 'ap' | 'ar'
        int $invoiceId,
        string $transactionId,
        float $amount,
        ?int $contractId,
        string $createdBy
    ): void {
        if ($contractId === null) return;
        $docType = ($type === 'ap') ? 'ap_invoice' : 'ar_invoice';
        $this->contractService->linkDocument(
            $contractId, $docType, $invoiceId,
            $transactionId, $amount, null, $createdBy
        );
    }
}
```

**In DI container:**
```php
$container[ContractCallbackHandler::class] = fn($c) =>
    new ContractCallbackHandler($c[ContractService::class]);

// Inject vào ApService:
$container[ApService::class] = fn($c) => new ApService(
    $c['pdo'], $c[SupplierRepositoryInterface::class],
    $c[AccountRepositoryInterface::class],
    $c[JournalServiceInterface::class],
    $c[AuditLoggerInterface::class],
    $c[ContractCallbackHandler::class]  // new optional parameter
);
```

**Backward compatibility:** ContractCallbackHandler là optional parameter — các service cũ vẫn hoạt động khi không có contract.

---

## 7. API Endpoints

| Method | Endpoint | Description | Auth |
|---|---|---|---|
| `GET` | `/api/contracts` | Danh sách hợp đồng (filter: type, status, partner, date range) | contract, read |
| `GET` | `/api/contracts/{id}` | Chi tiết hợp đồng + fulfillment summary | contract, read |
| `POST` | `/api/contracts` | Tạo hợp đồng mới | contract, create |
| `PUT` | `/api/contracts/{id}` | Cập nhật hợp đồng (chỉ draft hoặc active) | contract, update |
| `DELETE` | `/api/contracts/{id}` | Soft delete (chỉ draft) | contract, delete |

| `POST` | `/api/contracts/{id}/activate` | Phê duyệt → active | contract, approve |
| `POST` | `/api/contracts/{id}/suspend` | Tạm ngưng | contract, update |
| `POST` | `/api/contracts/{id}/complete` | Đề nghị hoàn thành | contract, update |
| `POST` | `/api/contracts/{id}/liquidate` | Thanh lý | contract, approve |
| `POST` | `/api/contracts/{id}/cancel` | Hủy | contract, delete |

| `GET` | `/api/contracts/{id}/payment-schedule` | Lịch thanh toán | contract, read |
| `PUT` | `/api/contracts/{id}/payment-schedule` | Cập nhật lịch thanh toán | contract, update |

| `GET` | `/api/contracts/{id}/fulfillment` | Chi tiết các giao dịch thực hiện | contract, read |
| `POST` | `/api/contracts/{id}/link` | Thủ công link 1 document vào hợp đồng | contract, create |

| `GET` | `/api/contracts/{id}/amendments` | Danh sách phụ lục | contract, read |
| `POST` | `/api/contracts/{id}/amendments` | Tạo phụ lục mới | contract, create |
| `POST` | `/api/contracts/{id}/amendments/{aid}/approve` | Phê duyệt phụ lục | contract, approve |

| `GET` | `/api/contracts/expiring?days=30` | DS hợp đồng sắp hết hạn | contract, read |
| `GET` | `/api/contracts/overdue` | DS hợp đồng quá hạn | contract, read |

| `GET` | `/api/contracts/report/summary` | Báo cáo tổng hợp contract | contract, read |
| `GET` | `/api/contracts/report/partner/{partnerId}` | Báo cáo theo đối tác | contract, read |

---

## 8. UI/UX Dashboard

### 8.1 Contract List View

```
┌──────────────────────────────────────────────────────────────────────────┐
│  [Tạo hợp đồng]  [Bộ lọc: Tất cả ▼]  [Tìm kiếm...]  [Xuất Excel]       │
├──────────────────────────────────────────────────────────────────────────┤
│  Số HĐ    Loại    Đối tác    Giá trị      Đã TH    %     Hết hạn    Trạng thái │
│  ──────── ─────── ────────── ──────────── ──────── ───── ──────────── ────────── │
│  HDMB-... Bán     Cty ABC    500,000,000  320,000K 64%   15/08/2026  ✅ Active  │
│  HDMU-... Mua     NCC XYZ    120,000,000  120,000K 100%  30/05/2026  🔴 Quá hạn │
│  HDMB-... Dịch vụ KH DEF     80,000,000   20,000K  25%   01/12/2026  ⏳ Sắp hết │
│  ...                                                                             │
├──────────────────────────────────────────────────────────────────────────┤
│  📊 Tổng: 15 hợp đồng | Giá trị: 2.5B | Đã TH: 1.8B (72%)               │
└──────────────────────────────────────────────────────────────────────────┘
```

### 8.2 Contract Detail View

Tab-based layout:
1. **Tổng quan:** Thông tin chính, progress bar (thực hiện vs giá trị), biểu đồ tròn paid/unpaid
2. **Lịch thanh toán:** Bảng các đợt + trạng thái từng đợt
3. **Phụ lục:** Danh sách amendment + nút tạo phụ lục mới
4. **Lịch sử thực hiện:** Danh sách fulfillment links (hóa đơn, phiếu thu/chi, nhập/xuất kho)
5. **Thanh lý:** Form thanh lý (nếu đủ điều kiện)

### 8.3 Quick Actions

- **Tạo hóa đơn từ hợp đồng:** Pre-fill customer/item/value từ contract data
- **Tạo phiếu thu/chi từ hợp đồng:** Pre-fill partner, amount (suggest từ payment schedule)
- **Gia hạn hợp đồng:** Tạo amendment kéo dài end_date

---

## 9. Implementation Checklist

### Phase 1 — Core Data Model & CRUD (Day 1)

```
[ ] 001 — Migration: Create contracts table
[ ] 002 — Migration: Create contract_payment_schedules table
[ ] 003 — Migration: Create contract_fulfillment_links table
[ ] 004 — Migration: Create contract_amendments table
[ ] 005 — Migration: Create contract_templates table
[ ] 006 — Model: src/Domain/Model/Contract.php
[ ] 007 — Model: src/Domain/Model/ContractPaymentSchedule.php
[ ] 008 — Model: src/Domain/Model/ContractAmendment.php
[ ] 009 — Repository Interface: src/Domain/Repository/ContractRepositoryInterface.php
[ ] 010 — PDO Repository: src/Infrastructure/Repository/PDOContractRepository.php
[ ] 011 — Service: src/Domain/Service/ContractService.php (CRUD + status transitions)
[ ] 012 — Controller: src/Interfaces/HTTP/Contract/ContractController.php
[ ] 013 — Routes: config/routes.php
[ ] 014 — DI: config/services.php
```

### Phase 2 — Fulfillment Tracking & Integration (Day 2)

```
[ ] 015 — Service: ContractCallbackHandler (hook vào ApService/ArService)
[ ] 016 — Update ApService: inject ContractCallbackHandler, call onInvoiceCreated/onPaymentCreated
[ ] 017 — Update ArService: inject ContractCallbackHandler, call onInvoiceCreated/onPaymentCreated
[ ] 018 — Service method: ContractService::linkDocument()
[ ] 019 — Service method: ContractService::getFulfillmentSummary()
[ ] 020 — API: GET /api/contracts/{id}/fulfillment
[ ] 021 — API: POST /api/contracts/{id}/link
[ ] 022 — Blocking rule: fulfilled_value > total_value
```

### Phase 3 — Payment Schedule & Amendments (Day 3)

```
[ ] 023 — Service methods: Schedule CRUD
[ ] 024 — Validation: Schedule total = contract value
[ ] 025 — Service method: ContractService::markSchedulePaid()
[ ] 026 — API: GET/PUT /api/contracts/{id}/payment-schedule
[ ] 027 — Service method: ContractService::createAmendment()
[ ] 028 — Service method: ContractService::approveAmendment()
[ ] 029 — API: GET/POST /api/contracts/{id}/amendments
[ ] 030 — Logic: Amendment updates contract total_value + end_date
[ ] 031 — Migration: voucher_sequences seed for 'HDMB', 'HDMU', 'PL-HDMB', 'PL-HDMU'
```

### Phase 4 — Status Lifecycle & Alerts (Day 3-4)

```
[ ] 032 — Service methods: activate/suspend/complete/liquidate/cancel
[ ] 033 — Status transition validation (State Machine)
[ ] 034 — Alert: Expiring contracts (30 days)
[ ] 035 — Alert: Overdue contracts
[ ] 036 — Alert: 80%+ fulfillment
[ ] 037 — Expired contract blocking (configurable)
[ ] 038 — Naming: VoucherService prefix 'HDMB' (Hợp đồng mua bán),
         'HDMU' (Hợp đồng mua), 'PL-HDMB' (Phụ lục)
```

### Phase 5 — Views & UI (Day 4)

```
[ ] 039 — View: public/views/contracts/list.php
[ ] 040 — View: public/views/contracts/detail.php
[ ] 041 — View: public/views/contracts/form.php (create/edit)
[ ] 042 — Sidebar: Thêm menu "Hợp đồng" vào layout
[ ] 043 — Dashboard: Top 5 hợp đồng sắp hết hạn
[ ] 044 — Dashboard: Biểu đồ giá trị hợp đồng theo tháng
[ ] 045 — CSV Export: Danh sách hợp đồng
```

### Phase 6 — Tests & Polish (Day 4)

```
[ ] 046 — Tests: tests/ContractServiceTest.php (happy path + failure cases)
[ ] 047 — Tests: tests/ContractFulfillmentTest.php
[ ] 048 — Tests: tests/ContractAmendmentTest.php
[ ] 049 — Permissions: Thêm module 'contract' vào RBAC
[ ] 050 — Audit: AuditLogger::log() trong mọi service method
[ ] 051 — Full test suite: 0 failures
[ ] 052 — PHP syntax check: php -l trên mọi file
```

---

## 10. Effort Estimate

| Phase | Tasks | Developer-days |
|---|---|---|
| Phase 1: Core + CRUD | 14 tasks | 1.0 |
| Phase 2: Fulfillment + Integration | 8 tasks | 0.75 |
| Phase 3: Payment Schedule + Amendments | 9 tasks | 0.75 |
| Phase 4: Status Lifecycle + Alerts | 7 tasks | 0.5 |
| Phase 5: Views & UI | 7 tasks | 0.75 |
| Phase 6: Tests & Polish | 7 tasks | 0.5 |
| **Total** | **52 tasks** | **4.25 days** |

**Risk adjustment:** +25% cho integration complexity (ApService/ArService modification)
**Contingency:** +0.5 day cho unforeseen edge cases
**Total estimate:** **5 days**

---

## Appendix A: Voucher Numbering

| Prefix | Loại | Format |
|---|---|---|
| `HDMB` | Hợp đồng mua bán (sales) | `HDMB2026-000001` |
| `HDMU` | Hợp đồng mua (purchase) | `HDMU2026-000001` |
| `HDDV` | Hợp đồng dịch vụ | `HDDV2026-000001` |
| `PL-HDMB` | Phụ lục hợp đồng bán | `PL-HDMB2026-000001` |
| `PL-HDMU` | Phụ lục hợp đồng mua | `PL-HDMU2026-000001` |
| `BTL` | Biên bản thanh lý | `BTL2026-000001` |

---

## Appendix B: Sample JSON Contract API Payload

```json
{
  "reference": "HDMB2026-000042",
  "type": "sales",
  "partner_id": "cus_abc123",
  "partner_name": "Công ty TNHH ABC",
  "partner_code": "ABC001",
  "start_date": "2026-06-01",
  "end_date": "2026-12-31",
  "total_value": 500000000,
  "net_value": 454545455,
  "tax_rate": 10.00,
  "tax_amount": 45454545,
  "payment_terms": "Đợt 1: 30% đặt cọc, Đợt 2: 40% khi giao hàng, Đợt 3: 30% sau nghiệm thu",
  "description": "Cung cấp phần mềm kế toán và triển khai",
  "created_by": "user_admin",
  "payment_schedule": [
    {"installment_no": 1, "description": "Đặt cọc 30%", "due_date": "2026-06-01", "amount": 150000000},
    {"installment_no": 2, "description": "Giao hàng đợt 1", "due_date": "2026-08-15", "amount": 200000000},
    {"installment_no": 3, "description": "Nghiệm thu", "due_date": "2026-12-31", "amount": 150000000}
  ]
}
```
