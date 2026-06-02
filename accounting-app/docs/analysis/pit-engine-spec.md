# PIT Engine — Personal Income Tax (Thuế TNCN)

> **Version:** 1.0
> **Created:** 2026-06-02
> **Legal basis:** Luật TNCN 109/2025/QH15 (2026), TT 20/2026/TT-BTC, Nghị quyết 110/2025/UBTVQH15
> **Service:** `PitDeclarationService` + `PayrollService` (PIT calculation)

## 1. Overview

PIT covers 2 declaration forms:
- **05/KK-TNCN** — monthly PIT declaration (khai thuế TNCN tháng)
- **05/QTT-TNCN** — annual PIT settlement (quyết toán thuế TNCN năm)

Calculation is embedded in `PayrollService`; declaration + XML export in `PitDeclarationService`.

## 2. Calculation Logic

### 2.1 Taxable Income (TNCT)

```
TNCT = Gross Salary - Mandatory Insurance (10.5%) - Personal Deduction - Dependent Deductions
```

### 2.2 Deductions (2026, config-driven)

| Item | Monthly | Annual |
|---|---|---|
| Personal deduction (bản thân) | 15,500,000 | 132,000,000 (11 tháng) |
| Dependent (NPT) | 6,200,000/người | 52,800,000/người |

### 2.3 Progressive Brackets (5 bậc, effective 07/2026)

| Bậc | Thu nhập tính thuế/tháng | Thuế suất | Cách tính |
|---|---|---|---|
| 1 | ≤ 10M | 5% | 0 + 5% × TNTT |
| 2 | 10M - 30M | 10% | 500K + 10% × (TNTT - 10M) |
| 3 | 30M - 60M | 20% | 2.5M + 20% × (TNTT - 30M) |
| 4 | 60M - 100M | 30% | 8.5M + 30% × (TNTT - 60M) |
| 5 | > 100M | 35% | 20.5M + 35% × (TNTT - 100M) |

### 2.4 Non-Resident

Flat 20% on gross income. No deductions.

## 3. Service API

```php
class PitDeclarationService {
    // Monthly: 05/KK-TNCN
    public function prepareMonthlyDeclaration(int $periodId, string $createdBy): array;
    public function finaliseDeclaration(int $declarationId, string $approvedBy): void;
    public function getMonthlyDeclaration(int $declarationId): array;
    
    // Annual: 05/QTT-TNCN
    public function prepareAnnualSettlement(int $year, string $createdBy): array;
    public function getAnnualSettlement(int $declarationId): array;
    
    // Export
    public function exportToXml(int $declarationId): string;  // HTKK-compatible XML
}
```

## 4. Data Flow

```
PayrollService::processPayroll()
  → calculateEmployeePay() → TNCT, PIT per employee
  → Dr 334 (salary payable) / Cr 3335 (PIT payable)
  
PitDeclarationService::prepareMonthlyDeclaration()
  → Query period's payroll entries
  → Aggregate TNCT, deductions, PIT per bracket
  → Store as declaration data (JSON)
  → Status: draft → finalised
  → exportToXml() → HTKK-compatible XML
```

## 5. Config-Driven Keys

| Key | Type | Default |
|---|---|---|
| pit.personal_deduction | decimal | 15500000 |
| pit.dependent_deduction | decimal | 6200000 |
| pit.non_resident_rate | percent | 20 |
| pit.progressive_brackets | json | [{"to":10,"rate":5},{"to":30,"rate":10},{"to":60,"rate":20},{"to":100,"rate":30},{"rate":35}] |

## 6. Integration Points

| Module | Data | Direction |
|---|---|---|
| PayrollService | Gross salary, deductions, PIT calculated | → reads |
| GL | TK 3335 (PIT payable) balance | → reads for reconciliation |
| JournalService | Salary + PIT posting | → posts via PayrollService |
