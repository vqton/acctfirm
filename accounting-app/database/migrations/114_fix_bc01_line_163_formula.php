<?php
// BC01 imbalance fix: line 163 "Thuế và khoản khác phải thu NN" wrongly includes
// 333x (tax payable) accounts on the asset side. This causes double-counting
// of balances like 3334 (Thuế TNDN) which also appear on the liability side (line 314).
//
// Root cause: 3334 with credit balance 2,800,000 was counted in BOTH assets (line 163)
// and liabilities (line 314), making Tổng tài sản ≠ Nợ phải trả + VCSH.
//
// Fix: remove all 333x from line 163 formula_detail. Line 163 now only includes
// 1383 (Phải thu về thuế GTKT) — the only legitimate tax receivable account.
return function (PDO $pdo) {
    $stmt = $pdo->prepare(
        "UPDATE fs_line_items
         SET formula_detail = '1383'
         WHERE statement = 'BC01'
           AND ma_so = '163'
           AND formula_detail LIKE '%333%'"
    );
    $stmt->execute();
    $affected = $stmt->rowCount();
    if ($affected === 0) {
        // Kiểm tra nếu chưa có dòng nào thì insert lại
        $check = $pdo->query(
            "SELECT COUNT(*) FROM fs_line_items WHERE statement = 'BC01' AND ma_so = '163'"
        )->fetchColumn();
        if ($check == 0) {
            $pdo->exec(
                "INSERT IGNORE INTO fs_line_items (statement, ma_so, parent_ma_so, name_vi, formula_type, formula_detail, sign_convention, display_order, is_control, is_total)
                 VALUES ('BC01', '163', NULL, 'Thuế và khoản khác phải thu NN', 'account', '1383', 'positive', 27, 0, 0)"
            );
        }
    }
};
