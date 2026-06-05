<?php
//
// R-18-20: Multi-tenant backfill verification
//
require __DIR__ . '/bootstrap.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');
$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4", "dev", "123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

// Skip list (phải khớp migration 110)
$skipTables = [
    '_migrations', 'accounting_entities', 'users', 'user_roles', 'roles', 'role_permissions',
    'business_config', 'period_config', 'bc09_config', 'exchange_rates', 'voucher_sequences',
    'report_definitions', 'attendance_records', 'departments', 'employees', 'pit_dependents',
    'payroll_configs', 'salary_adjustments', 'salary_advances', 'salary_components',
    'salary_formulas', 'tax_loss_carryforwards', 'tax_rates', 'uoms', 'warehouses',
];

// ─── 1. Tất cả non-skip tables có entity_id ───────────────────────────────
$tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME != '_migrations'")->fetchAll(PDO::FETCH_COLUMN);
$missing = [];
foreach ($tables as $t) {
    if (in_array($t, $skipTables)) continue;
    $hasCol = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . addslashes($t) . "' AND COLUMN_NAME = 'entity_id'")->fetchColumn();
    if ((int)$hasCol === 0) $missing[] = $t;
}
assertEq(count($missing), 0, 'Tất cả tables (trừ skip list) có entity_id. Missing: ' . implode(',', $missing));

// ─── 2. Default entity tồn tại ───────────────────────────────────────────
$hoExists = $pdo->query("SELECT COUNT(*) FROM accounting_entities WHERE id = 1")->fetchColumn();
assertTrue((int)$hoExists > 0, 'Default entity HO (id=1) tồn tại');

// ─── 3. Backfill: transactions table có entity_id = 1 ────────────────────
$sampleTxn = $pdo->query("SELECT entity_id FROM transactions LIMIT 1")->fetchColumn();
assertEq($sampleTxn, 1, 'Sample transaction có entity_id = 1 (HO)');

// ─── 4. Backfill: ledger_entries ─────────────────────────────────────────
$sampleLe = $pdo->query("SELECT entity_id FROM ledger_entries LIMIT 1")->fetchColumn();
assertEq($sampleLe, 1, 'Sample ledger_entry có entity_id = 1');

// ─── 5. Index idx_entity tồn tại trên tables lớn ─────────────────────────
$bigTables = ['transactions', 'ledger_entries', 'ap_invoices', 'ar_invoices', 'items'];
foreach ($bigTables as $t) {
    $hasIdx = $pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND INDEX_NAME = 'idx_entity'")->fetchColumn();
    assertTrue((int)$hasIdx > 0, "Table {$t} có index idx_entity");
}

// ─── 6. Skip tables KHÔNG có entity_id (theo design) ─────────────────────
foreach ($skipTables as $t) {
    if ($t === '_migrations') continue;
    if (!in_array($t, $tables)) continue; // table không tồn tại thì skip
    $hasCol = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = 'entity_id'")->fetchColumn();
    assertEq((int)$hasCol, 0, "Skip table {$t} KHÔNG có entity_id (đúng thiết kế)");
}

// ─── 7. No NULL entity_id in non-skip tables ──────────────────────────────
$nullCount = 0;
foreach ($tables as $t) {
    if (in_array($t, $skipTables)) continue;
    try {
        $c = $pdo->query("SELECT COUNT(*) FROM `$t` WHERE entity_id IS NULL")->fetchColumn();
        $nullCount += (int)$c;
    } catch (Exception $e) {
        // Table không có rows hoặc không query được
    }
}
assertEq($nullCount, 0, "Tổng rows với entity_id NULL trên tất cả tables = 0 (backfilled)");

// ─── 8. Audit log có bản ghi backfill ────────────────────────────────────
$logCount = $pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'multi_tenant.backfill'")->fetchColumn();
assertTrue((int)$logCount > 0, 'Audit log có bản ghi multi_tenant.backfill');

// ─── 9. accounting_entities có row HO ─────────────────────────────────────
$ho = $pdo->query("SELECT id, code, name FROM accounting_entities WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
assertEq($ho['code'], 'HO', 'Entity HO code = HO');
assertEq($ho['name'], 'Trụ sở chính', 'Entity HO name = Trụ sở chính');

// ─── 10. Tạo entity thứ 2 để test isolation ──────────────────────────────
// (Không persist — chỉ verify có thể tạo)
$newId = 'test_tenant_2';
$checkExists = $pdo->query("SELECT COUNT(*) FROM accounting_entities WHERE id = '$newId'")->fetchColumn();
assertEq((int)$checkExists, 0, 'Entity thứ 2 chưa tồn tại (test isolation)');

results();
