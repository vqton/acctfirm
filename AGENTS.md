# AGENTS.md — Enterprise Engineering Governance Document

> **Phiên bản:** 2.9  
> **Phạm vi:** Toàn bộ hệ thống kế toán doanh nghiệp Việt Nam  
> **Đối tượng:** Developers, AI agents, Architects, DevOps, Auditors, Onboarding engineers  
> **Nguyên tắc:** Mọi thay đổi phải đảm bảo **tính đúng đắn nghiệp vụ kế toán**, **không phá vỡ backward compatibility**, **có kiểm chứng bằng test**

---

## Mục lục

1. [Tổng quan dự án & Business Context](#1-t%E1%BB%95ng-quan-d%E1%BB%B1-%C3%A1n--business-context)
2. [Enterprise Governance Tiers](#2-enterprise-governance-tiers)
3. [Kiến trúc hệ thống](#3-ki%E1%BA%BFn-tr%C3%BAc-h%E1%BB%87-th%E1%BB%91ng)
4. [Công nghệ & Ràng buộc](#4-c%C3%B4ng-ngh%E1%BB%87--r%C3%A0ng-bu%E1%BB%99c)
5. [Code Standards & Conventions](#5-code-standards--conventions)
6. [Vietnamese Comment Standards](#6-vietnamese-comment-standards)
7. [Business Rules Preservation Framework](#7-business-rules-preservation-framework)
8. [Database & Migration Standards](#8-database--migration-standards)
9. [API & Integration Standards](#9-api--integration-standards)
10. [Security & Audit Standards](#10-security--audit-standards)
11. [Testing & Quality Gates](#11-testing--quality-gates)
12. [AI Agent Operational Policy](#12-ai-agent-operational-policy)
13. [CI/CD & DevOps Standards](#13-cicd--devops-standards)
14. [Operational Risk Register](#14-operational-risk-register)
15. [Quick Commands](#15-quick-commands)
16. [CodeGraph Workflow](#16-codegraph-workflow)
17. [Directory Structure](#17-directory-structure)
18. [New Entity Checklist](#18-new-entity-checklist)
19. [Skill Selection Matrix](#19-skill-selection-matrix)
20. [Changelog & ADR](#20-changelog--adr)

---

## 1. Tổng quan dự án & Business Context

Hệ thống kế toán doanh nghiệp Việt Nam — web application, PHP backend, MySQL/MariaDB.

### 1.1 Business Domain

- **Chế độ kế toán:** Thông tư 99/2025/TT-BTC (Circular 99) — Hệ thống tài khoản kế toán doanh nghiệp Việt Nam
- **Báo cáo tài chính:** BC 01 (Cân đối kế toán), BC 02 (Kết quả kinh doanh), BC 03 (Lưu chuyển tiền tệ)
- **Nghiệp vụ:** Tiền mặt, tiền gửi ngân hàng, hàng tồn kho (FIFO/Weighted Average), công nợ phải thu/phải trả, tài sản cố định, thuế GTGT, lương, tạm ứng, kết chuyển cuối kỳ
- **Thuế:** GTGT khấu trừ (133/3331), TNCN, TNDN

### 1.2 Regulatory Compliance

| Yêu cầu | Bắt buộc | Hậu quả nếu sai |
|---|---|---|
| Hệ thống tài khoản đúng Circular 99 | ✅ | Báo cáo tài chính sai → phạt thuế |
| Dr = Cr cho mọi bút toán | ✅ | Bảng cân đối không khớp → audit fail |
| Không post vào tài khoản tổng hợp (control account) | ✅ | Sai số dư chi tiết → sai BC |
| Số chứng từ tự động tăng | ✅ | Mất audit trail → rủi ro pháp lý |
| Kỳ kế toán đóng là read-only | ✅ | Sai số liệu kỳ trước → restate |
| Lưu audit trail đầy đủ | ✅ | Yêu cầu từ Kiểm toán độc lập |
| Từng nghiệp vụ bằng tiếng Việt | ✅ | Yêu cầu của Kế toán trưởng |

### 1.3 Key Architectural Decisions

| Decision | Rationale | ADR |
|---|---|---|
| No framework, pure PHP | Kiểm soát hoàn toàn, không dependency hell | `docs/decisions/adr-001.md` |
| PDO prepared statements | Chống SQL injection, performance | `docs/decisions/adr-002.md` |
| Domain-driven structure | Tách biệt nghiệp vụ khỏi infrastructure | `docs/decisions/adr-003.md` |
| JSON API + jQuery AJAX | Đơn giản, đủ cho ERP nội bộ | `docs/decisions/adr-004.md` |
| ActionJournal (JSON Lines) | Audit trail bất biến, dễ export | `docs/decisions/adr-005.md` |
| Vietnamese message audit | Toàn bộ thông báo người dùng sang tiếng Việt | `docs/decisions/adr-006.md` |
| CompositeDB declined | Pre-1.0 library, adds Composer (trái §4.2), no ROI | `docs/decisions/adr-007.md` |
| Priority-first tax implementation | P0 legal MUST before P2 nice-to-have | `docs/decisions/adr-009.md` |
| vat_groups data-driven | No hardcoded VAT rates, OCP via data | `docs/decisions/adr-010.md` |
| ConfigService business rules | All business rules in `business_config` table, change via UPDATE, not deploy | `docs/decisions/adr-011.md` |

---

## 2. Enterprise Governance Tiers

Mọi quy tắc trong document này được phân loại theo mức độ bắt buộc:

### Tier 1 — REQUIRED (BẮT BUỘC)

Vi phạm = rejected code review, không được merge, rollback nếu phát hiện trong production.

- **REQUIRED:** Mọi POST/PUT/DELETE phải kiểm tra CSRF token
- **REQUIRED:** Mọi SQL phải dùng prepared statements (PDO `?` placeholder)
- **REQUIRED:** Mọi bút toán phải đảm bảo tổng Dr = tổng Cr
- **REQUIRED:** Mọi migration phải idempotent (IF NOT EXISTS, ON DUPLICATE KEY, INSERT IGNORE)
- **REQUIRED:** Mọi thay đổi DB phải có migration file, không sửa tay
- **REQUIRED:** Mọi transaction multi-step phải wrap trong beginTransaction/commit/rollback
- **REQUIRED:** Không hardcode account code trong controller — phải dùng AccountRepository
- **REQUIRED:** Mọi API endpoint trả JSON phải có header `Content-Type: application/json`
- **REQUIRED:** Không commit secret, password, token vào git
- **REQUIRED:** Mọi model mới phải có Repository Interface + PDO Implementation
- **REQUIRED:** Mọi nghiệp vụ mới phải có test — happy path + ít nhất 1 failure case
- **REQUIRED:** Không dùng `eval()`, `extract()`, `create_function()`, `${}` interpolation

### Tier 2 — RECOMMENDED (KHUYẾN NGHỊ)

Không bắt buộc nhưng phải có lý do chính đáng nếu không tuân thủ.

- **RECOMMENDED:** Interface suffix `Interface`, PDO impl prefix `PDO`
- **RECOMMENDED:** 4 spaces indent, không tabs
- **RECOMMENDED:** Mọi class có constructor injection, không dùng static/global
- **RECOMMENDED:** Mọi API error trả về cấu trúc `{"error": "message"}`
- **RECOMMENDED:** Controller chỉ gọi service, không chứa business logic
- **RECOMMENDED:** Mọi model có `toArray()` method
- **RECOMMENDED:** Sử dụng `AuditLogger::log()` cho mọi thay đổi dữ liệu quan trọng
- **RECOMMENDED:** Unauthorized routes trả về 401, forbidden trả về 403
- **RECOMMENDED:** Sử dụng VoucherService thay vì tự sinh reference thủ công

### Tier 3 — FORBIDDEN (CẤM TUYỆT ĐỐI)

Không có ngoại lệ. Vi phạm = immediate rollback + escalate lên Engineering Lead.

- **FORBIDDEN:** String interpolation trong SQL — `"WHERE id = $id"` thay vì `WHERE id = ?`
- **FORBIDDEN:** Gọi `session_start()` sau khi đã gọi `session_write_close()`
- **FORBIDDEN:** Xóa dữ liệu gốc — chỉ soft delete hoặc status flag
- **FORBIDDEN:** Sửa migration đã chạy — viết migration mới
- **FORBIDDEN:** Hardcode user credentials, API keys, database passwords trong code
- **FORBIDDEN:** Sử dụng `require`/`include` với đường dẫn từ user input
- **FORBIDDEN:** Gọi `exit()` hoặc `die()` trong controller — phải throw exception
- **FORBIDDEN:** Sửa balance account trực tiếp — phải qua JournalService
- **FORBIDDEN:** Hardcode mã định danh (ID) — phải dùng generated ID (uniqid/UUID)
- **FORBIDDEN:** Xóa audit trail hoặc action journal

---

## 3. Kiến trúc hệ thống

### 3.1 Request Flow

```
Browser (jQuery AJAX)
  → public/index.php (entry + auth guard)
    → autoloader (PSR-4-like, Accounting\ → src/Accounting/)
      → config/services.php (DI container — $GLOBALS['container'])
        → config/routes.php ($router->get/post/put/delete)
          → Router::dispatch()
            → Controller (Interfaces/HTTP/)
              → Service (Domain/Service/) — business logic
                → Repository Interface → PDO Repository
                  → MySQL
              → JsonResponse::ok/error()
```

### 3.2 Service Dependencies

```
JournalService — CORE: mọi bút toán đi qua service này
├── PostingRuleService — validation Dr-Cr pairs
├── VoucherService — sinh số chứng từ tự động
└── AuditLoggerInterface — ghi audit trail

CashService → JournalService (tất cả thu/chi)
PettyCashService → CashService → JournalService
ApService → JournalService + Tk331 sub-ledger
ArService → JournalService + Tk131 sub-ledger
InventoryService → JournalService (xuất/nhập kho → bút toán)
FixedAssetService → JournalService (khấu hao, mua sắm)
FsService → AccountRepository (đọc số dư → BC01/02/03)
PeriodService → JournalService + InventoryService (kết chuyển cuối kỳ)
GlService → AccountRepository + TransactionRepository (sổ cái)
BankReconciliationService → JournalService (điều chỉnh ngân hàng)
ContractService → JournalService (giải ngân, tạm ứng hợp đồng)
ProjectAccountingService → JournalService (phân bổ chi phí dự án)
ManufacturingService → JournalService (xuất kho NVL, nhập kho thành phẩm)
BudgetService → AccountRepository + TransactionRepository (so sánh dự toán vs thực tế)
```

### 3.3 Module Boundaries

| Module | Service | Account Codes | Key Tables |
|---|---|---|---|---|
| Cash & Bank | CashService | 111, 112 | transactions, ledger_entries |
| Accounts Payable | ApService | 331 | ap_transactions, ap_aging |
| Accounts Receivable | ArService | 131 | ar_transactions, ar_aging |
| Inventory | InventoryService | 152, 153, 155, 156, 157, 632 | items, inventory_layers, warehouse_stock |
| Sales Orders | SalesOrderService | 511, 131 | sales_orders, sales_order_items |
| Fixed Assets | FixedAssetService | 211, 213, 214, 241, 242 | fixed_assets, depreciation_schedules |
| Payroll | PayrollService | 334, 3383, 3384 | payroll_entries, payroll_periods |
| Tax | (via journals) | 133, 3331, 33311 | tax_rates, vat_groups |
| Financial Statements | FsService | All | accounting_period_snapshots |
| Contract Management | ContractService | 331, 131 | contracts, contract_payment_schedules, contract_amendments |
| Project Accounting | ProjectAccountingService | 154, 632, 511 | projects, project_progress_billing, project_budgets |
| Manufacturing | ManufacturingService | 154, 155, 621, 622, 627, 632 | bom, bom_lines, production_orders, production_materials, production_labor, production_overhead |
| Budget & Planning | BudgetService | All | budget_scenarios, budget_plans |
| Custom Reports | ReportBuilderService | N/A | report_definitions |

**Critical rule:** Modules chỉ giao tiếp qua JournalService. Không module nào ghi trực tiếp vào transaction/account balance.

### 3.4 Posting Controls (GL Posting Engine)

Phase 1 đã implement — validation matrix:

```
postEntry/createDraft → validatePostingRules → posting_rules table
  → block: throw InvalidArgumentException (ví dụ: Dr 631/Cr 111 không hợp lệ)
  → warn: cho phép nhưng log warning (severity = warn)
  → pass: cho phép (no matching rule)
```

Control accounts (có sub-accounts: 111, 112, 131, 331, 333, 411...) bị block trừ khi `$allowControl = true`.

---

## 4. Công nghệ & Ràng buộc

### 4.1 Stack

| Layer | Technology | Ghi chú |
|---|---|---|
| Language | PHP 8.4 | Strict types RECOMMENDED |
| Database | MySQL 8+ / MariaDB 10.6+ | InnoDB, utf8mb4 |
| ORM | None | PDO trực tiếp + prepared statements |
| Frontend | Bootstrap 5 + jQuery 3.x | No React/Vue — legacy decision |
| API | JSON over HTTP | AJAX từ jQuery |
| Auth | PHP Sessions + CSRF | SessionMiddleware |
| DI | Manual — array in $GLOBALS | Không container library |
| Logging | Custom Logger + LoggingPDO + ActionJournal | Django-style |

### 4.2 Constraints

- **No Composer:** Custom PSR-4 autoloader. `Accounting\` namespace maps to `src/Accounting/`.
- **No framework:** Pure PHP. Router tự viết, DI tự viết.
- **No ORM:** Raw PDO + prepared statements.
- **No migration library:** Script `database/migrate.php` tự phát hiện file mới.
- **No test framework:** `assertEq`/`assertTrue` helpers trong `tests/bootstrap.php`.

### 4.3 Environment Requirements

- **PHP 8.4+** với extensions: `pdo`, `pdo_mysql`, `mbstring`, `json`, `session`, `fileinfo`
- **MySQL 8.0+ / MariaDB 10.6+** với: InnoDB engine, `utf8mb4` charset, `binary logging` có thể tắt ở dev
- **Development server:** PHP built-in (`php -S`) — không cần Apache/Nginx cho dev
- **OS:** Linux (production), bất kỳ (dev) — không có platform-specific dependency
- **Disk:** Thư mục `logs/` và `session save path` phải writable

---

## 5. Code Standards & Conventions

### 5.1 Naming

| Pattern | Convention | Ví dụ |
|---|---|---|
| Interface | Suffix `Interface` | `AccountRepositoryInterface` |
| PDO Implementation | Prefix `PDO` | `PDOAccountRepository` |
| Service | Suffix `Service` | `CashService`, `JournalService` |
| Controller | Suffix `Controller` | `CashController` |
| Model | Plain class | `Transaction`, `LedgerEntry` |
| Namespace | `Accounting\{Domain\|Infrastructure\|Interfaces}\{...}` | |
| Method | camelCase | `postEntry()`, `createDraft()` |
| Property | camelCase | `$ledgerEntries`, `$isDebit` |

### 5.2 Formatting

- **REQUIRED:** 4 spaces indent, no tabs
- **REQUIRED:** PHP opening tag `<?php` — no closing tag `?>`
- **RECOMMENDED:** Class braces on new line, method braces on new line
- **RECOMMENDED:** Line length max 120 characters
- **RECOMMENDED:** Strict types declaration `declare(strict_types=1);`

### 5.3 SQL

- **REQUIRED:** PDO prepared statements, `?` positional placeholders
- **REQUIRED:** No string interpolation, no named placeholders
- **REQUIRED:** Fetch mode `PDO::FETCH_ASSOC` mặc định
- **RECOMMENDED:** Error mode `PDO::ERRMODE_EXCEPTION` mặc định
- **RECOMMENDED:** Emulate prepares OFF (`PDO::ATTR_EMULATE_PREPARES => false`) để real prepared statement

### 5.4 Controllers & API

```php
// REQUIRED pattern:
$router->get('/api/cash/accounts', function () {
    Auth::requirePermission('cash', 'read');
    JsonResponse::ok((new CashController(...))->getAccounts());
});

// REQUIRED: Content-Type header — JsonResponse tự set
// REQUIRED: HTTP status code là tham số thứ 2
JsonResponse::ok($data, 200);
JsonResponse::error($message, 422);

// FORBIDDEN: return trong controller
// FORBIDDEN: exit/die trong controller
// FORBIDDEN: echo/json_encode trực tiếp — phải qua JsonResponse
```

### 5.5 Views

```php
<?php ob_start(); ?>
<div class="container">
  <!-- Nội dung view -->
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
```

### 5.6 Dependency Injection

```php
// RECOMMENDED pattern — constructor injection
// FORBIDDEN: static, singleton, service locator pattern trừ DI container
$GLOBALS['container'][JournalService::class] = fn($c) =>
    new JournalService(
        $c[AccountRepositoryInterface::class],
        $c[TransactionRepositoryInterface::class],
        $c['pdo'],
        $c[AuditLoggerInterface::class],
        $c[PostingRuleService::class],
        $c[VoucherService::class]
    );
```

### 5.7 Audit Logging

```php
// REQUIRED: mọi thay đổi dữ liệu quan trọng phải log
AuditLogger::log(
    action: 'journal.post',            // {module}.{action}
    resource: 'transaction',           // tên entity
    resourceId: $txn->getId(),
    oldValue: null,                    // null nếu create
    newValue: ['reference' => $ref],   // luôn include identifier
    actor: $createdBy
);

// ActionJournal — ghi mọi request
ActionJournal::record($method, $uri, $status, $reqBody, $resBody, $ms, $userId);
ActionJournal::setAction('auth.login');  // Ghi đè action name auto-generated
```

---

## 6. Vietnamese Comment Standards

### 6.1 Nguyên tắc Chung

**REQUIRED:** Mọi comment trong code phải viết bằng tiếng Việt.

Mục đích:
- Kế toán trưởng, kiểm toán viên người Việt đọc hiểu được
- Giảm hiểu nhầm về nghiệp vụ kế toán Việt Nam
- Kiểm soát viên nội bộ review được business logic
- Training nhân viên mới bằng tài liệu có sẵn trong code

### 6.2 Comment Mô tả Nghiệp vụ (Business Logic Comments)

**Comment phải giải thích WHY, không phải WHAT.**

```php
// TỐT — giải thích nghiệp vụ:
// Nghiệp vụ: Xuất kho bán hàng
// - Nợ 632 (Giá vốn hàng bán)
// - Có 156 (Hàng hóa)
//
// Hệ thống phải xác định đơn giá xuất kho theo phương pháp bình quân gia quyền
// tại thời điểm ghi nhận. Đơn giá = (Giá trị tồn đầu kỳ + Nhập trong kỳ) / (Số lượng tồn đầu kỳ + Nhập trong kỳ)
//
// Ảnh hưởng:
// - BC02 chỉ tiêu 24 (Giá vốn hàng bán) thay đổi
// - Thuế TNDN bị ảnh hưởng nếu tính sai giá vốn
// - Audit trail bắt buộc phải trace được đơn giá tại thời điểm xuất

// XẤU — chỉ lặp lại code:
// Trừ số lượng tồn kho
$stock -= $qty;
```

### 6.3 Comment Mô tả Rủi ro (Risk Comments)

```php
// RỦI RO: Nếu transfer này thất bại sau khi đã ghi nhận tồn kho tại kho đích,
// hệ thống sẽ không khôi phục được tồn kho tại kho nguồn.
//
// Xử lý: Transaction wrap + rollback nếu bất kỳ bước nào lỗi.
// Nếu rollback thất bại → tạo manual adjustment record để kế toán xử lý thủ công.
```

### 6.4 Comment Mô tả Tích hợp (Integration Comments)

```php
// TÍCH HỢP: API này được gọi từ module Bán hàng (SalesController::checkout)
// Contract:
//   Input: { items: [...], paymentMethod: 'cash'|'transfer'|'card' }
//   Output: { transactionId, voucherNo, totalAmount }
// Retry: Idempotent — nếu cùng requestId thì trả về kết quả cũ
// Timeout: 30s, nếu quá thời gian coi như thất bại
```

### 6.5 Comment Mô tả Thuế (Tax Comments)

```php
// THUẾ: Nghiệp vụ mua hàng có VAT đầu vào
// - Nợ 156: giá chưa thuế
// - Nợ 1331: thuế GTGT đầu vào được khấu trừ (10%)
// - Có 331: tổng giá thanh toán
//
// Lưu ý: Chỉ được khấu trừ thuế nếu có hóa đơn đỏ hợp lệ (TT 78/2021/TT-BTC)
// Nếu chưa có hóa đơn → hạch toán tạm thời vào 331, không ghi nhận 1331
```

### 6.6 Comment Mô tả Kỳ Kế toán (Period Comments)

```php
// KỲ KẾ TOÁN: Bút toán này chỉ được post vào kỳ hiện tại (đang mở)
// Nếu ngày chứng từ thuộc kỳ đã đóng → từ chối với lỗi "Kỳ kế toán đã đóng"
// Exception: Bút toán điều chỉnh hồi tố (prior period adjustment) — cần kế toán trưởng duyệt
```

### 6.7 FORBIDDEN Comment Patterns

```php
// FORBIDDEN: Comment lặp lại code
$total += $amount; // Cộng dồn tổng tiền  ← XẤU, code đã thể hiện điều này

// FORBIDDEN: TODO để lâu ngày không xử lý
// TODO: xử lý trường hợp ngoại lệ  ← PHẢI XỬ LÝ NGAY hoặc tạo ticket

// FORBIDDEN: Comment-out code
// $oldCalculation = $a + $b;  ← XÓA ĐI, git đã lưu history

// FORBIDDEN: Comment tiếng Anh mô tả nghiệp vụ Việt Nam
// This function processes inventory export  ← Dùng tiếng Việt
```

---

## 7. Business Rules Preservation Framework

### 7.1 Account Mapping (Circular 99)

| TK | Tên | Loại | Control | Ghi chú |
|---|---|---|---|---|
| 111 | Tiền mặt | Asset | ✅ Có TK con 1111, 1112, 1113 | Chỉ post vào TK con |
| 112 | Tiền gửi NH | Asset | ✅ | Chỉ post vào TK con |
| 131 | Phải thu KH | Asset | ✅ | |
| 133 | Thuế GTGT đc khấu trừ | Asset | ✅ | 1331 (hàng hóa), 1332 (TSCĐ) |
| 152 | Nguyên liệu, vật liệu | Asset | ❌ | |
| 153 | Công cụ, dụng cụ | Asset | ❌ | |
| 154 | CPSXKD dở dang | Asset | ❌ | |
| 155 | Thành phẩm | Asset | ❌ | |
| 156 | Hàng hóa | Asset | ❌ | |
| 211 | TSCĐ hữu hình | Asset | ✅ | |
| 214 | Hao mòn TSCĐ | Asset (contra) | ✅ | |
| 241 | XDCB dở dang | Asset | ❌ | |
| 242 | Chi phí trả trước | Asset | ❌ | |
| 331 | Phải trả NCC | Liability | ✅ | |
| 333 | Thuế và các khoản nộp NN | Liability | ✅ | |
| 334 | Phải trả NLĐ | Liability | ❌ | |
| 335 | Chi phí phải trả | Liability | ❌ | |
| 338 | Phải trả khác | Liability | ✅ | |
| 411 | Vốn đầu tư CSH | Equity | ✅ | |
| 421 | LN chưa phân phối | Equity | ❌ | |
| 511 | Doanh thu bán hàng | Revenue | ❌ | |
| 515 | Doanh thu HĐTC | Revenue | ❌ | |
| 621 | Chi phí NVL trực tiếp | Expense | ❌ | |
| 622 | Chi phí nhân công trực tiếp | Expense | ❌ | |
| 627 | Chi phí SXC | Expense | ❌ | |
| 632 | Giá vốn hàng bán | Expense | ❌ | |
| 635 | Chi phí tài chính | Expense | ❌ | |
| 641 | Chi phí bán hàng | Expense | ❌ | |
| 642 | Chi phí QLDN | Expense | ❌ | |
| 911 | Xác định KQKD | Revenue/Expense | ❌ | Temporary clearing |

### 7.2 Posting Rules (GL Engine Phase 1)

**75 rules seeded** trong `posting_rules` table. Mọi Dr-Cr pair được kiểm tra:

- **block:** Pair không hợp lệ cho module đó (ví dụ: Dr 631/Cr 111 không có trong thực tế)
- **warn:** Pair hiếm gặp hoặc cần xác nhận
- **pass:** Không có rule → cho phép (new business scenarios)

Module-scoped rules (ví dụ rule chỉ áp dụng cho module `purchase`).

### 7.3 Control Account Protection

**REQUIRED:** Không post trực tiếp vào control account (TK tổng hợp).

```php
// Đúng — post vào TK con:
$line = ['account_code' => '1111', 'amount' => 100000, 'is_debit' => true];
// Sai — post vào TK tổng hợp:
$line = ['account_code' => '111', 'amount' => 100000, 'is_debit' => true];
// => InvalidArgumentException: "Tài khoản 111 (Tiền mặt) là tài khoản tổng hợp. Vui lòng hạch toán vào tài khoản chi tiết."
```

Override: Post vào control account nếu `$allowControl = true` (chỉ Kế toán trưởng).

### 7.4 Debit = Credit Invariant

**REQUIRED:** Mọi bút toán phải thỏa mãn tổng Dr = tổng Cr.

Tolerance: ±10 VND (làm tròn). Sai lệch > 10 → throw `InvalidArgumentException`.

### 7.5 Period Locking

- **REQUIRED:** Kỳ đã đóng = read-only. Không post, không sửa, không xóa.
- **REQUIRED:** `PeriodService::isPeriodOpen()` kiểm tra trước mọi post.
- **REQUIRED:** Sử dụng transaction date (không phải system date) để kiểm tra kỳ.
- **Period Engine:** Chỉ Kế toán trưởng mới có quyền đóng/mở kỳ.

### 7.6 Voucher Numbering

- **REQUIRED:** Số chứng từ tự động tăng theo năm. Format: `{PREFIX}{YYYY}-{000000}`.
- **REQUIRED:** Sử dụng `SELECT ... FOR UPDATE` để đảm bảo uniqueness dưới concurrent access.
- **Prefix convention:** `PC` (Phiếu chi), `PT` (Phiếu thu), `JV` (Journal Voucher), `PNK` (Phiếu nhập kho), `PXK` (Phiếu xuất kho).

---

## 8. Database & Migration Standards

### 8.1 Migration Rules

- **REQUIRED:** Mọi thay đổi schema phải có migration file trong `database/migrations/`
- **REQUIRED:** Migration file trả về `fn(PDO $pdo)` — closure nhận PDO connection
- **REQUIRED:** Migration phải idempotent — `CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`, `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` (MySQL 8+)
- **FORBIDDEN:** Sửa migration đã chạy. Viết migration mới.
- **REQUIRED:** Migration tracking qua table `_migrations` — tự động bởi `database/migrate.php`
- **FORBIDDEN:** Sửa tay database — mọi thay đổi qua migration
- **RECOMMENDED:** Migration file đặt tên `NNN_description.php` (3-digit số thứ tự)

```php
// Example migration:
<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS example_table (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};
```

### 8.2 Schema Standards

- **REQUIRED:** `ENGINE=InnoDB` (transaction support)
- **REQUIRED:** `CHARSET=utf8mb4` (full Unicode, bao gồm tiếng Việt)
- **RECOMMENDED:** `UNSIGNED` cho integer primary keys
- **RECOMMENDED:** `DECIMAL(15,2)` cho tiền tệ (đủ cho số đến 99 tỷ)
- **RECOMMENDED:** `DECIMAL(15,4)` cho tỷ giá
- **RECOMMENDED:** `VARCHAR(10)` cho currency code
- **RECOMMENDED:** Timestamp fields: `created_at`, `updated_at`
- **FORBIDDEN:** `FLOAT` cho tiền tệ (precision issue)
- **FORBIDDEN:** `AUTO_INCREMENT` không có `UNSIGNED`

### 8.3 Naming Conventions

| Object | Convention | Example |
|---|---|---|
| Table | snake_case, plural | `ledger_entries`, `accounting_periods` |
| Column | snake_case | `account_code`, `is_debit` |
| Primary Key | `id` | |
| Foreign Key | `{table}_id` | `transaction_id`, `account_id` |
| Join Table | `{table1}_{table2}` | `role_permissions` |
| Index | `idx_{column}` | `idx_transaction_date` |
| Unique | `uq_{column1}_{column2}` | `uq_debit_credit_module` |

---

## 9. API & Integration Standards

### 9.1 Response Format

```json
// Success:
{ "data": { "id": "jrn_abc", "status": "posted" } }

// Error:
{ "error": "Không tìm thấy tài khoản: 999" }

// Validation error:
{ "error": "Số phát sinh Nợ (100000) không khớp với số phát sinh Có (90000)" }
```

### 9.2 HTTP Status Codes

| Code | Use |
|---|---|
| 200 | Success |
| 201 | Created |
| 400 | Bad request / validation error |
| 401 | Unauthenticated |
| 403 | Forbidden (no permission) |
| 404 | Not found |
| 409 | Conflict (duplicate, already posted) |
| 422 | Unprocessable entity (Dr != Cr, closed period) |
| 500 | Server error |

### 9.3 Authentication

```php
// REQUIRED: API routes:
SessionMiddleware::close();  // session_write_close — giải phóng lock cho AJAX concurrent

// RECOMMENDED: Non-API routes:
// Không cần close session vì PHP tự close sau response
```

### 9.4 CSRF Protection

```php
// REQUIRED: Mọi POST/PUT/DELETE từ browser phải có CSRF token
// Trong layout: <meta name="csrf-token" content="<?= Auth::csrfToken() ?>">
// Trong AJAX: headers: { 'X-CSRF-Token': token }
// Trong controller: Auth::checkCsrf();
```

---

## 10. Security & Audit Standards

### 10.1 Authentication & Session

- **REQUIRED:** `session_regenerate_id(true)` sau khi login (chống session fixation)
- **REQUIRED:** `session_set_cookie_params(httpOnly, SameSite=Lax)` — chống XSS, CSRF
- **REQUIRED:** Inactivity timeout — 8 giờ, sau đó destroy session
- **REQUIRED:** Logout phải destroy session (`session_destroy() + unlink session file`)
- **REQUIRED:** Logout bằng form POST, không dùng AJAX (tránh cached page)
- **FORBIDDEN:** `session_start()` sau `session_write_close()`

### 10.2 Authorization (RBAC)

```php
// REQUIRED: Kiểm tra permission trên mọi API endpoint
Auth::requirePermission('cash', 'read');   // module = 'cash', action = 'read'
Auth::requirePermission('journal', 'post'); // module = 'journal', action = 'post'

// Available modules: cash, journal, inventory, ap, ar, fs, gl, admin, report
// Available actions: read, create, update, delete, post, approve, export, close
```

### 10.3 Audit Trail

- **REQUIRED:** `AuditLogger::log()` cho mọi thay đổi quan trọng
- **REQUIRED:** ActionJournal ghi mọi HTTP request + response
- **REQUIRED:** Audit log bất biến — không được sửa/xóa
- **REQUIRED:** ActionJournal file format: JSON Lines (`.jsonl`) — dễ export, dễ parse
- **REQUIRED:** Audit log phải bao gồm: timestamp, actor, action, resource, old value, new value, IP

### 10.4 Input Validation

- **REQUIRED:** Validate account code tồn tại trước khi post (qua AccountRepository)
- **REQUIRED:** Validate amount > 0
- **REQUIRED:** Sanitize output — `htmlspecialchars()` (e helper function)
- **REQUIRED:** `realpath()` cho static file serving — chống path traversal

---

## 11. Testing & Quality Gates

### 11.1 Test Architecture

- **No test framework** — lightweight helpers
- **Bootstrap:** `tests/bootstrap.php` cung cấp autoloader + assert helpers
- **Pattern:** Mỗi file test = 1 module, chạy bằng `php tests/ModuleTest.php`

```php
<?php
require __DIR__ . '/bootstrap.php';

// Tests...
assertEq($result['balance'], 1000000, 'Balance = 1M');
assertTrue($txn->getStatus() === 'posted', 'Transaction posted');

results(); // In kết quả: "=== Results: 12 tests, 0 failed ==="
```

### 11.2 Coverage Requirements

- **REQUIRED:** Mọi service method mới phải có test
- **REQUIRED:** Happy path + ít nhất 1 failure case
- **REQUIRED:** Test nghiệp vụ kế toán: Dr = Cr, balance after transaction
- **REQUIRED:** Test control account protection (block khi post vào control account)
- **REQUIRED:** Test posting rules validation (block/warn/pass)
- **REQUIRED:** Test period locking (từ chối post vào kỳ đã đóng)
- **RECOMMENDED:** Test trial balance (tổng Dr = tổng Cr toàn hệ thống)
- **RECOMMENDED:** Test audit trail được ghi đầy đủ

### 11.3 Quality Gates

```
Pre-commit checklist:
[ ] php tests/ModuleTest.php — tất cả pass
[ ] for f in tests/*.php; do php "$f"; done — 0 failures
[ ] Code review: có tuân thủ AGENTS.md?
[ ] Audit trail: có log đầy đủ?
[ ] Migration: có idempotent?
[ ] Backward compatible: có breaking change?
```

### 11.4 Trial Balance Pattern

```php
// Test mẫu — kiểm tra tổng Dr = Cr toàn hệ thống:
$stmt = $pdo->query("
    SELECT SUM(CASE WHEN is_debit = 1 THEN amount ELSE 0 END) AS total_dr,
           SUM(CASE WHEN is_debit = 0 THEN amount ELSE 0 END) AS total_cr
    FROM ledger_entries
");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
assertEq($row['total_dr'], $row['total_cr'], 'Trial balance: Dr = Cr');
```

### 11.5 Static Analysis & CI Gates

**RECOMMENDED:** Add PHPStan (level 6+) for static analysis before production launch.

```sh
# Install (one-time):
composer require --dev phpstan/phpstan

# Run:
vendor/bin/phpstan analyse --level 6 src/

# Generate baseline (first run, suppress existing errors):
vendor/bin/phpstan analyse --level 6 --generate-baseline src/
```

Pre-commit hook (bash, `.git/hooks/pre-commit`):
```sh
#!/bin/sh
for f in $(git diff --cached --name-only --diff-filter=AM '*.php'); do
    php -l "$f" > /dev/null || exit 1
done
```

CI pipeline (GitHub Actions — RECOMMENDED):
```yaml
# .github/workflows/ci.yml
steps:
  - run: php -l src/
  - run: for f in tests/*.php; do php "$f"; done
```

---

## 12. AI Agent Operational Policy

### 12.1 Agent Behavior Rules

1. **REQUIRED:** Never modify more than what the task specifies. Surgical changes only.
2. **REQUIRED:** Never assume business rules. If unsure about accounting treatment → stop and ask.
3. **REQUIRED:** Run full test suite after every change. Mark task complete only when 0 failures.
4. **REQUIRED:** Write in Vietnamese for business logic comments. Write in English for code (identifiers, strings).
5. **REQUIRED:** Follow the [CodeGraph Workflow (§16)](#16-codegraph-workflow) for every task — context → impact → implement → sync → test. Never grep where CodeGraph suffices.
6. **REQUIRED:** Never introduce new dependencies (Composer, libraries) without explicit approval.
7. **REQUIRED:** Never refactor code that isn't part of the task scope.
8. **REQUIRED:** Never modify migration files that have already been executed.
9. **RECOMMENDED:** Load skill `karpathy-guidelines` + `caveman` by default.
10. **RECOMMENDED:** Use `incremental-implementation` for multi-file features.
11. **RECOMMENDED:** Use `doubt-driven-development` for high-stakes accounting logic.
12. **FORBIDDEN:** Never modify test assertions without verifying the expected value is correct.
13. **FORBIDDEN:** Never disable validation (posting rules, control account checks) in production code.

### 12.2 Decision Making Hierarchy

```
When in doubt:
1. Check AGENTS.md (this document)
2. Check accounting-app/docs/analysis/ for business specs
3. Check existing code for patterns
4. Check tests for expected behavior
5. Ask the user — never infer accounting rules
```

### 12.3 Agent Handoff Protocol

Khi kết thúc session:

1. `codegraph sync` — cập nhật index
2. Run full test suite
3. Update AGENTS.md nếu có quy tắc mới
4. Summary: những gì đã làm, những gì còn lại, known issues

---

## 13. CI/CD & DevOps Standards

### 13.1 Development Server

```sh
php -S 0.0.0.0:8080 -t public public/index.php
```

### 13.2 Deployment Checklist

```
[ ] All migrations run (database/migrate.php)
[ ] All tests pass
[ ] CodeGraph synced
[ ] ActionJournal directory writable
[ ] Session directory writable
[ ] PHP version ≥ 8.4
[ ] MySQL version ≥ 8.0
[ ] display_errors = Off
[ ] error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
```

### 13.3 Rollback Procedure

1. Revert code: `git revert <commit>`
2. Reverse migration: Viết migration mới để rollback (không sửa migration cũ)
3. Data fix: Nếu có dữ liệu sai → viết script fix data + test
4. Verify: Run full test suite

---

## 14. Operational Risk Register

| ID | Risk | Severity | Mitigation |
|---|---|---|---|
| R001 | Post vào kỳ đã đóng | Critical | Period lock check trước mọi post |
| R002 | Dr ≠ Cr do race condition | Critical | DB transaction + check trước commit |
| R003 | Mất audit trail | Critical | ActionJournal ghi bất biến, không cho xóa |
| R004 | Xóa dữ liệu gốc | Critical | Soft delete, status flag, không DELETE |
| R005 | Sai account code → sai FS | High | Posting rules + control account check |
| R006 | Concurrent sinh trùng số CT | High | SELECT FOR UPDATE trong VoucherService |
| R007 | Multi-step fail không rollback | High | beginTransaction/commit/rollback pattern |
| R008 | Scale: DB query không index | Medium | Index trên date, reference, account_id |
| R009 | Session hijack | Medium | httpOnly, SameSite=Lax, regenerate_id |
| R010 | CSRF attack | Medium | CSRF token trên mọi POST/PUT/DELETE |

### 14.1 Incident Response

1. **Stop the line:** Ngay lập tức dừng mọi thay đổi nếu phát hiện data integrity issue
2. **Isolate:** Xác định scope — account nào, transaction nào, period nào bị ảnh hưởng
3. **Assess:** Tính toán ảnh hưởng lên BC01/02/03 và tờ khai thuế
4. **Fix:** Viết migration + script fix data
5. **Verify:** So sánh trước/sau, kiểm tra trial balance
6. **Document:** Ghi nhận incident + root cause + fix

---

## 15. Quick Commands

```sh
# Start development server
php -S 0.0.0.0:8080 -t public public/index.php

# Run all migrations
php database/migrate.php

# Run seed data (nếu cần)
php database/seed_posting_rules.php

# Run all tests
for f in tests/*.php; do php "$f"; done

# Run single test
php tests/JournalServiceTest.php

```

---

## 16. CodeGraph Workflow

### 16.1 Implementation Workflow

Mỗi task đi qua 5 bước. Stick to this sequence — nó tiết kiệm 50-70% context so với grep+read loop.

```
┌─ 1. CONTEXT ──────────────────────────┐
│  codegraph_context "task description"  │  ← 1 call = entry points + key symbols + callers
└────────────────────────────────────────┘
         ↓
┌─ 2. DEEP DIVE (if needed) ────────────┐
│  codegraph_explore "Symbol1 Symbol2"  │  ← 1 call = source of several symbols grouped by file
│  codegraph_trace from → to            │  ← 1 call = full call path incl. dynamic hops
└────────────────────────────────────────┘
         ↓
┌─ 3. IMPACT ───────────────────────────┐
│  codegraph_impact Symbol              │  ← 1 call = everything affected by the change
└────────────────────────────────────────┘
         ↓
┌─ 4. IMPLEMENT ────────────────────────┐
│  Edit code (surgical, no scope creep) │
└────────────────────────────────────────┘
         ↓
┌─ 5. VERIFY ───────────────────────────┐
│  codegraph sync  (after file save)    │
│  for f in tests/*.php; do php "$f";   │  ← 0 new failures
└────────────────────────────────────────┘
```

### 16.2 Quick Reference

| When you need to... | 1 call, done |
|---|---|
| Understand a feature/module before coding | `codegraph_context "..."` |
| See actual source of several symbols | `codegraph_explore "Sym1 Sym2"` |
| Trace how A reaches B (full path) | `codegraph_trace A → B` |
| Check what breaks if you change X | `codegraph_impact X` |
| Find where X is defined | `codegraph_search X` |
| See X's signature + source | `codegraph_node X --code` |
| List files under a path | `codegraph_files path` |

### 16.3 Budget Rules

- **1 context call first** — enough for most tasks. Only then deep-dive.
- **1 explore call, not N node calls** — `codegraph_explore` returns multiple symbols' source in one call. Chain of `codegraph_search` + `codegraph_node` × N costs more.
- **Trust the AST** — CodeGraph is a full tree-sitter parse. Grep/Read to verify = waste. If it says a symbol is defined at `File.php:42`, it is.
- **Sync after every edit** — `codegraph sync` (index lags ~500ms behind file writes; wait, then query).
- **Never grep for structural questions.** Grep is for: log messages, comments, literal string contents. CodeGraph is for: definitions, callers, callees, types, imports, class hierarchies.

---

## 17. Directory Structure

```
├── config/               services.php (DI), routes.php, database.php
├── data/                 Static data files (coa_circular_99.json)
├── database/
│   ├── migrate.php       Migration runner
│   ├── migrations/       Migration files
│   └── seed_posting_rules.php
├── public/
│   ├── index.php         Entry point + auth guard
│   └── views/            View templates (extend layout.php)
├── src/Accounting/
│   ├── Domain/
│   │   ├── Model/        Plain PHP objects with getters/setters/toArray()
│   │   ├── Repository/   Interface definitions
│   │   ├── Service/      Business logic services
│   │   └── Contract/     Interface contracts (AuditLoggerInterface)
│   ├── Infrastructure/
│   │   ├── Auth.php      Authentication & authorization
│   │   ├── Database/     AuditLogger
│   │   ├── Helpers.php   Utility functions
│   │   ├── JsonResponse.php  API response helper
│   │   ├── Logging/      Logger + LoggingPDO + ActionJournal
│   │   ├── Router.php    Request router
│   │   ├── SessionMiddleware.php
│   │   └── Repository/   PDO implementations
│   └── Interfaces/HTTP/  Controllers
├── docs/
│   ├── analysis/         Business specs & gap analysis
│   └── decisions/        Architecture Decision Records (ADRs)
└── tests/                Test files (use tests/bootstrap.php)
```

---

## 18. New Entity Checklist

Khi thêm entity mới, thực hiện theo thứ tự:

```
[ ] 1. Migration: database/migrations/NNN_create_{table}.php
[ ] 2. Model: src/Accounting/Domain/Model/{Entity}.php
[ ] 3. Repository Interface: src/Accounting/Domain/Repository/{Entity}RepositoryInterface.php
[ ] 4. PDO Repository: src/Accounting/Infrastructure/Repository/PDO{Entity}Repository.php
[ ] 5. Controller: src/Accounting/Interfaces/HTTP/{Module}/{Entity}Controller.php
[ ] 6. Routes: config/routes.php
[ ] 7. DI: config/services.php
[ ] 8. View: public/views/{entity}.php (extends layout.php)
[ ] 9. Sidebar: public/views/layout.php
[ ] 10. Tests: tests/{Entity}Test.php (use tests/bootstrap.php)
[ ] 11. Permissions: Thêm module/action vào RBAC
[ ] 12. Audit: AuditLogger::log() trong controller/service
```

### 18.1 Definition of Done

Mọi task/feature phải đáp ứng tất cả các tiêu chí sau trước khi merge:

```
[ ] Code tuân thủ §5 conventions (naming, formatting, types)
[ ] Full test suite: 0 failures (for f in tests/*.php; do php "$f"; done)
[ ] Happy path + ít nhất 1 failure case cho mọi service method mới
[ ] PHP syntax check: php -l trên mọi file thay đổi
[ ] AUDIT: AuditLogger::log() cho mọi thay đổi dữ liệu quan trọng
[ ] BACKWARD COMPATIBLE: Không breaking change không cần thiết
[ ] DOCS: AGENTS.md cập nhật nếu có quy tắc mới
[ ] ADR: Viết ADR nếu là architectural decision
[ ] CLEAN: Không debug code, không comment-out code, không TODO
```

---

## 19. Skill Selection Matrix

### 19.1 Primary Skills

| Phase | Skill | When |
|---|---|---|
| **Clarify intent** | `interview-me` | Không biết user thực sự muốn gì |
| **Refine ideas** | `idea-refine` | Ý tưởng mơ hồ, cần variants |
| **Define** | `spec-driven-development` | Cần acceptance criteria trước khi code |
| **Plan** | `planning-and-task-breakdown` | Chia spec thành tasks |
| **Build — general** | `incremental-implementation` | Default: vertical slices, test each |
| **Build — API** | `api-and-interface-design` | REST endpoints |
| **Build — UI** | `frontend-ui-engineering` | Bootstrap 5 views |
| **Build — verified** | `source-driven-development` | Verify vs official docs |
| **Build — adversarial** | `doubt-driven-development` | Nghiệp vụ phức tạp/quan trọng |
| **Build — context** | `context-engineering` | Feed agent đúng files |
| **Test** | `tdd` / `test-driven-development` | Red-green-refactor |
| **Debug** | `debugging-and-error-recovery` | Reproduce → fix → guard |
| **Review** | `code-review-and-quality` | 5-axis review |
| **Review — security** | `security-and-hardening` | OWASP, input validation |
| **Simplify** | `code-simplification` | Simplify sau khi test pass |
| **Refactor** | `improve-codebase-architecture` | Deepen modules |
| **Git** | `git-workflow-and-versioning` | Atomic commits |
| **CI/CD** | `ci-cd-and-automation` | Quality gates |
| **Docs** | `documentation-and-adrs` | Capture why |
| **Ship** | `shipping-and-launch` | Pre-launch checklist |
| **Deprecate** | `deprecation-and-migration` | Remove code safely |
| **Communication** | `caveman` | Terse mode |

### 19.2 Default Skill

```
karpathy-guidelines (simplicity first, surgical changes)
+ caveman (terse output)
```

---

## 20. Changelog & ADR

### 20.1 Changelog

| Version | Date | Changes |
|---|---|---|---|---|
| 3.0 | 2026-06-02 | **6 feature gaps implemented in 7 commits.** Phase A (Export + SubLedger + BC09), Gap 1 (Sales Order), Gap 2 (Cost/Manufacturing), Gap 3 (Budget & Planning), Gap 4 (Contract Management), Gap 5 (Project Accounting), Gap 7 (Custom Report Builder). **New migrations:** 088-098 (11 files: e_invoices, vat_declarations, vat_groups, business_config, bc09_config, sales_orders, contract_enhancements, project_accounting, cost_manufacturing, budget_planning, report_definitions). **New services:** ContractService, ProjectAccountingService, ManufacturingService, BudgetService, ReportBuilderService. **New controllers:** ContractManagementController, ProjectAccountingController, ManufacturingController, BudgetController, ReportBuilderController. **New views:** contracts.php, project-accounting.php, manufacturing.php, budget.php, report-builder.php. **Test growth:** 1,194→1,385 tests, 59→66 test files, 0 failures. All 6 gaps complete — module maturity ~8.9/10 vs MISA/FAST/BRAVO. |
| 2.8 | 2026-05-29 | **GL Posting Engine BA analysis complete.** 14-section spec covering full journal lifecycle, 75 posting rules, data flows, internal controls, 7 user journeys, 4 extension items. **4 production-polish items implemented & committed:** (1) Period lock enforcement on Vat/Cit/Fct finalise; (2) VAT/CIT UI — scan non-deductible, reconcile, loss carryforward; (3) FCT CSV export — endpoint + view buttons; (4) FA views — fixed account codes (1111→111, 1121→112, 41111→411, 2141→214, 2112→211), category-dynamic FA account in preview. All 49 test files pass, 0 failures. | `docs/analysis/payroll-engine-brain-logic.md` expanded from 9 sections (1465 lines) to 14 sections (1928 lines). Added §1 Executive BA Analysis (business case, ROI model, risk assessment), §2 Scope Definition (in/out scope, integration boundaries, assumptions), §3 Payroll Functional Spec (8 feature areas with detailed requirements), §4 Full Payroll Lifecycle (employee lifecycle, monthly cycle, event triggers, period state machine), §10 Validation & Internal Control (rules engine, fraud detection, segregation of duties, period locking), §11 Reporting & Reconciliation (11 standard journal entries, trial balance, monthly reconciliation checklist, 5 statutory reports with forms), §13 Functional Rules Matrix (93 rules across 10 categories with legal references), §14 Final Deliverables (14 new DB tables, 10 services, 25 API endpoints, 20 UI screens, 185-test plan, 7-phase roadmap). All 49 test files pass, 0 failures. |
| 2.6 | 2026-05-29 | **FA module full 13-section BA/Chief Accountant analysis complete.** `docs/analysis/fa-ccdc-chief-accountant-analysis.md` expanded from 10 sections (1363 lines) to 13 sections (1958 lines). Added §11 Reporting & Tax Compliance (TT 99 forms 01-06, BC 01/02/09 mapping, CIT impact, tax inspection prep), §12 Integration Contracts (6 integration points with GL/AP/Cash/Inventory/Period Close/FS), §13 Implementation Roadmap (7-phase plan with acceptance criteria + priority matrix). FA acquisition + disposal implemented with 25 lifecycle tests. Views need production polish. |
| 2.5 | 2026-05-29 | **CashService VAT splitting (thuế GTGT).** 5 methods (recordReceipt, recordPayment, recordBankReceipt, recordBankPayment, recordBankCharge) now create 3-line journal entries (net + VAT 1331/33311) when vatAmount > 0. recordBankInterest unchanged (financial services exempt). Backward compatible via optional params defaulting to 0. 7 new tests — total 28 CashTest, 0 failures. |
| 2.3 | 2026-05-27 | **Vietnamese Language Audit — toàn bộ thông báo người dùng sang tiếng Việt chuyên nghiệp.** ~200 messages từ controllers, services, models, views được dịch. |
| 2.2 | 2026-05-27 | **Phase 5: Views & UX — AP/AR views.** Filter bar (status/supplier/search/date), CSV export, overdue highlighting, aging column, dự phòng phải thu. |
| 2.1 | 2026-05-26 | **Phase 2.2+2.3: Aging + Provision + Credit Limit.** getProvisionRate(), getProvisionSummary() TT 48/2019. Credit limit check trong recordInvoice. |
| 2.0.1 | 2026-05-26 | **Phase 2.1: Payment Allocation Engine.** payment_allocations table, allocatePayment/allocateReceipt multi-invoice. |
| 2.0 | 2026-05-25 | Enterprise rewrite: governance tiers, Vietnamese comments, risk register, AI agent policy |
| 1.0 | 2025 | Initial: Golden Rules, Quick Commands, Code Patterns, Gotchas |

### 20.2 Architecture Decision Records

ADRs được lưu tại `docs/decisions/`. Mọi architectural decision quan trọng phải có ADR.

Template:

```markdown
# ADR-NNN: Title

## Status
Proposed | Accepted | Superseded by ADR-XXX | Deprecated

## Date
YYYY-MM-DD

## Context
Problem, constraints, requirements.

## Decision
What was decided.

## Alternatives Considered
- Option A: pros/cons
- Option B: pros/cons

## Consequences
What this means for the project.
```

---

> **Tuyên bố cuối:** Document này là governance contract. Mọi deviation phải được Engineering Lead phê duyệt. Mọi vi phạm REQUIRED hoặc FORBIDDEN đều được coi là incident và phải có post-mortem.
