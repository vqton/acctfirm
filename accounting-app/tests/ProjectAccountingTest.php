<?php
require __DIR__ . '/bootstrap.php';
use Accounting\Domain\Service\ProjectAccountingService;
use Accounting\Domain\Model\Project;
use Accounting\Infrastructure\Persistence\PDOProjectRepository;
use Accounting\Infrastructure\Persistence\PDOAccountRepository;
use Accounting\Domain\Service\ReportExportService;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$projectRepo = new PDOProjectRepository($pdo);
$accountRepo = new PDOAccountRepository($pdo);
$exportService = new ReportExportService();
$service = new ProjectAccountingService($projectRepo, $pdo, $exportService);

// Cleanup
$pdo->exec("DELETE FROM ledger_entries WHERE project_id LIKE 'test_%'");
$pdo->exec("DELETE FROM project_progress_billing WHERE project_id LIKE 'test_%'");
$pdo->exec("DELETE FROM project_budgets WHERE project_id LIKE 'test_%'");
$pdo->exec("DELETE FROM projects WHERE id LIKE 'test_%'");

// Seed test project
$p = new Project('test_proj_1', 'P001', 'Dự án Test', 'CUS001', '2026-01-01', '2026-12-31', 100000000, 'Test notes');
$projectRepo->save($p);

// === TEST 1: Dashboard stats ===
$stats = $service->getDashboardStats();
assertEq($stats['total'] > 0, true, 'Dashboard has total');

// === TEST 2: Progress billing ===
$bid = $service->createProgressBilling('test_proj_1', '2026-06-15', 50000000, 50, 'Đợt 1', 'admin');
assertTrue(strlen($bid) > 0, 'Billing ID created');

$billings = $projectRepo->getProgressBillings('test_proj_1');
assertEq(count($billings), 1, 'One billing record');

// === TEST 3: Set budget line ===
$service->setBudgetLine('test_proj_1', '621', 30000000, 'Nguyên vật liệu');
$service->setBudgetLine('test_proj_1', '622', 20000000, 'Nhân công');
$budgets = $projectRepo->getProjectBudgets('test_proj_1');
assertEq(count($budgets), 2, 'Two budget lines');

// === TEST 4: Recognize revenue (POC) ===
$revenue = $service->recognizeRevenue('test_proj_1', 'admin');
assertEq($revenue >= 0, true, 'Revenue recognized');
$project = $projectRepo->findById('test_proj_1');
assertEq($project->getRevenueRecognized(), $revenue, 'Revenue saved to project');

// === TEST 5: Get project report ===
$report = $service->getProjectReport('test_proj_1');
assertTrue(isset($report['project']), 'Report has project');
assertTrue(isset($report['cost_summary']), 'Report has cost summary');
assertTrue(isset($report['billings']), 'Report has billings');

// === TEST 6: Finalize project ===
$service->finalizeProject('test_proj_1');
$finalized = $projectRepo->findById('test_proj_1');
assertEq($finalized->getStatus(), 'completed', 'Project finalized');
assertEq((int)$finalized->getEstimatedCompletionPct(), 100, 'Completion pct = 100');

// === TEST 7: Error on finalize already-completed ===
try {
    $service->finalizeProject('test_proj_1');
    assertTrue(false, 'Should throw for completed project');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Correctly rejects finalized project');
}

// === TEST 8: Error on allocate nonexistent ===
try {
    $service->allocateCost('test_proj_1', 'non_existent_txn', '621', 100000, true);
    assertTrue(false, 'Should throw for nonexistent txn');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Correctly rejects nonexistent transaction');
}

// === TEST 9: Export ===
$result = $service->exportProjectReport('csv', 'test_proj_1');
assertTrue(isset($result['content']), 'Export has content');
assertTrue(strlen($result['content']) > 0, 'Export content non-empty');

// === TEST 10: Allocate cost via ledger_entries ===
// Need to re-create since it was finalized
$p2 = new Project('test_proj_2', 'P002', 'Dự án Test 2', 'CUS002', '2026-01-01');
$projectRepo->save($p2);

$txnId = 'test_txn_proj_' . uniqid();
$acct = $accountRepo->findByCode('621');
assertTrue($acct !== null, 'Account 621 exists');
$ledgerId = 'test_ledger_' . uniqid();
$pdo->prepare("INSERT INTO transactions (id,`date`,transaction_date,description,reference,status,created_by) VALUES (?,NOW(),CURDATE(),?,?,'posted','admin')")
    ->execute([$txnId, 'Test transaction', 'TEST']);
$pdo->prepare("INSERT INTO ledger_entries (id,transaction_id,account_id,amount,is_debit) VALUES (?,?,?,1000000,1)")
    ->execute([$ledgerId, $txnId, $acct->getId()]);

$service->allocateCost('test_proj_2', $txnId, '621', 1000000, true);

$le = $pdo->prepare("SELECT project_id FROM ledger_entries WHERE id = ?");
$le->execute([$ledgerId]);
$row = $le->fetch(PDO::FETCH_ASSOC);
assertEq($row['project_id'], 'test_proj_2', 'Ledger entry project_id set');

// === TEST 11: Cost summary shows account ===
$summary = $projectRepo->getCostSummary('test_proj_2');
assertEq(count($summary) > 0, true, 'Cost summary has data');

// Cleanup
$pdo->exec("DELETE FROM ledger_entries WHERE id = '$ledgerId'");
$pdo->exec("DELETE FROM transactions WHERE id = '$txnId'");
$pdo->exec("DELETE FROM ledger_entries WHERE project_id LIKE 'test_%'");
$pdo->exec("DELETE FROM project_progress_billing WHERE project_id LIKE 'test_%'");
$pdo->exec("DELETE FROM project_budgets WHERE project_id LIKE 'test_%'");
$pdo->exec("DELETE FROM projects WHERE id LIKE 'test_%'");

results();
