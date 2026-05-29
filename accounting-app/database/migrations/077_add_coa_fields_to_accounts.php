<?php
// Thêm cột cho Hệ thống Tài khoản COA mở rộng:
// - Ánh xạ BCTC (FS mapping) — bắt buộc để lập BC01/02/03
// - Khóa tài khoản (lock) — CFO override cho TK có số dư
// - Phân hệ (entity/branch) — COA riêng cho từng chi nhánh
// - Chi tiết (detail_by) — theo dõi theo KH/NCC/NV/DA
return function (PDO $pdo) {
    $r = $pdo->query("SHOW COLUMNS FROM accounts LIKE 'fs_mapping_code'");
    if (!$r->fetch()) {
        $pdo->exec("ALTER TABLE accounts
            ADD COLUMN fs_mapping_code VARCHAR(50) DEFAULT NULL COMMENT 'Ánh xạ chỉ tiêu BCTC' AFTER is_control,
            ADD COLUMN fs_mapping_type ENUM('balance_sheet','income_statement','cash_flow','tax') DEFAULT NULL COMMENT 'Loại BCTC' AFTER fs_mapping_code,
            ADD COLUMN is_locked TINYINT(1) DEFAULT 0 COMMENT 'Khóa tài khoản' AFTER fs_mapping_type,
            ADD COLUMN locked_by VARCHAR(100) DEFAULT NULL COMMENT 'Người khóa' AFTER is_locked,
            ADD COLUMN locked_reason TEXT DEFAULT NULL COMMENT 'Lý do khóa' AFTER locked_by,
            ADD COLUMN locked_at DATETIME DEFAULT NULL COMMENT 'Thời điểm khóa' AFTER locked_reason,
            ADD COLUMN is_system TINYINT(1) DEFAULT 0 COMMENT 'TK hệ thống (không xóa được)' AFTER locked_at,
            ADD COLUMN alternative_code VARCHAR(50) DEFAULT NULL COMMENT 'Mã cũ (Circular 200)' AFTER is_system,
            ADD COLUMN detail_by VARCHAR(50) DEFAULT NULL COMMENT 'Theo dõi chi tiết: customer/supplier/employee/project' AFTER alternative_code,
            ADD INDEX idx_fs_mapping (fs_mapping_code),
            ADD INDEX idx_locked (is_locked),
            ADD INDEX idx_system (is_system)");
    }
};
