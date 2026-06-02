<?php
return function (PDO $pdo) {
    $pdo->exec('ALTER TABLE ledger_entries ADD COLUMN IF NOT EXISTS project_id VARCHAR(50) DEFAULT NULL AFTER account_id');
    $pdo->exec('ALTER TABLE ledger_entries ADD INDEX IF NOT EXISTS idx_project_id (project_id)');

    $pdo->exec('ALTER TABLE projects ADD COLUMN IF NOT EXISTS manager_id VARCHAR(50) DEFAULT NULL AFTER customer_id');
    $pdo->exec('ALTER TABLE projects ADD COLUMN IF NOT EXISTS actual_cost DECIMAL(15,2) DEFAULT 0 AFTER budget');
    $pdo->exec('ALTER TABLE projects ADD COLUMN IF NOT EXISTS billed_amount DECIMAL(15,2) DEFAULT 0 AFTER actual_cost');
    $pdo->exec('ALTER TABLE projects ADD COLUMN IF NOT EXISTS revenue_recognized DECIMAL(15,2) DEFAULT 0 AFTER billed_amount');
    $pdo->exec('ALTER TABLE projects ADD COLUMN IF NOT EXISTS estimated_completion_pct DECIMAL(5,2) DEFAULT 0 AFTER revenue_recognized');
    $pdo->exec('ALTER TABLE projects MODIFY status VARCHAR(20) NOT NULL DEFAULT "active"');

    $pdo->exec('CREATE TABLE IF NOT EXISTS project_progress_billing (
        id VARCHAR(50) PRIMARY KEY,
        project_id VARCHAR(50) NOT NULL,
        billing_date DATE NOT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        pct_complete DECIMAL(5,2) DEFAULT 0,
        description VARCHAR(255),
        status VARCHAR(20) NOT NULL DEFAULT "draft",
        invoice_id VARCHAR(50) DEFAULT NULL,
        created_by VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        INDEX idx_project (project_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS project_budgets (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        project_id VARCHAR(50) NOT NULL,
        account_code VARCHAR(20) NOT NULL,
        budget_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        spent_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        INDEX idx_project (project_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};
