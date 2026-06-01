<?php
// Module thu hồi công nợ (Debt Collection) — 5 bảng quản lý quy trình đòi nợ chủ động
// Nghiệp vụ: Nhắc nợ tự động, theo dõi hoạt động đòi nợ, cam kết thanh toán, phê duyệt xóa nợ, thỏa thuận
// Tham chiếu: docs/analysis/debt-collection-engine-brain-logic.md

return function (PDO $pdo) {
    // Hàng đợi đòi nợ — 1 entry per hóa đơn quá hạn
    $pdo->exec('CREATE TABLE IF NOT EXISTS debt_collection_queue (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        customer_id VARCHAR(50) NOT NULL,
        assigned_to VARCHAR(50) DEFAULT NULL,
        status ENUM("new","active","hold","escalated","settlement","writeoff","closed") DEFAULT "new",
        priority TINYINT DEFAULT 0,
        entered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_action_date TIMESTAMP NULL,
        next_action_date DATE NULL,
        escalation_level TINYINT UNSIGNED DEFAULT 0,
        hold_reason VARCHAR(255) DEFAULT NULL,
        hold_until DATE NULL,
        hold_count TINYINT UNSIGNED DEFAULT 0,
        resolved_at TIMESTAMP NULL,
        resolution VARCHAR(50) DEFAULT NULL,
        resolution_note TEXT DEFAULT NULL,
        created_by VARCHAR(50) DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (invoice_id) REFERENCES ar_invoices(id),
        FOREIGN KEY (customer_id) REFERENCES customers(id),
        INDEX idx_status (status),
        INDEX idx_assigned (assigned_to),
        INDEX idx_invoice (invoice_id),
        INDEX idx_priority (priority)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Lịch sử hoạt động đòi nợ — cuộc gọi, email, meeting, dispute
    $pdo->exec('CREATE TABLE IF NOT EXISTS debt_collection_activities (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        queue_id INT UNSIGNED NOT NULL,
        activity_type ENUM("call","email","meeting","sms","letter","dispute","other","auto") NOT NULL,
        summary VARCHAR(500) NOT NULL,
        detail TEXT DEFAULT NULL,
        contact_person VARCHAR(200) DEFAULT NULL,
        contact_phone VARCHAR(20) DEFAULT NULL,
        result VARCHAR(50) DEFAULT NULL,
        promise_date DATE NULL,
        promise_amount DECIMAL(15,2) NULL,
        duration_minutes SMALLINT UNSIGNED DEFAULT NULL,
        attachment_path VARCHAR(500) DEFAULT NULL,
        created_by VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (queue_id) REFERENCES debt_collection_queue(id) ON DELETE CASCADE,
        INDEX idx_queue (queue_id),
        INDEX idx_type (activity_type),
        INDEX idx_created (created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Cam kết thanh toán — KH hứa hẹn ngày trả nợ
    $pdo->exec('CREATE TABLE IF NOT EXISTS debt_collection_promises (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        queue_id INT UNSIGNED NOT NULL,
        activity_id INT UNSIGNED DEFAULT NULL,
        promise_date DATE NOT NULL,
        promise_amount DECIMAL(15,2) NOT NULL,
        promise_note VARCHAR(500) DEFAULT NULL,
        status ENUM("active","kept","broken","cancelled") DEFAULT "active",
        kept_date DATE NULL,
        broken_reason VARCHAR(500) DEFAULT NULL,
        broken_count TINYINT UNSIGNED DEFAULT 0,
        created_by VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (queue_id) REFERENCES debt_collection_queue(id) ON DELETE CASCADE,
        FOREIGN KEY (activity_id) REFERENCES debt_collection_activities(id),
        INDEX idx_queue (queue_id),
        INDEX idx_promise_date (promise_date),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Phê duyệt — write-off, settlement, escalation
    $pdo->exec('CREATE TABLE IF NOT EXISTS debt_collection_approvals (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        queue_id INT UNSIGNED NOT NULL,
        approval_type ENUM("writeoff","settlement","escalate","hold_extend") NOT NULL,
        requested_by VARCHAR(50) NOT NULL,
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        request_note TEXT NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        settlement_percent DECIMAL(5,2) DEFAULT NULL,
        settlement_amount DECIMAL(15,2) DEFAULT NULL,
        level_1_approver VARCHAR(50) DEFAULT NULL,
        level_1_status ENUM("pending","approved","rejected") DEFAULT "pending",
        level_1_at TIMESTAMP NULL,
        level_1_note TEXT DEFAULT NULL,
        level_2_approver VARCHAR(50) DEFAULT NULL,
        level_2_status ENUM("pending","approved","rejected") DEFAULT "pending",
        level_2_at TIMESTAMP NULL,
        level_2_note TEXT DEFAULT NULL,
        level_3_approver VARCHAR(50) DEFAULT NULL,
        level_3_status ENUM("pending","approved","rejected") DEFAULT "pending",
        level_3_at TIMESTAMP NULL,
        level_3_note TEXT DEFAULT NULL,
        overall_status ENUM("pending","approved","rejected") DEFAULT "pending",
        resolved_at TIMESTAMP NULL,
        FOREIGN KEY (queue_id) REFERENCES debt_collection_queue(id),
        INDEX idx_queue (queue_id),
        INDEX idx_status (overall_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Thỏa thuận thanh toán — giảm nợ, trả góp
    $pdo->exec('CREATE TABLE IF NOT EXISTS debt_collection_settlements (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        queue_id INT UNSIGNED NOT NULL,
        approval_id INT UNSIGNED DEFAULT NULL,
        original_balance DECIMAL(15,2) NOT NULL,
        settlement_amount DECIMAL(15,2) NOT NULL,
        discount_amount DECIMAL(15,2) NOT NULL,
        discount_percent DECIMAL(5,2) NOT NULL,
        payment_type ENUM("lump_sum","installment") DEFAULT "lump_sum",
        installment_count TINYINT UNSIGNED DEFAULT 1,
        installment_frequency VARCHAR(20) DEFAULT NULL,
        agreement_date DATE NOT NULL,
        due_by_date DATE NOT NULL,
        status ENUM("active","completed","defaulted","cancelled") DEFAULT "active",
        amount_paid DECIMAL(15,2) DEFAULT 0,
        last_payment_date DATE NULL,
        created_by VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (queue_id) REFERENCES debt_collection_queue(id),
        FOREIGN KEY (approval_id) REFERENCES debt_collection_approvals(id),
        INDEX idx_queue (queue_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};
