<?php // Màn hình: Lập phiếu nhập kho
// API: GET /api/inventory/receipts, GET /api/inventory/issue/items, POST /api/inventory/receive
// Nghiệp vụ: Nhập kho — Nợ 152/153/155/156 (tùy loại VT)/Có 331 (hoặc 111/112)
// Xác định đơn giá: Giá nhập kho = Đơn giá + Chi phí vận chuyển/phụ phí phân bổ
// Rủi ro: Nhập sai giá làm sai giá vốn hàng bán (TK 632) khi xuất sau này
$title = 'Nhập kho'; $activeMenu = 'receipt'; ob_start(); ?>
<div class="toolbar">
    <h5>Nhập kho</h5>
    <div>
        <select class="form-select form-select-sm d-inline-block w-auto me-2" id="filterItem">
            <option value="">-- Tất cả vật tư --</option>
        </select>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#receiptModal"><i class="bi bi-plus-lg"></i> Tạo phiếu nhập</button>
    </div>
</div>

<div class="card-table">
    <div class="card-header-x">
        <input type="text" id="searchInput" placeholder="🔍 Tìm kiếm..." onkeyup="loadData()">
        <span class="stats ms-auto" id="recordCount">0 bản ghi</span>
    </div>
    <table class="table table-hover">
        <thead><tr>
            <th>Mã chứng từ</th>
            <th>Diễn giải</th>
            <th>Trạng thái</th>
            <th>Ngày tạo</th>
        </tr></thead>
        <tbody id="dataBody"></tbody>
    </table>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<form id="receiptForm">
<div class="modal-header"><h5 class="modal-title">Phiếu nhập kho</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3">
        <label>Vật tư / Hàng hóa</label>
        <select class="form-select" id="itemId" required><option value="">-- Chọn --</option></select>
    </div>
    <div class="mb-3">
        <label>Số lượng</label>
        <input type="number" class="form-control" id="qty" step="0.01" min="0.01" required>
    </div>
    <div class="mb-3">
        <label>Đơn giá</label>
        <input type="number" class="form-control" id="unitPrice" step="1" min="1" required>
    </div>
    <div class="mb-3">
        <label>Chi phí vận chuyển / phụ phí</label>
        <input type="number" class="form-control" id="addonCost" step="1" min="0" value="0">
    </div>
    <div class="mb-3">
        <label>Số chứng từ</label>
        <input type="text" class="form-control" id="reference" placeholder="NK-2026-...">
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-primary">Lưu & Nhập kho</button>
</div>
</form>
</div></div>
</div>

<script>
function loadData() {
    $.get('/api/inventory/receipts', function(data) {
        var tbody = $('#dataBody'), search = $('#searchInput').val().toLowerCase();
        tbody.empty();
        var filtered = data.filter(function(r) {
            return !search || r.reference.toLowerCase().includes(search) || r.description.toLowerCase().includes(search);
        });
        $('#recordCount').text(filtered.length + ' bản ghi');
        if (filtered.length === 0) {
            tbody.append('<tr><td colspan="4" class="text-center text-muted py-4">Chưa có phiếu nhập nào</td></tr>');
            return;
        }
        filtered.forEach(function(r) {
            tbody.append('<tr><td>' + esc(r.reference) + '</td><td>' + esc(r.description) + '</td><td><span class="badge-status badge-active">' + esc(r.status) + '</span></td><td>' + esc(r.created_at) + '</td></tr>');
        });
    });
}

$.get('/api/inventory/issue/items', function(items) {
    items.forEach(function(it) {
        $('#itemId').append('<option value="' + esc(it.id) + '">' + esc(it.code) + ' - ' + esc(it.name) + '</option>');
    });
});

// Submit phiếu nhập kho — POST /api/inventory/receive
// Validate frontend: kiểm tra item_id, qty > 0, unit_price > 0
// Phụ phí (vận chuyển) được cộng vào giá vốn hàng nhập kho
$('#receiptForm').submit(function(e) {
    e.preventDefault();
    var data = {
        item_id: $('#itemId').val(),
        qty: parseFloat($('#qty').val()),
        unit_price: parseFloat($('#unitPrice').val()),
        addon_costs: [],
        reference: $('#reference').val() || undefined
    };
    var addon = parseFloat($('#addonCost').val()) || 0;
    if (addon > 0) data.addon_costs.push({ description: 'Phụ phí', amount: addon });
    if (!data.item_id) { showToast('Vui lòng chọn vật tư', 'error'); return; }
    if (!data.qty || data.qty <= 0) { showToast('Số lượng phải lớn hơn 0', 'error'); return; }
    if (!data.unit_price || data.unit_price <= 0) { showToast('Đơn giá phải lớn hơn 0', 'error'); return; }
    $.ajax({
        url: '/api/inventory/receive',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(data),
        success: function() {
            $('#receiptModal').modal('hide');
            $('#receiptForm')[0].reset();
            $('#addonCost').val(0);
            showToast('Nhập kho thành công', 'success');
            loadData();
        },
        error: function(xhr) {
            var msg = 'Lỗi nhập kho';
            try { msg = JSON.parse(xhr.responseText).error; } catch(e) {}
            showToast(msg, 'error');
        }
    });
});

$(document).ready(function() { loadData(); });
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
