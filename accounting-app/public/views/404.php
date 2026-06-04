<?php
header('HTTP/1.0 404 Not Found');
$code = 404;
$title = 'Không tìm thấy';
$message = \Accounting\Interfaces\HTTP\HttpError::$message ?? 'Trang hoặc tài nguyên bạn yêu cầu không tồn tại.';
ob_start(); ?>
<div class="container py-5 text-center" style="max-width:500px;">
    <div class="mb-4">
        <svg width="96" height="96" viewBox="0 0 24 24" fill="none" stroke="#6d7a8a" stroke-width="1.2">
            <circle cx="12" cy="12" r="10"/>
            <path d="M16 16s-1.5-2-4-2-4 2-4 2" stroke-linecap="round"/>
            <line x1="9" y1="9" x2="9.01" y2="9" stroke-linecap="round" stroke-width="2"/>
            <line x1="15" y1="9" x2="15.01" y2="9" stroke-linecap="round" stroke-width="2"/>
        </svg>
    </div>
    <h1 style="font-size:72px;font-weight:800;color:#d0d5dd;line-height:1;">404</h1>
    <h5 class="mt-2 mb-3" style="font-weight:600;">Không tìm thấy trang</h5>
    <p class="text-muted mb-4"><?= htmlspecialchars($message) ?></p>
    <div class="d-flex justify-content-center gap-2">
        <a href="/" class="btn btn-primary">Về trang chủ</a>
        <button onclick="history.back()" class="btn btn-outline-secondary">Quay lại</button>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>