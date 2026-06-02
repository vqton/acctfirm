# 10 HIGH-Priority Gaps — Use Cases & Implementation Spec

> **Nguồn:** MISA helpsme, BRAVO.com.vn, FAST.com.vn, GDT (gdt.gov.vn), Thuvienphapluat, EasyBooks, Ketoan Le Anh, AGS Accounting, Sanketoan  
> **Ngày:** 02/06/2026  
> **Module maturity:** Bookwise 5.8/10 → Mục tiêu: 8.5/10  
> **Xem thêm:** Deep-dive parity specs tại `docs/analysis/gap-specs/` (mỗi gap 1 file riêng)

---

## Master Index → Individual Parity Specs

| Gap | Title | File | Lines | Effort | Priority |
|-----|-------|------|-------|--------|----------|
| 1 | Sales Order Module | [gap-01-sales-order.md](gap-specs/gap-01-sales-order.md) | 638 | 5-7 days | P0 |
| 2 | Cost/Manufacturing | [gap-02-cost-manufacturing.md](gap-specs/gap-02-cost-manufacturing.md) | 1,283 | 10-14 days | P0 |
| 3 | Budget & Planning | [gap-03-budget-planning.md](gap-specs/gap-03-budget-planning.md) | 478 | 4-5 days | P1 |
| 4 | Contract Management | [gap-04-contract-management.md](gap-specs/gap-04-contract-management.md) | 777 | 3-4 days | P1 |
| 5 | Project Accounting | [gap-05-project-accounting.md](gap-specs/gap-05-project-accounting.md) | 897 | 5-7 days | P0 |
| 6 | BC09 Notes to FS | [gap-06-bc09-notes-to-fs.md](gap-specs/gap-06-bc09-notes-to-fs.md) | 763 | 3-4 days | P0 |
| 7 | Custom Report Builder | [gap-07-custom-report-builder.md](gap-specs/gap-07-custom-report-builder.md) | 625 | 5-7 days | P1 |
| 8 | Subsidiary Ledgers + Print | [gap-08-subsidiary-ledgers-print.md](gap-specs/gap-08-subsidiary-ledgers-print.md) | 860 | 3-4 days | P0 |
| 9 | Mobile App (PWA) | [gap-09-mobile-app-pwa.md](gap-specs/gap-09-mobile-app-pwa.md) | 1,003 | 2-3 days | P2 |
| 10 | PDF/Excel Export | [gap-10-pdf-excel-export.md](gap-specs/gap-10-pdf-excel-export.md) | 614 | 5-6 days | P0 |

**Tổng effort tất cả gaps:** 45-62 days  
**Quick wins (Phase 1):** Gap 8 (3-4d) + Gap 10 (5-6d) + Gap 6 (3-4d) = 11-14 days — zero new business logic, mostly UI + export

---

## Gap 1: Sales Order Module ([Full Spec](gap-specs/gap-01-sales-order.md))

## Gap 1: Sales Order Module (0/10)

### Business Context
Bookwise ghi nhận doanh thu qua JournalService (bút toán Nợ 131/Có 511) nhưng không có quy trình bán hàng end-to-end. MISA hỗ trợ 7+ kiểu bán hàng (báo giá → đơn hàng → hóa đơn → xuất kho → thu tiền). Thiếu quy trình này = kế toán phải nhập tay 3-4 chứng từ riêng rẽ, mất audit trail từ đơn hàng đến doanh thu.

### Use Case 1.1: Bán hàng theo đơn đặt hàng (Happy Path)

**Actor:** Nhân viên bán hàng, Kế toán bán hàng, Thủ kho  
**Trigger:** Khách hàng gửi đơn đặt hàng  
**Precondition:** Khách hàng đã có trong danh mục, mặt hàng đã có trong kho

**Steps:**
1. Nhân viên bán hàng nhập Đơn đặt hàng (SalesOrder): customer_id, order_date, items (product_id, qty, unit_price), delivery_date, payment_terms
2. Hệ thống kiểm tra tồn kho: nếu đủ → status = `confirmed`; nếu thiếu → status = `pending_stock`, gửi cảnh báo
3. Kế toán bán hàng tạo Chứng từ bán hàng từ Đơn đặt hàng (copy thông tin khách hàng + mặt hàng)
4. Lựa chọn: `action = create_invoice` (kèm hóa đơn) hoặc `ship_first` (xuất kho trước, xuất hóa đơn sau)
5. Nếu `create_invoice`:
   - Hệ thống gọi EInvoiceGateway để sinh hóa đơn điện tử (XML TT32 v2.0.0)
   - Tự động post bút toán: Nợ 131/Có 511 (doanh thu), Nợ 632/Có 156 (giá vốn)
   - Tự động sinh phiếu xuất kho (nếu `kiêm_phieu_xuat = true`)
6. Nếu `ship_first`:
   - Kế toán kho lập Phiếu xuất kho → Thủ kho ghi sổ
   - Xuất hóa đơn sau → hệ thống post bút toán doanh thu
7. Thanh toán: nếu `payment_method = cash/bank` → tự động sinh Phiếu thu (CashService)
8. SalesOrder status → `completed`

**Alternative 1.1a:** Bán hàng không qua kho (dịch vụ) — không sinh phiếu xuất kho, chỉ post Nợ 131/Có 511  
**Alternative 1.1b:** Bán hàng từ Báo giá (Quotation) → chuyển thành Đơn đặt hàng → quy trình tương tự  
**Exception 1.1e:** Khách hàng hủy đơn hàng → SalesOrder status = `cancelled`, hóa đơn đã phát hành → gọi cancelInvoice (nếu đã publish)

### Use Case 1.2: Bán hàng trả lại / Giảm giá

**Trigger:** Khách hàng trả hàng  
**Steps:**
1. Kế toán tạo Phiếu nhập kho trả lại (linked to original SalesOrder)
2. Hệ thống post bút toán đảo: Nợ 156/Có 632 (giá vốn), Nợ 511/Có 131 (doanh thu)
3. Nếu đã xuất hóa đơn → gọi replaceInvoice (âm hoặc điều chỉnh)
4. SalesOrder có `return_total` cập nhật

### Data Entities (NEW)

```sql
-- Đơn đặt hàng
sales_orders
  id, reference (SO{YYYY}-{000000}), customer_id, order_date, delivery_date,
  payment_terms, status (draft|confirmed|pending_stock|shipped|invoiced|completed|cancelled),
  total_amount, discount_amount, tax_amount, grand_total,
  created_by, approved_by, created_at, updated_at

sales_order_lines
  id, sales_order_id, product_id, qty_ordered, qty_shipped, qty_invoiced,
  unit_price, discount_pct, tax_rate, line_total

-- Liên kết giữa SalesOrder và các chứng từ phát sinh
sales_order_links
  id, sales_order_id, transaction_id, document_type (sales_invoice|delivery_order|credit_note|receipt),
  created_at
```

### Integration Points
| Module | Integration |
|---|---|
| InventoryService | Check stock, create delivery order, update qty_committed |
| EInvoiceService | Create/replace/cancel e-invoice linked to sales order |
| JournalService | Post revenue + COGS entries |
| CashService | Auto-create receipt if payment_method = cash/bank |
| ArService | Track receivable by customer + sales order reference |

### Effort: 5-7 days (full stack: DB + Model + Service + Controller + View)

---

## Gap 2: Cost/Manufacturing (0/10)

### Business Context
Bookwise có InventoryService (FIFO/Weighted Average cho xuất kho) nhưng không có tính giá thành sản phẩm sản xuất. BRAVO có MES-level manufacturing (BOM, routing, work orders, OEE tracking). FAST có 6-step cost allocation engine. DN sản xuất không thể dùng Bookwise nếu thiếu tính năng này.

### Use Case 2.1: Tính giá thành sản phẩm sản xuất (Period-end)

**Actor:** Kế toán giá thành  
**Trigger:** Cuối kỳ kế toán (tháng/quý)  
**Precondition:** Tất cả chi phí đã hạch toán (621/622/627), đã nhập kho thành phẩm

**FAST 6-Step Process:**
1. **Tính số lượng sản xuất:** Đếm số lượng thành phẩm nhập kho trong kỳ (mã GD = nhập từ SX)
2. **Tính & áp giá xuất kho NVL:** Tính đơn giá xuất cho nguyên vật liệu (FIFO/WA)
3. **Tập hợp & phân bổ CP NVL chi tiết theo mã NVL:** Đọc sổ kho, phân bổ theo định mức
4. **Tập hợp & phân bổ CP NVL không chi tiết, CP nhân công & CP chung:** Phân bổ theo hệ số
5. **Tổng hợp chi phí & tính giá thành đơn vị:** (DDĐK + PS - DDCK) / SL nhập kho
6. **Cập nhật giá cho phiếu nhập thành phẩm:** Áp giá vào phiếu nhập kho

**Post-step:** Tạo bút toán kết chuyển Nợ 154/Có 621,622,627

### Use Case 2.2: Sản xuất nhiều công đoạn (Multi-stage)

**Scenario:** Sản phẩm qua N công đoạn, mỗi công đoạn tạo bán thành phẩm  
**Process:**
- Vòng 1: NVL → BTP công đoạn 1
- Vòng 2: BTP công đoạn 1 → BTP công đoạn 2 (dùng phiếu điều chuyển công đoạn)
- ...
- Vòng N: BTP → Thành phẩm
- Mỗi vòng lặp lại steps 2-6

### Use Case 2.3: Định mức NVL & BOM (Bill of Materials)

**Steps:**
1. Khai báo BOM: sản phẩm X = NVL_A * 2 + NVL_B * 0.5 + BTP_Y * 1
2. Khi xuất NVL cho sản xuất, hệ thống so sánh thực tế vs định mức
3. Cảnh báo nếu vượt định mức > config key `cost.warn_excess_pct` (default: 10%)

### Use Case 2.4: Đánh giá sản phẩm dở dang (WIP)

**3 methods:**
1. **Chi phí NVL trực tiếp:** DD CK = NVL (đơn giản nhất)
2. **Ước lượng sản phẩm tương đương:** DD CK = NVL + NC*(%HT) + CPSXC*(%HT)
3. **Bán thành phẩm dở dang trên dây chuyền:** DD CK = chi phí từng công đoạn

### Data Entities (NEW)

```sql
bom (Bill of Materials)
  id, product_id, version, status (draft|active|archived), effective_date, created_at

bom_lines
  id, bom_id, material_id, qty_per_unit, wastage_pct, unit

production_orders
  id, reference (PO{YYYY}-{000000}), product_id, qty, start_date, end_date,
  status (draft|released|in_progress|completed|cancelled),
  bom_id, workshop_id

production_order_materials
  id, production_order_id, material_id, planned_qty, actual_qty, unit

cost_period_runs
  id, period_year, period_month, status (running|completed|failed),
  started_at, completed_at, error_log TEXT
```

### Integration Points
| Module | Integration |
|---|---|
| InventoryService | NVL xuất kho, thành phẩm nhập kho |
| JournalService | Post 621/622/627 → 154 kết chuyển |
| FsService | Cost affects BC02 (giá vốn 632) |
| ConfigService | Allocation methods, wastage rates |

### Effort: 10-14 days (Phases: 1=BOM+prod order, 2=single-stage costing, 3=multi-stage)

---

## Gap 3: Budget & Planning (0/10)

### Business Context
MISA AMIS có phân hệ Ngân sách riêng với lập dự toán revenue/cost/profit theo phòng ban, tháng/quý/năm, biểu đồ so sánh actual vs budget. BRAVO có budget trong ERP với kiểm soát chi tiêu. Bookwise không có — kế toán phải làm budget bằng Excel.

### Use Case 3.1: Lập dự toán ngân sách

**Actor:** Kế toán trưởng / Giám đốc tài chính  
**Trigger:** Đầu năm tài chính  
**Steps:**
1. Vào phân hệ Ngân sách → Tạo kế hoạch mới
2. Chọn: năm, phòng ban, loại (Doanh thu/Chi phí/Lợi nhuận)
3. Hệ thống load các khoản mục doanh thu/chi phí từ danh mục
4. Nhập dự toán cho từng khoản mục theo tháng/quý/năm
5. Lưu → status = `draft` → trình duyệt → `approved`
6. Hệ thống tự động tính lợi nhuận dự toán = doanh thu - chi phí

### Use Case 3.2: So sánh thực tế vs dự toán

**Trigger:** Báo cáo cuối tháng/quý  
**Steps:**
1. Hệ thống tổng hợp số phát sinh thực tế từ ledger_entries
2. Map với khoản mục ngân sách (qua account_code hoặc cost_center_id)
3. Tính chênh lệch: actual - budget, actual/budget %
4. Hiển thị biểu đồ: cột, đường, tròn
5. Cảnh báo nếu chi phí vượt budget > config key `budget.warn_threshold_pct` (default: 110%)

### Data Entities (NEW)

```sql
budget_plans
  id, year, department_id, type (revenue|cost|profit|all),
  status (draft|submitted|approved|rejected), created_by, approved_by,
  created_at, updated_at

budget_lines
  id, budget_plan_id, account_code, cost_center_id, month_01..month_12 DECIMAL(15,2),
  total DECIMAL(15,2)

budget_actuals (view or materialized)
  budget_line_id, month, budget_amount, actual_amount, variance, variance_pct
```

### Integration Points
| Module | Integration |
|---|---|
| AccountRepository | Read actual balances |
| ConfigService | Budget thresholds |
| FsService | Budget variance notes in BC09 |

### Effort: 4-5 days

---

## Gap 4: Contract Management (0/10)

### Business Context
MISA SME hỗ trợ hợp đồng mua/bán, theo dõi giá trị, thanh lý, công nợ theo hợp đồng. FAST Construction theo dõi hợp đồng nhận thầu/phụ thầu. BRAVO có contract lifecycle trong ERP. Bookwise không có — hợp đồng quản lý bằng file Word riêng.

### Use Case 4.1: Quản lý hợp đồng mua hàng (Purchase Contract)

**Actor:** Nhân viên mua hàng, Kế toán kho  
**Trigger:** Ký hợp đồng với nhà cung cấp  
**Steps:**
1. Nhập hợp đồng: contract_no, supplier_id, start/end date, total_value, payment_terms
2. Hệ thống theo dõi giá trị thực hiện qua các lần nhập kho / thanh toán
3. Cảnh báo khi giá trị thực hiện vượt hợp đồng
4. Khi kết thúc → lập biên bản thanh lý → contract status = `liquidated`

### Use Case 4.2: Quản lý hợp đồng bán hàng (Sales Contract)

**Actor:** Nhân viên bán hàng, Kế toán bán hàng  
**Steps:**
1. Nhập hợp đồng: khách hàng, sản phẩm, giá trị, tiến độ thanh toán
2. Tự động sinh lịch thanh toán (payment schedule)
3. Mỗi lần xuất hóa đơn / thu tiền → cập nhật giá trị thực hiện
4. Báo cáo: doanh thu theo hợp đồng, công nợ theo hợp đồng

### Data Entities (NEW)

```sql
contracts
  id, reference, type (purchase|sales|construction|service),
  partner_id, start_date, end_date, total_value DECIMAL(15,2),
  payment_terms, status (draft|active|suspended|completed|liquidated|cancelled),
  created_by, approved_by

contract_payment_schedule
  id, contract_id, due_date, amount, paid_amount, status (pending|partial|paid)

contract_fulfillment_links
  id, contract_id, transaction_id, document_type (invoice|receipt|delivery_note|payment),
  amount, created_at
```

### Integration Points
| Module | Integration |
|---|---|
| ApService | Track payable by contract |
| ArService | Track receivable by contract |
| EInvoiceService | Link invoice to contract |
| CashService | Link payment to contract schedule |

### Effort: 3-4 days

---

## Gap 5: Project Accounting (0/10)

### Business Context
Đây là điểm mạnh nhất của FAST Construction (phiên bản riêng cho xây lắp) và MISA SME phiên bản xây dựng. Bookwise không hỗ trợ — mất thị trường DN xây lắp (chiếm ~15% DN Việt Nam).

### Use Case 5.1: Theo dõi dự toán công trình

**Actor:** Kế toán công trình  
**Steps:**
1. Khai báo thông tin dự án: mã, tên, chủ đầu tư, ngày bắt đầu, giá trị hợp đồng
2. Nhập dự toán chi phí (từ file Excel dự toán xây dựng)
3. Dự toán chi tiết theo: mã vật tư, khoản mục (NVL, nhân công, máy thi công, chung)
4. Cập nhật dự toán bổ sung (nếu phát sinh)

### Use Case 5.2: Tập hợp chi phí theo công trình

**Steps:**
1. Mọi chứng từ mua hàng, xuất kho, lương, khấu hao TSCĐ đều gắn mã công trình
2. Hệ thống tự động tập hợp chi phí vào 154 theo từng công trình
3. Phân bổ chi phí chung (máy thi công dùng nhiều công trình) theo hệ số
4. Cuối kỳ tạo báo cáo: chi phí dở dang, giá trị thực hiện, giá trị đã xuất hóa đơn

### Use Case 5.3: Ghi nhận doanh thu & xác định lãi/lỗ công trình

**Steps:**
1. Khi nghiệm thu giai đoạn → ghi nhận doanh thu tương ứng
2. Kết chuyển giá vốn: so sánh giữa chi phí dở dang và doanh thu (phương pháp tỷ lệ)
3. Báo cáo lãi/lỗ theo từng công trình, so sánh với dự toán
4. Cảnh báo công trình lỗ vượt > config key `project.loss_warn_pct`

### Use Case 5.4: Điều chuyển vật tư giữa các công trình

**Steps:**
1. Xuất kho NVL công trình A → nhập kho công trình B
2. Hệ thống ghi nhận điều chuyển, cập nhật chi phí từng công trình

### Data Entities (NEW)

```sql
projects
  id, code, name, type (construction|service|internal), status,
  customer_id, start_date, end_date, contract_value, budget_total,
  parent_project_id (hỗ trợ phân cấp dự án → hạng mục),
  created_by

project_estimates
  id, project_id, line_type (material|labor|machine|overhead),
  material_code, description, qty, unit_price, total_cost

project_transaction_links
  id, project_id, transaction_id, document_type, cost_amount, created_at

project_phases
  id, project_id, phase_name, phase_value, completion_pct, invoice_value, received_amount
```

### Integration Points
| Module | Integration |
|---|---|
| InventoryService | Track inventory by project |
| JournalService | Post 154 by project dimension |
| ContractService | Link project to contract |
| FsService | Project P&L in BC09 section |

### Effort: 5-7 days

---

## Gap 6: BC09 — Notes to Financial Statements (3/10 → 10/10)

### Business Context
Bookwise hiện có FsService với BC01/BC02/BC03. BC09 (Thuyết minh BCTC) required by TT 99/2025 (mẫu B09-DN) có 9 phần chính + 22+ chính sách kế toán + chỉ tiêu bổ sung cho từng khoản mục. Hiện chưa implement.

### Use Case 6.1: Tự động lập BC09 từ số dư tài khoản

**Actor:** Kế toán tổng hợp  
**Trigger:** Cuối năm tài chính  
**Precondition:** BC01/BC02/BC03 đã được finalize

**BC09 Sections (TT 99/2025):**
1. **Đặc điểm hoạt động DN** (thủ công — nhập 1 lần)
2. **Kỳ kế toán, đơn vị tiền tệ** (tự động từ config)
3. **Chuẩn mực & Chế độ kế toán** (thủ công — nhập 1 lần)
4. **Các chính sách kế toán** (22+ policy templates — chọn/xác nhận)
5. **Thông tin bổ sung cho các khoản mục trên BC01** (tự động từ ledger):
   - Tiền (1111,1112,1113)
   - Đầu tư tài chính
   - Phải thu (131, 138...)
   - Hàng tồn kho (152,153,155,156,157)
   - TSCĐ (211,212,213,214)
   - Đầu tư BĐS
   - XDCB dở dang (241)
6. **Thông tin bổ sung cho BC02** (tự động):
   - Doanh thu từng loại (5111..5118)
   - Chi phí theo yếu tố
7. **Thông tin bổ sung cho BC03** (tự động)
8. **Thông tin theo lĩnh vực** (segment reporting — thủ công)
9. **Các thông tin khác** (thủ công)

### Use Case 6.2: Tự động đối chiếu số liệu BC09 với BC01/BC02

**Steps:**
1. Hệ thống tự động map từng chỉ tiêu BC09 với mã số tương ứng trên BC01/02/03
2. Check cross-reference: tổng tiền = tiền mặt + tiền gửi + tiền đang chuyển
3. Cảnh báo nếu số liệu không khớp
4. Tạo báo cáo đối chiếu: BC09 chỉ tiêu X → BC01 chỉ tiêu Y

### Data Entities (NEW)

```sql
fs_notes_config
  id, section_code, indicator_code, indicator_name,
  formula_expression TEXT (ví dụ: '1111+1112+1113'),
  account_codes TEXT (danh sách TK cách dấu phẩy),
  is_auto_calc BOOLEAN, is_required BOOLEAN

fs_notes_data
  id, period_id, section_code, indicator_code, year_start DECIMAL(15,2), year_end DECIMAL(15,2),
  note TEXT, is_manual BOOLEAN, created_by
```

### Effort: 3-4 days

---

## Gap 7: Custom Report Builder (0/10)

### Business Context
MISA AMIS có "Báo cáo tự tạo" với 3-step wizard: filter → report type (list/pivot) → parameters. Biểu đồ (cột/tròn/đường). Chia sẻ theo role. Export Excel. FAST có báo cáo tùy chỉnh mạnh. Bookwise chỉ có báo cáo cố định.

### Use Case 7.1: Tạo báo cáo tùy chỉnh

**Actor:** Kế toán trưởng / Giám đốc  
**Steps:**
1. Vào Báo cáo tự tạo → Tạo mới
2. **Bước 1 — Chọn nguồn dữ liệu:**
   - Account balances (ledger_entries)
   - Transactions
   - Customers/Suppliers (ap_ar)
   - Inventory (items, warehouse_stock)
3. **Bước 2 — Thiết lập bảng:**
   - Chọn cột hiển thị (drag-drop)
   - Chọn nhóm (group by): department, project, account, month
   - Chọn phép tính: SUM, AVG, COUNT, MIN, MAX
   - Chọn bộ lọc (filter): date range, status, account range
4. **Bước 3 — Thiết lập tham số:**
   - Chọn tham số đầu vào (kỳ báo cáo, đơn vị...)
   - Lưu template
5. **Chia sẻ:** với user/role cụ thể

### Use Case 7.2: Tạo Dashboard

**Steps:**
1. Chọn biểu đồ: cột dọc, cột ngang, cột chồng, tròn, đường
2. Chọn dữ liệu: trục X, trục Y, nhóm
3. Thêm vào màn hình Tổng quan
4. Tự động refresh theo kỳ

### Data Architecture

```sql
custom_reports
  id, name, data_source, report_type (list|pivot|chart),
  config JSON (columns, groups, filters, sort, chart_type),
  parameters JSON (param_name, param_type, default_value),
  shared_with JSON (user_ids|role_ids),
  created_by, created_at

custom_report_data (generated at runtime via query builder)
  SELECT fields FROM data_source WHERE filters
  GROUP BY groups ORDER BY sort
```

### Query Builder Logic
```php
// Build SQL dynamically from report config
$queryBuilder->from($dataSource)
    ->select($columns)
    ->where($filters)
    ->groupBy($groups)
    ->orderBy($sorts)
    ->paginate($page, $perPage);
```

**Security:** Query builder MUST use whitelist of allowed columns/tables. NEVER allow raw SQL. PDO parameterized for all values.

### Effort: 5-7 days

---

## Gap 8: Subsidiary Ledgers + Print (0/10)

### Business Context
Sổ chi tiết (sub-ledger) là yêu cầu bắt buộc theo TT 99/2025. DN cần in sổ: Sổ chi tiết tiền mặt, Sổ chi tiết bán hàng, Sổ chi tiết công nợ, Sổ kho. Bookwise có dữ liệu nhưng chưa có giao diện in ấn đúng chuẩn.

### Use Case 8.1: Sổ chi tiết tài khoản (Sổ cái)

**Actor:** Kế toán tổng hợp  
**Trigger:** Cuối tháng/quý/năm  
**Steps:**
1. Chọn tài khoản, kỳ báo cáo
2. Hệ thống load: số dư đầu kỳ + phát sinh Nợ/Có từng chứng từ + số dư cuối kỳ
3. Hiển thị dạng bảng: ngày, số CT, diễn giải, TK đối ứng, PS Nợ, PS Có
4. In PDF → đóng dấu, ký số

### Use Case 8.2: Sổ kho

**Actor:** Thủ kho  
**Steps:**
1. Chọn mặt hàng, kho, kỳ báo cáo
2. Hiển thị: tồn đầu + nhập + xuất + tồn cuối theo từng chứng từ
3. Hỗ trợ tính giá xuất (FIFO/WA) theo từng lần xuất

### Use Case 8.3: Sổ chi tiết công nợ

**Steps:**
1. Chọn khách hàng/nhà cung cấp, kỳ báo cáo
2. Hiển thị: phát sinh Nợ/Có, số dư cuối kỳ, tuổi nợ
3. In bảng đối chiếu công nợ gửi khách hàng

### Data Architecture

```php
// Pattern cho tất cả sổ chi tiết:
abstract class SubLedgerReport {
    abstract function getOpeningBalance(Period $period, $entityId): float;
    abstract function getTransactions(Period $period, $entityId): array;
    abstract function getClosingBalance(Period $period, $entityId): float;

    final function render(Period $period, $entityId, string $format = 'html'|'pdf'|'xlsx'): Output;
}
```

### Effort: 3-4 days (for all 3 sổ + PDF/XLSX export)

---

## Gap 9: Mobile App (0/10)

### Business Context
MISA AMIS có mobile app cho iOS/Android. Bookwise là web app không responsive — không dùng được trên mobile. Tuy nhiên, đây là P2 (nice-to-have), có thể làm PWA trước.

### Use Case 9.1: Dashboard mobile (Read-only)

**Actor:** Giám đốc / Kế toán trưởng  
**Features:**
- Tổng quan: doanh thu, chi phí, lợi nhuận (real-time from FsService)
- Công nợ đến hạn
- Tồn kho thấp
- Dòng tiền
- Biểu đồ (từ Custom Report Builder)

### Use Case 9.2: Duyệt chứng từ mobile

**Actor:** Giám đốc (phê duyệt)  
**Steps:**
1. Nhận thông báo push: "Phiếu chi 5tr chờ duyệt"
2. Mở mobile app → xem chi tiết
3. Duyệt / Từ chối (có ghi audit trail)

### Architecture

**Phase 1 — PWA (Progressive Web App):**
- Same PHP backend
- Manifest.json + Service Worker cho offline cache
- View responsive (Bootstrap 5 đã responsive, cần điều chỉnh sidebar/menu)

**Phase 2 — Native app (Flutter/React Native):**
- REST API endpoints đã có (cần thêm)
- Push notification qua Firebase

### Effort: 2-3 days (PWA), 10-14 days (Native)

---

## Gap 10: PDF/Excel Export (0/10)

### Business Context
Đây là basic feature mà mọi phần mềm đối thủ đều có. Bookwise trả JSON — kế toán không thể in nộp thuế.

### Use Case 10.1: Export chứng từ ra PDF

**Actor:** Kế toán  
**Scope:**
- Phiếu thu/chi → PDF (mẫu in)
- Hóa đơn → PDF (từ XML e-invoice)
- Báo cáo tài chính (BC01/02/03/09) → PDF
- Sổ chi tiết → PDF
- Bảng lương → PDF

### Use Case 10.2: Export báo cáo ra Excel

**Actor:** Kế toán  
**Scope:**
- Trial balance → XLSX
- BC01/BC02 (dạng Excel để gửi email)
- Báo cáo thuế (01/GTGT, 03/TNDN, 05/KK-TNCN)
- Sổ cái → Excel (để pivot phân tích)
- Custom report → XLSX

### Use Case 10.3: Export dữ liệu phục vụ kiểm toán

**Features:**
- Export ActionJournal (JSONL) cho kiểm toán
- Export all ledger_entries theo kỳ
- Export customer/供应商 aging

### Architecture

```php
interface ExportServiceInterface {
    public function toPdf(string $templateId, array $data, array $options = []): string; // returns binary
    public function toXlsx(string $templateId, array $data, array $options = []): string;
    public function toCsv(string $templateId, array $data, array $options = []): string;
}

// Implementation: Uses PHP built-in classes
// PDF: TCPDF or FPDF (no Composer — include manually)
// XLSX: Simple PHP XLSX writer (custom, ~500 lines)
// CSV: fputcsv built-in
```

**Constraint:** Không dùng Composer (per §4.2 AGENTS.md). PDF library bundled in `src/Accounting/Infrastructure/Export/Lib/`.

### Effort: 3-5 days

---

## Implementation Summary

| Gap | Priority | Effort | Dependencies | Phase |
|---|---|---|---|---|
| 1. Sales Order | HIGH | 5-7d | InventoryService, EInvoiceService | Phase 1 (Weeks 1-2) |
| 2. Cost/Manufacturing | HIGH | 10-14d | InventoryService, JournalService | Phase 2 (Weeks 3-4) |
| 3. Budget & Planning | HIGH | 4-5d | FsService, AccountRepository | Phase 2 (Week 4) |
| 4. Contract Management | HIGH | 3-4d | ApService, ArService | Phase 1 (Week 2) |
| 5. Project Accounting | HIGH | 5-7d | ContractService, InventoryService | Phase 3 (Weeks 5-6) |
| 6. BC09 Notes to FS | HIGH | 3-4d | FsService (existing) | Phase 1 (Week 2) |
| 7. Custom Report Builder | HIGH | 5-7d | All repositories | Phase 3 (Weeks 5-6) |
| 8. Subsidiary Ledgers + Print | HIGH | 3-4d | All repositories | Phase 1 (Week 1) |
| 9. Mobile App | MEDIUM | 2-3d PWA | Custom Report, Approval | Phase 4 (Week 7-8) |
| 10. PDF/Excel Export | HIGH | 3-5d | All modules | Phase 1 (Week 1) |

**Total:** 43-60 days (8-12 weeks) → fits 24-week roadmap from competitive analysis  
**Critical path:** Subsidiary Ledgers → Sales Order → Cost → Budget → Project

### Quick Wins (Phase 1, Weeks 1-2)
Gap 8 (Subsidiary Ledgers) + Gap 10 (PDF/Excel) có thể làm song song — zero new DB tables, chỉ UI + export library. Gap 6 (BC09) reuse existing FsService data.

### Architecture Rule
Mọi gap mới phải tuân thủ Hexagonal Architecture:
- Domain/Contract: `Interface`
- Infrastructure/Repository: `PDOImplementation`
- Service injected via constructor (DI container in services.php)
- Không static, không global
