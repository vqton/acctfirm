<?php
// Bảng công nợ phải trả — hóa đơn mua hàng và thanh toán nhà cung cấp
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS ap_invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id VARCHAR(50) NOT NULL,
        invoice_number VARCHAR(100) NOT NULL,
        invoice_date DATE NOT NULL,
        due_date DATE NOT NULL,
        currency_code VARCHAR(3) DEFAULT "VND",
        exchange_rate DECIMAL(15,4) DEFAULT 1,
        gross_amount DECIMAL(15,2) NOT NULL,
        net_amount DECIMAL(15,2) NOT NULL,
        vat_amount DECIMAL(15,2) DEFAULT 0,
        vat_rate DECIMAL(5,2) DEFAULT 0,
        paid_amount DECIMAL(15,2) DEFAULT 0,
        balance DECIMAL(15,2) NOT NULL,
        status VARCHAR(20) DEFAULT "unpaid",
        description TEXT DEFAULT NULL,
        created_by VARCHAR(50) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
        INDEX idx_supplier (supplier_id),
        INDEX idx_due_date (due_date),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS ap_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ap_invoice_id INT NOT NULL,
        transaction_id VARCHAR(50) NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        payment_type VARCHAR(30) DEFAULT "payment",
        description TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ap_invoice_id) REFERENCES ap_invoices(id) ON DELETE CASCADE,
        FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
        INDEX idx_invoice (ap_invoice_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};
