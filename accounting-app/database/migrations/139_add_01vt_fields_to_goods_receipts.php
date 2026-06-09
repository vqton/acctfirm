<?php
// MẪU 01-VT: Thêm các trường còn thiếu theo TT 99/2025/TT-BTC
//
// P0: Cột 1 (SL theo CT) + Số hóa đơn/lệnh nhập
// P1: Người giao hàng + Địa điểm kho + Số CT gốc kèm theo
//
// Thay đổi:
//   goods_receipts:
//     + invoice_ref VARCHAR(100)      — Số hóa đơn / lệnh nhập
//     + invoice_date DATE             — Ngày hóa đơn
//     + deliverer_name VARCHAR(255)   — Họ tên người giao hàng
//     + warehouse_location VARCHAR(255) — Địa điểm kho
//     + attach_doc VARCHAR(255)       — Số chứng từ gốc kèm theo
//   goods_receipt_lines:
//     + qty_in_document DECIMAL(15,2) — Cột 1: Số lượng theo chứng từ
return function (PDO $pdo) {
    // === goods_receipts ===
    $pdo->exec("ALTER TABLE goods_receipts ADD COLUMN IF NOT EXISTS invoice_ref VARCHAR(100) NULL COMMENT 'Số hóa đơn/lệnh nhập'");
    $pdo->exec("ALTER TABLE goods_receipts ADD COLUMN IF NOT EXISTS invoice_date DATE NULL COMMENT 'Ngày hóa đơn'");
    $pdo->exec("ALTER TABLE goods_receipts ADD COLUMN IF NOT EXISTS deliverer_name VARCHAR(255) NULL COMMENT 'Họ tên người giao hàng'");
    $pdo->exec("ALTER TABLE goods_receipts ADD COLUMN IF NOT EXISTS warehouse_location VARCHAR(255) NULL COMMENT 'Địa điểm kho'");
    $pdo->exec("ALTER TABLE goods_receipts ADD COLUMN IF NOT EXISTS attach_doc VARCHAR(255) NULL COMMENT 'Số chứng từ gốc kèm theo'");

    // === goods_receipt_lines ===
    $pdo->exec("ALTER TABLE goods_receipt_lines ADD COLUMN IF NOT EXISTS qty_in_document DECIMAL(15,2) DEFAULT 0 COMMENT 'Cột 1: Số lượng theo chứng từ'");
};
