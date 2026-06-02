<?php
namespace Accounting\Domain\Service;

//
// DỊCH VỤ QUYẾT TOÁN THUẾ TNDN: Tính toán thu nhập chịu thuế và thuế TNDN phải nộp
// Tuân thủ Thông tư 20/2026/TT-BTC về thuế TNDN
//
// Nghiệp vụ: Cuối kỳ, kế toán tổng hợp doanh thu, chi phí để xác định thu nhập chịu thuế
// và tính thuế TNDN phải nộp theo thuế suất hiện hành (mặc định 20%).
//
// Quy trình: prepareCalculation → review → finalise
// Thu nhập chịu thuế = Doanh thu - Giá vốn - CPBH - CPQLDN - CPTC + DTTài chính + TNKhác - CPKhác
// Thuế TNDN = max(0, Thu nhập chịu thuế × Thuế suất%)
//
class CitService
{
    private \PDO $pdo;
    private ?\Accounting\Domain\Contract\AuditLoggerInterface $auditLogger;
    private ?ConfigService $config;

    public function __construct(\PDO $pdo, ?\Accounting\Domain\Contract\AuditLoggerInterface $auditLogger = null, ?ConfigService $config = null)
    {
        $this->pdo = $pdo;
        $this->auditLogger = $auditLogger;
        $this->config = $config;
    }

    private function cfg(string $key, mixed $default): mixed
    {
        return $this->config?->get($key, $default) ?? $default;
    }

    //
    //
    // QUÉT CHI PHÍ KHÔNG ĐƯỢC TRỪ: Kiểm tra các khoản chi phí vượt trần cho phép
    // 1. Quảng cáo, tiếp thị > 10% doanh thu (TT 78/2014)
    // 2. Chi phí lãi vay > 30% EBITDA (TT 132/2020, BEPS 2.0)
    //
    // RỦI RO: Nếu không loại trừ chi phí không được trừ, thu nhập chịu thuế sẽ thấp hơn thực tế
    // → bị truy thu thuế + phạt chậm nộp.
    //
    public function scanNonDeductibleExpenses(string $period, float $revenue, ?float $advertisingExpense = null, ?float $interestExpense = null): array
    {
        $periodStart = $period . '-01';
        $nextPeriod = date('Y-m', strtotime('+1 month', strtotime($periodStart)));
        $periodEnd = date('Y-m-d', strtotime('-1 day', strtotime($nextPeriod . '-01')));

        // Nếu không truyền vào, tự động lấy từ ledger
        if ($advertisingExpense === null) {
            $advStmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(le.amount), 0)
                 FROM ledger_entries le
                 JOIN transactions t ON t.id = le.transaction_id
                 JOIN accounts a ON a.id = le.account_id
                 WHERE a.code = '641' AND t.status = 'posted'
                 AND t.transaction_date BETWEEN ? AND ?
                 AND le.is_debit = 1"
            );
            $advStmt->execute([$periodStart, $periodEnd]);
            $advertisingExpense = (float)$advStmt->fetchColumn();
        }
        if ($interestExpense === null) {
            $intStmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(le.amount), 0)
                 FROM ledger_entries le
                 JOIN transactions t ON t.id = le.transaction_id
                 JOIN accounts a ON a.id = le.account_id
                 WHERE a.code = '635' AND t.status = 'posted'
                 AND t.transaction_date BETWEEN ? AND ?
                 AND le.is_debit = 1"
            );
            $intStmt->execute([$periodStart, $periodEnd]);
            $interestExpense = (float)$intStmt->fetchColumn();
        }

        $advCap = $this->cfg('cit.advertising_cap', 10) / 100;
        $advLimit = $revenue * $advCap;
        $advExcess = max(0, $advertisingExpense - $advLimit);

        $intCap = $this->cfg('cit.interest_ebitda_cap', 30) / 100;
        $ebitda = $revenue;
        $intLimit = $ebitda * $intCap;
        $intExcess = max(0, $interestExpense - $intLimit);

        return [
            'period' => $period,
            'revenue' => $revenue,
            'advertising_expense' => $advertisingExpense,
            'advertising_limit_10pct' => round($advLimit, 0),
            'advertising_excess_non_deductible' => round($advExcess, 0),
            'interest_expense' => $interestExpense,
            'interest_limit_30pct' => round($intLimit, 0),
            'interest_excess_non_deductible' => round($intExcess, 0),
            'total_non_deductible' => round($advExcess + $intExcess, 0),
        ];
    }

    //
    // LẤY LỖ LUÂN CHUYỂN: Số lỗ còn được chuyển từ các kỳ trước
    // Theo TT 78/2014: lỗ được chuyển tối đa 5 năm liên tục
    //
    public function getLossCarryforward(string $period): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, period, loss_amount, remaining_amount, carryforward_years, expiry_date
             FROM tax_loss_carryforwards
             WHERE status = 'active' AND expiry_date >= CURDATE()
             ORDER BY period ASC"
        );
        $stmt->execute();
        $losses = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalAvailable = 0;
        foreach ($losses as &$l) {
            $l['loss_amount'] = (float)$l['loss_amount'];
            $l['remaining_amount'] = (float)$l['remaining_amount'];
            $totalAvailable += $l['remaining_amount'];
        }

        return [
            'period' => $period,
            'losses' => $losses,
            'total_available' => $totalAvailable,
        ];
    }

    //
    // GHI NHẬN LỖ MỚI: Khi prepareCalculation phát hiện lỗ, tự động tạo bản ghi loss carryforward
    //
    private function recordLossCarryforward(string $period, float $lossAmount, string $createdBy): void
    {
        if ($lossAmount <= 0) return;
        $id = uniqid('tlc_');
        $carryforwardYears = $this->cfg('cit.loss_carryforward_years', 5);
        $expiryDate = date('Y-m-d', strtotime("+{$carryforwardYears} years", strtotime($period . '-01')));
        $this->pdo->prepare(
            "INSERT INTO tax_loss_carryforwards (id, period, loss_amount, remaining_amount, carryforward_years, expiry_date, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, 'active', ?)"
        )->execute([$id, $period, $lossAmount, $lossAmount, $carryforwardYears, $expiryDate, $createdBy]);
    }

    //
    // SỬ DỤNG LỖ LUÂN CHUYỂN: Giảm thu nhập chịu thuế bằng lỗ còn được chuyển
    //
    private function useLossCarryforward(float $taxableIncome, string $createdBy): float
    {
        if ($taxableIncome <= 0) return 0;
        $losses = $this->pdo->query(
            "SELECT id, period, remaining_amount FROM tax_loss_carryforwards WHERE status = 'active' AND expiry_date >= CURDATE() ORDER BY period ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $remainingIncome = $taxableIncome;
        foreach ($losses as $loss) {
            if ($remainingIncome <= 0) break;
            $useAmount = min($remainingIncome, (float)$loss['remaining_amount']);
            $this->pdo->prepare("UPDATE tax_loss_carryforwards SET remaining_amount = remaining_amount - ? WHERE id = ?")->execute([$useAmount, $loss['id']]);
            $remainingIncome -= $useAmount;
            // Nếu hết lỗ thì đánh dấu fully_used
            $this->pdo->prepare("UPDATE tax_loss_carryforwards SET status = 'fully_used' WHERE id = ? AND remaining_amount <= 0")->execute([$loss['id']]);
        }
        return round($taxableIncome - $remainingIncome, 0);
    }

    // Chuẩn bị quyết toán TNDN — tổng hợp số liệu từ ledger_entries cho kỳ được chọn
    // Sử dụng bút toán đã ghi sổ (transactions.status = 'posted') trong kỳ
    //
    public function prepareCalculation(string $period, string $createdBy): array
    {
        $periodStart = $period . '-01';
        $nextPeriod = date('Y-m', strtotime('+1 month', strtotime($periodStart)));
        $periodEnd = date('Y-m-d', strtotime('-1 day', strtotime($nextPeriod . '-01')));

        // Lấy số phát sinh cho từng tài khoản trong kỳ
        // Doanh thu/Thu nhập: lấy bên Có (is_debit=0)
        // Chi phí: lấy bên Nợ (is_debit=1)
        $revenue = $this->getAccountPeriodTotal('511', $periodStart, $periodEnd, 'revenue');
        $costOfSales = $this->getAccountPeriodTotal('632', $periodStart, $periodEnd, 'expense');
        $sellingExpense = $this->getAccountPeriodTotal('641', $periodStart, $periodEnd, 'expense');
        $adminExpense = $this->getAccountPeriodTotal('642', $periodStart, $periodEnd, 'expense');
        $financialExpense = $this->getAccountPeriodTotal('635', $periodStart, $periodEnd, 'expense');
        $financialIncome = $this->getAccountPeriodTotal('515', $periodStart, $periodEnd, 'revenue');
        $otherIncome = $this->getAccountPeriodTotal('711', $periodStart, $periodEnd, 'revenue');
        $otherExpense = $this->getAccountPeriodTotal('811', $periodStart, $periodEnd, 'expense');

        // Tính thu nhập chịu thuế
        $taxableIncome = $revenue - $costOfSales - $sellingExpense - $adminExpense
            - $financialExpense + $financialIncome + $otherIncome - $otherExpense;

        // Quét chi phí không được trừ
        $nonDeductibleResult = $this->scanNonDeductibleExpenses($period, $revenue);
        $nonDeductibleExpenses = $nonDeductibleResult['total_non_deductible'];

        // Thu nhập chịu thuế sau điều chỉnh
        $adjustedTaxableIncome = $taxableIncome + $nonDeductibleExpenses;
        $lossUsed = 0;

        // Sử dụng lỗ luân chuyển nếu có lãi
        if ($adjustedTaxableIncome > 0) {
            $lossUsed = $this->useLossCarryforward($adjustedTaxableIncome, $createdBy);
            $adjustedTaxableIncome -= $lossUsed;
        }

        // Nếu lỗ thì ghi nhận loss carryforward cho kỳ sau
        if ($adjustedTaxableIncome < 0) {
            $this->recordLossCarryforward($period, abs($adjustedTaxableIncome), $createdBy);
        }

        $citRate = $this->cfg('cit.default_rate', 20);
        $citAmount = max(0, $adjustedTaxableIncome * $citRate / 100);

        // Lưu hoặc cập nhật bản ghi quyết toán TNDN
        $id = uniqid('cit_');
        $this->pdo->prepare(
            "INSERT INTO cit_calculations (id, period, revenue, cost_of_sales, selling_expense, admin_expense,
             financial_expense, financial_income, other_income, other_expense, taxable_income,
             non_deductible_expenses, adjusted_taxable_income, loss_carryforward_used,
             cit_rate, cit_amount, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)
             ON DUPLICATE KEY UPDATE
             revenue = VALUES(revenue), cost_of_sales = VALUES(cost_of_sales),
             selling_expense = VALUES(selling_expense), admin_expense = VALUES(admin_expense),
             financial_expense = VALUES(financial_expense), financial_income = VALUES(financial_income),
             other_income = VALUES(other_income), other_expense = VALUES(other_expense),
             taxable_income = VALUES(taxable_income),
             non_deductible_expenses = VALUES(non_deductible_expenses),
             adjusted_taxable_income = VALUES(adjusted_taxable_income),
             loss_carryforward_used = VALUES(loss_carryforward_used),
             cit_rate = VALUES(cit_rate), cit_amount = VALUES(cit_amount),
             status = IF(status = 'finalised', status, 'draft'),
             created_by = VALUES(created_by)"
        )->execute([$id, $period, $revenue, $costOfSales, $sellingExpense, $adminExpense,
            $financialExpense, $financialIncome, $otherIncome, $otherExpense,
            $taxableIncome, $nonDeductibleExpenses, max(0, $adjustedTaxableIncome),
            $lossUsed, $citRate, $citAmount, $createdBy]);

        // Use period-based lookup to handle ON DUPLICATE KEY UPDATE (existing record has different id)
        $stmt = $this->pdo->prepare("SELECT id FROM cit_calculations WHERE period = ?");
        $stmt->execute([$period]);
        $existingId = $stmt->fetchColumn();
        $result = $this->getCalculation($existingId ?: $id);
        $this->auditLogger?->log('cit.prepare', 'cit_calculation', $result['id'],
            null, ['period' => $period, 'cit_amount' => $citAmount, 'taxable_income' => $taxableIncome], $createdBy);
        return $result;
    }

    //
    // Lấy số phát sinh của một tài khoản trong kỳ
    // Với loại revenue: lấy tổng bên Có (phát sinh tăng doanh thu)
    // Với loại expense: lấy tổng bên Nợ (phát sinh tăng chi phí)
    //
    private function getAccountPeriodTotal(string $accountCode, string $periodStart, string $periodEnd, string $type): float
    {
        if ($type === 'revenue') {
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(le.amount), 0)
                 FROM ledger_entries le
                 JOIN transactions t ON t.id = le.transaction_id
                 JOIN accounts a ON a.id = le.account_id
                 WHERE a.code = ? AND t.status = 'posted'
                 AND t.transaction_date BETWEEN ? AND ?
                 AND le.is_debit = 0"
            );
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(le.amount), 0)
                 FROM ledger_entries le
                 JOIN transactions t ON t.id = le.transaction_id
                 JOIN accounts a ON a.id = le.account_id
                 WHERE a.code = ? AND t.status = 'posted'
                 AND t.transaction_date BETWEEN ? AND ?
                 AND le.is_debit = 1"
            );
        }
        $stmt->execute([$accountCode, $periodStart, $periodEnd]);
        return (float)$stmt->fetchColumn();
    }

    public function getCalculation(string $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM cit_calculations WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['revenue'] = (float)$row['revenue'];
        $row['cost_of_sales'] = (float)$row['cost_of_sales'];
        $row['selling_expense'] = (float)$row['selling_expense'];
        $row['admin_expense'] = (float)$row['admin_expense'];
        $row['financial_expense'] = (float)$row['financial_expense'];
        $row['financial_income'] = (float)$row['financial_income'];
        $row['other_income'] = (float)$row['other_income'];
        $row['other_expense'] = (float)$row['other_expense'];
        $row['taxable_income'] = (float)$row['taxable_income'];
        $row['cit_amount'] = (float)$row['cit_amount'];
        $row['cit_rate'] = (float)$row['cit_rate'];
        $row['non_deductible_expenses'] = (float)($row['non_deductible_expenses'] ?? 0);
        $row['adjusted_taxable_income'] = (float)($row['adjusted_taxable_income'] ?? 0);
        $row['loss_carryforward_used'] = (float)($row['loss_carryforward_used'] ?? 0);
        return $row;
    }

    public function getCalculations(): array
    {
        $rows = $this->pdo->query(
            "SELECT * FROM cit_calculations ORDER BY period DESC LIMIT 50"
        )->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($r) => [
            'id' => $r['id'],
            'period' => $r['period'],
            'status' => $r['status'],
            'revenue' => (float)$r['revenue'],
            'cost_of_sales' => (float)$r['cost_of_sales'],
            'selling_expense' => (float)$r['selling_expense'],
            'admin_expense' => (float)$r['admin_expense'],
            'financial_expense' => (float)$r['financial_expense'],
            'financial_income' => (float)$r['financial_income'],
            'other_income' => (float)$r['other_income'],
            'other_expense' => (float)$r['other_expense'],
            'taxable_income' => (float)$r['taxable_income'],
            'cit_amount' => (float)$r['cit_amount'],
            'cit_rate' => (float)$r['cit_rate'],
            'non_deductible_expenses' => (float)($r['non_deductible_expenses'] ?? 0),
            'adjusted_taxable_income' => (float)($r['adjusted_taxable_income'] ?? 0),
            'loss_carryforward_used' => (float)($r['loss_carryforward_used'] ?? 0),
            'created_at' => $r['created_at'],
        ], $rows);
    }

    public function finalise(string $id): array
    {
        // Load calculation first to get period info
        $calc = $this->getCalculation($id);
        if (!$calc) throw new \RuntimeException('Không tìm thấy quyết toán TNDN.');

        // Kiểm tra kỳ kế toán đang mở
        $period = $calc['period'] ?? '';
        if ($period && !PeriodService::isPeriodOpen($period . '-15', $this->pdo)) {
            throw new \RuntimeException("Kỳ kế toán {$period} đã đóng. Không thể khóa tờ khai.");
        }

        $stmt = $this->pdo->prepare(
            "UPDATE cit_calculations SET status = 'finalised' WHERE id = ? AND status = 'draft'"
        );
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('Không thể khóa quyết toán. Bản ghi không tồn tại hoặc đã được khóa.');
        }
        $this->auditLogger?->log('cit.finalise', 'cit_calculation', $id,
            $calc, ['status' => 'finalised'], 'system');
        return $this->getCalculation($id);
    }
}
