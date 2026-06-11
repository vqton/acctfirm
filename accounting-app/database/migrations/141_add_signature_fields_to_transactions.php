<?php
// Thêm trường chữ ký số vào bảng transactions
// Mục đích: Lưu chữ ký số cho phiếu thu/chi (TT99 Mẫu 01-TT, 02-TT)
// Yêu cầu từ gap G05 trong BA/CA analysis phiếu thu
// Tích hợp DigitalSignatureService để ký số chứng từ kế toán

return function (PDO $pdo) {
    $pdo->exec("ALTER TABLE transactions
        ADD COLUMN IF NOT EXISTS signature TEXT NULL DEFAULT NULL COMMENT 'Chữ ký số (base64)',
        ADD COLUMN IF NOT EXISTS signed_by VARCHAR(50) NULL DEFAULT NULL COMMENT 'Người ký',
        ADD COLUMN IF NOT EXISTS signed_at DATETIME NULL DEFAULT NULL COMMENT 'Thời điểm ký'
    ");
};
