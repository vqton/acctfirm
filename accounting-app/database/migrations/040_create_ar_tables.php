<?php
// Bảng công nợ phải thu — hóa đơn bán hàng và thu tiền khách hàng
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS ar_invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id VARCHAR(50) NOT NULL,
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
        FOREIGN KEY (customer_id) REFERENCES customers(id),
        INDEX idx_customer (customer_id),
        INDEX idx_due_date (due_date),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS ar_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ar_invoice_id INT NOT NULL,
        transaction_id VARCHAR(50) NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        payment_type VARCHAR(30) DEFAULT "payment",
        description TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ar_invoice_id) REFERENCES ar_invoices(id) ON DELETE CASCADE,
        FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
        INDEX idx_invoice (ar_invoice_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};
