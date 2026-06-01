<?php
// BC01: Sửa formula_detail cho chỉ tiêu thuế — dùng composite pattern (account_tree) để tự động
// tính tổng số dư các tài khoản con thay vì liệt kê thủ công từng TK leaf.
//
// Composite: formula_detail chỉ cần mã TK tổng hợp (133, 333), FsService tự đệ quy tính SUM.
// Thay đổi này ngăn toàn bộ class bug "control account balance = 0" trong tương lai.
//
// Migration này chuyển MS 162 từ account + leaf list sang account_tree + control account.
return function (PDO $pdo) {
    // MS 162 — Thuế GTGT được khấu trừ
    // Trước: formula_type='account', formula_detail='1331,1332'
    // Sau:   formula_type='account_tree', formula_detail='133'
    // Lý do: 133 là TK tổng hợp có con 1331,1332. account_tree tự động sum → không cần liệt kê.
    $pdo->exec("UPDATE fs_line_items
                 SET formula_type = 'account_tree', formula_detail = '133'
                 WHERE statement = 'BC01' AND ma_so = '162'");

    // MS 163 — Giữ nguyên 1383,333 (cần BA phân tích thêm về cách xác định khoản phải thu thuế)
    // 333 vẫn là control account, nhưng khoản mục này cần phân tích nghiệp vụ cụ thể
    // (các khoản phải thu từ NSNN không chỉ đơn thuần là số dư bên Nợ TK thuế)

    // MS 314 — Thuế và các khoản phải nộp NN (NH)
    // Giữ nguyên formula_detail='33311,...,3339' vì MS 314 chỉ lấy một số TK con của 333
    // (các TK con của 333 có bản chất ngắn hạn). account_tree với '333' sẽ lấy cả các TK con
    // dài hạn không mong muốn.
    $pdo->exec("UPDATE fs_line_items SET formula_detail = '33311,33312,3332,3333,3334,3335,3336,3337,33381,33382,3339'
                 WHERE statement = 'BC01' AND ma_so = '314'");

    // MS 333 — Thuế và khoản phải nộp NN (DH): giữ nguyên '333' vì trong thực tế
    // thuế phải nộp luôn là ngắn hạn (VAT, CIT, PIT đều ≤ 12 tháng).
    // Không có trường hợp thực tế nào thuế phải nộp được phân loại dài hạn.
    // Nếu có kỳ hạn trả nợ thuế > 12 tháng, cần phân tích riêng và cập nhật sau.
};
