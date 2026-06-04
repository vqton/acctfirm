<?php
header('HTTP/1.0 500 Internal Server Error');
$code = 500;
$title = 'Lỗi máy chủ';
$message = \Accounting\Interfaces\HTTP\HttpError::$message ?? 'Đã xảy ra lỗi không mong muốn. Vui lòng thử lại sau.';
ob_start(); ?>
<div class="container py-5 text-center" style="max-width:500px;">
    <div class="mb-4">
        <svg width="96" height="96" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="1.2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12" stroke-linecap="round"/>
            <line x1="12" y1="16" x2="12.01" y2="16" stroke-linecap="round" stroke-width="2"/>
        </svg>
    </div>
    <h1 style="font-size:72px;font-weight:800;color:#d0d5dd;line-height:1;">500</h1>
    <h5 class="mt-2 mb-3" style="font-weight:600;">Lỗi máy chủ nội bộ</h5>
    <p class="text-muted mb-4"><?= htmlspecialchars($message) ?></p>
    <div class="d-flex justify-content-center gap-2">
        <a href="/" class="btn btn-primary">Về trang chủ</a>
        <button onclick="history.back()" class="btn btn-outline-secondary">Quay lại</button>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>