<?php
//
// NGHIỆP VỤ: Bảng tracking cho mọi lần import dữ liệu hàng loạt
//
// Bối cảnh: R-4 Import Safety Framework yêu cầu:
//   - Audit đầy đủ: ai import, lúc nào, file nào, bao nhiêu dòng
//   - Rollback trong window (24-72h) nếu phát hiện sai
//   - File hash để detect trùng lặp / audit verification
//
// Schema fields:
//   - id: mã batch (uniqid)
//   - entity_type: loại dữ liệu (items/customers/suppliers/coa/opening_balance)
//   - file_hash: SHA256 của file upload → verify integrity
//   - status: pending → committed | rolled_back | failed
//   - error_log: JSON array lỗi validation
//   - total_rows / valid_rows / error_rows: thống kê
//
// Rủi ro nếu thiếu:
//   - Không rollback được khi user phát hiện sai → phải manual fix
//   - Không audit được "ai đã import dữ liệu rác vào hệ thống"
//
return function (PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS import_batches (
        id VARCHAR(20) PRIMARY KEY,
        entity_type VARCHAR(50) NOT NULL COMMENT 'items/customers/suppliers/coa/opening_balance',
        file_name VARCHAR(255) NOT NULL,
        file_hash VARCHAR(64) NOT NULL COMMENT 'SHA256 file content',
        total_rows INT NOT NULL DEFAULT 0,
        valid_rows INT NOT NULL DEFAULT 0,
        error_rows INT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending/committed/rolled_back/failed',
        imported_by VARCHAR(100) NOT NULL,
        imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        committed_at TIMESTAMP NULL,
        rolled_back_at TIMESTAMP NULL,
        rolled_back_by VARCHAR(100) NULL,
        error_log TEXT NULL COMMENT 'JSON array of {row, column, error}',
        notes TEXT NULL,
        INDEX idx_batch_entity (entity_type, status),
        INDEX idx_batch_user (imported_by, imported_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
