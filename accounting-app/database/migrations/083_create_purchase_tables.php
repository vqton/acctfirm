<?php
return function (PDO $pdo) {
    // ĐỀ NGHỊ MUA HÀNG (Purchase Requisition)
    $pdo->exec('CREATE TABLE IF NOT EXISTS purchase_requisitions (
        id VARCHAR(36) PRIMARY KEY,
        pr_number VARCHAR(20) NOT NULL UNIQUE,
        status VARCHAR(20) NOT NULL DEFAULT "draft",
        requester_id VARCHAR(36),
        department_id VARCHAR(36),
        project_id VARCHAR(36) NULL,
        total_estimated DECIMAL(15,2) NOT NULL DEFAULT 0,
        delivery_date DATE NULL,
        note TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS purchase_requisition_lines (
        id VARCHAR(36) PRIMARY KEY,
        pr_id VARCHAR(36) NOT NULL,
        item_id VARCHAR(36) NULL,
        free_text_name VARCHAR(255) NULL,
        qty DECIMAL(15,2) NOT NULL,
        uom_id VARCHAR(36) NULL,
        price_estimate DECIMAL(15,2) NOT NULL DEFAULT 0,
        total_estimate DECIMAL(15,2) GENERATED ALWAYS AS (qty * price_estimate) STORED,
        is_catalog BOOLEAN DEFAULT TRUE,
        FOREIGN KEY (pr_id) REFERENCES purchase_requisitions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // ĐƠN ĐẶT HÀNG (Purchase Order)
    $pdo->exec('CREATE TABLE IF NOT EXISTS purchase_orders (
        id VARCHAR(36) PRIMARY KEY,
        po_number VARCHAR(20) NOT NULL UNIQUE,
        status VARCHAR(20) NOT NULL DEFAULT "draft",
        supplier_id VARCHAR(36) NOT NULL,
        contract_id VARCHAR(36) NULL,
        buyer_id VARCHAR(36),
        payment_terms VARCHAR(100),
        delivery_terms VARCHAR(100),
        total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        tax_amount DECIMAL(15,2) DEFAULT 0,
        grand_total DECIMAL(15,2) GENERATED ALWAYS AS (total_amount + tax_amount) STORED,
        expected_delivery DATE NULL,
        note TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS purchase_order_lines (
        id VARCHAR(36) PRIMARY KEY,
        po_id VARCHAR(36) NOT NULL,
        pr_line_id VARCHAR(36) NULL,
        item_id VARCHAR(36) NULL,
        free_text_name VARCHAR(255) NULL,
        qty_ordered DECIMAL(15,2) NOT NULL,
        qty_received DECIMAL(15,2) DEFAULT 0,
        qty_invoiced DECIMAL(15,2) DEFAULT 0,
        uom_id VARCHAR(36) NULL,
        unit_price DECIMAL(15,2) NOT NULL,
        total DECIMAL(15,2) GENERATED ALWAYS AS (qty_ordered * unit_price) STORED,
        FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // PHIẾU NHẬP KHO (Goods Receipt)
    $pdo->exec('CREATE TABLE IF NOT EXISTS goods_receipts (
        id VARCHAR(36) PRIMARY KEY,
        gr_number VARCHAR(20) NOT NULL UNIQUE,
        po_id VARCHAR(36) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT "draft",
        warehouse_id VARCHAR(36) NULL,
        received_date DATE NOT NULL,
        note TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS goods_receipt_lines (
        id VARCHAR(36) PRIMARY KEY,
        gr_id VARCHAR(36) NOT NULL,
        po_line_id VARCHAR(36) NOT NULL,
        item_id VARCHAR(36) NOT NULL,
        qty_received DECIMAL(15,2) NOT NULL,
        qty_rejected DECIMAL(15,2) DEFAULT 0,
        batch_no VARCHAR(50) NULL,
        expiry_date DATE NULL,
        unit_price DECIMAL(15,2) DEFAULT 0,
        total DECIMAL(15,2) GENERATED ALWAYS AS (qty_received * unit_price) STORED,
        FOREIGN KEY (gr_id) REFERENCES goods_receipts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // ĐỐI CHIẾU HÓA ĐƠN (Invoice Matching)
    $pdo->exec('CREATE TABLE IF NOT EXISTS purchase_invoice_matches (
        id VARCHAR(36) PRIMARY KEY,
        po_id VARCHAR(36) NOT NULL,
        gr_id VARCHAR(36) NULL,
        supplier_invoice_no VARCHAR(100),
        invoice_date DATE NULL,
        invoice_amount DECIMAL(15,2),
        vat_amount DECIMAL(15,2) DEFAULT 0,
        match_status VARCHAR(20) NOT NULL DEFAULT "pending",
        matched_by VARCHAR(36) NULL,
        matched_at TIMESTAMP NULL,
        note TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS purchase_invoice_match_lines (
        id VARCHAR(36) PRIMARY KEY,
        match_id VARCHAR(36) NOT NULL,
        po_line_id VARCHAR(36) NOT NULL,
        gr_line_id VARCHAR(36) NULL,
        qty_invoiced DECIMAL(15,2) DEFAULT 0,
        qty_received DECIMAL(15,2) DEFAULT 0,
        unit_price_invoiced DECIMAL(15,2) DEFAULT 0,
        unit_price_po DECIMAL(15,2) DEFAULT 0,
        qty_tolerance_pass BOOLEAN DEFAULT TRUE,
        price_tolerance_pass BOOLEAN DEFAULT TRUE,
        FOREIGN KEY (match_id) REFERENCES purchase_invoice_matches(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // LUỒNG PHÊ DUYỆT (Approvals)
    $pdo->exec('CREATE TABLE IF NOT EXISTS purchase_approvals (
        id VARCHAR(36) PRIMARY KEY,
        doc_type VARCHAR(10) NOT NULL,
        doc_id VARCHAR(36) NOT NULL,
        step INT DEFAULT 1,
        approver_id VARCHAR(36) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT "pending",
        note TEXT NULL,
        acted_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_step (doc_type, doc_id, step)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // ĐÁNH GIÁ NHÀ CUNG CẤP (Supplier Performance)
    $pdo->exec('CREATE TABLE IF NOT EXISTS supplier_performance (
        id VARCHAR(36) PRIMARY KEY,
        supplier_id VARCHAR(36) NOT NULL,
        period VARCHAR(7) NOT NULL,
        on_time_rate DECIMAL(5,2) DEFAULT 0,
        quality_reject_rate DECIMAL(5,2) DEFAULT 0,
        price_competitiveness INT DEFAULT 0,
        overall_rating DECIMAL(3,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_supplier_period (supplier_id, period)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // KIỂM SOÁT NGÂN SÁCH (Budget Control)
    $pdo->exec('CREATE TABLE IF NOT EXISTS purchase_budgets (
        id VARCHAR(36) PRIMARY KEY,
        department_id VARCHAR(36) NOT NULL,
        period VARCHAR(7) NOT NULL,
        budget_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        committed_amount DECIMAL(15,2) DEFAULT 0,
        actual_amount DECIMAL(15,2) DEFAULT 0,
        remaining DECIMAL(15,2) GENERATED ALWAYS AS (budget_amount - committed_amount - actual_amount) STORED,
        UNIQUE KEY uq_dept_period (department_id, period)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Thêm cột vào suppliers table
    try {
        $pdo->exec('ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS supplier_category VARCHAR(50) NULL');
        $pdo->exec('ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS credit_limit DECIMAL(15,2) DEFAULT 0');
    } catch (\Exception $e) {
        // Column may already exist
    }
};
