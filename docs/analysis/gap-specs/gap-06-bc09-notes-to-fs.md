# Gap 06: BC09 Notes to Financial Statements — Parity Specification

**Status:** Draft  
**Priority:** HIGH  
**Effort:** 3-4 days  
**Module:** Financial Statements (FsService)  
**Regulatory Basis:** Thông tư 99/2025/TT-BTC — Mẫu B09-DN  
**VAS/VFAS:** VAS 21 (Trình bày Báo cáo tài chính), VAS 24 (Báo cáo lưu chuyển tiền tệ)  
**Current State:** Prototype (`FsService::tt99()` + `public/views/bc09.php`) — 6 rudimentary sections, no data persistence, no cross-reference validation, no PDF export

---

## 1. Business Context & Regulatory Framework

### 1.1 TT 99/2025 Requirement

Mẫu B09-DN (BC09 — Thuyết minh Báo cáo tài chính) là báo cáo bắt buộc trong bộ Báo cáo tài chính năm theo TT 99/2025/TT-BTC. BC09 cung cấp thông tin chi tiết và giải thích bổ sung cho các chỉ tiêu đã trình bày trên BC01 (B01-DN), BC02 (B02-DN), và BC03 (B03-DN).

**Căn cứ pháp lý:**
- TT 99/2025/TT-BTC Điều 100: BC09 là một phần không thể tách rời của BCTC năm
- VAS 21 Đoạn 14-18: Yêu cầu thuyết minh các chính sách kế toán và giải trình biến động >20%
- Nghị định 41/2018/NĐ-CP (sửa đổi): Phạt 20-30tr nếu BCTC không đầy đủ thành phần

### 1.2 Why BC09 Exists

Accounts do not tell the full story. TK 511 shows total revenue but not revenue by product line. TK 211 shows original cost but not movements (additions/disposals/depreciation). BC09 fills this gap.

BC09 required for:
- **Kiểm toán độc lập:** Auditor's opinion requires BC09 notes — missing notes = qualified opinion
- **CIT finalization:** Cơ quan thuế yêu cầu BC09 với tờ khai quyết toán TNDN
- **Ngân hàng:** Credit review yêu cầu BC notes để đánh giá tình hình tài chính
- **Đối tác:** Đánh giá năng lực tài chính trước khi ký hợp đồng lớn

### 1.3 Integration Points

```
FsService (BC01/BC02/BC03) ──cross-ref──▶ FsNotesService
                                              │
                    AccountRepository ◄───────┤ (getTreeBalance, findByFsMapping)
                                              │
                    ConfigService ◄───────────┤ (entity_name, tax_code, currency)
                                              │
                    PeriodService ◄───────────┤ (current_period, prior_period)
                                              │
                    PostingRuleService ◄──────┤ (control account check for disclosures)
```

---

## 2. BC09 Complete Section Structure (TT 99/2025 Mẫu B09-DN)

### Phần I: Đặc điểm hoạt động của doanh nghiệp

Manual entry fields — configured once, reused every year:

| Mã chỉ tiêu | Tên chỉ tiêu | Nguồn | Ghi chú |
|---|---|---|---|
| I.01 | Hình thức sở hữu vốn | Cấu hình (ConfigService) | Cty TNHH/CP/TNHH 1TV/DNNN |
| I.02 | Ngành nghề kinh doanh chính | Cấu hình | Mô tả ngành nghề |
| I.03 | Giấy CNĐKKD số | Cấu hình | Số GPKD |
| I.04 | Vốn điều lệ | Số dư TK 411 | getTreeBalance('411') |
| I.05 | Tổng số lao động bình quân | Nhập thủ công | Cập nhật hàng năm |
| I.06 | Địa chỉ trụ sở chính | Cấu hình | |

### Phần II: Kỳ kế toán, đơn vị tiền tệ áp dụng

Auto-generated from ConfigService + PeriodService:

| Mã chỉ tiêu | Tên chỉ tiêu | Công thức |
|---|---|---|
| II.01 | Năm tài chính | PeriodService -> period_code |
| II.02 | Kỳ kế toán năm | Từ 01/01/{year} đến 31/12/{year} |
| II.03 | Đơn vị tiền tệ | ConfigService::get('currency', 'VNĐ') |
| II.04 | Phương pháp chuyển đổi ngoại tệ | ConfigService::get('fx_method', 'Tỷ giá thực tế tại ngày giao dịch') |
| II.05 | Hình thức ghi sổ kế toán | ConfigService::get('bookkeeping_form', 'Nhật ký chung') |

### Phần III: Chuẩn mực và Chế độ kế toán áp dụng

Manual entry — template text with placeholders:

```
"Áp dụng Chế độ kế toán doanh nghiệp theo Thông tư 99/2025/TT-BTC và các chuẩn mực kế toán
Việt Nam (VAS) do Bộ Tài chính ban hành."
```

Lưu trữ trong `bc09_config` — chỉ nhập 1 lần, có thể sửa hàng năm.

### Phần IV: Các chính sách kế toán áp dụng (22+ indicators)

Danh sách 22+ chính sách kế toán theo TT 99 và VAS. Mỗi chính sách có:
- **Template text** — sinh từ hệ thống dựa trên cấu hình
- **Manual override** — kế toán có thể sửa

| Mã | Chính sách | Auto source | Template logic |
|---|---|---|---|
| IV.01 | Nguyên tắc ghi nhận tiền và tương đương tiền | ConfigService | "Tiền gồm tiền mặt, tiền gửi, tiền đang chuyển. Tương đương tiền là đầu tư ngắn hạn ≤3 tháng." |
| IV.02 | Nguyên tắc ghi nhận hàng tồn kho | ConfigService | "Phương pháp tính giá xuất kho: {inventory_method}. Phương pháp hạch toán: KKTX." |
| IV.03 | Nguyên tắc ghi nhận TSCĐ hữu hình | ConfigService | "Nguyên giá = giá mua + CP vận chuyển, lắp đặt + CP liên quan trực tiếp." |
| IV.04 | Nguyên tắc ghi nhận TSCĐ vô hình | Template | "Phần mềm, bản quyền, thương hiệu được vốn hóa nếu thỏa mãn VAS 04." |
| IV.05 | Phương pháp khấu hao TSCĐ | ConfigService | "Phương pháp: {depreciation_method}. Tỷ lệ khấu hao theo Thông tư 99." |
| IV.06 | Nguyên tắc ghi nhận chi phí trả trước | ConfigService | "Chi phí trả trước ≤ 12 tháng: phân bổ 1 lần. > 12 tháng: phân bổ dần." |
| IV.07 | Nguyên tắc ghi nhận doanh thu | ConfigService | "Doanh thu bán hàng ghi nhận khi chuyển giao rủi ro và lợi ích. Dịch vụ ghi nhận theo tỷ lệ hoàn thành." |
| IV.08 | Nguyên tắc ghi nhận chi phí tài chính | Template | "Chi phí đi vay ghi nhận vào chi phí SXKD trong kỳ, trừ chi phí đi vay đủ điều kiện vốn hóa." |
| IV.09 | Nguyên tắc ghi nhận thuế TNDN | Template | "Thuế TNDN hiện hành tính trên thu nhập chịu thuế. Thuế hoãn lại ghi nhận cho chênh lệch tạm thời." |
| IV.10 | Nguyên tắc ghi nhận chi phí đi vay | Template | "Theo VAS 16: chi phí đi vay liên quan trực tiếp đến XDCB được vốn hóa." |
| IV.11 | Nguyên tắc ghi nhận các khoản dự phòng | Template | "Dự phòng phải thu khó đòi: {provision_method}. Dự phòng giảm giá HTK: theo giá trị thuần." |
| IV.12 | Nguyên tắc ghi nhận doanh thu HĐTC | Template | "Cổ tức, lãi tiền gửi, lãi trái phiếu ghi nhận khi phát sinh." |
| IV.13 | Nguyên tắc ghi nhận ngoại tệ | ConfigService | "Tỷ giá ghi sổ: {fx_method}. Chênh lệch tỷ giá phân bổ hoặc ghi nhận ngay." |
| IV.14 | Nguyên tắc ghi nhận công cụ tài chính | Template | "Đầu tư nắm giữ đến ngày đáo hạn (HTM): giá gốc. Đầu tư sẵn sàng để bán (AFS): giá hợp lý." |
| IV.15 | Nguyên tắc ghi nhận chi phí thuê TSCĐ | Template | "Thuê tài chính: ghi nhận TS + nợ thuê. Thuê hoạt động: chi phí thuê phân bổ đều." |
| IV.16 | Nguyên tắc ghi nhận tài sản sinh học | Template | "Theo VAS 27: tài sản sinh học ghi nhận theo giá gốc." |
| IV.17 | Nguyên tắc ghi nhận bất động sản đầu tư | Template | "BĐS đầu tư ghi nhận theo giá gốc. Không tính khấu hao." |
| IV.18 | Nguyên tắc ghi nhận quỹ khen thưởng, phúc lợi | Template | "Trích lập quỹ từ lợi nhuận sau thuế theo Nghị định 91/2025." |
| IV.19 | Nguyên tắc ghi nhận lợi nhuận chưa phân phối | Template | "Lợi nhuận sau thuế chưa phân phối theo quyết định của ĐHĐCĐ." |
| IV.20 | Nguyên tắc ghi nhận các khoản nợ tiềm tàng | Template | "Các khoản nợ tiềm tàng được thuyết minh trong BC09, không ghi nhận trên BC01." |
| IV.21 | Nguyên tắc ghi nhận giao dịch bên liên quan | Template | "Giao dịch bên liên quan được trình bày theo VAS 21." |
| IV.22 | Công cụ chuyển đổi ngoại tệ | ConfigService | "Đánh giá lại cuối kỳ theo tỷ giá BIDV/VCB." |

### Phần V: Thông tin bổ sung cho các khoản mục trên BC01

This is the largest section — ~40 indicators mapping BC01 line items to their account-level detail. Each indicator has:
- **year_start_amount** (số dư đầu năm)
- **year_end_amount** (số dư cuối năm)
- **formula_expression** for auto-calculation

| Mã BC09 | Tên chỉ tiêu | BC01 MS | Account mapping | Formula |
|---|---|---|---|---|
| V.01 | Tiền và tương đương tiền | 110 | 1111+1112+1113+1121+1122+113+1281 | '1111+1112+1113+1121+1122+113+1281' |
| V.02 | Đầu tư tài chính ngắn hạn | 120 | 121+1282+1283+1288 | '121+1282+1283+1288' |
| V.03 | Các khoản phải thu ngắn hạn | 130 | 131+136+138+141 | '131+136+138+141' |
| V.04 | Hàng tồn kho | 141 | 151+152+153+154+155+156+157+158 | tree sum all class-2 assets |
| V.05 | TSCĐ hữu hình nguyên giá | 221 | 211 | getTreeBalance('211') |
| V.05a | Hao mòn TSCĐ hữu hình | — | 2141 | getTreeBalance('2141') (negative) |
| V.05b | TSCĐ hữu hình còn lại | 221 | 211-2141 | '211-2141' |
| V.06 | TSCĐ thuê tài chính | 222 | 212 | getTreeBalance('212') |
| V.07 | TSCĐ vô hình | 227 | 213 | getTreeBalance('213') |
| V.08 | Chi phí trả trước | 242 | 242 | getTreeBalance('242') |
| V.09 | Đầu tư vào công ty con | 253 | 221 | getTreeBalance('221') |
| V.10 | Đầu tư vào công ty liên doanh | 254 | 222 | getTreeBalance('222') |
| V.11 | Đầu tư khác dài hạn | 255 | 2281+2288 | '2281+2288' |
| V.12 | Tài sản thiếu chờ xử lý | — | 1381 | getTreeBalance('1381') |
| V.13 | Dự phòng giảm giá HTK | — | 2294 | getTreeBalance('2294') (negative) |
| V.14 | Dự phòng phải thu khó đòi | — | 2293 | getTreeBalance('2293') (negative) |
| V.15 | Vay ngắn hạn | 310 | 331+332+333+334+335+336+337+338 | tree sum all class-3 liability |
| V.16 | Thuế GTGT được khấu trừ | — | 1331+1332 | '1331+1332' |
| V.17 | Thuế và các khoản phải nộp NN | 315 | 333 | getTreeBalance('333') |
| V.18 | Vốn đầu tư CSH | 410 | 411 | getTreeBalance('411') |
| V.19 | Lợi nhuận chưa phân phối | 420 | 421 | getTreeBalance('421') |
| V.20 | Dự phòng giảm giá đầu tư | — | 2291+2292 | '2291+2292' |

### Phần VI: Thông tin bổ sung cho BC02

Disaggregation of income statement items:

#### 6.1 Doanh thu (511) by category:

| Mã | Chỉ tiêu | Account | Source |
|---|---|---|---|
| VI.01 | Doanh thu bán hàng hóa | 5111 | getTreeBalance('5111') |
| VI.02 | Doanh thu cung cấp dịch vụ | 5112 | getTreeBalance('5112') |
| VI.03 | Doanh thu HĐTC | 515 | getTreeBalance('515') |
| VI.04 | Doanh thu nội bộ | 5113 | getTreeBalance('5113') |
| VI.05 | Doanh thu khác | 5118+711 | '5118-5118'+'711-711' |
| VI.06 | Các khoản giảm trừ DT | 521 | getTreeBalance('521') |

#### 6.2 Chi phí theo yếu tố (PP gián tiếp):

| Mã | Yếu tố | Công thức |
|---|---|---|
| VI.10 | Chi phí NVL trực tiếp | Debit turnover to TK 621 in period |
| VI.11 | Chi phí nhân công trực tiếp | Debit turnover to TK 622 in period |
| VI.12 | Chi phí khấu hao TSCĐ | Credit turnover to TK 214 in period |
| VI.13 | Chi phí dịch vụ mua ngoài | Debit turnover to TK 6417+6427+6277 in period |
| VI.14 | Chi phí bằng tiền khác | Remaining production-cost expenditures |
| VI.15 | Tổng chi phí SXKD | Sum VI.10–VI.14 — must = BC02 MS 24+25+26-27 |

#### 6.3 Lợi nhuận từ HĐKD disaggregation:

| Mã | Chỉ tiêu | Công thức |
|---|---|---|
| VI.20 | LN gộp từ bán hàng | BC02 MS 20 |
| VI.21 | LN từ HĐTC | BC02 MS 21 |
| VI.22 | Chi phí bán hàng | BC02 MS 24 |
| VI.23 | Chi phí QLDN | BC02 MS 25 |

### Phần VII: Thông tin bổ sung cho BC03

Disclosures supporting the cash flow statement:

| Mã | Chỉ tiêu | Source |
|---|---|---|
| VII.01 | Tiền đầu kỳ | BC03 MS 20 (prior year) |
| VII.02 | Tiền cuối kỳ | BC03 MS 70 |
| VII.03 | Các giao dịch không bằng tiền | Manual entry — mua TSCĐ bằng nợ, đổi hàng... |
| VII.04 | Các khoản vay nhận được trong kỳ | Debit 111/112 ↔ Credit 341/343/344 |
| VII.05 | Các khoản trả nợ vay trong kỳ | Debit 341/343/344 ↔ Credit 111/112 |
| VII.06 | Tiền chi trả lãi vay | Debit 635 ↔ Credit 111/112 |
| VII.07 | Tiền chi nộp thuế TNDN | Debit 3334 ↔ Credit 111/112 |
| VII.08 | Tiền thu lãi tiền gửi | Debit 111/112 ↔ Credit 515 |
| VII.09 | Tiền thu cổ tức, lợi nhuận | Debit 111/112 ↔ Credit 5113/515 |

**Công thức tổng quát cho VII.04–VII.09:**
Query `ledger_entries` JOIN `transactions` WHERE transaction_date IN kỳ AND account_code IN relevant range, grouped by opponent pattern.

### Phần VIII: Thông tin bộ phận (Segment Reporting)

**Manual entry** — không thể tự động hóa hoàn toàn.

| Mã | Chỉ tiêu | Nguồn |
|---|---|---|
| VIII.01 | Bộ phận kinh doanh theo lĩnh vực | Thủ công — nhập từng bộ phận |
| VIII.02 | Doanh thu theo từng bộ phận | Thủ công hoặc từ TK 511 chi tiết theo bộ phận |
| VIII.03 | Kết quả KD theo từng bộ phận | Thủ công |
| VIII.04 | Tài sản theo từng bộ phận | Thủ công |
| VIII.05 | Bộ phận theo khu vực địa lý | Thủ công |

**Future enhancement:** Có thể partial auto-populate nếu TK được chi tiết theo bộ phận qua `detail_by` trên Account model.

### Phần IX: Những thông tin khác

**Manual entry** — narrative disclosures:

| Mã | Chỉ tiêu | Template |
|---|---|---|
| IX.01 | Nợ tiềm tàng | "Tại ngày kết thúc niên độ, công ty không có khoản nợ tiềm tàng trọng yếu." |
| IX.02 | Cam kết thuê hoạt động | "Công ty không có cam kết thuê hoạt động trọng yếu chưa phản ánh trên BC01." |
| IX.03 | Giao dịch với bên liên quan | "Không có giao dịch trọng yếu với các bên liên quan trong niên độ." |
| IX.04 | Sự kiện sau ngày kết thúc niên độ | "Không có sự kiện trọng yếu nào xảy ra sau ngày kết thúc niên độ." |
| IX.05 | Thông tin khác | Kế toán nhập tay |

---

## 3. Data Architecture

### 3.1 New Tables

#### `bc09_config` — Lưu cấu hình policy + manual sections

```sql
CREATE TABLE IF NOT EXISTS bc09_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_code VARCHAR(10) NOT NULL,       -- 'I', 'III', 'IV', 'VIII', 'IX'
    indicator_code VARCHAR(20) NOT NULL,      -- 'I.01', 'IV.01', 'IX.01'...
    indicator_name VARCHAR(255) NOT NULL,
    template_text TEXT,                        -- Default template text
    custom_text TEXT NULL,                     -- User override (NULL = use template)
    is_active BOOLEAN DEFAULT TRUE,
    updated_by VARCHAR(100),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_section_indicator (section_code, indicator_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `bc09_indicators` — Định nghĩa tất cả chỉ tiêu BC09 (Phần V, VI, VII)

```sql
CREATE TABLE IF NOT EXISTS bc09_indicators (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_code VARCHAR(10) NOT NULL,         -- 'V', 'VI', 'VII'
    indicator_code VARCHAR(20) NOT NULL,        -- 'V.01', 'VI.01', 'VII.01'
    indicator_name VARCHAR(255) NOT NULL,
    bc_statement VARCHAR(10) NULL,              -- 'BC01', 'BC02', 'BC03'
    bc_ma_so VARCHAR(10) NULL,                  -- '110', '20', '70' — cross-ref
    formula_expression VARCHAR(500) NULL,        -- '1111+1112+1113'
    account_codes VARCHAR(500) NULL,             -- '1111,1112,1113'
    formula_type ENUM('account_sum','account_tree','expression','manual','from_bc','from_getBalance') NOT NULL,
    sign_convention ENUM('positive','negative') DEFAULT 'positive',
    is_auto_calc BOOLEAN DEFAULT TRUE,
    is_required BOOLEAN DEFAULT TRUE,
    display_order INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_section_indicator (section_code, indicator_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `bc09_data` — Lưu số liệu từng kỳ

```sql
CREATE TABLE IF NOT EXISTS bc09_data (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_id INT UNSIGNED NOT NULL,
    section_code VARCHAR(10) NOT NULL,
    indicator_code VARCHAR(20) NOT NULL,
    year_start_amount DECIMAL(15,2) DEFAULT 0.00,   -- Số dư đầu năm
    year_end_amount DECIMAL(15,2) DEFAULT 0.00,     -- Số dư cuối năm
    note_text TEXT NULL,                              -- Narrative override
    is_manual BOOLEAN DEFAULT FALSE,                  -- TRUE = user edited
    is_locked BOOLEAN DEFAULT FALSE,                  -- TRUE = cannot recalc
    created_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by VARCHAR(100),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_period_indicator (period_id, section_code, indicator_code),
    FOREIGN KEY (period_id) REFERENCES accounting_periods(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.2 Key Design Decisions

**Zero new DB tables for auto-calc indicators in prototype.** The `bc09_data` table is sufficient — no need for `fs_notes_config` in §3 of the consolidated gaps because:

1. `bc09_config` handles sections I/III/IV/VIII/IX (manual narrative)
2. `bc09_indicators` handles sections V/VI/VII (auto-calc definitions)
3. `bc09_data` handles period-pivoted storage for all calculated numbers

### 3.3 Formula Expression Engine

Reuse the `safeEval()` evaluator from FsService (`evaluateExpression` at line 654) with one enhancement:

Support account balance substitution:
```php
// Expression: '1111+1112+1113'
// Step 1: Resolve each account code → balance using getTreeBalance()
// Step 2: Substitute into expression → "10000000+5000000+0"
// Step 3: Eval via safeEval() → 15000000
```

**Formula types:**

| type | Behavior | Example |
|---|---|---|
| `account_sum` | Sum getTreeBalance() for each code | `1111+1112+1113` |
| `account_tree` | getTreeBalance() on single control account | `211` (auto-includes 2111,2112,2113) |
| `expression` | Arithmetic with + - * / ( ) | `'211-214'` for net fixed assets |
| `from_bc` | Read value from BC01/BC02/BC03 snapshot | `BC01.110` = cash equivalent |
| `manual` | User-entered value | Narrative sections |

### 3.4 Contra Account Handling

Contra accounts (214 — hao mòn, 229 — dự phòng) have normal_balance = 'C' despite being assets. The formula engine handles this via sign_convention:

```php
// Indicator V.05b "TSCĐ hữu hình còn lại"
// formula_expression = '211-2141'
// getTreeBalance('211') → 500,000,000 (normal D)
// getTreeBalance('2141') → 120,000,000 (normal C, returns +120M)
// Expression: 500000000 - 120000000 = 380000000 ✅

// But for indicator "Hao mòn lũy kế" (disclosure only):
// formula_type = 'account_sum', sign_convention = 'negative'
// getTreeBalance('2141') → 120,000,000 → apply negative → -120,000,000
```

---

## 4. Auto-Calculation Engine

### 4.1 `FsNotesService::generateBc09(int $periodId): array`

**Input:** period_id  
**Process:**
1. Load period info from PeriodService (getPeriod, getPriorPeriod)
2. Load all `bc09_indicators` ordered by display_order
3. For each indicator with `is_auto_calc = true`:
   - Parse account codes
   - Compute balances via `AccountRepository::getTreeBalance()`
   - Handle sign_convention
   - Store in `bc09_data` (upsert)
4. Load all `bc09_config` sections (I, III, IV, VIII, IX) with template/custom text
5. Return complete BC09 structure

### 4.2 Balance Derivation

**Opening balances (year_start_amount):**
- If prior period data exists in `bc09_data`: use prior's year_end_amount
- Else if `fs_snapshots` for prior BC01 exists: derive from opening balances
- Else: 0 (first year)

**Closing balances (year_end_amount):**
- Current period snapshot via `FsService::generateBC01()` for cross-ref
- Account-level via `getTreeBalance()` for detail

**Flow indicators (BC02, BC03):**
- These are period flows, not balances
- Turnover = sum of ledger_entries amount for account in period
- Source: `SELECT COALESCE(SUM(amount), 0) FROM ledger_entries le JOIN transactions t ON t.id = le.transaction_id WHERE a.code = ? AND t.transaction_date BETWEEN ? AND ? AND t.status = 'posted'`

### 4.3 Cost-by-Nature Computation (VI.10–VI.15)

Chi phí theo yếu tố requires decomposition of production cost accounts (621, 622, 627, 154) by their nature. Bookwise cannot fully auto-decompose if accounts are not already detailed by nature.

**Strategy:**
1. If detail_by = 'cost_element' on 621/622/627 → use sub-accounts
2. Else → estimate using journal analysis:
   - Labor: entries with 334 as opponent
   - Depreciation: entries with 214 as opponent
   - Materials: entries with 152 as opponent
   - Services: entries with 331 as opponent and description pattern
3. Manual override always available

### 4.4 Data Generation Sequence

```
1. Get period_id, validate period exists and BC01 snapshots exist
2. Initialize bc09_data for period (DELETE + INSERT pattern for auto entries)
3. Compute Part V indicators from account balances
4. Compute Part VI indicators from period turnover + BC02 cross-ref
5. Compute Part VII indicators from cash account analysis + BC03 cross-ref
6. Load Part I/II/III/IV/VIII/IX from bc09_config templates
7. Cross-reference validate vs BC01/BC02/BC03 snapshots
8. Return complete structure
```

---

## 5. Validation & Cross-Reference Rules

### 5.1 Must-Pass Validations (REQUIRED)

| Rule | Condition | Error message |
|---|---|---|
| V.01 = BC01 MS 110 | abs(V.01 - BC01_110) ≤ 1 | "Tiền (V.01) {v01} không khớp MS 110 BC01 {bc01110}" |
| V.04 = BC01 MS 141 | abs(V.04 - BC01_141) ≤ 1 | "Hàng tồn kho (V.04) không khớp BC01 MS 141" |
| V.05b = BC01 MS 221 | abs(V.05b - BC01_221) ≤ 1 | "TSCĐ hữu hình còn lại không khớp BC01 MS 221" |
| V.18 = BC01 MS 410 | abs(V.18 - BC01_410) ≤ 1 | "Vốn CSH (V.18) không khớp BC01 MS 410" |
| V.19 = BC01 MS 420 | abs(V.19 - BC01_420) ≤ 1 | "LN chưa phân phối (V.19) không khớp BC01 MS 420" |
| VI.01+VI.02+VI.05 = BC02 MS 01 | ±1 | "Tổng doanh thu không khớp BC02 MS 01" |
| VI.15 = BC02 MS 24+25+26 | ±1 | "Tổng CP theo yếu tố không khớp chi phí BC02" |
| VII.02 = BC03 MS 70 | ±1 | "Tiền cuối kỳ (VII.02) không khớp BC03 MS 70" |
| VII.02 = V.01 (year_end) | ±1 | "Tiền cuối kỳ BC09 Phần V và VII phải khớp" |

### 5.2 Soft Validations (WARNING)

| Rule | Threshold | Warning |
|---|---|---|
| Year-over-year change > 20% | abs(new-old)/old > 0.20 | "Biến động {indicator_name} > 20%: {old} → {new}" |
| Indicator = 0 but BC01 > 0 | BC01 > 1000 AND indicator = 0 | "Chỉ tiêu {name} = 0 nhưng BC01 có số liệu" |
| Manual section unchanged from template | custom_text IS NULL | "Phần {section} chưa được cập nhật — vẫn sử dụng template mặc định" |

### 5.3 Validation Tolerance

Tolerance ±1 VND như FsService (do rounding error). Sai lệch > 1 → ERROR.

### 5.4 Cross-Reference Report Format

```json
{
  "period_id": 12,
  "status": "has_warnings",
  "errors": [],
  "warnings": [
    "Biến động V.01 (Tiền) > 20%: 150,000,000 → 210,000,000",
    "Phần IX.01 chưa được cập nhật — vẫn dùng template mặc định"
  ],
  "cross_refs": [
    { "bc09_code": "V.01", "bc09_name": "Tiền", "bc09_value": 210000000,
      "bc_statement": "BC01", "bc_ma_so": "110", "bc_value": 210000000,
      "diff": 0, "status": "ok" },
    { "bc09_code": "V.04", "bc09_name": "Hàng tồn kho", "bc09_value": 85000000,
      "bc_statement": "BC01", "bc_ma_so": "141", "bc_value": 85000000,
      "diff": 0, "status": "ok" }
  ]
}
```

---

## 6. UI/UX Flow

### 6.1 Screen: BC09 Management

**Route:** `/fs-bc09` → `viewBC09()` in FsController

**Tabs/Sections:**

| Tab | Content | Interaction |
|---|---|---|
| I. Company info | Form fields from bc09_config section I | Edit/save |
| II. Period info | Auto-generated from ConfigService | Read-only |
| III. Standards | Template text editor | Edit/save |
| IV. Policies | 22+ policy templates | Expand/confirm |
| V. BC01 notes | Auto-calc table with manual overrides | View/edit |
| VI. BC02 notes | Auto-calc table | View/edit |
| VII. BC03 notes | Auto-calc + manual entries | View/edit |
| VIII. Segment | Manual entry | Edit |
| IX. Other | Manual text areas | Edit |
| Cross-ref | Validation report | View |
| Export | PDF/Excel buttons | Download |

### 6.2 User Journey

**Setup (once per year):**
1. User selects period
2. System prompts: "Cập nhật các thông tin chung cho năm {year}?"
3. User updates section I (employees, changes)
4. User reviews section IV policies — confirms or overrides
5. User fills sections VIII and IX

**Generation:**
1. Click "Tạo BC09"
2. System auto-calculates all indicators in V/VI/VII
3. System shows results with auto/edited badges
4. User can click any auto-calc cell to override (cell turns yellow, is_manual = true)
5. User edits manual sections

**Validation:**
1. Click "Kiểm tra đối chiếu"
2. System runs all cross-reference rules
3. Results: green checkmarks for passes, red X with diff for failures
4. User must resolve all errors before export

**Export:**
1. Button "Xuất PDF" or "Xuất Excel"
2. System generates BC09 in official B09-DN format
3. Includes all 9 sections, signature blocks, KTT + Người lập

### 6.3 UI States

```
┌──────────────────────────────────────────────────────────────┐
│  BC09 — Thuyết minh Báo cáo tài chính năm {year}           │
├──────────────────────────────────────────────────────────────┤
│ [Tạo BC09] [Kiểm tra đối chiếu] [Xuất PDF] [Xuất Excel]     │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  ✅ Đối chiếu: 0 errors, 2 warnings                          │
│                                                              │
│  ┌─ Phần V: Thông tin bổ sung BC01 ──────────────────────┐  │
│  │ Mã │ Chỉ tiêu         │ Đầu năm  │ Cuối năm  │ Nguồn  │  │
│  │────┼──────────────────┼──────────┼───────────┼────────│  │
│  │V.01│ Tiền             │150,000,00│210,000,000│ Tự động│  │
│  │V.02│ Đầu tư NH        │ 20,000,00│ 15,000,000│ Tự động│  │
│  │V.03│ Phải thu         │ 80,000,00│ 95,000,000│ ✏️ Sửa │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                              │
│  ┌─ Phần VI: Thông tin bổ sung BC02 ──────────────────────┐  │
│  │ ...                                                      │  │
│  └────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────┘
```

---

## 7. Integration Contracts

### 7.1 FsService (existing)

```php
// Required methods:
$fsService->generateBC01($periodCode);     // For cross-ref V.01 ↔ 110
$fsService->generateBC02($periodCode);     // For cross-ref VI.01 ↔ BC02 MS 01
$fsService->generateBC03($periodCode);     // For cross-ref VII.02 ↔ BC03 MS 70
$fsService->getPriorPeriodValues($statement, $periodCode);  // For opening balances
$fsService->getLineItems($statement);      // To read BC01/02/03 indicator list
$fsService->safeEval($expression, $values);// Expression evaluator (reuse)
```

### 7.2 AccountRepositoryInterface

```php
// Required methods:
$accountRepo->getTreeBalance(string $code): float;   // Core — recursive CTE
$accountRepo->findByCode(string $code): ?Account;     // For account metadata
$accountRepo->findByFsMapping(string $code): array;   // For FS-mapped account groups
$accountRepo->findAll(): array;                        // For full account enumeration
```

### 7.3 ConfigService

```php
// Keys to add or retrieve:
$config->getString('entity_name');              // Tên doanh nghiệp
$config->getString('tax_code');                 // MST
$config->getString('business_reg_number');      // Số GPKD
$config->getString('address');                  // Địa chỉ
$config->getString('currency', 'VNĐ');          // Đơn vị tiền tệ
$config->getString('inventory_method');         // Phương pháp tính giá XK
$config->getString('depreciation_method');      // Phương pháp khấu hao
$config->getString('vat_method');               // PP tính thuế GTGT
$config->getString('fx_method');                // PP chuyển đổi ngoại tệ
$config->getString('bookkeeping_form');         // Hình thức ghi sổ
$config->getString('ownership_structure');      // Hình thức sở hữu vốn
$config->getString('main_business_activities'); // Ngành nghề KD chính
$config->getInt('average_employees');           // Số lao động BQ (override hàng năm)
$config->getString('depreciation_method_detail'); // PP khấu hao cụ thể
$config->getString('provision_method');         // PP trích lập dự phòng
```

### 7.4 PeriodService

```php
$periodService->getPeriod(int $id): array;           // Period metadata
$periodService->getPeriods(): array;                 // Available periods
// getPriorPeriod logic: for annual BC09, prior = year-1
// Or use fs_snapshots for 'BC01' with period_code = (year-1)
```

### 7.5 FsNotesService (NEW)

```php
class FsNotesService
{
    public function __construct(
        \PDO $pdo,
        FsService $fsService,
        AccountRepositoryInterface $accountRepo,
        ConfigService $config,
        PeriodService $periodService,
        ?AuditLoggerInterface $auditLogger = null
    );

    // Generate/refresh BC09 for a period
    public function generate(int $periodId): array;

    // Get full BC09 structure for display
    public function getBc09(int $periodId): array;

    // Update a single indicator value (manual override)
    public function updateIndicator(int $periodId, string $section, string $indicator, float $yearEnd, ?string $note = null): void;

    // Update narrative section (I, III, IV, VIII, IX)
    public function updateNarrative(string $section, string $indicator, string $text): void;

    // Get policy templates for Section IV
    public function getPolicyTemplates(): array;

    // Run cross-reference validation
    public function validate(int $periodId): array;

    // Check if BC09 exists for period
    public function exists(int $periodId): bool;
}
```

---

## 8. API Endpoints

### 8.1 RESTful Endpoints

| Method | Path | Handler | Auth | Description |
|---|---|---|---|---|
| `GET` | `/api/fs/bc09/{periodId}` | `FsNotesController::getBc09` | `report,read` | Get full BC09 for period |
| `POST` | `/api/fs/bc09/{periodId}/generate` | `FsNotesController::generate` | `report,create` | Generate/regenerate BC09 |
| `PUT` | `/api/fs/bc09/{periodId}/indicator/{indicatorCode}` | `FsNotesController::updateIndicator` | `report,update` | Update single indicator |
| `POST` | `/api/fs/bc09/{periodId}/validate` | `FsNotesController::validate` | `report,read` | Run cross-reference check |
| `GET` | `/api/fs/bc09/policies` | `FsNotesController::policies` | `report,read` | Get policy templates |
| `PUT` | `/api/fs/bc09/policies` | `FsNotesController::updatePolicies` | `report,update` | Save policy customizations |
| `PUT` | `/api/fs/bc09/narrative/{section}` | `FsNotesController::updateNarrative` | `report,update` | Save narrative section |
| `POST` | `/api/fs/bc09/{periodId}/export` | `FsNotesController::exportPdf` | `report,export` | Export BC09 to PDF |
| `GET` | `/api/fs/bc09/{periodId}/export/csv` | `FsNotesController::exportCsv` | `report,export` | Export BC09 to CSV |

### 8.2 Route Registration

```php
// In config/routes/api_financial.php:
$router->get('/api/fs/bc09/:periodId', function($periodId) use ($c) { $c['FsNotesController']->getBc09((int)$periodId); });
$router->post('/api/fs/bc09/:periodId/generate', function($periodId) use ($c) { $c['FsNotesController']->generate((int)$periodId); });
// ... remaining routes
```

---

## 9. Implementation Checklist

### Phase 1 — Foundation (Day 1)

```
[ ] 1. Migration: bc09_config table
[ ] 2. Migration: bc09_indicators table
[ ] 3. Migration: bc09_data table
[ ] 4. Seed script: bc09_indicators — ~60 indicators for sections V/VI/VII
[ ] 5. Seed script: bc09_config — section I/III/IV/IX default templates
[ ] 6. Model: Domain/Model/Bc09Config.php
[ ] 7. Model: Domain/Model/Bc09Indicator.php
[ ] 8. Model: Domain/Model/Bc09Data.php
[ ] 9. Repository Interface: Domain/Repository/Bc09RepositoryInterface.php
    Methods: findConfig, findIndicators, findData, saveData, upsertConfig, deleteDataForPeriod
[ ] 10. PDO Implementation: Infrastructure/Persistence/PDOBc09Repository.php
[ ] 11. DI registration: config/services.php
```

### Phase 2 — Auto-Calc Engine (Day 2)

```
[ ] 12. Service: Domain/Service/FsNotesService.php
[ ] 13. Method: generate(int $periodId) — auto-calc all indicators
[ ] 14. Method: getBc09(int $periodId) — return structured BC09 data
[ ] 15. Method: updateIndicator() — manual override with is_manual flag
[ ] 16. Formula resolver: account_sum, account_tree, expression, from_bc
[ ] 17. Contra account handling (214, 229 negative sign)
[ ] 18. Opening balance derivation from prior period
[ ] 19. Cost-by-nature estimation from journal entries
```

### Phase 3 — Validation & UI (Day 3)

```
[ ] 20. Method: validate(int $periodId) — cross-reference engine
[ ] 21. Controller: Interfaces/HTTP/Financial/FsNotesController.php
[ ] 22. View: public/views/fs-bc09.php (replace existing bc09.php)
[ ] 23. Route registration: 9 API endpoints
[ ] 24. Layout update: sidebar menu for BC09
[ ] 25. JS: auto-calc table with inline edit, manual override toggle
[ ] 26. JS: validation report display with expand/collapse
[ ] 27. Permission: fs_bc09.read, fs_bc09.update
```

### Phase 4 — Export & Polish (Day 4)

```
[ ] 28. PDF export: BC09 official B09-DN print layout
[ ] 29. CSV/Excel export: BC09 data as spreadsheet
[ ] 30. AuditLogger integration: log generate, update, validate
[ ] 31. Period lock: BC09 data read-only for closed/hard-closed periods
[ ] 32. Integration test: cross-ref with BC01/BC02/BC03
```

### Phase 5 — Testing (Ongoing)

```
[ ] 33. Test: bc09_indicator formula resolution (all formula types)
[ ] 34. Test: cross-reference validation (pass + fail cases)
[ ] 35. Test: manual override persistence and is_manual flag
[ ] 36. Test: opening balance derivation chain
[ ] 37. Test: period lock enforcement
[ ] 38. Test: contra account sign convention
[ ] 39. Test: full BC09 generation with known account balances
[ ] 40. Test: zero-datasets (first year, no transactions)
```

---

## 10. Effort Estimate

| Activity | Hours | Dependencies |
|---|---|---|
| DB migration + seed (3 tables, ~60 indicators) | 4h | None |
| Models + Repository + DI | 3h | Migration complete |
| Auto-calc engine (generate + formula resolve) | 6h | Repository ready |
| Cross-reference validation | 3h | FsService BC01/BC02/BC03 |
| Controller + 9 routes | 2h | Service ready |
| UI view (replace bc09.php) | 6h | Service + Controller |
| PDF export | 4h | UI ready |
| Testing (40 tests) | 4h | All above |
| **Total** | **32h (4 days)** | |

**Risk factors:**
- Cost-by-nature decomposition accuracy (may need manual override)
- Opening balance derivation if prior year data is missing
- UI complexity with 9 sections + inline editing

---

## 11. Risk Register

| ID | Risk | Severity | Mitigation |
|---|---|---|---|
| BC09-R01 | Cross-reference validation fails due to timing drift between BC01 snapshot and BC09 generation | MEDIUM | Always use fs_snapshots for cross-ref (not live balances). Regenerate BC09 immediately after BC01/02/03. |
| BC09-R02 | Manual override lost on regenerate | HIGH | is_locked flag on bc09_data. If is_locked = true, skip indicator on regenerate. Flag to user: "Chỉ tiêu {code} đã được sửa thủ công — bỏ qua khi tạo lại." |
| BC09-R03 | Prior year data missing for opening balance | LOW | Default to 0, flag in validation report. Use FsService::getPriorPeriodValues() with graceful null. |
| BC09-R04 | Large enterprise with 100+ accounts → indicator list too long | LOW | Configurable — admin can activate/deactivate indicators in bc09_indicators.is_active. |
| BC09-R05 | PDF output does not match official TT 99 B09-DN format | MEDIUM | Design print layout to match mẫu. Use fixed-width font, proper column alignment. Test with actual data. |

---

## 12. Future Enhancements (Out of Scope for Initial Spec)

- **Automatic segment reporting (Part VIII):** Nếu account_tree có `detail_by = 'segment'`, có thể partial auto-fill
- **AI-assisted narrative generation:** For "giải trình biến động >20%"
- **Comparative multi-year view:** 3-year columnar BC09
- **XBRL tagging:** Export BC09 as XBRL for GDT submission
- **Consolidated BC09:** For group reporting with elimination entries
- **Auditor review mode:** Track changes, comments, sign-off workflow within BC09
