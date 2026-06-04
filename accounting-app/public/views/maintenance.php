<?php
header('HTTP/1.0 503 Service Unavailable');
$code = 503;
$title = 'Đang bảo trì';
$message = \Accounting\Interfaces\HTTP\HttpError::$message ?? 'Hệ thống đang được bảo trì định kỳ. Vui lòng quay lại sau ít phút.';
ob_start(); ?>
<div class="container py-5 text-center" style="max-width:500px;">
    <div class="mb-4">
        <svg width="96" height="96" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="1.2">
            <circle cx="12" cy="12" r="10"/>
            <polyline points="12 6 12 12 16 14" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>
    <h1 style="font-size:72px;font-weight:800;color:#d0d5dd;line-height:1;">503</h1>
    <h5 class="mt-2 mb-3" style="font-weight:600;">Hệ thống đang bảo trì</h5>
    <p class="text-muted mb-4"><?= htmlspecialchars($message) ?></p>
    <div class="d-flex justify-content-center">
        <a href="/" class="btn btn-primary">Thử lại</a>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>