<?php
return function (PDO $pdo) {
    // Bảng lưu hóa đơn điện tử đã phát hành
    // Mỗi transaction có thể có nhiều e-invoice (nếu điều chỉnh/thay thế)
    $pdo->exec("CREATE TABLE IF NOT EXISTS e_invoices (
        id VARCHAR(20) NOT NULL PRIMARY KEY,
        transaction_id VARCHAR(20) DEFAULT NULL,
        reference VARCHAR(50) DEFAULT NULL,
        invoice_type VARCHAR(10) NOT NULL DEFAULT '01GTKT',
        template_code VARCHAR(2) NOT NULL DEFAULT '1',
        template_symbol VARCHAR(10) NOT NULL DEFAULT '',
        invoice_number VARCHAR(8) NOT NULL DEFAULT '',
        fkey VARCHAR(100) DEFAULT NULL,
        inv_token VARCHAR(100) DEFAULT NULL,
        cqt_code VARCHAR(100) DEFAULT NULL,
        xml_unsigned LONGTEXT DEFAULT NULL,
        xml_signed LONGTEXT DEFAULT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        issue_date DATE DEFAULT NULL,
        customer_name VARCHAR(400) DEFAULT NULL,
        customer_tax_code VARCHAR(20) DEFAULT NULL,
        total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_vat DECIMAL(15,2) NOT NULL DEFAULT 0,
        grand_total DECIMAL(15,2) NOT NULL DEFAULT 0,
        currency VARCHAR(3) NOT NULL DEFAULT 'VND',
        payment_method VARCHAR(10) NOT NULL DEFAULT 'TM',
        adjustment_type VARCHAR(20) DEFAULT NULL,
        original_fkey VARCHAR(100) DEFAULT NULL,
        error_code VARCHAR(20) DEFAULT NULL,
        error_message TEXT DEFAULT NULL,
        signed_at DATETIME DEFAULT NULL,
        submitted_at DATETIME DEFAULT NULL,
        created_by VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_transaction_id (transaction_id),
        INDEX idx_status (status),
        INDEX idx_fkey (fkey),
        INDEX idx_inv_token (inv_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Bảng lưu dòng hóa đơn (hàng hóa/dịch vụ)
    $pdo->exec("CREATE TABLE IF NOT EXISTS e_invoice_lines (
        id VARCHAR(20) NOT NULL PRIMARY KEY,
        e_invoice_id VARCHAR(20) NOT NULL,
        line_number INT UNSIGNED NOT NULL DEFAULT 1,
        product_code VARCHAR(50) DEFAULT NULL,
        product_name VARCHAR(400) NOT NULL DEFAULT '',
        unit VARCHAR(50) DEFAULT NULL,
        quantity DECIMAL(15,4) NOT NULL DEFAULT 0,
        unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
        discount_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
        discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_before_vat DECIMAL(15,2) NOT NULL DEFAULT 0,
        vat_rate VARCHAR(10) NOT NULL DEFAULT '10%',
        is_service TINYINT(1) NOT NULL DEFAULT 0,
        INDEX idx_e_invoice_id (e_invoice_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Bảng cấu hình kết nối T-VAN (multi-provider)
    $pdo->exec("CREATE TABLE IF NOT EXISTS tvan_providers (
        id VARCHAR(20) NOT NULL PRIMARY KEY,
        name VARCHAR(100) NOT NULL DEFAULT '',
        provider_code VARCHAR(20) NOT NULL DEFAULT '',
        api_url VARCHAR(500) NOT NULL DEFAULT '',
        username VARCHAR(100) DEFAULT NULL,
        password_encrypted VARCHAR(500) DEFAULT NULL,
        account VARCHAR(100) DEFAULT NULL,
        acpass_encrypted VARCHAR(500) DEFAULT NULL,
        pattern VARCHAR(10) NOT NULL DEFAULT '',
        serial VARCHAR(10) NOT NULL DEFAULT '',
        cert_serial VARCHAR(100) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_provider (provider_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed default T-VAN provider record
    $pdo->exec("INSERT IGNORE INTO tvan_providers (id, name, provider_code, api_url, pattern, serial, is_active, is_default)
        VALUES ('tvan_vnpt', 'VNPT Invoice', 'VNPT', 'https://api.vnpt-invoice.vn/ws/services/InvoiceWS', '1', '01GTKT0/001', 1, 1)");
};
