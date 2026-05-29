<?php
return function (PDO $pdo) {
    // Bổ sung cột cho VAT declarations — theo dõi VAT không được khấu trừ (TT 69/2025)
    // Khoản chi ≥ 5 triệu đồng thanh toán bằng tiền mặt → VAT không được khấu trừ
    $pdo->exec("ALTER TABLE vat_declarations ADD COLUMN IF NOT EXISTS non_deductible_vat DECIMAL(15,2) DEFAULT 0 COMMENT 'VAT không được khấu trừ (TT 69/2025)'");

    // Bổ sung cột cho CIT calculations — điều chỉnh thu nhập chịu thuế
    // Chi phí không được trừ (quảng cáo >10% DT, lãi vay >30% EBITDA, ...)
    // Lỗ từ kỳ trước được chuyển sang kỳ này
    $pdo->exec("ALTER TABLE cit_calculations ADD COLUMN IF NOT EXISTS non_deductible_expenses DECIMAL(15,2) DEFAULT 0 COMMENT 'Chi phí không được trừ khi tính TNDN'");
    $pdo->exec("ALTER TABLE cit_calculations ADD COLUMN IF NOT EXISTS adjusted_taxable_income DECIMAL(15,2) DEFAULT 0 COMMENT 'TNCT sau điều chỉnh'");
    $pdo->exec("ALTER TABLE cit_calculations ADD COLUMN IF NOT EXISTS loss_carryforward_used DECIMAL(15,2) DEFAULT 0 COMMENT 'Lỗ kỳ trước chuyển sang kỳ này'");

    // Bảng theo dõi lỗ luân chuyển (carryforward up to 5 years, TT 78/2014)
    $pdo->exec("CREATE TABLE IF NOT EXISTS tax_loss_carryforwards (
        id VARCHAR(50) PRIMARY KEY,
        period VARCHAR(7) NOT NULL COMMENT 'Kỳ phát sinh lỗ (YYYY-MM)',
        loss_amount DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Số lỗ phát sinh',
        remaining_amount DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'Số lỗ còn được chuyển',
        carryforward_years INT NOT NULL DEFAULT 5 COMMENT 'Số năm được chuyển (tối đa 5)',
        expiry_date DATE DEFAULT NULL COMMENT 'Hạn cuối được chuyển',
        status VARCHAR(20) DEFAULT 'active' COMMENT 'active/fully_used/expired',
        created_by VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_tlc_period (period),
        INDEX idx_tlc_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
};
