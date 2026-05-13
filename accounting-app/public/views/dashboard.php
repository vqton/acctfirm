<?php
$title = 'Dashboard';
$activeMenu = 'dashboard';
ob_start();
?>
<div class="toolbar">
    <h5>Dashboard</h5>
</div>
<div class="row g-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-box" style="font-size:32px;color:#4f6ef7;"></i>
                <h6 class="mt-3">Danh mục vật tư</h6>
                <p class="text-muted small">Quản lý vật tư, hàng hóa</p>
                <a href="/danh-muc/vat-tu" class="btn btn-outline-primary btn-sm">Đi đến</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-people" style="font-size:32px;color:#10b981;"></i>
                <h6 class="mt-3">Danh mục khách hàng</h6>
                <p class="text-muted small">Quản lý khách hàng, công nợ</p>
                <a href="/danh-muc/khach-hang" class="btn btn-outline-primary btn-sm">Đi đến</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-truck" style="font-size:32px;color:#f59e0b;"></i>
                <h6 class="mt-3">Danh mục nhà cung cấp</h6>
                <p class="text-muted small">Quản lý nhà cung cấp</p>
                <a href="/danh-muc/nha-cung-cap" class="btn btn-outline-primary btn-sm">Đi đến</a>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
