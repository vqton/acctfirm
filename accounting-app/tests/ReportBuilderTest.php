<?php
require __DIR__ . '/bootstrap.php';
use Accounting\Domain\Service\ReportBuilderService;
use Accounting\Domain\Service\ReportExportService;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4","dev","123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$export = new ReportExportService();
$service = new ReportBuilderService($pdo, $export);

// Cleanup
$pdo->exec("DELETE FROM report_definitions WHERE created_by = 'test_user'");

// === TEST 1: Get available tables ===
$tables = $service->getAvailableTables();
assertTrue(count($tables) >= 8, 'At least 8 tables available');
assertTrue(in_array('transactions', array_column($tables, 'table')), 'Contains transactions table');
assertTrue(in_array('accounts', array_column($tables, 'table')), 'Contains accounts table');

// === TEST 2: Save report definition ===
$id = $service->saveReport([
    'name' => 'Test Report',
    'type' => 'list',
    'source_table' => 'transactions',
    'fields' => ['id', 'transaction_date', 'description', 'amount'],
    'created_by' => 'test_user',
]);
assertTrue(strlen($id) > 0, 'Report saved with ID');

// === TEST 3: Get saved reports ===
$reports = $service->getSavedReports('test_user');
assertTrue(count($reports) >= 1, 'Returns saved reports');

// === TEST 4: Get report definition ===
$def = $service->getReportDefinition($id);
assertEq($def['name'], 'Test Report', 'Definition loaded');
assertEq($def['source_table'], 'transactions', 'Correct source table');

// === TEST 5: Execute report ===
$data = $service->executeReport($def);
assertTrue(is_array($data), 'Execution returns array');
assertTrue(isset($data[0]['id']) || empty($data), 'Has id field or empty result');

// === TEST 6: Execute with filter ===
$def['filters'] = [['field' => 'transaction_date', 'operator' => '>', 'value' => '2020-01-01']];
$data = $service->executeReport($def);
assertTrue(is_array($data), 'Filtered execution works');

// === TEST 7: Execute with LIKE filter ===
$def['filters'] = [['field' => 'description', 'operator' => 'LIKE', 'value' => 'test']];
$data = $service->executeReport($def);
assertTrue(is_array($data), 'LIKE filter works');

// === TEST 8: Execute with sort ===
$def['sort_config'] = ['field' => 'transaction_date', 'direction' => 'DESC'];
$data = $service->executeReport($def);
assertTrue(is_array($data), 'Sorted execution works');

// === TEST 9: Execute with group by ===
$def2 = $service->getReportDefinition($id);
$def2['fields'] = ['source_module', 'count(*) as cnt'];
$def2['group_by'] = 'source_module';
$data = $service->executeReport($def2);
assertTrue(is_array($data), 'Group by works');

// === TEST 10: Execute with IN filter ===
$def['filters'] = [['field' => 'id', 'operator' => 'IN', 'value' => '1,2,3']];
$data = $service->executeReport($def);
assertTrue(is_array($data), 'IN filter works');

// === TEST 11: Export ===
$result = $service->executeAndExport($def, 'csv');
assertTrue(isset($result['content']), 'Export has content');
assertTrue(isset($result['mime']), 'Export has mime type');

// === TEST 12: Save with filter and chart ===
$id2 = $service->saveReport([
    'name' => 'Chart Report',
    'type' => 'chart',
    'source_table' => 'ledger_entries',
    'fields' => ['account_id', 'amount'],
    'chart_type' => 'bar',
    'chart_config' => ['title' => 'Test Chart'],
    'group_by' => 'account_id',
    'created_by' => 'test_user',
]);
assertTrue(strlen($id2) > 0, 'Chart report saved');

// === TEST 13: Delete report ===
$service->deleteReport($id2);
$reports = $service->getSavedReports('test_user');
$found = array_filter($reports, fn($r) => $r['id'] === $id2);
assertEq(count($found), 0, 'Deleted report not found');

// === TEST 14: Reject invalid table ===
try {
    $service->executeReport(['source_table' => 'nonexistent', 'fields' => ['*']]);
    assertTrue(false, 'Should throw on invalid table');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Throws on invalid table');
}

// === TEST 15: Get reports for different user (public filter) ===
$id3 = $service->saveReport([
    'name' => 'Public Report',
    'source_table' => 'accounts',
    'fields' => ['code', 'name'],
    'is_public' => 1,
    'created_by' => 'other_user',
]);
$reports = $service->getSavedReports('test_user');
$found = array_filter($reports, fn($r) => $r['id'] === $id3);
assertTrue(count($found) >= 1, 'Public reports visible to other users');

// Cleanup
$pdo->exec("DELETE FROM report_definitions WHERE created_by IN ('test_user','other_user')");

results();
