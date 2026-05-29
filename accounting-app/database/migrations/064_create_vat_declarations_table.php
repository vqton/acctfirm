<?php
return function (PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS vat_declarations (
        id VARCHAR(50) PRIMARY KEY,
        period VARCHAR(7) NOT NULL COMMENT 'Kỳ kê khai (YYYY-MM)',
        declaration_type VARCHAR(20) NOT NULL DEFAULT 'monthly' COMMENT 'monthly/quarterly',
        status VARCHAR(20) DEFAULT 'draft' COMMENT 'draft/finalised/submitted',
        total_vat_input DECIMAL(15,2) DEFAULT 0 COMMENT 'Tổng VAT đầu vào được khấu trừ (1331)',
        total_vat_output DECIMAL(15,2) DEFAULT 0 COMMENT 'Tổng VAT đầu ra (33311)',
        vat_payable DECIMAL(15,2) DEFAULT 0 COMMENT 'VAT phải nộp = output - input',
        invoice_count_input INT DEFAULT 0 COMMENT 'Số hóa đơn đầu vào',
        invoice_count_output INT DEFAULT 0 COMMENT 'Số hóa đơn đầu ra',
        notes TEXT DEFAULT NULL,
        created_by VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_vat_period (period)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS vat_declaration_details (
        id VARCHAR(50) PRIMARY KEY,
        declaration_id VARCHAR(50) NOT NULL,
        line_type VARCHAR(20) NOT NULL COMMENT 'input/output',
        invoice_ref VARCHAR(100) DEFAULT NULL,
        supplier_or_customer VARCHAR(255) DEFAULT NULL,
        invoice_date DATE DEFAULT NULL,
        gross_amount DECIMAL(15,2) DEFAULT 0 COMMENT 'Tổng giá thanh toán',
        vat_amount DECIMAL(15,2) DEFAULT 0 COMMENT 'Tiền thuế',
        vat_rate DECIMAL(5,2) DEFAULT 0 COMMENT 'Thuế suất (%)',
        description TEXT DEFAULT NULL,
        source_table VARCHAR(50) DEFAULT NULL COMMENT 'ap_invoices/ar_invoices',
        source_id VARCHAR(50) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (declaration_id) REFERENCES vat_declarations(id) ON DELETE CASCADE,
        INDEX idx_vatdet_decl (declaration_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
