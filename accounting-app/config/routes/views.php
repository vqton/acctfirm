<?php

// === TRANG CHỦ & DANH MỤC ===
$router->get('/', function() { require __DIR__ . '/../../public/views/dashboard.php'; });
$router->get('/danh-muc/vat-tu', function() { require __DIR__ . '/../../public/views/items.php'; });
$router->get('/danh-muc/khach-hang', function() { require __DIR__ . '/../../public/views/customers.php'; });
$router->get('/danh-muc/nha-cung-cap', function() { require __DIR__ . '/../../public/views/suppliers.php'; });
$router->get('/danh-muc/kho', function() { require __DIR__ . '/../../public/views/warehouses.php'; });
$router->get('/danh-muc/phong-ban', function() { require __DIR__ . '/../../public/views/departments.php'; });
$router->get('/danh-muc/nhan-vien', function() { require __DIR__ . '/../../public/views/employees.php'; });
$router->get('/danh-muc/don-vi-tinh', function() { require __DIR__ . '/../../public/views/uoms.php'; });
$router->get('/danh-muc/cong-cu-dung-cu', function() { require __DIR__ . '/../../public/views/ccdc.php'; });
$router->get('/danh-muc/ty-gia', function() { require __DIR__ . '/../../public/views/exchange_rates.php'; });
$router->get('/danh-muc/bieu-thue', function() { require __DIR__ . '/../../public/views/tax_rates.php'; });
$router->get('/danh-muc/tai-san-co-dinh', function() { require __DIR__ . '/../../public/views/fixed_assets.php'; });
$router->get('/danh-muc/tai-san-co-dinh/tinh-khau-hao', function() { require __DIR__ . '/../../public/views/fixed_asset_depreciation.php'; });
$router->get('/danh-muc/phuong-phap-tinh-gia', function() { require __DIR__ . '/../../public/views/valuation_methods.php'; });
$router->get('/danh-muc/hop-dong', function() { require __DIR__ . '/../../public/views/contracts.php'; });
$router->get('/danh-muc/du-an', function() { require __DIR__ . '/../../public/views/projects.php'; });
$router->get('/danh-muc/chinh-sach-khau-hao', function() { require __DIR__ . '/../../public/views/depreciation_policies.php'; });
$router->get('/danh-muc/he-thong-tai-khoan', function() { require __DIR__ . '/../../public/views/accounts.php'; });
$router->get('/danh-muc/tai-khoan-ngan-hang', function() { require __DIR__ . '/../../public/views/bank_accounts.php'; });

// === MUA HÀNG ===
$router->get('/mua/de-nghi-mua-hang', function() { require __DIR__ . '/../../public/views/purchase_requisitions.php'; });
$router->get('/mua/don-dat-hang', function() { require __DIR__ . '/../../public/views/purchase_orders.php'; });
$router->get('/mua/nhap-kho-theo-po', function() { require __DIR__ . '/../../public/views/purchase_receipts.php'; });
$router->get('/mua/doi-chieu-hoa-don', function() { require __DIR__ . '/../../public/views/purchase_matching.php'; });
$router->get('/mua/ngan-sach', function() { require __DIR__ . '/../../public/views/purchase_budgets.php'; });
$router->get('/mua/cong-no-phai-tra', function() { require __DIR__ . '/../../public/views/ap_invoices.php'; });
$router->get('/mua/phan-tich-tuoi-no', function() { require __DIR__ . '/../../public/views/ap_aging.php'; });
$router->get('/mua/so-chi-tiet-cong-no', function() { require __DIR__ . '/../../public/views/ap_statement.php'; });

// === BÁN HÀNG ===
$router->get('/ban/cong-no-phai-thu', function() { require __DIR__ . '/../../public/views/ar_invoices.php'; });
$router->get('/ban/phan-tich-tuoi-no', function() { require __DIR__ . '/../../public/views/ar_aging.php'; });
$router->get('/ban/so-chi-tiet-cong-no', function() { require __DIR__ . '/../../public/views/ar_statement.php'; });

// === KHO ===
$router->get('/kho/nhap-kho', function() { require __DIR__ . '/../../public/views/receipt.php'; });
$router->get('/kho/xuat-kho', function() { require __DIR__ . '/../../public/views/issue.php'; });
$router->get('/kho/hang-ban-tra-lai', function() { require __DIR__ . '/../../public/views/customer_return.php'; });
$router->get('/kho/dieu-chuyen', function() { require __DIR__ . '/../../public/views/transfers.php'; });
$router->get('/kho/hang-gui-ban', function() { require __DIR__ . '/../../public/views/consignment.php'; });
$router->get('/kho/kiem-ke', function() { require __DIR__ . '/../../public/views/physical_count.php'; });
$router->get('/kho/du-phong-giam-gia', function() { require __DIR__ . '/../../public/views/impairment.php'; });
$router->get('/kho/hang-mua-tra-lai', function() { require __DIR__ . '/../../public/views/supplier_return.php'; });
$router->get('/kho/xuat-huy', function() { require __DIR__ . '/../../public/views/write_off.php'; });
$router->get('/kho/kiem-ke-dinh-ky', function() { require __DIR__ . '/../../public/views/periodic.php'; });
$router->get('/kho/hang-dang-di-duong', function() { require __DIR__ . '/../../public/views/transit.php'; });

// === THU / CHI (Tiền mặt & Ngân hàng) ===
$router->get('/thu/quy-tien-mat', function() { require __DIR__ . '/../../public/views/cash_receipts.php'; });
$router->get('/chi/quy-tien-mat', function() { require __DIR__ . '/../../public/views/cash_payments.php'; });
$router->get('/thu/giao-bao-co', function() { require __DIR__ . '/../../public/views/bank_credit.php'; });
$router->get('/chi/giao-bao-no', function() { require __DIR__ . '/../../public/views/bank_debit.php'; });
$router->get('/thu/tien-dang-chuyen', function() { require __DIR__ . '/../../public/views/cash_transit.php'; });
$router->get('/thu/so-quy-tien-mat', function() { require __DIR__ . '/../../public/views/cash_book.php'; });
$router->get('/thu/tam-ung', function() { require __DIR__ . '/../../public/views/petty_cash.php'; });
$router->get('/thu/doi-chieu-ngan-hang', function() { require __DIR__ . '/../../public/views/bank_reconciliation.php'; });
$router->get('/thu/bao-cao-von-bang-tien', function() { require __DIR__ . '/../../public/views/cash_reports.php'; });
$router->get('/thu/danh-gia-lai-ngoai-te', function() { require __DIR__ . '/../../public/views/fx_revaluation.php'; });

// === TSCĐ (Tài sản cố định) ===
$router->get('/tai-san-co-dinh/ghi-tang', function() { require __DIR__ . '/../../public/views/fixed_asset_acquisition.php'; });
$router->get('/tai-san-co-dinh/thanh-ly', function() { require __DIR__ . '/../../public/views/fixed_asset_disposal.php'; });

// === TỔNG HỢP (Journal, Approvals, Reports) ===
$router->get('/tong-hop/chung-tu-ghi-so', function() { require __DIR__ . '/../../public/views/journal.php'; });
$router->get('/tong-hop/phe-duyet', function() { require __DIR__ . '/../../public/views/approvals.php'; });
$router->get('/tong-hop/bang-can-doi-so-phat-sinh', function() { require __DIR__ . '/../../public/views/trial_balance.php'; });
$router->get('/tong-hop/khoa-so-cuoi-ky', function() { require __DIR__ . '/../../public/views/period_close.php'; });

// === BÁO CÁO TỰ THIẾT KẾ ===
$router->get('/bao-cao/tu-thiet-ke', function() use ($c) { $c['ReportBuilderController']->viewIndex(); });

// === BÁO CÁO TÀI CHÍNH ===
$router->get('/bao-cao/tinh-hinh-tai-chinh', function() use ($c) { $c['FsController']->viewBC01(); });
$router->get('/bao-cao/ket-qua-kinh-doanh', function() use ($c) { $c['FsController']->viewBC02(); });
$router->get('/bao-cao/luu-chuyen-tien-te', function() use ($c) { $c['FsController']->viewBC03(); });
$router->get('/bao-cao/thuyet-minh-bctc', function() use ($c) { $c['FsController']->viewTT99(); });
$router->get('/bao-cao/so-cai', function() use ($c) { $c['GlController']->view(); });
$router->get('/bao-cao/so-chi-tiet', function() { require __DIR__ . '/../../public/views/so_chi_tiet.php'; });
$router->get('/bao-cao/doi-soat', function() { require __DIR__ . '/../../public/views/reconciliation.php'; });
$router->get('/bao-cao/ty-gia', function() use ($c) { $c['FxController']->view(); });
$router->get('/bao-cao/nhat-ky-chung', function() use ($c) { $c['JournalBookController']->view(); });

// === HỆ THỐNG ===
$router->get('/he-thong/quan-ly-ky', function() { require __DIR__ . '/../../public/views/periods.php'; });
$router->get('/tong-hop/so-sanh-ky', function() { require __DIR__ . '/../../public/views/period_compare.php'; });
$router->get('/he-thong/thong-bao', function() { require __DIR__ . '/../../public/views/notifications.php'; });
$router->get('/he-thong/thiet-ke-mau-in', function() { require __DIR__ . '/../../public/views/print_designer.php'; });
$router->get('/he-thong/kiem-tra-truoc-khi-khoa-so', function() { require __DIR__ . '/../../public/views/pre_close_checklist.php'; });
$router->get('/he-thong/noi-bo', function() use ($c) { $c['IntercompanyController']->view(); });
$router->get('/he-thong/nhat-ky-hoat-dong', function() { require __DIR__ . '/../../public/views/audit_log.php'; });

// === THUẾ ===
$router->get('/thue/ke-khai-gtgt', function() use ($c) { $c['VatController']->view(); });
$router->get('/thue/quyet-toan-tndn', function() use ($c) { $c['CitController']->view(); });
$router->get('/thue/nha-thau-nuoc-ngoai', function() use ($c) { $c['FctController']->view(); });

// === ĐIỀU CHỈNH BÚT TOÁN ===
$router->get('/dieu-chinh-but-toan', function() use ($c) { $c['CorrectionController']->view(); });

// === CCDC ===
$router->get('/ccdc/phan-bo', function() use ($c) { $c['CcdcAllocationController']->view(); });

// === TIỀN LƯƠNG ===
$router->get('/tien-luong', function() { require __DIR__ . '/../../public/views/payroll.php'; });
$router->get('/tien-luong/nhan-vien', function() { require __DIR__ . '/../../public/views/payroll_employees.php'; });
$router->get('/tien-luong/ky-luong', function() { require __DIR__ . '/../../public/views/payroll_periods.php'; });
$router->get('/tien-luong/bang-luong', function() { require __DIR__ . '/../../public/views/payroll_entries.php'; });
$router->get('/tien-luong/bang-luong/:id', function($id) { require __DIR__ . '/../../public/views/payroll_entry_detail.php'; });
$router->get('/tien-luong/tinh-luong', function() { require __DIR__ . '/../../public/views/payroll_calculate.php'; });
$router->get('/tien-luong/bao-hiem', function() { require __DIR__ . '/../../public/views/payroll_insurance.php'; });
$router->get('/tien-luong/thue-tncn', function() { require __DIR__ . '/../../public/views/payroll_tax.php'; });
$router->get('/tien-luong/phieu-luong', function() { require __DIR__ . '/../../public/views/payroll_payslip.php'; });
$router->get('/tien-luong/ke-khai-bhxh', function() { require __DIR__ . '/../../public/views/payroll_bhxh.php'; });
