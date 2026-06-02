<?php
namespace Accounting\Domain\Service;

//
// DỊCH VỤ KHAI THUẾ TNCN
//
// Xử lý 2 mẫu tờ khai:
//   05/KK-TNCN: Khai thuế TNCN theo tháng/quý (kê khai doanh thu, thuế khấu trừ)
//   05/QTT-TNCN: Quyết toán thuế TNCN năm (tổng hợp cả năm)
//
// Tuân thủ Luật TNCN 109/2025, TT 111/2013, TT 92/2015.
//
// Phân biệt cư trú/không cư trú (183-day rule):
//   - Cư trú: >183 ngày trong năm hoặc có nơi ở thường xuyên
//   - Thuế suất: Biểu lũy tiến 5-35% (cư trú) hoặc 20% (không cư trú)
//
class PitDeclarationService
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

    //
    // TỜ KHAI THÁNG/QUÝ (05/KK-TNCN)
    //
    public function prepareMonthly(string $period): array
    {
        $periodStart = $period . '-01';
        $nextPeriod = date('Y-m', strtotime('+1 month', strtotime($periodStart)));
        $periodEnd = date('Y-m-d', strtotime('-1 day', strtotime($nextPeriod . '-01')));

        $employees = $this->pdo->query(
            "SELECT e.id, e.full_name, e.tax_code,
                    e.is_resident AS is_resident,
                    e.dependents AS dependents
             FROM employees e WHERE e.is_active = 1"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $totalIncome = 0.0;
        $totalDeduction = 0.0;
        $totalTaxWithheld = 0.0;
        $employeeDetails = [];

        foreach ($employees as $emp) {
            $income = $this->getIncome($emp['id'], $periodStart, $periodEnd);

            $isResident = $this->isResident($emp['id'], $period . substr($period, 0, 4));
            $dependents = (int)($emp['dependents'] ?? 0);
            $standardDeduction = $isResident ? $this->cfgInt('pit.resident_deduction_monthly', 11000000) : 0;
            $dependentDeduction = $isResident ? $dependents * $this->cfgInt('pit.dependent_deduction_monthly', 4400000) : 0;
            $deduction = $standardDeduction + $dependentDeduction;
            $taxableIncome = max(0, $income - $deduction);
            $tax = $isResident
                ? $this->progressiveTax($taxableIncome)
                : $income * $this->cfg('pit.non_resident_rate', 20) / 100;

            $totalIncome += $income;
            $totalDeduction += $deduction;
            $totalTaxWithheld += $tax;

            $employeeDetails[] = [
                'id' => $emp['id'],
                'name' => $emp['full_name'],
                'tax_code' => $emp['tax_code'],
                'is_resident' => $isResident,
                'income' => $income,
                'deduction' => $deduction,
                'dependents' => $dependents,
                'taxable_income' => $taxableIncome,
                'tax_withheld' => $tax,
            ];
        }

        return [
            'period' => $period,
            'form' => '05/KK-TNCN',
            'total_employees' => count($employees),
            'total_income' => $totalIncome,
            'total_deduction' => $totalDeduction,
            'total_taxable_income' => max(0, $totalIncome - $totalDeduction),
            'total_tax_withheld' => $totalTaxWithheld,
            'employees' => $employeeDetails,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    //
    // QUYẾT TOÁN NĂM (05/QTT-TNCN)
    //
    public function prepareAnnual(string $year): array
    {
        $start = $year . '-01-01';
        $end = $year . '-12-31';

        $stmt = $this->pdo->prepare(
            "SELECT id, full_name, tax_code, is_active, dependents,
                    is_resident
             FROM employees WHERE is_active = 1"
        );
        $stmt->execute();
        $employees = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalIncome = 0.0;
        $totalDeduction = 0.0;
        $totalTaxWithheld = 0.0;
        $employeeDetails = [];

        foreach ($employees as $emp) {
            $income = $this->getIncome($emp['id'], $start, $end);
            $isResident = $this->isResident($emp['id'], $year);
            $dependents = (int)($emp['dependents'] ?? 0);
            $standardDeduction = $isResident ? $this->cfgInt('pit.resident_deduction_annual', 132000000) : 0;
            $dependentDeduction = $isResident ? $dependents * $this->cfgInt('pit.dependent_deduction_annual', 52800000) : 0;
            $deduction = $standardDeduction + $dependentDeduction;
            $taxableIncome = max(0, $income - $deduction);
            $tax = $isResident
                ? $this->progressiveTax($taxableIncome / 12) * 12
                : $income * $this->cfg('pit.non_resident_rate', 20) / 100;

            $monthlyPaid = $this->getMonthlyTaxPaid($emp['id'], $year);
            $remaining = max(0, $tax - $monthlyPaid);

            $totalIncome += $income;
            $totalDeduction += $deduction;
            $totalTaxWithheld += $tax;

            $employeeDetails[] = [
                'id' => $emp['id'],
                'name' => $emp['full_name'],
                'tax_code' => $emp['tax_code'],
                'is_resident' => $isResident,
                'annual_income' => $income,
                'annual_deduction' => $deduction,
                'dependents' => $dependents,
                'taxable_income' => $taxableIncome,
                'annual_tax' => $tax,
                'monthly_paid' => $monthlyPaid,
                'remaining' => $remaining,
            ];
        }

        return [
            'year' => $year,
            'form' => '05/QTT-TNCN',
            'total_employees' => count($employees),
            'total_income' => $totalIncome,
            'total_deduction' => $totalDeduction,
            'total_taxable_income' => max(0, $totalIncome - $totalDeduction),
            'total_annual_tax' => $totalTaxWithheld,
            'employees' => $employeeDetails,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function exportKkXml(string $period): string
    {
        $data = $this->prepareMonthly($period);
        return $this->buildXml('05/KK-TNCN', $data);
    }

    public function exportQttXml(string $year): string
    {
        $data = $this->prepareAnnual($year);
        return $this->buildXml('05/QTT-TNCN', $data);
    }

    private function buildXml(string $form, array $data): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $root = $dom->createElementNS('', 'TKN');
        $root->setAttribute('xmlns', 'https://gdt.gov.vn/pit/declaration');
        $dom->appendChild($root);

        $add = fn(string $n, string $v) => $root->appendChild($dom->createElement($n, htmlspecialchars($v)));

        $add('TBAN', $form);
        $period = $data['period'] ?? $data['year'] ?? '';
        $add('Ky', $period);
        $add('TongThuNhap', (string)($data['total_income'] ?? 0));
        $add('TongGiamTru', (string)($data['total_deduction'] ?? 0));
        $add('TongTNTT', (string)($data['total_taxable_income'] ?? 0));
        $add('TongThue', (string)($data['total_tax_withheld'] ?? 0));

        // Chi tiết từng nhân viên
        $dsEl = $dom->createElement('DanhSach');
        foreach (($data['employees'] ?? []) as $emp) {
            $empEl = $dom->createElement('NhanVien');
            foreach ($emp as $k => $v) {
                if (is_bool($v)) { $v = $v ? '1' : '0'; }
                $empEl->appendChild($dom->createElement(
                    $k === 'id' ? 'MaNV' : $k,
                    htmlspecialchars((string)$v)
                ));
            }
            $dsEl->appendChild($empEl);
        }
        $root->appendChild($dsEl);

        return $dom->saveXML();
    }

    private function getIncome(int $employeeId, string $start, string $end): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(le.amount), 0)
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             JOIN accounts a ON a.id = le.account_id
             WHERE a.code = '334' AND t.status = 'posted'
             AND t.transaction_date BETWEEN ? AND ?
             AND le.is_debit = 0
             AND (t.reference LIKE ? OR t.description LIKE ?)"
        );
        $empRef = '%emp=' . $employeeId . '%';
        $stmt->execute([$start, $end, $empRef, $empRef]);
        return (float)$stmt->fetchColumn();
    }

    private function isResident(int $employeeId, string $year): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT is_resident FROM employees WHERE id = ?"
        );
        $stmt->execute([$employeeId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? (bool)$row['is_resident'] : true;
    }

    private function getMonthlyTaxPaid(int $employeeId, string $year): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0)
             FROM pit_monthly_tax
             WHERE employee_id = ? AND fiscal_year = ?"
        );
        $stmt->execute([$employeeId, $year]);
        return (float)$stmt->fetchColumn();
    }

    private function getDependents(int $employeeId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM employee_dependents WHERE employee_id = ? AND is_active = 1"
        );
        $stmt->execute([$employeeId]);
        return (int)$stmt->fetchColumn();
    }

    private function cfg(string $key, mixed $default = null): mixed
    {
        return $this->config?->get($key, $default) ?? $default;
    }

    private function cfgInt(string $key, int $default = 0): int
    {
        return $this->config?->getInt($key, $default) ?? $default;
    }

    private function cfgJson(string $key, array $default = []): array
    {
        return $this->config?->getJson($key, $default) ?? $default;
    }

    // Biểu thuế lũy tiến 5-35% (áp dụng cho cư trú)
    private function progressiveTax(float $monthlyTaxableIncome): float
    {
        $brackets = $this->cfgJson('pit.resident_brackets', [
            ['bound' => 5000000, 'rate' => 0.05, 'baseTax' => 0],
            ['bound' => 10000000, 'rate' => 0.10, 'baseTax' => 250000],
            ['bound' => 18000000, 'rate' => 0.15, 'baseTax' => 750000],
            ['bound' => 32000000, 'rate' => 0.20, 'baseTax' => 1950000],
            ['bound' => 52000000, 'rate' => 0.25, 'baseTax' => 4750000],
            ['bound' => 80000000, 'rate' => 0.30, 'baseTax' => 9750000],
            ['bound' => PHP_FLOAT_MAX, 'rate' => 0.35, 'baseTax' => 18150000],
        ]);
        foreach ($brackets as $bracket) {
            if ($monthlyTaxableIncome <= $bracket['bound']) {
                return $monthlyTaxableIncome * $bracket['rate'] - $bracket['baseTax'];
            }
        }
        return $monthlyTaxableIncome * 0.35 - 18150000;
    }
}
