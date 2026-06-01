<?php
// Bảng cấu hình kỳ kế toán — lưu các tỷ lệ và tham số có thể cấu hình
// Cho phép kế toán trưởng thay đổi CIT rate, tỷ lệ trích quỹ mà không cần sửa code
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS period_config (
        `key` VARCHAR(64) PRIMARY KEY,
        `value` DECIMAL(15,4) NOT NULL,
        `description` TEXT,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `updated_by` VARCHAR(255)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Seed mặc định theo Circular 99 và Luật Thuế TNDN
    // Nếu đã tồn tại (INSERT IGNORE) thì giữ nguyên giá trị hiện tại
    $defaults = [
        ['cit_rate', 0.2000, 'Thuế suất TNDN (20% theo TT 78/2021, doanh nghiệp vừa và nhỏ 15-17%)'],
        ['bonus_rate', 0.1000, 'Tỷ lệ trích quỹ khen thưởng, phúc lợi (TK 353) — mặc định 10% LNST'],
        ['investment_rate', 0.2000, 'Tỷ lệ trích quỹ đầu tư phát triển (TK 414) — mặc định 20% LNST'],
    ];

    $stmt = $pdo->prepare('INSERT IGNORE INTO period_config (`key`, `value`, `description`) VALUES (?, ?, ?)');
    foreach ($defaults as $row) {
        $stmt->execute($row);
    }
};
