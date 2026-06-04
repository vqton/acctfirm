<?php
header('HTTP/1.0 403 Forbidden');
$code = 403;
$title = 'Truy cập bị từ chối';
$message = \Accounting\Interfaces\HTTP\HttpError::$message ?? 'Bạn không có quyền truy cập tài nguyên này.';
ob_start(); ?>
<div class="container py-5 text-center" style="max-width:500px;">
    <div class="mb-4">
        <svg width="96" height="96" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="1.2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0110 0v4" stroke-linecap="round"/>
            <circle cx="12" cy="16" r="1"/>
        </svg>
    </div>
    <h1 style="font-size:72px;font-weight:800;color:#d0d5dd;line-height:1;">403</h1>
    <h5 class="mt-2 mb-3" style="font-weight:600;">Truy cập bị từ chối</h5>
    <p class="text-muted mb-4"><?= htmlspecialchars($message) ?></p>
    <div class="d-flex justify-content-center gap-2">
        <a href="/" class="btn btn-primary">Về trang chủ</a>
        <button onclick="history.back()" class="btn btn-outline-secondary">Quay lại</button>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>