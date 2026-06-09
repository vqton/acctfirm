<?php
// Màn hình: Quản lý đề nghị mua hàng (Purchase Requisition)
// API: GET /api/purchase/requisitions, POST /api/purchase/requisitions, POST /api/purchase/requisitions/{id}/approve
// Nghiệp vụ: Đề xuất mua hàng hóa/dịch vụ — cần phê duyệt trước khi tạo PO
// Trạng thái: draft (nháp) → pending (chờ duyệt) → approved (đã duyệt) / rejected (từ chối) → fulfilled (đã đặt hàng)
// Rủi ro: Phê duyệt PR chưa có đủ thông tin ngân sách → sai kế hoạch mua hàng
$title = 'Đề nghị mua hàng'; $activeMenu = 'purchase_pr'; ob_start(); ?>
<div class="toolbar">
    <h5>Đề nghị mua hàng <span class="stats" id="recordCount"></span></h5>
    <div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#prModal"><i class="bi bi-plus-lg"></i> Tạo đề nghị</button>
    </div>
</div>

<div class="card p-2 mb-3 border-0 shadow-sm bg-white" style="font-size:13px;">
    <div class="row g-2 align-items-end">
        <div class="col-auto">
            <input class="form-control form-control-sm" id="filterSearch" placeholder="🔍 Tìm số PR..." style="width:160px;">
        </div>
        <div class="col-auto">
            <select class="form-select form-select-sm" id="filterStatus" style="width:150px;">
                <option value="">Tất cả trạng thái</option>
                <option value="draft">Nháp</option>
                <option value="pending">Chờ duyệt</option>
                <option value="approved">Đã duyệt</option>
                <option value="rejected">Từ chối</option>
                <option value="fulfilled">Đã đặt hàng</option>
            </select>
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
            <tr><th>Số PR</th><th>Người đề nghị</th><th>Phòng ban</th><th class="text-end">Tổng tiền</th><th>Ngày giao</th><th>Trạng thái</th><th class="text-center">Thao tác</th></tr>
        </thead>
        <tbody id="dataBody"></tbody>
    </table>
</div>

<div class="modal fade" id="prModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<form id="prForm">
    <div class="modal-header"><h5 class="modal-title">Tạo đề nghị mua hàng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="row g-2 mb-2">
            <div class="col-4"><label>Người đề nghị</label><input class="form-control" id="f_requester_id" required></div>
            <div class="col-4"><label>Phòng ban</label><input class="form-control" id="f_department_id" required></div>
            <div class="col-4"><label>Ngày giao dự kiến</label><input type="date" class="form-control" id="f_delivery_date" required></div>
        </div>
        <div class="mb-3"><label>Ghi chú</label><textarea class="form-control" id="f_note" rows="2"></textarea></div>

        <h6 class="mb-2">Chi tiết hàng hóa</h6>
        <table class="table table-sm table-bordered" id="linesTable">
            <thead><tr><th style="width:40%">Hàng hóa</th><th style="width:20%">Số lượng</th><th style="width:25%">Đơn giá dự kiến</th><th style="width:15%"></th></tr></thead>
            <tbody id="linesBody">
                <tr><td><input class="form-control form-control-sm" name="item_id[]" placeholder="Mã/NCC"></td><td><input type="number" class="form-control form-control-sm qty-input" name="qty[]" step="1" min="1"></td><td><input type="number" class="form-control form-control-sm price-input" name="price[]" step="1000" min="0"></td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLine(this)"><i class="bi bi-x"></i></button></td></tr>
            </tbody>
        </table>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addLine()"><i class="bi bi-plus"></i> Thêm dòng</button>
        <div class="mt-2 text-end"><strong>Tổng: </strong><span id="lineTotal">0</span></div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="submit" class="btn btn-sm btn-primary">Gửi đề nghị</button>
    </div>
</form>
</div></div></div>

<script>
var allData = [];

function addLine() {
    var tbody = document.getElementById('linesBody');
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><input class="form-control form-control-sm" name="item_id[]" placeholder="Mã hàng"></td><td><input type="number" class="form-control form-control-sm qty-input" name="qty[]" step="1" min="1"></td><td><input type="number" class="form-control form-control-sm price-input" name="price[]" step="1000" min="0"></td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLine(this)"><i class="bi bi-x"></i></button></td>';
    tbody.appendChild(tr);
    tr.querySelectorAll('.qty-input, .price-input').forEach(function(el) { el.addEventListener('input', updateLineTotal); });
}

function removeLine(btn) {
    var tr = btn.closest('tr');
    if (document.getElementById('linesBody').children.length > 1) {
        tr.remove();
        updateLineTotal();
    }
}

function updateLineTotal() {
    var total = 0;
    document.querySelectorAll('#linesBody tr').forEach(function(tr) {
        var qtyInput = tr.querySelector('.qty-input');
        var priceInput = tr.querySelector('.price-input');
        var qty = parseFloat(qtyInput ? qtyInput.value : 0) || 0;
        var price = parseFloat(priceInput ? priceInput.value : 0) || 0;
        total += qty * price;
    });
    document.getElementById('lineTotal').textContent = total.toLocaleString() + ' VND';
}

function renderRows(data) {
    var tbody = document.getElementById('dataBody');
    document.getElementById('recordCount').textContent = '(' + data.length + ' bản ghi)';
    tbody.innerHTML = data.map(function(r) {
        var total = parseFloat(r.total_amount || 0).toLocaleString();
        var actions = '<button class="btn-action me-1" onclick="viewDetail(\'' + r.id + '\')" title="Xem"><i class="bi bi-eye"></i></button>';
        if (r.status === 'pending') {
            actions += '<button class="btn-action text-success me-1" onclick="approvePr(\'' + r.id + '\')" title="Duyệt"><i class="bi bi-check-lg"></i></button>';
        }
        return '<tr>' +
            '<td>' + esc(r.reference || r.id) + '</td>' +
            '<td>' + esc(r.requester_name || r.requester_id) + '</td>' +
            '<td>' + esc(r.department_name || r.department_id) + '</td>' +
            '<td class="text-end font-monospace">' + total + '</td>' +
            '<td>' + (r.delivery_date || '') + '</td>' +
            '<td>' + statusBadge(r.status) + '</td>' +
            '<td class="text-center">' + actions + '</td>' +
        '</tr>';
    }).join('');
    if (!data.length) tbody.innerHTML = '<tr><td colspan="7" class="empty-state"><i class="bi bi-inbox"></i>Không có đề nghị mua hàng nào</td></tr>';
}

function applyFilters() {
    var s = document.getElementById('filterSearch').value.toLowerCase().trim();
    var st = document.getElementById('filterStatus').value;
    var filtered = allData.filter(function(r) {
        if (s && !(r.reference || r.id).toLowerCase().includes(s)) return false;
        if (st && r.status !== st) return false;
        return true;
    });
    renderRows(filtered);
}

function clearFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterStatus').value = '';
    renderRows(allData);
}

function loadData() {
    fetch('/api/purchase/requisitions')
    .then(function(r) { return r.json(); })
    .then(function(data) {
        allData = data || [];
        renderRows(allData);
    })
    .catch(function(err) { showToast('Lỗi tải dữ liệu: ' + err.message, 'error'); });
}

function viewDetail(id) {
    fetch('/api/purchase/requisitions/' + id)
    .then(function(r) { return r.json(); })
    .then(function(r) {
        var msg = '<strong>Số PR:</strong> ' + esc(r.reference || r.id) + '<br>';
        msg += '<strong>Người đề nghị:</strong> ' + esc(r.requester_name || r.requester_id) + '<br>';
        msg += '<strong>Phòng ban:</strong> ' + esc(r.department_name || r.department_id) + '<br>';
        msg += '<strong>Ngày giao:</strong> ' + (r.delivery_date || '') + '<br>';
        if (r.note) msg += '<strong>Ghi chú:</strong> ' + esc(r.note) + '<br>';
        msg += '<strong>Trạng thái:</strong> ' + statusBadge(r.status) + '<br>';
        msg += '<hr><h6>Chi tiết hàng hóa</h6>';
        if (r.lines && r.lines.length) {
            msg += '<table class="table table-sm"><thead><tr><th>Hàng hóa</th><th class="text-end">SL</th><th class="text-end">Đơn giá</th><th class="text-end">Thành tiền</th></tr></thead><tbody>';
            r.lines.forEach(function(l) {
                msg += '<tr><td>' + esc(l.item_name || l.item_id) + '</td><td class="text-end">' + (parseFloat(l.qty) || 0) + '</td><td class="text-end font-monospace">' + (parseFloat(l.unit_price || l.price) || 0).toLocaleString() + '</td><td class="text-end font-monospace">' + ((parseFloat(l.qty) || 0) * (parseFloat(l.unit_price || l.price) || 0)).toLocaleString() + '</td></tr>';
            });
            msg += '</tbody></table>';
        } else {
            msg += '<p class="text-muted">Không có chi tiết</p>';
        }
        showToast(msg, 'success');
    })
    .catch(function(err) { showToast('Lỗi tải chi tiết: ' + err.message, 'error'); });
}

function approvePr(id) {
    if (!confirm('Xác nhận duyệt đề nghị mua hàng này?')) return;
    fetch('/api/purchase/requisitions/' + id + '/approve', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrf }
    })
    .then(function(r) {
        if (!r.ok) return r.json().then(function(j) { throw new Error(j.error || 'Lỗi duyệt'); });
        showToast('Đã duyệt đề nghị thành công', 'success');
        loadData();
    })
    .catch(function(err) { showToast(err.message, 'error'); });
}

document.getElementById('prForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var lines = [];
    var itemInputs = document.querySelectorAll('#linesBody tr');
    itemInputs.forEach(function(tr) {
        var itemId = tr.querySelector('input[name="item_id[]"]').value.trim();
        var qty = parseFloat(tr.querySelector('input[name="qty[]"]').value) || 0;
        var price = parseFloat(tr.querySelector('input[name="price[]"]').value) || 0;
        if (itemId && qty > 0) {
            lines.push({ item_id: itemId, qty: qty, unit_price: price });
        }
    });
    if (lines.length === 0) { showToast('Vui lòng thêm ít nhất một dòng hàng hóa', 'error'); return; }
    var data = {
        requester_id: document.getElementById('f_requester_id').value.trim(),
        department_id: document.getElementById('f_department_id').value.trim(),
        delivery_date: document.getElementById('f_delivery_date').value,
        note: document.getElementById('f_note').value.trim(),
        items: lines
    };
    var btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch('/api/purchase/requisitions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(data)
    })
    .then(function(r) {
        if (!r.ok) return r.json().then(function(j) { throw new Error(j.error || 'Lỗi tạo đề nghị'); });
        document.getElementById('prModal').querySelector('[data-bs-dismiss="modal"]').click();
        document.getElementById('prForm').reset();
        document.getElementById('linesBody').innerHTML = '<tr><td><input class="form-control form-control-sm" name="item_id[]" placeholder="Mã/NCC"></td><td><input type="number" class="form-control form-control-sm qty-input" name="qty[]" step="1" min="1"></td><td><input type="number" class="form-control form-control-sm price-input" name="price[]" step="1000" min="0"></td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLine(this)"><i class="bi bi-x"></i></button></td></tr>';
        document.getElementById('lineTotal').textContent = '0';
        showToast('Đã tạo đề nghị mua hàng thành công', 'success');
        loadData();
    })
    .catch(function(err) { showToast(err.message, 'error'); })
    .finally(function() { btn.disabled = false; btn.innerHTML = 'Gửi đề nghị'; });
});

document.getElementById('linesBody').addEventListener('input', function(e) {
    if (e.target.classList.contains('qty-input') || e.target.classList.contains('price-input')) {
        updateLineTotal();
    }
});

document.getElementById('filterSearch').addEventListener('keyup', applyFilters);
document.getElementById('filterStatus').addEventListener('change', applyFilters);

loadData();
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
