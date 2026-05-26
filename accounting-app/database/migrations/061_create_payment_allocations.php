<?php
// Bảng phân bổ thanh toán cho nhiều hóa đơn (AP/AR).
// Cho phép 1 lần thanh toán/thu tiền phân bổ vào nhiều hóa đơn khác nhau.
// Nghiệp vụ thực tế: Chuyển khoản 50tr thanh toán 3 hóa đơn NCC cùng lúc.
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS payment_allocations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        payment_type ENUM("ap","ar") NOT NULL,
        transaction_id VARCHAR(50) NOT NULL,
        invoice_id INT UNSIGNED NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_transaction (payment_type, transaction_id),
        INDEX idx_invoice (payment_type, invoice_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};
