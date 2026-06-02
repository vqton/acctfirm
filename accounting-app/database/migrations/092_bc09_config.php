<?php
// Bảng cấu hình chỉ tiêu và dữ liệu Thuyết minh Báo cáo tài chính (BC09 - Mẫu B09-DN)
// Tuân thủ Thông tư 99/2025/TT-BTC
return function (PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bc09_config (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        section_code VARCHAR(10) NOT NULL COMMENT 'Mã phần (V, VI, VII, VIII, IX)',
        indicator_code VARCHAR(30) NOT NULL COMMENT 'Mã chỉ tiêu (VD: V.01, V.02)',
        indicator_name VARCHAR(255) NOT NULL COMMENT 'Tên chỉ tiêu',
        formula_expression TEXT COMMENT 'Biểu thức tính (VD: 1111+1112+1113 hoặc 211-214)',
        account_codes VARCHAR(500) COMMENT 'Danh sách mã TK cách nhau bằng dấu phẩy',
        is_auto_calc BOOLEAN DEFAULT TRUE COMMENT 'TRUE = tự động tính từ số dư TK',
        is_required BOOLEAN DEFAULT TRUE COMMENT 'TRUE = bắt buộc có số liệu',
        parent_code VARCHAR(30) DEFAULT NULL COMMENT 'Mã chỉ tiêu cha',
        sort_order INT DEFAULT 0 COMMENT 'Thứ tự sắp xếp',
        UNIQUE KEY uq_section_indicator (section_code, indicator_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS bc09_data (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        period_id INT UNSIGNED NOT NULL COMMENT 'Kỳ kế toán',
        section_code VARCHAR(10) NOT NULL COMMENT 'Mã phần',
        indicator_code VARCHAR(30) NOT NULL COMMENT 'Mã chỉ tiêu',
        year_start DECIMAL(15,2) DEFAULT 0 COMMENT 'Số đầu năm/kỳ',
        year_end DECIMAL(15,2) DEFAULT 0 COMMENT 'Số cuối năm/kỳ',
        note_text TEXT COMMENT 'Ghi chú thuyết minh',
        is_manual BOOLEAN DEFAULT FALSE COMMENT 'TRUE = nhập tay',
        created_by INT UNSIGNED DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_period_indicator (period_id, indicator_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
