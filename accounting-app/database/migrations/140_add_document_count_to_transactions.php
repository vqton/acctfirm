<?php
// Thêm trường document_count vào bảng transactions
// Mục đích: Lưu số lượng chứng từ gốc kèm theo phiếu thu/chi (TT99 Mẫu 01-TT, 02-TT)
// Yêu cầu từ gap G02 trong BA/CA analysis phiếu thu

return function (PDO $pdo) {
    $pdo->exec("ALTER TABLE transactions
        ADD COLUMN IF NOT EXISTS document_count INT UNSIGNED DEFAULT NULL COMMENT 'Số chứng từ gốc kèm theo'
    ");
};
