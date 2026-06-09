<?php
// Mẫu số 01-VT (Phiếu nhập kho) theo Thông tư 99/2025/TT-BTC
// API: GET  /api/goods-receipt/list
//      POST /api/goods-receipt/draft
//      GET  /api/goods-receipt/{id}
//      POST /api/goods-receipt/{id}/post
//      POST /api/goods-receipt/{id}/cancel
//      GET  /api/goods-receipt/{id}/print
// Nghiệp vụ: Nhập kho — Nợ 15x (giá trị hàng) / Có 331 (phải trả NCC)
// Lifecycle: draft → posted → cancelled
// Cột: A(STT), B(tên), C(mã số), D(ĐVT), 1(SL theo CT), 2(SL thực nhập), 3(Đơn giá), 4(Thành tiền)
$title = 'Phiếu nhập kho (Mẫu 01-VT)';
$activeMenu = 'goods_receipt';
ob_start(); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<style>
.tt99-grid { border: 1px solid #dee2e6; }
.tt99-grid th { background: #f8f9fa; font-size: 11px; text-align: center; vertical-align: middle; padding: 4px 2px; border: 1px solid #dee2e6; }
.tt99-grid td { border: 1px solid #dee2e6; padding: 2px; vertical-align: middle; }
.tt99-grid .line-remove { color: #dc3545; cursor: pointer; font-size: 18px; line-height: 1; padding: 0 4px; }
.tt99-grid .line-remove:hover { color: #a71d2a; }
.tt99-header { border-bottom: 2px solid #0d6efd; padding-bottom: 12px; margin-bottom: 16px; }
.tt99-signature { border-top: 1px solid #dee2e6; margin-top: 24px; padding-top: 16px; }
.tt99-signature .sig-col { text-align: center; font-size: 12px; }
.tt99-signature .sig-col .sig-line { border-top: 1px solid #333; width: 80%; margin: 40px auto 4px; padding-top: 4px; }
.tt99-accounts { font-size: 13px; font-weight: 600; color: #0d6efd; background: #f0f7ff; padding: 4px 10px; border-radius: 4px; display: inline-block; }
.qty-warning { background: #fff3cd; border: 1px solid #ffc107; padding: 6px 12px; border-radius: 4px; font-size: 12px; margin: 6px 0; }
.invoice-ref { background: #f8f9fa; padding: 6px 10px; border-radius: 4px; font-size: 13px; border-left: 3px solid #0d6efd; margin-bottom: 8px; }
</style>

<div class="toolbar">
    <h5><i class="bi bi-box-arrow-in-right me-1"></i> Phiếu nhập kho (Mẫu 01-VT)</h5>
    <div>
        <select class="form-select form-select-sm d-inline-block w-auto me-2" id="filterStatus">
            <option value="">-- Tất cả trạng thái --</option>
            <option value="draft">Nháp</option>
            <option value="posted">Đã ghi sổ</option>
            <option value="cancelled">Đã hủy</option>
        </select>
        <button class="btn btn-outline-success btn-sm me-1" onclick="exportCSV('#dataBody', 'phieu-nhap-kho')"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</button>
        <button class="btn btn-primary btn-sm" onclick="showCreateForm()"><i class="bi bi-plus-lg"></i> Tạo PNK mới</button>
    </div>
</div>

<div id="listView">
    <div class="card-table">
        <div class="card-header-x">
            <input type="text" id="searchInput" placeholder="🔍 Tìm kiếm..." onkeyup="filterList()">
            <span class="stats ms-auto" id="recordCount">0 bản ghi</span>
        </div>
        <table class="table table-hover">
            <thead><tr>
                <th>Số PNK</th>
                <th>Ngày</th>
                <th>Loại</th>
                <th>Nhà cung cấp</th>
                <th class="text-end">Tổng tiền</th>
                <th>Trạng thái</th>
                <th></th>
            </tr></thead>
            <tbody id="dataBody"></tbody>
        </table>
    </div>
</div>

<div id="formView" style="display:none">
    <div class="card">
        <div class="card-header bg-white tt99-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0" id="formTitle">Tạo phiếu nhập kho</h5>
                <small class="text-muted" id="formSubtitle">Mẫu số 01-VT (Kèm theo TT 99/2025/TT-BTC)</small>
            </div>
            <div>
                <button class="btn btn-outline-secondary btn-sm" onclick="showList()"><i class="bi bi-arrow-left"></i> Quay lại</button>
                <button class="btn btn-success btn-sm d-none" id="btnPrint" onclick="printPNK()"><i class="bi bi-printer"></i> In PNK</button>
            </div>
        </div>
        <div class="card-body">
            <form id="receiptForm">
                <input type="hidden" id="receiptId">

                <!-- Header row -->
                <div class="row g-2 mb-2">
                    <div class="col-md-2">
                        <label class="form-label">Số PNK</label>
                        <input type="text" class="form-control form-control-sm" id="fGrNumber" readonly placeholder="(tự động)">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Ngày nhập <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm datepicker" id="fReceivedDate" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Loại nhập <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="fReceiptType">
                            <option value="purchase">Mua hàng</option>
                            <option value="production_return">Thu hồi sản xuất</option>
                            <option value="other">Khác</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Kho nhập <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="fWarehouseId">
                            <option value="">-- Chọn kho --</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Địa điểm kho</label>
                        <input type="text" class="form-control form-control-sm" id="fWarehouseLocation" placeholder="Địa điểm nhập kho">
                    </div>
                </div>

                <!-- Nợ/Có accounts -->
                <div class="mb-2 tt99-accounts" id="accountsDisplay">
                    <span>Nợ: <span id="debitAccounts">15x</span></span>
                    <span class="ms-3">Có: <span id="creditAccount">331 (hoặc 1111)</span></span>
                </div>

                <!-- Invoice reference -->
                <div class="invoice-ref" id="invoiceRefDisplay" style="display:none">
                    Theo <span id="fInvoiceRefDisplay"></span>
                </div>

                <!-- Supplier + deliverer -->
                <div class="row g-2 mb-2">
                    <div class="col-md-4">
                        <label class="form-label">Nhà cung cấp</label>
                        <input type="text" class="form-control form-control-sm" id="fSupplierName" placeholder="Tên nhà cung cấp" list="supplierList">
                        <datalist id="supplierList"></datalist>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Địa chỉ NCC</label>
                        <input type="text" class="form-control form-control-sm" id="fSupplierAddress" placeholder="Địa chỉ">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Người giao hàng</label>
                        <input type="text" class="form-control form-control-sm" id="fDelivererName" placeholder="Họ tên người giao">
                    </div>
                </div>

                <!-- Invoice details -->
                <div class="row g-2 mb-2">
                    <div class="col-md-4">
                        <label class="form-label">Theo hóa đơn/lệnh nhập số</label>
                        <input type="text" class="form-control form-control-sm" id="fInvoiceRef" placeholder="Số hóa đơn/lệnh nhập">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Ngày HĐ</label>
                        <input type="text" class="form-control form-control-sm datepicker" id="fInvoiceDate" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Phòng ban</label>
                        <input type="text" class="form-control form-control-sm" id="fDepartment" placeholder="Bộ phận">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Số CT gốc kèm theo</label>
                        <input type="text" class="form-control form-control-sm" id="fAttachDoc" placeholder="VD: 01 HĐ GTGT số ...">
                    </div>
                </div>

                <!-- Lines grid — 8 cột A-D + 1-2-3-4 -->
                <div class="mb-2">
                    <label class="form-label">Danh sách hàng hóa <span class="text-danger">*</span></label>
                    <table class="tt99-grid w-100" id="linesGrid">
                        <thead><tr>
                            <th style="width:24px">A</th>
                            <th style="width:60px">B<br>Mã</th>
                            <th>C — Tên hàng hóa</th>
                            <th style="width:40px">D<br>ĐVT</th>
                            <th style="width:70px">1 — SL<br>theo CT</th>
                            <th style="width:70px">2 — SL<br>thực nhập</th>
                            <th style="width:90px">3 — Đơn giá</th>
                            <th style="width:90px">4 — Thành tiền</th>
                            <th style="width:70px">Số lô</th>
                            <th style="width:24px"></th>
                        </tr></thead>
                        <tbody id="linesBody"></tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-1" onclick="addLine()"><i class="bi bi-plus-lg"></i> Thêm dòng</button>
                </div>

                <!-- Qty warning -->
                <div id="qtyWarning" class="qty-warning d-none"></div>

                <!-- Totals + notes -->
                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label">Ghi chú</label>
                        <textarea class="form-control form-control-sm" id="fNote" rows="2" placeholder="Diễn giải..."></textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tổng tiền (Cộng)</label>
                        <div class="fs-5 fw-bold text-danger" id="totalDisplay">0</div>
                        <div class="small text-muted fst-italic" id="totalWordsDisplay"></div>
                    </div>
                </div>

                <!-- Signatures (Mẫu 01-VT) -->
                <div class="tt99-signature">
                    <div class="row">
                        <div class="col sig-col">
                            <div class="sig-line" id="sigPreparer"></div>
                            <strong>Người lập phiếu</strong>
                            <div class="text-muted">(Ký, họ tên)</div>
                        </div>
                        <div class="col sig-col">
                            <div class="sig-line" id="sigDeliver"></div>
                            <strong>Người giao hàng</strong>
                            <div class="text-muted">(Ký, họ tên)</div>
                        </div>
                        <div class="col sig-col">
                            <div class="sig-line" id="sigKeeper"></div>
                            <strong>Thủ kho</strong>
                            <div class="text-muted">(Ký, họ tên)</div>
                        </div>
                        <div class="col sig-col">
                            <div class="sig-line" id="sigAccountant"></div>
                            <strong>Kế toán trưởng</strong>
                            <div class="text-muted">(Ký, họ tên)</div>
                        </div>
                        <div class="col sig-col">
                            <div class="sig-line" id="sigDirector"></div>
                            <strong>Giám đốc</strong>
                            <div class="text-muted">(Ký, họ tên)</div>
                        </div>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="mt-3 d-flex gap-2" id="actionButtons">
                    <button type="button" class="btn btn-success btn-sm" id="btnSaveDraft" onclick="saveDraft()"><i class="bi bi-save"></i> Lưu nháp</button>
                    <button type="button" class="btn btn-success btn-sm d-none" id="btnPost" onclick="postReceipt()"><i class="bi bi-check-lg"></i> Ghi sổ</button>
                    <button type="button" class="btn btn-danger btn-sm d-none" id="btnCancel" onclick="cancelReceipt()"><i class="bi bi-x-lg"></i> Hủy</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Item search modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title">Chọn mặt hàng</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="text" class="form-control form-control-sm mb-2" id="itemSearch" placeholder="Tìm kiếm...">
                <table class="table table-sm table-hover">
                    <thead><tr><th>Mã</th><th>Tên</th><th>ĐVT</th><th>Tồn kho</th><th></th></tr></thead>
                    <tbody id="itemSearchBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
var currentId = null;
var itemsCache = [];
var suppliersCache = [];
var currentItemRow = null;
var lineCounter = 0;

function fmt(n) { return (n||0).toLocaleString('vi-VN', {style:'currency',currency:'VND'}); }
function fmtNum(n) { return (n||0).toLocaleString('vi-VN'); }
function esc(s) { var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }

// Map item_type → inventory account
function invAccount(itemType) {
    return ({merchandise:'156', material:'152', product:'155', tool:'153'})[itemType] || '152';
}

// === LIST VIEW ===
function showList() {
    currentId = null;
    $('#formView').hide();
    $('#listView').show();
    loadList();
}

function statusBadge(s) {
    return s === 'posted' ? '<span class="badge bg-success">Đã ghi sổ</span>'
         : s === 'draft' ? '<span class="badge bg-warning text-dark">Nháp</span>'
         : '<span class="badge bg-secondary">Đã hủy</span>';
}

function loadList() {
    var status = $('#filterStatus').val();
    var url = '/api/goods-receipt/list' + (status ? '?status=' + encodeURIComponent(status) : '');
    $.get(url, function(data) {
        var tbody = $('#dataBody');
        tbody.empty();
        var list = data.data || data || [];
        $('#recordCount').text(list.length + ' bản ghi');
        list.forEach(function(r) {
            tbody.append('<tr>' +
                '<td>' + esc(r.gr_number) + '</td>' +
                '<td style="font-size:12px">' + esc(r.received_date) + '</td>' +
                '<td>' + esc(r.receipt_type === 'purchase' ? 'Mua hàng' : r.receipt_type === 'production_return' ? 'Thu hồi SX' : 'Khác') + '</td>' +
                '<td>' + esc(r.supplier_name || '') + '</td>' +
                '<td class="text-end font-monospace">' + fmt(r.total_amount) + '</td>' +
                '<td>' + statusBadge(r.status) + '</td>' +
                '<td><button class="btn btn-sm btn-outline-primary" onclick="openDetail(\'' + esc(r.id) + '\')"><i class="bi bi-eye"></i></button></td>' +
            '</tr>');
        });
    });
}

// === FORM ===
function showCreateForm() {
    currentId = null;
    resetForm();
    $('#formTitle').text('Tạo phiếu nhập kho');
    $('#btnPost').addClass('d-none');
    $('#btnSaveDraft').removeClass('d-none');
    $('#formView').show();
    $('#listView').hide();
    $('#btnPrint').addClass('d-none');
    loadWarehouses();
    loadSuppliers();
    addLine();
    if (!$('#fReceivedDate').val()) {
        $('#fReceivedDate').val(new Date().toISOString().slice(0,10));
    }
}

function openDetail(id) {
    currentId = id;
    resetForm();
    $('#formView').show();
    $('#listView').hide();
    $('#btnPrint').removeClass('d-none');
    $('#btnSaveDraft').addClass('d-none');
    loadWarehouses();
    loadSuppliers();

    // Load print data for accounts info
    $.get('/api/goods-receipt/' + id + '/print', function(pdata) {
        var pd = pdata.data || pdata;
        // Show Nợ/Có
        var debits = pd.debit_accounts || {};
        var debitStr = Object.keys(debits).length
            ? Object.entries(debits).map(function(e) { return e[0] + ' (' + fmt(e[1]) + ')'; }).join(', ')
            : '15x';
        $('#debitAccounts').text(debitStr);
        $('#creditAccount').text(pd.credit_account || '331');
    }).fail(function() { /* ignore */ });

    $.get('/api/goods-receipt/' + id, function(data) {
        var d = data.data || data;
        $('#receiptId').val(d.id);
        $('#fGrNumber').val(d.gr_number);
        $('#fReceivedDate').val(d.received_date);
        $('#fReceiptType').val(d.receipt_type || 'purchase');
        $('#fSupplierName').val(d.supplier_name || '');
        $('#fSupplierAddress').val(d.supplier_address || '');
        $('#fDelivererName').val(d.deliverer_name || '');
        $('#fWarehouseLocation').val(d.warehouse_location || '');
        $('#fInvoiceRef').val(d.invoice_ref || '');
        $('#fInvoiceDate').val(d.invoice_date || '');
        $('#fAttachDoc').val(d.attach_doc || '');
        $('#fNote').val(d.note || '');
        $('#fDepartment').val(d.department || '');
        if (d.warehouse_id) $('#fWarehouseId').val(d.warehouse_id);

        // Show invoice ref display
        if (d.invoice_ref) {
            $('#fInvoiceRefDisplay').text(d.invoice_ref + (d.invoice_date ? ' ngày ' + d.invoice_date : ''));
            $('#invoiceRefDisplay').show();
        } else {
            $('#invoiceRefDisplay').hide();
        }

        $('#formTitle').text('Phiếu nhập kho: ' + d.gr_number);
        $('#formSubtitle').text('Mẫu số 01-VT | ' + statusBadge(d.status));

        // Lines
        var lines = d.lines || [];
        if (lines.length === 0) { addLine(); return; }
        lines.forEach(function(l, i) {
            addLine(l);
        });

        // Action buttons
        $('#btnPost,#btnCancel').addClass('d-none');
        $('#btnSaveDraft').addClass('d-none');
        if (d.status === 'draft') {
            $('#btnPost').removeClass('d-none');
            $('#btnCancel').removeClass('d-none');
            $('#btnSaveDraft').removeClass('d-none');
        }

        // Signatures
        $('#sigDeliver').text(d.deliverer_name || d.supplier_name || '...');
        $('#sigPreparer').text(d.created_by || '...');
        if (d.status === 'posted') {
            $('#sigKeeper').text('(Đã nhập)');
            $('#sigAccountant').text('(Đã ghi sổ)');
        }

        // Qty warning
        checkQtyWarning();
        recalcTotal();
    }).fail(function() {
        showToast('Không tìm thấy phiếu nhập kho', 'error');
        showList();
    });
}

function resetForm() {
    $('#receiptForm')[0].reset();
    $('#receiptId').val('');
    $('#fGrNumber').val('');
    $('#linesBody').empty();
    $('#totalDisplay').text('0');
    $('#totalWordsDisplay').text('');
    $('#btnPost,#btnCancel,#btnSaveDraft').addClass('d-none');
    $('#sigPreparer,#sigDeliver,#sigKeeper,#sigAccountant,#sigDirector').text('');
    $('#debitAccounts').text('15x');
    $('#creditAccount').text('331 (hoặc 1111)');
    $('#invoiceRefDisplay').hide();
    $('#qtyWarning').addClass('d-none').text('');
    lineCounter = 0;
}

// === LINES GRID — 8 cột A-D + 1-2-3-4 ===
function addLine(data) {
    data = data || {};
    var idx = lineCounter++;
    var html = '<tr id="line_' + idx + '">' +
        '<td class="text-center">' + (idx + 1) + '</td>' +
        '<td><input type="text" class="form-control form-control-sm item-code" data-idx="' + idx + '" value="' + esc(data.item_code || '') + '" placeholder="Mã" style="min-width:50px;font-size:11px"></td>' +
        '<td>' +
            '<input type="hidden" class="item-id" value="' + esc(data.item_id || '') + '">' +
            '<input type="hidden" class="item-type" value="' + esc(data.item_type || '') + '">' +
            '<input type="text" class="form-control form-control-sm item-name" data-idx="' + idx + '" value="' + esc(data.item_name || '') + '" placeholder="Chọn hàng..." style="min-width:100px;font-size:11px" readonly>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary ms-1 py-0" onclick="openItemPicker(' + idx + ')"><i class="bi bi-search"></i></button>' +
        '</td>' +
        '<td><input type="text" class="form-control form-control-sm item-uom" value="' + esc(data.uom || '') + '" readonly style="min-width:36px;font-size:11px"></td>' +
        // Cột 1: Số lượng theo chứng từ
        '<td><input type="number" class="form-control form-control-sm line-qty-doc" data-idx="' + idx + '" value="' + (data.qty_in_document || '') + '" step="1" min="0" oninput="recalcLine(' + idx + ');checkQtyWarning()" placeholder="CT" style="min-width:55px;font-size:11px"></td>' +
        // Cột 2: Số lượng thực nhập
        '<td><input type="number" class="form-control form-control-sm line-qty" data-idx="' + idx + '" value="' + (data.qty_received || '') + '" step="1" min="0" oninput="recalcLine(' + idx + ');checkQtyWarning()" placeholder="NK" style="min-width:55px;font-size:11px"></td>' +
        // Cột 3: Đơn giá
        '<td><input type="number" class="form-control form-control-sm line-price" data-idx="' + idx + '" value="' + (data.unit_price || '') + '" step="1000" min="0" oninput="recalcLine(' + idx + ')" placeholder="ĐG" style="min-width:65px;font-size:11px"></td>' +
        // Cột 4: Thành tiền
        '<td><input type="text" class="form-control form-control-sm line-total" readonly value="' + (data.total > 0 ? fmt(data.total) : '0') + '" style="min-width:65px;font-weight:bold;font-size:11px"></td>' +
        '<td><input type="text" class="form-control form-control-sm" value="' + esc(data.batch_no || '') + '" placeholder="Lô" style="min-width:55px;font-size:11px"></td>' +
        '<td><span class="line-remove" onclick="removeLine(' + idx + ')">&times;</span></td>' +
    '</tr>';
    $('#linesBody').append(html);
    recalcTotal();
}

function removeLine(idx) {
    $('#line_' + idx).remove();
    recalcTotal();
    checkQtyWarning();
}

function recalcLine(idx) {
    // Tính dựa trên cột 2 (thực nhập) — đây là cơ sở ghi sổ
    var qty = parseFloat($('#line_' + idx + ' .line-qty').val()) || 0;
    var price = parseFloat($('#line_' + idx + ' .line-price').val()) || 0;
    var total = qty * price;
    $('#line_' + idx + ' .line-total').val(total > 0 ? fmt(total) : '0');
    recalcTotal();
}

function recalcTotal() {
    var total = 0;
    $('.line-total').each(function() {
        var v = parseFloat($(this).val().replace(/[^\d]/g, '')) || 0;
        total += v;
    });
    $('#totalDisplay').text(fmt(total));
    if (total > 0) {
        $.get('/api/helpers/vn-words?amount=' + total, function(r) {
            $('#totalWordsDisplay').text(r.words || '');
        }).fail(function() {
            $('#totalWordsDisplay').text('');
        });
    } else {
        $('#totalWordsDisplay').text('');
    }
}

// Kiểm tra chênh lệch cột 1 ≠ cột 2
function checkQtyWarning() {
    var warnings = [];
    $('#linesBody tr').each(function() {
        var doc = parseFloat($(this).find('.line-qty-doc').val()) || 0;
        var act = parseFloat($(this).find('.line-qty').val()) || 0;
        var name = $(this).find('.item-name').val() || 'dòng';
        if (doc > 0 && doc !== act) {
            warnings.push(name + ': CT=' + fmtNum(doc) + ', NK=' + fmtNum(act));
        }
    });
    if (warnings.length > 0) {
        $('#qtyWarning').removeClass('d-none').text('⚠ Chênh lệch số lượng: ' + warnings.join('; '));
    } else {
        $('#qtyWarning').addClass('d-none').text('');
    }
}

// === ITEM PICKER ===
function openItemPicker(idx) {
    currentItemRow = idx;
    if (itemsCache.length === 0) {
        $.get('/api/inventory/receive/items', function(data) {
            itemsCache = data.data || data || [];
            showItemModal();
        });
    } else {
        showItemModal();
    }
}

function showItemModal() {
    renderItemList(itemsCache);
    $('#itemSearch').val('').focus();
    $('#itemModal').modal('show');
}

function renderItemList(items) {
    var tbody = $('#itemSearchBody');
    tbody.empty();
    items.forEach(function(item) {
        tbody.append('<tr onclick="selectItem(\'' + esc(item.id) + '\',\'' + esc(item.code) + '\',\'' + esc(item.name) + '\',\'' + esc(item.uom || '') + '\',\'' + esc(item.item_type || '') + '\')" style="cursor:pointer">' +
            '<td>' + esc(item.code) + '</td>' +
            '<td>' + esc(item.name) + '</td>' +
            '<td>' + esc(item.uom || '') + '</td>' +
            '<td class="text-end">' + fmtNum(item.stock_qty || 0) + '</td>' +
        '</tr>');
    });
    if (items.length === 0) {
        tbody.append('<tr><td colspan="4" class="text-center text-muted">Không tìm thấy</td></tr>');
    }
}

function selectItem(id, code, name, uom, itemType) {
    var idx = currentItemRow;
    $('#line_' + idx + ' .item-id').val(id);
    $('#line_' + idx + ' .item-type').val(itemType || '');
    $('#line_' + idx + ' .item-code').val(code);
    $('#line_' + idx + ' .item-name').val(name);
    $('#line_' + idx + ' .item-uom').val(uom);
    // Auto-fill default qty = 1, price = 0
    if (!$('#line_' + idx + ' .line-qty').val()) $('#line_' + idx + ' .line-qty').val(1);
    recalcLine(idx);
    // Update Nợ/Có accounts
    updateAccountsDisplay();
    $('#itemModal').modal('hide');
}

function updateAccountsDisplay() {
    var accounts = {};
    $('#linesBody tr').each(function() {
        var itemType = $(this).find('.item-type').val() || 'other';
        var total = parseFloat($(this).find('.line-total').val().replace(/[^\d]/g, '')) || 0;
        var acct = invAccount(itemType);
        accounts[acct] = (accounts[acct] || 0) + total;
    });
    if (Object.keys(accounts).length > 0) {
        var parts = Object.entries(accounts).map(function(e) { return e[0] + ' (' + fmt(e[1]) + ')'; });
        $('#debitAccounts').text(parts.join(', '));
    }
    var hasSupplier = $('#fSupplierName').val().trim().length > 0;
    $('#creditAccount').text(hasSupplier ? '331' : '1111');
}

$(document).on('input', '#itemSearch', function() {
    var q = $(this).val().toLowerCase();
    var filtered = itemsCache.filter(function(i) {
        return (i.code && i.code.toLowerCase().includes(q)) ||
               (i.name && i.name.toLowerCase().includes(q));
    });
    renderItemList(filtered);
});

// === LOAD WAREHOUSES & SUPPLIERS ===
function loadWarehouses() {
    $.get('/api/warehouses', function(data) {
        var list = data.data || data || [];
        var sel = $('#fWarehouseId').empty().append('<option value="">-- Chọn kho --</option>');
        list.forEach(function(w) {
            sel.append('<option value="' + esc(w.id) + '">' + esc(w.name) + ' (' + esc(w.code) + ')</option>');
        });
    });
}

function loadSuppliers() {
    $.get('/api/suppliers', function(data) {
        var list = data.data || data || [];
        var dl = $('#supplierList').empty();
        list.forEach(function(s) {
            dl.append('<option value="' + esc(s.name) + '">');
        });
    });
}

// === SAVE DRAFT ===
function saveDraft() {
    var lines = [];
    var hasError = false;
    $('#linesBody tr').each(function() {
        var itemId = $(this).find('.item-id').val();
        var itemName = $(this).find('.item-name').val();
        var itemCode = $(this).find('.item-code').val();
        var uom = $(this).find('.item-uom').val();
        var qtyDoc = parseFloat($(this).find('.line-qty-doc').val()) || 0;
        var qty = parseFloat($(this).find('.line-qty').val()) || 0;
        var price = parseFloat($(this).find('.line-price').val()) || 0;
        var batchNo = $(this).find('input[placeholder="Lô"]').val() || null;

        if (!itemId || qty <= 0) { hasError = true; return; }
        lines.push({
            item_id: itemId,
            item_name: itemName,
            item_code: itemCode,
            uom: uom,
            qty_in_document: qtyDoc,
            qty_received: qty,
            unit_price: price,
            batch_no: batchNo
        });
    });

    if (lines.length === 0) {
        showToast('Vui lòng nhập ít nhất một dòng hàng hợp lệ', 'warning');
        return;
    }

    var receivedDate = $('#fReceivedDate').val();
    if (!receivedDate) { showToast('Vui lòng chọn ngày nhập kho', 'warning'); return; }

    var data = {
        received_date: receivedDate,
        receipt_type: $('#fReceiptType').val(),
        warehouse_id: $('#fWarehouseId').val(),
        warehouse_location: $('#fWarehouseLocation').val().trim() || null,
        supplier_name: $('#fSupplierName').val().trim() || null,
        supplier_address: $('#fSupplierAddress').val().trim() || null,
        deliverer_name: $('#fDelivererName').val().trim() || null,
        invoice_ref: $('#fInvoiceRef').val().trim() || null,
        invoice_date: $('#fInvoiceDate').val().trim() || null,
        attach_doc: $('#fAttachDoc').val().trim() || null,
        department: $('#fDepartment').val().trim() || null,
        note: $('#fNote').val().trim() || null,
        lines: lines
    };

    $.ajax({
        url: '/api/goods-receipt/draft',
        method: 'POST',
        contentType: 'application/json',
        headers: {'X-CSRF-Token': csrf},
        data: JSON.stringify(data),
        success: function(r) {
            var d = r.data || r;
            showToast('Đã tạo phiếu nhập kho thành công', 'success');
            openDetail(d.id);
        },
        error: function(x) {
            var m = 'Lỗi'; try { m = JSON.parse(x.responseText).error; } catch(e) {}
            showToast(m, 'error');
        }
    });
}

// === POST ===
function postReceipt() {
    if (!currentId) { saveDraft(); return; }
    if (!confirm('Xác nhận ghi sổ phiếu nhập kho này?')) return;
    $.ajax({
        url: '/api/goods-receipt/' + currentId + '/post',
        method: 'POST',
        headers: {'X-CSRF-Token': csrf},
        success: function() {
            showToast('Đã ghi sổ phiếu nhập kho thành công', 'success');
            openDetail(currentId);
        },
        error: function(x) {
            var m = 'Lỗi'; try { m = JSON.parse(x.responseText).error; } catch(e) {}
            showToast(m, 'error');
        }
    });
}

// === CANCEL ===
function cancelReceipt() {
    if (!currentId) return;
    if (!confirm('Hủy phiếu nhập kho này?')) return;
    $.ajax({
        url: '/api/goods-receipt/' + currentId + '/cancel',
        method: 'POST',
        headers: {'X-CSRF-Token': csrf},
        success: function() {
            showToast('Đã hủy phiếu nhập kho', 'success');
            openDetail(currentId);
        },
        error: function(x) {
            var m = 'Lỗi'; try { m = JSON.parse(x.responseText).error; } catch(e) {}
            showToast(m, 'error');
        }
    });
}

// === PRINT ===
function printPNK() {
    if (!currentId) return;
    window.open('/goods-receipt/' + currentId + '/print-view', '_blank');
}

// === FILTER ===
function filterList() {
    var q = $('#searchInput').val().toLowerCase();
    $('#dataBody tr').each(function() {
        var txt = $(this).text().toLowerCase();
        $(this).toggle(txt.includes(q));
    });
}

// === BIND EVENTS ===
$(document).ready(function() {
    loadList();
    $('#filterStatus').change(function() { loadList(); });

    // Update accounts when supplier changes
    $('#fSupplierName').on('input', function() {
        updateAccountsDisplay();
    });

    if (typeof flatpickr !== 'undefined') {
        flatpickr('.datepicker', { dateFormat: 'Y-m-d', locale: 'vn' });
    }

    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && $('#formView').is(':visible')) {
            e.preventDefault();
            if (currentId) { postReceipt(); } else { saveDraft(); }
        }
    });
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
