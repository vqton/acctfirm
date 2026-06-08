<?php
// Liên kết petty_cash_transactions với advance_payment_requests (Mẫu 03-TT)
// Cho phép chi tiền từ đề nghị tạm ứng đã duyệt, tự động ghi nhận trạng thái paid
return function (PDO $pdo) {
    // Thêm cột request_id để link với advance_payment_requests
    $pdo->exec("ALTER TABLE petty_cash_transactions
        ADD COLUMN IF NOT EXISTS request_id VARCHAR(50) DEFAULT NULL AFTER expense_account,
        ADD COLUMN IF NOT EXISTS request_number VARCHAR(30) DEFAULT NULL AFTER request_id,
        ADD INDEX IF NOT EXISTS idx_pct_request_id (request_id)");
};
