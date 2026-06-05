<?php
//
// R-18-20: Multi-Tenant Backfill (ADR-012 Pattern B)
//
// Context: ADR-012 chọn multi-tenant single DB với column `entity_id` (không clone blank DB).
// Trước migration: 121 tables, 2 có entity_id, 119 KHÔNG có.
// Migration này thêm entity_id vào 119 tables + backfill về entity 1 (HO - Trụ sở chính).
//
// Quyết định (locked từ session 2026-06-04):
//   - Skip tables: _migrations (internal), accounting_entities (parent self-ref),
//     users/user_roles/role_permissions/roles (RBAC global), business_config/period_config
//     (global config), exchange_rates (global rates), voucher_sequences (global sequences),
//     report_definitions (template global)
//   - Tất cả tables còn lại: thêm entity_id INT UNSIGNED NULL, backfill 1, add index
//   - Idempotent: kiểm tra column tồn tại trước khi ALTER
//   - NULL cho phép tạm thời: rows tạo ra trước backfill có thể NULL (sẽ fail FK nếu cần)
//
// Rủi ro:
//   - R-19: Thêm index trên table lớn (transactions, ledger_entries) có thể lock table → chạy
//     ngoài giờ hoặc dùng thuật toán online (MySQL 8 hỗ trợ ALGORITHM=INPLACE)
//   - R-20: Backfill sai entity → orphan data, fix khó. Mitigation: default 1 (HO), verify sau
//
return function (PDO $pdo) {
    $defaultEntity = 1; // HO - Trụ sở chính (seeded in migration 055)

    // Skip list: tables KHÔNG cần entity_id (global, không gắn với entity cụ thể)
    $skipTables = [
        '_migrations',           // internal
        'accounting_entities',   // parent (self-ref)
        'users',                 // RBAC global (cross-entity access)
        'user_roles',            // global
        'roles',                 // global
        'role_permissions',      // global
        'business_config',       // global config
        'period_config',         // global period config
        'bc09_config',           // global
        'exchange_rates',        // global rates
        'voucher_sequences',     // global
        'report_definitions',    // templates global
        'attendance_records',    // HR global
        'departments',           // HR global structure
        'employees',             // HR global
        'pit_dependents',        // HR global
        'payroll_configs',       // HR global
        'salary_adjustments',    // HR global
        'salary_advances',       // HR global
        'salary_components',     // HR global
        'salary_formulas',       // HR global
        'tax_loss_carryforwards',// tax global
        'tax_rates',             // tax rates global
        'uoms',                  // units of measure global
        'warehouses',            // inventory structure global
    ];

    // Lấy danh sách tables KHÔNG có entity_id
    $tables = $pdo->query(
        "SELECT TABLE_NAME FROM information_schema.tables
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME NOT IN ('_migrations')"
    )->fetchAll(PDO::FETCH_COLUMN);

    $toBackfill = [];
    foreach ($tables as $t) {
        if (in_array($t, $skipTables)) continue;
        $hasCol = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . addslashes($t) . "'
             AND COLUMN_NAME = 'entity_id'"
        )->fetchColumn();
        if ((int)$hasCol === 0) {
            $toBackfill[] = $t;
        }
    }

    // Đếm số tables cần backfill cho log
    $count = count($toBackfill);

    // Add column + backfill + index cho từng table
    foreach ($toBackfill as $table) {
        // 1) ADD COLUMN
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN entity_id INT UNSIGNED NULL COMMENT 'Multi-tenant: FK to accounting_entities'");
        // 2) BACKFILL về default entity
        $pdo->exec("UPDATE `{$table}` SET entity_id = {$defaultEntity} WHERE entity_id IS NULL");
        // 3) ADD INDEX (nếu chưa có)
        $hasIdx = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.statistics
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . addslashes($table) . "'
             AND INDEX_NAME = 'idx_entity'"
        )->fetchColumn();
        if ((int)$hasIdx === 0) {
            $pdo->exec("CREATE INDEX idx_entity ON `{$table}` (entity_id)");
        }
    }

    // Verify: tất cả tables đã có entity_id column (trừ skip list)
    $remaining = 0;
    foreach ($tables as $t) {
        if (in_array($t, $skipTables)) continue;
        $hasCol = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . addslashes($t) . "'
             AND COLUMN_NAME = 'entity_id'"
        )->fetchColumn();
        if ((int)$hasCol === 0) $remaining++;
    }

    // Log kết quả (ghi vào audit_log để trace)
    $log = $pdo->prepare(
        "INSERT INTO audit_log (action, resource_type, resource_id, actor_id, new_values, created_at)
         VALUES ('multi_tenant.backfill', 'schema', 'R-18-20', 'system', ?, NOW())"
    );
    $log->execute([json_encode([
        'tables_backfilled' => $count,
        'tables_remaining_without_entity' => $remaining,
        'default_entity_id' => $defaultEntity,
    ])]);
};
