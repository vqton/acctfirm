<?php
// Cập nhật công thức BC02 theo TT 99/2025/TT-BTC (G02):
//   - MS 23 (Chi phí tài chính): formula_type → account_tree (tổng hợp tất cả TK con của 635)
//   - MS 24 (Trong đó: Chi phí đi vay): formula_detail → 6351 (chỉ lãi vay, không lấy toàn bộ 635)
//
// Lý do: Không thể dùng formula_type=account + formula_detail=635 vì khi hạch toán vào TK con (6351, 6352...),
// số dư TK 635 (tổng hợp) không tự động cập nhật. Cần account_tree để CTE đệ quy tính tổng.

return function (PDO $pdo) {
    // MS 23: đổi từ account → account_tree để tự động tính tổng các TK con của 635
    $stmt = $pdo->prepare(
        "UPDATE fs_line_items
         SET formula_type = 'account_tree'
         WHERE statement = 'BC02' AND ma_so = '23' AND formula_type != 'account_tree'"
    );
    $stmt->execute();
    $affected = $stmt->rowCount();
    echo "  MS 23 (Chi phí tài chính): formula_type → account_tree ($affected rows)\n";

    // MS 24: đổi formula_detail từ 635 → 6351 (chỉ lãi vay)
    $stmt = $pdo->prepare(
        "UPDATE fs_line_items
         SET formula_detail = '6351'
         WHERE statement = 'BC02' AND ma_so = '24'"
    );
    $stmt->execute();
    $affected = $stmt->rowCount();
    echo "  MS 24 (Chi phí đi vay): formula_detail → 6351 ($affected rows)\n";
};
