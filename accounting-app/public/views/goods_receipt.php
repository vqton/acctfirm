<?php
// Mẫu số 01-VT (Phiếu nhập kho) theo Thông tư 99/2025/TT-BTC
// API: GET  /api/goods-receipt/list (danh sách PNK)
//      POST /api/goods-receipt/draft (tạo nháp)
//      GET  /api/goods-receipt/{id} (chi tiết)
//      POST /api/goods-receipt/{id}/post (ghi sổ)
//      POST /api/goods-receipt/{id}/cancel (hủy)
// Nghiệp vụ: Nhập kho — Nợ 15x (giá trị hàng) / Có 331 (phải trả NCC)
// Lifecycle: draft → posted → cancelled
$title = 'Phiếu nhập kho (Mẫu 01-VT)';
$activeMenu = 'goods_receipt';
ob_start(); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<style>
.tt99-grid { border: 1px solid #dee2e6; }
.tt99-grid th { background: #f8f9fa; font-size: 12px; text-align: center; vertical-align: middle; padding: 6px 4px; border: 1px solid #dee2e6; }
.tt99-grid td { border: 1px solid #dee2e6; padding: 4px; vertical-align: middle; }
.tt99-grid .line-remove { color: #dc3545; cursor: pointer; font-size: 18px; line-height: 1; padding: 0 4px; }
.tt99-grid .line-remove:hover { color: #a71d2a; }
.tt99-header { border-bottom: 2px solid #0d6efd; padding-bottom: 12px; margin-bottom: 16px; }
.tt99-signature { border-top: 1px solid #dee2e6; margin-top: 24px; padding-top: 16px; }
.tt99-signature .sig-col { text-align: center; font-size: 12px; }
.tt99-signature .sig-col .sig-line { border-top: 1px solid #333; width: 80%; margin: 40px auto 4px; padding-top: 4px; }
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
                <!-- Hidden ID -->
                <input type="hidden" id="receiptId">

                <!-- Header row: PNK number + date -->
                <div class="row g-2 mb-2">
                    <div class="col-md-3">
                        <label class="form-label">Số PNK</label>
                        <input type="text" class="form-control form-control-sm" id="fGrNumber" readonly placeholder="(tự động)">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ngày nhập <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm datepicker" id="fReceivedDate" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="col-md-3">
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
                </div>

                <!-- Supplier info -->
                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label">Nhà cung cấp</label>
                        <input type="text" class="form-control form-control-sm" id="fSupplierName" placeholder="Tên nhà cung cấp" list="supplierList">
                        <datalist id="supplierList"></datalist>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Địa chỉ NCC</label>
                        <input type="text" class="form-control form-control-sm" id="fSupplierAddress" placeholder="Địa chỉ">
                    </div>
                </div>

                <!-- Lines grid -->
                <div class="mb-2">
                    <label class="form-label">Danh sách hàng hóa <span class="text-danger">*</span></label>
                    <table class="tt99-grid w-100" id="linesGrid">
                        <thead><tr>
                            <th style="width:30px">#</th>
                            <th style="width:80px">Mã hàng</th>
                            <th>Tên hàng hóa</th>
                            <th style="width:60px">ĐVT</th>
                            <th style="width:100px">Số lượng</th>
                            <th style="width:120px">Đơn giá</th>
                            <th style="width:120px">Thành tiền</th>
                            <th style="width:100px">Số lô</th>
                            <th style="width:30px"></th>
                        </tr></thead>
                        <tbody id="linesBody">
                            <!-- Rows inserted by JS -->
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-1" onclick="addLine()"><i class="bi bi-plus-lg"></i> Thêm dòng</button>
                </div>

                <!-- Totals -->
                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label">Ghi chú</label>
                        <textarea class="form-control form-control-sm" id="fNote" rows="2" placeholder="Diễn giải..."></textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Phòng ban</label>
                        <input type="text" class="form-control form-control-sm" id="fDepartment" placeholder="Bộ phận">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tổng tiền</label>
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

// === LIST VIEW ===
function showList() {
    currentId = null;
    $('#formView').hide();
    $('#listView').show();
    loadList();
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
    $('#btnPost').removeClass('d-none');
    $('#formView').show();
    $('#listView').hide();
    $('#btnPrint').addClass('d-none');
    loadWarehouses();
    loadSuppliers();
    addLine();
    // Date mặc định
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
    loadWarehouses();
    loadSuppliers();

    $.get('/api/goods-receipt/' + id, function(data) {
        var d = data.data || data;
        $('#receiptId').val(d.id);
        $('#fGrNumber').val(d.gr_number);
        $('#fReceivedDate').val(d.received_date);
        $('#fReceiptType').val(d.receipt_type || 'purchase');
        $('#fSupplierName').val(d.supplier_name || '');
        $('#fSupplierAddress').val(d.supplier_address || '');
        $('#fNote').val(d.note || '');
        $('#fDepartment').val(d.department || '');
        if (d.warehouse_id) $('#fWarehouseId').val(d.warehouse_id);

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
        if (d.status === 'draft') {
            $('#btnPost').removeClass('d-none');
            $('#btnCancel').removeClass('d-none');
        }

        // Signatures
        var user = $('#fSupplierName').val() || '...';
        $('#sigDeliver').text(user);
        $('#sigPreparer').text(d.created_by || '...');
        if (d.status === 'posted') {
            $('#sigKeeper').text('(Đã nhập)');
            $('#sigAccountant').text('(Đã ghi sổ)');
        }
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
    $('#btnPost,#btnCancel').addClass('d-none');
    $('#sigPreparer,#sigDeliver,#sigKeeper,#sigAccountant,#sigDirector').text('');
    lineCounter = 0;
}

// === LINES GRID ===
function addLine(data) {
    data = data || {};
    var idx = lineCounter++;
    var html = '<tr id="line_' + idx + '">' +
        '<td class="text-center">' + (idx + 1) + '</td>' +
        '<td><input type="text" class="form-control form-control-sm item-code" data-idx="' + idx + '" value="' + esc(data.item_code || '') + '" placeholder="Mã" style="min-width:60px"></td>' +
        '<td>' +
            '<input type="hidden" class="item-id" value="' + esc(data.item_id || '') + '">' +
            '<input type="text" class="form-control form-control-sm item-name" data-idx="' + idx + '" value="' + esc(data.item_name || '') + '" placeholder="Chọn hàng..." style="min-width:120px" readonly>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary ms-1 py-0" onclick="openItemPicker(' + idx + ')"><i class="bi bi-search"></i></button>' +
        '</td>' +
        '<td><input type="text" class="form-control form-control-sm item-uom" value="' + esc(data.uom || '') + '" readonly style="min-width:50px"></td>' +
        '<td><input type="number" class="form-control form-control-sm line-qty" data-idx="' + idx + '" value="' + (data.qty_received || '') + '" step="1" min="0" oninput="recalcLine(' + idx + ')" placeholder="SL" style="min-width:80px"></td>' +
        '<td><input type="number" class="form-control form-control-sm line-price" data-idx="' + idx + '" value="' + (data.unit_price || '') + '" step="1000" min="0" oninput="recalcLine(' + idx + ')" placeholder="ĐG" style="min-width:80px"></td>' +
        '<td><input type="text" class="form-control form-control-sm line-total" readonly value="' + fmt(data.total) + '" style="min-width:80px;font-weight:bold"></td>' +
        '<td><input type="text" class="form-control form-control-sm" value="' + esc(data.batch_no || '') + '" placeholder="Lô" style="min-width:80px"></td>' +
        '<td><span class="line-remove" onclick="removeLine(' + idx + ')">&times;</span></td>' +
    '</tr>';
    $('#linesBody').append(html);
    recalcTotal();
}

function removeLine(idx) {
    $('#line_' + idx).remove();
    recalcTotal();
}

function recalcLine(idx) {
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
    // Amount in words
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
        tbody.append('<tr onclick="selectItem(\'' + esc(item.id) + '\',\'' + esc(item.code) + '\',\'' + esc(item.name) + '\',\'' + esc(item.uom || '') + '\')" style="cursor:pointer">' +
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

function selectItem(id, code, name, uom) {
    var idx = currentItemRow;
    $('#line_' + idx + ' .item-id').val(id);
    $('#line_' + idx + ' .item-code').val(code);
    $('#line_' + idx + ' .item-name').val(name);
    $('#line_' + idx + ' .item-uom').val(uom);
    $('#itemModal').modal('hide');
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
        var qty = parseFloat($(this).find('.line-qty').val()) || 0;
        var price = parseFloat($(this).find('.line-price').val()) || 0;
        var total = qty * price;
        var batchNo = $(this).find('input[placeholder="Lô"]').val() || null;

        if (!itemId || qty <= 0) { hasError = true; return; }
        lines.push({
            item_id: itemId,
            item_name: itemName,
            item_code: itemCode,
            uom: uom,
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
        supplier_name: $('#fSupplierName').val().trim() || null,
        supplier_address: $('#fSupplierAddress').val().trim() || null,
        department: $('#fDepartment').val().trim() || null,
        note: $('#fNote').val().trim() || null,
        lines: lines
    };

    if (currentId) {
        // Đã tồn tại — cập nhật
    }

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
function printPNK() { window.print(); }

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

    // Date picker
    if (typeof flatpickr !== 'undefined') {
        flatpickr('.datepicker', { dateFormat: 'Y-m-d', locale: 'vn' });
    }

    // Ctrl+Enter to save
    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && $('#formView').is(':visible')) {
            e.preventDefault();
            if (currentId) { postReceipt(); } else { saveDraft(); }
        }
    });
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
