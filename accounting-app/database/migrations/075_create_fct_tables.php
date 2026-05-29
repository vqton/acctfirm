<?php
return function (PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS fct_contracts (
        id VARCHAR(50) PRIMARY KEY,
        contract_no VARCHAR(100) NOT NULL COMMENT 'Số hợp đồng',
        contractor_name VARCHAR(255) NOT NULL COMMENT 'Tên nhà thầu nước ngoài',
        contractor_country VARCHAR(100) DEFAULT NULL COMMENT 'Quốc gia',
        tax_code VARCHAR(50) DEFAULT NULL COMMENT 'MST nước ngoài',
        service_type VARCHAR(50) NOT NULL COMMENT 'services/services_with_goods/trading/leasing/other',
        contract_value DECIMAL(15,2) DEFAULT 0 COMMENT 'Giá trị hợp đồng (gồm VAT)',
        vat_rate DECIMAL(5,2) DEFAULT 0 COMMENT 'Thuế suất GTGT (%)',
        cit_rate DECIMAL(5,2) DEFAULT 0 COMMENT 'Thuế suất TNDN (%)',
        vat_withholding DECIMAL(15,2) DEFAULT 0 COMMENT 'Thuế GTGT phải khấu trừ',
        cit_withholding DECIMAL(15,2) DEFAULT 0 COMMENT 'Thuế TNDN phải khấu trừ',
        net_payment DECIMAL(15,2) DEFAULT 0 COMMENT 'Thanh toán ròng cho nhà thầu',
        currency VARCHAR(10) DEFAULT 'VND',
        exchange_rate DECIMAL(15,4) DEFAULT 1 COMMENT 'Tỷ giá quy đổi',
        journal_id VARCHAR(50) DEFAULT NULL COMMENT 'Bút toán ghi nhận',
        status VARCHAR(20) DEFAULT 'draft' COMMENT 'draft/posted/cancelled',
        notes TEXT DEFAULT NULL,
        created_by VARCHAR(100) DEFAULT NULL,
        posted_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS fct_declarations (
        id VARCHAR(50) PRIMARY KEY,
        period VARCHAR(7) NOT NULL COMMENT 'Kỳ kê khai (YYYY-MM)',
        status VARCHAR(20) DEFAULT 'draft' COMMENT 'draft/finalised/submitted',
        total_contract_value DECIMAL(15,2) DEFAULT 0 COMMENT 'Tổng giá trị hợp đồng',
        total_vat_withholding DECIMAL(15,2) DEFAULT 0 COMMENT 'Tổng VAT khấu trừ',
        total_cit_withholding DECIMAL(15,2) DEFAULT 0 COMMENT 'Tổng TNDN khấu trừ',
        contract_count INT DEFAULT 0 COMMENT 'Số hợp đồng',
        notes TEXT DEFAULT NULL,
        created_by VARCHAR(100) DEFAULT NULL,
        submitted_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_fct_period (period)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
