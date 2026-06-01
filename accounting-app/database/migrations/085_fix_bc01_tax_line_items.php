<?php
// BC01: Sửa formula_detail cho chỉ tiêu thuế — dùng tài khoản chi tiết thay vì tài khoản tổng hợp
// Bug: MS 162, MS 163, MS 314, MS 333 dùng control account (133, 333) → balance luôn = 0
// Fix: Thay bằng sub-accounts (1331,1332 và tất cả TK con của 333)
return function (PDO $pdo) {
    // MS 162 — Thuế GTGT được khấu trừ: 133 (control) → 1331,1332
    $pdo->exec("UPDATE fs_line_items SET formula_detail = '1331,1332'
                 WHERE statement = 'BC01' AND ma_so = '162'");

    // MS 163 — Giữ nguyên 1383,333 (cần BA phân tích thêm về cách xác định khoản phải thu thuế)
    // 333 vẫn là control account, nhưng khoản mục này cần phân tích nghiệp vụ cụ thể
    // (các khoản phải thu từ NSNN không chỉ đơn thuần là số dư bên Nợ TK thuế)

    // MS 314 — Thuế và các khoản phải nộp NN (NH): 333 (control) → leaf-level sub-accounts
    // 3331 (control, balance=0) → 33311,33312 (leaf). 3338 (control) → 33381,33382 (leaf)
    $pdo->exec("UPDATE fs_line_items SET formula_detail = '33311,33312,3332,3333,3334,3335,3336,3337,33381,33382,3339'
                 WHERE statement = 'BC01' AND ma_so = '314'");

    // MS 333 — Thuế và khoản phải nộp NN (DH): giữ nguyên '333' vì trong thực tế
    // thuế phải nộp luôn là ngắn hạn (VAT, CIT, PIT đều ≤ 12 tháng).
    // Không có trường hợp thực tế nào thuế phải nộp được phân loại dài hạn.
    // Nếu có kỳ hạn trả nợ thuế > 12 tháng, cần phân tích riêng và cập nhật sau.
};
