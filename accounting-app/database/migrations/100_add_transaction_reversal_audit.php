<?php
//
// NGHIỆP VỤ: Lưu vết bút toán bị đảo ngược (reverse) — phục vụ audit trail TT99
//
// Bối cảnh:
//   - createNegativeEntry() đã tồn tại, tạo transaction đảo dấu + set status='reversed'
//   - Nhưng Transaction::reverse() KHÔNG lưu ai reverse, khi nào reverse
//   - Thiếu audit trail cho người kiểm tra (KTT, Kiểm toán độc lập)
//
// Rủi ro nếu thiếu:
//   - Không truy được "Ai đã hủy bút toán X lúc nào, với lý do gì"
//   - Vi phạm TT99/2025/TT-BTC yêu cầu "lưu trữ đầy đủ thông tin về điều chỉnh"
//
// Backward compatible: 2 cột NULLable → migration an toàn cho data cũ
//
return function (PDO $pdo) {
    $r = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'reversed_by'");
    if (!$r->fetch()) {
        $pdo->exec("ALTER TABLE transactions
            ADD COLUMN reversed_by VARCHAR(100) DEFAULT NULL COMMENT 'User đã reverse bút toán' AFTER status,
            ADD COLUMN reversed_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Thời điểm reverse' AFTER reversed_by,
            ADD INDEX idx_tx_reversed (reversed_by, reversed_at)");
    }
};
