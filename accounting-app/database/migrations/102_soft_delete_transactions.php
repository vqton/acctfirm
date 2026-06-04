<?php
//
// NGHIỆP VỤ: Soft delete bút toán — giữ audit trail, khôi phục được trong 30 ngày
//
// Bối cảnh: AGENTS.md cấm hard delete (FORBIDDEN: Xóa dữ liệu gốc).
// Bút toán đã ghi sổ KHÔNG được xóa (sai BC), nhưng draft nhầm cần xóa + restore.
//
// R-13: Thêm deleted_at + deleted_by vào transactions.
//   - Soft delete: set deleted_at = NOW() + deleted_by = current user
//   - Restore: set deleted_at = NULL (chỉ trong 30 ngày)
//   - List query tự động filter WHERE deleted_at IS NULL
//   - KTT + Admin mới có quyền xóa bút toán đã posted (cần audit nghiêm)
//
return function (PDO $pdo) {
    $r = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'deleted_at'");
    if (!$r->fetch()) {
        $pdo->exec("ALTER TABLE transactions
            ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete marker' AFTER reversed_at,
            ADD COLUMN deleted_by VARCHAR(100) DEFAULT NULL COMMENT 'User đã xóa' AFTER deleted_at,
            ADD INDEX idx_tx_deleted (deleted_at, deleted_by)");
    }
};
