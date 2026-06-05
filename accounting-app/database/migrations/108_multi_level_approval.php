<?php
//
// R-16: Multi-level Approval — extend routing + approvals log
//
// Quyết định:
//   - approval_routing.approval_sequence JSON: danh sách role theo thứ tự duyệt
//     (vd: ["chief_accountant", "director"] cho giao dịch > 100M cần 2 cấp duyệt)
//   - journal_entry_approvals.approval_level INT (1-based): cấp duyệt hiện tại
//   - Backward compat: nếu approval_sequence NULL → dùng required_role (1 cấp như cũ)
//   - Không thêm cột vào transactions: tính toán số approval cần từ rules + count hiện tại
//
// Ví dụ flows:
//   1) Giao dịch 50M, rule {min:10M, max:100M, sequence:["chief_accountant"]}
//      → 1 cấp duyệt, KTT duyệt xong → approved
//   2) Giao dịch 500M, rule {min:100M, sequence:["chief_accountant", "director"]}
//      → KTT duyệt (level 1) → vẫn submitted. GD duyệt (level 2) → approved
//   3) Giao dịch 1B, rule {min:1B, sequence:["chief_accountant", "director", "cfo"]}
//      → 3 cấp: KTT → GD → CFO → approved
//
return function (PDO $pdo) {
    // 1. Thêm approval_sequence JSON vào approval_routing (backward compat: NULL = 1 cấp)
    $hasCol = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'approval_routing' AND column_name = 'approval_sequence'"
    )->fetchColumn();
    if ((int)$hasCol === 0) {
        $pdo->exec("ALTER TABLE approval_routing ADD COLUMN approval_sequence JSON NULL COMMENT 'Ordered list of roles for multi-level approval, e.g. [\"chief_accountant\",\"director\"]' AFTER required_role");
    }

    // 2. Thêm approval_level vào journal_entry_approvals
    $hasCol2 = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'journal_entry_approvals' AND column_name = 'approval_level'"
    )->fetchColumn();
    if ((int)$hasCol2 === 0) {
        $pdo->exec("ALTER TABLE journal_entry_approvals ADD COLUMN approval_level INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Cấp duyệt hiện tại (1-based)' AFTER action");
    }

    // 3. Index cho query theo (transaction_id, level)
    $hasIdx = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = 'journal_entry_approvals'
         AND index_name = 'idx_transaction_level'"
    )->fetchColumn();
    if ((int)$hasIdx === 0) {
        $pdo->exec("CREATE INDEX idx_transaction_level ON journal_entry_approvals (transaction_id, approval_level)");
    }

    // 4. Seed examples cho multi-level — không overwrite existing rules
    $existing = $pdo->query("SELECT COUNT(*) FROM approval_routing WHERE approval_sequence IS NOT NULL")->fetchColumn();
    if ((int)$existing === 0) {
        $rules = [
            // > 100M: 2 cấp (KTT → GD)
            [100000000, null, null, null, 'director', 30, '["chief_accountant","director"]'],
            // > 1B: 3 cấp (KTT → GD → CFO)
            [1000000000, null, null, null, 'cfo', 25, '["chief_accountant","director","cfo"]'],
            // intercompany: 2 cấp (KTT → CFO)
            [null, null, null, 'intercompany', 'cfo', 40, '["chief_accountant","cfo"]'],
        ];
        $stmt = $pdo->prepare(
            "INSERT INTO approval_routing (min_amount, max_amount, account_type, module, required_role, priority, approval_sequence)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($rules as $r) {
            $stmt->execute($r);
        }
    }
};
