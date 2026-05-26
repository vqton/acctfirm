<?php
// Test: Module Tien luong — PayrollService
spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

use Accounting\Domain\Service\PayrollService;
use Accounting\Domain\Service\JournalService;
use Accounting\Domain\Model\Employee;
use Accounting\Domain\Repository\EmployeeRepositoryInterface;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;
use Accounting\Infrastructure\Persistence\PDOPayrollEntryRepository;
use Accounting\Infrastructure\Persistence\PDOPayrollPeriodRepository;
use Accounting\Infrastructure\Persistence\PDOSalaryComponentRepository;
use Accounting\Infrastructure\Persistence\PDOEmployeeRepository;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$payrollEntryRepo = new PDOPayrollEntryRepository($pdo);
$payrollPeriodRepo = new PDOPayrollPeriodRepository($pdo);
$salaryComponentRepo = new PDOSalaryComponentRepository($pdo);
$employeeRepo = new PDOEmployeeRepository($pdo);

$journal = new JournalService($accountRepo, $txnRepo, $pdo);

$payroll = new PayrollService(
    $payrollEntryRepo, $payrollPeriodRepo,
    $salaryComponentRepo, $employeeRepo,
    $journal, $pdo
);

// Xoa du lieu cu
$pdo->exec('DELETE FROM payroll_detail_lines');
$pdo->exec('DELETE FROM payroll_details');
$pdo->exec('DELETE FROM payroll_entries');
$pdo->exec('DELETE FROM payroll_periods');
$pdo->exec('DELETE FROM employees');
$pdo->exec('DELETE FROM ledger_entries');
$pdo->exec('DELETE FROM transactions');
$pdo->exec('UPDATE accounts SET balance = 0');

// Tao nhan vien mau trong DB
$emp1 = new Employee('emp_1', 'NV001', 'Nguyen Van A', 'dept_1', 'Nhan vien',
    '0901111111', 'a@test.com', 15000000, '123456789', 'Vietcombank',
    'MST001', 2, 'I', 'indefinite');
$emp2 = new Employee('emp_2', 'NV002', 'Tran Thi B', 'dept_2', 'Truong phong',
    '0902222222', 'b@test.com', 30000000, '987654321', 'Techcombank',
    'MST002', 1, 'I', 'definite_12');
$emp3 = new Employee('emp_3', 'NV003', 'Le Van C', 'dept_1', 'Tho vu',
    null, null, 5000000, null, null, null, 0, 'III', 'seasonal');
$employeeRepo->save($emp1);
$employeeRepo->save($emp2);
$employeeRepo->save($emp3);

$failed = 0; $total = 0;
function assertEq($a, $b, $m) { global $total, $failed;
    $total++; if ((string)$a !== (string)$b) { echo "FAIL: {$m}\n  expected: " . var_export($b,true) . "\n  actual:   " . var_export($a,true) . "\n"; $failed++; }
    else echo "PASS: {$m}\n";
}
function assertTrue($c, $m) { global $total, $failed;
    $total++; if (!$c) { echo "FAIL: {$m}\n"; $failed++; } else echo "PASS: {$m}\n";
}
function assertFloat($a, $b, $m, $tol = 1) { global $total, $failed;
    $total++; if (abs((float)$a - (float)$b) > $tol) { echo "FAIL: {$m}\n  expected ~{$b}, got {$a}\n"; $failed++; }
    else echo "PASS: {$m}\n";
}

// === TEST 1: Tinh BH ===
echo "\n=== Test 1: calculateInsurance ===\n";
$ins1 = $payroll->calculateInsurance(15000000, 15000000, 'I');
assertFloat($ins1['bhxh_ee'], 1200000, 'BHXH EE 8% = 1,200,000');
assertFloat($ins1['bhyt_ee'], 225000, 'BHYT EE 1.5% = 225,000');
assertFloat($ins1['bhtn_ee'], 150000, 'BHTN EE 1% = 150,000');
assertFloat($ins1['total_ee'], 1575000, 'Total EE = 1,575,000');
assertFloat($ins1['bhxh_er'], 2625000, 'BHXH ER 17.5% = 2,625,000');
assertFloat($ins1['bhyt_er'], 450000, 'BHYT ER 3% = 450,000');
assertFloat($ins1['bhtn_er'], 150000, 'BHTN ER 1% = 150,000');
assertFloat($ins1['total_er'], 3225000, 'Total ER = 3,225,000');

// Test tran BH (insurance_salary > tran)
echo "\n=== Test 2: Insurance ceiling ===\n";
$ins2 = $payroll->calculateInsurance(80000000, 80000000, 'I');
assertFloat($ins2['bhxh_ee'], 6400000, 'BHXH EE = 6,400,000 (under ceiling)');

$ins3 = $payroll->calculateInsurance(120000000, 120000000, 'I');
assertFloat($ins3['bhxh_ee'], 7936000, 'BHXH EE ceiling = 7,936,000 (99.2tr * 8%)');

echo "\n=== Test 3: Insurance salary != gross ===\n";
$ins4 = $payroll->calculateInsurance(20000000, 10000000, 'I');
assertFloat($ins4['bhxh_ee'], 800000, 'BHXH EE based on insurance_salary=10tr');

$ins5 = $payroll->calculateInsurance(0, 0, 'I');
assertFloat($ins5['total_ee'], 0, 'No insurance when gross=0');

// === TEST 4: Tinh thue TNCN ===
echo "\n=== Test 5: calculateTax ===\n";
$tax1 = $payroll->calculateTax(20000000, 1575000, 2);
assertFloat($tax1, 0, 'Tax=0 when taxable income < 0');

$tax2 = $payroll->calculateTax(30000000, 3150000, 1);
assertFloat($tax2, 257500, 'Tax with 1 dependent = 257,500');

$tax3 = $payroll->calculateTax(150000000, 0, 0);
assertFloat($tax3, 22125000, 'PIT 5 brackets = 22,125,000', 1000);

// === TEST 5: Tinh luong cho nhan vien ===
echo "\n=== Test 6: calculateEmployeePay ===\n";
$result1 = $payroll->calculateEmployeePay($emp1);
assertEq($result1['employee_id'], 'emp_1', 'Correct employee');
assertFloat($result1['gross_salary'], 10000000, 'Default gross = 10tr');
// Insurance EE = 10tr * 10.5% = 1,050,000 (tinh tren insurance_salary=15tr, co tran)
assertFloat($result1['insurance_total_ee'], 1575000, 'Insurance EE = 1,575,000 (based on insurance_salary=15tr)');
assertFloat($result1['tax_amount'], 0, 'Tax = 0 (under personal deduction)');
assertFloat($result1['net_pay'], 8425000, 'Net = 10tr - 1.575tr = 8,425,000');

$result2 = $payroll->calculateEmployeePay($emp1, [
    'gross_salary' => 50000000,
    'allowances' => 5000000,
    'deductions' => 1000000,
    'overtime' => 2000000,
]);
assertFloat($result2['gross_salary'], 50000000, 'Override gross');
assertFloat($result2['allowances'], 5000000, 'Override allowances');

// === TEST 6: Tao ky luong ===
echo "\n=== Test 7: createPayrollPeriod ===\n";
$period = $payroll->createPayrollPeriod('202606', 'tester');
assertTrue($period->getId() !== '', 'Period ID generated');
assertEq($period->getPeriodCode(), '202606', 'Period code = 202606');
assertEq($period->getStatus(), 'open', 'Period status = open');

$period2 = $payroll->createPayrollPeriod('202605', 'tester');
assertEq($period2->getPeriodCode(), '202605', 'Period code = 202605');

// === TEST 7: Xu ly bang luong ===
echo "\n=== Test 8: processPayroll ===\n";
$entry = $payroll->processPayroll($period->getId(), 'tester');
assertEq($entry->getStatus(), 'draft', 'Entry status = draft');
assertEq($entry->getTotalEmployees(), 3, '3 employees processed');

// Kiem tra tong
assertTrue($entry->getTotalGross() > 0, 'Total gross > 0');
assertTrue($entry->getTotalNet() > 0, 'Total net > 0');
assertEq($entry->getTotalCost(), $entry->getTotalGross() + $entry->getTotalInsuranceEr(), 'Total cost = gross + insurance_er');

// Kiem tra chi tiet
$details = $payroll->findDetailsByEntry($entry->getId());
assertEq(count($details), 3, '3 detail records');

// === TEST 8: Duyet bang luong ===
echo "\n=== Test 9: approvePayroll ===\n";
$approved = $payroll->approvePayroll($entry->getId(), 'ke_toan_truong');
assertEq($approved->getStatus(), 'approved', 'Entry approved');

// === TEST 9: Post but toan ===
echo "\n=== Test 10: postPayroll ===\n";
$result = $payroll->postPayroll($entry->getId(), 'ke_toan_truong');
assertEq($result['status'], 'posted', 'Post status = posted');
assertTrue($result['total_gross'] > 0, 'Total gross posted');

// Kiem tra trang thai entry
$postedEntry = $payroll->findEntryById($entry->getId());
assertEq($postedEntry->getStatus(), 'posted', 'Entry status = posted');

// Kiem tra trial balance
$allAccts = $accountRepo->findAll();
$totalDr = 0; $totalCr = 0;
foreach ($allAccts as $a) {
    $bal = $a->getBalance();
    if (abs($bal) < 1) continue;
    if (in_array($a->getType(), ['asset', 'expense'])) $totalDr += $bal;
    else $totalCr += $bal;
}
assertFloat($totalDr, $totalCr, 'Trial balance Dr = Cr after payroll post', 100);

// === TEST 10: Post that bai khi thieu JournalService ===
echo "\n=== Test 11: Post without JournalService fails ===\n";
$payrollNoJournal = new PayrollService(
    $payrollEntryRepo, $payrollPeriodRepo,
    $salaryComponentRepo, $employeeRepo
);
$entry2 = $payroll->processPayroll($period2->getId(), 'tester');
try {
    $payrollNoJournal->postPayroll($entry2->getId(), 'tester');
    echo "FAIL: Expected RuntimeException when no JournalService\n";
    $failed++;
} catch (\RuntimeException $e) {
    assertTrue(true, 'No JournalService throws RuntimeException');
}

// === TEST 11: Dieu chinh bang luong ===
echo "\n=== Test 12: adjustPayroll ===\n";
$adjEntry = $payroll->adjustPayroll($entry->getId(), 'ke_toan_truong', [
    [
        'employee_id' => 'emp_1',
        'gross_salary' => 2000000,
        'notes' => 'Dieu chinh tang luong NV A',
    ]
]);
assertEq($adjEntry->getStatus(), 'draft', 'Adjustment entry is draft');

// === TEST 12: Dong ky luong ===
echo "\n=== Test 13: closePayroll ===\n";
$closed = $payroll->closePayroll($period->getId(), 'ke_toan_truong');
assertEq($closed->getStatus(), 'closed', 'Period status = closed');

// Dong ky da dong => loi
try {
    $payroll->closePayroll($period->getId(), 'tester');
    echo "FAIL: Expected exception on closing already-closed period\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Close already-closed period throws exception');
}

// === TEST 13: Loi xu ly ===
echo "\n=== Test 14: Error handling ===\n";
try {
    $payroll->processPayroll('invalid_period', 'tester');
    echo "FAIL: Expected exception for invalid period\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Invalid period ID throws exception');
}

try {
    $payroll->approvePayroll('nonexistent', 'tester');
    echo "FAIL: Expected exception for nonexistent entry\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Nonexistent entry throws exception');
}

try {
    $payroll->postPayroll('nonexistent', 'tester');
    echo "FAIL: Expected exception for nonexistent entry\n";
    $failed++;
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Post nonexistent throws exception');
}

echo "\n=== KET QUA: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);
