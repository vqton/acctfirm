<?php // Màn hình: Trang thông báo lỗi hệ thống
$title = $title ?? 'Lỗi'; ob_start(); ?>
<div class="container py-4">
    <h5><?= $title ?></h5>
    <p><?= htmlspecialchars(\Accounting\Interfaces\HTTP\HttpError::$message) ?></p>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>