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
use Accounting\Domain\Contract\JournalServiceInterface;

/**
 * DỊCH VỤ TÍNH LƯƠNG — core engine của module Tiền lương.
 *
 * Nghiệp vụ: Tính toán bảng lương, bảo hiểm (BHXH, BHYT, BHTN), thuế TNCN,
 * hạch toán bút toán lương vào sổ cái, quản lý kỳ lương, phê duyệt và điều chỉnh.
 *
 * Tham số lấy từ business_config (migration 091). Xem business_config chi tiết.
 *
 * Rủi ro: Sai số người phụ thuộc → sai thuế TNCN → bị phạt thuế.
 * Sai trần BHXH → đóng thiếu BH → bị phạt.
 */
class PayrollService
{
    private PayrollEntryRepositoryInterface $payrollEntryRepo;
    private PayrollPeriodRepositoryInterface $payrollPeriodRepo;
    private SalaryComponentRepositoryInterface $salaryComponentRepo;
    private EmployeeRepositoryInterface $employeeRepo;
    private ?JournalServiceInterface $journalService;
    private ?\PDO $pdo;
    private ?AuditLoggerInterface $auditLogger;
    private ?ConfigService $config;

    /**
     * @param PayrollEntryRepositoryInterface $payrollEntryRepo Repository bảng lương
     * @param PayrollPeriodRepositoryInterface $payrollPeriodRepo Repository kỳ lương
     * @param SalaryComponentRepositoryInterface $salaryComponentRepo Repository thành phần lương
     * @param EmployeeRepositoryInterface $employeeRepo Repository nhân viên
     * @param JournalServiceInterface|null $journalService Dịch vụ hạch toán bút toán (tùy chọn)
     * @param \PDO|null $pdo Kết nối database cho transaction (tùy chọn)
     * @param AuditLoggerInterface|null $auditLogger Ghi audit trail (tùy chọn)
     * @param ConfigService|null $config Dịch vụ cấu hình business_config (tùy chọn)
     */
    public function __construct(
        PayrollEntryRepositoryInterface $payrollEntryRepo,
        PayrollPeriodRepositoryInterface $payrollPeriodRepo,
        SalaryComponentRepositoryInterface $salaryComponentRepo,
        EmployeeRepositoryInterface $employeeRepo,
        ?JournalServiceInterface $journalService = null,
        ?\PDO $pdo = null,
        ?AuditLoggerInterface $auditLogger = null,
        ?ConfigService $config = null
    ) {
        $this->payrollEntryRepo = $payrollEntryRepo;
        $this->payrollPeriodRepo = $payrollPeriodRepo;
        $this->salaryComponentRepo = $salaryComponentRepo;
        $this->employeeRepo = $employeeRepo;
        $this->journalService = $journalService;
        $this->pdo = $pdo;
        $this->auditLogger = $auditLogger;
        $this->config = $config;
    }

    /**
     * Lấy giá trị cấu hình từ business_config với kiểu mixed.
     *
     * @param string $key Khóa cấu hình
     * @param mixed $default Giá trị mặc định nếu không tìm thấy
     * @return mixed Giá trị cấu hình
     */
    private function cfg(string $key, mixed $default): mixed
    {
        return $this->config?->get($key, $default) ?? $default;
    }

    /**
     * Lấy giá trị cấu hình dạng phần trăm (float).
     *
     * @param string $key Khóa cấu hình
     * @param float $default Giá trị mặc định
     * @return float Giá trị phần trăm
     */
    private function cfgPercent(string $key, float $default): float
    {
        return $this->config?->getPercent($key, $default) ?? $default;
    }

    /**
     * Lấy giá trị cấu hình dạng số nguyên.
     *
     * @param string $key Khóa cấu hình
     * @param int $default Giá trị mặc định
     * @return int Giá trị số nguyên
     */
    private function cfgInt(string $key, int $default): int
    {
        return $this->config?->getInt($key, $default) ?? $default;
    }

    /**
     * Lấy giá trị cấu hình dạng mảng JSON.
     *
     * @param string $key Khóa cấu hình
     * @param array $default Giá trị mặc định
     * @return array Mảng dữ liệu cấu hình
     */
    private function cfgJson(string $key, array $default): array
    {
        return $this->config?->getJson($key, $default) ?? $default;
    }

    /**
     * Lấy mức lương tối thiểu vùng theo quy định.
     * Dùng cho tính trần BHXH, BHYT, BHTN.
     *
     * @param string|null $region Mã vùng (I, II, III, IV). Mặc định IV nếu null.
     * @return int Mức lương tối thiểu vùng (VNĐ)
     */
    private function getRegionMinWage(?string $region): int
    {
        $defaultWages = ['I' => 4960000, 'II' => 4410000, 'III' => 3860000, 'IV' => 3450000];
        $regionWages = $this->cfgJson('insurance.region_min_wage', $defaultWages);
        return $regionWages[$region ?? 'IV'] ?? $regionWages['IV'] ?? 3450000;
    }

    /**
     * Tính các khoản bảo hiểm (BHXH, BHYT, BHTN) cho người lao động và doanh nghiệp.
     *
     * Nghiệp vụ: Tính các khoản BHXH, BHYT, BHTN cho người lao động và doanh nghiệp.
     * Đầu vào: gross (lương gross), insuranceSalary (lương tham gia BH), region (vùng).
     * Đầu ra: mảng với các khoản BH (ee: NLĐ, er: DN).
     *
     * Rủi ro: Trần BHXH = 20 lần lương tối thiểu vùng. Nếu insuranceSalary > trần,
     * chỉ tính BH trên trần. Sai trần → đóng thiếu BH → bị phạt.
     *
     * @param float $gross Lương gross (VNĐ)
     * @param float|null $insuranceSalary Lương tham gia bảo hiểm (VNĐ). Mặc định = gross nếu null.
     * @param string|null $region Mã vùng lương tối thiểu (I, II, III, IV)
     * @return array{
     *   bhxh_ee: int, bhyt_ee: int, bhtn_ee: int,
     *   bhxh_er: int, bhyt_er: int, bhtn_er: int,
     *   total_ee: int, total_er: int
     * } Mảng kết quả các khoản bảo hiểm
     */
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

        $minWage = $this->getRegionMinWage($region);

        $bhxhMultiplier = $this->cfgInt('insurance.bhxh_ceiling_multiplier', 20);
        $bhytMultiplier = $this->cfgInt('insurance.bhyt_ceiling_multiplier', 14);
        $bhxhCeiling = $minWage * $bhxhMultiplier;
        $bhytCeiling = $minWage * $bhytMultiplier;
        $bhtnCeiling = $bhxhCeiling;

        $bhxhBase = min($base, $bhxhCeiling);
        $bhytBase = min($base, $bhytCeiling);
        $bhtnBase = min($base, $bhtnCeiling);

        $bhxhRateEe = $this->cfgPercent('insurance.bhxh_ee', 0.08);
        $bhytRateEe = $this->cfgPercent('insurance.bhyt_ee', 0.015);
        $bhtnRateEe = $this->cfgPercent('insurance.bhtn_ee', 0.01);
        $bhxhRateEr = $this->cfgPercent('insurance.bhxh_er', 0.175);
        $bhytRateEr = $this->cfgPercent('insurance.bhyt_er', 0.03);
        $bhtnRateEr = $this->cfgPercent('insurance.bhtn_er', 0.01);

        $bhxhEe = round($bhxhBase * $bhxhRateEe);
        $bhytEe = round($bhytBase * $bhytRateEe);
        $bhtnEe = round($bhtnBase * $bhtnRateEe);

        $bhxhEr = round($bhxhBase * $bhxhRateEr);
        $bhytEr = round($bhytBase * $bhytRateEr);
        $bhtnEr = round($bhtnBase * $bhtnRateEr);

        return [
            'bhxh_ee' => $bhxhEe, 'bhyt_ee' => $bhytEe, 'bhtn_ee' => $bhtnEe,
            'bhxh_er' => $bhxhEr, 'bhyt_er' => $bhytEr, 'bhtn_er' => $bhtnEr,
            'total_ee' => $bhxhEe + $bhytEe + $bhtnEe,
            'total_er' => $bhxhEr + $bhytEr + $bhtnEr,
        ];
    }

    /**
     * Tính thuế TNCN theo biểu lũy tiến từng phần năm 2026.
     *
     * Nghiệp vụ: Tính thuế thu nhập cá nhân theo biểu lũy tiến từng phần.
     * Thuế TNCN = (Tổng thu nhập chịu thuế - Giảm trừ) * Thuế suất
     *
     * Công thức:
     *   Thu nhập chịu thuế = Gross - BHXH_e - BHYT_e - BHTN_e
     *   Thu nhập tính thuế = Thu nhập chịu thuế - Giảm trừ bản thân - Giảm trừ NPT
     *   Thuế TNCN = Tính theo từng bậc của thu nhập tính thuế
     *
     * Rủi ro: Sai số người phụ thuộc → sai thuế TNCN → bị phạt thuế.
     *
     * @param float $gross Lương gross (VNĐ)
     * @param float $insuranceEe Tổng bảo hiểm NLĐ phải đóng (VNĐ)
     * @param int $dependentCount Số người phụ thuộc
     * @return float Số thuế TNCN phải nộp (VNĐ)
     */
    public function calculateTax(float $gross, float $insuranceEe, int $dependentCount = 0): float
    {
        $personalDeduction = $this->cfgInt('pit.resident_deduction_monthly', 15500000);
        $dependentDeduction = $this->cfgInt('pit.dependent_deduction_monthly', 6200000);
        $taxableIncome = $gross - $insuranceEe - $personalDeduction - ($dependentCount * $dependentDeduction);
        if ($taxableIncome <= 0) return 0;

        $brackets = $this->cfgJson('pit.resident_brackets', [
            ['bound' => 20000000, 'rate' => 0.05, 'baseTax' => 0],
            ['bound' => 40000000, 'rate' => 0.10, 'baseTax' => 250000],
            ['bound' => 70000000, 'rate' => 0.15, 'baseTax' => 750000],
            ['bound' => 100000000, 'rate' => 0.20, 'baseTax' => 1950000],
            ['bound' => 9999999999, 'rate' => 0.25, 'baseTax' => 4750000],
        ]);

        $tax = 0;
        $remaining = $taxableIncome;
        $prevBound = 0;
        foreach ($brackets as $bracket) {
            if ($remaining <= 0) break;
            $bound = $bracket['bound'];
            $rate = $bracket['rate'];
            $bracketAmount = min($remaining, $bound - $prevBound);
            $tax += round($bracketAmount * $rate);
            $remaining -= $bracketAmount;
            $prevBound = $bound;
        }

        return $tax;
    }

    /**
     * Tính lương đầy đủ cho một nhân viên.
     *
     * Nghiệp vụ: Tính toán đầy đủ các khoản lương cho một nhân viên.
     * Đầu vào: Employee object + tùy chọn ghi đè.
     *
     * Lương net = Gross - BHXH_e - BHYT_e - BHTN_e - Thuế TNCN
     * Chi phí DN = Gross + BHXH_dn + BHYT_dn + BHTN_dn
     *
     * @param Employee $emp Đối tượng nhân viên
     * @param array $override Mảng ghi đè các tham số tính lương (gross_salary, insurance_salary, region, dependent_count, contract_type, working_days, allowances, deductions, overtime)
     * @return array{
     *   employee_id: string,
     *   employee_code: string,
     *   employee_name: string,
     *   department_id: ?string,
     *   contract_type: string,
     *   gross_salary: float,
     *   allowances: float,
     *   deductions: float,
     *   overtime: float,
     *   insurance_bhxh_ee: int,
     *   insurance_bhyt_ee: int,
     *   insurance_bhtn_ee: int,
     *   insurance_total_ee: int,
     *   insurance_bhxh_er: int,
     *   insurance_bhyt_er: int,
     *   insurance_bhtn_er: int,
     *   insurance_total_er: int,
     *   tax_amount: float,
     *   net_pay: float,
     *   total_cost: float,
     *   working_days: int
     * } Chi tiết lương nhân viên
     * @throws \InvalidArgumentException
     */
    public function calculateEmployeePay(Employee $emp, array $override = []): array
    {
        $gross = $override['gross_salary'] ?? $this->cfgInt('payroll.default_gross', 10000000);

        // Lay thong tin BH tu employee (hoac override)
        $insuranceSalary = $override['insurance_salary'] ?? $emp->getInsuranceSalary() ?? $gross;
        $region = $override['region'] ?? $emp->getRegion();
        $dependentCount = $override['dependent_count'] ?? $emp->getDependentCount() ?? 0;
        $contractType = $override['contract_type'] ?? $emp->getContractType() ?? 'indefinite';
        $workingDays = $override['working_days'] ?? $this->cfgInt('payroll.default_working_days', 26);
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

    /**
     * Tạo kỳ lương mới cho một tháng.
     *
     * Nghiệp vụ: Tạo kỳ lương với trạng thái "open" cho tháng/năm được chỉ định.
     * Ngày bắt đầu = ngày 01 của tháng, ngày kết thúc = ngày cuối tháng.
     *
     * @param string $yearMonth Mã kỳ lương định dạng YYYYMM (vd: "202606")
     * @param string|null $createdBy ID người tạo
     * @return PayrollPeriod Đối tượng kỳ lương đã tạo
     */
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

    /**
     * Xử lý bảng lương — tính toán cho tất cả nhân viên trong một kỳ.
     *
     * Nghiệp vụ: Tính toán bảng lương cho tất cả nhân viên trong một kỳ.
     * Tạo payroll_entry + payroll_details cho từng nhân viên.
     * Status ban đầu là "draft" — chờ duyệt.
     *
     * @param string $periodId ID kỳ lương
     * @param string|null $createdBy ID người tạo
     * @param array $employeeOverrides Mảng ghi đè tham số tính lương theo employee_id
     * @return PayrollEntry Đối tượng bảng lương đã tạo
     * @throws \InvalidArgumentException Nếu không tìm thấy kỳ lương hoặc kỳ lương không mở
     * @throws \RuntimeException Nếu không có nhân viên đang hoạt động
     */
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

    /**
     * Ghi nhận bút toán lương vào sổ cái qua JournalService.
     *
     * Nghiệp vụ: Ghi nhận bút toán lương vào sổ cái qua JournalService.
     *
     * Bút toán phát sinh:
     *   1. Chi phí lương: Nợ 641/642/622/627 / Có 334 (tổng gross)
     *   2. BHXH NLĐ: Nợ 334 / Có 3383
     *   3. BHYT NLĐ: Nợ 334 / Có 3384
     *   4. BHTN NLĐ: Nợ 334 / Có 3386
     *   5. BHXH DN: Nợ 641/642/622/627 / Có 3383
     *   6. BHYT DN: Nợ 641/642/622/627 / Có 3384
     *   7. BHTN DN: Nợ 641/642/622/627 / Có 3386
     *   8. Thuế TNCN: Nợ 334 / Có 3335
     *
     * Rủi ro: Nếu post thất bại, toàn bộ transaction rollback.
     * Không có trạng thái "post một nửa".
     *
     * Yêu cầu: JournalService phải được cấu hình trước khi gọi postPayroll().
     * Sai account code → nên để tài khoản mặc định có thể tùy chỉnh.
     *
     * @param string $entryId ID bảng lương
     * @param string $postedBy ID người thực hiện
     * @param array $accountOverrides Mảng ghi đè tài khoản kế toán (cost_account, payable_account, bhxh_payable, bhyt_payable, bhtn_payable, tax_payable)
     * @return array{entry_id: string, total_gross: float, total_insurance_er: float, total_tax: float, status: string} Kết quả hạch toán
     * @throws \RuntimeException Nếu JournalService hoặc PDO chưa được cấu hình
     * @throws \InvalidArgumentException Nếu không tìm thấy bảng lương hoặc trạng thái không hợp lệ
     * @throws \Exception Nếu có lỗi trong quá trình post (rollback tự động)
     */
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

        $costAccount = $accountOverrides['cost_account'] ?? $this->cfg('account.default_expense', '642');
        $payableAccount = $accountOverrides['payable_account'] ?? $this->cfg('account.default_payable', '334');
        $bhxhPayable = $accountOverrides['bhxh_payable'] ?? $this->cfg('account.payroll_bhxh_payable', '3383');
        $bhytPayable = $accountOverrides['bhyt_payable'] ?? $this->cfg('account.payroll_bhyt_payable', '3384');
        $bhtnPayable = $accountOverrides['bhtn_payable'] ?? $this->cfg('account.payroll_bhtn_payable', '3386');
        $taxPayable = $accountOverrides['tax_payable'] ?? $this->cfg('account.payroll_tax_payable', '3335');

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

    /**
     * Tách insurance tổng thành BHXH/BHYT/BHTN theo tỷ lệ mặc định.
     *
     * Khi payroll_detail chỉ có tổng insurance_ee/er, tách theo tỷ lệ 8:1.5:1.
     *
     * @param array $details Mảng chi tiết payroll (mỗi phần tử có insurance_ee, insurance_er)
     * @param float $totalEe Tổng bảo hiểm NLĐ
     * @return array{bhxh_ee: float, bhyt_ee: float, bhtn_ee: float, bhxh_er: float, bhyt_er: float, bhtn_er: float} Kết quả tách bảo hiểm
     */
    private function splitInsuranceDetails(array $details, float $totalEe): array
    {
        $bhxhEe = 0; $bhytEe = 0; $bhtnEe = 0;
        $bhxhEr = 0; $bhytEr = 0; $bhtnEr = 0;

        $bhxhRateEe = $this->cfgPercent('insurance.bhxh_ee', 0.08);
        $bhytRateEe = $this->cfgPercent('insurance.bhyt_ee', 0.015);
        $bhtnRateEe = $this->cfgPercent('insurance.bhtn_ee', 0.01);
        $totalRate = $bhxhRateEe + $bhytRateEe + $bhtnRateEe;
        $ratioBhxh = $totalRate > 0 ? $bhxhRateEe / $totalRate : 8 / 10.5;
        $ratioBhyt = $totalRate > 0 ? $bhytRateEe / $totalRate : 1.5 / 10.5;
        $ratioBhtn = $totalRate > 0 ? $bhtnRateEe / $totalRate : 1 / 10.5;

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

    /**
     * Phê duyệt bảng lương.
     *
     * Nghiệp vụ: Chuyển trạng thái bảng lương từ "draft" sang "approved".
     * Chỉ áp dụng cho bảng lương ở trạng thái nháp.
     *
     * @param string $entryId ID bảng lương
     * @param string $approvedBy ID người phê duyệt
     * @return PayrollEntry Đối tượng bảng lương đã phê duyệt
     * @throws \InvalidArgumentException Nếu không tìm thấy bảng lương hoặc không ở trạng thái nháp
     */
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

    /**
     * Đóng kỳ lương.
     *
     * Nghiệp vụ: Chuyển trạng thái kỳ lương từ "open" sang "closed".
     * Sau khi đóng, không thể tạo bảng lương mới cho kỳ này.
     *
     * @param string $periodId ID kỳ lương
     * @param string $closedBy ID người đóng
     * @return PayrollPeriod Đối tượng kỳ lương đã đóng
     * @throws \InvalidArgumentException Nếu không tìm thấy kỳ lương hoặc không ở trạng thái mở
     */
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

    /**
     * Điều chỉnh bảng lương đã post (bút toán bù trừ).
     *
     * Nghiệp vụ: Điều chỉnh bảng lương đã post (bút toán bù trừ).
     * Tạo một payroll_entry mới với số liệu điều chỉnh.
     * Chỉ áp dụng cho bảng lương đã post.
     *
     * @param string $originalEntryId ID bảng lương gốc
     * @param string $createdBy ID người tạo điều chỉnh
     * @param array $adjustments Mảng các điều chỉnh (mỗi phần tử chứa employee_id, gross_salary, allowances, deductions, insurance_ee, insurance_er, tax_amount, net_pay, overtime, total_cost, working_days, notes)
     * @return PayrollEntry Đối tượng bảng lương điều chỉnh
     * @throws \InvalidArgumentException Nếu không tìm thấy bảng lương gốc hoặc chưa ghi sổ
     */
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

    /**
     * Tìm bảng lương theo ID.
     *
     * @param string $id ID bảng lương
     * @return PayrollEntry|null Đối tượng bảng lương hoặc null nếu không tìm thấy
     */
    public function findEntryById(string $id): ?PayrollEntry
    {
        return $this->payrollEntryRepo->findById($id);
    }

    /**
     * Tìm kỳ lương theo ID.
     *
     * @param string $id ID kỳ lương
     * @return PayrollPeriod|null Đối tượng kỳ lương hoặc null nếu không tìm thấy
     */
    public function findPeriodById(string $id): ?PayrollPeriod
    {
        return $this->payrollPeriodRepo->findById($id);
    }

    /**
     * Lấy danh sách tất cả bảng lương.
     *
     * @return PayrollEntry[] Mảng các bảng lương
     */
    public function findAllEntries(): array
    {
        return $this->payrollEntryRepo->findAll();
    }

    /**
     * Lấy danh sách tất cả kỳ lương.
     *
     * @return PayrollPeriod[] Mảng các kỳ lương
     */
    public function findAllPeriods(): array
    {
        return $this->payrollPeriodRepo->findAll();
    }

    /**
     * Lấy chi tiết bảng lương theo ID bảng lương.
     *
     * @param string $entryId ID bảng lương
     * @return array Mảng các chi tiết bảng lương
     */
    public function findDetailsByEntry(string $entryId): array
    {
        return $this->payrollEntryRepo->findDetailsByEntry($entryId);
    }

    /**
     * Lấy danh sách kỳ lương đang mở.
     *
     * @return PayrollPeriod[] Mảng các kỳ lương đang mở
     */
    public function findOpenPeriods(): array
    {
        return $this->payrollPeriodRepo->findOpen();
    }

    /**
     * Nộp tiền BHXH, BHYT, BHTN cho cơ quan bảo hiểm xã hội.
     *
     * Nghiệp vụ: Nộp tiền BHXH, BHYT, BHTN cho cơ quan bảo hiểm xã hội.
     * Hạch toán: Nợ 3383 (BHXH, BHYT, BHTN phải nộp) / Có 111, 112 (tiền mặt, tiền gửi NH).
     * Yêu cầu: Chỉ thực hiện sau khi bảng lương đã ghi sổ.
     * Rủi ro: Nộp thiếu → phạt chậm nộp. Nộp thừa → dư 3383 bên Nợ → cần bù trừ kỳ sau.
     *
     * @param string $periodId ID kỳ lương
     * @param float $amount Số tiền nộp (VNĐ)
     * @param string $bankAccount Tài khoản ngân hàng hoặc tiền mặt (vd: "1111", "1121")
     * @param string $createdBy ID người thực hiện
     * @return array{transaction_id: string, amount: float, bank_account: string, status: string} Kết quả nộp BHXH
     * @throws \RuntimeException Nếu JournalService chưa được cấu hình
     * @throws \InvalidArgumentException Nếu số tiền <= 0
     */
    public function payInsurance(string $periodId, float $amount, string $bankAccount, string $createdBy): array
    {
        if (!$this->journalService) {
            throw new \RuntimeException('JournalService chua duoc cau hinh');
        }
        if ($amount <= 0) {
            throw new \InvalidArgumentException('So tien nop BHXH phai lon hon 0');
        }
        $txn = $this->journalService->postEntry(
            "Nop BHXH ky {$periodId}",
            '',
            [
                ['account_code' => '3383', 'is_debit' => true, 'amount' => $amount],
                ['account_code' => $bankAccount, 'is_debit' => false, 'amount' => $amount],
            ],
            $createdBy, false, 'payroll', null, 'PMT', 'payroll'
        );
        $this->auditLogger?->log('payroll.insurance.pay', 'payroll_insurance', "period:{$periodId}",
            null, ['amount' => $amount, 'bank_account' => $bankAccount, 'transaction_id' => $txn->getId()], $createdBy);
        return [
            'transaction_id' => $txn->getId(),
            'amount' => $amount,
            'bank_account' => $bankAccount,
            'status' => 'posted',
        ];
    }
}
