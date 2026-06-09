<?php
// NGHIỆP VỤ: Bổ sung quyển số và địa chỉ cho Phiếu chi (Mẫu 02-TT)
//
// Quyển số (book_number): Mỗi quyển phiếu chi có số hiệu riêng (VD: PC-01, PC-02)
// Yêu cầu từ TT 99/2025/TT-BTC: Phiếu chi phải ghi rõ quyển số và số chứng từ
//
// Địa chỉ (payer_address): Địa chỉ người nhận tiền theo quy định trên Mẫu 02-TT
// Hiện tại transactions có payer_name nhưng thiếu payer_address
//
// Composite unique: (book_number, reference) — đảm bảo không trùng số CT trong cùng quyển
// Lưu ý: reference đã được VoucherService đảm bảo unique theo năm bằng SELECT FOR UPDATE
// Unique constraint này là lớp bảo vệ thứ 2 ở DB level

return function (PDO $pdo) {
    $pdo->exec("ALTER TABLE transactions
        ADD COLUMN IF NOT EXISTS book_number VARCHAR(20) NULL DEFAULT NULL COMMENT 'Số quyển (VD: PC-01, PC-02)' AFTER reference,
        ADD COLUMN IF NOT EXISTS payer_address TEXT NULL DEFAULT NULL COMMENT 'Địa chỉ người nhận/người nộp' AFTER payer_name");

    // Composite unique: bảo vệ không trùng (quyển + số) trong cùng kỳ
    // MySQL cho phép nhiều NULL trong UNIQUE — chỉ enforce khi cả 2 có giá trị
    try {
        $pdo->exec("ALTER TABLE transactions ADD UNIQUE INDEX uq_book_ref (book_number, reference)");
    } catch (\PDOException $e) {
        // Index đã tồn tại — bỏ qua (idempotent)
        if (strpos($e->getMessage(), '1061') === false) throw $e;
    }
};
