<?php
// Màn hình: Nhập kho theo đơn đặt hàng (Goods Receipt / Purchase Receipt)
// API: GET /api/purchase/receipts, POST /api/purchase/receipts, GET /api/purchase/orders, GET /api/warehouses
// Nghiệp vụ: Nhập kho hàng hóa từ PO — ghi nhận số lượng thực nhận, số lượng từ chối — cập nhật tồn kho
// Trạng thái: draft → completed → cancelled
// Rủi ro: Nhập kho sai số lượng dẫn đến sai tồn kho và sai giá vốn — cần đối chiếu với PO
$title = 'Nhập kho theo PO'; $activeMenu = 'purchase_gr'; ob_start(); ?>
<div class="toolbar">
    <h5>Nhập kho theo đơn đặt hàng <span class="stats" id="recordCount"></span></h5>
    <div>
        <button class="btn btn-outline-success btn-sm me-1" onclick="exportCSV('#dataBody', 'phieu-nhap-kho')"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</button>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#grModal"><i class="bi bi-plus-lg"></i> Nhập kho</button>
    </div>
</div>

<div class="card-table">
    <table class="table table-hover">
        <thead>
            <tr><th>Số PNK</th><th>PO</th><th>Kho</th><th>Ngày nhập</th><th>Trạng thái</th><th class="text-center">Thao tác</th></tr>
        </thead>
        <tbody id="dataBody"></tbody>
    </table>
</div>

<div class="modal fade" id="grModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<form id="grForm">
    <div class="modal-header"><h5 class="modal-title">Nhập kho theo PO</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-2 mb-2">
            <div class="col-6"><label>Chọn đơn đặt hàng *</label>
                <select class="form-select" id="f_po_id" required>
                    <option value="">-- Chọn PO --</option>
                </select>
            </div>
            <div class="col-3"><label>Kho nhập *</label>
                <select class="form-select" id="f_warehouse_id" required>
                    <option value="">-- Chọn kho --</option>
                </select>
            </div>
            <div class="col-3"><label>Ngày nhập</label><input type="date" class="form-control" id="f_received_date" value="<?= date('Y-m-d') ?>"></div>
        </div>
        <div id="poLinesContainer" style="display:none">
            <h6 class="mb-2">Chi tiết nhập kho</h6>
            <table class="table table-sm table-bordered">
                <thead><tr><th>Hàng hóa</th><th class="text-end">SL đặt</th><th class="text-end">SL còn lại</th><th style="width:20%">SL thực nhận</th><th style="width:15%">SL từ chối</th></tr></thead>
                <tbody id="grLinesBody"></tbody>
            </table>
        </div>
        <div id="poSelectMessage" class="text-muted text-center py-4"><i class="bi bi-inbox"></i> Vui lòng chọn đơn đặt hàng để nhập kho</div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-sm btn-primary" id="grSubmitBtn">Ghi nhận nhập kho</button>
    </div>
</form>
</div></div></div>

<script>
var allData = [];
var warehouses = [];

function loadWarehouses() {
    fetch('/api/warehouses')
    .then(function(r) { return r.json(); })
    .then(function(data) {
        warehouses = data || [];
        var sel = document.getElementById('f_warehouse_id');
        sel.innerHTML = '<option value="">-- Chọn kho --</option>';
        warehouses.forEach(function(w) {
            sel.innerHTML += '<option value="' + esc(w.id) + '">' + esc(w.name) + '</option>';
        });
    })
    .catch(function(err) { showToast('Lỗi tải danh sách kho', 'error'); });
}

function loadPoOptions() {
    var sel = document.getElementById('f_po_id');
    sel.innerHTML = '<option value="">-- Chọn PO --</option>';
    sel.disabled = true;
    fetch('/api/purchase/orders')
    .then(function(r) { return r.json(); })
    .then(function(data) {
        (data || []).forEach(function(po) {
            if (po.status === 'sent' || po.status === 'partially_received') {
                var label = esc(po.reference || po.id) + ' - ' + esc(po.supplier_name || '') + ' (' + parseFloat(po.total_amount || 0).toLocaleString() + ')';
                sel.innerHTML += '<option value="' + esc(po.id) + '">' + label + '</option>';
            }
        });
        sel.disabled = false;
    })
    .catch(function(err) {
        showToast('Lỗi tải danh sách PO', 'error');
        sel.disabled = false;
    });
}

function renderRows(data) {
    var tbody = document.getElementById('dataBody');
    document.getElementById('recordCount').textContent = '(' + data.length + ' bản ghi)';
    tbody.innerHTML = data.map(function(r) {
        var warehouseName = r.warehouse_name || '';
        if (!warehouseName && r.warehouse_id) {
            var found = warehouses.find(function(w) { return w.id === r.warehouse_id; });
            if (found) warehouseName = found.name;
        }
        var poRef = r.po_reference || r.po_id || '';
        return '<tr>' +
            '<td>' + esc(r.reference || r.id) + '</td>' +
            '<td>' + esc(poRef) + '</td>' +
            '<td>' + esc(warehouseName) + '</td>' +
            '<td>' + (r.received_date || '') + '</td>' +
            '<td>' + statusBadge(r.status) + '</td>' +
            '<td class="text-center"><button class="btn-action" onclick="viewGrDetail(\'' + r.id + '\')"><i class="bi bi-eye"></i></button></td>' +
        '</tr>';
    }).join('');
    if (!data.length) tbody.innerHTML = '<tr><td colspan="6" class="empty-state"><i class="bi bi-inbox"></i>Không có phiếu nhập kho nào</td></tr>';
}

function loadData() {
    fetch('/api/purchase/receipts')
    .then(function(r) { return r.json(); })
    .then(function(data) {
        allData = data || [];
        renderRows(allData);
    })
    .catch(function(err) { showToast('Lỗi tải dữ liệu: ' + err.message, 'error'); });
}

function viewGrDetail(id) {
    fetch('/api/purchase/receipts/' + id)
    .then(function(r) { return r.json(); })
    .then(function(r) {
        var warehouseName = r.warehouse_name || '';
        if (!warehouseName && r.warehouse_id) {
            var found = warehouses.find(function(w) { return w.id === r.warehouse_id; });
            if (found) warehouseName = found.name;
        }
        var html = '<div class="row g-2 mb-3">' +
            '<div class="col-4"><strong>Số PNK:</strong> ' + esc(r.reference || r.id) + '</div>' +
            '<div class="col-4"><strong>PO:</strong> ' + esc(r.po_reference || r.po_id || '') + '</div>' +
            '<div class="col-4"><strong>Trạng thái:</strong> ' + statusBadge(r.status) + '</div>' +
            '<div class="col-4"><strong>Kho:</strong> ' + esc(warehouseName) + '</div>' +
            '<div class="col-4"><strong>Ngày nhập:</strong> ' + (r.received_date || '') + '</div>' +
        '</div><hr><h6>Chi tiết nhập kho</h6>';
        if (r.items && r.items.length) {
            html += '<table class="table table-sm"><thead><tr><th>Hàng hóa</th><th class="text-end">SL đặt</th><th class="text-end">SL nhận</th><th class="text-end">SL từ chối</th></tr></thead><tbody>';
            r.items.forEach(function(l) {
                html += '<tr><td>' + esc(l.item_name || l.item_id) + '</td>' +
                    '<td class="text-end">' + (parseFloat(l.qty_ordered || 0) || 0) + '</td>' +
                    '<td class="text-end">' + (parseFloat(l.qty_received || 0) || 0) + '</td>' +
                    '<td class="text-end">' + (parseFloat(l.qty_rejected || 0) || 0) + '</td></tr>';
            });
            html += '</tbody></table>';
        } else {
            html += '<p class="text-muted">Không có chi tiết</p>';
        }
        showToast('', 'success');
        var tmp = document.createElement('div');
        tmp.style.position = 'fixed'; tmp.style.top = '-9999px';
        tmp.innerHTML = html;
        document.body.appendChild(tmp);
        showToast('Xem chi tiết PNK: ' + esc(r.reference || r.id), 'success');
        document.body.removeChild(tmp);
    })
    .catch(function(err) { showToast('Lỗi tải chi tiết: ' + err.message, 'error'); });
}

document.getElementById('f_po_id').addEventListener('change', function() {
    var poId = this.value;
    var container = document.getElementById('poLinesContainer');
    var msg = document.getElementById('poSelectMessage');
    var tbody = document.getElementById('grLinesBody');

    if (!poId) {
        container.style.display = 'none';
        msg.style.display = 'block';
        return;
    }

    fetch('/api/purchase/orders/' + poId)
    .then(function(r) { return r.json(); })
    .then(function(po) {
        tbody.innerHTML = '';
        if (po.lines && po.lines.length) {
            po.lines.forEach(function(l) {
                var qty = parseFloat(l.qty) || 0;
                var qtyReceived = parseFloat(l.qty_received || 0) || 0;
                var remaining = Math.max(0, qty - qtyReceived);
                var tr = document.createElement('tr');
                tr.innerHTML = '<td>' + esc(l.item_name || l.item_id) + '<input type="hidden" name="po_line_id[]" value="' + esc(l.id) + '"></td>' +
                    '<td class="text-end">' + qty + '</td>' +
                    '<td class="text-end">' + remaining + '</td>' +
                    '<td><input type="number" class="form-control form-control-sm" name="qty_received[]" value="' + remaining + '" step="1" min="0" max="' + remaining + '"></td>' +
                    '<td><input type="number" class="form-control form-control-sm" name="qty_rejected[]" value="0" step="1" min="0"></td>';
                tbody.appendChild(tr);
            });
            container.style.display = 'block';
            msg.style.display = 'none';
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center">PO này không có chi tiết hàng hóa</td></tr>';
            container.style.display = 'block';
            msg.style.display = 'none';
        }
    })
    .catch(function(err) {
        showToast('Lỗi tải PO: ' + err.message, 'error');
        container.style.display = 'none';
        msg.style.display = 'block';
    });
});

document.getElementById('grForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var poId = document.getElementById('f_po_id').value;
    var warehouseId = document.getElementById('f_warehouse_id').value;
    var receivedDate = document.getElementById('f_received_date').value;

    if (!poId || !warehouseId) { showToast('Vui lòng chọn PO và kho nhập', 'error'); return; }

    var items = [];
    var hasItems = false;
    document.querySelectorAll('#grLinesBody tr').forEach(function(tr) {
        var poLineId = tr.querySelector('input[name="po_line_id[]"]');
        var qtyReceived = tr.querySelector('input[name="qty_received[]"]');
        var qtyRejected = tr.querySelector('input[name="qty_rejected[]"]');
        if (poLineId && poLineId.value) {
            var recv = parseFloat(qtyReceived ? qtyReceived.value : 0) || 0;
            var rej = parseFloat(qtyRejected ? qtyRejected.value : 0) || 0;
            if (recv > 0 || rej > 0) hasItems = true;
            items.push({ po_line_id: poLineId.value, qty_received: recv, qty_rejected: rej });
        }
    });

    if (!hasItems) { showToast('Vui lòng nhập ít nhất một dòng hàng hóa', 'error'); return; }

    var data = {
        po_id: poId,
        warehouse_id: warehouseId,
        received_date: receivedDate,
        items: items
    };

    var btn = document.getElementById('grSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Đang xử lý...';

    fetch('/api/purchase/receipts', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(data)
    })
    .then(function(r) {
        if (!r.ok) return r.json().then(function(j) { throw new Error(j.error || 'Lỗi nhập kho'); });
        document.getElementById('grModal').querySelector('[data-bs-dismiss="modal"]').click();
        document.getElementById('grForm').reset();
        document.getElementById('poLinesContainer').style.display = 'none';
        document.getElementById('poSelectMessage').style.display = 'block';
        document.getElementById('grLinesBody').innerHTML = '';
        loadPoOptions();
        showToast('Nhập kho thành công', 'success');
        loadData();
    })
    .catch(function(err) { showToast(err.message, 'error'); })
    .finally(function() { btn.disabled = false; btn.innerHTML = 'Ghi nhận nhập kho'; });
});

loadWarehouses();
loadPoOptions();
loadData();
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
