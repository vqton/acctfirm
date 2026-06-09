<?php
// FIX BC02: Sửa seed data theo TT 99/2025/TT-BTC
// 
// Các fix:
// G01 (P1): MS 21 — xóa formula_detail=632 (gây hiểu nhầm là GVHB thông thường)
//           MS 21 là "Lãi/lỗ từ bán, thanh lý BĐS ĐT" — cần nhập tay
// G04 (P2): MS 11 — thêm TK 631 (Chi phí SX) vì TT99 mở rộng Giá vốn gồm cả CP SX
//           formula_detail: '632' → '632,631'
// G99 (P2): MS 24 — set parent_ma_so = '23' để hiển thị đúng cấu trúc phân cấp
//           MS 24 là chỉ tiêu con của MS 23 (Chi phí tài chính)
//
return function (PDO $pdo) {
    // G01: MS 21 — xóa formula_detail gây hiểu nhầm
    $stmt = $pdo->prepare("UPDATE fs_line_items SET formula_detail = '' WHERE statement = 'BC02' AND ma_so = '21'");
    $stmt->execute();

    // G04: MS 11 — thêm TK 631
    $stmt = $pdo->prepare("UPDATE fs_line_items SET formula_detail = '632,631' WHERE statement = 'BC02' AND ma_so = '11'");
    $stmt->execute();

    // G99: MS 24 — set parent_ma_so = '23'
    $stmt = $pdo->prepare("UPDATE fs_line_items SET parent_ma_so = '23' WHERE statement = 'BC02' AND ma_so = '24'");
    $stmt->execute();
};
