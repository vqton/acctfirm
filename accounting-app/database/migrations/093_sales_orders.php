<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS sales_orders (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        reference VARCHAR(20) NOT NULL,
        customer_id INT UNSIGNED NOT NULL,
        order_date DATE NOT NULL,
        delivery_date DATE DEFAULT NULL,
        payment_terms VARCHAR(50) DEFAULT NULL,
        payment_method VARCHAR(20) DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT "draft",
        currency VARCHAR(3) NOT NULL DEFAULT "VND",
        exchange_rate DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
        total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        grand_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        amount_invoiced DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        notes TEXT DEFAULT NULL,
        is_quotation_converted TINYINT(1) NOT NULL DEFAULT 0,
        quotation_id INT UNSIGNED DEFAULT NULL,
        created_by VARCHAR(100) NOT NULL,
        approved_by VARCHAR(100) DEFAULT NULL,
        cancelled_by VARCHAR(100) DEFAULT NULL,
        cancel_reason TEXT DEFAULT NULL,
        cancelled_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_reference (reference),
        INDEX idx_customer (customer_id),
        INDEX idx_status (status),
        INDEX idx_order_date (order_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $pdo->exec('CREATE TABLE IF NOT EXISTS sales_order_lines (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sales_order_id INT UNSIGNED NOT NULL,
        line_no SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        item_id INT UNSIGNED DEFAULT NULL,
        item_code VARCHAR(50) DEFAULT NULL,
        item_name VARCHAR(255) NOT NULL,
        unit VARCHAR(30) DEFAULT NULL,
        qty_ordered DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        qty_shipped DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        qty_invoiced DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        unit_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        tax_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,
        tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        line_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        is_service TINYINT(1) NOT NULL DEFAULT 0,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
        INDEX idx_item (item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $pdo->exec('CREATE TABLE IF NOT EXISTS sales_order_links (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sales_order_id INT UNSIGNED NOT NULL,
        linked_type VARCHAR(30) NOT NULL,
        linked_id VARCHAR(100) NOT NULL,
        linked_reference VARCHAR(30) DEFAULT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        notes VARCHAR(255) DEFAULT NULL,
        created_by VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
        INDEX idx_so_id (sales_order_id),
        INDEX idx_linked (linked_type, linked_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
};
