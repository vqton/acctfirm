# ConfigService — Data-Driven Business Rules

> **Version:** 1.0
> **Created:** 2026-06-02
> **Status:** Implemented (migration 091, ConfigService.php)
> **ADR:** ADR-011 — business_config data-driven pattern

## 1. Problem

Every business rule (tax rates, insurance %s, deduction limits, thresholds) was hardcoded as PHP constants or inline magic numbers across services. Changing a rate required code deploy:

```php
// Before — hardcoded in PayrollService.php
private const BHXH_EE = 0.08;    // 8%
private const BHYT_EE = 0.015;   // 1.5%
```

Circular 99 rates can change yearly. Each change = code review + deploy + rollback risk.

## 2. Solution: `business_config` Table + `ConfigService`

### 2.1 Table Schema

```sql
CREATE TABLE business_config (
    config_key   VARCHAR(64) PRIMARY KEY,
    config_value TEXT NOT NULL,
    config_type  VARCHAR(16) NOT NULL DEFAULT 'string',  -- int|decimal|percent|string|json
    description  TEXT,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8mb4;
```

### 2.2 ConfigService API

```php
class ConfigService {
    public function get(string $key, mixed $default = null): mixed;
    public function getInt(string $key, ?int $default = null): ?int;
    public function getDecimal(string $key, ?float $default = null): ?float;
    public function getPercent(string $key, ?float $default = null): ?float;  // returns 0.08 for '8%'
    public function getJson(string $key, ?array $default = null): ?array;
    public function set(string $key, mixed $value, string $type = 'string'): void;
}
```

Type casting is automatic based on `config_type` column:
- `int` → `(int)$value`
- `decimal` → `(float)$value`
- `percent` → `(float)$value / 100`
- `string` → `(string)$value`
- `json` → `json_decode($value, true)`

### 2.3 Safe Defaults Pattern

ConfigService is **nullable** across services. If null (not configured), hardcoded defaults remain:

```php
public function __construct(
    private \PDO $pdo,
    private array $cache = [],
    private ?ConfigService $config = null  // optional
) {}
```

This ensures 100% backward compatibility during migration. Services that don't receive ConfigService work exactly as before.

### 2.4 In-Memory Cache

All reads are cached in `$this->cache` per request. Only the first read hits the DB. No TTL needed (config doesn't change mid-request).

```php
public function get(string $key, mixed $default = null): mixed {
    if (!array_key_exists($key, $this->cache)) {
        $stmt = $this->pdo->prepare('SELECT config_value, config_type FROM business_config WHERE config_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->cache[$key] = $row ? $this->cast($row['config_value'], $row['config_type']) : $default;
    }
    return $this->cache[$key];
}
```

## 3. Seeded Config Keys (39 keys, migration 091)

| Key | Type | Default | Used By |
|---|---|---|---|
| `cit.rate` | percent | 20 | CitService, CitDeclarationEngine |
| `cit.ad_cap_percent` | percent | 10 | CitService |
| `cit.interest_cap_percent` | percent | 30 | CitService |
| `cit.loss_carryforward_years` | int | 5 | CitService |
| `pit.personal_deduction` | decimal | 15500000 | PitDeclarationService, PayrollService |
| `pit.dependent_deduction` | decimal | 6200000 | PitDeclarationService, PayrollService |
| `pit.non_resident_rate` | percent | 20 | PitDeclarationService |
| `pit.progressive_brackets` | json | [{"to":10,"rate":5},...] | PitDeclarationService, PayrollService |
| `payroll.insurance.bhxh_ee` | percent | 8 | PayrollService |
| `payroll.insurance.bhyt_ee` | percent | 1.5 | PayrollService |
| `payroll.insurance.bhtn_ee` | percent | 1 | PayrollService |
| `payroll.insurance.bhxh_er` | percent | 17.5 | PayrollService |
| `payroll.insurance.bhyt_er` | percent | 3 | PayrollService |
| `payroll.insurance.bhtn_er` | percent | 1 | PayrollService |
| `payroll.insurance.ceiling` | decimal | 93600000 | PayrollService |
| `payroll.insurance.salary_min` | decimal | 4680000 | PayrollService |
| `payroll.default_gross` | decimal | 10000000 | PayrollService |
| `insurance.region_min_wage` | json | {...} | PayrollService |
| `period.max_reopen` | int | 3 | PeriodService |
| `debt_collection.*` (11 keys) | various | — | DebtCollectionService |

## 4. Usage Example

```php
// Before — hardcoded:
$tax = max(0, $taxableIncome * 0.20);

// After — config-driven:
$rate = $this->config->getPercent('cit.rate', 0.20);
$tax = max(0, $taxableIncome * $rate);
```

Changing CIT rate: `UPDATE business_config SET config_value = '15' WHERE config_key = 'cit.rate';` — no deploy.

## 5. Services Migrated to Config-Driven

| Service | Config Keys | Migration Date |
|---|---|---|
| CitService | cit.rate, cit.ad_cap_percent, cit.interest_cap_percent, cit.loss_carryforward_years | 06/2026 |
| PitDeclarationService | pit.* (5 keys) | 06/2026 |
| PayrollService | payroll.insurance.*, pit.personal_deduction, pit.dependent_deduction, pit.progressive_brackets | 06/2026 |
| PeriodService | period.max_reopen | 06/2026 |
| DebtCollectionService | debt_collection.* (11 thresholds) | 06/2026 |

## 6. Non-Goal: Account Code Templates

ConfigService handles **scalar values** (rates, limits, thresholds). Account codes (320+ hardcoded references across services) need a JournalTemplate pattern — template rows with Dr/Cr side + account_src + amount_src. Deferred to separate session.
