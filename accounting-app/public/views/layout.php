<?php use Accounting\Infrastructure\Auth; ?>
<?php
header('Content-Type: text/html; charset=utf-8');
$title = $title ?? 'BookWise';
$activeMenu = $activeMenu ?? 'dashboard';

function isActive($keys, $menu) {
    return in_array($menu, (array)$keys);
}
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $title ?> - BookWise</title>
<link rel="icon" type="image/svg+xml" href="/assets/images/bookwise-icon.svg">
<link href="/assets/css/bootstrap.min.css" rel="stylesheet">
<link href="/assets/css/bootstrap-icons.css" rel="stylesheet">
<script src="/assets/js/jquery-3.7.1.min.js"></script>
<script src="/assets/js/bootstrap.bundle.min.js"></script>
<style>
body { background:#f5f6fa; font-family:'Segoe UI',system-ui,sans-serif; }
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
</style>
</head>
<body>

<div class="sidebar">
    <div class="brand">
        <a href="/" style="display:block;"><img src="/assets/images/bookwise-logo.svg" alt="BookWise" height="40" style="display:block;"></a>
    </div>
    <div class="sidebar-scroll">

        <div class="nav-section">Tổng quan</div>
        <div class="nav-item"><a href="/" class="nav-link-s<?= $activeMenu==='dashboard'?' active':'' ?>"><i class="bi bi-speedometer2"></i> Dashboard</a></div>

        <div class="nav-section">Vốn bằng tiền</div>
        <div class="nav-item">
            <a class="nav-link-s" data-bs-toggle="collapse" href="#menuCash"><i class="bi bi-cash"></i> Vốn bằng tiền <i class="bi bi-chevron-right ms-auto" style="width:auto;font-size:10px;"></i></a>
            <div class="collapse sub-menu<?= isActive(['bank_accounts','cash_receipts','cash_payments','bank_credit','bank_debit','cash_transit','cash_book','petty_cash','bank_reconciliation','cash_reports','fx_revaluation'],$activeMenu)?' show':'' ?>" id="menuCash">
                <a href="/danh-muc/tai-khoan-ngan-hang" class="nav-link-s<?= isActive('bank_accounts',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> TK ngân hàng</a>
                <a href="/thu/quy-tien-mat" class="nav-link-s<?= isActive('cash_receipts',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Phiếu thu</a>
                <a href="/chi/quy-tien-mat" class="nav-link-s<?= isActive('cash_payments',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Phiếu chi</a>
                <a href="/thu/giao-bao-co" class="nav-link-s<?= isActive('bank_credit',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Giấy báo Có</a>
                <a href="/chi/giao-bao-no" class="nav-link-s<?= isActive('bank_debit',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Giấy báo Nợ</a>
                <a href="/thu/tien-dang-chuyen" class="nav-link-s<?= isActive('cash_transit',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Tiền đang chuyển</a>
                <a href="/thu/so-quy-tien-mat" class="nav-link-s<?= isActive('cash_book',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Sổ quỹ tiền mặt</a>
                <a href="/thu/tam-ung" class="nav-link-s<?= isActive('petty_cash',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Tạm ứng</a>
                <a href="/thu/doi-chieu-ngan-hang" class="nav-link-s<?= isActive('bank_reconciliation',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Đối chiếu NH</a>
                <a href="/thu/bao-cao-von-bang-tien" class="nav-link-s<?= isActive('cash_reports',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Báo cáo vốn bằng tiền</a>
                <a href="/thu/danh-gia-lai-ngoai-te" class="nav-link-s<?= isActive('fx_revaluation',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Đánh giá lại ngoại tệ</a>
            </div>
        </div>

        <div class="nav-section">Mua hàng</div>
        <div class="nav-item">
            <a class="nav-link-s" data-bs-toggle="collapse" href="#menuPurchase"><i class="bi bi-cart"></i> Mua hàng <i class="bi bi-chevron-right ms-auto" style="width:auto;font-size:10px;"></i></a>
            <div class="collapse sub-menu<?= isActive(['suppliers','contracts','ap_invoices','ap_aging','ap_statement'],$activeMenu)?' show':'' ?>" id="menuPurchase">
                <a href="/danh-muc/nha-cung-cap" class="nav-link-s<?= isActive('suppliers',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Nhà cung cấp</a>
                <a href="/danh-muc/hop-dong" class="nav-link-s<?= isActive('contracts',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Hợp đồng</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Đơn đặt hàng</a>
                <a href="/mua/cong-no-phai-tra" class="nav-link-s<?= isActive('ap_invoices',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Công nợ phải trả</a>
                <a href="/mua/phan-tich-tuoi-no" class="nav-link-s<?= isActive('ap_aging',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Phân tích tuổi nợ</a>
                <a href="/mua/so-chi-tiet-cong-no" class="nav-link-s<?= isActive('ap_statement',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Sổ chi tiết công nợ</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Bù trừ công nợ</a>
            </div>
        </div>

        <div class="nav-section">Bán hàng</div>
        <div class="nav-item">
            <a class="nav-link-s" data-bs-toggle="collapse" href="#menuSales"><i class="bi bi-bag"></i> Bán hàng <i class="bi bi-chevron-right ms-auto" style="width:auto;font-size:10px;"></i></a>
            <div class="collapse sub-menu<?= isActive(['customers','ar_invoices','ar_aging','ar_statement'],$activeMenu)?' show':'' ?>" id="menuSales">
                <a href="/danh-muc/khach-hang" class="nav-link-s<?= isActive('customers',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Khách hàng</a>
                <a href="/ban/cong-no-phai-thu" class="nav-link-s<?= isActive('ar_invoices',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Công nợ phải thu</a>
                <a href="/ban/phan-tich-tuoi-no" class="nav-link-s<?= isActive('ar_aging',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Phân tích tuổi nợ</a>
                <a href="/ban/so-chi-tiet-cong-no" class="nav-link-s<?= isActive('ar_statement',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Sổ chi tiết công nợ</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Bảng giá & Chiết khấu</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Bù trừ công nợ</a>
            </div>
        </div>

        <div class="nav-section">Hàng tồn kho</div>
        <div class="nav-item">
            <a class="nav-link-s" data-bs-toggle="collapse" href="#menuInventory"><i class="bi bi-box"></i> Hàng tồn kho <i class="bi bi-chevron-right ms-auto" style="width:auto;font-size:10px;"></i></a>
            <div class="collapse sub-menu<?= isActive(['items','ccdc','warehouses','uoms','valuation_methods','receipt','issue','customer_return','transfers','transit','consignment','physical_count','periodic','impairment'],$activeMenu)?' show':'' ?>" id="menuInventory">
                <a href="/danh-muc/vat-tu" class="nav-link-s<?= isActive('items',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Vật tư, hàng hóa</a>
                <a href="/danh-muc/cong-cu-dung-cu" class="nav-link-s<?= isActive('ccdc',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> CCDC</a>
                <a href="/danh-muc/kho" class="nav-link-s<?= isActive('warehouses',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Kho</a>
                <a href="/danh-muc/don-vi-tinh" class="nav-link-s<?= isActive('uoms',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Đơn vị tính</a>
                <a href="/danh-muc/phuong-phap-tinh-gia" class="nav-link-s<?= isActive('valuation_methods',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> PP tính giá</a>
                <a href="/kho/du-phong-giam-gia" class="nav-link-s<?= isActive('impairment',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Dự phòng giảm giá</a>
                <a href="/kho/nhap-kho" class="nav-link-s<?= isActive('receipt',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Nhập kho</a>
                <a href="/kho/xuat-kho" class="nav-link-s<?= isActive('issue',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Xuất kho</a>
                <a href="/kho/hang-ban-tra-lai" class="nav-link-s<?= isActive('customer_return',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Hàng bán trả lại</a>
                <a href="/kho/dieu-chuyen" class="nav-link-s<?= isActive('transfers',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Điều chuyển kho</a>
                <a href="/kho/kiem-ke" class="nav-link-s<?= isActive('physical_count',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Kiểm kê</a>
                <a href="/kho/kiem-ke-dinh-ky" class="nav-link-s<?= isActive('periodic',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Tính giá xuất kho (Định kỳ)</a>
                <a href="/kho/hang-dang-di-duong" class="nav-link-s<?= isActive('transit',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Hàng mua đang đi đường</a>
                <a href="/kho/hang-gui-ban" class="nav-link-s<?= isActive('consignment',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Hàng gửi đi bán</a>
            </div>
        </div>

        <div class="nav-section">TSCĐ & CCDC</div>
        <div class="nav-item">
            <a class="nav-link-s" data-bs-toggle="collapse" href="#menuFA"><i class="bi bi-building"></i> TSCĐ & CCDC <i class="bi bi-chevron-right ms-auto" style="width:auto;font-size:10px;"></i></a>
            <div class="collapse sub-menu<?= isActive(['fixed_assets','depreciation_policies','depreciation'],$activeMenu)?' show':'' ?>" id="menuFA">
                <a href="/danh-muc/tai-san-co-dinh" class="nav-link-s<?= isActive('fixed_assets',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Tài sản cố định</a>
                <a href="/danh-muc/chinh-sach-khau-hao" class="nav-link-s<?= isActive('depreciation_policies',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> CS khấu hao</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Ghi tăng TSCĐ</a>
                <a href="/danh-muc/tai-san-co-dinh/tinh-khau-hao" class="nav-link-s<?= isActive('depreciation',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Tính khấu hao</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Điều chuyển TSCĐ</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Giảm / Thanh lý</a>
            </div>
        </div>

        <div class="nav-section">Tiền lương</div>
        <div class="nav-item">
            <a class="nav-link-s" data-bs-toggle="collapse" href="#menuPayroll"><i class="bi bi-people"></i> Tiền lương <i class="bi bi-chevron-right ms-auto" style="width:auto;font-size:10px;"></i></a>
            <div class="collapse sub-menu<?= isActive(['employees','departments'],$activeMenu)?' show':'' ?>" id="menuPayroll">
                <a href="/danh-muc/nhan-vien" class="nav-link-s<?= isActive('employees',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Nhân viên</a>
                <a href="/danh-muc/phong-ban" class="nav-link-s<?= isActive('departments',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Phòng ban</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Bảng lương</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Tính lương</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Trích bảo hiểm</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Tính thuế TNCN</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Phiếu lương</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Kê khai BHXH</a>
            </div>
        </div>

        <div class="nav-section">Thuế</div>
        <div class="nav-item">
            <a class="nav-link-s" data-bs-toggle="collapse" href="#menuTax"><i class="bi bi-file-text"></i> Thuế <i class="bi bi-chevron-right ms-auto" style="width:auto;font-size:10px;"></i></a>
            <div class="collapse sub-menu<?= isActive(['tax_rates','exchange_rates'],$activeMenu)?' show':'' ?>" id="menuTax">
                <a href="/danh-muc/bieu-thue" class="nav-link-s<?= isActive('tax_rates',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Biểu thuế</a>
                <a href="/danh-muc/ty-gia" class="nav-link-s<?= isActive('exchange_rates',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Tỷ giá</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Kê khai GTGT</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Bảng kê mua / bán</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Quyết toán GTGT</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Quyết toán TNDN</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Quyết toán TNCN</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Hóa đơn điện tử</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Gửi & Nộp thuế</a>
            </div>
        </div>

        <div class="nav-section">Tổng hợp</div>
        <div class="nav-item">
            <a class="nav-link-s" data-bs-toggle="collapse" href="#menuGL"><i class="bi bi-journal"></i> Tổng hợp <i class="bi bi-chevron-right ms-auto" style="width:auto;font-size:10px;"></i></a>
            <div class="collapse sub-menu<?= isActive(['projects','so_cai','journal','trial_balance','period_close'],$activeMenu)?' show':'' ?>" id="menuGL">
                <a href="/danh-muc/he-thong-tai-khoan" class="nav-link-s<?= $activeMenu==='coa'?' active':'' ?>"><i class="bi bi-circle-fill"></i> Hệ thống tài khoản</a>
                <a href="/danh-muc/du-an" class="nav-link-s<?= isActive('projects',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Dự án / Công trình</a>
                <a href="/tong-hop/chung-tu-ghi-so" class="nav-link-s<?= isActive('journal',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Chứng từ ghi sổ</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Bút toán điều chỉnh</a>
                <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Bút toán kết chuyển</a>
                <a href="/tong-hop/khoa-so-cuoi-ky" class="nav-link-s<?= isActive('period_close',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Khóa sổ cuối kỳ</a>
                <a href="/tong-hop/bang-can-doi-so-phat-sinh" class="nav-link-s<?= isActive('trial_balance',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> BCĐ số phát sinh</a>
                <a href="/bao-cao/nhat-ky-chung" class="nav-link-s<?= isActive('general_journal',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Sổ Nhật ký chung</a>
                <a href="/bao-cao/so-cai" class="nav-link-s<?= isActive('so_cai',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Sổ cái</a>
                <a href="/bao-cao/tinh-hinh-tai-chinh" class="nav-link-s<?= isActive('fs_bc01',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Báo cáo tài chính</a>
            </div>
        </div>

        <div class="nav-section">Báo cáo</div>
        <div class="nav-item"><a class="nav-link-s" data-bs-toggle="collapse" href="#menuReports"><i class="bi bi-bar-chart"></i> Báo cáo <i class="bi bi-chevron-right ms-auto" style="width:auto;font-size:10px;"></i></a>
        <div class="collapse sub-menu<?= isActive(['fs_bc01','fs_bc02'],$activeMenu)?' show':'' ?>" id="menuReports">
            <a href="/bao-cao/tinh-hinh-tai-chinh" class="nav-link-s<?= isActive('fs_bc01',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> BC CĐKT (BC 01)</a>
            <a href="/bao-cao/ket-qua-kinh-doanh" class="nav-link-s<?= isActive('fs_bc02',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> KQKD (BC 02)</a>
            <a href="/bao-cao/luu-chuyen-tien-te" class="nav-link-s<?= isActive('fs_bc03',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> LCTT (BC 03)</a>
            <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Thuyết minh BCTC</a>
            <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Báo cáo thuế</a>
            <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Báo cáo quản trị</a>
            <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Tự thiết kế</a>
        </div></div>

        <div class="nav-section">Hệ thống</div>
        <div class="nav-item"><a class="nav-link-s" data-bs-toggle="collapse" href="#menuSys"><i class="bi bi-gear"></i> Hệ thống <i class="bi bi-chevron-right ms-auto" style="width:auto;font-size:10px;"></i></a>
        <div class="collapse sub-menu<?= isActive(['users','roles','audit_log','periods'],$activeMenu)?' show':'' ?>" id="menuSys">
            <a href="/he-thong/nguoi-dung" class="nav-link-s<?= isActive('users',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Người dùng</a>
            <a href="/he-thong/vai-tro" class="nav-link-s<?= isActive('roles',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Vai trò & Phân quyền</a>
            <a href="/he-thong/nhat-ky-hoat-dong" class="nav-link-s<?= isActive('audit_log',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Nhật ký hoạt động</a>
            <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Cấu hình</a>
            <a href="/he-thong/quan-ly-ky" class="nav-link-s<?= isActive('periods',$activeMenu)?' active':'' ?>"><i class="bi bi-circle-fill"></i> Quản lý kỳ</a>
            <a href="#" class="nav-link-s"><i class="bi bi-circle-fill"></i> Sao lưu & Phục hồi</a>
        </div></div>
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

function showToast(msg,type){
    var icon=type==='success'?'bi-check-circle-fill text-success':'bi-x-circle-fill text-danger';
    var t=$('<div class="toast show align-items-center border-0" role="alert"><div class="d-flex"><div class="toast-body"><i class="bi '+icon+' me-2"></i>'+msg+'</div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div></div>');
    $('#toastContainer').append(t);
    setTimeout(function(){t.remove();},3000);
}

function hideConfirm(){document.getElementById('confirmOverlay').style.display='none';}

var deleteId=null,deleteApi=null;
function confirmDelete(id,api,name){
    deleteId=id;deleteApi=api;
    document.getElementById('confirmTitle').textContent='Xóa';
    document.getElementById('confirmMessage').textContent='Bạn có chắc chắn muốn xóa "'+esc(name)+'"?';
    document.getElementById('confirmOverlay').style.display='flex';
}
document.getElementById('confirmBtn').addEventListener('click',function(){
    if(deleteId&&deleteApi){
        fetch(deleteApi+'/'+deleteId,{method:'DELETE'})
            .then(function(){showToast('Đã xóa thành công','success');loadData();})
            .catch(function(){showToast('Lỗi khi xóa','error');});
    }
    hideConfirm();
});

$(function(){
    $('.sidebar-scroll .nav-link-s[href="#"]').on('click',function(e){
        e.preventDefault();
        showToast('Module đang phát triển','info');
    });
});
</script>
</body>
</html>
