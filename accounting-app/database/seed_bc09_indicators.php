<?php
// Seed dữ liệu chỉ tiêu BC09 (Mẫu B09-DN) theo Thông tư 99/2025/TT-BTC
// Section V: Thông tin bổ sung BC01 (auto-calc từ số dư tài khoản)
// Section VI-VII: các phần khác có thể seed sau
//
// Cách dùng: php database/seed_bc09_indicators.php
// hoặc require trong migration.

require_once __DIR__ . '/../config/database.php';

function seedBc09Indicators(PDO $pdo): array
{
    $indicators = [
        // ========== SECTION V: Thông tin bổ sung Bảng CĐKT (BC01) ==========
        ['V', 'V.01', 'Tiền và tương đương tiền', '', '1111,1112,1113,1121,1122', 1, 1, null, 10],
        ['V', 'V.02', 'Đầu tư tài chính ngắn hạn', '', '121,128', 1, 1, null, 20],
        ['V', 'V.03', 'Các khoản phải thu ngắn hạn', '', '131,136,138,331', 1, 1, null, 30],
        ['V', 'V.04', 'Hàng tồn kho', '', '152,153,154,155,156,157', 1, 1, null, 40],
        ['V', 'V.05', 'Tài sản cố định hữu hình', '211-214', '211,212,214', 1, 1, null, 50],
        ['V', 'V.06', 'Tài sản cố định vô hình', '213-2147', '213,2147', 1, 1, null, 60],
        ['V', 'V.07', 'Chi phí trả trước', '', '242', 1, 1, null, 70],
        ['V', 'V.08', 'Đầu tư tài chính dài hạn', '', '221,222,228', 1, 1, null, 80],
        ['V', 'V.09', 'Phải thu dài hạn', '', '241', 1, 1, null, 90],
        ['V', 'V.10', 'Vay và nợ thuê tài chính', '', '341', 1, 1, null, 100],
        ['V', 'V.11', 'Vốn chủ sở hữu', '', '411,412,413,414,415,416,417,418,419,421', 1, 1, null, 110],

        // ========== SECTION VI: Doanh thu, chi phí (auto-calc) ==========
        ['VI', 'VI.01', 'Doanh thu bán hàng thuần', '', '511', 1, 1, null, 10],
        ['VI', 'VI.02', 'Giá vốn hàng bán', '', '632', 1, 1, null, 20],
        ['VI', 'VI.03', 'Chi phí bán hàng', '', '641', 1, 1, null, 30],
        ['VI', 'VI.04', 'Chi phí quản lý doanh nghiệp', '', '642', 1, 1, null, 40],
        ['VI', 'VI.05', 'Chi phí tài chính', '', '635', 1, 1, null, 50],
        ['VI', 'VI.06', 'Chi phí thuế TNDN', '', '821', 1, 1, null, 60],

        // ========== SECTION VII: Các khoản mục ngoài BC ==========
        ['VII', 'VII.01', 'Nợ tiềm tàng', '', '', 0, 0, null, 10],
        ['VII', 'VII.02', 'Cam kết thuê hoạt động', '', '', 0, 0, null, 20],
        ['VII', 'VII.03', 'Giao dịch với bên liên quan', '', '', 0, 0, null, 30],
    ];

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO bc09_config
         (section_code, indicator_code, indicator_name, formula_expression, account_codes,
          is_auto_calc, is_required, parent_code, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $count = 0;
    foreach ($indicators as $row) {
        $stmt->execute($row);
        $count += $stmt->rowCount();
    }

    return ['seeded' => $count, 'total' => count($indicators)];
}

// CLI entry point
if (PHP_SAPI === 'cli' && !isset($_SERVER['REQUEST_METHOD'])) {
    $dbConfig = require __DIR__ . '/../config/database.php';
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'], $dbConfig['password'], $dbConfig['options']
    );
    $result = seedBc09Indicators($pdo);
    echo "Seeded {$result['seeded']}/{$result['total']} BC09 indicators\n";
}
