<?php

// Kết nối cơ sở dữ liệu kế toán — MySQL/MariaDB, utf8mb4 cho tiếng Việt
// RỦI RO: Thông tin kết nối DB lưu dưới dạng plain text — không được commit vào public repo
// Cân nhắc: Dùng biến môi trường (.env) cho production để tránh lộ mật khẩu
return [
    'host' => '127.0.0.1',
    'dbname' => 'accounting_db',
    'username' => 'dev',
    'password' => '123456',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,    // Ném exception khi SQL lỗi — không cho phép silent fail
        // RỦI RO: Nếu không có ERRMODE_EXCEPTION, lỗi SQL sẽ bị bỏ qua → sai số dư tài khoản
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Trả về mảng kết hợp theo tên cột
        PDO::ATTR_EMULATE_PREPARES => false,              // Real prepared statement — bảo vệ chống SQL injection
        // RỦI RO: Nếu EMULATE_PREPARES = true, MySQL driver tự động escape — dễ quên bind param
        PDO::ATTR_PERSISTENT => true,                     // Kết nối Persistent — giảm overhead kết nối mỗi request
    ],
];