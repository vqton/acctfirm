<?php
// Màn hình: Đối chiếu hóa đơn 3 chiều (PO ↔ GR ↔ Hóa đơn)
// API: GET /api/purchase/matches, POST /api/purchase/matches, GET /api/purchase/orders, GET /api/purchase/orders/{id}
// Nghiệp vụ: So khớp hóa đơn nhà cung cấp với PO và phiếu nhập kho
// Trạng thái: matched → badge-active, warning → badge-warning, mismatch → badge-danger, pending → badge-inactive
// Rủi ro: Bỏ qua mismatch → thanh toán sai giá/sai số lượng → mất tiền
$title = 'Đối chiếu hóa đơn'; $activeMenu = 'purchase_match'; ob_start(); ?>
<div class="toolbar">
    <h5>Đối chiếu hóa đơn <span class="stats" id="recordCount"></span></h5>
    <div></div>
</div>

<div class="card-table mb-4">
    <div class="card-header-x"><span>Kết quả đối chiếu</span></div>
    <table class="table table-hover">
        <thead><tr><th>Số hóa đơn</th><th>PO</th><th>Ngày hóa đơn</th><th class="text-end">Số tiền</th><th>Trạng thái</th><th class="text-center">Thao tác</th></tr></thead>
        <tbody id="dataBody"></tbody>
    </table>
</div>

<div class="card-table">
    <div class="card-header-x"><span>Tạo đối chiếu mới</span></div>
    <div class="p-3">
        <form id="matchForm">
            <div class="row g-2 mb-2">
                <div class="col-4">
                    <label>Đơn đặt hàng *</label>
                    <select class="form-select" id="f_po_id" required>
                        <option value="">-- Chọn PO --</option>
                    </select>
                </div>
                <div class="col-4">
                    <label>Số hóa đơn *</label>
                    <input class="form-control" id="f_invoice_no" required>
                </div>
                <div class="col-2">
                    <label>Ngày hóa đơn</label>
                    <input type="date" class="form-control" id="f_invoice_date" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-2">
                    <label>Thuế GTGT</label>
                    <input type="number" class="form-control" id="f_vat_amount" step="1000" min="0" value="0">
                </div>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-4">
                    <label>Tổng tiền hóa đơn *</label>
                    <input type="number" class="form-control" id="f_invoice_amount" step="1000" min="1" required>
                </div>
            </div>
            <div id="poLinesContainer" style="display:none">
                <h6 class="mb-2">Chi tiết đối chiếu</h6>
                <table class="table table-sm table-bordered">
                    <thead><tr><th>Hàng hóa</th><th class="text-end">SL đặt</th><th class="text-end">SL nhận</th><th class="text-end">Đơn giá PO</th><th style="width:14%">SL hóa đơn</th><th style="width:14%">Đơn giá HĐ</th></tr></thead>
                    <tbody id="matchLinesBody"></tbody>
                </table>
            </div>
            <div id="poSelectMessage" class="text-muted text-center py-4"><i class="bi bi-inbox"></i> Vui lòng chọn đơn đặt hàng để đối chiếu</div>
            <button type="submit" class="btn btn-primary btn-sm" id="matchSubmitBtn">Thực hiện đối chiếu</button>
        </form>
        <div id="matchResult" class="mt-3" style="display:none"></div>
    </div>
</div>

<script>
var allData = [];

function statusBadge(s) {
    switch (s) {
        case 'matched': return 'badge-active';
        case 'warning': return 'badge-warning';
        case 'mismatch': return 'badge-danger';
        case 'pending': return 'badge-inactive';
        default: return 'badge-inactive';
    }
}

function statusLabel(s) {
    switch (s) {
        case 'matched': return 'Đã khớp';
        case 'warning': return 'Cảnh báo';
        case 'mismatch': return 'Lệch';
        case 'pending': return 'Chờ xử lý';
        default: return s;
    }
}

function renderRows(data) {
    var tbody = document.getElementById('dataBody');
    document.getElementById('recordCount').textContent = '(' + data.length + ' bản ghi)';
    tbody.innerHTML = data.map(function(r) {
        var badge = statusBadge(r.match_status);
        return '<tr>' +
            '<td>' + esc(r.supplier_invoice_no || '') + '</td>' +
            '<td>' + esc(r.po_id || '') + '</td>' +
            '<td>' + (r.invoice_date || '') + '</td>' +
            '<td class="text-end font-monospace">' + parseFloat(r.invoice_amount || 0).toLocaleString() + '</td>' +
            '<td><span class="badge-status ' + badge + '">' + statusLabel(r.match_status) + '</span></td>' +
            '<td class="text-center"><button class="btn-action" onclick="viewMatchDetail(\'' + r.id + '\')"><i class="bi bi-eye"></i></button></td>' +
        '</tr>';
    }).join('');
    if (!data.length) tbody.innerHTML = '<tr><td colspan="6" class="empty-state"><i class="bi bi-inbox"></i>Không có kết quả đối chiếu nào</td></tr>';
}

function loadPOs() {
    var sel = document.getElementById('f_po_id');
    sel.innerHTML = '<option value="">-- Chọn PO --</option>';
    fetch('/api/purchase/orders')
    .then(function(r) { return r.json(); })
    .then(function(data) {
        (data || []).forEach(function(po) {
            var label = esc(po.reference || po.id) + ' - ' + esc(po.supplier_name || '') + ' (' + parseFloat(po.total_amount || 0).toLocaleString() + ')';
            sel.innerHTML += '<option value="' + esc(po.id) + '">' + label + '</option>';
        });
    })
    .catch(function(err) { showToast('Lỗi tải danh sách PO: ' + err.message, 'error'); });
}

function loadData() {
    fetch('/api/purchase/matches')
    .then(function(r) { return r.json(); })
    .then(function(data) {
        allData = data || [];
        renderRows(allData);
    })
    .catch(function(err) { showToast('Lỗi tải dữ liệu: ' + err.message, 'error'); });
}

function viewMatchDetail(id) {
    var match = allData.find(function(m) { return m.id === id; });
    if (!match) { showToast('Không tìm thấy dữ liệu', 'error'); return; }
    var lines = match.lines || [];
    var html = '<div class="row g-2 mb-3">' +
        '<div class="col-4"><strong>Hóa đơn:</strong> ' + esc(match.supplier_invoice_no || '') + '</div>' +
        '<div class="col-4"><strong>PO:</strong> ' + esc(match.po_id || '') + '</div>' +
        '<div class="col-4"><strong>Trạng thái:</strong> <span class="badge-status ' + statusBadge(match.match_status) + '">' + statusLabel(match.match_status) + '</span></div>' +
        '<div class="col-4"><strong>Ngày HĐ:</strong> ' + (match.invoice_date || '') + '</div>' +
        '<div class="col-4"><strong>Tiền HĐ:</strong> ' + parseFloat(match.invoice_amount || 0).toLocaleString() + '</div>' +
        (match.vat_amount ? '<div class="col-4"><strong>Thuế GTGT:</strong> ' + parseFloat(match.vat_amount).toLocaleString() + '</div>' : '') +
    '</div><hr><h6>Chi tiết đối chiếu</h6>';
    if (lines.length) {
        html += '<table class="table table-sm"><thead><tr><th>Hàng hóa</th><th class="text-end">SL HĐ</th><th class="text-end">SL nhận</th><th class="text-end">ĐG HĐ</th><th class="text-end">ĐG PO</th><th>SL</th><th>ĐG</th></tr></thead><tbody>';
        lines.forEach(function(l) {
            var qtyOk = l.qty_tolerance_pass ? '<span class="badge-status badge-active">OK</span>' : '<span class="badge-status badge-danger">Lệch</span>';
            var priceOk = l.price_tolerance_pass ? '<span class="badge-status badge-active">OK</span>' : '<span class="badge-status badge-danger">Lệch</span>';
            html += '<tr><td>' + esc(l.item_id || '') + '</td>' +
                '<td class="text-end">' + (parseFloat(l.qty_invoiced || 0) || 0) + '</td>' +
                '<td class="text-end">' + (parseFloat(l.qty_received || 0) || 0) + '</td>' +
                '<td class="text-end font-monospace">' + (parseFloat(l.unit_price_invoiced || 0) || 0).toLocaleString() + '</td>' +
                '<td class="text-end font-monospace">' + (parseFloat(l.unit_price_po || 0) || 0).toLocaleString() + '</td>' +
                '<td class="text-center">' + qtyOk + '</td>' +
                '<td class="text-center">' + priceOk + '</td></tr>';
        });
        html += '</tbody></table>';
    } else {
        html += '<p class="text-muted">Không có chi tiết</p>';
    }
    var tmp = document.createElement('div');
    tmp.style.cssText = 'position:fixed;top:-9999px;';
    tmp.innerHTML = html;
    document.body.appendChild(tmp);
    var matchResult = document.getElementById('matchResult');
    matchResult.innerHTML = '<div class="alert alert-info"><button type="button" class="btn-close float-end" onclick="this.parentElement.remove()"></button>' + html + '</div>';
    matchResult.style.display = 'block';
    document.body.removeChild(tmp);
}

document.getElementById('f_po_id').addEventListener('change', function() {
    var poId = this.value;
    var container = document.getElementById('poLinesContainer');
    var msg = document.getElementById('poSelectMessage');
    var tbody = document.getElementById('matchLinesBody');

    if (!poId) {
        container.style.display = 'none';
        msg.style.display = 'block';
        return;
    }

    fetch('/api/purchase/orders/' + poId)
    .then(function(r) { return r.json(); })
    .then(function(po) {
        tbody.innerHTML = '';
        var lines = po.lines || [];
        if (lines.length) {
            lines.forEach(function(l) {
                var qty = parseFloat(l.qty) || 0;
                var qtyReceived = parseFloat(l.qty_received || 0) || 0;
                var unitPrice = parseFloat(l.unit_price || l.price || 0) || 0;
                var defaultQty = qtyReceived > 0 ? qtyReceived : qty;
                var tr = document.createElement('tr');
                tr.innerHTML = '<td>' + esc(l.item_name || l.item_id) + '<input type="hidden" name="po_line_id[]" value="' + esc(l.id || '') + '"></td>' +
                    '<td class="text-end">' + qty + '</td>' +
                    '<td class="text-end">' + qtyReceived + '</td>' +
                    '<td class="text-end font-monospace">' + unitPrice.toLocaleString() + '</td>' +
                    '<td><input type="number" class="form-control form-control-sm" name="qty_invoiced[]" value="' + defaultQty + '" step="1" min="0"></td>' +
                    '<td><input type="number" class="form-control form-control-sm" name="unit_price_invoiced[]" value="' + unitPrice + '" step="100" min="0"></td>';
                tbody.appendChild(tr);
            });
            container.style.display = 'block';
            msg.style.display = 'none';
        } else {
            tbody.innerHTML = '<tr><td colspan="6" class="text-muted text-center">PO này không có chi tiết hàng hóa</td></tr>';
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

document.getElementById('matchForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var poId = document.getElementById('f_po_id').value;
    var invoiceNo = document.getElementById('f_invoice_no').value.trim();
    var invoiceDate = document.getElementById('f_invoice_date').value;
    var invoiceAmount = parseFloat(document.getElementById('f_invoice_amount').value) || 0;
    var vatAmount = parseFloat(document.getElementById('f_vat_amount').value) || 0;

    if (!poId || !invoiceNo) { showToast('Vui lòng chọn PO và nhập số hóa đơn', 'error'); return; }
    if (invoiceAmount <= 0) { showToast('Vui lòng nhập số tiền hóa đơn', 'error'); return; }

    var items = [];
    var hasItems = false;
    document.querySelectorAll('#matchLinesBody tr').forEach(function(tr) {
        var poLineId = tr.querySelector('input[name="po_line_id[]"]');
        var qtyInv = tr.querySelector('input[name="qty_invoiced[]"]');
        var priceInv = tr.querySelector('input[name="unit_price_invoiced[]"]');
        if (poLineId && poLineId.value) {
            var qi = parseFloat(qtyInv ? qtyInv.value : 0) || 0;
            var pi = parseFloat(priceInv ? priceInv.value : 0) || 0;
            if (qi > 0) hasItems = true;
            items.push({ po_line_id: poLineId.value, qty_invoiced: qi, unit_price_invoiced: pi });
        }
    });

    if (!hasItems) { showToast('Vui lòng nhập ít nhất một dòng hàng hóa', 'error'); return; }

    var data = {
        po_id: poId,
        supplier_invoice_no: invoiceNo,
        invoice_date: invoiceDate,
        invoice_amount: invoiceAmount,
        vat_amount: vatAmount,
        items: items
    };

    var btn = document.getElementById('matchSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Đang xử lý...';

    fetch('/api/purchase/matches', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(data)
    })
    .then(function(r) {
        if (!r.ok) return r.json().then(function(j) { throw new Error(j.error || 'Lỗi đối chiếu hóa đơn'); });
        return r.json();
    })
    .then(function(result) {
        var statusClass = result.match_status === 'matched' ? 'alert-success' : (result.match_status === 'warning' ? 'alert-warning' : 'alert-danger');
        var statusText = statusLabel(result.match_status);
        var resultDiv = document.getElementById('matchResult');
        var html = '<div class="alert ' + statusClass + '"><h6>Kết quả: ' + statusText + '</h6>';
        if (result.lines && result.lines.length) {
            html += '<table class="table table-sm mb-0"><thead><tr><th>Hàng hóa</th><th class="text-end">SL HĐ</th><th class="text-end">SL nhận</th><th class="text-end">ĐG HĐ</th><th class="text-end">ĐG PO</th><th>SL</th><th>ĐG</th></tr></thead><tbody>';
            result.lines.forEach(function(l) {
                var qtyOk = l.qty_pass ? '<span class="badge-status badge-active">OK</span>' : '<span class="badge-status badge-danger">Lệch</span>';
                var priceOk = l.price_pass ? '<span class="badge-status badge-active">OK</span>' : '<span class="badge-status badge-danger">Lệch</span>';
                html += '<tr><td>' + esc(l.item_id || '') + '</td>' +
                    '<td class="text-end">' + (parseFloat(l.qty_invoiced || 0) || 0) + '</td>' +
                    '<td class="text-end">' + (parseFloat(l.qty_received || 0) || 0) + '</td>' +
                    '<td class="text-end font-monospace">' + (parseFloat(l.unit_price_invoiced || 0) || 0).toLocaleString() + '</td>' +
                    '<td class="text-end font-monospace">' + (parseFloat(l.unit_price_po || 0) || 0).toLocaleString() + '</td>' +
                    '<td class="text-center">' + qtyOk + '</td>' +
                    '<td class="text-center">' + priceOk + '</td></tr>';
            });
            html += '</tbody></table>';
        }
        html += '</div>';
        resultDiv.innerHTML = html;
        resultDiv.style.display = 'block';
        showToast('Đối chiếu thành công: ' + statusText, result.match_status === 'matched' ? 'success' : 'error');
        loadData();
        document.getElementById('matchForm').reset();
        document.getElementById('poLinesContainer').style.display = 'none';
        document.getElementById('poSelectMessage').style.display = 'block';
        document.getElementById('matchLinesBody').innerHTML = '';
    })
    .catch(function(err) { showToast(err.message, 'error'); })
    .finally(function() { btn.disabled = false; btn.innerHTML = 'Thực hiện đối chiếu'; });
});

loadPOs();
loadData();
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
