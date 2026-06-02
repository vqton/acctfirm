<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS budget_scenarios (
        id VARCHAR(50) PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        year SMALLINT UNSIGNED NOT NULL,
        type VARCHAR(20) NOT NULL DEFAULT "operating",
        status VARCHAR(20) NOT NULL DEFAULT "draft",
        notes TEXT,
        created_by VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_year (year),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS budget_plans (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        scenario_id VARCHAR(50) NOT NULL,
        period_code VARCHAR(10) NOT NULL,
        account_code VARCHAR(20) NOT NULL,
        budget_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (scenario_id) REFERENCES budget_scenarios(id) ON DELETE CASCADE,
        INDEX idx_scenario (scenario_id),
        INDEX idx_period (period_code),
        INDEX idx_account (account_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};
