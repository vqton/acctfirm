<?php
return function (PDO $pdo) {
    // HARD DEADLINE: Thêm cột deadline vào accounting_periods để thiết lập
    // thời hạn cứng — sau deadline, mọi giao dịch bị từ chối trừ khi Kế toán trưởng approve
    $pdo->exec("ALTER TABLE accounting_periods
        ADD COLUMN IF NOT EXISTS deadline DATE DEFAULT NULL AFTER end_date,
        ADD COLUMN IF NOT EXISTS hard_closed TINYINT(1) NOT NULL DEFAULT 0 AFTER deadline
    ");
};
