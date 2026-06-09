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
    // Account codes use our system's chart of accounts (not TT200 sub-account codes).
    // FsNotesService::calculateIndicator() uses getTreeBalance() which recursively
    // sums all sub-accounts via MySQL CTE, so using control account codes is sufficient.
    $indicators = [
        // ========== SECTION V: Thông tin bổ sung Bảng CĐKT (BC01) ==========
        ['V', 'V.01', 'Tiền và tương đương tiền', '', '111,112,113', 1, 1, null, 10],
        ['V', 'V.02', 'Đầu tư tài chính ngắn hạn', '', '121,128', 1, 1, null, 20],
        ['V', 'V.03', 'Các khoản phải thu ngắn hạn', '', '131,136,138,331', 1, 1, null, 30],
        ['V', 'V.04', 'Hàng tồn kho', '', '152,153,154,155,156,157,158', 1, 1, null, 40],
        ['V', 'V.05', 'Tài sản cố định hữu hình', '211-214', '211,212,214', 1, 1, null, 50],
        ['V', 'V.06', 'Tài sản cố định vô hình', '213-2147', '213,2147', 1, 1, null, 60],
        ['V', 'V.07', 'Chi phí chờ phân bổ', '', '242', 1, 1, null, 70],
        ['V', 'V.08', 'Đầu tư tài chính dài hạn', '', '221,222,228', 1, 1, null, 80],
        ['V', 'V.09', 'Phải thu dài hạn', '', '131,136,138,331,141', 1, 1, null, 90],
        ['V', 'V.10', 'Vay và nợ thuê tài chính', '', '341,343', 1, 1, null, 100],
        ['V', 'V.11', 'Vốn chủ sở hữu', '', '411,412,413,414,415,416,417,418,419,421', 1, 1, null, 110],
        ['V', 'V.12', 'Tài sản sinh học', '', '215', 1, 1, null, 120],
        ['V', 'V.13', 'Bất động sản đầu tư', '', '217,2147', 1, 1, null, 130],

        // ========== SECTION VI: Doanh thu, chi phí (auto-calc) ==========
        ['VI', 'VI.01', 'Doanh thu bán hàng và CCDV', '', '511', 1, 1, null, 10],
        ['VI', 'VI.02', 'Các khoản giảm trừ doanh thu', '', '521', 1, 1, null, 20],
        ['VI', 'VI.03', 'Doanh thu thuần', '511-521', '511,521', 1, 1, null, 30],
        ['VI', 'VI.04', 'Giá vốn hàng bán', '', '632', 1, 1, null, 40],
        ['VI', 'VI.05', 'Chi phí bán hàng', '', '641', 1, 1, null, 50],
        ['VI', 'VI.06', 'Chi phí quản lý doanh nghiệp', '', '642', 1, 1, null, 60],
        ['VI', 'VI.07', 'Chi phí tài chính', '', '635', 1, 1, null, 70],
        ['VI', 'VI.08', 'Chi phí đi vay', '', '635', 1, 1, null, 80],
        ['VI', 'VI.09', 'Thu nhập khác', '', '711', 1, 1, null, 90],
        ['VI', 'VI.10', 'Chi phí khác', '', '811', 1, 1, null, 100],
        ['VI', 'VI.11', 'Chi phí thuế TNDN hiện hành', '', '8211', 1, 1, null, 110],
        ['VI', 'VI.12', 'Chi phí thuế TNDN hoãn lại', '', '8212', 1, 1, null, 120],

        // ========== SECTION VII: Các khoản mục ngoài BC ==========
        ['VII', 'VII.01', 'Nợ tiềm tàng, cam kết và các khoản mục ngoài BC', '', '', 0, 0, null, 10],
        ['VII', 'VII.02', 'Cam kết thuê hoạt động', '', '', 0, 0, null, 20],
        ['VII', 'VII.03', 'Giao dịch với bên liên quan', '', '', 0, 0, null, 30],
        ['VII', 'VII.04', 'Tài sản thế chấp, cầm cố', '', '', 0, 0, null, 40],
        ['VII', 'VII.05', 'Các khoản bảo lãnh', '', '', 0, 0, null, 50],
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
