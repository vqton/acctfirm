<?php // Màn hình: Khung bố cục chung của toàn bộ ứng dụng
use Accounting\Infrastructure\Auth; ?>
<?php
header('Content-Type: text/html; charset=utf-8');
$title = $title ?? 'BookWise';
$activeMenu = $activeMenu ?? 'dashboard';

if (!function_exists('isActive')) {
    function isActive($keys, $menu) {
        return in_array($menu, (array)$keys);
    }
}

// === MENU DYNAMIC LOAD ===
// Load menu tree từ MenuService — role-based filtering tự động
$c = $GLOBALS['container'] ?? null;
$menuTree = [];
$periodInfo = null;
$pendingCount = 0;
$overdueCounts = ['ap' => 0, 'ar' => 0];

if ($c && isset($c['menuService'])) {
    $ms = $c['menuService'];
    $menuTree = $ms->getSidebarMenu();
    $periodInfo = $ms->getCurrentPeriod();
    $pendingCount = $ms->getPendingApprovalCount();
    $overdueCounts = $ms->getOverdueCounts();
}
$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $title ?> - BookWise</title>
<link rel="icon" type="image/svg+xml" href="/assets/images/bookwise-icon.svg">
<link href="/assets/css/bootstrap.min.css" rel="stylesheet">
<link href="/assets/css/bootstrap-icons.css" rel="stylesheet">
<link href="/assets/js/components/form-components.css" rel="stylesheet">
<link href="/assets/css/vas-financial.css" rel="stylesheet">
<script src="/assets/js/jquery-3.7.1.min.js"></script>
<script src="/assets/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/components/form-toast.js"></script>
<script src="/assets/js/components/form-confirm.js"></script>
<script src="/assets/js/components/form-modal.js"></script>
<script src="/assets/js/components/form-validation.js"></script>
<script src="/assets/js/components/form-grid.js"></script>
<script src="/assets/js/components/account-picker.js"></script>
<script src="/assets/js/components/partner-picker.js"></script>
<script src="/assets/js/vas-financial.js"></script>
<style>
body { background:#f5f6fa; font-family:'Segoe UI',system-ui,sans-serif; line-height:1.6; }
.sidebar { width:250px; background:#1e2a3a; color:#b4bcc8; position:fixed; top:0; left:0; height:100vh; z-index:1000; display:flex; flex-direction:column; }
.sidebar .brand { padding:16px 20px; background:#15202b; border-bottom:1px solid #2a3546; }
.sidebar .brand h6 { margin:0; color:#fff; font-weight:600; letter-spacing:.3px; }
.sidebar-scroll { flex:1; overflow-y:auto; padding:6px 0; }
.sidebar-scroll::-webkit-scrollbar { width:3px; }
.sidebar-scroll::-webkit-scrollbar-thumb { background:#3a4a5e; }
.nav-section { font-size:10px; text-transform:uppercase; letter-spacing:1px; color:#6d8aaa; padding:14px 20px 4px; font-weight:600; }
.nav-item { border-bottom:1px solid #253141; }
.nav-link-s { display:flex; align-items:center; gap:10px; padding:8px 20px; color:#b4bcc8; text-decoration:none; font-size:13px; cursor:pointer; }
.nav-link-s:hover, .nav-link-s.active { background:#253141; color:#fff; }
.nav-link-s i { width:18px; font-size:14px; color:#6d8aaa; }
.sub-menu { background:#15202b; list-style:none; padding:0; margin:0; }
.sub-menu .nav-link-s { padding-left:48px; font-size:12px; }
.sub-menu .nav-link-s i { font-size:5px; width:auto; color:#5a7a98; }
.sub-level .nav-link-s { padding-left:36px; font-size:12px; font-weight:600; color:#8aabcc; }
.sub-level .nav-link-s i { font-size:12px; width:18px; color:#6d8aaa; }
.sub-level .sub-menu .nav-link-s { padding-left:56px; font-weight:400; color:#b4bcc8; }
.content-area { margin-left:250px; min-height:100vh; }
.topbar { background:#fff; padding:10px 24px; border-bottom:1px solid #e2e6ef; display:flex; align-items:center; gap:16px; }
.topbar h6 { margin:0; font-weight:600; color:#1a2a3a; font-size:15px; }
.topbar .breadcrumb { margin:0; font-size:12px; }
.page-content { padding:20px 24px; }
.toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:8px; }
.toolbar h5 { margin:0; font-weight:600; color:#1a2a3a; }
.toolbar .stats { font-size:13px; color:#6d7a8a; margin-left:12px; }
.card-table { background:#fff; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.06); overflow:hidden; }
.card-table .card-header-x { background:#f8f9fc; padding:10px 16px; border-bottom:1px solid #e2e6ef; display:flex; align-items:center; gap:8px; }
.card-table .card-header-x input { border:1px solid #d0d5dd; border-radius:4px; padding:5px 10px; font-size:13px; max-width:240px; }
.card-table .card-header-x input:focus { outline:none; border-color:#4f6ef7; box-shadow:0 0 0 2px rgba(79,110,247,.15); }
.table { margin-bottom:0; font-size:13px; }
.table thead th { background:#f8f9fc; border-bottom:2px solid #e2e6ef; font-weight:600; color:#1a2a3a; padding:10px 12px; white-space:nowrap; }
.table tbody td { padding:8px 12px; vertical-align:middle; border-bottom:1px solid #f0f0f5; }
.table tbody tr:hover { background:#f5f7ff; }
.table tbody tr:last-child td { border-bottom:1px solid #e2e6ef; }
.badge-status { display:inline-block; padding:2px 10px; border-radius:10px; font-size:11px; font-weight:500; }
.badge-active { background:#d1fae5; color:#065f46; }
.badge-inactive { background:#f3f4f6; color:#6b7280; }
.badge-warning { background:#fef3c7; color:#92400e; }
.badge-danger { background:#fee2e2; color:#991b1b; }
.badge-type { background:#eef2ff; color:#4338ca; }
.btn-action { padding:2px 8px; font-size:12px; border-radius:4px; border:1px solid #d0d5dd; background:#fff; color:#1a2a3a; cursor:pointer; text-decoration:none; }
.btn-action:hover { background:#f3f4f6; border-color:#9ca3af; }
.btn-action-danger { color:#dc2626; border-color:#fca5a5; }
.btn-action-danger:hover { background:#fef2f2; }
.pagination-bar { background:#f8f9fc; padding:8px 16px; border-top:1px solid #e2e6ef; display:flex; justify-content:space-between; align-items:center; font-size:12px; color:#6d7a8a; }
.toast-notification { position:fixed; top:16px; right:16px; z-index:9999; min-width:280px; }
.toast-notification .toast { background:#fff; border-left:4px solid; box-shadow:0 4px 12px rgba(0,0,0,.1); }
.toast-notification .toast-success { border-color:#10b981; }
.toast-notification .toast-error { border-color:#ef4444; }
.empty-state { text-align:center; padding:48px 20px; color:#9ca3af; }
.empty-state i { font-size:48px; margin-bottom:12px; display:block; }
.modal-content { border-radius:10px; border:none; box-shadow:0 8px 32px rgba(0,0,0,.15); }
.modal-header { background:#f8f9fc; border-radius:10px 10px 0 0; border-bottom:1px solid #e2e6ef; padding:14px 20px; }
.modal-header .modal-title { font-weight:600; font-size:16px; }
.modal-footer { border-top:1px solid #e2e6ef; padding:12px 20px; }
.modal-body { padding:20px; }
.modal-body label { font-size:13px; font-weight:500; color:#374151; margin-bottom:2px; display:block; }
.modal-body .form-control, .modal-body .form-select { font-size:13px; border-radius:6px; border-color:#d0d5dd; }
.modal-body .form-control:focus, .modal-body .form-select:focus { border-color:#4f6ef7; box-shadow:0 0 0 2px rgba(79,110,247,.15); }
.confirm-overlay { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.4); display:flex; align-items:center; justify-content:center; z-index:10000; }
.confirm-box { background:#fff; border-radius:10px; padding:24px; max-width:400px; width:90%; box-shadow:0 8px 32px rgba(0,0,0,.15); }
.confirm-box h6 { margin:0 0 8px; font-weight:600; }
.confirm-box p { margin:0 0 20px; color:#6b7280; font-size:14px; }
.confirm-box .d-flex { gap:8px; justify-content:flex-end; }

/* === PERIOD INDICATOR === */
.period-badge { display:flex; align-items:center; gap:6px; padding:4px 10px; margin:8px 12px 4px; border-radius:6px; font-size:11px; font-weight:500; }
.period-badge.status-open { background:#1a3a2a; color:#6fcf97; }
.period-badge.status-closed { background:#3a1a1a; color:#cf6f6f; }
.period-badge i { font-size:12px; }
.period-dot { width:6px; height:6px; border-radius:50%; display:inline-block; }
.period-dot.open { background:#27ae60; box-shadow:0 0 4px #27ae60; }
.period-dot.closed { background:#e74c3c; }

/* === MENU SEARCH === */
.menu-search { position:relative; padding:4px 12px 8px; }
.menu-search i { position:absolute; left:20px; top:12px; font-size:12px; color:#6d8aaa; }
.menu-search input { width:100%; background:#253141; border:1px solid #3a4a5e; border-radius:6px; padding:6px 8px 6px 28px; color:#e0e6ed; font-size:12px; outline:none; }
.menu-search input::placeholder { color:#6d8aaa; }
.menu-search input:focus { border-color:#4f6ef7; }
.menu-search-results { position:absolute; top:100%; left:12px; right:12px; background:#1e2a3a; border:1px solid #3a4a5e; border-radius:6px; max-height:320px; overflow-y:auto; z-index:999; box-shadow:0 8px 24px rgba(0,0,0,.4); }
.menu-search-results a { display:flex; align-items:center; gap:8px; padding:8px 12px; color:#b4bcc8; text-decoration:none; font-size:12px; border-bottom:1px solid #253141; }
.menu-search-results a:hover, .menu-search-results a.active { background:#253141; color:#fff; }
.menu-search-results a:last-child { border-bottom:none; }
.menu-search-results .no-result { padding:12px; color:#6d8aaa; font-size:12px; text-align:center; }

/* === SIDEBAR BADGES === */
.badge-sidebar { background:#4f6ef7; color:#fff; font-size:9px; padding:1px 6px; border-radius:8px; font-weight:600; margin-left:auto; }
.badge-sidebar.warning { background:#f59e0b; }
.badge-sidebar.danger { background:#ef4444; }

/* === SIDEBAR BRAND LOGO === */
.brand { display:flex; flex-direction:column; }
.brand a img { display:block; margin:0 auto; }

/* === RESPONSIVE SIDEBAR === */
@media (max-width:992px) {
    .sidebar { width:60px; overflow:hidden; }
    .sidebar:hover { width:250px; overflow:visible; }
    .sidebar .brand a img { height:32px; }
}
</style>
</head>
<body>

<div class="sidebar" id="appSidebar">
    <div class="brand">
        <a href="/"><img src="/assets/images/bookwise-logo.svg" alt="BookWise" height="40"></a>
        <?php if ($periodInfo): ?>
        <div class="period-badge status-<?= \Accounting\Infrastructure\Helpers::e($periodInfo['status']) ?>">
            <i class="bi bi-calendar3"></i>
            <span><?= \Accounting\Infrastructure\Helpers::e($periodInfo['name']) ?></span>
            <span class="period-dot <?= $periodInfo['status'] === 'open' ? 'open' : 'closed' ?>"></span>
        </div>
        <?php endif; ?>
        <div class="menu-search">
            <i class="bi bi-search"></i>
            <input type="text" id="menuSearchInput" placeholder="Tìm chức năng..." autocomplete="off">
            <div id="menuSearchResults" class="menu-search-results d-none"></div>
        </div>
    </div>
    <div class="sidebar-scroll" id="sidebarScroll">
<?php if (empty($menuTree)): ?>
        <!-- Fallback: menu_items table chưa có dữ liệu -->
        <div class="nav-section">Tổng quan</div>
        <div class="nav-item"><a href="/" class="nav-link-s<?= $activeMenu==='dashboard'?' active':'' ?>"><i class="bi bi-speedometer2"></i> Dashboard</a></div>
        <div class="nav-section">Hệ thống</div>
        <div class="nav-item"><a href="/he-thong/nguoi-dung" class="nav-link-s"><i class="bi bi-gear"></i> Hệ thống</a></div>
<?php else: ?>
<?php foreach ($menuTree as $section):
$secId = preg_replace('/[^a-z0-9]/i', '', $section['section'] ?? 'sec' . $section['id']);
// Check active trong cả children và sub-children
$hasActive = false;
if (!empty($section['children'])) {
    foreach ($section['children'] as $ch) {
        $r = $ch['route'] ?? '';
        if ($r && ($currentUri === $r || $currentUri === $r . '/')) { $hasActive = true; break; }
        if (!empty($ch['children'])) {
            foreach ($ch['children'] as $gc) {
                $gr = $gc['route'] ?? '';
                if ($gr && ($currentUri === $gr || $currentUri === $gr . '/')) { $hasActive = true; break 2; }
            }
        }
    }
}
?>
        <div class="nav-section"><?= \Accounting\Infrastructure\Helpers::e($section['label'] ?? '') ?></div>
        <?php if (!empty($section['children'])): ?>
        <div class="nav-item">
            <a class="nav-link-s" data-bs-toggle="collapse" href="#menu<?= $secId ?>">
                <?php if ($section['icon']): ?><i class="bi <?= \Accounting\Infrastructure\Helpers::e($section['icon']) ?>"></i><?php endif; ?>
                <span><?= \Accounting\Infrastructure\Helpers::e($section['label'] ?? '') ?></span>
                <?php if (!empty($section['badge'])): ?><span class="badge badge-sidebar"><?= \Accounting\Infrastructure\Helpers::e($section['badge']) ?></span><?php endif; ?>
                <i class="bi bi-chevron-right ms-auto"></i>
            </a>
            <div class="collapse sub-menu<?= $hasActive ? ' show' : '' ?>" id="menu<?= $secId ?>">
<?php foreach ($section['children'] as $child):
$subChildren = $child['children'] ?? [];
$hasSubChildren = !empty($subChildren);
$childRoute = $child['route'] ?? '#';
$isChildActive = $childRoute !== '#' && ($currentUri === $childRoute || $currentUri === $childRoute . '/');

if ($hasSubChildren):
    $subId = $secId . 'sub' . ($child['id'] ?? '');
    $subActive = false;
    foreach ($subChildren as $sc) {
        $sr = $sc['route'] ?? '';
        if ($sr && ($currentUri === $sr || $currentUri === $sr . '/')) { $subActive = true; break; }
    }
?>
                <div class="nav-item sub-level">
                    <a class="nav-link-s" data-bs-toggle="collapse" href="#<?= $subId ?>">
                        <?php if ($child['icon']): ?><i class="bi <?= \Accounting\Infrastructure\Helpers::e($child['icon']) ?>"></i><?php endif; ?>
                        <span><?= \Accounting\Infrastructure\Helpers::e($child['label'] ?? '') ?></span>
                        <i class="bi bi-chevron-right ms-auto"></i>
                    </a>
                    <div class="collapse sub-menu<?= $subActive ? ' show' : '' ?>" id="<?= $subId ?>">
<?php foreach ($subChildren as $sc):
$scRoute = $sc['route'] ?? '#';
$scActive = $scRoute !== '#' && ($currentUri === $scRoute || $currentUri === $scRoute . '/');
?>
                        <a href="<?= \Accounting\Infrastructure\Helpers::e($scRoute) ?>" class="nav-link-s<?= $scActive ? ' active' : '' ?>">
                            <span><?= \Accounting\Infrastructure\Helpers::e($sc['label'] ?? '') ?></span>
                        </a>
<?php endforeach; ?>
                    </div>
                </div>
<?php else: ?>
                <a href="<?= \Accounting\Infrastructure\Helpers::e($childRoute) ?>" class="nav-link-s<?= $isChildActive ? ' active' : '' ?>">
                    <?php if ($child['icon']): ?><i class="bi <?= \Accounting\Infrastructure\Helpers::e($child['icon']) ?>"></i><?php endif; ?>
                    <span><?= \Accounting\Infrastructure\Helpers::e($child['label'] ?? '') ?></span>
                    <?php if (!empty($child['badge'])): ?><span class="badge badge-sidebar"><?= \Accounting\Infrastructure\Helpers::e($child['badge']) ?></span><?php endif; ?>
                </a>
<?php endif; ?>
<?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>
    </div>
</div>

<div class="content-area">
    <div class="topbar">
        <a href="/" class="text-decoration-none text-dark"><h6><i class="bi bi-house-door me-1"></i></h6></a>
        <span class="text-muted" style="font-size:12px;">/ <?= $title ?></span>
        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="text-muted" style="font-size:13px;"><i class="bi bi-person-circle me-1"></i><?= \Accounting\Infrastructure\Helpers::e($_SESSION['user']['full_name'] ?? '') ?></span>
            <form method="POST" action="/api/auth/logout" style="display:inline;margin:0;padding:0;">
                <button type="submit" class="btn btn-link text-muted p-0 border-0 align-baseline" style="font-size:13px;text-decoration:none;vertical-align:baseline;">
                    <i class="bi bi-box-arrow-right"></i> Đăng xuất
                </button>
            </form>
        </div>
    </div>
    <div class="page-content">
        <?= $content ?? '' ?>
    </div>
</div>

<div class="toast-notification" id="toastContainer"></div>

<div class="confirm-overlay" id="confirmOverlay" style="display:none;" onclick="if(event.target===this)hideConfirm()">
    <div class="confirm-box" onclick="event.stopPropagation()">
        <h6 id="confirmTitle">Xác nhận</h6>
        <p id="confirmMessage">Bạn có chắc chắn muốn xóa?</p>
        <div>
            <button class="btn btn-sm btn-secondary" onclick="hideConfirm()">Hủy</button>
            <button class="btn btn-sm btn-danger" id="confirmBtn">Xóa</button>
        </div>
    </div>
</div>

<script>
var csrf=<?= json_encode(Auth::csrfToken()) ?>;
function esc(s){return String(s).replace(/[&<>"']/g,function(m){if(m==='&')return'&amp;';if(m==='<')return'&lt;';if(m==='>')return'&gt;';if(m==='"')return'&quot;';return'&#39;';});}

// === VAS FINANCIAL HELPERS ===
// Global shorthands for VAS.fmt — available in ALL views
function fmt(n, opts) { return VAS.fmt(n, opts); }
function fmtZero(n, opts) { return VAS.fmt(n, Object.assign({}, opts||{}, {zeroDash:true})); }
function fmtDrCr(d, c) { return VAS.fmtDrCr(d, c); }

// Global status badge — unified across all views
function statusBadge(status) {
    var labels = {
        draft:'Nháp',submitted:'Chờ duyệt',pending:'Chờ duyệt',
        approved:'Đã duyệt',posted:'Đã ghi sổ',paid:'Đã chi',
        cancelled:'Đã hủy',settled:'Đã hoàn ứng',reversed:'Đã đảo',closed:'Đã đóng',
        active:'Hoạt động',inactive:'Ngừng',
        completed:'Hoàn thành',confirmed:'Đã xác nhận',
        finalised:'Đã chốt',issued:'Đã phát hành',
        matched:'Đã đối chiếu',sent:'Đã gửi',
        running:'Đang chạy',in_progress:'Đang thực hiện',
        written_off:'Đã xóa sổ',prepayment:'Tạm ứng',
        unpaid:'Chưa TT',partial:'Một phần',
        released:'Đã phát hành',costed:'Đã tính giá',
        liquidated:'Đã thanh lý',open:'Đang mở',
        verified:'Đã xác thực',unverified:'Chưa xác thực',
        yes:'Có',no:'Không',
        warning:'Cảnh báo',mismatch:'Lệch',
        pending_approval:'Chờ duyệt',partially_received:'Nhận một phần',
        rejected:'Từ chối',fulfilled:'Đã đặt hàng'
    };
    var classes = {
        draft:'badge-warning',submitted:'badge-info',pending:'badge-info',
        approved:'badge-active',posted:'badge-active',paid:'badge-active',
        cancelled:'badge-inactive',reversed:'badge-inactive',closed:'badge-secondary',
        active:'badge-active',inactive:'badge-inactive',
        completed:'badge-active',confirmed:'badge-active',
        finalised:'badge-success',issued:'badge-active',
        matched:'badge-active',sent:'badge-active',
        running:'badge-warning',in_progress:'badge-warning',
        written_off:'badge-inactive',prepayment:'badge-warning',
        unpaid:'badge-warning',partial:'badge-warning',
        released:'badge-warning',costed:'badge-success',
        liquidated:'badge-light',open:'badge-active',
        verified:'badge-active',unverified:'badge-warning',
        yes:'badge-active',no:'badge-inactive',
        warning:'badge-warning',mismatch:'badge-danger',
        pending_approval:'badge-info',partially_received:'badge-info',
        rejected:'badge-danger',fulfilled:'badge-inactive'
    };
    var label = labels[status] || status;
    var cls = classes[status] || 'badge-secondary';
    return '<span class="badge-status ' + cls + '">' + esc(label) + '</span>';
}

// Backward-compatible: showToast → FormToast (already set by form-toast.js)
// Backward-compatible: hideConfirm → ẩn cả overlay cũ và modal mới
function hideConfirm(){
    document.getElementById('confirmOverlay').style.display='none';
    $('#formConfirmModal').modal('hide');
}

// Backward-compatible: confirmDelete → FormConfirm
function confirmDelete(id,api,name){
    FormConfirm.confirmDelete(api+'/'+id, name, function(){ if(typeof loadData==='function') loadData(); });
}

// Tải danh sách thuế suất VAT active từ API và render vào <select>
// selector: CSS selector của <select>, defaultRate: giá trị mặc định (VD: 10)
function loadVatRates(selector, defaultRate) {
    fetch('/api/vat-rates').then(function(r){return r.json();}).then(function(data){
        var sel = document.querySelector(selector);
        if (!sel) return;
        sel.innerHTML = '';
        if (!data || !data.length) {
            sel.innerHTML = '<option value="10">10%</option>';
            return;
        }
        data.forEach(function(r){
            var opt = document.createElement('option');
            opt.value = r.rate;
            opt.textContent = r.name;
            if (parseFloat(r.rate) === parseFloat(defaultRate)) opt.selected = true;
            sel.appendChild(opt);
        });
    }).catch(function(){
        // Fallback: giữ nguyên option cứng nếu API lỗi
    });
}

$(function(){
    $('.sidebar-scroll .nav-link-s[href="#"]').on('click',function(e){
        e.preventDefault();
        showToast('Chức năng này đang được phát triển. Vui lòng quay lại sau.','info');
    });

    // === BADGE NOTIFICATIONS ===
    function updateBadges() {
        $.get('/api/menu/sidebar', function(data){
            if (!data.badges) return;
            var b = data.badges;
            var $sidebar = $('#sidebarScroll');

            // Badge phê duyệt
            if (b.pending_approvals > 0) {
                var $a = $sidebar.find('a[href*="phe-duyet"]');
                $a.find('.badge-sidebar').remove();
                $('<span class="badge-sidebar danger">' + b.pending_approvals + '</span>').appendTo($a);
            }
            // Badge công nợ phải thu quá hạn
            if (b.overdue_ar > 0) {
                var $a = $sidebar.find('a[href*="cong-no-phai-thu"]');
                $a.find('.badge-sidebar').remove();
                $('<span class="badge-sidebar danger">' + b.overdue_ar + '</span>').appendTo($a);
            }
            // Badge công nợ phải trả quá hạn
            if (b.overdue_ap > 0) {
                var $a = $sidebar.find('a[href*="cong-no-phai-tra"]');
                $a.find('.badge-sidebar').remove();
                $('<span class="badge-sidebar danger">' + b.overdue_ap + '</span>').appendTo($a);
            }
        }).fail(function(){});
    }
    updateBadges();
    setInterval(updateBadges, 60000);
});

// === MENU SEARCH ===
var searchTimer;
$('#menuSearchInput').on('input', function(){
    var q = $(this).val().trim();
    var $results = $('#menuSearchResults');
    if (q.length < 2) { $results.addClass('d-none'); return; }
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function(){
        $.get('/api/menu/search', {q: q}, function(data){
            if (!data.results || !data.results.length) {
                $results.removeClass('d-none').html('<div class="no-result">Không tìm thấy chức năng</div>');
                return;
            }
            var html = '';
            data.results.forEach(function(item){
                var icon = item.icon ? '<i class="bi ' + item.icon + '"></i>' : '<i class="bi bi-circle-fill"></i>';
                html += '<a href="' + (item.route || '#') + '">' + icon + item.label + '</a>';
            });
            $results.removeClass('d-none').html(html);
        }).fail(function(){
            $results.addClass('d-none');
        });
    }, 200);
});
$(document).on('click', function(e) {
    if (!$(e.target).closest('.menu-search').length) {
        $('#menuSearchResults').addClass('d-none');
    }
});
$('#menuSearchInput').on('blur', function(){
    setTimeout(function(){ $('#menuSearchResults').addClass('d-none'); }, 200);
});
</script>
</body>
</html>
