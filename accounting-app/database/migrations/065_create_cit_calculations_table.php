<?php
return function (PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cit_calculations (
        id VARCHAR(50) PRIMARY KEY,
        period VARCHAR(7) NOT NULL COMMENT 'Kỳ (YYYY-MM)',
        status VARCHAR(20) DEFAULT 'draft' COMMENT 'draft/finalised',
        revenue DECIMAL(15,2) DEFAULT 0 COMMENT 'Doanh thu (TK 511)',
        cost_of_sales DECIMAL(15,2) DEFAULT 0 COMMENT 'Giá vốn (TK 632)',
        selling_expense DECIMAL(15,2) DEFAULT 0 COMMENT 'Chi phí bán hàng (TK 641)',
        admin_expense DECIMAL(15,2) DEFAULT 0 COMMENT 'Chi phí QLDN (TK 642)',
        financial_expense DECIMAL(15,2) DEFAULT 0 COMMENT 'Chi phí tài chính (TK 635)',
        financial_income DECIMAL(15,2) DEFAULT 0 COMMENT 'Doanh thu tài chính (TK 515)',
        other_income DECIMAL(15,2) DEFAULT 0 COMMENT 'Thu nhập khác (TK 711)',
        other_expense DECIMAL(15,2) DEFAULT 0 COMMENT 'Chi phí khác (TK 811)',
        taxable_income DECIMAL(15,2) DEFAULT 0 COMMENT 'Thu nhập chịu thuế',
        cit_rate DECIMAL(5,2) DEFAULT 20 COMMENT 'Thuế suất TNDN (%)',
        cit_amount DECIMAL(15,2) DEFAULT 0 COMMENT 'Thuế TNDN phải nộp',
        created_by VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cit_period (period)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
