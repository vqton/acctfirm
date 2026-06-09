<?php
// NGHIỆP VỤ: Di chuyển menu items bị đặt sai section
//
// Audit phát hiện 4 items thuộc Hệ thống (admin) nhưng là nghiệp vụ kế toán:
//   1. Hệ thống tài khoản (COA)  → Kế toán tổng hợp → Nhập liệu
//      - Lý do: COA là danh mục kế toán nền tảng, không phải admin
//   2. Số dư đầu kỳ               → Kế toán tổng hợp → Nhập liệu
//      - Lý do: Nhập số dư đầu kỳ là nghiệp vụ kế toán, không phải admin
//   3. Giao dịch nội bộ          → Kế toán tổng hợp → Nhập liệu
//      - Lý do: Bút toán nội bộ là journal entry, không phải admin
//   4. Quản lý kỳ                → Kế toán tổng hợp → Xử lý cuối kỳ
//      - Lý do: Kỳ kế toán là nghiệp vụ kế toán (đóng/mở kỳ), không phải admin
//
return function (PDO $pdo) {
    // Tìm sub-heading IDs trong gl_report bằng label (không hardcode ID)
    $glNlId = $pdo->query(
        "SELECT id FROM menu_items WHERE section='gl_report' AND is_heading=1 AND label='Nhập liệu'"
    )->fetchColumn();
    $glXlId = $pdo->query(
        "SELECT id FROM menu_items WHERE section='gl_report' AND is_heading=1 AND label='Xử lý cuối kỳ'"
    )->fetchColumn();

    if (!$glNlId || !$glXlId) {
        throw new \RuntimeException('Không tìm thấy sub-heading gl_report. Chạy migration 129 trước.');
    }

    // 1. Hệ thống tài khoản (COA) → gl_report → Nhập liệu, sort trước chứng từ ghi sổ
    $pdo->prepare(
        "UPDATE menu_items SET section='gl_report', parent_id=?, sort_order=179 WHERE section='system' AND label='Hệ thống tài khoản' AND is_active=1"
    )->execute([$glNlId]);

    // 2. Số dư đầu kỳ → gl_report → Nhập liệu
    $pdo->prepare(
        "UPDATE menu_items SET section='gl_report', parent_id=?, sort_order=180 WHERE section='system' AND label='Số dư đầu kỳ' AND is_active=1"
    )->execute([$glNlId]);

    // 3. Giao dịch nội bộ → gl_report → Nhập liệu
    $pdo->prepare(
        "UPDATE menu_items SET section='gl_report', parent_id=?, sort_order=183 WHERE section='system' AND label='Giao dịch nội bộ' AND is_active=1"
    )->execute([$glNlId]);

    // 4. Quản lý kỳ → gl_report → Xử lý cuối kỳ
    $pdo->prepare(
        "UPDATE menu_items SET section='gl_report', parent_id=?, sort_order=185 WHERE section='system' AND label='Quản lý kỳ' AND is_active=1"
    )->execute([$glXlId]);
};
