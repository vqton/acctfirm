<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS attendance_records (
        id VARCHAR(36) PRIMARY KEY,
        employee_id VARCHAR(36) NOT NULL,
        period_id VARCHAR(36) NOT NULL,
        working_days DECIMAL(5,2) NOT NULL DEFAULT 0,
        overtime_hours_weekday DECIMAL(5,2) NOT NULL DEFAULT 0,
        overtime_hours_weekend DECIMAL(5,2) NOT NULL DEFAULT 0,
        overtime_hours_holiday DECIMAL(5,2) NOT NULL DEFAULT 0,
        overtime_hours_night_weekday DECIMAL(5,2) NOT NULL DEFAULT 0,
        overtime_hours_night_weekend DECIMAL(5,2) NOT NULL DEFAULT 0,
        overtime_hours_night_holiday DECIMAL(5,2) NOT NULL DEFAULT 0,
        unpaid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0,
        paid_leave_days DECIMAL(5,2) NOT NULL DEFAULT 0,
        late_count INT NOT NULL DEFAULT 0,
        early_leave_count INT NOT NULL DEFAULT 0,
        no_checkin_count INT NOT NULL DEFAULT 0,
        status ENUM("draft","approved","rejected") NOT NULL DEFAULT "draft",
        approved_by VARCHAR(255) DEFAULT NULL,
        approved_at TIMESTAMP NULL DEFAULT NULL,
        notes TEXT,
        created_by VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        INDEX idx_period (period_id),
        INDEX idx_employee_period (employee_id, period_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};
