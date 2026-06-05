<?php
//
// NGHIỆP VỤ: User display currency preference + R-11 multi-currency display
//
// Bối cảnh: Doanh nghiệp xuất nhập khẩu có nghiệp vụ ngoại tệ (USD, EUR, JPY...)
// nhưng báo cáo tài chính vẫn phải trình bày bằng VND (theo TT 99/2025/TT-BTC).
//
// Vấn đề: Kế toán viên người nước ngoài hoặc ban giám đốc muốn xem báo cáo
// bằng USD/EUR để dễ hiểu hơn. Hệ thống cần:
//   1. Mỗi user chọn display_currency (VND, USD, EUR...)
//   2. Trong view, hiển thị song song: số gốc (VND) + số quy đổi (USD)
//   3. Tỷ giá lấy từ bảng exchange_rates (đã có sẵn)
//
// Rủi ro:
//   - Tỷ giá thay đổi → số quy đổi cũng thay đổi → ảnh hưởng báo cáo quản trị
//     (không ảnh hưởng BC chính thức vì BC chính thức giữ theo VND)
//   - Không có tỷ giá cho ngày cụ thể → phải fallback về tỷ giá mới nhất
//   - Hiển thị sai số sau dấu phẩy → kế toán mất tin tưởng
//
return function (PDO $pdo) {
    // 1. Thêm cột display_currency vào users (nullable = dùng VND default)
    $hasCol = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'display_currency'"
    )->fetchColumn();
    if (!$hasCol) {
        $pdo->exec("ALTER TABLE users ADD COLUMN display_currency VARCHAR(10) DEFAULT 'VND' AFTER email");
    }

    // 2. Seed thêm các loại ngoại tệ phổ biến nếu chưa có (idempotent)
    $today = date('Y-m-d');
    $rates = [
        ['USD', 'US Dollar', 25480.00],
        ['EUR', 'Euro', 27700.00],
        ['JPY', 'Japanese Yen', 162.50],
        ['GBP', 'British Pound', 32200.00],
        ['CNY', 'Chinese Yuan', 3510.00],
        ['SGD', 'Singapore Dollar', 18900.00],
        ['AUD', 'Australian Dollar', 16800.00],
    ];
    $checkStmt = $pdo->prepare(
        "SELECT id FROM exchange_rates WHERE currency_code = ? AND rate_date = ?"
    );
    $insertStmt = $pdo->prepare(
        "INSERT INTO exchange_rates (id, currency_code, currency_name, rate, rate_date)
         VALUES (?, ?, ?, ?, ?)"
    );
    foreach ($rates as $r) {
        $checkStmt->execute([$r[0], $today]);
        if (!$checkStmt->fetchColumn()) {
            $insertStmt->execute(['fx_' . $r[0] . '_' . uniqid(), $r[0], $r[1], $r[2], $today]);
        }
    }
};
