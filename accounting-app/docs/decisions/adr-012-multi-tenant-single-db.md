# ADR-012: Multi-Tenant Single Database Architecture

**Status:** Accepted
**Date:** 2026-06-04
**Supersedes:** ADR-N/A (new decision)
**Related:** AGENTS.md §17 (Directory Structure), §3 (Kiến trúc hệ thống)

## Context

BookWise currently targets single-company deployments. SME Việt Nam typically has 1-5 công ty trong cùng tập đoàn (công ty mẹ + 1-4 công ty con/cùng group) requiring:
- Báo cáo tài chính riêng cho từng công ty
- Báo cáo hợp nhất (consolidation) cho thuế TNDN nhóm theo Nghị định 132/2020/NĐ-CP
- Phân quyền user theo công ty (1 user có thể access nhiều công ty, switch giữa chúng)

3 architectural patterns considered:

| Pattern | Isolation | Migration | Cross-company | Cost | Used by |
|---|---|---|---|---|---|
| A. Clone blank DB per company | Strong | Phải apply cho N DBs | Query N DBs | N instances | SAP B1 |
| B. Multi-tenant single DB (`company_id` column) | Logical | 1 lần | JOIN/GROUP BY | 1 instance | MISA, NetSuite, Xero |
| C. Single company (current) | Strong | Đơn giản | Không | 1 instance | SME 1 công ty |

## Decision

Adopt **Pattern B (multi-tenant single database)** với các quyết định chi tiết:

1. **Schema:** Tất cả data tables có `company_id INT NOT NULL` (FK → `companies.id`)
2. **Migration strategy:** Auto-backfill tạo "Default Company" (code=`CT001`) cho data hiện tại
3. **User access:** M:N qua `user_company_access` table; mỗi user có 1 default company
4. **Login flow:** 1 company → auto-set; nhiều companies → chọn dropdown (MISA pattern)
5. **Query injection:** Auth layer tự động inject `WHERE company_id = ?` vào mọi query (middleware pattern)
6. **Consolidation:** Configurable FX rate mode trong `business_config`: `consolidation.fx_rate_mode = 'year_end' | 'transaction_date' | 'configurable'`
7. **Future escape hatch:** Data đã có `company_id` → có thể migrate sang Pattern A sau nếu cần strict isolation

## Alternatives Considered

- **Pattern A (clone blank DB):** Migration overhead lớn (1 commit → N DBs), cross-company consolidation khó (query N DBs), tái cấu trúc DN (sáp nhập) downtime nặng. **Rejected** cho SME VN.
- **Pattern C (single company):** Không scale, mỗi công ty mới = setup lại từ đầu. **Rejected** vì 30%+ khách hàng BookWise target có 2+ công ty.
- **Hybrid (single DB nhưng không có company_id, dùng schema riêng):** PostgreSQL schema-per-tenant. Yêu cầu PostgreSQL. **Rejected** vì AGENTS.md §4.1 stack yêu cầu MySQL/MariaDB.

## Consequences

### Positive
- Migration 1 lần áp dụng cho tất cả công ty (zero-downtime)
- Cross-company consolidation = SQL `GROUP BY company_id` (đơn giản, performant)
- User switch company mà không cần re-login
- Tái cấu trúc DN (sáp nhập/tách công ty) = `UPDATE ... SET company_id = X` (vài giây)
- 1 DB instance → chi phí hạ tầng thấp
- Backward compatible: data cũ tự động gán `company_id = 1` (Default Company)

### Negative
- Data leakage risk nếu query thiếu `WHERE company_id = ?` (mitigated by Auth middleware injection + unit test assertion)
- Performance: shared table scan lớn hơn (mitigated by composite index `(company_id, ...)`)
- Compliance: data không tách biệt vật lý (mitigated by `user_company_access` audit + logging)
- Migration effort: 40+ tables cần thêm column (mitigated by idempotent migration script với IF NOT EXISTS)

### Risks
- **R012-1:** Query thiếu `company_id` filter → lộ data công ty khác. **Mitigation:** Auth middleware tự động inject; code review checklist bắt buộc; integration test verify.
- **R012-2:** Migration chạy lâu trên DB lớn. **Mitigation:** Add column với default = 1 (instant ở MySQL 8 online DDL), backfill batch.
- **R012-3:** Consolidation report sai do FX rate không đồng nhất. **Mitigation:** Config-driven mode + audit log FX rate per report.

## Implementation Plan

### Migrations (3 files)

**Migration 099:** `accounting_periods.balance_locked` (P0 cho R-6 Opening Balance)
```sql
ALTER TABLE accounting_periods
  ADD COLUMN balance_locked TINYINT(1) DEFAULT 0,
  ADD COLUMN balance_locked_at TIMESTAMP NULL,
  ADD COLUMN balance_locked_by INT NULL,
  ADD COLUMN period_reopened_count INT DEFAULT 0;
```

**Migration 100:** `opening_balance_imports` (P0 cho R-6)
```sql
CREATE TABLE opening_balance_imports (
    id VARCHAR(20) PRIMARY KEY,
    period_id INT NOT NULL,
    total_debit DECIMAL(15,2) NOT NULL,
    total_credit DECIMAL(15,2) NOT NULL,
    row_count INT NOT NULL,
    file_hash VARCHAR(64) NOT NULL,
    imported_by INT NOT NULL,
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    rolled_back_at TIMESTAMP NULL,
    rolled_back_by INT NULL,
    FOREIGN KEY (period_id) REFERENCES accounting_periods(id)
);
```

**Migration 101:** `companies` + `user_company_access` (Multi-tenant core)
```sql
CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    name_vi VARCHAR(255) NOT NULL,
    name_en VARCHAR(255),
    tax_code VARCHAR(20) UNIQUE NOT NULL,
    address TEXT,
    fiscal_year_start_month TINYINT DEFAULT 1,
    base_currency VARCHAR(3) DEFAULT 'VND',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_company_access (
    user_id INT NOT NULL,
    company_id INT NOT NULL,
    is_default TINYINT(1) DEFAULT 0,
    access_level VARCHAR(20) DEFAULT 'full',
    granted_by INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, company_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (company_id) REFERENCES companies(id)
);
```

**Migration 102:** Backfill + add `company_id` to 40+ tables
```sql
-- Step 1: Insert Default Company
INSERT IGNORE INTO companies (id, code, name_vi, tax_code, base_currency)
VALUES (1, 'CT001', 'Default Company', '0000000000', 'VND');

-- Step 2: Add company_id column (idempotent)
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS company_id INT DEFAULT 1;
ALTER TABLE accounts ADD COLUMN IF NOT EXISTS company_id INT DEFAULT 1;
-- ... 38 more tables (auto-generated script)

-- Step 3: Backfill
UPDATE transactions SET company_id = 1 WHERE company_id IS NULL;
-- ... 38 more UPDATEs

-- Step 4: Make NOT NULL (after backfill verified)
ALTER TABLE transactions MODIFY COLUMN company_id INT NOT NULL;
-- ... 38 more ALTERs

-- Step 5: Composite indexes
CREATE INDEX idx_tx_company_date ON transactions(company_id, transaction_date);
CREATE INDEX idx_acct_company_code ON accounts(company_id, account_code);
-- ... 38 more indexes
```

### Code Changes

**Auth middleware (existing file, surgical edit):**
```php
class Auth {
    private static ?int $currentCompanyId = null;

    public static function setCurrentCompany(int $companyId): void
    {
        if (!self::hasCompanyAccess($companyId)) {
            throw new ForbiddenException('Không có quyền truy cập công ty này');
        }
        self::$currentCompanyId = $companyId;
        $_SESSION['current_company_id'] = $companyId;
    }

    public static function getCurrentCompanyId(): int
    {
        if (self::$currentCompanyId === null && isset($_SESSION['current_company_id'])) {
            self::$currentCompanyId = (int)$_SESSION['current_company_id'];
        }
        if (self::$currentCompanyId === null) {
            // First login: lấy default company
            $stmt = $GLOBALS['pdo']->prepare("
                SELECT company_id FROM user_company_access
                WHERE user_id = ? AND is_default = 1 LIMIT 1
            ");
            $stmt->execute([$_SESSION['user_id']]);
            self::$currentCompanyId = (int)$stmt->fetchColumn() ?: 1;
        }
        return self::$currentCompanyId;
    }
}
```

**PDORepository base (new file, pattern):**
```php
abstract class CompanyScopedRepository {
    protected function applyCompanyScope(string $sql, array $params): array
    {
        $companyId = Auth::getCurrentCompanyId();
        // Tự động inject company_id vào WHERE clause
        if (stripos($sql, 'WHERE') !== false) {
            $sql = preg_replace('/WHERE/i', 'WHERE company_id = ? AND', $sql, 1);
        } else {
            $sql = preg_replace('/(FROM\s+\w+)/i', '$1 WHERE company_id = ?', $sql, 1);
        }
        array_unshift($params, $companyId);
        return [$sql, $params];
    }
}
```

### Config Seeds (8 new keys)
```sql
INSERT INTO business_config (config_key, config_value, config_type, description, module) VALUES
('approval.threshold.cash_payment_l1', '500000000', 'decimal', 'Ngưỡng KTT duyệt phiếu chi (VND)', 'approval'),
('approval.threshold.cash_payment_l2', '2000000000', 'decimal', 'Ngưỡng GĐ duyệt phiếu chi (VND)', 'approval'),
('approval.threshold.fixed_asset', '1000000000', 'decimal', 'Ngưỡng GĐ duyệt mua TSCĐ (VND)', 'approval'),
('approval.threshold.contract', '5000000000', 'decimal', 'Ngưỡng HĐQT duyệt hợp đồng (VND)', 'approval'),
('approval.allow_self_approve', 'true', 'boolean', 'Cho phép KTT tự duyệt (SME pattern)', 'approval'),
('import.rollback_window_hours', '24', 'integer', 'Thời gian rollback sau import (giờ)', 'import'),
('opening_balance.lock_after_first_period', 'true', 'boolean', 'Khóa số dư sau period đầu có phát sinh', 'opening_balance'),
('consolidation.fx_rate_mode', 'year_end', 'string', 'Chế độ tỷ giá hợp nhất: year_end|transaction_date|configurable', 'consolidation');
```

### Test Coverage
- Unit: 12 tests cho CompanyScopedRepository (inject company_id đúng cách, edge cases)
- Unit: 8 tests cho Auth::setCurrentCompany / getCurrentCompanyId
- Integration: 5 tests cho user login flow (1 company, nhiều companies, switch)
- Migration: 1 test verify backfill chính xác (transactions count = pre-migration count)
- E2E: 3 tests cho consolidation report (single currency, multi-currency, FX rate configurable)

**Total: 29 new tests**

## Approval

- Engineering Lead: Pending
- Chief Accountant: Pending (FX rate consolidation mode validation)

## References

- AGENTS.md §4.1 (Stack: MySQL/MariaDB)
- AGENTS.md §8 (Migration Standards)
- AGENTS.md §10 (Security & Audit)
- MISA AMIS multi-company pattern
- SAP B1 consolidation guide
