# CIT Engine — Corporate Income Tax (Thuế TNDN)

> **Version:** 1.0
> **Created:** 2026-06-02
> **Legal basis:** Luật TNDN 67/2025/QH15, TT 20/2026/TT-BTC, NĐ 320/2025/NĐ-CP, NĐ 132/2020 (interest cap)
> **Services:** `CitService` + `CitDeclarationEngine`

## 1. Overview

CIT covers form **03/TNDN** (quyết toán thuế TNDN năm) with 25 indicators. Flow:

```
EBT (lợi nhuận kế toán trước thuế)
  + Điều chỉnh tăng (chi phí không được trừ)
  - Điều chỉnh giảm (thu nhập miễn thuế)
  - Kết chuyển lỗ (max 5 năm)
  = TNTT (thu nhập tính thuế)
  × Thuế suất (20% tiêu chuẩn)
  = Thuế TNDN phải nộp
  - Tạm nộp các quý (≥80%)
  = Còn phải nộp / nộp thừa
```

## 2. Non-Deductible Adjustments

### 2.1 Advertising Cap (TT 20/2026)

Max deductible advertising + promotion = 10% of revenue. Excess = non-deductible.

```php
$limit = $revenue * 0.10;
$excess = max(0, $advertisingExpense - $limit);
```

### 2.2 Interest Cap (NĐ 132/2020)

Interest expense ≤ 30% of EBITDA. Excess = non-deductible.

### 2.3 Loss Carryforward (TT 20/2026)

Max 5 consecutive years. Earliest losses consumed first (FIFO).

## 3. Service API

### CitService

```php
class CitService {
    public function scanNonDeductibleExpenses(int $periodId): array;
    public function getLossCarryforward(int $periodId): array;
    public function prepareCalculation(int $periodId, string $createdBy): array;
    public function finalise(int $periodId, string $approvedBy): void;
    public function getCalculation(int $periodId): ?array;
}
```

### CitDeclarationEngine

```php
class CitDeclarationEngine {
    public function calculate(string $periodId, array $txnData): CitDeclarationResult;
    public function exportToXml(CitDeclarationResult $result): string;
}
```

## 4. 03/TNDN 25 Indicators

| MS | Name | Source |
|---|---|---|
| [01] | Doanh thu | From FS |
| [02] | Chi phí | From FS |
| [03] | Lợi nhuận gộp | [01] - [02] |
| [04] | Thu nhập khác | From FS |
| ... | (25 total) | ... |
| [25] | Tổng thuế TNDN phải nộp | Calculated |

XML export follows GDT portal format.

## 5. Config-Driven Keys

| Key | Type | Default |
|---|---|---|
| cit.rate | percent | 20 |
| cit.ad_cap_percent | percent | 10 |
| cit.interest_cap_percent | percent | 30 |
| cit.loss_carryforward_years | int | 5 |

## 6. Integration

| Module | Data | Direction |
|---|---|---|
| FS (BC02) | Revenue, expenses | reads |
| JournalService | CIT adjustment posting (Dr 8211/Cr 3334) | posts |
| PeriodService | Year-end close includes CIT true-up | called by |
