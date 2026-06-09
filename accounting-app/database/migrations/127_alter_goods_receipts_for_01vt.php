<?php
// MẪU 01-VT: ALTER goods_receipts để hỗ trợ Phiếu nhập kho độc lập (không PO)
//
// Thay đổi:
//   1. po_id: NOT NULL → NULL (cho phép nhập kho không theo đơn đặt hàng)
//   2. Thêm: supplier_name, supplier_address, receipt_type (purchase|production_return|other)
//   3. Thêm: department, created_by, updated_at
//   4. Thêm: total_amount, amount_in_words
//   5. goods_receipt_lines.po_line_id: NOT NULL → NULL
return function (PDO $pdo) {
    // 1. Cho phép nhập kho không PO
    $pdo->exec('ALTER TABLE goods_receipts MODIFY COLUMN po_id VARCHAR(36) NULL');

    // 2. Thêm cột nghiệp vụ cho Mẫu 01-VT
    try { $pdo->exec('ALTER TABLE goods_receipts ADD COLUMN IF NOT EXISTS supplier_name VARCHAR(255) NULL AFTER po_id'); } catch (\Exception $e) {}
    try { $pdo->exec('ALTER TABLE goods_receipts ADD COLUMN IF NOT EXISTS supplier_address VARCHAR(500) NULL'); } catch (\Exception $e) {}
    try { $pdo->exec("ALTER TABLE goods_receipts ADD COLUMN IF NOT EXISTS receipt_type VARCHAR(30) NOT NULL DEFAULT 'purchase'"); } catch (\Exception $e) {}
    try { $pdo->exec('ALTER TABLE goods_receipts ADD COLUMN IF NOT EXISTS department VARCHAR(100) NULL'); } catch (\Exception $e) {}
    try { $pdo->exec('ALTER TABLE goods_receipts ADD COLUMN IF NOT EXISTS created_by VARCHAR(36) NULL'); } catch (\Exception $e) {}
    try { $pdo->exec('ALTER TABLE goods_receipts ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP'); } catch (\Exception $e) {}
    try { $pdo->exec('ALTER TABLE goods_receipts ADD COLUMN IF NOT EXISTS total_amount DECIMAL(15,2) DEFAULT 0'); } catch (\Exception $e) {}
    try { $pdo->exec('ALTER TABLE goods_receipts ADD COLUMN IF NOT EXISTS amount_in_words VARCHAR(500) NULL'); } catch (\Exception $e) {}

    // 3. Cho phép nhập kho không PO line
    try { $pdo->exec('ALTER TABLE goods_receipt_lines MODIFY COLUMN po_line_id VARCHAR(36) NULL'); } catch (\Exception $e) {}
};
