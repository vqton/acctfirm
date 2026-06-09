<?php
// Màn hình: Quản lý đơn đặt hàng nhà cung cấp (Purchase Order)
// API: GET /api/purchase/orders, POST /api/purchase/orders, GET /api/purchase/orders/{id}, GET /api/suppliers
// Nghiệp vụ: Đơn đặt hàng được tạo từ PR đã duyệt hoặc tạo trực tiếp — gửi NCC, theo dõi nhận hàng
// Trạng thái: draft → pending_approval → sent → partially_received → completed / cancelled
// Rủi ro: PO không khớp PR về giá cả, số lượng — sai chi phí mua hàng
$title = 'Đơn đặt hàng'; $activeMenu = 'purchase_po'; ob_start(); ?>
<div class="toolbar">
    <h5>Đơn đặt hàng <span class="stats" id="recordCount"></span></h5>
    <div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#poModal"><i class="bi bi-plus-lg"></i> Tạo đơn hàng</button>
    </div>
</div>

<div class="card p-2 mb-3 border-0 shadow-sm bg-white" style="font-size:13px;">
    <div class="row g-2 align-items-end">
        <div class="col-auto">
            <input class="form-control form-control-sm" id="filterSearch" placeholder="🔍 Tìm số PO..." style="width:160px;">
        </div>
        <div class="col-auto">
            <select class="form-select form-select-sm" id="filterStatus" style="width:170px;">
                <option value="">Tất cả trạng thái</option>
                <option value="draft">Nháp</option>
                <option value="pending_approval">Chờ duyệt</option>
                <option value="sent">Đã gửi NCC</option>
                <option value="partially_received">Nhận một phần</option>
                <option value="completed">Hoàn thành</option>
                <option value="cancelled">Đã hủy</option>
            </select>
        </div>
        <div class="col-auto">
            <select class="form-select form-select-sm" id="filterSupplier" style="width:200px;"><option value="">Tất cả NCC</option></select>
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-outline-primary" onclick="applyFilters()"><i class="bi bi-funnel"></i> Lọc</button>
            <button class="btn btn-sm btn-outline-secondary" onclick="clearFilters()"><i class="bi bi-x-lg"></i></button>
        </div>
    </div>
</div>

<div class="card-table">
    <table class="table table-hover">
        <thead>
            <tr><th>Số PO</th><th>Nhà cung cấp</th><th class="text-end">Tổng tiền</th><th>Ngày giao</th><th>Trạng thái</th><th class="text-center">Thao tác</th></tr>
        </thead>
        <tbody id="dataBody"></tbody>
    </table>
</div>

<div class="modal fade" id="poModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<form id="poForm">
    <div class="modal-header"><h5 class="modal-title">Tạo đơn đặt hàng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-2 mb-2">
            <div class="col-4"><label>PR liên quan</label><input class="form-control" id="f_pr_id" placeholder="Số PR (nếu có)"></div>
            <div class="col-8"><label>Nhà cung cấp *</label>
                <select class="form-select" id="f_supplier_id" required><option value="">-- Chọn NCC --</option></select>
            </div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-4"><label>Điều khoản thanh toán</label><input class="form-control" id="f_payment_terms" placeholder="VD: 30 ngày"></div>
            <div class="col-4"><label>Điều khoản giao hàng</label><input class="form-control" id="f_delivery_terms" placeholder="VD: Giao tại kho"></div>
            <div class="col-4"><label>Ngày giao dự kiến</label><input type="date" class="form-control" id="f_expected_delivery" required></div>
        </div>
        <h6 class="mb-2">Chi tiết hàng hóa</h6>
        <table class="table table-sm table-bordered" id="poLinesTable">
            <thead><tr><th style="width:35%">Hàng hóa</th><th style="width:15%">Số lượng</th><th style="width:20%">Đơn giá</th><th style="width:20%">Thành tiền</th><th style="width:10%"></th></tr></thead>
            <tbody id="poLinesBody">
                <tr><td><input class="form-control form-control-sm" name="item_id[]" placeholder="Mã hàng"></td><td><input type="number" class="form-control form-control-sm po-qty-input" name="qty[]" step="1" min="1"></td><td><input type="number" class="form-control form-control-sm po-price-input" name="price[]" step="1000" min="0"></td><td class="text-end font-monospace po-line-total align-middle">0</td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removePoLine(this)"><i class="bi bi-x"></i></button></td></tr>
            </tbody>
        </table>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPoLine()"><i class="bi bi-plus"></i> Thêm dòng</button>
        <div class="mt-2 text-end"><strong>Tổng tiền hàng: </strong><span id="poTotal">0</span></div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-sm btn-primary">Tạo đơn hàng</button>
    </div>
</form>
</div></div></div>

<div class="modal fade" id="detailModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Chi tiết đơn đặt hàng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="detailBody"></div>
    <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Đóng</button></div>
</div></div></div>

<script>
var allData = [];
var suppliers = [];

function addPoLine() {
    var tbody = document.getElementById('poLinesBody');
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><input class="form-control form-control-sm" name="item_id[]" placeholder="Mã hàng"></td><td><input type="number" class="form-control form-control-sm po-qty-input" name="qty[]" step="1" min="1"></td><td><input type="number" class="form-control form-control-sm po-price-input" name="price[]" step="1000" min="0"></td><td class="text-end font-monospace po-line-total align-middle">0</td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removePoLine(this)"><i class="bi bi-x"></i></button></td>';
    tbody.appendChild(tr);
    tr.querySelectorAll('.po-qty-input, .po-price-input').forEach(function(el) { el.addEventListener('input', updatePoLineTotals); });
}

function removePoLine(btn) {
    var tr = btn.closest('tr');
    if (document.getElementById('poLinesBody').children.length > 1) {
        tr.remove();
        updatePoLineTotals();
    }
}

function updatePoLineTotals() {
    var grandTotal = 0;
    document.querySelectorAll('#poLinesBody tr').forEach(function(tr) {
        var qty = parseFloat(tr.querySelector('.po-qty-input') ? tr.querySelector('.po-qty-input').value : 0) || 0;
        var price = parseFloat(tr.querySelector('.po-price-input') ? tr.querySelector('.po-price-input').value : 0) || 0;
        var total = qty * price;
        var td = tr.querySelector('.po-line-total');
        if (td) td.textContent = total.toLocaleString();
        grandTotal += total;
    });
    document.getElementById('poTotal').textContent = grandTotal.toLocaleString() + ' VND';
}

function loadSuppliers() {
    fetch('/api/suppliers')
    .then(function(r) { return r.json(); })
    .then(function(data) {
        suppliers = data || [];
        var sel = document.getElementById('f_supplier_id');
        var filterSel = document.getElementById('filterSupplier');
        sel.innerHTML = '<option value="">-- Chọn NCC --</option>';
        filterSel.innerHTML = '<option value="">Tất cả NCC</option>';
        suppliers.forEach(function(s) {
            sel.innerHTML += '<option value="' + esc(s.id) + '">' + esc(s.name) + '</option>';
            filterSel.innerHTML += '<option value="' + esc(s.id) + '">' + esc(s.name) + '</option>';
        });
    })
    .catch(function(err) { showToast('Lỗi tải danh sách NCC', 'error'); });
}

function renderRows(data) {
    var tbody = document.getElementById('dataBody');
    document.getElementById('recordCount').textContent = '(' + data.length + ' bản ghi)';
    tbody.innerHTML = data.map(function(r) {
        var total = parseFloat(r.total_amount || 0).toLocaleString();
        var supplierName = r.supplier_name || '';
        if (!supplierName && r.supplier_id) {
            var found = suppliers.find(function(s) { return s.id === r.supplier_id; });
            if (found) supplierName = found.name;
        }
        return '<tr onclick="viewDetail(\'' + r.id + '\')" style="cursor:pointer">' +
            '<td>' + esc(r.reference || r.id) + '</td>' +
            '<td>' + esc(supplierName) + '</td>' +
            '<td class="text-end font-monospace">' + total + '</td>' +
            '<td>' + (r.expected_delivery || r.delivery_date || '') + '</td>' +
            '<td>' + statusBadge(r.status) + '</td>' +
            '<td class="text-center"><button class="btn-action" onclick="event.stopPropagation();viewDetail(\'' + r.id + '\')"><i class="bi bi-eye"></i></button></td>' +
        '</tr>';
    }).join('');
    if (!data.length) tbody.innerHTML = '<tr><td colspan="6" class="empty-state"><i class="bi bi-inbox"></i>Không có đơn đặt hàng nào</td></tr>';
}

function applyFilters() {
    var s = document.getElementById('filterSearch').value.toLowerCase().trim();
    var st = document.getElementById('filterStatus').value;
    var su = document.getElementById('filterSupplier').value;
    var filtered = allData.filter(function(r) {
        if (s && !(r.reference || r.id).toLowerCase().includes(s)) return false;
        if (st && r.status !== st) return false;
        if (su && r.supplier_id !== su) return false;
        return true;
    });
    renderRows(filtered);
}

function clearFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterSupplier').value = '';
    renderRows(allData);
}

function loadData() {
    fetch('/api/purchase/orders')
    .then(function(r) { return r.json(); })
    .then(function(data) {
        allData = data || [];
        renderRows(allData);
    })
    .catch(function(err) { showToast('Lỗi tải dữ liệu: ' + err.message, 'error'); });
}

function viewDetail(id) {
    fetch('/api/purchase/orders/' + id)
    .then(function(r) { return r.json(); })
    .then(function(r) {
        var supplierName = r.supplier_name || '';
        if (!supplierName && r.supplier_id) {
            var found = suppliers.find(function(s) { return s.id === r.supplier_id; });
            if (found) supplierName = found.name;
        }
        var html = '<div class="row g-2 mb-3">' +
            '<div class="col-4"><strong>Số PO:</strong> ' + esc(r.reference || r.id) + '</div>' +
            '<div class="col-4"><strong>Nhà cung cấp:</strong> ' + esc(supplierName) + '</div>' +
            '<div class="col-4"><strong>Trạng thái:</strong> ' + statusBadge(r.status) + '</div>' +
            '<div class="col-4"><strong>PR liên quan:</strong> ' + (r.pr_reference || r.pr_id || '—') + '</div>' +
            '<div class="col-4"><strong>Ngày giao:</strong> ' + (r.expected_delivery || r.delivery_date || '') + '</div>' +
            '<div class="col-4"><strong>Thanh toán:</strong> ' + esc(r.payment_terms || '') + '</div>' +
            (r.delivery_terms ? '<div class="col-4"><strong>Giao hàng:</strong> ' + esc(r.delivery_terms) + '</div>' : '') +
        '</div><hr><h6>Chi tiết hàng hóa</h6>';
        if (r.lines && r.lines.length) {
            html += '<table class="table table-sm"><thead><tr><th>Hàng hóa</th><th class="text-end">SL</th><th class="text-end">Đơn giá</th><th class="text-end">Thành tiền</th><th class="text-end">Đã nhận</th></tr></thead><tbody>';
            r.lines.forEach(function(l) {
                var total = (parseFloat(l.qty) || 0) * (parseFloat(l.unit_price || l.price) || 0);
                html += '<tr><td>' + esc(l.item_name || l.item_id) + '</td>' +
                    '<td class="text-end">' + (parseFloat(l.qty) || 0) + '</td>' +
                    '<td class="text-end font-monospace">' + (parseFloat(l.unit_price || l.price) || 0).toLocaleString() + '</td>' +
                    '<td class="text-end font-monospace">' + total.toLocaleString() + '</td>' +
                    '<td class="text-end">' + (parseFloat(l.qty_received || 0) || 0) + '</td></tr>';
            });
            html += '</tbody></table>';
        } else {
            html += '<p class="text-muted">Không có chi tiết</p>';
        }
        document.getElementById('detailBody').innerHTML = html;
        $('#detailModal').modal('show');
    })
    .catch(function(err) { showToast('Lỗi tải chi tiết: ' + err.message, 'error'); });
}

document.getElementById('poForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var lines = [];
    document.querySelectorAll('#poLinesBody tr').forEach(function(tr) {
        var itemId = tr.querySelector('input[name="item_id[]"]').value.trim();
        var qty = parseFloat(tr.querySelector('input[name="qty[]"]').value) || 0;
        var price = parseFloat(tr.querySelector('input[name="price[]"]').value) || 0;
        if (itemId && qty > 0) {
            lines.push({ item_id: itemId, qty: qty, unit_price: price });
        }
    });
    if (lines.length === 0) { showToast('Vui lòng thêm ít nhất một dòng hàng hóa', 'error'); return; }
    var data = {
        pr_id: document.getElementById('f_pr_id').value.trim(),
        supplier_id: document.getElementById('f_supplier_id').value,
        payment_terms: document.getElementById('f_payment_terms').value.trim(),
        delivery_terms: document.getElementById('f_delivery_terms').value.trim(),
        expected_delivery: document.getElementById('f_expected_delivery').value,
        items: lines
    };
    var btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch('/api/purchase/orders', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(data)
    })
    .then(function(r) {
        if (!r.ok) return r.json().then(function(j) { throw new Error(j.error || 'Lỗi tạo đơn hàng'); });
        document.getElementById('poModal').querySelector('[data-bs-dismiss="modal"]').click();
        document.getElementById('poForm').reset();
        document.getElementById('poLinesBody').innerHTML = '<tr><td><input class="form-control form-control-sm" name="item_id[]" placeholder="Mã hàng"></td><td><input type="number" class="form-control form-control-sm po-qty-input" name="qty[]" step="1" min="1"></td><td><input type="number" class="form-control form-control-sm po-price-input" name="price[]" step="1000" min="0"></td><td class="text-end font-monospace po-line-total align-middle">0</td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removePoLine(this)"><i class="bi bi-x"></i></button></td></tr>';
        document.getElementById('poTotal').textContent = '0';
        loadSuppliers();
        showToast('Đã tạo đơn đặt hàng thành công', 'success');
        loadData();
    })
    .catch(function(err) { showToast(err.message, 'error'); })
    .finally(function() { btn.disabled = false; btn.innerHTML = 'Tạo đơn hàng'; });
});

document.getElementById('poLinesBody').addEventListener('input', function(e) {
    if (e.target.classList.contains('po-qty-input') || e.target.classList.contains('po-price-input')) {
        updatePoLineTotals();
    }
});

document.getElementById('filterSearch').addEventListener('keyup', applyFilters);
document.getElementById('filterStatus').addEventListener('change', applyFilters);
document.getElementById('filterSupplier').addEventListener('change', applyFilters);

loadSuppliers();
loadData();
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
