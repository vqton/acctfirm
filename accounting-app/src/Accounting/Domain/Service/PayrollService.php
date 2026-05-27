<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Model\PayrollEntry;
use Accounting\Domain\Model\PayrollPeriod;
use Accounting\Domain\Model\PayrollDetail;
use Accounting\Domain\Model\Employee;
use Accounting\Domain\Repository\PayrollEntryRepositoryInterface;
use Accounting\Domain\Repository\PayrollPeriodRepositoryInterface;
use Accounting\Domain\Repository\SalaryComponentRepositoryInterface;
use Accounting\Domain\Repository\EmployeeRepositoryInterface;
use Accounting\Domain\Contract\AuditLoggerInterface;

// NGHIEP VU: Dich vu tinh luong — core engine cua module Tien luong.
//
// Chuc nang:
//   1. Tinh BHXH/BHYT/BHTN — dua tren luong tham gia BH, tran BH theo vung
//   2. Tinh thue TNCN — luy tien tung phan (5 bac) theo Luat 109/2025/QH15
//   3. Tinh luong Gross -> Net cho tung nhan vien
//   4. Tao bang luong (payroll_entries + payroll_details)
//   5. Post but toan luong qua JournalService
//   6. Duyet / dong ky luong
//
// THAM SO TINH THUE 2026:
//   - Giam tru ban than: 15.500.000 VND/thang
//   - Giam tru nguoi phu thuoc: 6.200.000 VND/thang/NPT
//   - Bac 1: 0-20tr (5%), Bac 2: 20-40tr (10%), Bac 3: 40-70tr (15%),
//   - Bac 4: 70-100tr (20%), Bac 5: >100tr (25%)
//
// THAM SO BAO HIEM:
//   - BHXH NLĐ: 8% (Toi da 46.800.000 = 20 * luong toi thieu vung)
//   - BHYT NLĐ: 1.5% (Toi da 32.760.000 = 14 * luong toi thieu vung)
//   - BHTN NLĐ: 1% (Theo luong toi thieu vung)
//   - BHXH DN: 17.5%
//   - BHYT DN: 3%
//   - BHTN DN: 1%
//   - Luong toi thieu vung 2026: Vung I=4.960.000, II=4.410.000, III=3.860.000, IV=3.450.000

class PayrollService
{
    private PayrollEntryRepositoryInterface $payrollEntryRepo;
    private PayrollPeriodRepositoryInterface $payrollPeriodRepo;
    private SalaryComponentRepositoryInterface $salaryComponentRepo;
    private EmployeeRepositoryInterface $employeeRepo;
    private ?JournalService $journalService;
    private ?\PDO $pdo;
    private ?AuditLoggerInterface $auditLogger;

    // Tran BHXH: 20 lan luong toi thieu vung
    private const BHXH_RATE_EE = 0.08;
    private const BHYT_RATE_EE = 0.015;
    private const BHTN_RATE_EE = 0.01;
    private const BHXH_RATE_ER = 0.175;
    private const BHYT_RATE_ER = 0.03;
    private const BHTN_RATE_ER = 0.01;
    private const BHXH_CEILING_MULTIPLIER = 20;
    private const BHYT_CEILING_MULTIPLIER = 14;

    // Luong toi thieu vung 2026 (Nghi dinh 293/2025/ND-CP)
    private const REGION_MIN_WAGE = [
        'I' => 4960000,
        'II' => 4410000,
        'III' => 3860000,
        'IV' => 3450000,
    ];

    // Giam tru thue TNCN 2026 (Luat 109/2025/QH15)
    private const TAX_PERSONAL_DEDUCTION = 15500000;
    private const TAX_DEPENDENT_DEDUCTION = 6200000;

    // Bac thue TNCN luy tien tung phan 2026
    private const TAX_BRACKETS = [
        ['min' => 0, 'max' => 20000000, 'rate' => 0.05, 'cumulative' => 0],
        ['min' => 20000000, 'max' => 40000000, 'rate' => 0.10, 'cumulative' => 1000000],
        ['min' => 40000000, 'max' => 70000000, 'rate' => 0.15, 'cumulative' => 3000000],
        ['min' => 70000000, 'max' => 100000000, 'rate' => 0.20, 'cumulative' => 7500000],
        ['min' => 100000000, 'max' => INF, 'rate' => 0.25, 'cumulative' => 13500000],
    ];

    public function __construct(
        PayrollEntryRepositoryInterface $payrollEntryRepo,
        PayrollPeriodRepositoryInterface $payrollPeriodRepo,
        SalaryComponentRepositoryInterface $salaryComponentRepo,
        EmployeeRepositoryInterface $employeeRepo,
        ?JournalService $journalService = null,
        ?\PDO $pdo = null,
        ?AuditLoggerInterface $auditLogger = null
    ) {
        $this->payrollEntryRepo = $payrollEntryRepo;
        $this->payrollPeriodRepo = $payrollPeriodRepo;
        $this->salaryComponentRepo = $salaryComponentRepo;
        $this->employeeRepo = $employeeRepo;
        $this->journalService = $journalService;
        $this->pdo = $pdo;
        $this->auditLogger = $auditLogger;
    }

    // --- 1. TINH BAO HIEM ---
    // NGHIEP VU: Tinh cac khoan BHXH, BHYT, BHTN cho nguoi lao dong va doanh nghiep.
    // Dau vao: gross (luong gross), insuranceSalary (luong tham gia BH), region (vung)
    // Dau ra: mang voi cac khoan BH (ee: nld, er: dn)
    //
    // RUI RO: Tran BHXH = 20 lan luong toi thieu vung. Neu insuranceSalary > tran,
    // chi tinh BH tren tran. Sai tran -> dong thieu BH -> bi phat.
    public function calculateInsurance(float $gross, ?float $insuranceSalary, ?string $region = null): array
    {
        $base = $insuranceSalary ?? $gross;
        if ($base <= 0) {
            return [
                'bhxh_ee' => 0, 'bhyt_ee' => 0, 'bhtn_ee' => 0,
                'bhxh_er' => 0, 'bhyt_er' => 0, 'bhtn_er' => 0,
                'total_ee' => 0, 'total_er' => 0,
            ];
        }

        // Xac dinh luong toi thieu vung
        $minWage = self::REGION_MIN_WAGE[$region] ?? self::REGION_MIN_WAGE['IV'];

        // Tran BHXH = 20 * luong toi thieu vung
        $bhxhCeiling = $minWage * self::BHXH_CEILING_MULTIPLIER;
        // Tran BHYT = 14 * luong toi thieu vung
        $bhytCeiling = $minWage * self::BHYT_CEILING_MULTIPLIER;
        // BHTN: tran = luong toi thieu vung * 20 (nhu BHXH)
        $bhtnCeiling = $bhxhCeiling;

        $bhxhBase = min($base, $bhxhCeiling);
        $bhytBase = min($base, $bhytCeiling);
        $bhtnBase = min($base, $bhtnCeiling);

        $bhxhEe = round($bhxhBase * self::BHXH_RATE_EE);
        $bhytEe = round($bhytBase * self::BHYT_RATE_EE);
        $bhtnEe = round($bhtnBase * self::BHTN_RATE_EE);

        $bhxhEr = round($bhxhBase * self::BHXH_RATE_ER);
        $bhytEr = round($bhytBase * self::BHYT_RATE_ER);
        $bhtnEr = round($bhtnBase * self::BHTN_RATE_ER);

        return [
            'bhxh_ee' => $bhxhEe, 'bhyt_ee' => $bhytEe, 'bhtn_ee' => $bhtnEe,
            'bhxh_er' => $bhxhEr, 'bhyt_er' => $bhytEr, 'bhtn_er' => $bhtnEr,
            'total_ee' => $bhxhEe + $bhytEe + $bhtnEe,
            'total_er' => $bhxhEr + $bhytEr + $bhtnEr,
        ];
    }

    // --- 2. TINH THUE TNCN ---
    // NGHIEP VU: Tinh thue thu nhap ca nhan theo bieu luy tien tung phan 2026.
    // Thue TNCN = (Tong thu nhap chiu thue - Giam tru) * Thue suat
    //
    // Cong thuc:
    //   Thu nhap chiu thue = Gross - BHXH_e - BHYT_e - BHTN_e
    //   Thu nhap tinh thue = Thu nhap chiu thue - Giam tru ban than - Giam tru NPT
    //   Thue TNCN = Tinh theo tung bac cua thu nhap tinh thue
    //
    // RUI RO: Sai so nguoi phu thuoc -> sai thue TNCN -> bi phat thue.
    public function calculateTax(float $gross, float $insuranceEe, int $dependentCount = 0): float
    {
        $taxableIncome = $gross - $insuranceEe - self::TAX_PERSONAL_DEDUCTION - ($dependentCount * self::TAX_DEPENDENT_DEDUCTION);
        if ($taxableIncome <= 0) return 0;

        $tax = 0;
        $remaining = $taxableIncome;
        foreach (self::TAX_BRACKETS as $bracket) {
            if ($remaining <= 0) break;
            $bracketAmount = min($remaining, $bracket['max'] - $bracket['min']);
            $tax += round($bracketAmount * $bracket['rate']);
            $remaining -= $bracketAmount;
        }

        return $tax;
    }

    // --- 3. TINH LUONG CHO MOT NHAN VIEN ---
    // NGHIEP VU: Tinh toan day du cac khoan luong cho mot nhan vien.
    // Dau vao: Employee object + tuy chon ghi de
    // Dau ra: mang chi tiet cac khoan tinh luong
    //
    // Luong net = Gross - BHXH_e - BHYT_e - BHTN_e - Thue TNCN
    // Chi phi DN = Gross + BHXH_dn + BHYT_dn + BHTN_dn
    public function calculateEmployeePay(Employee $emp, array $override = []): array
    {
        $gross = $override['gross_salary'] ?? 10000000;

        // Lay thong tin BH tu employee (hoac override)
        $insuranceSalary = $override['insurance_salary'] ?? $emp->getInsuranceSalary() ?? $gross;
        $region = $override['region'] ?? $emp->getRegion();
        $dependentCount = $override['dependent_count'] ?? $emp->getDependentCount() ?? 0;
        $contractType = $override['contract_type'] ?? $emp->getContractType() ?? 'indefinite';
        $workingDays = $override['working_days'] ?? 26;
        $allowances = $override['allowances'] ?? 0;
        $deductions = $override['deductions'] ?? 0;
        $overtime = $override['overtime'] ?? 0;

        // Tinh BH
        $insurance = $this->calculateInsurance($gross, $insuranceSalary, $region);

        // Tinh thue TNCN (BHTN chi ap dung cho HD tu 1 thang tro len)
        $applyBhtn = in_array($contractType, ['indefinite', 'definite_12', 'definite_under_12']);
        $actualInsuranceEe = $insurance['total_ee'];

        $tax = $this->calculateTax($gross, $actualInsuranceEe, $dependentCount);

        $netPay = $gross + $allowances + $overtime - $deductions - $actualInsuranceEe - $tax;

        $totalCost = $gross + $allowances + $overtime + $insurance['total_er'];

        return [
            'employee_id' => $emp->getId(),
            'employee_code' => $emp->getCode(),
            'employee_name' => $emp->getName(),
            'department_id' => $emp->getDepartmentId(),
            'contract_type' => $contractType,
            'gross_salary' => $gross,
            'allowances' => $allowances,
            'deductions' => $deductions,
            'overtime' => $overtime,
            'insurance_bhxh_ee' => $insurance['bhxh_ee'],
            'insurance_bhyt_ee' => $insurance['bhyt_ee'],
            'insurance_bhtn_ee' => $insurance['bhtn_ee'],
            'insurance_total_ee' => $actualInsuranceEe,
            'insurance_bhxh_er' => $insurance['bhxh_er'],
            'insurance_bhyt_er' => $insurance['bhyt_er'],
            'insurance_bhtn_er' => $insurance['bhtn_er'],
            'insurance_total_er' => $insurance['total_er'],
            'tax_amount' => $tax,
            'net_pay' => $netPay,
            'total_cost' => $totalCost,
            'working_days' => $workingDays,
        ];
    }

    // --- 4. TAO KY LUONG ---
    public function createPayrollPeriod(string $yearMonth, ?string $createdBy = null): PayrollPeriod
    {
        $year = (int)substr($yearMonth, 0, 4);
        $month = (int)substr($yearMonth, 4, 2);
        $startDate = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $endDate = $startDate->modify('last day of this month');

        $id = uniqid('pp_');
        $period = new PayrollPeriod(
            $id, $yearMonth,
            "Bảng lương tháng {$month}/{$year}",
            $startDate, $endDate, 'open', $createdBy
        );
        $this->payrollPeriodRepo->save($period);

        $this->auditLogger?->log('payroll.period.create', 'payroll_period', $id,
            null, ['period_code' => $yearMonth], $createdBy);

        return $period;
    }

    // --- 5. XU LY BANG LUONG ---
    // NGHIEP VU: Tinh toan bang luong cho tat ca nhan vien trong mot ky.
    // Tao payroll_entry + payroll_details cho tung nhan vien.
    // Status ban dau la "draft" — cho duyet.
    public function processPayroll(string $periodId, ?string $createdBy = null, array $employeeOverrides = []): PayrollEntry
    {
        $period = $this->payrollPeriodRepo->findById($periodId);
        if (!$period) throw new \InvalidArgumentException("Không tìm thấy kỳ lương mã {$periodId}.");
        if ($period->getStatus() !== 'open') throw new \InvalidArgumentException("Kỳ lương không ở trạng thái mở. Vui lòng kiểm tra lại.");

        // Chuyen status sang processing
        $period->setStatus('processing');
        $this->payrollPeriodRepo->save($period);

        $employees = $this->employeeRepo->findAll();
        // Chi lay nhan vien active
        $employees = array_filter($employees, fn(Employee $e) => $e->isStatus());
        if (count($employees) === 0) throw new \RuntimeException('Không tìm thấy nhân viên đang hoạt động');

        $entryId = uniqid('prl_');
        $entry = new PayrollEntry($entryId, $periodId, 'draft', 0);
        $this->payrollEntryRepo->save($entry);

        $totalGross = 0; $totalAllowances = 0; $totalDeductions = 0;
        $totalInsuranceEe = 0; $totalInsuranceEr = 0;
        $totalTax = 0; $totalNet = 0; $totalCost = 0;
        $totalEmployees = 0;

        foreach ($employees as $emp) {
            $override = $employeeOverrides[$emp->getId()] ?? [];
            $result = $this->calculateEmployeePay($emp, $override);

            $detail = new PayrollDetail(
                uniqid('prd_'), $entryId, $emp->getId(),
                $result['gross_salary'], $result['allowances'], $result['deductions'],
                $result['insurance_total_ee'], $result['insurance_total_er'],
                $result['tax_amount'], $result['net_pay'], $result['overtime'],
                $result['total_cost'], $result['working_days']
            );
            $this->payrollEntryRepo->saveDetail($detail);

            $totalGross += $result['gross_salary'];
            $totalAllowances += $result['allowances'];
            $totalDeductions += $result['deductions'];
            $totalInsuranceEe += $result['insurance_total_ee'];
            $totalInsuranceEr += $result['insurance_total_er'];
            $totalTax += $result['tax_amount'];
            $totalNet += $result['net_pay'];
            $totalCost += $result['total_cost'];
            $totalEmployees++;
        }

        $entry->setTotalEmployees($totalEmployees);
        $entry->setTotalGross($totalGross);
        $entry->setTotalAllowances($totalAllowances);
        $entry->setTotalDeductions($totalDeductions);
        $entry->setTotalInsuranceEe($totalInsuranceEe);
        $entry->setTotalInsuranceEr($totalInsuranceEr);
        $entry->setTotalTax($totalTax);
        $entry->setTotalNet($totalNet);
        $entry->setTotalCost($totalCost);
        $entry->setCreatedBy($createdBy);
        $this->payrollEntryRepo->save($entry);

        // Mo lai ky luong
        $period->setStatus('open');
        $this->payrollPeriodRepo->save($period);

        $this->auditLogger?->log('payroll.process', 'payroll_entry', $entryId,
            null, ['period_id' => $periodId, 'total_employees' => $totalEmployees], $createdBy);

        return $entry;
    }

    // --- 6. POST BUT TOAN LUONG ---
    // NGHIEP VU: Ghi nhan but toan luong vao so cai qua JournalService.
    //
    // But toan phat sinh:
    //   1. Chi phi luong: No 641/642/622/627 / Co 334 (tong gross)
    //   2. BHXH NLĐ: No 334 / Co 3383
    //   3. BHYT NLĐ: No 334 / Co 3384
    //   4. BHTN NLĐ: No 334 / Co 3386
    //   5. BHXH DN: No 641/642/622/627 / Co 3383
    //   6. BHYT DN: No 641/642/622/627 / Co 3384
    //   7. BHTN DN: No 641/642/622/627 / Co 3386
    //   8. Thue TNCN: No 334 / Co 3335
    //
    // RUI RO: Neu post that bai, toan bo transaction rollback.
    // Khong co trang thai "post mot nua".
    //
    // YEU CAU: JournalService phai duoc cau hinh truoc khi goi postPayroll().
    // Sai account code -> nen de tai khoan mac dinh co the tuy chinh.
    public function postPayroll(string $entryId, string $postedBy, array $accountOverrides = []): array
    {
        if (!$this->journalService) {
            throw new \RuntimeException('JournalService chưa được cấu hình cho hạch toán lương');
        }
        if (!$this->pdo) {
            throw new \RuntimeException('PDO chưa được cấu hình cho hạch toán lương');
        }

        $entry = $this->payrollEntryRepo->findById($entryId);
        if (!$entry) throw new \InvalidArgumentException("Không tìm thấy bảng lương mã {$entryId}.");
        if (!in_array($entry->getStatus(), ['draft', 'approved'])) {
            throw new \InvalidArgumentException("Bảng lương phải ở trạng thái nháp hoặc đã duyệt mới có thể ghi sổ.");
        }

        $details = $this->payrollEntryRepo->findDetailsByEntry($entryId);
        if (count($details) === 0) throw new \RuntimeException('Không có chi tiết lương để hạch toán');

        // Lay tai khoan chi phi mac dinh (co the override)
        $costAccount = $accountOverrides['cost_account'] ?? '642';
        $payableAccount = $accountOverrides['payable_account'] ?? '334';
        $bhxhPayable = $accountOverrides['bhxh_payable'] ?? '3383';
        $bhytPayable = $accountOverrides['bhyt_payable'] ?? '3384';
        $bhtnPayable = $accountOverrides['bhtn_payable'] ?? '3386';
        $taxPayable = $accountOverrides['tax_payable'] ?? '3335';

        $totalGross = 0; $totalInsuranceEr = 0; $totalTax = 0;
        $totalBhxhEe = 0; $totalBhytEe = 0; $totalBhtnEe = 0;
        $totalBhxhEr = 0; $totalBhytEr = 0; $totalBhtnEr = 0;

        foreach ($details as $d) {
            $totalGross += (float)$d['gross_salary'];
            $totalBhxhEe += (float)$d['insurance_ee'];
            $totalBhxhEr += (float)$d['insurance_er'];
            $totalTax += (float)$d['tax_amount'];
        }

        // Tach insurance EE thanh BHXH/BHYT/BHTN
        // Ty le mac dinh: BHXH=8%, BHYT=1.5%, BHTN=1% (tong 10.5%)
        // Neu chi co tong insurance_ee ma khong co chi tiet, tach theo ty le
        $insuranceComponents = $this->splitInsuranceDetails($details, $totalBhxhEe);

        $totalBhxhEe = $insuranceComponents['bhxh_ee'];
        $totalBhytEe = $insuranceComponents['bhyt_ee'];
        $totalBhtnEe = $insuranceComponents['bhtn_ee'];
        $totalBhxhEr = $insuranceComponents['bhxh_er'];
        $totalBhytEr = $insuranceComponents['bhyt_er'];
        $totalBhtnEr = $insuranceComponents['bhtn_er'];
        $totalInsuranceEr = $totalBhxhEr + $totalBhytEr + $totalBhtnEr;

        $this->pdo->beginTransaction();
        try {
            // But toan 1: Chi phi luong (No CP / Co 334)
            $this->journalService->postEntry(
                "Lương tháng - {$entry->getPeriodId()}",
                '',
                [
                    ['account_code' => $costAccount, 'is_debit' => true, 'amount' => $totalGross],
                    ['account_code' => $payableAccount, 'is_debit' => false, 'amount' => $totalGross],
                ],
                $postedBy, false, 'payroll', null, 'SL', 'payroll'
            );

            // But toan 2: BHXH NLĐ (No 334 / Co 3383)
            if ($totalBhxhEe > 0) {
                $this->journalService->postEntry(
                    "BHXH NLĐ - {$entry->getPeriodId()}",
                    '',
                    [
                        ['account_code' => $payableAccount, 'is_debit' => true, 'amount' => $totalBhxhEe],
                        ['account_code' => $bhxhPayable, 'is_debit' => false, 'amount' => $totalBhxhEe],
                    ],
                    $postedBy, false, 'payroll', null, 'SL', 'payroll'
                );
            }

            // But toan 3: BHYT NLĐ (No 334 / Co 3384)
            if ($totalBhytEe > 0) {
                $this->journalService->postEntry(
                    "BHYT NLĐ - {$entry->getPeriodId()}",
                    '',
                    [
                        ['account_code' => $payableAccount, 'is_debit' => true, 'amount' => $totalBhytEe],
                        ['account_code' => $bhytPayable, 'is_debit' => false, 'amount' => $totalBhytEe],
                    ],
                    $postedBy, false, 'payroll', null, 'SL', 'payroll'
                );
            }

            // But toan 4: BHTN NLĐ (No 334 / Co 3386)
            if ($totalBhtnEe > 0) {
                $this->journalService->postEntry(
                    "BHTN NLĐ - {$entry->getPeriodId()}",
                    '',
                    [
                        ['account_code' => $payableAccount, 'is_debit' => true, 'amount' => $totalBhtnEe],
                        ['account_code' => $bhtnPayable, 'is_debit' => false, 'amount' => $totalBhtnEe],
                    ],
                    $postedBy, false, 'payroll', null, 'SL', 'payroll'
                );
            }

            // But toan 5: BHXH DN (No CP / Co 3383)
            if ($totalBhxhEr > 0) {
                $this->journalService->postEntry(
                    "BHXH DN - {$entry->getPeriodId()}",
                    '',
                    [
                        ['account_code' => $costAccount, 'is_debit' => true, 'amount' => $totalBhxhEr],
                        ['account_code' => $bhxhPayable, 'is_debit' => false, 'amount' => $totalBhxhEr],
                    ],
                    $postedBy, false, 'payroll', null, 'SL', 'payroll'
                );
            }

            // But toan 6: BHYT DN (No CP / Co 3384)
            if ($totalBhytEr > 0) {
                $this->journalService->postEntry(
                    "BHYT DN - {$entry->getPeriodId()}",
                    '',
                    [
                        ['account_code' => $costAccount, 'is_debit' => true, 'amount' => $totalBhytEr],
                        ['account_code' => $bhytPayable, 'is_debit' => false, 'amount' => $totalBhytEr],
                    ],
                    $postedBy, false, 'payroll', null, 'SL', 'payroll'
                );
            }

            // But toan 7: BHTN DN (No CP / Co 3386)
            if ($totalBhtnEr > 0) {
                $this->journalService->postEntry(
                    "BHTN DN - {$entry->getPeriodId()}",
                    '',
                    [
                        ['account_code' => $costAccount, 'is_debit' => true, 'amount' => $totalBhtnEr],
                        ['account_code' => $bhtnPayable, 'is_debit' => false, 'amount' => $totalBhtnEr],
                    ],
                    $postedBy, false, 'payroll', null, 'SL', 'payroll'
                );
            }

            // But toan 8: Thue TNCN (No 334 / Co 3335)
            if ($totalTax > 0) {
                $this->journalService->postEntry(
                    "Thuế TNCN - {$entry->getPeriodId()}",
                    '',
                    [
                        ['account_code' => $payableAccount, 'is_debit' => true, 'amount' => $totalTax],
                        ['account_code' => $taxPayable, 'is_debit' => false, 'amount' => $totalTax],
                    ],
                    $postedBy, false, 'payroll', null, 'SL', 'payroll'
                );
            }

            $entry->setStatus('posted');
            $entry->setPostedAt(new \DateTimeImmutable());
            $this->payrollEntryRepo->save($entry);

            $this->pdo->commit();

            $this->auditLogger?->log('payroll.post', 'payroll_entry', $entryId,
                ['status' => 'draft'], ['status' => 'posted'], $postedBy);

            return [
                'entry_id' => $entryId,
                'total_gross' => $totalGross,
                'total_insurance_er' => $totalInsuranceEr,
                'total_tax' => $totalTax,
                'status' => 'posted',
            ];
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Tach insurance tong thanh BHXH/BHYT/BHTN theo ty le mac dinh
    // Khi payroll_detail chi co tong insurance_ee/er, tach theo ty le 8:1.5:1
    private function splitInsuranceDetails(array $details, float $totalEe): array
    {
        $bhxhEe = 0; $bhytEe = 0; $bhtnEe = 0;
        $bhxhEr = 0; $bhytEr = 0; $bhtnEr = 0;

        // Ty le: BHXH=8, BHYT=1.5, BHTN=1 => tong 10.5
        $ratioBhxh = 8 / 10.5;
        $ratioBhyt = 1.5 / 10.5;
        $ratioBhtn = 1 / 10.5;

        $bhxhEe = round($totalEe * $ratioBhxh);
        $bhytEe = round($totalEe * $ratioBhyt);
        $bhtnEe = $totalEe - $bhxhEe - $bhytEe;

        $totalEr = 0;
        foreach ($details as $d) { $totalEr += (float)$d['insurance_er']; }

        $bhxhEr = round($totalEr * $ratioBhxh);
        $bhytEr = round($totalEr * $ratioBhyt);
        $bhtnEr = $totalEr - $bhxhEr - $bhytEr;

        return [
            'bhxh_ee' => $bhxhEe, 'bhyt_ee' => $bhytEe, 'bhtn_ee' => $bhtnEe,
            'bhxh_er' => $bhxhEr, 'bhyt_er' => $bhytEr, 'bhtn_er' => $bhtnEr,
        ];
    }

    // --- 7. DUYET BANG LUONG ---
    public function approvePayroll(string $entryId, string $approvedBy): PayrollEntry
    {
        $entry = $this->payrollEntryRepo->findById($entryId);
        if (!$entry) throw new \InvalidArgumentException("Không tìm thấy bảng lương mã {$entryId}.");
        if ($entry->getStatus() !== 'draft') {
            throw new \InvalidArgumentException("Bảng lương phải ở trạng thái nháp mới có thể phê duyệt.");
        }

        $entry->setStatus('approved');
        $this->payrollEntryRepo->save($entry);

        $this->auditLogger?->log('payroll.approve', 'payroll_entry', $entryId,
            ['status' => 'draft'], ['status' => 'approved'], $approvedBy);

        return $entry;
    }

    // --- 8. DONG KY LUONG ---
    public function closePayroll(string $periodId, string $closedBy): PayrollPeriod
    {
        $period = $this->payrollPeriodRepo->findById($periodId);
        if (!$period) throw new \InvalidArgumentException("Không tìm thấy kỳ lương mã {$periodId}.");
        if ($period->getStatus() !== 'open') {
            throw new \InvalidArgumentException("Kỳ lương phải ở trạng thái mở mới có thể đóng.");
        }

        $period->setStatus('closed');
        $this->payrollPeriodRepo->save($period);

        $this->auditLogger?->log('payroll.period.close', 'payroll_period', $periodId,
            ['status' => 'open'], ['status' => 'closed'], $closedBy);

        return $period;
    }

    // --- 9. DIEU CHINH BANG LUONG ---
    // NGHIEP VU: Dieu chinh bang luong da post (but toan bu tru).
    // Tao mot payroll_entry moi voi so lieu dieu chinh.
    // Chi ap dung cho bang luong da post.
    public function adjustPayroll(string $originalEntryId, string $createdBy, array $adjustments): PayrollEntry
    {
        $original = $this->payrollEntryRepo->findById($originalEntryId);
        if (!$original) throw new \InvalidArgumentException("Không tìm thấy bảng lương gốc mã {$originalEntryId}.");
        if ($original->getStatus() !== 'posted') {
            throw new \InvalidArgumentException("Chỉ có thể điều chỉnh bảng lương đã ghi sổ.");
        }

        $entryId = uniqid('prl_');
        $entry = new PayrollEntry($entryId, $original->getPeriodId(), 'draft', 0);
        $this->payrollEntryRepo->save($entry);

        $totalGross = 0; $totalNet = 0; $totalCost = 0;
        foreach ($adjustments as $adj) {
            $detail = new PayrollDetail(
                uniqid('prd_'), $entryId, $adj['employee_id'],
                (float)($adj['gross_salary'] ?? 0),
                (float)($adj['allowances'] ?? 0),
                (float)($adj['deductions'] ?? 0),
                (float)($adj['insurance_ee'] ?? 0),
                (float)($adj['insurance_er'] ?? 0),
                (float)($adj['tax_amount'] ?? 0),
                (float)($adj['net_pay'] ?? 0),
                (float)($adj['overtime'] ?? 0),
                (float)($adj['total_cost'] ?? 0),
                (float)($adj['working_days'] ?? 0),
                'active', $adj['notes'] ?? null
            );
            $this->payrollEntryRepo->saveDetail($detail);
            $totalGross += abs($detail->getGrossSalary());
            $totalNet += abs($detail->getNetPay());
            $totalCost += abs($detail->getTotalCost());
        }

        $entry->setTotalEmployees(count($adjustments));
        $entry->setTotalGross($totalGross);
        $entry->setTotalNet($totalNet);
        $entry->setTotalCost($totalCost);
        $entry->setCreatedBy($createdBy);
        $this->payrollEntryRepo->save($entry);

        $this->auditLogger?->log('payroll.adjust', 'payroll_entry', $entryId,
            ['original_id' => $originalEntryId], ['adjustments' => count($adjustments)], $createdBy);

        return $entry;
    }

    // --- HELPERS ---

    public function findEntryById(string $id): ?PayrollEntry
    {
        return $this->payrollEntryRepo->findById($id);
    }

    public function findPeriodById(string $id): ?PayrollPeriod
    {
        return $this->payrollPeriodRepo->findById($id);
    }

    public function findAllEntries(): array
    {
        return $this->payrollEntryRepo->findAll();
    }

    public function findAllPeriods(): array
    {
        return $this->payrollPeriodRepo->findAll();
    }

    public function findDetailsByEntry(string $entryId): array
    {
        return $this->payrollEntryRepo->findDetailsByEntry($entryId);
    }

    public function findOpenPeriods(): array
    {
        return $this->payrollPeriodRepo->findOpen();
    }
}
