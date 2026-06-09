<?php
return function (PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS einvoice_imports (
        id VARCHAR(50) PRIMARY KEY,
        original_xml LONGTEXT NOT NULL COMMENT 'XML gốc từ nhà cung cấp',
        invoice_number VARCHAR(50) NOT NULL COMMENT 'Số hóa đơn',
        invoice_date DATE DEFAULT NULL COMMENT 'Ngày hóa đơn',
        template_code VARCHAR(20) DEFAULT NULL COMMENT 'Mẫu số (1=GTGT)',
        template_symbol VARCHAR(50) DEFAULT NULL COMMENT 'Ký hiệu hóa đơn',
        supplier_tax_code VARCHAR(20) DEFAULT NULL COMMENT 'MST người bán',
        supplier_name VARCHAR(255) DEFAULT NULL COMMENT 'Tên người bán',
        supplier_address TEXT DEFAULT NULL COMMENT 'Địa chỉ người bán',
        buyer_tax_code VARCHAR(20) DEFAULT NULL COMMENT 'MST người mua',
        buyer_name VARCHAR(255) DEFAULT NULL COMMENT 'Tên người mua',
        total_before_vat DECIMAL(15,2) DEFAULT 0 COMMENT 'Tổng trước thuế',
        total_vat DECIMAL(15,2) DEFAULT 0 COMMENT 'Tổng thuế GTGT',
        grand_total DECIMAL(15,2) DEFAULT 0 COMMENT 'Tổng thanh toán',
        currency VARCHAR(5) DEFAULT 'VND' COMMENT 'Tiền tệ',
        items JSON DEFAULT NULL COMMENT 'Danh sách hàng hóa (parsed)',
        status VARCHAR(20) DEFAULT 'imported' COMMENT 'imported/processed/duplicate/error',
        fkey VARCHAR(100) DEFAULT NULL COMMENT 'Invoice FKey (chống trùng)',
        transaction_id VARCHAR(50) DEFAULT NULL COMMENT 'Giao dịch kế toán được tạo',
        supplier_id VARCHAR(50) DEFAULT NULL COMMENT 'Nhà cung cấp (tạo/Map)',
        error_message TEXT DEFAULT NULL COMMENT 'Lỗi nếu có',
        created_by VARCHAR(50) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at TIMESTAMP NULL DEFAULT NULL,
        UNIQUE KEY uq_fkey (fkey),
        KEY idx_status (status),
        KEY idx_supplier_tax (supplier_tax_code),
        KEY idx_invoice_number (invoice_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Lịch sử import hóa đơn điện tử đầu vào'");
};
