<?php
// Thêm tài khoản con cho TK 635 (Chi phí tài chính) để phân tách chi phí lãi vay
// Phục vụ BC02 chỉ tiêu 24 (Trong đó: Chi phí đi vay) theo TT 99/2025/TT-BTC
// Yêu cầu: G02 — MS 24 chỉ lấy lãi vay (6351), không phải toàn bộ 635

return function (PDO $pdo) {
    // Tìm ID của tài khoản 635
    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE code = '635' LIMIT 1");
    $stmt->execute();
    $parentId = $stmt->fetchColumn();

    if (!$parentId) {
        echo "KHÔNG tìm thấy tài khoản 635. Bỏ qua migration.\n";
        return;
    }

    // Các tài khoản con cần tạo
    $children = [
        ['6351', 'Chi phí lãi vay'],
        ['6352', 'Chênh lệch tỷ giá'],
        ['6353', 'Chiết khấu thanh toán'],
        ['6358', 'Chi phí tài chính khác'],
    ];

    $insertStmt = $pdo->prepare(
        'INSERT IGNORE INTO accounts (id, code, name, type, parent_id, normal_balance, account_class, is_control, entity_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1)'
    );

    foreach ($children as [$code, $name]) {
        $id = uniqid('coa_');
        $insertStmt->execute([$id, $code, $name, 'expense', $parentId, 'D', '6']);
        echo "  Đã tạo tài khoản $code ($name)\n";
    }

    // Đánh dấu 635 là tài khoản tổng hợp (control account)
    $updateStmt = $pdo->prepare("UPDATE accounts SET is_control = 1 WHERE id = ? AND is_control = 0");
    $updateStmt->execute([$parentId]);
    echo "  Đã đánh dấu TK 635 là tài khoản tổng hợp (is_control=1)\n";
};
