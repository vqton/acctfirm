<?php
// Test: DashboardService — KPI aggregation từ nhiều nguồn
require_once __DIR__ . '/bootstrap.php';

use Accounting\Domain\Service\DashboardService;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Infrastructure\Persistence\PDOTransactionRepository;

$pdo = new PDO('mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4', 'dev', '123456', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$accountRepo = new PDOAccountRepository($pdo);
$txnRepo = new PDOTransactionRepository($pdo);
$svc = new DashboardService($accountRepo, $txnRepo, $pdo);

$kpi = $svc->getKPIs();

// === KIỂM TRA CẤU TRÚC ===
$requiredKeys = ['cash_balance','bank_balance','total_cash','today_receipts','today_payments',
    'revenue_ytd','expense_ytd','profit_ytd','pending_approvals','total_transactions',
    'status_breakdown','trend','current_period','recent_transactions','trial_balance'];
foreach ($requiredKeys as $k) {
    assertTrue(array_key_exists($k, $kpi), "Key tồn tại: {$k}");
}

// === TIỀN MẶT & NGÂN HÀNG ===
assertTrue($kpi['cash_balance'] >= 0, 'cash_balance >= 0');
assertTrue($kpi['bank_balance'] >= 0, 'bank_balance >= 0');
assertEq($kpi['total_cash'], $kpi['cash_balance'] + $kpi['bank_balance'], 'total_cash = cash + bank');

// === THU/CHI HÔM NAY ===
assertTrue($kpi['today_receipts'] >= 0, 'today_receipts >= 0');
assertTrue($kpi['today_payments'] >= 0, 'today_payments >= 0');

// === DOANH THU / CHI PHÍ YTD ===
assertTrue($kpi['revenue_ytd'] >= 0, 'revenue_ytd >= 0');
assertTrue($kpi['expense_ytd'] >= 0, 'expense_ytd >= 0');
assertEq($kpi['profit_ytd'], $kpi['revenue_ytd'] - $kpi['expense_ytd'], 'profit_ytd = revenue - expense');

// === CHỜ DUYỆT ===
assertTrue($kpi['pending_approvals'] >= 0, 'pending_approvals >= 0');
assertTrue($kpi['total_transactions'] > 0, 'total_transactions > 0');

// === TRẠNG THÁI GIAO DỊCH ===
assertTrue(is_array($kpi['status_breakdown']), 'status_breakdown là array');
$knownStatuses = ['draft','submitted','approved','posted','reversed'];
$cnt = 0;
foreach ($knownStatuses as $s) {
    if (isset($kpi['status_breakdown'][$s])) $cnt++;
}
assertTrue($cnt > 0, 'Có ít nhất 1 trạng thái');

// === DÒNG TIỀN 7 NGÀY ===
assertEq(count($kpi['trend']), 7, 'trend có 7 ngày');
foreach ($kpi['trend'] as $day) {
    assertTrue(isset($day['date']), 'trend item có date');
    assertTrue(isset($day['receipts']), 'trend item có receipts');
    assertTrue(isset($day['payments']), 'trend item có payments');
}

// === KỲ HIỆN TẠI ===
if ($kpi['current_period']) {
    assertTrue(isset($kpi['current_period']['name']), 'current_period có name');
    assertTrue(isset($kpi['current_period']['status']), 'current_period có status');
}

// === GIAO DỊCH GẦN ĐÂY ===
assertTrue(count($kpi['recent_transactions']) <= 5, 'recent_transactions <= 5');
foreach ($kpi['recent_transactions'] as $txn) {
    assertTrue(isset($txn['id']), 'recent txn có id');
    assertTrue(isset($txn['reference']), 'recent txn có reference');
    assertTrue(isset($txn['status']), 'recent txn có status');
}

// === TRIAL BALANCE ===
assertTrue(isset($kpi['trial_balance']['total_dr']), 'trial_balance có total_dr');
assertTrue(isset($kpi['trial_balance']['total_cr']), 'trial_balance có total_cr');
assertTrue(isset($kpi['trial_balance']['balanced']), 'trial_balance có balanced');
assertTrue($kpi['trial_balance']['total_dr'] > 0, 'total_dr > 0');
assertTrue($kpi['trial_balance']['total_cr'] > 0, 'total_cr > 0');
assertNear($kpi['trial_balance']['total_dr'], $kpi['trial_balance']['total_cr'], 'Dr ≈ Cr (trial balance)');
assertTrue($kpi['trial_balance']['balanced'], 'Trial balance is balanced');

// === KIỂM TRA NHANH GIÁ TRỊ CỤ THỂ ===
// Kiểm tra khớp với DB (sau khi các test khác đã tạo giao dịch)
$actualCash = (float)$pdo->query("SELECT balance FROM accounts WHERE code='111'")->fetchColumn();
assertNear($kpi['cash_balance'], $actualCash, "cash_balance khớp DB (code=111: {$actualCash})");
$actualBank = (float)$pdo->query("SELECT balance FROM accounts WHERE code='112'")->fetchColumn();
assertNear($kpi['bank_balance'], $actualBank, "bank_balance khớp DB (code=112: {$actualBank})");

results();
