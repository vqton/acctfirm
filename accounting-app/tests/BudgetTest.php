<?php
require __DIR__ . '/bootstrap.php';
use Accounting\Domain\Service\BudgetService;
use Accounting\Domain\Service\ReportExportService;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$export = new ReportExportService();
$service = new BudgetService($pdo, $export);

// Cleanup
$pdo->exec("DELETE FROM budget_plans WHERE scenario_id LIKE 'test_%'");
$pdo->exec("DELETE FROM budget_scenarios WHERE id LIKE 'test_%'");

// === TEST 1: Create scenario ===
$s = $service->createScenario('Test Budget', 2026, 'operating', 'Test', 'admin');
assertTrue(strlen($s['id']) > 0, 'Scenario created');
assertEq($s['year'], 2026, 'Scenario year = 2026');

// === TEST 2: Get scenarios ===
$scenarios = $service->getScenarios(2026);
assertTrue(count($scenarios) >= 1, 'Scenarios found for 2026');

// === TEST 3: Activate scenario ===
$service->activateScenario($s['id']);
$scenarios = $service->getScenarios(2026);
$active = array_filter($scenarios, fn($x) => $x['id'] === $s['id']);
$active = reset($active);
assertEq($active['status'], 'active', 'Scenario activated');

// === TEST 4: Set budget lines ===
$service->setBudget($s['id'], '2026-01', '511', 100000000, 'DT tháng 1');
$service->setBudget($s['id'], '2026-01', '632', 60000000, 'GV tháng 1');
$service->setBudget($s['id'], '2026-02', '511', 120000000, 'DT tháng 2');

// === TEST 5: Get budget lines ===
$lines = $service->getBudgetLines($s['id']);
assertEq(count($lines), 3, '3 budget lines');

// === TEST 6: Summary ===
$summary = $service->getSummary($s['id']);
assertTrue($summary['total_lines'] >= 3, 'Summary has 3+ lines');
assertTrue($summary['total_budget'] > 0, 'Summary has budget');

// === TEST 7: Variance report (no actual data yet) ===
$variance = $service->getVarianceReport($s['id']);
assertTrue(count($variance) >= 3, 'Variance has 3+ rows');
assertEq((float)$variance[0]['budget_amount'] > 0, true, 'Variance has budget values');
assertEq((int)$variance[0]['actual_debit'], 0, 'Variance actual = 0 (no data)');

// === TEST 8: Dashboard ===
$dash = $service->getDashboard(2026);
assertTrue(isset($dash['scenarios']), 'Dashboard has scenarios');
assertTrue(isset($dash['summary_by_month']), 'Dashboard has monthly summary');

// === TEST 9: Export ===
$result = $service->exportVarianceReport($s['id']);
assertTrue(isset($result['content']), 'Export has content');

// Cleanup
$pdo->exec("DELETE FROM budget_plans WHERE scenario_id = '{$s['id']}'");
$pdo->exec("DELETE FROM budget_scenarios WHERE id = '{$s['id']}'");

results();
