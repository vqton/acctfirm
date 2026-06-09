<?php
// Migration 126: Journal attachments — lưu file đính kèm cho bút toán
// Nghiệp vụ: Hỗ trợ đính kèm hóa đơn, chứng từ gốc vào bút toán kế toán (TT99 Điều 16)
// Rủi ro: File upload không validate → có thể upload file độc hại
return function (PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS journal_attachments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        transaction_id VARCHAR(50) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        stored_name VARCHAR(255) NOT NULL,
        mime_type VARCHAR(100) NOT NULL DEFAULT 'application/octet-stream',
        file_size INT UNSIGNED NOT NULL DEFAULT 0,
        description VARCHAR(500) DEFAULT NULL,
        uploaded_by VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_attachment_transaction (transaction_id),
        INDEX idx_attachment_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensure upload directory exists
    $uploadDir = __DIR__ . '/../../public/uploads/attachments';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
};
