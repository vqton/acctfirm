<?php
return function (PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id VARCHAR(50) PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) DEFAULT NULL,
        status VARCHAR(20) DEFAULT "active",
        last_login TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS roles (
        id VARCHAR(50) PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description VARCHAR(255) DEFAULT NULL,
        is_system TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS role_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_id VARCHAR(50) NOT NULL,
        module VARCHAR(50) NOT NULL,
        can_view TINYINT(1) DEFAULT 0,
        can_create TINYINT(1) DEFAULT 0,
        can_edit TINYINT(1) DEFAULT 0,
        can_delete TINYINT(1) DEFAULT 0,
        can_post TINYINT(1) DEFAULT 0,
        can_print TINYINT(1) DEFAULT 0,
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
        UNIQUE KEY uk_role_module (role_id, module)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS user_roles (
        user_id VARCHAR(50) NOT NULL,
        role_id VARCHAR(50) NOT NULL,
        PRIMARY KEY (user_id, role_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Seed default roles
    $roles = [
        ['admin', 'Quản trị dữ liệu', 'Toàn quyền hệ thống', 1],
        ['ke_toan_truong', 'Kế toán trưởng', 'Quản lý toàn bộ nghiệp vụ kế toán', 1],
        ['kt_tien', 'Kế toán vốn bằng tiền', 'Quản lý thu chi tiền mặt, ngân hàng', 1],
        ['kt_mua', 'Kế toán mua hàng', 'Quản lý mua hàng, công nợ nhà cung cấp', 1],
        ['kt_ban', 'Kế toán bán hàng', 'Quản lý bán hàng, công nợ khách hàng', 1],
        ['kt_kho', 'Kế toán kho', 'Quản lý hàng tồn kho, kiểm kê', 1],
        ['kt_thue', 'Kế toán thuế', 'Quản lý thuế GTGT, TNDN, TNCN', 1],
        ['lanh_dao', 'Lãnh đạo', 'Xem báo cáo, không nhập liệu', 1],
        ['kiem_toan', 'Kiểm toán', 'Xem toàn bộ dữ liệu, không thay đổi', 1],
    ];
    $insert = $pdo->prepare('INSERT IGNORE INTO roles (id, name, description, is_system) VALUES (?, ?, ?, ?)');
    foreach ($roles as $r) $insert->execute($r);

    // Seed admin user
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->prepare('INSERT IGNORE INTO users (id, username, password_hash, full_name) VALUES (?, ?, ?, ?)')
        ->execute(['admin', 'admin', $hash, 'Quản trị viên']);
    $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)')
        ->execute(['admin', 'admin']);

    // Seed permissions for admin (all modules, all permissions)
    $modules = ['cash','bank','gl','master_data','inventory','reconciliation','report','audit','system'];
    $permInsert = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete, can_post, can_print) VALUES (?, ?, 1, 1, 1, 1, 1, 1)');
    foreach ($modules as $m) $permInsert->execute(['admin', $m]);

    // Seed permissions for other roles
    $rolePerms = [
        'ke_toan_truong' => ['cash'=>1,'bank'=>1,'gl'=>1,'master_data'=>1,'inventory'=>1,'reconciliation'=>1,'report'=>1,'audit'=>1,'system'=>1],
        'kt_tien' => ['cash'=>1,'bank'=>1,'gl'=>0,'master_data'=>1,'inventory'=>0,'reconciliation'=>1,'report'=>1,'audit'=>0,'system'=>0],
        'kt_mua' => ['cash'=>0,'bank'=>0,'gl'=>0,'master_data'=>1,'inventory'=>1,'reconciliation'=>0,'report'=>1,'audit'=>0,'system'=>0],
        'kt_ban' => ['cash'=>0,'bank'=>0,'gl'=>0,'master_data'=>1,'inventory'=>1,'reconciliation'=>0,'report'=>1,'audit'=>0,'system'=>0],
        'kt_kho' => ['cash'=>0,'bank'=>0,'gl'=>0,'master_data'=>1,'inventory'=>1,'reconciliation'=>0,'report'=>1,'audit'=>0,'system'=>0],
        'kt_thue' => ['cash'=>0,'bank'=>0,'gl'=>0,'master_data'=>1,'inventory'=>0,'reconciliation'=>0,'report'=>1,'audit'=>0,'system'=>0],
        'lanh_dao' => ['cash'=>1,'bank'=>1,'gl'=>1,'master_data'=>1,'inventory'=>1,'reconciliation'=>1,'report'=>1,'audit'=>0,'system'=>0],
        'kiem_toan' => ['cash'=>1,'bank'=>1,'gl'=>1,'master_data'=>1,'inventory'=>1,'reconciliation'=>1,'report'=>1,'audit'=>1,'system'=>0],
    ];
    foreach ($rolePerms as $roleId => $mods) {
        foreach ($mods as $mod => $view) {
            if (!$view) continue;
            if ($roleId === 'lanh_dao' || $roleId === 'kiem_toan') {
                $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, module, can_view) VALUES (?, ?, 1)')->execute([$roleId, $mod]);
            } else {
                $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete, can_post, can_print) VALUES (?, ?, 1, 1, 1, 1, 1, 1)')
                    ->execute([$roleId, $mod]);
            }
        }
    }
};
